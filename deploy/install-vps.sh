#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DB_NAME="${DB_NAME:-ltz_operational_intelligence}"
DB_USER="${DB_USER:-ltz_app}"
DB_USER_HOST="${DB_USER_HOST:-localhost}"
DB_PASS="${DB_PASS:-}"
WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-www-data}"
INSTALL_SEED="${INSTALL_SEED:-yes}"
RESET_DB="${RESET_DB:-no}"
INSTALL_PACKAGES="${INSTALL_PACKAGES:-no}"
CONFIGURE_NGINX="${CONFIGURE_NGINX:-no}"
SITE_NAME="${SITE_NAME:-ltz}"
DOMAIN="${DOMAIN:-}"
PHP_FPM_SOCKET="${PHP_FPM_SOCKET:-}"
MYSQL_CNF=""

if [[ "${EUID}" -eq 0 ]]; then
    SUDO=""
else
    SUDO="sudo"
fi

fail() {
    echo "ERROR: $*" >&2
    exit 1
}

info() {
    echo "==> $*"
}

require_file() {
    [[ -f "$1" ]] || fail "Missing required file: $1"
}

sql_escape() {
    printf "%s" "$1" | sed "s/'/''/g"
}

php_escape() {
    printf "%s" "$1" | sed "s/\\\\/\\\\\\\\/g; s/'/\\\\'/g"
}

valid_identifier() {
    [[ "$1" =~ ^[A-Za-z0-9_]+$ ]]
}

require_file "$PROJECT_ROOT/database/schema.sql"
require_file "$PROJECT_ROOT/database/seed.sql"
require_file "$PROJECT_ROOT/config/config.php.example"
require_file "$PROJECT_ROOT/public/index.php"

valid_identifier "$DB_NAME" || fail "DB_NAME may only contain letters, numbers, and underscores."
valid_identifier "$DB_USER" || fail "DB_USER may only contain letters, numbers, and underscores."

if [[ "$INSTALL_PACKAGES" == "yes" ]]; then
    info "Installing system packages"
    $SUDO apt-get update
    $SUDO apt-get install -y nginx mysql-server php-fpm php-mysql
fi

command -v mysql >/dev/null 2>&1 || fail "mysql client is not installed."
command -v php >/dev/null 2>&1 || fail "php is not installed."

if [[ -z "$DB_PASS" ]]; then
    if command -v openssl >/dev/null 2>&1; then
        DB_PASS="$(openssl rand -base64 30 | tr -d '\n')"
    else
        DB_PASS="$(tr -dc 'A-Za-z0-9_@%+=' < /dev/urandom | head -c 32)"
    fi
    GENERATED_PASS="yes"
else
    GENERATED_PASS="no"
fi

DB_PASS_SQL="$(sql_escape "$DB_PASS")"
DB_USER_HOST_SQL="$(sql_escape "$DB_USER_HOST")"
DB_PASS_PHP="$(php_escape "$DB_PASS")"

info "Preparing MySQL database and user"
MYSQL_ADMIN_SQL="$(mktemp)"
trap 'rm -f "$MYSQL_ADMIN_SQL" "${MYSQL_CNF:-}"' EXIT

{
    if [[ "$RESET_DB" == "yes" ]]; then
        echo "DROP DATABASE IF EXISTS \`$DB_NAME\`;"
    fi
    echo "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    echo "CREATE USER IF NOT EXISTS '$DB_USER'@'$DB_USER_HOST_SQL' IDENTIFIED BY '$DB_PASS_SQL';"
    echo "ALTER USER '$DB_USER'@'$DB_USER_HOST_SQL' IDENTIFIED BY '$DB_PASS_SQL';"
    echo "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'$DB_USER_HOST_SQL';"
    echo "FLUSH PRIVILEGES;"
} > "$MYSQL_ADMIN_SQL"

$SUDO mysql < "$MYSQL_ADMIN_SQL"

MYSQL_CNF="$(mktemp)"
chmod 600 "$MYSQL_CNF"
cat > "$MYSQL_CNF" <<EOF
[client]
user=$DB_USER
password=$DB_PASS
host=localhost
EOF

TABLE_COUNT="$($SUDO mysql --batch --skip-column-names -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$DB_NAME';")"
if [[ "$TABLE_COUNT" == "0" ]]; then
    info "Importing database schema"
    mysql --defaults-extra-file="$MYSQL_CNF" "$DB_NAME" < "$PROJECT_ROOT/database/schema.sql"
else
    info "Database already contains tables; skipping schema import. Use RESET_DB=yes for a fresh reinstall."
fi

USER_COUNT="0"
if mysql --defaults-extra-file="$MYSQL_CNF" "$DB_NAME" --batch --skip-column-names -e "SHOW TABLES LIKE 'users';" | grep -q '^users$'; then
    USER_COUNT="$(mysql --defaults-extra-file="$MYSQL_CNF" "$DB_NAME" --batch --skip-column-names -e "SELECT COUNT(*) FROM users;")"
fi

if [[ "$INSTALL_SEED" == "yes" && "$USER_COUNT" == "0" ]]; then
    info "Importing seed data"
    mysql --defaults-extra-file="$MYSQL_CNF" "$DB_NAME" < "$PROJECT_ROOT/database/seed.sql"
elif [[ "$INSTALL_SEED" == "yes" ]]; then
    info "Seed data already appears to exist; skipping seed import."
else
    info "Skipping seed data import"
fi

CONFIG_FILE="$PROJECT_ROOT/config/config.php"
info "Writing application config"
$SUDO tee "$CONFIG_FILE" >/dev/null <<EOF
<?php

return [
    'app_name' => 'LTZ Operational Intelligence',
    'db_host' => '127.0.0.1',
    'db_port' => '3306',
    'db_name' => '$DB_NAME',
    'db_user' => '$DB_USER',
    'db_pass' => '$DB_PASS_PHP',
];
EOF

info "Setting filesystem ownership and permissions"
$SUDO chown -R "$WEB_USER:$WEB_GROUP" "$PROJECT_ROOT"
$SUDO find "$PROJECT_ROOT" -type d -exec chmod 755 {} \;
$SUDO find "$PROJECT_ROOT" -type f -exec chmod 644 {} \;
$SUDO chmod 640 "$CONFIG_FILE"
$SUDO chmod 755 "$PROJECT_ROOT/deploy/install-vps.sh"

if [[ "$CONFIGURE_NGINX" == "yes" ]]; then
    [[ -n "$DOMAIN" ]] || fail "DOMAIN is required when CONFIGURE_NGINX=yes."

    if [[ -z "$PHP_FPM_SOCKET" ]]; then
        PHP_FPM_SOCKET="$(find /run/php -maxdepth 1 -name 'php*-fpm.sock' 2>/dev/null | sort -V | tail -n 1 || true)"
    fi
    [[ -n "$PHP_FPM_SOCKET" ]] || fail "Could not detect PHP-FPM socket. Set PHP_FPM_SOCKET=/run/php/phpX.Y-fpm.sock."

    info "Writing Nginx site for $DOMAIN"
    $SUDO tee "/etc/nginx/sites-available/$SITE_NAME" >/dev/null <<EOF
server {
    listen 80;
    server_name $DOMAIN;

    root $PROJECT_ROOT/public;
    index index.php;

    location / {
        try_files \$uri /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:$PHP_FPM_SOCKET;
    }

    location ~ /\. {
        deny all;
    }
}
EOF
    $SUDO ln -sfn "/etc/nginx/sites-available/$SITE_NAME" "/etc/nginx/sites-enabled/$SITE_NAME"
    $SUDO nginx -t
    $SUDO systemctl reload nginx
fi

info "Install complete"
echo
echo "Project root: $PROJECT_ROOT"
echo "Database:     $DB_NAME"
echo "Database user:$DB_USER@$DB_USER_HOST"
if [[ "$GENERATED_PASS" == "yes" ]]; then
    echo "Generated DB password: $DB_PASS"
    echo "This password has been written to config/config.php. Store it somewhere safe."
fi
echo
echo "Seed login password: ChangeMe123!"
echo "Change seed passwords before client use."
if [[ "$CONFIGURE_NGINX" != "yes" ]]; then
    echo
    echo "Nginx was not configured. Re-run with CONFIGURE_NGINX=yes DOMAIN=yourdomain.com to create the site."
fi
