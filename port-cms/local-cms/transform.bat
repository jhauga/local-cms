@echo off
REM Local CMS inbound adapter for port-cms (Windows). Delegates to transform.php.
setlocal

where php >nul 2>nul || (
  echo [local-cms] PHP not found on PATH; the Local CMS adapter needs PHP.
  exit /b 1
)

php "%~dp0transform.php" %*
exit /b %errorlevel%
