@echo off
echo Running Rovix CRM database migrations...
C:\xampp\php\php.exe "%~dp0spark" migrate
if %ERRORLEVEL% NEQ 0 (
    echo Migration failed. Ensure MySQL is running in XAMPP.
    pause
    exit /b 1
)
echo.
echo Migration complete.
pause