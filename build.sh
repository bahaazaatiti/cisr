#!/usr/bin/env bash
# Rebuild Tailwind CSS and minified JS. Run after editing templates/snippets/js.
set -e
cd "$(dirname "$0")"
./.bin/tailwindcss \
  -i assets/css/tailwind.src.css \
  -o assets/css/app.css \
  --minify
echo "built: assets/css/app.css — $(wc -c < assets/css/app.css) bytes raw, $(gzip -9 -c assets/css/app.css | wc -c) bytes gz"
php build-js.php assets/js/app.js assets/js/app.min.js
php build-js.php assets/js/p2p.js assets/js/p2p.min.js
php build-js.php assets/js/comm.js assets/js/comm.min.js
php build-js.php assets/js/ticker.js assets/js/ticker.min.js
