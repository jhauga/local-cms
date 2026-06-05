@echo off
REM Drupal adapter for port-cms (Windows). Delegates to transform.php.
setlocal

where php >nul 2>nul || (
  echo [drupal] PHP not found on PATH; the Drupal adapter needs PHP.
  exit /b 1
)

php "%~dp0transform.php" %*
exit /b %errorlevel%
