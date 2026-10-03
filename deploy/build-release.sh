#!/usr/bin/env bash
# Builds a cPanel-ready zip: production vendor/, compiled assets, no dev files, no .env.
set -euo pipefail
cd "$(dirname "$0")/.."
REL=release; rm -rf "$REL" && mkdir -p "$REL"
npm ci --no-audit --no-fund && npm run build
rsync -a --exclude-from=deploy/release-exclude.txt ./ "$REL/fspra/"
( cd "$REL/fspra" && composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-scripts && composer dump-autoload -o --no-dev --classmap-authoritative )
cp deploy/.env.production.example "$REL/fspra/.env.example"
( cd "$REL" && zip -qr fspra-release.zip fspra )
echo "Built $REL/fspra-release.zip ($(du -h $REL/fspra-release.zip | cut -f1))"
