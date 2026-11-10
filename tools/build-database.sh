#!/usr/bin/env bash
# Developer tool: rebuilds database/fahim_store.sql = schema + starter data.
# Usage: DB_USER=root DB_NAME=fahim_build tools/build-database.sh
set -euo pipefail
cd "$(dirname "$0")/.."
DB_NAME="${DB_NAME:-fahim_build}"
MYSQL="mysql -u${DB_USER:-root} ${DB_PASS:+-p$DB_PASS}"
DUMP="mysqldump -u${DB_USER:-root} ${DB_PASS:+-p$DB_PASS}"
$MYSQL -e "DROP DATABASE IF EXISTS \`$DB_NAME\`; CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
$MYSQL "$DB_NAME" < database/schema.sql
rm -rf uploads/products/sample/* logs/cache
FS_DB_NAME="$DB_NAME" FS_DB_USER="${DB_USER:-root}" FS_DB_PASS="${DB_PASS:-}" php -d display_errors=1 tools/seed.php
{
  echo "-- ====================================================================="
  echo "--  Fahim Store - complete database (schema + starter data)"
  echo "--  Import into an EMPTY database with phpMyAdmin or:"
  echo "--    mysql -u USER -p DATABASE < database/fahim_store.sql"
  echo "--  Admin login: admin@fahimstore.pk / Admin@123  (change after login!)"
  echo "-- ====================================================================="
  cat database/schema.sql
  echo
  echo "-- ---------------------------------------------------------------------"
  echo "--  STARTER DATA"
  echo "-- ---------------------------------------------------------------------"
  echo "SET NAMES utf8mb4;"
  echo "SET FOREIGN_KEY_CHECKS = 0;"
  $DUMP --no-create-info --skip-triggers --complete-insert --skip-comments --skip-add-locks --skip-disable-keys --compact "$DB_NAME" \
     --ignore-table="$DB_NAME.login_attempts" --ignore-table="$DB_NAME.search_logs"
  echo "SET FOREIGN_KEY_CHECKS = 1;"
} > database/fahim_store.sql
echo "Wrote database/fahim_store.sql ($(wc -c < database/fahim_store.sql) bytes)"
