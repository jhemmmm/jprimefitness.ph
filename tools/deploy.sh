#!/usr/bin/env bash
#
# Refresh the running stack on the on-premise box after a git pull.
#
#   ./tools/deploy.sh
#
# Pulls the current branch, reinstalls dependencies, rebuilds frontend assets
# (public/build is gitignored, so it has to be built here), runs migrations,
# rebuilds the Laravel caches and recreates the app + web containers.
#
# Run from anywhere; it always operates on the repo that contains this script.
#
# Environment overrides:
#   DEPLOY_SKIP_PULL=1      Use the working tree as-is instead of pulling.
#   DEPLOY_SKIP_ASSETS=1    Skip npm ci && npm run build.
#   DEPLOY_SKIP_MIGRATE=1   Skip php artisan migrate.

set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${repo_root}"

step() { printf '\n\033[1;36m==> %s\033[0m\n' "$1"; }

if [[ "${DEPLOY_SKIP_PULL:-0}" != "1" ]]; then
    if ! git diff --quiet || ! git diff --cached --quiet; then
        echo "error: working tree has uncommitted changes; commit or discard them first:" >&2
        git status --short >&2
        exit 1
    fi

    step "git pull"
    git pull --ff-only
fi

step "composer install"
docker compose exec -T app \
    composer install --no-interaction --prefer-dist --optimize-autoloader

if [[ "${DEPLOY_SKIP_ASSETS:-0}" != "1" ]]; then
    step "build frontend assets"
    docker compose --profile node run --rm --no-deps node \
        sh -lc 'npm ci && npm run build'
fi

if [[ "${DEPLOY_SKIP_MIGRATE:-0}" != "1" ]]; then
    step "migrate"
    docker compose exec -T app php artisan migrate --force
fi

step "rebuild caches"
# config:clear first so `optimize` caches the current .env rather than folding
# a stale cached config back in.
docker compose exec -T app php artisan config:clear
docker compose exec -T app php artisan optimize

step "recreate app + web"
# Picks up docker-compose.yml / php.ini / fpm changes and clears OPcache.
docker compose up -d --no-deps app web

step "smoke test"
port="$(docker compose port web 80 2>/dev/null | sed 's/.*://')"
curl -fsS -o /dev/null \
    -w 'origin  ttfb=%{time_starttransfer}s  total=%{time_total}s  http=%{http_code}\n' \
    -H 'Host: jprimefitness.ph' "http://127.0.0.1:${port:-8080}/"
curl -fsS -o /dev/null \
    -H 'Accept-Encoding: gzip, br' \
    -w 'css     encoding=%{content_type}  bytes=%{size_download}\n' \
    -H 'Host: jprimefitness.ph' "http://127.0.0.1:${port:-8080}/build/assets/$(
        docker compose exec -T web sh -c 'ls /var/www/html/public/build/assets | grep -m1 "\.css$"' | tr -d "\r"
    )"

printf '\n\033[1;32mdeploy complete\033[0m\n'
