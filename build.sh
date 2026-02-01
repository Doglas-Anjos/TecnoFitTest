#!/bin/bash

# Check if running with sudo/root permissions
if [ "$EUID" -ne 0 ] && ! groups | grep -q docker; then
    echo "WARNING: You may need to run this script with sudo"
    echo "Or add your user to docker group: sudo usermod -aG docker \$USER"
    echo ""
fi

echo "========================================"
echo "Building TecnoFit Docker Environment"
echo "========================================"

echo ""
echo "[1/3] Building MySQL image..."
docker-compose build mysql
if [ $? -ne 0 ]; then
    echo "ERROR: Failed to build MySQL image"
    exit 1
fi

echo ""
echo "[2/3] Building Mailhog image..."
docker-compose build mailhog
if [ $? -ne 0 ]; then
    echo "ERROR: Failed to build Mailhog image"
    exit 1
fi

echo ""
echo "[3/3] Building Hyperf image..."
docker-compose build hyperf
if [ $? -ne 0 ]; then
    echo "ERROR: Failed to build Hyperf image"
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
    docker-compose up -d
    echo ""
    echo "Containers started!"
    echo ""
    docker-compose ps
else
    echo ""
    echo "To start containers later, run: docker-compose up -d"
fi

echo ""
echo "========================================"
echo "Access Points:"
echo "  - Hyperf API:  http://localhost:9501"
echo "  - Mailhog UI:  http://localhost:8025"
echo "  - MySQL:       localhost:3306"
echo "========================================"
