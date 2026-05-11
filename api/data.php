<?php
declare(strict_types=1);

// GET /api/data?key=…
// Returns the current JSON for a content key. Used by the client if it ever
// needs to re-hydrate without a page reload.

$key = $_GET['key'] ?? '';
if (!is_string($key) || $key === '') {
    json_response(['ok' => false, 'error' => 'Missing key.'], 400);
}
$schemas = schemas();
if (!isset($schemas[$key])) {
    json_response(['ok' => false, 'error' => "Unknown key: $key"], 400);
}
try {
    $loaded = data_load($key);
    json_response(['ok' => true, 'data' => $loaded['data'], 'mtime' => $loaded['mtime']]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 500);
}
