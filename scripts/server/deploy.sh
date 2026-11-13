#!/usr/bin/env bash
# Выкладка новой версии. Запускается процессом deploy от пользователя deploy
# после того, как код уже скопирован в /opt/tender-radar.
set -euo pipefail

cd /opt/tender-radar

docker compose build --quiet
docker compose up -d db redis

docker compose run --rm --no-deps app composer install --no-dev --optimize-autoloader --no-interaction --no-progress

if ! grep -q '^APP_KEY=base64:' .env; then
    docker compose run --rm --no-deps app php artisan key:generate --force
fi

docker compose run --rm app php artisan migrate --force
docker compose run --rm app php artisan db:seed --force
docker compose run --rm --no-deps app php artisan optimize

docker compose up -d --remove-orphans
docker compose restart queue scheduler

docker image prune -f >/dev/null

echo "Выкладка завершена: $(git log -1 --format='%h %s' 2>/dev/null || cat REVISION)"
