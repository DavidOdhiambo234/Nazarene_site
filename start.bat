@echo off
title University Church Website
echo ========================================
echo   University Church of the Nazarene
echo ========================================
echo.
echo Starting PHP Server...
echo Starting M-Pesa payment API...
echo.
echo Website: http://localhost:8000
echo Payment API: http://localhost:3000
echo Admin:   http://localhost:8000/admin/login.php
echo.
echo Press Ctrl+C to stop the server
echo.
start "Nazarene M-Pesa API" /D "%~dp0" cmd /k "npm start"
php -S localhost:8000