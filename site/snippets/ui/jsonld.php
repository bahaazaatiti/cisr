<?php
  /** @var \Kirby\Cms\Page $page */
  /** @var string $type  'Article' | 'Organization' */
  $data = ['@context' => 'https://schema.org'];

  if ($type === 'Article') {
      $data['@type']         = 'Article';
      $data['headline']      = (string) $page->title();
      $data['mainEntityOfPage'] = (string) $page->url();
      $data['inLanguage']    = $page->kirby()->language()?->code() ?? 'en';
      if ($page->date()->isNotEmpty()) {
          $data['datePublished'] = $page->date()->toDate('c');
      }
      if ($page->summary()->isNotEmpty()) {
          $data['description'] = (string) $page->summary();
      }
      $data['publisher'] = [
          '@type' => 'Organization',
          'name'  => (string) $page->site()->title(),
      ];
  } elseif ($type === 'Organization') {
      $data['@type'] = 'Organization';
      $data['name']  = (string) $page->title();
      if ($page->native_name()->isNotEmpty()) {
          $data['alternateName'] = (string) $page->native_name();
      }
      if ($page->homepage()->isNotEmpty()) {
          $data['url'] = (string) $page->homepage();
      }
      if ($page->founded()->isNotEmpty()) {
          $data['foundingDate'] = (string) $page->founded();
      }
      if ($page->summary()->isNotEmpty()) {
          $data['description'] = (string) $page->summary();
      }
  } else {
      return;
  }
?>
<script type="application/ld+json"><?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
