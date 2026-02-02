#!/bin/bash

# Check if running with sudo/root permissions
if [ "$EUID" -ne 0 ] && ! groups | grep -q docker; then
    echo "WARNING: You may need to run this script with sudo"
    echo "Or add your user to docker group: sudo usermod -aG docker \$USER"
    echo ""
fi

echo "========================================"
echo "Building TecnoFit Docker Environment (Scale)"
echo "========================================"

echo ""
echo "[1/5] Building MySQL image..."
docker compose -f docker compose-scale.yml build mysql-scale
if [ $? -ne 0 ]; then
    echo "ERROR: Failed to build MySQL image"
    exit 1
fi

echo ""
echo "[2/5] Building Nginx image..."
docker compose -f docker compose-scale.yml build nginx
if [ $? -ne 0 ]; then
    echo "ERROR: Failed to build Nginx image"
    exit 1
fi

echo ""
echo "[3/5] Building Hyperf API 1 image..."
docker compose -f docker compose-scale.yml build hyperf-api-1
if [ $? -ne 0 ]; then
    echo "ERROR: Failed to build Hyperf API 1 image"
    exit 1
fi

echo ""
echo "[4/5] Building Hyperf API 2 image..."
docker compose -f docker compose-scale.yml build hyperf-api-2
if [ $? -ne 0 ]; then
    echo "ERROR: Failed to build Hyperf API 2 image"
    exit 1
fi

echo ""
echo "[5/5] Building Hyperf Cron image..."
docker compose -f docker compose-scale.yml build hyperf-cron
if [ $? -ne 0 ]; then
    echo "ERROR: Failed to build Hyperf Cron image"
    exit 1
fi

echo ""
echo "========================================"
echo "All images built successfully!"
echo "========================================"

echo ""
read -p "Do you want to start the containers now? (y/n): " start_containers

if [ "$start_containers" = "y" ] || [ "$start_containers" = "Y" ]; then
    echo ""
    echo "Starting containers..."
    docker compose -f docker compose-scale.yml up -d
    echo ""
    echo "Containers started!"
    echo ""
    docker compose -f docker compose-scale.yml ps
else
    echo ""
    echo "To start containers later, run: docker compose -f docker compose-scale.yml up -d"
fi

echo ""
echo "========================================"
echo "Access Points:"
echo "  - Nginx LB:    http://localhost:9501"
echo "  - MySQL:       localhost:3307"
echo "========================================"
