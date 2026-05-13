#!/usr/bin/env bash
# Rebuild Tailwind CSS. Run after editing templates/snippets.
set -e
cd "$(dirname "$0")"
./.bin/tailwindcss \
  -i assets/css/tailwind.src.css \
  -o assets/css/app.css \
  --minify
echo "built: $(wc -c < assets/css/app.css) bytes raw, $(gzip -9 -c assets/css/app.css | wc -c) bytes gz"
