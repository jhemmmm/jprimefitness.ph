#!/usr/bin/env bash
#
# Build a deployable bundle of the app (vendor/ and public/build/ included,
# dev tooling and local state excluded) into
#   dist/jprimefitness.ph-<version>.zip  +  dist/jprimefitness.ph-<version>.zip.sha256
#
# Mirrors .github/workflows/release.yml so the same bundle can be produced from
# a local checkout; keep the exclusion lists in the two files in sync.
#
# Run from anywhere; it always operates on the repo that contains this script.
#
# WARNING: this runs `composer install --no-dev` in the checkout, which removes
# dev dependencies (phpunit, pint, boost, ...). Run `composer install` afterwards
# to restore them.
#
# Environment overrides:
#   BUNDLE_VERSION=<label>   Version label used in the zip name
#                            (default: git describe --tags --always).
#   BUNDLE_SKIP_BUILD=1      Skip composer/npm/build steps and only zip the
#                            current tree (useful for checking the packaging).

set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${repo_root}"

if ! command -v zip >/dev/null 2>&1; then
    echo "error: 'zip' is required but not installed." >&2
    exit 1
fi

version="${BUNDLE_VERSION:-$(git describe --tags --always)}"
dist_dir="dist"
zip_name="jprimefitness.ph-${version}.zip"
zip_path="${dist_dir}/${zip_name}"

# Directories the app writes to at runtime: shipped empty, with their
# .gitignore placeholder, so the extracted tree works without extra mkdirs.
runtime_dirs=(
    storage/framework/cache/data
    storage/framework/sessions
    storage/framework/views
    storage/logs
    storage/app/public
    storage/app/private
    bootstrap/cache
)

if [ "${BUNDLE_SKIP_BUILD:-0}" != "1" ]; then
    composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
    npm ci
    npm run build

    rm -f public/hot
    rm -f bootstrap/cache/*.php
fi

for dir in "${runtime_dirs[@]}"; do
    mkdir -p "${dir}"
    [ -e "${dir}/.gitignore" ] || printf '*\n!.gitignore\n' > "${dir}/.gitignore"
done

mkdir -p "${dist_dir}"
rm -f "${zip_path}" "${zip_path}.sha256"

zip -r -q "${zip_path}" . \
    -x '.git/*' '.github/*' '.agents/*' '.codex/*' '.claude/*' '.vscode/*' \
       'tests/*' 'node_modules/*' \
       'storage/app/hikvision-sdk/*' 'storage/app/public/*' 'storage/app/private/*' \
       'storage/logs/*' 'storage/framework/cache/data/*' 'storage/framework/sessions/*' 'storage/framework/views/*' \
       '.env' '.env.*' 'docker/*' 'docker-compose.yml' 'Dockerfile' \
       'graphify-out/*' '.graphify*' '.phpunit.result.cache' '_ide_helper.php' 'boost.json' 'LEAN-CTX.md' \
       'public/hot' 'bootstrap/cache/*.php' 'public/storage' 'public/storage/*' 'database/*.sqlite' "${dist_dir}/*"

# .env.* is excluded above; .env.example is the one that belongs in the bundle.
zip -q "${zip_path}" .env.example

# Keep the (empty) runtime directories with their .gitignore placeholders.
for dir in "${runtime_dirs[@]}"; do
    zip -q "${zip_path}" "${dir}/" "${dir}/.gitignore"
done

(cd "${dist_dir}" && sha256sum "${zip_name}" > "${zip_name}.sha256")

echo "Bundle written to ${zip_path}"
cat "${zip_path}.sha256"
