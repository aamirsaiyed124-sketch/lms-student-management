@echo off
echo =======================================================
echo  Starting EduPulse LMS (Student Management System)
echo =======================================================
echo.

echo [1/2] Using WAMP/Apache PHP API at:
echo http://localhost/lms-student-management/backend/api
echo.
echo Make sure WAMP Apache and MySQL are GREEN/running.
echo.

REM Launch React Frontend Server
echo [2/2] Starting React Vite Frontend on http://localhost:5173 ...
start "EduPulse React Frontend" cmd /k "cd /d %~dp0frontend && npm run dev"

echo.
echo =======================================================
echo  WAMP provides the PHP backend; React is starting now.
echo  Open your browser at: http://localhost:5173
echo =======================================================
pause
