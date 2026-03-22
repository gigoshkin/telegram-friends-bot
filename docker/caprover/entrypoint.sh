#!/bin/bash
set -euo pipefail

PG_BIN="/usr/lib/postgresql/16/bin"
PG_DATA="/var/lib/postgresql/data"
POSTGRES_PASSWORD="${POSTGRES_PASSWORD:-changeme}"

export DATABASE_URL="${DATABASE_URL:-postgresql://app:${POSTGRES_PASSWORD}@127.0.0.1:5432/app?serverVersion=16&charset=utf8}"

# ── 1. Initialize PostgreSQL data directory (first deploy only) ───────────────
if [ ! -s "${PG_DATA}/PG_VERSION" ]; then
    echo "==> Initializing PostgreSQL data directory..."
    install -d -m 0700 -o postgres "${PG_DATA}"
    su -m postgres -c "${PG_BIN}/initdb -D ${PG_DATA} --username=postgres --auth-local=trust --auth-host=md5"
    echo "==> PostgreSQL initialized."
fi

# ── 2. Start PostgreSQL temporarily for setup / migrations ────────────────────
echo "==> Starting PostgreSQL temporarily..."
su -m postgres -c "${PG_BIN}/pg_ctl start -D ${PG_DATA} -w -o '-c listen_addresses=127.0.0.1'"

# ── 3. Create app user, database, and pg_trgm extension (idempotent) ──────────
echo "==> Provisioning database..."
su -m postgres -c "psql -tc \"SELECT 1 FROM pg_roles WHERE rolname='app'\" | grep -q 1 || \
    psql -c \"CREATE USER app WITH PASSWORD '${POSTGRES_PASSWORD}';\"" || true

su -m postgres -c "psql -tc \"SELECT 1 FROM pg_database WHERE datname='app'\" | grep -q 1 || \
    psql -c \"CREATE DATABASE app OWNER app;\"" || true

su -m postgres -c "psql -d app -c \"CREATE EXTENSION IF NOT EXISTS pg_trgm;\"" || true

# Update password in case it changed
su -m postgres -c "psql -c \"ALTER USER app WITH PASSWORD '${POSTGRES_PASSWORD}';\"" || true

# ── 4. Run Doctrine migrations ────────────────────────────────────────────────
echo "==> Running migrations..."
cd /app && php bin/console doctrine:migrations:migrate --no-interaction

# ── 5. Stop temporary PostgreSQL ──────────────────────────────────────────────
echo "==> Stopping temporary PostgreSQL..."
su -m postgres -c "${PG_BIN}/pg_ctl stop -D ${PG_DATA} -w"

# ── 6. Hand off to supervisord ────────────────────────────────────────────────
echo "==> Starting supervisord..."
exec "$@"
