<?php
  /** @var \Kirby\Cms\App $kirby */
  /** @var \Kirby\Cms\Site $site */
  /** @var \Kirby\Cms\Page $page */
  $lang = $kirby->language();
  $dir  = $lang ? $lang->direction() : 'ltr';
  $code = $lang ? $lang->code() : 'en';
?>
<!doctype html>
<html lang="<?= esc($code) ?>" dir="<?= esc($dir) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= esc($page->fullTitle()) ?></title>
  <?php $desc = $page->summary()->or($site->tagline())->value(); ?>
  <?php if ($desc): ?><meta name="description" content="<?= esc($desc) ?>"><?php endif ?>
  <script>try{const m=localStorage.getItem('theme')||(matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light');if(m==='dark')document.documentElement.classList.add('dark')}catch(e){}</script>
  <link rel="preload" href="<?= url('assets/fonts/jetbrains-mono-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
  <!--style added here and not inline in app.css to avoid FOUC on first load, since the font is used in the sidebar which is visible immediately. The rest of the styles can be loaded asynchronously without causing layout shifts. -->
  <style>
  :root{--background:#FFFFFF;--foreground:#000000;--secondary:#F2F2F2;--muted-foreground:#666666;--accent:#0000FF;--accent-foreground:#FFFFFF;--border:#000000;--ring:#0000FF}
  .dark{--background:#000000;--foreground:#00A645;--secondary:#0a0a0a;--muted-foreground:#999999;--border:#00A645;--ring:#FFFFFF}
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
  .sidebar-head{padding:.75rem 1rem;border-block-end:1px solid var(--border);position:relative;overflow:hidden}
  .sidebar-sign{position:absolute;inset-block-start:.4rem;inset-inline-end:.4rem;width:2.6rem;height:auto;opacity:.85;pointer-events:none}
  .home-art{display:flex;justify-content:center;margin:1rem 0}
  .home-art img{display:block;max-width:30%;height:auto}
  .home-art-place img{max-width:35%}
  .dark .home-art img,.dark .sidebar-sign{filter:invert(1)}
  .sidebar-nav{flex:1;overflow-y:auto;padding:.5rem 0}
  .sidebar-foot{border-block-start:1px solid var(--border);padding:.75rem 1rem;display:flex;gap:.5rem;font-size:.75rem}
  .group-label{padding:.25rem 1rem;font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:var(--muted-foreground)}
  .sidebar-nav ul{margin-block-end:.75rem}
  .nav-item{display:block;padding:.25rem 1rem;text-decoration:none}
  .nav-item:hover{background:var(--secondary)}
  .nav-item.active{background:var(--secondary);color:var(--foreground)}
  .main{margin-inline-start:16rem;padding:1.5rem;max-width:80ch;width:100%}
  .main > section{margin-block-end:1.25rem}
  @media (min-width:1024px){.main{margin-inline-end:18rem}}
  @media (max-width:767px){.main{margin-inline-start:0;padding:3rem 1rem 4rem}}
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

  /* Right sidebar */
  .aside-right{width:18rem;position:fixed;inset-block:0;inset-inline-end:0;border-inline-start:1px solid var(--border);background:var(--background);display:flex;flex-direction:column;z-index:30}
  @media (max-width:1023px){.aside-right{display:none}}
  .ar-half{flex:1;min-height:0;display:flex;flex-direction:column}
  .ar-video{border-block-start:1px solid var(--border)}
  .ar-head{display:flex;align-items:center;justify-content:space-between;padding:.4rem .6rem;border-block-end:1px solid var(--border);font-size:.7rem;text-transform:uppercase;letter-spacing:.08em}
  .ar-title{font-weight:700}
  .ar-tools{display:flex;gap:.25rem}
  .ar-mode,.lib-up{font:inherit;border:1px solid var(--border);background:transparent;padding:.15rem .45em;cursor:pointer;line-height:1.4;min-width:24px;min-height:24px}
  .ar-mode.active{background:var(--secondary)}
  .lib-up[disabled]{opacity:.4;cursor:not-allowed}
  .ar-body{flex:1;overflow:auto;padding:.4rem .5rem;font-size:.78rem;line-height:1.4}

  /* Library GUI grid */
  .lib-gui{display:flex;flex-direction:column;height:100%}
  .lib-bar{display:flex;align-items:center;gap:.4rem;padding:.2rem 0 .4rem;border-block-end:1px dashed var(--border);margin-block-end:.4rem;font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted-foreground)}
  .lib-cwd{flex:1;word-break:break-all}
  .lib-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:.4rem;align-content:start}
  .lib-cell{display:flex;flex-direction:column;align-items:flex-start;gap:.1rem;border:1px solid var(--border);background:transparent;font:inherit;text-decoration:none;padding:.4rem .5rem;cursor:pointer;text-align:start;color:inherit;min-height:3.2rem;line-height:1.25;user-select:none}
  .lib-cell:hover{background:var(--secondary)}
  .lib-cell:focus-visible{outline:1px solid var(--ring);outline-offset:1px}
  .lib-cell-folder{font-weight:700}
  .lib-cell-icon{font-family:var(--font-mono);color:var(--muted-foreground)}
  .lib-cell-folder .lib-cell-icon{color:var(--accent)}
  .lib-cell-name{word-break:break-word;font-size:.78rem;line-height:1.2}
  .lib-cell-meta{font-size:.65rem;margin-block-start:auto}
  .lib-empty-grid{grid-column:1/-1;padding:.5rem}

  .lib-table{width:100%;border-collapse:collapse;font-size:.72rem}
  .lib-table th,.lib-table td{border-block-end:1px solid var(--border);padding:.2rem .3rem;text-align:start;vertical-align:top}
  .lib-table th{text-transform:uppercase;letter-spacing:.05em;font-weight:700}
  .lib-table a{text-decoration:none}
  .lib-table a:hover{background:var(--secondary)}
  .lib-row-folder{cursor:pointer}
  .lib-row-folder:hover td{background:var(--secondary)}
  .lib-toggle{color:var(--accent);user-select:none}
  .lib-flat-folder{color:var(--muted-foreground)}

  .vid-stage{padding:.5rem}
  .vid-frame{display:block;width:100%;aspect-ratio:16/9;border:1px solid var(--border)}
  iframe.vid-frame,video.vid-frame{background:#000;outline:0}
  .vid-frame-empty{background:var(--secondary);display:flex;align-items:center;justify-content:center}
  .ar-video #player{--vid-zoom:.7;overflow:hidden}
  .ar-video #player > iframe.vid-frame{width:calc(100% / var(--vid-zoom));height:calc(100% / var(--vid-zoom));transform:scale(var(--vid-zoom));transform-origin:top left;border:0}
  html[dir=rtl] .ar-video #player > iframe.vid-frame{transform-origin:top right}
  .vid-list{list-style:none;padding:0;margin:0;border-block-start:1px solid var(--border)}
  .vid-list li{border-block-end:1px solid var(--border)}
  .vid-pick{display:flex;gap:.4rem;width:100%;text-align:start;font:inherit;background:transparent;border:0;cursor:pointer;padding:.35rem .5rem;align-items:baseline}
  .vid-pick:hover{background:var(--secondary)}
  .vid-pick.active{background:var(--secondary)}
  .vid-pick-icon{color:var(--accent)}
  .vid-pick-title{flex:1}

  /* Drawer (mobile) */
  .drawer-toggle{display:none;position:fixed;inset-block-end:.5rem;inset-inline-end:.5rem;z-index:40;border:1px solid var(--border);background:var(--background);padding:.3rem .6rem;font:inherit;cursor:pointer;font-size:.7rem;text-transform:uppercase;letter-spacing:.08em}
  @media (max-width:1023px){.drawer-toggle{display:inline-block}}
  .drawer{position:fixed;inset-inline:0;inset-block-end:0;height:50vh;border-block-start:1px solid var(--border);background:var(--background);z-index:50;transform:translateY(100%);transition:transform .15s ease;display:flex;flex-direction:column}
  .drawer:not([hidden]){display:flex}
  .drawer.open{transform:none}
  .drawer-tabs{display:flex;border-block-end:1px solid var(--border)}
  .drawer-tabs button{flex:0 0 auto;font:inherit;border:0;border-inline-end:1px solid var(--border);background:transparent;padding:.4rem .8rem;cursor:pointer;text-transform:uppercase;letter-spacing:.08em;font-size:.7rem}
  .drawer-tabs button.active{background:var(--secondary);font-weight:700}
  .drawer-x{margin-inline-start:auto;border-inline-end:0!important;border-inline-start:1px solid var(--border)!important}
  .drawer-panels{flex:1;overflow:hidden;position:relative}
  .drawer-panel{position:absolute;inset:0;overflow:auto;padding:.5rem}

  /* Context menu */
  .ctxmenu{position:fixed;z-index:60;background:var(--background);border:1px solid var(--border);display:flex;flex-direction:column;min-width:9rem}
  .ctxmenu button{font:inherit;border:0;background:transparent;text-align:start;padding:.35rem .6rem;cursor:pointer;text-transform:uppercase;letter-spacing:.05em;font-size:.72rem}
  .ctxmenu button:hover{background:var(--secondary)}

  /* Featured / sort */
  .nav-featured{font-weight:700}
  .featured-list{list-style:none;padding:0;margin:0;border-block-start:1px solid var(--border)}
  .featured-list li{border-block-end:1px solid var(--border)}
  .featured-list a{display:block;padding:.6rem 0;text-decoration:none}
  .featured-list a:hover{background:var(--secondary)}
  .featured-list h3{margin:0;font-size:1rem;text-transform:uppercase;letter-spacing:.08em}
  .sort-bar{display:flex;gap:.8rem;align-items:baseline;margin-block-end:.5rem}
  .sort-bar a{text-decoration:none}
  .sort-bar a.active{font-weight:700;text-decoration:underline;color:var(--foreground)}
  </style>
  <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>" media="print" onload="this.media='all'">
  <noscript><link rel="stylesheet" href="<?= url('assets/css/app.css') ?>"></noscript>
</head>
<body>
