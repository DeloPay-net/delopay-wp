#!/usr/bin/env bash
#
# Build WordPress.org submission zips for the WP DeloPay plugin and
# the DeloPay Shop theme.
#
# Output:
#   dist/wp-delopay.zip       (plugin slug: wp-delopay)
#   dist/delopay-shop.zip     (theme slug:  delopay-shop)
#
# What is excluded:
#   * VCS:           .git, .gitignore, .gitattributes
#   * Node:          node_modules, package.json, package-lock.json, src/, tailwind.config.js
#   * OS / editor:   .DS_Store, Thumbs.db, .idea, .vscode, *.swp
#   * Repo-only:     wp-test/, README.md (readme.txt ships instead), bin/, dist/
#
# Pre-requisites:
#   - rsync, zip
#   - The theme's compiled CSS at theme/assets/css/tailwind.css must exist.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST="${ROOT}/dist"
STAGE="${DIST}/_stage"

PLUGIN_SLUG="wp-delopay"
THEME_SLUG="delopay-shop"

# -----------------------------------------------------------------------------
# Excludes shared by both packages.
# -----------------------------------------------------------------------------
COMMON_EXCLUDES=(
  --exclude='.git'
  --exclude='.gitignore'
  --exclude='.gitattributes'
  --exclude='node_modules'
  --exclude='package.json'
  --exclude='package-lock.json'
  --exclude='pnpm-lock.yaml'
  --exclude='yarn.lock'
  --exclude='.DS_Store'
  --exclude='Thumbs.db'
  --exclude='.idea'
  --exclude='.vscode'
  --exclude='*.swp'
  --exclude='*.swo'
  --exclude='*~'
  --exclude='README.md'
)

# Theme-only build artifacts (sources kept in repo, not shipped).
THEME_EXCLUDES=(
  --exclude='src'
  --exclude='tailwind.config.js'
  --exclude='postcss.config.js'
)

# -----------------------------------------------------------------------------

if ! command -v rsync >/dev/null 2>&1; then
  echo "error: rsync is required" >&2
  exit 1
fi
if ! command -v zip >/dev/null 2>&1; then
  echo "error: zip is required" >&2
  exit 1
fi

if [[ ! -f "${ROOT}/theme/assets/css/tailwind.css" ]]; then
  echo "error: theme/assets/css/tailwind.css is missing — run 'npm run build' in theme/ first" >&2
  exit 1
fi

rm -rf "${DIST}"
mkdir -p "${STAGE}"

# Plugin --------------------------------------------------------------------
PLUGIN_STAGE="${STAGE}/${PLUGIN_SLUG}"
mkdir -p "${PLUGIN_STAGE}"
rsync -a "${COMMON_EXCLUDES[@]}" "${ROOT}/plugin/" "${PLUGIN_STAGE}/"

# Theme ---------------------------------------------------------------------
THEME_STAGE="${STAGE}/${THEME_SLUG}"
mkdir -p "${THEME_STAGE}"
rsync -a "${COMMON_EXCLUDES[@]}" "${THEME_EXCLUDES[@]}" "${ROOT}/theme/" "${THEME_STAGE}/"

# Zip -----------------------------------------------------------------------
( cd "${STAGE}" && zip -rq "${DIST}/${PLUGIN_SLUG}.zip" "${PLUGIN_SLUG}" )
( cd "${STAGE}" && zip -rq "${DIST}/${THEME_SLUG}.zip"  "${THEME_SLUG}" )

rm -rf "${STAGE}"

echo "Built:"
echo "  ${DIST}/${PLUGIN_SLUG}.zip ($(du -h "${DIST}/${PLUGIN_SLUG}.zip" | cut -f1))"
echo "  ${DIST}/${THEME_SLUG}.zip  ($(du -h "${DIST}/${THEME_SLUG}.zip" | cut -f1))"
