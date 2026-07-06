### Prompt 15.2 — Production Optimization & Monitoring

```
Production performance optimization and monitoring setup for Rovix AI Leads Tool.

PERFORMANCE OPTIMIZATION FOR PRODUCTION:

## 1. Enable OpCache (PHP)

cPanel → Select PHP Version → Options

Enable:
☑ opcache
☑ opcache.enable = On
☑ opcache.memory_consumption = 128
☑ opcache.max_accelerated_files = 10000
☑ opcache.revalidate_freq = 2

Or add to php.ini:

[opcache]
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
opcache.validate_timestamps=1

## 2. Database Optimization

Add indexes for better performance:

-- Conversations index
CREATE INDEX idx_conversations_account_updated ON conversations(account_id, updated_at DESC);
CREATE INDEX idx_conversations_contact ON conversations(contact_id);
CREATE INDEX idx_conversations_assigned ON conversations(assigned_agent_id);

-- Messages index
CREATE INDEX idx_messages_conversation_created ON messages(conversation_id, created_at DESC);
CREATE INDEX idx_messages_direction ON messages(direction);
CREATE INDEX idx_messages_status ON messages(status);

-- Jobs index
CREATE INDEX idx_jobs_status_priority ON jobs(status, priority DESC, created_at);
CREATE INDEX idx_jobs_locked ON jobs(locked_until);

-- Contacts index
CREATE INDEX idx_contacts_account_phone ON contacts(account_id, phone_normalized);
CREATE INDEX idx_contacts_account_created ON contacts(account_id, created_at DESC);

-- Broadcast recipients index
CREATE INDEX idx_broadcast_recipients_status ON broadcast_recipients(broadcast_id, status);
CREATE INDEX idx_broadcast_recipients_scheduled ON broadcast_recipients(scheduled_at);

-- Flow runs index
CREATE INDEX idx_flow_runs_contact_status ON flow_runs(contact_id, status);
CREATE INDEX idx_flow_runs_flow_status ON flow_runs(flow_id, status);

-- Activity logs index
CREATE INDEX idx_activity_logs_account_created ON activity_logs(account_id, created_at DESC);

## 3. Query Optimization

Enable slow query logging:

-- In MySQL
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1; -- Log queries > 1 second
SET GLOBAL slow_query_log_file = '/var/log/mysql/slow-query.log';

Review slow queries weekly:

-- Top 10 slowest queries
SELECT query_time, sql_text 
FROM mysql.slow_log 
ORDER BY query_time DESC 
LIMIT 10;

## 4. Cache Configuration

Enable CodeIgniter caching:

# In app/Config/Cache.php
public string $handler = 'file'; // or 'redis' if available

# Cache dashboard stats (5 minutes)
$cache = \Config\Services::cache();
$key = 'dashboard_stats_' . session('account_id');

if (!$stats = $cache->get($key)) {
    $stats = $this->calculateStats();
    $cache->save($key, $stats, 300); // 5 minutes
}

## 5. Image Optimization

Compress uploaded images:

# Add to ContactController, InboxController after upload

$image = \Config\Services::image()
    ->withFile($uploadPath)
    ->resize(1920, 1080, true, 'auto')
    ->save($uploadPath, 80); // 80% quality

## 6. CDN for Static Assets (Optional)

Move Tailwind CSS to local:

# Download Tailwind CSS
curl -o public/css/tailwind.min.css https://cdn.tailwindcss.com/...

# Update views
<link rel="stylesheet" href="<?= base_url('css/tailwind.min.css') ?>">

Or use a CDN with caching:
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.0/dist/tailwind.min.css">

## 7. Gzip Compression

Enable in .htaccess:

<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json
</IfModule>

## 8. Browser Caching

Add to .htaccess in public/:

<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>

---

MONITORING & LOGGING:

## 1. Application Logging

Configure log levels in .env:

# Production
CI_ENVIRONMENT = production

# Log only errors in production
logger.threshold = 4
# 1=Emergency, 2=Alert, 3=Critical, 4=Error, 5=Warning, 6=Notice, 7=Info, 8=Debug

## 2. Error Monitoring Script

Create monitoring/check-errors.sh:

#!/bin/bash

LOG_FILE="/home/username/public_html/crm/writable/logs/log-$(date +%Y-%m-%d).php"
ERROR_COUNT=$(grep -c "ERROR" $LOG_FILE 2>/dev/null || echo "0")

if [ $ERROR_COUNT -gt 10 ]; then
    echo "⚠️ High error count: $ERROR_COUNT errors found"
    # Send email alert
    mail -s "Rovix CRM Error Alert" admin@yourdomain.com <<< "Found $ERROR_COUNT errors in logs today"
fi

Add to cron (run every hour):
0 * * * * /home/username/monitoring/check-errors.sh

## 3. Queue Monitor

Create monitoring/check-queue.sh:

#!/bin/bash

DB_NAME="yourdomain_rovix"
DB_USER="yourdomain_rovix_user"
DB_PASS="your_password"

PENDING_COUNT=$(mysql -u $DB_USER -p$DB_PASS -D $DB_NAME -N -e "SELECT COUNT(*) FROM jobs WHERE status='pending'")

if [ $PENDING_COUNT -gt 1000 ]; then
    echo "⚠️ Queue backlog: $PENDING_COUNT pending jobs"
    mail -s "Rovix CRM Queue Alert" admin@yourdomain.com <<< "Queue has $PENDING_COUNT pending jobs"
fi

Add to cron (run every 10 minutes):
*/10 * * * * /home/username/monitoring/check-queue.sh

## 4. Disk Space Monitor

Create monitoring/check-disk.sh:

#!/bin/bash

DISK_USAGE=$(df -h /home/username | awk 'NR==2 {print $5}' | sed 's/%//')

if [ $DISK_USAGE -gt 80 ]; then
    echo "⚠️ Disk usage: ${DISK_USAGE}%"
    mail -s "Rovix CRM Disk Alert" admin@yourdomain.com <<< "Disk usage is at ${DISK_USAGE}%"
fi

Add to cron (run daily at 6 AM):
0 6 * * * /home/username/monitoring/check-disk.sh

## 5. Uptime Monitoring

Use external service (free options):
- UptimeRobot: https://uptimerobot.com
- Pingdom: https://www.pingdom.com
- StatusCake: https://www.statuscake.com

Monitor:
- https://yourdomain.com (every 5 minutes)
- Alert if down for > 2 minutes

## 6. Application Health Check Endpoint

Create app/Controllers/HealthController.php:

<?php
namespace App\Controllers;

class HealthController extends BaseController
{
    public function check()
    {
        $health = [
            'status' => 'ok',
            'timestamp' => date('Y-m-d H:i:s'),
            'checks' => []
        ];

        // Database check
        try {
            $db = \Config\Database::connect();
            $db->query('SELECT 1');
            $health['checks']['database'] = 'ok';
        } catch (\Exception $e) {
            $health['status'] = 'error';
            $health['checks']['database'] = 'failed';
        }

        // Writable directory check
        if (is_writable(WRITEPATH)) {
            $health['checks']['writable'] = 'ok';
        } else {
            $health['status'] = 'error';
            $health['checks']['writable'] = 'failed';
        }

        // Queue check
        $jobModel = new \App\Models\JobModel();
        $pendingJobs = $jobModel->where('status', 'pending')->countAllResults();
        $health['checks']['queue_pending'] = $pendingJobs;

        if ($pendingJobs > 5000) {
            $health['status'] = 'warning';
        }

        return $this->response->setJSON($health);
    }
}

Add route:
GET /health → HealthController::check

Monitor this endpoint:
curl https://yourdomain.com/health

## 7. Custom Monitoring Dashboard (Optional)

Create simple stats page for admins:

app/Controllers/MonitorController.php:

public function stats()
{
    if (session('role') !== 'admin') {
        return redirect()->to('/dashboard');
    }

    $db = \Config\Database::connect();

    $stats = [
        'pending_jobs' => $db->table('jobs')->where('status', 'pending')->countAllResults(),
        'failed_jobs' => $db->table('jobs')->where('status', 'failed')->countAllResults(),
        'active_conversations' => $db->table('conversations')->where('status', 'open')->countAllResults(),
        'messages_today' => $db->table('messages')->where('DATE(created_at)', date('Y-m-d'))->countAllResults(),
        'disk_usage' => disk_free_space('/') / disk_total_space('/') * 100,
        'error_count_today' => $this->countErrorsToday()
    ];

    return view('monitor/stats', $stats);
}

---

MAINTENANCE PROCEDURES:

## Daily Maintenance (Automated via Cron)

1. Cleanup webhook logs (>30 days): 4 AM
2. Cleanup stale flows: 3 AM
3. Cleanup old sessions: 5 AM
4. Database optimization: 2 AM (weekly)

## Weekly Maintenance (Manual)

□ Review error logs
□ Check disk space usage
□ Review failed jobs in DLQ
□ Check backup integrity
□ Review slow queries
□ Update dependencies (if needed)

## Monthly Maintenance (Manual)

□ Full database backup (download from server)
□ Review performance metrics
□ Update SSL certificates (auto-renews, but verify)
□ Review user accounts (remove inactive)
□ Security audit (check for vulnerabilities)
□ Update documentation

---

DISASTER RECOVERY PLAN:

## Scenario 1: Database Corruption

1. Stop application (maintenance mode)
2. Restore from last backup
3. Verify data integrity
4. Resume application
5. Monitor for issues

## Scenario 2: Server Crash

1. Contact hosting provider
2. Restore from backup to new server
3. Update DNS if IP changed
4. Reconfigure WhatsApp webhook
5. Test critical flows

## Scenario 3: Data Loss

1. Identify affected timeframe
2. Restore from nearest backup before incident
3. Manually reconcile any lost data
4. Implement additional backup frequency

## Scenario 4: Security Breach

1. Immediately change all passwords
2. Regenerate all API keys
3. Review access logs
4. Patch vulnerability
5. Notify affected users
6. Implement additional security measures

---

SCALING CONSIDERATIONS (Future):

When to scale:
- > 10,000 active conversations
- > 100,000 messages/day
- > 50 concurrent users
- Queue processing takes > 5 minutes

Options:
1. Upgrade hosting (more RAM/CPU)
2. Move to VPS/dedicated server
3. Implement Redis for caching
4. Separate job queue to different server
5. Database read replicas
6. CDN for media files
7. Load balancer for multiple app servers

---

Continue with Part 3 (Final Checklist & Go-Live)?
