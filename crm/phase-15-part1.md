## PHASE 15: Deployment & Go-Live (Week 11-12)

### Prompt 15.1 — XAMPP to cPanel Migration Guide

```
Complete deployment guide for moving Rovix AI Leads Tool from XAMPP (development) to cPanel (production).

PREREQUISITES:

□ cPanel shared hosting account
□ PHP 8.1+ available
□ MySQL 8.0+ database
□ SSH access (optional but recommended)
□ Domain/subdomain configured
□ SSL certificate (Let's Encrypt via cPanel)

---

STEP 1: PREPARE LOCAL APPLICATION

1. Test everything works on XAMPP:
   - Run all manual tests
   - Check all features work
   - Verify no errors in logs

2. Clean up development artifacts:
   
# Remove test data
DELETE FROM contacts WHERE email LIKE '%test%';
DELETE FROM broadcasts WHERE name LIKE 'Test%';
DELETE FROM flows WHERE name LIKE 'Test%';

# Clear logs
cd C:\xampp\htdocs\rovix-ai-leads-tool
rm -rf writable/logs/*

# Clear cache
php spark cache:clear

3. Update configuration for production:

# Copy .env.example to .env.production
cp .env.example .env.production

Edit .env.production:

CI_ENVIRONMENT = production

# Database (will update with cPanel credentials)
database.default.hostname = localhost
database.default.database = YOUR_CPANEL_DB_NAME
database.default.username = YOUR_CPANEL_DB_USER
database.default.password = YOUR_CPANEL_DB_PASSWORD
database.default.DBDriver = MySQLi
database.default.port = 3306

# Encryption key (KEEP THE SAME - or re-encrypt all tokens!)
encryption.key = YOUR_EXISTING_KEY

# Base URL
app.baseURL = https://yourdomain.com/

# Session
app.sessionDriver = database
app.sessionCookieName = rovix_session
app.sessionExpiration = 7200
app.sessionSavePath = null

# Email (configure SMTP)
email.protocol = smtp
email.SMTPHost = mail.yourdomain.com
email.SMTPPort = 465
email.SMTPUser = noreply@yourdomain.com
email.SMTPPass = your_email_password
email.SMTPCrypto = ssl
email.fromEmail = noreply@yourdomain.com
email.fromName = Rovix AI

4. Create production database export:

# Export from XAMPP
cd C:\xampp\mysql\bin
mysqldump -u root rovix_crm > rovix_production.sql

# Note: This includes structure + data (accounts, users, etc.)

---

STEP 2: SET UP cPANEL HOSTING

1. Create MySQL Database:

cPanel → MySQL Databases
- Create database: yourdomain_rovix
- Create user: yourdomain_rovix_user
- Set strong password (save it!)
- Add user to database with ALL PRIVILEGES

2. Import Database:

cPanel → phpMyAdmin
- Select database: yourdomain_rovix
- Click Import
- Choose file: rovix_production.sql
- Click Go
- Wait for import to complete

3. Verify database imported:

SELECT COUNT(*) FROM accounts;
SELECT COUNT(*) FROM users;
# Should show your data

4. Create subdomain (optional):

cPanel → Subdomains
- Create: crm.yourdomain.com
- Document Root: /home/username/crm.yourdomain.com

Or use main domain with subdirectory:
- Document Root: /home/username/public_html/crm

---

STEP 3: UPLOAD APPLICATION FILES

Method A: File Manager (for small sites)

1. cPanel → File Manager
2. Navigate to document root
3. Upload application as ZIP
4. Extract ZIP
5. Delete ZIP file

Method B: FTP (recommended)

1. Use FileZilla or similar
2. Connect to your cPanel FTP
3. Navigate to document root
4. Upload entire application folder
5. Wait for transfer (may take 10-30 minutes)

Method C: SSH/SCP (fastest, requires SSH access)

# From your local machine
scp -r C:\xampp\htdocs\rovix-ai-leads-tool username@yourserver.com:/home/username/public_html/crm

# Or use tar for faster transfer
tar -czf rovix.tar.gz rovix-ai-leads-tool
scp rovix.tar.gz username@yourserver.com:/home/username/
ssh username@yourserver.com
cd /home/username/public_html
tar -xzf ../rovix.tar.gz
mv rovix-ai-leads-tool crm

---

STEP 4: CONFIGURE APPLICATION ON SERVER

1. Update .env file:

# Via File Manager or SSH
cd /home/username/public_html/crm
nano .env

# Paste .env.production content
# Update database credentials from Step 2
# Save and exit

2. Set file permissions:

# Via SSH
chmod 755 /home/username/public_html/crm
chmod -R 755 app
chmod -R 777 writable
chmod -R 755 public
chmod 644 .env

# Important: writable/ must be writable by web server
find writable -type d -exec chmod 777 {} \;
find writable -type f -exec chmod 666 {} \;

3. Update .htaccess in public/:

# Should already be there, but verify:
cat public/.htaccess

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    
    # Redirect to https
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
    
    # CodeIgniter routing
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php/$1 [L]
</IfModule>

# Disable directory listing
Options -Indexes

# Protect .env
<Files .env>
    Order allow,deny
    Deny from all
</Files>

4. Point domain to public/ folder:

Option A: Document root already points to public/
- Set document root to: /home/username/public_html/crm/public

Option B: Use .htaccess redirect (if document root is /crm)
- Add to /crm/.htaccess:

RewriteEngine On
RewriteRule ^$ public/ [L]
RewriteRule ^((?!public/).*)$ public/$1 [L]

---

STEP 5: INSTALL COMPOSER DEPENDENCIES (if needed)

Most cPanel hosts don't have Composer, but CodeIgniter 4 ships with vendor/.

If you need to run composer:

# Via SSH (if Composer available)
cd /home/username/public_html/crm
composer install --no-dev --optimize-autoloader

# If Composer not available, upload vendor/ from local:
# (Already included if you uploaded entire folder)

---

STEP 6: SET UP CRON JOBS

cPanel → Cron Jobs

Add these cron jobs:

1. Process job queue (every minute):
   * * * * * cd /home/username/public_html/crm && /usr/bin/php spark queue:process >> /dev/null 2>&1

2. Run scheduled tasks (every hour):
   0 * * * * cd /home/username/public_html/crm && /usr/bin/php spark scheduled:run >> /dev/null 2>&1

3. Cleanup stale flows (daily at 3 AM):
   0 3 * * * cd /home/username/public_html/crm && /usr/bin/php spark flows:cleanup-stale >> /dev/null 2>&1

4. Cleanup webhook logs (daily at 4 AM):
   0 4 * * * cd /home/username/public_html/crm && /usr/bin/php spark webhooks:cleanup >> /dev/null 2>&1

5. Cleanup old sessions (daily at 5 AM):
   0 5 * * * cd /home/username/public_html/crm && /usr/bin/php spark sessions:gc >> /dev/null 2>&1

Note: Verify PHP path with:
which php
# Or: /usr/bin/php, /usr/local/bin/php

---

STEP 7: CONFIGURE SSL CERTIFICATE

1. cPanel → SSL/TLS Status
2. Select your domain
3. Click "Run AutoSSL"
4. Wait for certificate to install (2-5 minutes)

5. Force HTTPS (already in .htaccess, but verify):

# Test manually:
http://yourdomain.com
# Should redirect to https://

---

STEP 8: CONFIGURE WHATSAPP WEBHOOK

1. Login to application:
   https://yourdomain.com/login

2. Go to Settings → WhatsApp

3. Copy webhook URL:
   https://yourdomain.com/webhook/whatsapp

4. Configure in Meta Business Manager:
   - Go to: WhatsApp → Configuration → Webhooks
   - Callback URL: https://yourdomain.com/webhook/whatsapp
   - Verify Token: (from your settings)
   - Click "Verify and Save"

5. Subscribe to webhook fields:
   - messages
   - message_status
   - message_template_status_update

6. Test webhook:
   - Send test message from WhatsApp
   - Check if it appears in Inbox
   - Check webhook logs in Settings

---

STEP 9: VERIFY DEPLOYMENT

Critical tests:

1. Can you access the site?
   https://yourdomain.com

2. Can you login?
   https://yourdomain.com/login

3. Dashboard loads correctly?
   https://yourdomain.com/dashboard

4. WhatsApp webhook works?
   - Send test message
   - Check inbox

5. Can you send a message?
   - Reply to conversation
   - Check message sent

6. Broadcast works?
   - Create test broadcast
   - Send to 1-2 recipients
   - Check delivered

7. Cron jobs running?
   # Via SSH
   tail -f writable/logs/log-*.php
   # Should see queue processing logs

8. File uploads work?
   - Try uploading image in inbox
   - Check saved to writable/uploads/

9. Email sending works?
   - Invite team member
   - Check email received

10. Check error logs:
    # Via SSH or File Manager
    cat writable/logs/log-YYYY-MM-DD.php
    # Should have no critical errors

---

STEP 10: POST-DEPLOYMENT SECURITY

1. Secure .env file:

chmod 600 .env
# Only owner can read/write

2. Disable directory listing:
   (Already in .htaccess)

3. Hide PHP version:

# Add to .htaccess in public/
<IfModule mod_headers.c>
    Header unset X-Powered-By
</IfModule>

4. Verify database user has minimum privileges:
   - Should NOT have GRANT, CREATE USER, etc.
   - Only needs: SELECT, INSERT, UPDATE, DELETE

5. Change default admin password:
   - Login as admin
   - Settings → Account → Change Password

6. Regenerate API key:
   - Settings → API Keys → Regenerate

7. Review team access:
   - Remove test users
   - Verify only real users remain

---

STEP 11: SETUP BACKUPS

Automated Backup Script (via cron):

cPanel → Cron Jobs

Add daily backup at 2 AM:

0 2 * * * /home/username/backup-rovix.sh >> /home/username/backup.log 2>&1

Create backup-rovix.sh:

#!/bin/bash

DATE=$(date +%Y%m%d)
BACKUP_DIR="/home/username/backups"
APP_DIR="/home/username/public_html/crm"
DB_NAME="yourdomain_rovix"
DB_USER="yourdomain_rovix_user"
DB_PASS="your_db_password"

# Create backup directory
mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > $BACKUP_DIR/db-$DATE.sql

# Backup uploaded files
tar -czf $BACKUP_DIR/uploads-$DATE.tar.gz $APP_DIR/writable/uploads

# Keep only last 7 days
find $BACKUP_DIR -name "db-*.sql" -mtime +7 -delete
find $BACKUP_DIR -name "uploads-*.tar.gz" -mtime +7 -delete

echo "Backup completed: $DATE"

Make executable:
chmod +x /home/username/backup-rovix.sh

---

TROUBLESHOOTING COMMON cPANEL ISSUES:

1. 500 Internal Server Error
   - Check .htaccess syntax
   - Check file permissions (755/644)
   - Check error logs: writable/logs/
   - Check PHP version (must be 8.1+)

2. Database connection error
   - Verify credentials in .env
   - Check database exists
   - Check user has privileges
   - Try: mysql -u user -p database

3. Writable not writable
   - chmod -R 777 writable
   - Check directory ownership

4. Cron jobs not running
   - Verify PHP path: which php
   - Check cron logs: /var/log/cron
   - Add logging: >> /home/username/cron.log 2>&1

5. WhatsApp webhook fails
   - Check SSL certificate valid
   - Check webhook URL accessible publicly
   - Check firewall not blocking Meta IPs

6. File uploads fail
   - Check writable/uploads exists
   - Check permissions: 777
   - Check PHP upload_max_filesize (10MB+)

7. Sessions not persisting
   - Check ci_sessions table exists
   - Check session config in .env
   - Clear browser cookies

8. Composer autoload errors
   - Re-upload vendor/ folder
   - Or run: composer dump-autoload

---

Continue with Part 2 (Performance Optimization & Monitoring)?
