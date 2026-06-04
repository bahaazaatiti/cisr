<?php
// Panel-only config page: holds the live-broadcast switch, room, relay toggle and
// the signing public key. Never ships as a static route (bin/generate.php filters
// the template out). The front end reads its fields via broadcast_*() while
// rendering the home page. If opened on the dev server, bounce home.
go(site()->homePage()->url());
