<?php
declare(strict_types=1);

// POST /api/publish
// Rebuilds the Astro site and atomically swaps dist/ into the web root.

require_once CMS_ROOT . '/inc/publish.php';

@set_time_limit(600); // up to 10 minutes for the build

$result = publish_run();
json_response($result, $result['ok'] ? 200 : 500);
