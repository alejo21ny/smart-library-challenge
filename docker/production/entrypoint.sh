#!/bin/sh
set -e

# PORT is provided by the deployment platform at runtime (Render sets it;
# defaults to 8080 to match the Dockerfile's EXPOSE/HEALTHCHECK otherwise).
export PORT="${PORT:-8080}"

envsubst '${PORT}' < /etc/nginx/templates/nginx.conf.template > /etc/nginx/nginx.conf

# Config/route/view caching needs real runtime env vars (APP_KEY, DB_*, ...),
# which only exist once the container starts — never baked into the image.
# APP_KEY must already be set by the platform; this does not generate one.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Deliberately NOT running migrations or seeding here — with more than one
# replica, every container restart would race the same migration. That's a
# one-off deploy-time step (Render's Pre-Deploy Command, or a manual `php
# artisan migrate --force`) — see docs/DEPLOYMENT.md.

exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
