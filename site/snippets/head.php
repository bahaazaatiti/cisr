<?php
  /** @var \Kirby\Cms\App $kirby */
  /** @var \Kirby\Cms\Site $site */
  /** @var \Kirby\Cms\Page $page */
  $lang = $kirby->language();
  $dir  = $lang ? $lang->direction() : 'ltr';
  $code = $lang ? $lang->code() : 'en';
  $title = $page->isHomePage()
    ? $site->title()
    : $page->title() . ' · ' . $site->title();
?>
<!doctype html>
<html lang="<?= esc($code) ?>" dir="<?= esc($dir) ?>" class="">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= esc($title) ?></title>
  <?php if ($page->summary()->isNotEmpty()): ?>
    <meta name="description" content="<?= esc($page->summary()) ?>">
  <?php endif ?>
  <script>(()=>{try{const m=localStorage.getItem('theme')||(matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light');if(m==='dark')document.documentElement.classList.add('dark')}catch(e){}})()</script>
  <link rel="preload" href="<?= url('assets/fonts/jetbrains-mono-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
  <style>
  :root{--background:#FFFFFF;--foreground:#000000;--card:#FFFFFF;--popover:#FFFFFF;--primary:#000000;--primary-foreground:#FFFFFF;--secondary:#F2F2F2;--muted:#F2F2F2;--muted-foreground:#666666;--accent:#0000FF;--accent-foreground:#FFFFFF;--destructive:#FF0000;--border:#000000;--ring:#0000FF;--radius:2px}
  .dark{--background:#000000;--foreground:#00A645;--card:#000000;--popover:#000000;--primary:#00A645;--primary-foreground:#000000;--secondary:#0a0a0a;--muted:#0a0a0a;--muted-foreground:#999999;--border:#00A645;--ring:#FFFFFF}
  @font-face{font-family:"JetBrains Mono";font-style:normal;font-weight:100 800;font-display:swap;src:url("/assets/fonts/jetbrains-mono-latin.woff2") format("woff2");unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+2000-206F,U+20AC,U+2122,U+2212}
  *,*::before,*::after{box-sizing:border-box;border:0 solid var(--border);margin:0;padding:0}
  html,body{background:var(--background);color:var(--foreground);font-family:"JetBrains Mono",ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:14px;line-height:1.5;-webkit-font-smoothing:antialiased}
  a{color:inherit;text-decoration:underline;text-underline-offset:2px;text-decoration-thickness:1px}
  a:hover{color:var(--accent)}
  h1,h2,h3,h4,h5,h6{font-weight:700;text-transform:uppercase;letter-spacing:.08em}
  ::selection{background:var(--accent);color:var(--accent-foreground)}
  .layout{display:flex;min-height:100vh}
  .sidebar{width:16rem;border-inline-end:1px solid var(--border);display:flex;flex-direction:column;position:fixed;inset-block:0;inset-inline-start:0;background:var(--background);z-index:30;transition:transform .15s ease}
  @media (max-width:767px){.sidebar{transform:translateX(-100%)}html[dir=rtl] .sidebar{transform:translateX(100%)}.sidebar.translate-x-0{transform:translateX(0)!important}}
  .sidebar-head{padding:.75rem 1rem;border-block-end:1px solid var(--border)}
  .sidebar-nav{flex:1;overflow-y:auto;padding:.5rem 0}
  .sidebar-foot{border-block-start:1px solid var(--border);padding:.75rem 1rem;display:flex;gap:.5rem;font-size:.75rem}
  .group-label{padding:.25rem 1rem;font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:var(--muted-foreground)}
  .nav-item{display:block;padding:.25rem 1rem;text-decoration:none}
  .nav-item:hover{background:var(--secondary)}
  .nav-item.active{background:var(--secondary);color:var(--foreground)}
  .main{margin-inline-start:16rem;padding:1.5rem;max-width:80ch;width:100%}
  @media (max-width:767px){.main{margin-inline-start:0;padding:3rem 1rem 1rem}}
  .sidebar-toggle{position:fixed;inset-block-start:.5rem;inset-inline-start:.5rem;z-index:40;border:1px solid var(--border);background:var(--background);padding:.25rem .5rem;font:inherit;cursor:pointer;display:none}
  @media (max-width:767px){.sidebar-toggle{display:block}}
  .usgc-sku{font-size:.75em;text-transform:uppercase;letter-spacing:.08em;color:var(--muted-foreground)}
  .usgc-badge{display:inline-block;border:1px solid currentColor;padding:0 .4em;text-transform:uppercase;letter-spacing:.05em;font-size:.7rem;line-height:1.6;background:transparent;cursor:pointer;font:inherit;text-decoration:none}
  .usgc-badge:hover{background:var(--secondary)}
  #loadbar{position:fixed;inset-block-start:0;inset-inline-start:0;height:2px;width:0;background:var(--accent);z-index:50;transition:width .15s ease,opacity .2s ease;opacity:0}
  #loadbar.loading{width:80%;opacity:1;transition:width 1.6s cubic-bezier(.1,.7,.1,1)}
  .usgc-skeleton{padding:0}
  .usgc-skeleton .sk{background:var(--secondary);height:.85rem;margin:.6rem 0;animation:sk 1.2s linear infinite alternate}
  .usgc-skeleton .sk-h{height:1.4rem;width:60%;margin-block-end:1.2rem}
  .usgc-skeleton .sk-line{width:100%}
  .usgc-skeleton .w-2\/3{width:66%}
  @keyframes sk{from{opacity:.5}to{opacity:1}}
  .breadcrumb{display:flex;gap:.5rem;align-items:baseline;font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:var(--muted-foreground);margin-block-end:.5rem}
  .breadcrumb a{text-decoration:none}
  .breadcrumb a:hover{color:var(--accent)}
  .breadcrumb-sep{opacity:.6}
  </style>
  <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>" media="print" onload="this.media='all'">
  <noscript><link rel="stylesheet" href="<?= url('assets/css/app.css') ?>"></noscript>
</head>
<body>
