@echo off
setlocal
REM ===========================================================================
REM  export.bat - Build a distributable zip for a Local CMS theme or plugin.
REM
REM  A committable, self-contained replacement for the personal task.bat export
REM  flow. Uses only built-in Windows commands (PowerShell Compress-Archive),
REM  with no external batch library.
REM
REM  Usage:
REM    export [type] [name]
REM      type : themes | plugins        (default: themes)
REM      name : folder under <type>     (default: "default" for themes,
REM             "local-cms-markdown" for plugins)
REM
REM  Examples:
REM    export                             Zip themes\default
REM    export themes default              Zip the default theme
REM    export plugins local-cms-markdown  Build/zip the markdown plugin
REM
REM  Output: _export-<type>\<name>.zip whose ROOT is the folder's files (e.g.
REM  style.css, template-parts\). Extract it straight into the target folder,
REM  e.g. wp-content\themes\<slug>\ or wp-content\plugins\<slug>\.
REM ===========================================================================

cd /d "%~dp0"

set "_type=%~1"
set "_name=%~2"

if /i "%_type%"=="-h"     goto :usage
if /i "%_type%"=="--help" goto :usage
if /i "%_type%"=="/?"     goto :usage

if "%_type%"=="" set "_type=themes"

if /i not "%_type%"=="themes" if /i not "%_type%"=="plugins" (
  echo [export] Unknown type "%_type%". Use "themes" or "plugins".
  exit /b 1
)

if "%_name%"=="" (
  if /i "%_type%"=="plugins" (set "_name=local-cms-markdown") else (set "_name=default")
)

set "_src=%_type%\%_name%"

if not exist "%_src%\" (
  echo [export] Source folder not found: %_src%
  exit /b 1
)

REM Plugins that ship their own build.php manage their assets + zip themselves.
if /i "%_type%"=="plugins" if exist "%_src%\build.php" (
  where php >nul 2>nul || (
    echo [export] PHP not found on PATH; cannot run %_src%\build.php
    exit /b 1
  )
  echo [export] Delegating to %_src%\build.php ...
  php "%_src%\build.php"
  exit /b
)
if "%_name%"=="default" (
 set "_name=local-cms"
)
set "_outDir=_export-%_type%"
set "_zip=%_outDir%\%_name%.zip"

if not exist "%_outDir%\" mkdir "%_outDir%"
if exist "%_zip%" del /q "%_zip%"

echo [export] Zipping %_src% -^> %_zip%
powershell -NoProfile -ExecutionPolicy Bypass -Command "Compress-Archive -Path '%_src%\*' -DestinationPath '%_zip%' -Force"
if errorlevel 1 (
  echo [export] Failed to create %_zip%
  exit /b 1
)

echo [export] Done: %_zip%
exit /b 0

:usage
echo Usage: export [themes^|plugins] [name]
echo   export                             Zip themes\default
echo   export themes ^<name^>               Zip a theme folder
echo   export plugins ^<name^>              Build/zip a plugin folder
exit /b 0
