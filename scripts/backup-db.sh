#!/usr/bin/env bash
set -euo pipefail

TIMESTAMP=$(date +%Y%m%d-%H%M%S)
DB_CONNECTION="${DB_CONNECTION:-sqlite}"

if [ "$DB_CONNECTION" = "sqlite" ]; then
  DB_PATH="${DB_DATABASE:-database/database.sqlite}"
  if [ -f "$DB_PATH" ]; then
    mkdir -p storage/backups
    cp "$DB_PATH" "storage/backups/socialpilot-$TIMESTAMP.sqlite"
    echo "Backed up SQLite database to storage/backups/socialpilot-$TIMESTAMP.sqlite"
  else
    echo "No SQLite database found at $DB_PATH"
    exit 1
  fi
else
  mysqldump \
    --host="${DB_HOST:-127.0.0.1}" \
    --port="${DB_PORT:-3306}" \
    --user="${DB_USERNAME:-root}" \
    --password="${DB_PASSWORD}" \
    "${DB_DATABASE:-socialpilot}" > "storage/backups/socialpilot-$TIMESTAMP.sql"
  echo "Backed up MySQL database to storage/backups/socialpilot-$TIMESTAMP.sql"
fi
