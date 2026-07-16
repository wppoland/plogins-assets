#!/usr/bin/env bash
# Build a clean, installable plogins-assets zip for wp.org, honouring .distignore.
# Boots via the PSR-4 fallback in autoload.php, so /vendor is not shipped.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="${1:-/tmp/plogins-assets-build}"
STAGE="${OUT_DIR}/plogins-assets"

rm -rf "${OUT_DIR}"
mkdir -p "${STAGE}"

rsync -a --exclude-from="${ROOT_DIR}/.distignore" \
    --exclude '.git' --exclude 'node_modules' --exclude 'vendor' \
    --exclude '.DS_Store' \
    "${ROOT_DIR}/" "${STAGE}/"

find "${STAGE}" -name '.DS_Store' -delete

VERSION="$(grep -m1 "Version:" "${ROOT_DIR}/plogins-assets.php" | tr -dc '0-9.')"
ZIP="/tmp/plogins-assets.zip"
rm -f "${ZIP}"
( cd "${OUT_DIR}" && zip -rqX "${ZIP}" plogins-assets -x '*.DS_Store' )
cp "${ZIP}" "${HOME}/Downloads/plogins-assets-${VERSION}.zip"
echo "✓ Built ${ZIP} and ~/Downloads/plogins-assets-${VERSION}.zip"
