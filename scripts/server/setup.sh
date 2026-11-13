#!/usr/bin/env bash
# Первичная настройка сервера. Запускается один раз от root процессом server-setup.
# Секреты (пароль БД, APP_KEY) генерируются здесь и никогда не покидают сервер.
set -euo pipefail

APP_DIR=/opt/tender-radar
APP_DOMAIN="${1:?Укажите домен первым аргументом}"
DEPLOY_USER=deploy

export DEBIAN_FRONTEND=noninteractive

echo "==> Обновление системы"
apt-get update -q
apt-get upgrade -yq
apt-get install -yq ca-certificates curl ufw fail2ban unattended-upgrades rsync

echo "==> Автоматические обновления безопасности"
dpkg-reconfigure -f noninteractive unattended-upgrades

echo "==> Docker"
if ! command -v docker >/dev/null; then
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
    chmod a+r /etc/apt/keyrings/docker.asc
    . /etc/os-release
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu ${VERSION_CODENAME} stable" \
        > /etc/apt/sources.list.d/docker.list
    apt-get update -q
    apt-get install -yq docker-ce docker-ce-cli containerd.io docker-compose-plugin
fi

echo "==> Пользователь ${DEPLOY_USER} (uid 1000, как www-data в контейнере)"
if ! id "${DEPLOY_USER}" >/dev/null 2>&1; then
    if getent passwd 1000 >/dev/null; then
        useradd -m -s /bin/bash "${DEPLOY_USER}"
    else
        useradd -m -s /bin/bash -u 1000 "${DEPLOY_USER}"
    fi
fi
usermod -aG docker "${DEPLOY_USER}"
install -d -m 700 -o "${DEPLOY_USER}" -g "${DEPLOY_USER}" "/home/${DEPLOY_USER}/.ssh"
install -m 600 -o "${DEPLOY_USER}" -g "${DEPLOY_USER}" /root/.ssh/authorized_keys "/home/${DEPLOY_USER}/.ssh/authorized_keys"

echo "==> Каталог приложения и .env"
install -d -o "${DEPLOY_USER}" -g "${DEPLOY_USER}" "${APP_DIR}" /opt/backups
chown -R "${DEPLOY_USER}:${DEPLOY_USER}" "${APP_DIR}"
if [ ! -f "${APP_DIR}/.env" ]; then
    cp "${APP_DIR}/.env.example" "${APP_DIR}/.env"
    DB_PASSWORD="$(openssl rand -hex 24)"
    sed -i \
        -e "s|^APP_ENV=.*|APP_ENV=production|" \
        -e "s|^APP_DEBUG=.*|APP_DEBUG=false|" \
        -e "s|^APP_URL=.*|APP_URL=https://${APP_DOMAIN}|" \
        -e "s|^APP_DOMAIN=.*|APP_DOMAIN=${APP_DOMAIN}|" \
        -e "s|^LOG_LEVEL=.*|LOG_LEVEL=warning|" \
        -e "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" \
        "${APP_DIR}/.env"
    chown "${DEPLOY_USER}:${DEPLOY_USER}" "${APP_DIR}/.env"
    chmod 600 "${APP_DIR}/.env"
fi

echo "==> Ежедневная резервная копия базы (храним 14 дней)"
cat > /etc/cron.d/tender-radar-backup <<CRON
30 3 * * * ${DEPLOY_USER} cd ${APP_DIR} && docker compose exec -T db sh -c 'pg_dump -U "\$POSTGRES_USER" "\$POSTGRES_DB"' | gzip > /opt/backups/db-\$(date +\%F).sql.gz && find /opt/backups -name 'db-*.sql.gz' -mtime +14 -delete
CRON

echo "==> Фаервол: только SSH, HTTP, HTTPS"
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

echo "==> SSH: только по ключу, root запрещён"
cat > /etc/ssh/sshd_config.d/99-tender-radar.conf <<SSHD
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin no
SSHD
sshd -t
# Ключ root больше не нужен: дальше выкладка идёт от пользователя deploy.
rm -f /root/.ssh/authorized_keys
systemctl reload ssh

systemctl enable --now fail2ban

echo "==> Готово"
