#!/usr/bin/env zsh
# NovaWebhook - Test container entry script
# This file is licensed under the MIT License; See LICENSE for full text.

set -euo pipefail
cd "$APP_HOME/mediawiki"

mkdir -p /tmp/nova-test-php
print -r -- 'apc.enable_cli=1' > /tmp/nova-test-php/apcu.ini
export PHP_INI_SCAN_DIR="${PHP_INI_SCAN_DIR:-/usr/local/etc/php/conf.d}:/tmp/nova-test-php"

trap 'sleep 1' EXIT  # let stdout settle first

local -a opt_all opt_stop
zparseopts -D -E -- -all=opt_all -stop=opt_stop

printf '%s\n' 'Initializing test environment...'

PASS="$(php -r 'echo bin2hex(random_bytes(12));')"
printf 'Using NovaAdmin:%s for wiki admin if you somehow need it\n' "$PASS"

php maintenance/run.php install \
    --dbname='nova_wiki' \
    --dbserver='database' \
    --installdbuser='nova' \
    --installdbpass="$NOVA_DB_PASSWORD" \
    --dbuser='nova' \
    --dbpass="$NOVA_DB_PASSWORD" \
    --server="https://novawebhook.local/" \
    --scriptpath='' \
    --lang='en' \
    --pass="$PASS" "NovaWebhook" "NovaAdmin"

cp -fv LocalSettings.new LocalSettings.php

printf '\n%s\n\n' '=== Starting Tests ==='
git rev-parse HEAD
php --version
composer show phpunit/phpunit --no-ansi

local -a phpunit_args=("extensions/NovaWebhook/tests/phpunit" "$@")
(( ${#opt_stop} )) && phpunit_args=("--stop-on-failure" "${phpunit_args[@]}")

if (( ${#opt_all} )); then
    composer phpunit:entrypoint -- "${phpunit_args[@]}" 2>&1
else
    { composer phpunit:entrypoint -- "${phpunit_args[@]}" 2>&1 } | grep -Evi 'DeferredUpdates|objectcache'
fi
