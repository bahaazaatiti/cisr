<?php
// Panel-only config page: it holds the four crawl-source tables but never ships
// as a static route (bin/generate.php filters the template out). The front end
// reads its fields via ticker_feeds()/ticker_news() while rendering every *other* page. If
// someone opens it on the live dev server, bounce them home.
go(site()->homePage()->url());
