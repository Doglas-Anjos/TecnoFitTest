#\!/bin/bash
set -e

# Install composer dependencies if vendor directory is missing
if [ \! -f "/data/project/vendor/autoload.php" ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist
fi

# Execute the main command
exec "$@"
