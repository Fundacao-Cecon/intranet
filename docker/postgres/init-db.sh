#!/bin/sh
set -eu
psql --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
    --set ON_ERROR_STOP=1 --set db_name="$POSTGRES_DB" \
    --set migration_password="$MIGRATION_PASSWORD" --set runtime_password="$RUNTIME_PASSWORD" <<'SQL'
CREATE ROLE intranet_migrator LOGIN PASSWORD :'migration_password';
CREATE ROLE intranet_app LOGIN PASSWORD :'runtime_password';
ALTER DATABASE :"db_name" OWNER TO intranet_migrator;
ALTER SCHEMA public OWNER TO intranet_migrator;
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO intranet_app;
ALTER DEFAULT PRIVILEGES FOR ROLE intranet_migrator IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO intranet_app;
ALTER DEFAULT PRIVILEGES FOR ROLE intranet_migrator IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO intranet_app;
SQL
