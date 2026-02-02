@echo off
chcp 65001 >nul

echo ========================================
echo Building TecnoFit Docker Environment (Scale)
echo ========================================

echo.
echo [1/5] Building MySQL image...
docker-compose -f docker-compose-scale.yml build mysql-scale
if %ERRORLEVEL% neq 0 (
    echo ERROR: Failed to build MySQL image
    pause
    exit /b 1
)

echo.
echo [2/5] Building Nginx image...
docker-compose -f docker-compose-scale.yml build nginx
if %ERRORLEVEL% neq 0 (
    echo ERROR: Failed to build Nginx image
    pause
    exit /b 1
)

echo.
echo [3/5] Building Hyperf API 1 image...
docker-compose -f docker-compose-scale.yml build hyperf-api-1
if %ERRORLEVEL% neq 0 (
    echo ERROR: Failed to build Hyperf API 1 image
    pause
    exit /b 1
)

echo.
echo [4/5] Building Hyperf API 2 image...
docker-compose -f docker-compose-scale.yml build hyperf-api-2
if %ERRORLEVEL% neq 0 (
    echo ERROR: Failed to build Hyperf API 2 image
    pause
    exit /b 1
)

echo.
echo [5/5] Building Hyperf Cron image...
docker-compose -f docker-compose-scale.yml build hyperf-cron
if %ERRORLEVEL% neq 0 (
    echo ERROR: Failed to build Hyperf Cron image
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
    docker-compose -f docker-compose-scale.yml up -d
    echo.
    echo Containers started!
    echo.
    docker-compose -f docker-compose-scale.yml ps
) else (
    echo.
    echo To start containers later, run: docker-compose -f docker-compose-scale.yml up -d
)

echo.
echo ========================================
echo Access Points:
echo   - Nginx LB:    http://localhost:9501
echo   - MySQL:       localhost:3307
echo ========================================

pause
