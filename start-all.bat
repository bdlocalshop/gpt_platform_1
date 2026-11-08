@echo off
echo Starting GPT Rewards Development Servers...
start "Laravel Backend API" cmd /k "%~dp0start-backend.bat"
start "Next.js Frontend UI" cmd /k "%~dp0start-frontend.bat"
echo Both servers have been launched in separate windows!
echo - Backend: http://127.0.0.1:8000
echo - Frontend: http://localhost:3000
pause
