@echo off
rem Double-click to upload the CRM to the hosting and check it. See deploy.ps1.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0deploy.ps1" %*
echo.
pause
