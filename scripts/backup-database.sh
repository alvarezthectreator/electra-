#!/bin/sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
BACKUP_DIR="${BACKUP_DIR:-$ROOT_DIR/storage/backups}"
MYSQL_BIN="${MYSQL_BIN:-/Applications/MAMP/Library/bin/mysql80/bin/mysqldump}"
DB_HOST="${ELYS_DB_HOST:-127.0.0.1}"
DB_PORT="${ELYS_DB_PORT:-8889}"
DB_NAME="${ELYS_DB_NAME:-angel}"
DB_USER="${ELYS_DB_USER:-root}"
DB_PASS="${ELYS_DB_PASS:-root}"

mkdir -p "$BACKUP_DIR"
STAMP=$(date +%Y%m%d-%H%M%S)
OUTPUT="$BACKUP_DIR/${DB_NAME}-${STAMP}.sql"
MYSQL_PWD="$DB_PASS" "$MYSQL_BIN" --single-transaction --routines --triggers -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DB_NAME" > "$OUTPUT"
printf 'Database backup written to %s\n' "$OUTPUT"
