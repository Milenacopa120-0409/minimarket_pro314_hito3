@ECHO OFF
SETLOCAL
SET "PROJECT_DIR=%~dp0"
SET "MVN_HOME=%PROJECT_DIR%.mvn\apache-maven-3.9.11"
IF NOT EXIST "%MVN_HOME%\bin\mvn.cmd" (
  ECHO Preparando Apache Maven 3.9.11 en la carpeta del proyecto...
  powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%PROJECT_DIR%.mvn\wrapper\bootstrap-maven.ps1"
  IF ERRORLEVEL 1 EXIT /B 1
)
CALL "%MVN_HOME%\bin\mvn.cmd" %*
EXIT /B %ERRORLEVEL%
