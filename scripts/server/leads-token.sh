#!/usr/bin/env bash
# Токен для заявок с сайта: записывает LEADS_API_TOKEN в .env и присылает его владельцу ботом.
# Запуск из веб-консоли (без символов с Shift): bash /opt/tender-radar/scripts/server/leads-token.sh
# Заменить существующий: bash /opt/tender-radar/scripts/server/leads-token.sh --force
set -euo pipefail

cd /opt/tender-radar

docker compose run --rm --no-deps app php artisan leads:token "$@"
