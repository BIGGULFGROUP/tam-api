#!/bin/bash
set -e

echo "==> TAM API Startup"

# Create .env from environment if missing
if [ ! -f /app/.env ] || [ ! -s /app/.env ]; then
    echo "==> Generating .env from environment variables"
    cat > /app/.env <<ENVEOF
APP_NAME="${APP_NAME:-The African Mail API}"
APP_ENV=${APP_ENV:-production}
APP_KEY=${APP_KEY:-}
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=${APP_URL:-https://tam-api.onrender.com}

LOG_CHANNEL=${LOG_CHANNEL:-stack}
LOG_LEVEL=${LOG_LEVEL:-error}

DB_CONNECTION=${DB_CONNECTION:-pgsql}
DB_HOST=${DB_HOST:-}
DB_PORT=${DB_PORT:-5432}
DB_DATABASE=${DB_DATABASE:-}
DB_USERNAME=${DB_USERNAME:-}
DB_PASSWORD=${DB_PASSWORD:-}

SESSION_DRIVER=${SESSION_DRIVER:-database}
CACHE_STORE=${CACHE_STORE:-database}
QUEUE_CONNECTION=${QUEUE_CONNECTION:-database}

FRONTEND_URL=${FRONTEND_URL:-}
SANCTUM_STATEFUL_DOMAINS=${SANCTUM_STATEFUL_DOMAINS:-}
ADMIN_SECRET=${ADMIN_SECRET:-}
ENVEOF
    echo "==> .env created"
fi

# Generate app key if empty
if ! grep -q "APP_KEY=base64:" /app/.env; then
    php artisan key:generate --force
    echo "==> APP_KEY generated"
fi

# Force-clear ALL cached config/route files from previous builds
# These MUST be cleared before migrations or env changes won't apply
rm -f /app/bootstrap/cache/*.php 2>/dev/null || true
echo "==> Config cache cleared"

# Run migrations
echo "==> Running migrations..."
php artisan migrate --force --no-interaction || echo "⚠ Migration step done"

# Run seeders (idempotent — updateOrCreate)
echo "==> Running seeders..."
php artisan db:seed --force --no-interaction || echo "⚠ Seeder step done"

# Cache for production
php artisan config:cache 2>/dev/null || true
php artisan route:cache 2>/dev/null || true

# Start Laravel scheduler in background (runs every minute, auto-restarts)
echo "==> Starting scheduler..."
php artisan schedule:work &

echo "==> Starting Apache..."
exec apache2-foreground
