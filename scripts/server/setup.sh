#!/usr/bin/env bash
# Первичная настройка сервера. Запускается один раз от root из веб-консоли:
#   bash /opt/tender-radar/scripts/server/setup.sh <домен>
# К этому моменту репозиторий уже склонирован в /opt/tender-radar ключом /root/dk (создан в консоли).
# Секреты (ключ доступа к GitHub, пароль БД, APP_KEY) создаются на сервере и не покидают его.
set -euo pipefail

APP_DIR=/opt/tender-radar
APP_DOMAIN="${1:?Укажите домен первым аргументом}"
DEPLOY_USER=deploy
REPO=git@github.com:promstroy407/tender-radar.git
# Опубликованный GitHub отпечаток ключа ed25519 github.com.
GITHUB_FINGERPRINT='SHA256:+DiY3wvvV6TuJJhbpZisF/zLDA0zPMSvHdkr4UvCOqU'

export DEBIAN_FRONTEND=noninteractive

echo "==> Обновление системы"
apt-get update -q
apt-get upgrade -yq
apt-get install -yq ca-certificates curl git ufw fail2ban unattended-upgrades

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
DEPLOY_HOME="/home/${DEPLOY_USER}"

echo "==> Ключ доступа к GitHub (только чтение) переходит пользователю ${DEPLOY_USER}"
install -d -m 700 -o "${DEPLOY_USER}" -g "${DEPLOY_USER}" "${DEPLOY_HOME}/.ssh"
if [ -f /root/dk ]; then
    install -m 600 -o "${DEPLOY_USER}" -g "${DEPLOY_USER}" /root/dk "${DEPLOY_HOME}/.ssh/id_ed25519"
    install -m 644 -o "${DEPLOY_USER}" -g "${DEPLOY_USER}" /root/dk.pub "${DEPLOY_HOME}/.ssh/id_ed25519.pub"
    shred -u /root/dk
    rm -f /root/dk.pub
fi
ssh-keyscan -t ed25519 github.com 2>/dev/null > /tmp/github_known_hosts
if ! ssh-keygen -lf /tmp/github_known_hosts | grep -q "${GITHUB_FINGERPRINT}"; then
    echo "Отпечаток github.com не совпал с опубликованным, остановка." >&2
    exit 1
fi
install -m 644 -o "${DEPLOY_USER}" -g "${DEPLOY_USER}" /tmp/github_known_hosts "${DEPLOY_HOME}/.ssh/known_hosts"
rm -f /tmp/github_known_hosts

echo "==> Каталог приложения и .env"
install -d -o "${DEPLOY_USER}" -g "${DEPLOY_USER}" /opt/backups
chown -R "${DEPLOY_USER}:${DEPLOY_USER}" "${APP_DIR}"
sudo -u "${DEPLOY_USER}" git -C "${APP_DIR}" remote set-url origin "${REPO}"
if [ ! -f "${APP_DIR}/.env" ]; then
    install -m 600 -o "${DEPLOY_USER}" -g "${DEPLOY_USER}" "${APP_DIR}/.env.example" "${APP_DIR}/.env"
    DB_PASSWORD="$(openssl rand -hex 24)"
    sed -i \
        -e "s|^APP_ENV=.*|APP_ENV=production|" \
        -e "s|^APP_DEBUG=.*|APP_DEBUG=false|" \
        -e "s|^APP_URL=.*|APP_URL=https://${APP_DOMAIN}|" \
        -e "s|^APP_DOMAIN=.*|APP_DOMAIN=${APP_DOMAIN}|" \
        -e "s|^LOG_LEVEL=.*|LOG_LEVEL=warning|" \
        -e "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" \
        "${APP_DIR}/.env"
fi

echo "==> Автовыкладка: каждые 2 минуты проверяем ветку production"
cat > /etc/systemd/system/tender-radar-deploy.service <<UNIT
[Unit]
Description=Тендер-Радар: выкладка новой версии из ветки production
After=docker.service network-online.target
Wants=network-online.target

[Service]
Type=oneshot
User=${DEPLOY_USER}
WorkingDirectory=${APP_DIR}
ExecStart=/bin/bash ${APP_DIR}/scripts/server/pull-deploy.sh
UNIT
cat > /etc/systemd/system/tender-radar-deploy.timer <<UNIT
[Unit]
Description=Тендер-Радар: проверка обновлений

[Timer]
OnBootSec=1min
OnUnitActiveSec=2min

[Install]
WantedBy=timers.target
UNIT
systemctl daemon-reload
systemctl enable --now tender-radar-deploy.timer

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

echo "==> SSH: пароли и root запрещены (управление — через веб-консоль FirstVDS)"
cat > /etc/ssh/sshd_config.d/99-tender-radar.conf <<SSHD
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin no
SSHD
sshd -t
systemctl reload ssh
systemctl enable --now fail2ban

echo "==> Первая выкладка"
systemctl start tender-radar-deploy.service
journalctl -u tender-radar-deploy.service -n 15 --no-pager

echo "==> Готово: https://${APP_DOMAIN}"
