#!/bin/sh
set -e

if [ "$1" = "frankenphp" ] || [ "${1#-}" != "$1" ]; then
    attempts=0
    until php bin/console dbal:run-sql -q "SELECT 1" >/dev/null 2>&1; do
        attempts=$((attempts + 1))
        if [ "$attempts" -ge 30 ]; then
            echo "Database unreachable, giving up." >&2
            exit 1
        fi
        echo "Waiting for the database..."
        sleep 2
    done

    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
    php bin/console cache:warmup
fi

exec docker-php-entrypoint "$@"
