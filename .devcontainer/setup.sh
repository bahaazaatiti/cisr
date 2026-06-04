#!/usr/bin/env bash
set -euo pipefail

TAILWIND_VERSION="v4.3.0"   # keep in sync with .github/workflows/build.yml

echo "──────────────────────────────────────────"
echo " Kirby dev environment setup"
echo "──────────────────────────────────────────"

# ── 1. Composer deps ───────────────────────────────────────────────────────────
echo "→ Installing Composer dependencies..."
composer install --no-interaction --no-progress

# ── 1b. PHP extensions Kirby needs at runtime (gd for thumbnails, intl) ────────
# install-php-extensions ships in the devcontainers/php image and is idempotent.
# Guarded + non-fatal so a missing helper or offline build never aborts setup.
if command -v install-php-extensions >/dev/null 2>&1; then
  echo "→ Ensuring PHP extensions (gd, intl)..."
  sudo install-php-extensions gd intl || echo "  (could not add gd/intl — check the base image)"
fi

# ── 2. Tailwind CLI ────────────────────────────────────────────────────────────
echo "→ Installing Tailwind CLI ${TAILWIND_VERSION}..."
mkdir -p .bin
curl -sLo .bin/tailwindcss \
  "https://github.com/tailwindlabs/tailwindcss/releases/download/${TAILWIND_VERSION}/tailwindcss-linux-x64"
chmod +x .bin/tailwindcss

# Add .bin to PATH permanently for this user
echo 'export PATH="$PATH:$(pwd)/.bin"' >> ~/.bashrc
echo 'export PATH="$PATH:$(pwd)/.bin"' >> ~/.zshrc 2>/dev/null || true

# ── 3. Build CSS + JS ──────────────────────────────────────────────────────────
echo "→ Running build.sh to compile CSS/JS..."
if [ -f build.sh ]; then
  chmod +x build.sh
  ./build.sh
else
  echo "  (no build.sh found, skipping)"
fi

# ── 4. Git config (Codespaces sets this but just in case) ─────────────────────
git config --global --add safe.directory "*" 2>/dev/null || true

echo ""
echo "✓ Setup complete!"
echo ""
echo "  Dev server auto-starts on boot (composer start, port 8765)."
echo "  Restart it:   composer start"
echo "  Open panel:   http://localhost:8765/panel"
echo ""
