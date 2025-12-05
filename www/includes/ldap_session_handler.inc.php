<?php

/**
 * LDAP Session Handler
 *
 * Custom PHP session handler that stores session data in LDAP when USE_LDAP_AS_DB is enabled.
 * Falls back to default file-based sessions when disabled or if LDAP entry doesn't exist.
 *
 * Benefits of LDAP session storage:
 * - Session persistence across container restarts
 * - Horizontal scaling (multiple containers share sessions)
 * - Centralized session management
 * - Automatic garbage collection via LDAP cleanup
 *
 * Session data is stored in the cn=luminary,ou=applications LDAP entry using the format:
 * description: session:SESSION_ID:BASE64(SERIALIZED_DATA):EXPIRY_TIMESTAMP
 *
 * For performance, sessions are also cached in /tmp for faster reads.
 */

if (!defined('LDAP_USER_MANAGER')) {
  die('Direct access not permitted');
}

class LDAPSessionHandler implements SessionHandlerInterface {

  /**
   * @var resource LDAP connection resource
   */
  private $ldap_connection;

  /**
   * @var bool Whether LDAP storage is enabled and available
   */
  private $use_ldap = false;

  /**
   * @var bool Whether we've checked LDAP availability
   */
  private $ldap_checked = false;

  /**
   * @var int Session lifetime in seconds
   */
  private $lifetime;

  /**
   * @var bool Debug logging enabled
   */
  private $debug;

  /**
   * @var bool Whether we own the LDAP connection (and should close it)
   */
  private $own_ldap_connection = false;

  /**
   * Constructor
   *
   * @param resource $ldap_connection LDAP connection resource (optional)
   */
  public function __construct($ldap_connection = null) {
    global $SESSION_TIMEOUT, $SESSION_DEBUG;

    $this->lifetime = $SESSION_TIMEOUT * 60; // Convert minutes to seconds
    $this->debug = ($SESSION_DEBUG == TRUE);
    $this->ldap_connection = $ldap_connection;

    // LDAP availability will be checked lazily on first read/write
    // This allows ldap_functions.inc.php to be loaded after session initialisation
  }

  /**
   * Destructor - close LDAP connection if we opened it
   */
  public function __destruct() {
    if ($this->own_ldap_connection && $this->ldap_connection !== null) {
      @ldap_close($this->ldap_connection);
    }
  }

  /**
   * Check if LDAP storage is available (lazy initialisation)
   *
   * @return void
   */
  private function check_ldap_availability() {
    global $USE_LDAP_AS_DB, $log_prefix;

    if ($this->ldap_checked) {
      return; // Already checked
    }

    $this->ldap_checked = true;

    if ($this->debug) {
      $use_ldap_value = isset($USE_LDAP_AS_DB) ? ($USE_LDAP_AS_DB === TRUE ? 'TRUE' : var_export($USE_LDAP_AS_DB, true)) : 'NOT SET';
      error_log("$log_prefix Session Handler: check_ldap_availability() called, USE_LDAP_AS_DB = $use_ldap_value",0);
    }

    // If LDAP storage not enabled, stick with /tmp
    if ($USE_LDAP_AS_DB != TRUE) {
      return;
    }

    // If we don't have a connection, try to open one
    if ($this->ldap_connection === null) {
      if (!function_exists('open_ldap_connection')) {
        if ($this->debug) {
          error_log("$log_prefix Session Handler: open_ldap_connection() not available yet",0);
        }
        return;
      }

      $this->ldap_connection = @open_ldap_connection();
      if ($this->ldap_connection !== false) {
        $this->own_ldap_connection = true;
        if ($this->debug) {
          error_log("$log_prefix Session Handler: Opened LDAP connection for sessions",0);
        }
      } else {
        if ($this->debug) {
          error_log("$log_prefix Session Handler: Failed to open LDAP connection",0);
        }
        return;
      }
    }

    // Check if LDAP entry exists
    if (!function_exists('ldap_app_data_entry_exists')) {
      if ($this->debug) {
        error_log("$log_prefix Session Handler: ldap_app_data_entry_exists() not available yet",0);
      }
      return;
    }

    if (ldap_app_data_entry_exists($this->ldap_connection)) {
      $this->use_ldap = true;
      if ($this->debug) {
        error_log("$log_prefix Session Handler: LDAP storage enabled and available",0);
      }
    } else {
      if ($this->debug) {
        error_log("$log_prefix Session Handler: LDAP storage enabled but entry doesn't exist, using /tmp fallback",0);
      }
    }
  }

  /**
   * Open session
   *
   * @param string $save_path Session save path (ignored when using LDAP)
   * @param string $session_name Session name
   * @return bool TRUE on success
   */
  public function open(string $path, string $name): bool {
    global $log_prefix;

    if ($this->debug) {
      $storage = $this->use_ldap ? 'LDAP' : '/tmp';
      error_log("$log_prefix Session Handler: Opening session (storage: $storage, name: $name)",0);
    }

    return true;
  }

  /**
   * Close session
   *
   * @return bool TRUE on success
   */
  public function close(): bool {
    global $log_prefix;

    if ($this->debug) {
      error_log("$log_prefix Session Handler: Closing session",0);
    }

    return true;
  }

  /**
   * Read session data
   *
   * @param string $session_id Session ID
   * @return string Session data (empty string if not found)
   */
  public function read(string $id): string|false {
    $session_id = $id;
    global $log_prefix;

    // Check LDAP availability on first read
    $this->check_ldap_availability();

    // Sanitize session ID
    $session_id = preg_replace('/[^a-zA-Z0-9-]/', '', $session_id);

    // Try /tmp cache first (fast path)
    $cache_file = "/tmp/session_$session_id";
    if (file_exists($cache_file)) {
      $cache_data = @file_get_contents($cache_file);
      if ($cache_data !== false) {
        $parts = explode(':', $cache_data, 2);
        if (count($parts) == 2) {
          list($expiry, $data) = $parts;
          if (time() < $expiry) {
            if ($this->debug) {
              error_log("$log_prefix Session Handler: Read session $session_id from /tmp cache",0);
            }
            return base64_decode($data);
          }
        }
      }
    }

    // Try LDAP if enabled
    if ($this->use_ldap) {
      $entries = ldap_app_data_get_entries($this->ldap_connection, "session:");

      if ($entries !== FALSE) {
        $prefix = "session:$session_id:";

        foreach ($entries as $entry) {
          if (strpos($entry, $prefix) === 0) {
            // Format: session:SESSION_ID:USERNAME:BASE64(DATA):EXPIRY
            $parts = explode(':', $entry, 5);

            if (count($parts) == 5) {
              $data = $parts[3];
              $expiry = $parts[4];

              // Check if expired
              if (time() < $expiry) {
                $decoded_data = base64_decode($data);

                // Update /tmp cache for faster future reads
                @file_put_contents($cache_file, "$expiry:$data");

                if ($this->debug) {
                  error_log("$log_prefix Session Handler: Read session $session_id from LDAP",0);
                }

                return $decoded_data;
              } else {
                // Session expired, clean it up
                if ($this->debug) {
                  error_log("$log_prefix Session Handler: Session $session_id expired, removing",0);
                }
                ldap_app_data_remove_entry($this->ldap_connection, $entry);
              }
            }
          }
        }
      }
    }

    if ($this->debug) {
      error_log("$log_prefix Session Handler: Session $session_id not found",0);
    }

    return '';
  }

  /**
   * Write session data
   *
   * @param string $session_id Session ID
   * @param string $session_data Session data
   * @return bool TRUE on success
   */
  public function write(string $id, string $data): bool {
    $session_id = $id;
    $session_data = $data;
    global $log_prefix;

    // Check LDAP availability on first write
    $this->check_ldap_availability();

    // Sanitize session ID
    $session_id = preg_replace('/[^a-zA-Z0-9-]/', '', $session_id);

    // Calculate expiry timestamp
    $expiry = time() + $this->lifetime;

    // Encode session data
    $encoded_data = base64_encode($session_data);

    // Always write to /tmp cache (for fast reads)
    $cache_file = "/tmp/session_$session_id";
    @file_put_contents($cache_file, "$expiry:$encoded_data");

    // Write to LDAP if enabled
    if ($this->use_ldap) {
      // First, remove any existing session entry for this ID
      $entries = ldap_app_data_get_entries($this->ldap_connection, "session:");
      if ($entries !== FALSE) {
        $prefix = "session:$session_id:";
        foreach ($entries as $entry) {
          if (strpos($entry, $prefix) === 0) {
            ldap_app_data_remove_entry($this->ldap_connection, $entry);
          }
        }
      }

      // Extract username from global scope if available
      global $USER_ID;
      $username = isset($USER_ID) ? $USER_ID : 'anonymous';

      // Add new session entry with username
      // Format: session:SESSION_ID:USERNAME:BASE64(DATA):EXPIRY
      $session_entry = "session:$session_id:$username:$encoded_data:$expiry";
      $result = ldap_app_data_add_entry($this->ldap_connection, $session_entry);

      if ($result !== FALSE) {
        if ($this->debug) {
          error_log("$log_prefix Session Handler: Wrote session $session_id to LDAP",0);
        }
        return true;
      } else {
        error_log("$log_prefix Session Handler: Failed to write session $session_id to LDAP",0);
        // Still return true since /tmp cache was written
        return true;
      }
    } else {
      // Using /tmp storage only
      if ($this->debug) {
        error_log("$log_prefix Session Handler: Wrote session $session_id to /tmp",0);
      }
      return true;
    }
  }

  /**
   * Destroy session
   *
   * @param string $session_id Session ID
   * @return bool TRUE on success
   */
  public function destroy(string $id): bool {
    $session_id = $id;
    global $log_prefix;

    // Sanitize session ID
    $session_id = preg_replace('/[^a-zA-Z0-9-]/', '', $session_id);

    // Remove from /tmp cache
    $cache_file = "/tmp/session_$session_id";
    @unlink($cache_file);

    // Remove from LDAP if enabled
    if ($this->use_ldap) {
      $entries = ldap_app_data_get_entries($this->ldap_connection, "session:");
      if ($entries !== FALSE) {
        $prefix = "session:$session_id:";
        foreach ($entries as $entry) {
          if (strpos($entry, $prefix) === 0) {
            ldap_app_data_remove_entry($this->ldap_connection, $entry);
            if ($this->debug) {
              error_log("$log_prefix Session Handler: Destroyed session $session_id in LDAP",0);
            }
          }
        }
      }
    }

    if ($this->debug) {
      error_log("$log_prefix Session Handler: Destroyed session $session_id",0);
    }

    return true;
  }

  /**
   * Garbage collection - remove expired sessions
   *
   * @param int $maxlifetime Maximum session lifetime
   * @return int|false Number of sessions deleted, or false on failure
   */
  public function gc(int $max_lifetime): int|false {
    $maxlifetime = $max_lifetime;
    global $log_prefix;

    $now = time();
    $deleted = 0;

    // Clean up /tmp cache
    $tmp_files = glob('/tmp/session_*');
    if ($tmp_files !== false) {
      foreach ($tmp_files as $file) {
        $cache_data = @file_get_contents($file);
        if ($cache_data !== false) {
          $parts = explode(':', $cache_data, 2);
          if (count($parts) == 2) {
            $expiry = $parts[0];
            if ($now >= $expiry) {
              @unlink($file);
              $deleted++;
            }
          }
        }
      }
    }

    // Clean up LDAP if enabled
    if ($this->use_ldap) {
      $entries = ldap_app_data_get_entries($this->ldap_connection, "session:");
      if ($entries !== FALSE) {
        foreach ($entries as $entry) {
          // Format: session:SESSION_ID:USERNAME:BASE64(DATA):EXPIRY
          $parts = explode(':', $entry, 5);

          if (count($parts) == 5) {
            $expiry = $parts[4];

            if ($now >= $expiry) {
              ldap_app_data_remove_entry($this->ldap_connection, $entry);
              $deleted++;
            }
          }
        }
      }
    }

    if ($this->debug && $deleted > 0) {
      error_log("$log_prefix Session Handler: Garbage collection removed $deleted expired sessions",0);
    }

    return $deleted;
  }
}

/**
 * Initialise LDAP session handler
 *
 * Call this function after LDAP connection is established to set up
 * the custom session handler.
 *
 * @param resource $ldap_connection LDAP connection resource
 * @return bool TRUE if session handler was set up successfully
 */
function ldap_session_init($ldap_connection = null) {
  global $log_prefix, $SESSION_DEBUG;

  // Create session handler
  $handler = new LDAPSessionHandler($ldap_connection);

  // Register as the session handler
  $result = session_set_save_handler($handler, true);

  if (!$result) {
    error_log("$log_prefix Session Handler: Failed to register custom session handler",0);
    return false;
  }

  // Start the session
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
    if ($SESSION_DEBUG == TRUE) {
      error_log("$log_prefix Session Handler: Session started with custom handler",0);
    }
  }

  return true;
}
