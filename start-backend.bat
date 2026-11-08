@echo off
cd /d "%~dp0backend"
echo ==============================================
echo   Starting Laravel Backend API (Port 8000)
echo ==============================================
php artisan serve
pause
