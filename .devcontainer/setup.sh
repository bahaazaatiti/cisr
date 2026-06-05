#!/usr/bin/env bash
set -euo pipefail

TAILWIND_VERSION="v4.3.0"   # keep in sync with .github/workflows/build.yml

echo "──────────────────────────────────────────"
echo " Kirby dev environment setup"
echo "──────────────────────────────────────────"

# ── 1. PHP extensions Kirby needs (gd for image processing, intl) ──────────────
# Kirby (claviska/simpleimage + league/color-extractor) REQUIRES ext-gd, and the
# devcontainers/php:8.3 base image does NOT ship it — install it BEFORE `composer
# install`, which platform-checks ext-gd and aborts without it. mlocati's
# install-php-extensions handles gd's system libs + enables it, but it is NOT on
# PATH in this image, so fetch the self-contained script when it's missing. Skip the
# whole step when gd is already loaded (local dev), and VERIFY afterward so a silent
# miss can never resurface as a cryptic composer "ext-gd missing" abort.
if ! php -m | grep -qix gd; then
  echo "→ Installing PHP extensions (gd, intl)..."
  if ! command -v install-php-extensions >/dev/null 2>&1; then
    sudo curl -fsSLo /usr/local/bin/install-php-extensions \
      https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions
    sudo chmod +x /usr/local/bin/install-php-extensions
  fi
  sudo install-php-extensions gd intl
  php -m | grep -qix gd || { echo "✗ ext-gd still not loaded after install — aborting."; exit 1; }
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
