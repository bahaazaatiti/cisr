<?php
  /** @var \Kirby\Cms\App $kirby */
  /** @var \Kirby\Cms\Site $site */
  /** @var \Kirby\Cms\Page $page */
  $lang = $kirby->language();
  $dir  = $lang ? $lang->direction() : 'ltr';
  $code = $lang ? $lang->code() : 'en';
?>
<!doctype html>
<html lang="<?= esc($code) ?>" dir="<?= esc($dir) ?>" data-mirror-cap="3">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' blob:; style-src 'self' 'unsafe-inline'; img-src 'self' https://i.ytimg.com data: blob:; media-src 'self' blob:; frame-src https://www.youtube-nocookie.com; connect-src 'self' wss: https:; worker-src 'self' blob:; manifest-src 'self'">
  <title><?= esc($page->fullTitle()) ?></title>
  <?php $desc = $page->metaDescription(); ?>
  <meta name="description" content="<?= esc($desc) ?>">
  <link rel="icon" type="image/svg+xml" href="<?= url('assets/img/favicon.svg') ?>">
  <link rel="canonical" href="<?= esc($page->url()) ?>">
  <?php foreach ($kirby->languages() as $l): ?>
    <link rel="alternate" hreflang="<?= esc($l->code()) ?>" href="<?= esc($page->url($l->code())) ?>">
  <?php endforeach ?>
  <?php if ($default = $kirby->defaultLanguage()): ?>
    <link rel="alternate" hreflang="x-default" href="<?= esc($page->url($default->code())) ?>">
  <?php endif ?>
  <meta property="og:type" content="<?= $page->intendedTemplate()->name() === 'article' ? 'article' : 'website' ?>">
  <meta property="og:site_name" content="<?= esc($site->title()) ?>">
  <meta property="og:title" content="<?= esc($page->fullTitle()) ?>">
  <meta property="og:description" content="<?= esc($desc) ?>">
  <meta property="og:url" content="<?= esc($page->url()) ?>">
  <meta property="og:locale" content="<?= esc(str_replace('-', '_', $code)) ?>">
  <meta name="twitter:card" content="summary">
  <meta name="build-sha" content="<?= esc(build_stamp()['sha'] ?? 'dev') ?>">
  <script>try{const m=localStorage.getItem('theme')||(matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light');if(m==='dark')document.documentElement.classList.add('dark')}catch(e){}</script>
  <style>
  /* Wrapped in @layer base so the rest of app.css (@layer components) can
     override individual rules — unlayered CSS would otherwise beat layered. */
  :root{--background:#FFFFFF;--foreground:#000000;--secondary:#F2F2F2;--muted-foreground:#666666;--accent:#0000FF;--accent-foreground:#FFFFFF;--border:#000000;--ring:#0000FF}
  .dark{--background:#000000;--foreground:#00A645;--secondary:#0a0a0a;--muted-foreground:#999999;--accent:#FFB000;--accent-foreground:#000000;--border:#00A645;--ring:#FFFFFF}
  @layer base{
  *,*::before,*::after{box-sizing:border-box;border:0 solid var(--border);margin:0;padding:0}
  html,body{background:var(--background);color:var(--foreground);font-family:monospace;font-size:14px;line-height:1.5;-webkit-font-smoothing:antialiased}
  a{color:inherit;text-decoration:underline;text-underline-offset:2px;text-decoration-thickness:1px}
  a:hover{color:var(--accent)}
  h1,h2,h3,h4,h5,h6{font-weight:700;text-transform:uppercase;letter-spacing:.08em}
  ::selection{background:var(--accent);color:var(--accent-foreground)}
  .layout{display:flex;min-height:100svh}
  .sidebar{width:16rem;border-inline-end:1px solid var(--border);display:flex;flex-direction:column;position:fixed;inset-block:0;inset-inline-start:0;background:var(--background);z-index:30;transition:transform .15s ease}
  @media (max-width:767px){.sidebar{transform:translateX(-100%)}html[dir=rtl] .sidebar{transform:translateX(100%)}.sidebar.translate-x-0{transform:translateX(0)!important}}
  .sidebar-head{padding:.75rem 1rem;border-block-end:1px solid var(--border);position:relative;overflow:hidden}
  .sidebar-nav{flex:1;overflow-y:auto;padding:.5rem 0}
  .sidebar-nav ul{margin-block-end:.75rem}
  .sidebar-foot{border-block-start:1px solid var(--border);padding:.75rem 1rem;display:flex;flex-direction:column;gap:.5rem;font-size:.75rem}
  .group-label{padding:.25rem 1rem;font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:var(--muted-foreground)}
  .nav-item{display:block;padding:.25rem 1rem;text-decoration:none}
  .nav-item:hover,.nav-item.active{background:var(--secondary)}
  .main{margin-inline-start:16rem;padding:1.5rem;max-width:80ch;width:100%}
  .main > section{margin-block-end:1.25rem}
  @media (min-width:1024px){.main{margin-inline-end:18rem}}
  @media (max-width:767px){.main{margin-inline-start:0;padding:3rem 1rem 4rem}}
  .sidebar-toggle{position:fixed;inset-block-start:.5rem;inset-inline-start:.5rem;z-index:40;border:1px solid var(--border);background:var(--background);padding:.25rem .5rem;font:inherit;cursor:pointer;display:none}
  @media (max-width:767px){.sidebar-toggle{display:block}}
  .ui-sku{font-size:.75em;text-transform:uppercase;letter-spacing:.08em;color:var(--muted-foreground)}
  .ui-badge{display:inline-block;border:1px solid currentColor;padding:0 .4em;text-transform:uppercase;letter-spacing:.05em;font-size:.7rem;line-height:1.6;background:transparent;cursor:pointer;font:inherit;text-decoration:none}
  .ui-badge:hover{background:var(--secondary)}
  #loadbar{position:fixed;inset-block-start:0;inset-inline-start:0;height:2px;width:0;background:var(--accent);z-index:50;transition:width .15s ease,opacity .2s ease;opacity:0}
  #loadbar.loading{width:80%;opacity:1;transition:width 1.6s cubic-bezier(.1,.7,.1,1)}
  .breadcrumb{display:flex;gap:.5rem;align-items:baseline;font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:var(--muted-foreground);margin-block-end:.5rem}
  .breadcrumb a{text-decoration:none}
  .breadcrumb a:hover{color:var(--accent)}
  .breadcrumb-sep{opacity:.6}
  }
  </style>
  <link rel="preload" as="style" href="<?= url('assets/css/app.css') ?>">
  <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
</head>
<body<?= ticker_active() ? ' class="has-ticker"' : '' ?>>
