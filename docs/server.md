# Сервер Тендер-Радара

- Сервер: FirstVDS, Москва, `promstroy4071.fvds.ru`, 45.10.218.63, Ubuntu 24.04.
- Адрес приложения: https://tender.es12.ru (A-запись `tender` в зоне es12.ru, DNSmanager FirstVDS).
- Каталог: `/opt/tender-radar`, пользователь `deploy`. Секреты — только в `/opt/tender-radar/.env`.
- Вход: веб-консоль FirstVDS (root). SSH по паролю и root по SSH закрыты.

## Выкладка

CI на зелёном коммите в `main` переносит его в ветку `production`.
Таймер `tender-radar-deploy.timer` раз в 2 минуты забирает `production` и запускает `scripts/server/deploy.sh`.

```
systemctl list-timers tender-radar-deploy.timer
journalctl -u tender-radar-deploy.service -n 50
```

## Бэкапы

Ежедневно в 3:30 `pg_dump` в `/opt/backups/db-ГГГГ-ММ-ДД.sql.gz`, хранятся 14 дней.

## Telegram через пересыльщик

С московских серверов FirstVDS `api.telegram.org` недоступен. Все запросы к Bot API идут
через пересыльщик `https://tg.udallenka.ru/<секретный-путь>` (у Тендер-Радара свой путь,
отдельный от сайта es12.ru). Адрес собирается так: `{TELEGRAM_API_URL}/bot{TOKEN}/{метод}`.
Через пересыльщик ходит только Telegram; zakupki.gov.ru и остальное — напрямую.

Переменные в `.env` (значения в репозиторий не пишутся, в ошибках и журналах заменяются на `***`):

| Переменная | Что это |
|---|---|
| `TELEGRAM_BOT_TOKEN` | токен бота от @BotFather |
| `TELEGRAM_API_URL` | `https://tg.udallenka.ru/<секретный-путь>`; пусто — `https://api.telegram.org` |
| `TELEGRAM_WEBHOOK_SECRET` | создаётся командой `php artisan telegram:webhook-secret` (значение не выводится) |
| `TELEGRAM_UPDATES_MODE` | `webhook` (по умолчанию) или `polling` |

После правки `.env`: `docker compose run --rm --no-deps app php artisan optimize`.

Проверка:

```
cd /opt/tender-radar
docker compose run --rm --no-deps app php artisan telegram:webhook me     # бот отвечает через пересыльщик
docker compose run --rm --no-deps app php artisan telegram:webhook set    # Telegram будет слать на /telegram/webhook
docker compose run --rm --no-deps app php artisan telegram:webhook info   # смотреть «последняя ошибка»
```

Webhook идёт напрямую от Telegram на https://tender.es12.ru/telegram/webhook и проверяет
заголовок `X-Telegram-Bot-Api-Secret-Token` (без него — 403).

Если в `info` видны ошибки доставки и сообщения боту не доходят — запасной режим:
`TELEGRAM_UPDATES_MODE=polling` в `.env`, затем `optimize`. Планировщик раз в минуту
запускает `telegram:poll`: он снимает webhook (`deleteWebhook`) и забирает сообщения
через `getUpdates` (ожидание 25 с) через пересыльщик.
