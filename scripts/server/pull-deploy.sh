#!/usr/bin/env bash
# Запускается таймером systemd от пользователя deploy.
# Если в ветке production появилась новая версия (а туда она попадает только
# после зелёного CI), обновляет код и выкладывает его.
set -euo pipefail

cd /opt/tender-radar

git fetch --quiet origin production

LOCAL="$(git rev-parse HEAD)"
REMOTE="$(git rev-parse origin/production)"
MARKER=storage/app/.deployed-revision

if [ "${LOCAL}" = "${REMOTE}" ] && [ "$(cat "${MARKER}" 2>/dev/null)" = "${REMOTE}" ]; then
    exit 0
fi

echo "Новая версия: ${REMOTE}"
git reset --quiet --hard "${REMOTE}"

bash scripts/server/deploy.sh

echo "${REMOTE}" > "${MARKER}"
