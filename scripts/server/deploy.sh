#!/usr/bin/env bash
# Выкладка текущей версии кода из /opt/tender-radar. Запускает pull-deploy.sh.
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

# Нормативы ГЭСН и ставки труда: если ещё не загружены или устарели — в очередь (не блокирует выкладку).
docker compose exec -T app php artisan normatives:sync --if-stale=7 || true

docker image prune -f >/dev/null

echo "Выкладка завершена: $(git log -1 --format='%h %s')"
