# Audit Logging Feature

The audit logging feature provides comprehensive security event tracking and administrative action logging for compliance, security monitoring, and forensic analysis.

## Features

- **Comprehensive Event Tracking** - Logs all administrative actions, security events, and user activities
- **Structured JSON Logging** - Machine-readable log format for integration with log aggregation tools
- **Dual Output Modes** - Docker STDOUT mode or traditional file-based logging
- **Search and Filter** - Web-based interface for searching and filtering audit logs
- **CSV Export** - Export audit logs for analysis in spreadsheet applications
- **Automatic Retention Management** - Configurable retention period with automatic cleanup
- **IP Address Tracking** - Records client IP addresses (proxy-aware)
- **Syslog Integration** - Redundant logging to system syslog for reliability

## Configuration

### Docker Deployment (Recommended)

```bash
docker run -e AUDIT_ENABLED=true \
           -e AUDIT_LOG_FILE=stdout \
           -e AUDIT_LOG_RETENTION_DAYS=90 \
           luminary
```

**Viewing logs in Docker:**

```bash
# View all container logs (including audit entries)
docker logs luminary

# Follow logs in real-time
docker logs -f luminary

# Show only audit entries (JSON format)
docker logs luminary 2>&1 | grep -E '^\{.*"action"'

# Export logs to file
docker logs luminary > luminary-audit.log 2>&1

# View last 100 lines
docker logs --tail 100 luminary
```

### File-Based Deployment

```bash
docker run -e AUDIT_ENABLED=true \
           -e AUDIT_LOG_FILE=/var/log/luminary/audit.log \
           -e AUDIT_LOG_RETENTION_DAYS=90 \
           -v /var/log/luminary:/var/log/luminary \
           luminary
```

**Important:** Ensure the log directory is writable by the web server user (www-data).

### Configuration Options

| Variable | Default | Description |
|----------|---------|-------------|
| `AUDIT_ENABLED` | `FALSE` | Enable audit logging |
| `AUDIT_LOG_FILE` | `stdout` | Log destination: `stdout`, `stderr`, or file path |
| `AUDIT_LOG_RETENTION_DAYS` | `90` | Days to retain logs (file mode only) |

## What Gets Logged?

### Authentication Events

- **login_success** - Successful user login
- **login_failure** - Failed login attempt (invalid credentials)
- **logout** - User logout (manual or session timeout)
- **mfa_verify_success** - Successful TOTP code verification during login
- **mfa_verify_failure** - Failed TOTP code verification

### User Management

- **user_created** - New user account created by admin
- **user_create_failure** - Failed user creation attempt
- **user_updated** - User account details modified by admin
- **user_update_failure** - Failed user update attempt
- **user_deleted** - User account deleted by admin
- **user_delete_failure** - Failed user deletion attempt
- **user_added_to_group** - User added to group by admin
- **user_removed_from_group** - User removed from group by admin

### Group Management

- **group_created** - New group created by admin
- **group_create_failure** - Failed group creation attempt
- **group_updated** - Group attributes modified by admin
- **group_update_failure** - Failed group update attempt
- **group_deleted** - Group deleted by admin
- **group_delete_failure** - Failed group deletion attempt
- **group_member_added** - User added to group
- **group_member_removed** - User removed from group
- **group_mfa_updated** - Group MFA settings changed by admin
- **group_mfa_update_failure** - Failed MFA settings update

### MFA Events

- **mfa_enrolled** - User enrolled in multi-factor authentication
- **mfa_disabled** - User disabled their MFA
- **mfa_backup_codes_regenerated** - Admin regenerated backup codes for user
- **mfa_backup_codes_regen_failure** - Failed backup code regeneration

### Account Lifecycle

- **account_expiry_set** - Admin set account expiration date
- **account_expiry_set_failure** - Failed to set expiration
- **account_expiry_removed** - Admin removed account expiration
- **account_expiry_remove_failure** - Failed to remove expiration
- **account_unlocked** - Admin unlocked locked account (ppolicy)
- **account_unlock_failure** - Failed to unlock account

## Log Format

### JSON Structure

Each log entry is a JSON object on a single line (JSON Lines format):

```json
{
  "timestamp": "2025-01-19 14:32:45 UTC",
  "actor": "admin",
  "ip": "192.168.1.100",
  "action": "user_created",
  "target": "jsmith",
  "result": "success",
  "details": "User created with email: jsmith@example.com"
}
```

### Field Descriptions

- **timestamp** - ISO 8601 timestamp with timezone
- **actor** - Username of person performing the action (or "system")
- **ip** - Client IP address (handles proxy headers: X-Forwarded-For, X-Real-IP, CF-Connecting-IP)
- **action** - Action type (see "What Gets Logged?" above)
- **target** - Target of the action (username, group name, etc.)
- **result** - Outcome: `success`, `failure`, or `warning`
- **details** - Additional context and details about the action

## Using the Audit Log Viewer

### Web Interface (File Mode Only)

When using file-based logging, Luminary provides a web interface for viewing audit logs:

**Access:** Admin Menu → Audit Logs

**Features:**
- **Search** - Search across actor, action, target, and details fields
- **Filter by Result** - Show only success, failure, or warning events
- **Pagination** - Navigate through large log files (50 entries per page)
- **CSV Export** - Export filtered results to CSV
- **Log Cleanup** - Manually remove entries older than retention period

### Docker Mode

When using `AUDIT_LOG_FILE=stdout`, the web interface shows instructions for viewing logs via `docker logs` commands. Historical logs cannot be displayed in the web UI when using STDOUT mode.

## Integration with Log Aggregation Tools

### Splunk

```bash
# Configure Splunk forwarder to monitor Docker logs
[monitor:///var/lib/docker/containers/*/*.log]
sourcetype = docker:luminary
index = security

# Extract JSON fields
[docker:luminary]
INDEXED_EXTRACTIONS = json
KV_MODE = json
TIME_PREFIX = "timestamp"\s*:\s*"
TIME_FORMAT = %Y-%m-%d %H:%M:%S %Z
```

### ELK Stack (Elasticsearch, Logstash, Kibana)

**Logstash configuration:**

```ruby
input {
  docker {
    host => "unix:///var/run/docker.sock"
    type => "luminary-audit"
  }
}

filter {
  if [type] == "luminary-audit" {
    json {
      source => "message"
    }

    date {
      match => ["timestamp", "yyyy-MM-dd HH:mm:ss Z"]
      target => "@timestamp"
    }
  }
}

output {
  elasticsearch {
    hosts => ["localhost:9200"]
    index => "luminary-audit-%{+YYYY.MM.dd}"
  }
}
```

### Graylog

```bash
# Send Docker logs to Graylog
docker run --log-driver=gelf \
           --log-opt gelf-address=udp://graylog.example.com:12201 \
           -e AUDIT_ENABLED=true \
           luminary
```

## Security Considerations

### Log Protection

- **File permissions** - Audit log files should be readable only by root and the web server user
- **Directory permissions** - Log directory should have 0750 permissions
- **Rotation** - Use logrotate to prevent disk space exhaustion
- **Backup** - Regularly backup audit logs to tamper-proof storage

**Example permissions:**

```bash
chmod 0750 /var/log/luminary
chmod 0640 /var/log/luminary/audit.log
chown www-data:www-data /var/log/luminary/audit.log
```

### Retention Policy

- **Compliance** - Check regulatory requirements (GDPR, HIPAA, SOC 2, etc.)
- **Disk space** - Monitor disk usage, especially for high-activity environments
- **Backup before cleanup** - Archive old logs before automatic cleanup

### Log Tampering Protection

- **WORM storage** - Write audit logs to write-once-read-many storage
- **External logging** - Send logs to external syslog server or SIEM
- **File integrity monitoring** - Use AIDE or Tripwire to detect modifications
- **Digital signatures** - Consider signing log entries for non-repudiation

## Troubleshooting

### Logs not appearing in web interface

**Cause 1:** Using STDOUT mode

**Solution:** Historical logs cannot be displayed in web UI when using `AUDIT_LOG_FILE=stdout`. Use `docker logs luminary` to view logs.

---

**Cause 2:** Incorrect file permissions

**Solution:**

```bash
# Check file permissions
ls -la /var/log/luminary/audit.log

# Fix permissions
chown www-data:www-data /var/log/luminary/audit.log
chmod 0640 /var/log/luminary/audit.log
```

---

**Cause 3:** Log file path doesn't exist

**Solution:**

```bash
# Create log directory
mkdir -p /var/log/luminary
chown www-data:www-data /var/log/luminary
chmod 0750 /var/log/luminary
```

### High disk usage from audit logs

**Cause:** Large number of events in high-activity environment

**Solution 1 - Reduce retention period:**

```bash
docker run -e AUDIT_LOG_RETENTION_DAYS=30 luminary
```

**Solution 2 - Use logrotate:**

```conf
# /etc/logrotate.d/luminary
/var/log/luminary/audit.log {
    daily
    rotate 90
    compress
    delaycompress
    missingok
    notifempty
    create 0640 www-data www-data
}
```

**Solution 3 - Switch to STDOUT mode:**

```bash
docker run -e AUDIT_LOG_FILE=stdout luminary
```

Let Docker handle log rotation with `--log-opt max-size=10m --log-opt max-file=3`.

### CSV export not working

**Cause:** Using STDOUT mode

**Solution:** CSV export is only available in file mode. Set `AUDIT_LOG_FILE` to a file path to enable export.

### Missing IP addresses in logs

**Cause:** Proxy headers not being forwarded

**Solution:**

```nginx
# Nginx proxy configuration
location / {
    proxy_pass http://luminary:8080;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
}
```

### Duplicate log entries

**Cause:** Both audit_log() and syslog are being captured

**Explanation:** This is expected. Audit logs are written to both the configured destination (file/STDOUT) AND syslog for redundancy. If you're aggregating both sources, you'll see duplicates.

**Solution:** Filter by format - JSON entries are from audit log, plain text is from syslog.

## Best Practices

### 1. Enable from Day One

Enable audit logging from the start of deployment. Retroactive logging is impossible.

### 2. Monitor Critical Events

Set up alerts for:
- Multiple failed login attempts
- User/group deletions
- MFA disablement
- Account unlocks
- Permission escalations

### 3. Regular Review

- Weekly: Review failed authentication attempts
- Monthly: Review user/group changes
- Quarterly: Review MFA enrolment status changes

### 4. Secure Log Storage

- Store logs on separate partition/volume
- Use centralised logging (syslog, SIEM)
- Implement log immutability (WORM storage)
- Encrypt logs at rest and in transit

### 5. Retention Policy

Consider:
- Regulatory requirements (e.g., HIPAA: 6 years)
- Storage capacity
- Incident response needs (typically 90-365 days)

### 6. Test Log Recovery

Regularly test that you can:
- Access historical logs
- Search and filter effectively
- Export logs for analysis
- Restore from backups

## Example Queries

### Find all actions by specific user

```bash
# Docker STDOUT mode
docker logs luminary 2>&1 | grep '"actor":"admin"'

# File mode
grep '"actor":"admin"' /var/log/luminary/audit.log
```

### Find all failed login attempts

```bash
# Docker STDOUT mode
docker logs luminary 2>&1 | grep '"action":"login_failure"'

# File mode
grep '"action":"login_failure"' /var/log/luminary/audit.log
```

### Count events by action type

```bash
# Docker STDOUT mode
docker logs luminary 2>&1 | grep -E '^\{.*"action"' | \
  jq -r '.action' | sort | uniq -c | sort -rn

# File mode
cat /var/log/luminary/audit.log | jq -r '.action' | sort | uniq -c | sort -rn
```

### Export specific date range to CSV

Via web interface: Admin Menu → Audit Logs → Export to CSV

Or using jq:

```bash
cat /var/log/luminary/audit.log | \
  jq -r '["timestamp","actor","ip","action","target","result","details"],
         (. | [.timestamp,.actor,.ip,.action,.target,.result,.details]) | @csv'
```

## Compliance Mapping

### GDPR (General Data Protection Regulation)

- **Article 30** - Records of processing activities
- **Article 32** - Security of processing (audit trails)

**Luminary provides:** Comprehensive audit trail of all data access and modifications.

### HIPAA (Health Insurance Portability and Accountability Act)

- **§164.308(a)(1)(ii)(D)** - Information system activity review
- **§164.312(b)** - Audit controls

**Luminary provides:** Audit logging of all access to protected health information (if stored in LDAP).

### SOC 2 (Service Organization Control 2)

- **CC6.1** - Logical and physical access controls
- **CC6.2** - System operations
- **CC7.2** - System monitoring

**Luminary provides:** Complete audit trail for access control reviews and security monitoring.

### PCI DSS (Payment Card Industry Data Security Standard)

- **Requirement 10** - Track and monitor all access to network resources and cardholder data

**Luminary provides:** Detailed logging of administrative access to directory services.

## References

- [JSON Lines format](https://jsonlines.org/)
- [OpenLDAP Logging](https://www.openldap.org/doc/admin24/slapdconf2.html)
- [Docker logging drivers](https://docs.docker.com/config/containers/logging/configure/)
- [NIST SP 800-92 - Guide to Computer Security Log Management](https://csrc.nist.gov/publications/detail/sp/800-92/final)

## See Also

- [Password Policy Documentation](password_policy.md) - Password policy enforcement
- [Account Lifecycle Documentation](account_lifecycle.md) - Account expiration management
- [MFA Documentation](mfa.md) - Multi-factor authentication setup
- [Configuration Reference](configuration.md) - All available settings
