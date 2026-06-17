@echo off
rem Reset Propel test fixtures (Windows port of test/reset_tests.sh)

setlocal EnableExtensions

rem Resolve paths from this script's location (test\)
set "SCRIPT_DIR=%~dp0"
if "%SCRIPT_DIR:~-1%"=="\" set "SCRIPT_DIR=%SCRIPT_DIR:~0,-1%"

set "REPO_ROOT=%SCRIPT_DIR%\.."
set "FIXTURES_DIR=%SCRIPT_DIR%\fixtures"
set "PROPEL_GEN=%REPO_ROOT%\generator\bin\propel-gen.bat"

if not exist "%FIXTURES_DIR%\" (
    echo ERROR: No 'test\fixtures\' directory found!
    exit /b 1
)

if not exist "%PROPEL_GEN%" (
    echo ERROR: propel-gen not found at "%PROPEL_GEN%"
    exit /b 1
)

for /d %%D in ("%FIXTURES_DIR%\*") do (
    call :rebuild "%%~nxD"
    if errorlevel 1 exit /b 1
)

rem Special case for reverse fixtures
if exist "%FIXTURES_DIR%\reverse\" (
    for /d %%D in ("%FIXTURES_DIR%\reverse\*") do (
        if exist "%%D\build.properties" (
            echo [ %%~nxD ]
            call "%PROPEL_GEN%" "%%D" insert-sql >nul 2>&1
            if errorlevel 1 (
                echo ERROR: insert-sql failed for reverse\%%~nxD
                exit /b 1
            )
        )
    )
)

echo Done.
exit /b 0

:rebuild
set "DIRNAME=%~1"

if not exist "%FIXTURES_DIR%\%DIRNAME%\build.properties" (
    exit /b 0
)

echo [ %DIRNAME% ]

if exist "%FIXTURES_DIR%\%DIRNAME%\build" (
    rmdir /s /q "%FIXTURES_DIR%\%DIRNAME%\build"
)

call "%PROPEL_GEN%" "%FIXTURES_DIR%\%DIRNAME%" main >nul 2>&1
if errorlevel 1 (
    echo ERROR: main failed for %DIRNAME%
    exit /b 1
)

call "%PROPEL_GEN%" "%FIXTURES_DIR%\%DIRNAME%" insert-sql >nul 2>&1
if errorlevel 1 (
    echo ERROR: insert-sql failed for %DIRNAME%
    exit /b 1
)

exit /b 0
