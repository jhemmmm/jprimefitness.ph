#!/usr/bin/env bash
#
# Refresh the running stack on the on-premise box after a git pull.
#
#   ./tools/deploy.sh
#
# Pulls the current branch, reinstalls dependencies, rebuilds frontend assets
# (public/build is gitignored, so it has to be built here), recreates the app
# and web containers, runs migrations and rebuilds the Laravel caches.
#
# Two ordering rules this script exists to enforce:
#
#   * Containers are recreated with --force-recreate. Compose only recreates a
#     container when its service definition changes, but docker/nginx/*.conf and
#     docker/php/*.ini are single-file bind mounts -- git pull replaces them with
#     a new inode, and a running container keeps the old one mounted.
#
#   * The Laravel caches are rebuilt AFTER the recreate. config:cache bakes in
#     whatever environment the container it runs in happens to have, and that
#     file outlives the container.
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
fail() { printf '\033[1;31mFAIL\033[0m %s\n' "$1" >&2; failed=1; }
ok()   { printf '\033[1;32mok\033[0m   %s\n' "$1"; }
failed=0

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

step "recreate app + web"
docker compose up -d --no-deps --force-recreate app web

step "wait for php-fpm"
for _ in $(seq 1 30); do
    docker compose exec -T app php -v >/dev/null 2>&1 && break
    sleep 1
done

if [[ "${DEPLOY_SKIP_MIGRATE:-0}" != "1" ]]; then
    step "migrate"
    docker compose exec -T app php artisan migrate --force
fi

step "rebuild caches"
docker compose exec -T app php artisan config:clear
docker compose exec -T app php artisan optimize

step "verify"
port="$(docker compose port web 80 2>/dev/null | sed 's/.*://')"
port="${port:-8080}"
base="http://127.0.0.1:${port}"
asset="$(ls public/build/assets | grep -m1 '\.js$')"

env_line="$(docker compose exec -T app php -r \
    'require "vendor/autoload.php"; $app = require "bootstrap/app.php";
     $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
     printf("%s debug=%s", $app->environment(), var_export(config("app.debug"), true));')"
case "${env_line}" in
    "production debug=false") ok  "laravel: ${env_line}" ;;
    *)                        fail "laravel: ${env_line} (expected: production debug=false)" ;;
esac

headers="$(curl -fsSI -H 'Host: jprimefitness.ph' -H 'Accept-Encoding: gzip' \
    "${base}/build/assets/${asset}")"
grep -qi '^content-encoding: gzip' <<<"${headers}" \
    && ok "gzip on ${asset}" \
    || fail "no Content-Encoding: gzip on ${asset} -- nginx is running an old config"
grep -qi '^cache-control: public, max-age=31536000, immutable' <<<"${headers}" \
    && ok "immutable cache header on /build/" \
    || fail "no immutable Cache-Control on /build/ -- nginx is running an old config"

curl -fsS -o /dev/null -H 'Host: jprimefitness.ph' \
    -w 'ok   origin ttfb=%{time_starttransfer}s http=%{http_code}\n' "${base}/"

if [[ "${failed}" -eq 1 ]]; then
    printf '\n\033[1;31mdeploy finished with failed checks\033[0m\n'
    exit 1
fi

printf '\n\033[1;32mdeploy complete\033[0m\n'
