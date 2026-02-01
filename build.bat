@echo off
chcp 65001 >nul

echo ========================================
echo Building TecnoFit Docker Environment
echo ========================================

echo.
echo [1/3] Building MySQL image...
docker-compose build mysql
if %ERRORLEVEL% neq 0 (
    echo ERROR: Failed to build MySQL image
    pause
    exit /b 1
)

echo.
echo [2/3] Building Mailhog image...
docker-compose build mailhog
if %ERRORLEVEL% neq 0 (
    echo ERROR: Failed to build Mailhog image
    pause
    exit /b 1
)

echo.
echo [3/3] Building Hyperf image...
docker-compose build hyperf
if %ERRORLEVEL% neq 0 (
    echo ERROR: Failed to build Hyperf image
    pause
    exit /b 1
)

echo.
echo ========================================
echo All images built successfully!
echo ========================================

echo.
set /p start_containers="Do you want to start the containers now? (y/n): "

if /i "%start_containers%"=="y" (
    echo.
    echo Starting containers...
    docker-compose up -d
    echo.
    echo Containers started!
    echo.
    docker-compose ps
) else (
    echo.
    echo To start containers later, run: docker-compose up -d
)

echo.
echo ========================================
echo Access Points:
echo   - Hyperf API:  http://localhost:9501
echo   - Mailhog UI:  http://localhost:8025
echo   - MySQL:       localhost:3306
echo ========================================

pause
