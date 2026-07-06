@echo off
cd /d C:\xampp\htdocs\rovix-crm
C:\xampp\php\php.exe spark run:scheduled >> writable\logs\cron.log 2>&1
