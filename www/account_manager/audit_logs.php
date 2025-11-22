<?php

set_include_path( ".:" . __DIR__ . "/../includes/");

include_once "web_functions.inc.php";
include_once "ldap_functions.inc.php";
include_once "totp_functions.inc.php";
include_once "audit_functions.inc.php";
include_once "module_functions.inc.php";
set_page_access("admin");

// Handle CSV export BEFORE any output
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  $filter = isset($_GET['filter']) ? $_GET['filter'] : '';
  $result_filter = isset($_GET['result']) ? $_GET['result'] : 'all';

  $csv_content = audit_export_csv($filter, $result_filter);

  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="audit_log_' . date('Y-m-d_His') . '.csv"');
  header('Pragma: no-cache');
  header('Expires: 0');

  echo $csv_content;
  exit;
}

render_header("$ORGANISATION_NAME account manager");
render_submenu();

// Handle cleanup action
if (isset($_POST['cleanup']) && $_POST['cleanup'] === '1') {
  $removed_count = audit_cleanup_old_entries();
  if ($removed_count > 0) {
    render_alert_banner("Removed $removed_count old audit log entries.");
  } else {
    render_alert_banner("No old entries to remove.", "info", 4000);
  }
}

// Get pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Get filter parameters
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';
$result_filter = isset($_GET['result']) ? $_GET['result'] : 'all';

// Check if audit logging is enabled
if (!$AUDIT_ENABLED) {
  ?>
  <div class="container">
    <h2><i class="bi bi-journal-text"></i> Audit logs</h2>

    <div class="alert alert-warning">
      <h5><i class="bi bi-exclamation-triangle"></i> Audit logging disabled</h5>
      <p>Audit logging is currently disabled. To enable audit logging, set the following environment variable:</p>
      <p><code>AUDIT_ENABLED=TRUE</code></p>
      <p><strong>For Docker deployments (recommended):</strong></p>
      <p class="mb-2">Logs will be written to STDOUT by default. View with <code>docker logs luminary</code></p>
      <p><strong>For file-based logging:</strong></p>
      <p class="mb-0">Set <code>AUDIT_LOG_FILE=/path/to/audit.log</code> to write to a file instead</p>
    </div>

    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">What gets logged?</h5>
      </div>
      <div class="card-body">
        <p>When audit logging is enabled, the following events are recorded:</p>
        <ul>
          <li><strong>User Management:</strong> User creation, deletion, attribute changes</li>
          <li><strong>Group Management:</strong> Group creation, deletion, membership changes</li>
          <li><strong>MFA Events:</strong> MFA enrolment, verification, backup code generation</li>
          <li><strong>Authentication:</strong> Login attempts (success and failure), logout</li>
          <li><strong>Security:</strong> Password changes, account lockouts, permission changes</li>
        </ul>
        <p class="mb-0">Each log entry includes timestamp, actor (user), IP address, action, target, result, and details.</p>
      </div>
    </div>
  </div>
  <?php
  render_footer();
  exit;
}

// Check if using STDOUT (Docker mode)
$using_stdout = audit_is_using_stdout();

// Get audit log entries (empty if using STDOUT)
$entries = audit_read_log($per_page, $offset, $filter, $result_filter);
$total_entries = audit_count_entries($filter, $result_filter);
$total_pages = ceil($total_entries / $per_page);

?>

<div class="container-fluid">

  <h2><i class="bi bi-journal-text"></i> Audit logs</h2>
  <p class="text-muted">Security and administrative event logging</p>

  <?php if ($using_stdout) { ?>
  <!-- Docker STDOUT Mode Info -->
  <div class="alert alert-info">
    <h5><i class="bi bi-info-circle"></i> Docker logging mode</h5>
    <p>Audit logs are being written to <strong>STDOUT</strong> for Docker log management.</p>
    <p class="mb-2"><strong>To view audit logs:</strong></p>
    <pre class="mb-2" style="background: #f8f9fa; padding: 10px; border-radius: 5px;"><code># View all container logs (including audit entries)
docker logs luminary

# Follow logs in real-time
docker logs -f luminary

# Show only recent audit entries (JSON format)
docker logs luminary 2>&1 | grep -E '^\{.*"action"'

# Export logs to file
docker logs luminary > luminary-audit.log 2>&1</code></pre>
    <p class="mb-0">
      <strong>Note:</strong> Historical audit logs cannot be displayed in this interface when using STDOUT mode.
      To enable the web-based audit log viewer, set <code>AUDIT_LOG_FILE</code> to a file path (e.g., <code>/var/log/luminary/audit.log</code>).
    </p>
  </div>
  <?php } ?>

  <!-- Stats and Actions -->
  <div class="row mb-3">
    <div class="col-md-6">
      <div class="card">
        <div class="card-body">
          <h6 class="card-title">Log statistics</h6>
          <?php if ($using_stdout) { ?>
            <p class="mb-1"><strong>Mode:</strong> <span class="badge bg-info">Docker STDOUT</span></p>
            <p class="mb-1"><strong>View Logs:</strong> <code>docker logs luminary</code></p>
            <p class="mb-0"><strong>Retention:</strong> Managed by Docker</p>
          <?php } else { ?>
            <p class="mb-1"><strong>Total Entries:</strong> <?php echo number_format($total_entries); ?></p>
            <p class="mb-1"><strong>Log File:</strong> <code><?php echo htmlspecialchars($AUDIT_LOG_FILE); ?></code></p>
            <p class="mb-0"><strong>Retention:</strong> <?php echo $AUDIT_LOG_RETENTION_DAYS; ?> days</p>
          <?php } ?>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card">
        <div class="card-body">
          <h6 class="card-title">Actions</h6>
          <?php if ($using_stdout) { ?>
            <p class="text-muted mb-0"><small>File operations not available in STDOUT mode. Use <code>docker logs</code> commands shown above.</small></p>
          <?php } else { ?>
            <form method="post" action="" style="display: inline;">
              <input type="hidden" name="cleanup" value="1">
              <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Remove all entries older than <?php echo $AUDIT_LOG_RETENTION_DAYS; ?> days?');">
                <i class="bi bi-trash"></i> Clean Up Old Entries
              </button>
            </form>
            <a href="?export=csv<?php
              if (!empty($filter)) echo '&filter=' . urlencode($filter);
              if ($result_filter !== 'all') echo '&result=' . urlencode($result_filter);
            ?>" class="btn btn-primary btn-sm">
              <i class="bi bi-download"></i> Export to CSV
            </a>
          <?php } ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Search and Filters -->
  <?php if (!$using_stdout) { ?>
  <div class="card mb-3">
    <div class="card-body">
      <form method="get" action="" class="row g-3">
        <div class="col-md-6">
          <label for="filter" class="form-label">Search</label>
          <input type="text" class="form-control" id="filter" name="filter"
                 value="<?php echo htmlspecialchars($filter); ?>"
                 placeholder="Search actor, action, target, or details...">
        </div>
        <div class="col-md-3">
          <label for="result" class="form-label">Result Filter</label>
          <select class="form-select" id="result" name="result">
            <option value="all" <?php if ($result_filter === 'all') echo 'selected'; ?>>All Results</option>
            <option value="success" <?php if ($result_filter === 'success') echo 'selected'; ?>>Success Only</option>
            <option value="failure" <?php if ($result_filter === 'failure') echo 'selected'; ?>>Failure Only</option>
            <option value="warning" <?php if ($result_filter === 'warning') echo 'selected'; ?>>Warning Only</option>
          </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button type="submit" class="btn btn-primary me-2">
            <i class="bi bi-search"></i> Filter
          </button>
          <a href="?" class="btn btn-secondary">
            <i class="bi bi-x-circle"></i> Clear
          </a>
        </div>
      </form>
    </div>
  </div>
  <?php } ?>

  <?php if (empty($entries)) { ?>
    <?php if (!$using_stdout) { ?>
      <div class="alert alert-info">
        <i class="bi bi-info-circle"></i>
        <?php if (!empty($filter) || $result_filter !== 'all') { ?>
          No audit log entries match your search criteria.
        <?php } else { ?>
          No audit log entries yet. Events will appear here as they occur.
        <?php } ?>
      </div>
    <?php } ?>
  <?php } else { ?>

    <!-- Audit Log Table -->
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">
          Audit log entries
          <?php if (!empty($filter) || $result_filter !== 'all') { ?>
            <span class="badge bg-primary"><?php echo number_format($total_entries); ?> matching</span>
          <?php } ?>
        </h5>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-striped mb-0">
          <thead>
            <tr>
              <th style="width: 12%;">Timestamp</th>
              <th style="width: 12%;">Actor</th>
              <th style="width: 12%;">IP Address</th>
              <th style="width: 15%;">Action</th>
              <th style="width: 15%;">Target</th>
              <th style="width: 8%;">Result</th>
              <th>Details</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($entries as $entry) {
              $result = $entry['result'] ?? 'unknown';
              $result_badge_class = 'bg-secondary';

              if ($result === 'success') {
                $result_badge_class = 'bg-success';
              } elseif ($result === 'failure') {
                $result_badge_class = 'bg-danger';
              } elseif ($result === 'warning') {
                $result_badge_class = 'bg-warning text-dark';
              }
            ?>
              <tr>
                <td class="text-nowrap"><small><?php echo htmlspecialchars($entry['timestamp'] ?? ''); ?></small></td>
                <td><code><?php echo htmlspecialchars($entry['actor'] ?? ''); ?></code></td>
                <td><small><?php echo htmlspecialchars($entry['ip'] ?? ''); ?></small></td>
                <td><code><?php echo htmlspecialchars($entry['action'] ?? ''); ?></code></td>
                <td><?php echo htmlspecialchars($entry['target'] ?? ''); ?></td>
                <td>
                  <span class="badge <?php echo $result_badge_class; ?>">
                    <?php echo htmlspecialchars($result); ?>
                  </span>
                </td>
                <td><small><?php echo htmlspecialchars($entry['details'] ?? ''); ?></small></td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1) { ?>
      <nav aria-label="Audit log pagination" class="mt-3">
        <ul class="pagination justify-content-center">
          <!-- Previous -->
          <li class="page-item <?php if ($page <= 1) echo 'disabled'; ?>">
            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php
              if (!empty($filter)) echo '&filter=' . urlencode($filter);
              if ($result_filter !== 'all') echo '&result=' . urlencode($result_filter);
            ?>">Previous</a>
          </li>

          <!-- Page numbers -->
          <?php
          $start_page = max(1, $page - 5);
          $end_page = min($total_pages, $page + 5);

          if ($start_page > 1) {
            echo '<li class="page-item"><a class="page-link" href="?page=1';
            if (!empty($filter)) echo '&filter=' . urlencode($filter);
            if ($result_filter !== 'all') echo '&result=' . urlencode($result_filter);
            echo '">1</a></li>';
            if ($start_page > 2) {
              echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
          }

          for ($i = $start_page; $i <= $end_page; $i++) {
            $active = ($i == $page) ? 'active' : '';
            echo '<li class="page-item ' . $active . '"><a class="page-link" href="?page=' . $i;
            if (!empty($filter)) echo '&filter=' . urlencode($filter);
            if ($result_filter !== 'all') echo '&result=' . urlencode($result_filter);
            echo '">' . $i . '</a></li>';
          }

          if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) {
              echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages;
            if (!empty($filter)) echo '&filter=' . urlencode($filter);
            if ($result_filter !== 'all') echo '&result=' . urlencode($result_filter);
            echo '">' . $total_pages . '</a></li>';
          }
          ?>

          <!-- Next -->
          <li class="page-item <?php if ($page >= $total_pages) echo 'disabled'; ?>">
            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php
              if (!empty($filter)) echo '&filter=' . urlencode($filter);
              if ($result_filter !== 'all') echo '&result=' . urlencode($result_filter);
            ?>">Next</a>
          </li>
        </ul>
      </nav>

      <p class="text-center text-muted">
        Page <?php echo $page; ?> of <?php echo number_format($total_pages); ?>
        (<?php echo number_format($total_entries); ?> total entries)
      </p>
    <?php } ?>

  <?php } ?>

</div>

<?php
render_footer();
?>
