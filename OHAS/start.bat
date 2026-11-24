@echo off
setlocal
set "OHAS_ROOT=%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%OHAS_ROOT%start.ps1"
set "OHAS_RC=%ERRORLEVEL%"
if not "%OHAS_RC%"=="0" (
  echo Failed to start OHAS. See the message above.
  pause
)
endlocal & exit /b %OHAS_RC%
