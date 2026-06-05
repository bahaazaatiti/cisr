<?php
// Panel-only media overview: lists every file for management. Never ships as a
// static route (bin/generate.php filters the template out). If opened on the
// dev server, bounce home.
go(site()->homePage()->url());
