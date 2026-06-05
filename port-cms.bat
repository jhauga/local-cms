@echo off
setlocal enabledelayedexpansion
REM ===========================================================================
REM  port-cms.bat - Port a Local CMS theme or plugin to another CMS (Windows).
REM
REM  Companion to export.bat. Where export packages a theme/plugin for stock
REM  WordPress, port-cms stages it for a different content management system
REM  (Drupal, Joomla, Ghost, Grav, ...). This is the cross-platform base: it
REM  handles argument parsing, output scaffolding, .gitignore hygiene, staging,
REM  and archiving. Each target CMS is registered in port-cms\registry.txt and
REM  may ship an optional adapter at port-cms\<cms>\transform.bat that rewrites
REM  the staged files into that platform's conventions.
REM
REM  Usage:
REM    port-cms <cms> <tool/name>      Port a theme or plugin to <cms>
REM    port-cms -l | --list            List available CMS targets
REM    port-cms -h | --help            Show this help
REM
REM      <cms>       Target CMS (case-insensitive, e.g. drupal, DrUpAL)
REM      tool/name   tool = theme(s) | plugin(s); name = folder under that tool
REM
REM  Examples:
REM    port-cms drupal themes/default              Port the default theme
REM    port-cms drupal plugin/local-cms-markdown   Port the markdown plugin
REM
REM  Output (Windows): _port-<tool>\<cms>\<slug>.zip
REM  The default theme/plugin name maps to the "local-cms" slug, since most CMS
REM  already ship a theme named "default".
REM ===========================================================================

cd /d "%~dp0"

set "_arg1=%~1"
set "_arg2=%~2"

if /i "%_arg1%"=="-h"     goto :usage
if /i "%_arg1%"=="--help" goto :usage
if /i "%_arg1%"=="/?"     goto :usage
if "%_arg1%"==""          goto :usage

set "_registry=%~dp0port-cms\registry.txt"
if not exist "%_registry%" (
  echo [port-cms] Registry not found: %_registry%
  exit /b 1
)

if /i "%_arg1%"=="-l"     goto :list
if /i "%_arg1%"=="--list" goto :list

REM --- Port flow: arg1 = CMS target, arg2 = tool/name -----------------------
set "_target=%_arg1%"
set "_spec=%_arg2%"

if "%_spec%"=="" (
  echo [port-cms] Missing tool/name. Example: port-cms drupal themes/default
  exit /b 1
)

REM Resolve the CMS against the registry (case-insensitive); keep its casing.
set "_cms="
for /f "usebackq eol=# tokens=* delims= " %%c in ("%_registry%") do (
  if /i "%%c"=="%_target%" set "_cms=%%c"
)
if not defined _cms (
  echo [port-cms] Unsupported CMS "%_target%". Run "port-cms --list" to see options.
  exit /b 1
)

REM Split tool/name on the first slash.
for /f "tokens=1* delims=/" %%a in ("%_spec%") do (
  set "_tool=%%a"
  set "_name=%%b"
)
if "%_name%"=="" (
  echo [port-cms] Expected tool/name, e.g. themes/default or plugin/local-cms-markdown.
  exit /b 1
)

REM Normalize the tool to its folder name (accept singular or plural).
if /i "%_tool%"=="theme"  set "_tool=themes"
if /i "%_tool%"=="plugin" set "_tool=plugins"
if /i not "%_tool%"=="themes" if /i not "%_tool%"=="plugins" (
  echo [port-cms] Unknown tool "%_tool%". Use "theme(s)" or "plugin(s)".
  exit /b 1
)

set "_src=%_tool%\%_name%"
if not exist "%_src%\" (
  echo [port-cms] Source folder not found: %_src%
  exit /b 1
)

REM The "default" theme/plugin ships under the "local-cms" slug.
set "_slug=%_name%"
if /i "%_name%"=="default" set "_slug=local-cms"

REM Ensure the per-tool output root is gitignored.
set "_outRoot=_port-%_tool%"
findstr /i /c:"%_outRoot%" .gitignore >nul 2>nul || echo %_outRoot%/>>.gitignore

set "_outDir=%_outRoot%\%_cms%"
if not exist "%_outRoot%\" mkdir "%_outRoot%"
if not exist "%_outDir%\" mkdir "%_outDir%"

REM Stage a clean copy of the source for transformation.
set "_work=%_outDir%\%_slug%"
if exist "%_work%\" rmdir /s /q "%_work%"
mkdir "%_work%"
echo [port-cms] Staging %_src% -^> %_work%
xcopy "%_src%\*" "%_work%\" /e /i /q /y >nul

REM Apply the optional per-CMS adapter (the "add a CMS" extension seam).
set "_hook=port-cms\%_cms%\transform.bat"
if exist "%_hook%" (
  echo [port-cms] Applying %_cms% adapter: %_hook%
  call "%_hook%" "%_tool%" "%_name%" "%_work%"
  if errorlevel 1 (
    echo [port-cms] Adapter failed for %_cms%.
    exit /b 1
  )
) else (
  echo [port-cms] No %_cms% adapter found; packaging the staged source as-is.
)

REM Archive: zip on Windows.
set "_zip=%_outDir%\%_slug%.zip"
if exist "%_zip%" del /q "%_zip%"
echo [port-cms] Packaging %_zip%
powershell -NoProfile -ExecutionPolicy Bypass -Command "Compress-Archive -Path '%_work%\*' -DestinationPath '%_zip%' -Force"
if errorlevel 1 (
  echo [port-cms] Failed to create %_zip%
  exit /b 1
)

echo [port-cms] Done: %_zip%
exit /b 0

:list
echo Available CMS targets for porting:
for /f "usebackq eol=# tokens=* delims= " %%c in ("%_registry%") do (
  set "_note="
  if exist "port-cms\%%c\transform.bat" set "_note= (adapter)"
  if exist "port-cms\%%c\transform.sh"  set "_note= (adapter)"
  echo   %%c!_note!
)
exit /b 0

:usage
echo Usage: port-cms ^<cms^> ^<tool/name^>
echo   port-cms drupal themes/default              Port the default theme to Drupal
echo   port-cms drupal plugin/local-cms-markdown   Port the markdown plugin to Drupal
echo   port-cms -l, --list                         List available CMS targets
echo   port-cms -h, --help                         Show this help
exit /b 0
