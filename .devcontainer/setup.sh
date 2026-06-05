#!/usr/bin/env bash
set -euo pipefail

TAILWIND_VERSION="v4.3.0"   # keep in sync with .github/workflows/build.yml

echo "──────────────────────────────────────────"
echo " Kirby dev environment setup"
echo "──────────────────────────────────────────"

# ── 1. PHP extensions Kirby needs (gd for image processing, intl) ──────────────
# MUST run BEFORE `composer install`: composer platform-checks ext-gd, and the
# mcr…/devcontainers/php base image does NOT enable gd — Kirby pulls
# claviska/simpleimage + league/color-extractor, both require it, so the install
# aborts without this. install-php-extensions ships in that image + is idempotent.
# Guarded so a non-Codespace run (local PHP already has the extensions; the helper
# isn't installed) skips it instead of erroring.
if command -v install-php-extensions >/dev/null 2>&1; then
  echo "→ Ensuring PHP extensions (gd, intl)..."
  sudo install-php-extensions gd intl
fi

# ── 2. Composer deps ───────────────────────────────────────────────────────────
echo "→ Installing Composer dependencies..."
composer install --no-interaction --no-progress

# ── 3. Tailwind CLI ────────────────────────────────────────────────────────────
echo "→ Installing Tailwind CLI ${TAILWIND_VERSION}..."
mkdir -p .bin
curl -sLo .bin/tailwindcss \
  "https://github.com/tailwindlabs/tailwindcss/releases/download/${TAILWIND_VERSION}/tailwindcss-linux-x64"
chmod +x .bin/tailwindcss

# Add .bin to PATH permanently for this user
echo 'export PATH="$PATH:$(pwd)/.bin"' >> ~/.bashrc
echo 'export PATH="$PATH:$(pwd)/.bin"' >> ~/.zshrc 2>/dev/null || true

# ── 4. Build CSS + JS ──────────────────────────────────────────────────────────
echo "→ Running build.sh to compile CSS/JS..."
if [ -f build.sh ]; then
  chmod +x build.sh
  ./build.sh
else
  echo "  (no build.sh found, skipping)"
fi

# ── 5. Git config (Codespaces sets this but just in case) ─────────────────────
git config --global --add safe.directory "*" 2>/dev/null || true

echo ""
echo "✓ Setup complete!"
echo ""
echo "  Dev server auto-starts on boot (composer start, port 8765)."
echo "  Restart it:   composer start"
echo "  Open panel:   http://localhost:8765/panel"
echo ""
