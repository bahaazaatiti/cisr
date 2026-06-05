<?php

// Listed articles, newest first — the base set the home snippet reuses. Kept
// un-limited per Kirby's collection guidance; callers add ->limit() as needed.
return function ($site) {
    $articles = $site->find('articles');
    return $articles
        ? $articles->children()->listed()->sortBy('date', 'desc')
        : new \Kirby\Cms\Pages();
};
