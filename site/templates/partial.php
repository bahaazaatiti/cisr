<?php
  /** @var \Kirby\Cms\Page $page */
  $tpl = $page->intendedTemplate()->name();
  $snippetPath = kirby()->root('snippets') . '/page/' . $tpl . '.php';
  $snippet = file_exists($snippetPath) ? 'page/' . $tpl : 'page/default';
  snippet($snippet, ['page' => $page]);
?>
