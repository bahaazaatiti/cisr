<?php

// Inject HIGHK/RETICLE tokens into the Kirby panel so the admin surface
// matches the public site. Kirby v5 auto-loads index.css from any plugin
// root into the panel head — no manifest key needed.
Kirby::plugin('site/panel-theme', []);
