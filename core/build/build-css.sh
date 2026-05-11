#!/usr/bin/env bash
# core/build/build-css.sh — compile site/assets-src/styles.css ->
# site/public/assets/css/styles.css using the Tailwind v3 standalone CLI.
#
# The binary is downloaded once into ./bin/tailwindcss (gitignored).
# Re-run this whenever a new utility class is introduced in any PHP
# file under site/, or whenever core/build/tailwind.config.js changes.
#
# Usage:
#   core/build/build-css.sh              # production build (minified)
#   core/build/build-css.sh --watch      # dev watch mode (unminified)

set -euo pipefail

VERSION="v3.4.17"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
BIN_DIR="${ROOT}/bin"
BIN="${BIN_DIR}/tailwindcss"
SRC="${ROOT}/site/assets-src/styles.css"
OUT="${ROOT}/site/public/assets/css/styles.css"
CONFIG="${ROOT}/core/build/tailwind.config.js"

# Detect platform once. Most maintainers will be on Linux or macOS; the
# Windows binary is downloadable too, but cygwin/wsl will already pick
# linux-x64 here.
case "$(uname -s)-$(uname -m)" in
    Linux-x86_64)        ASSET="tailwindcss-linux-x64" ;;
    Linux-aarch64)       ASSET="tailwindcss-linux-arm64" ;;
    Darwin-x86_64)       ASSET="tailwindcss-macos-x64" ;;
    Darwin-arm64)        ASSET="tailwindcss-macos-arm64" ;;
    *) echo "Unsupported platform $(uname -s)-$(uname -m). Download from"; \
       echo "https://github.com/tailwindlabs/tailwindcss/releases/tag/${VERSION}"; \
       exit 1 ;;
esac

if [[ ! -x "$BIN" ]]; then
    echo "Downloading Tailwind CLI ${VERSION} (${ASSET})..."
    mkdir -p "$BIN_DIR"
    curl -sSL --fail -o "$BIN" \
        "https://github.com/tailwindlabs/tailwindcss/releases/download/${VERSION}/${ASSET}"
    chmod +x "$BIN"
fi

if [[ "${1:-}" == "--watch" ]]; then
    exec "$BIN" -c "$CONFIG" -i "$SRC" -o "$OUT" --watch
fi

"$BIN" -c "$CONFIG" -i "$SRC" -o "$OUT" --minify
echo "Compiled $(wc -c < "$OUT" | tr -d ' ') bytes -> ${OUT#$ROOT/}"
