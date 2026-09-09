#!/bin/sh
set -eu

attempt=1
max_attempts="${DB_STARTUP_MAX_ATTEMPTS:-30}"

php bin/preflight.php

until php bin/migrate.php; do
    if [ "$attempt" -ge "$max_attempts" ]; then
        echo "Não foi possível preparar o banco após ${max_attempts} tentativas." >&2
        exit 1
    fi
    echo "Banco ainda indisponível; nova tentativa ${attempt}/${max_attempts} em 2 segundos." >&2
    attempt=$((attempt + 1))
    sleep 2
done

exec apache2-foreground
