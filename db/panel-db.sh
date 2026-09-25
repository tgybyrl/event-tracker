#!/bin/sh
# Creates the panel's own database and the MySQL user it connects as.
#
# The panel keeps its accounts, sessions and cache in panel_db, and the
# `panel` user can reach panel_db and nothing else. It has no rights at all
# on events_db: event data reaches the panel only through the Go API.
#
# Runs by itself on a fresh volume (docker-compose.yaml mounts it into
# /docker-entrypoint-initdb.d). On an existing volume run it once by hand:
#   docker compose exec db sh /docker-entrypoint-initdb.d/02-panel-db.sh
# Safe to run again: every statement is IF NOT EXISTS.
set -e

: "${PANEL_DB_PASSWORD:?PANEL_DB_PASSWORD is not set; see .env.example}"

mysql -uroot -p"$MYSQL_ROOT_PASSWORD" <<SQL
CREATE DATABASE IF NOT EXISTS panel_db;
CREATE USER IF NOT EXISTS 'panel'@'%' IDENTIFIED BY '${PANEL_DB_PASSWORD}';
GRANT ALL PRIVILEGES ON panel_db.* TO 'panel'@'%';
SQL
