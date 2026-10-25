#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
docker compose exec -T mariadb bash -lc 'mariadb -u root -p"$MARIADB_ROOT_PASSWORD" <<SQL
CREATE DATABASE IF NOT EXISTS zionfood_test;
CREATE DATABASE IF NOT EXISTS zionfood_load_test;
GRANT ALL PRIVILEGES ON zionfood_test.* TO '\''zionfood'\''@'\''%'\'';
GRANT ALL PRIVILEGES ON zionfood_load_test.* TO '\''zionfood'\''@'\''%'\'';
SQL'
