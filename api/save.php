<?php
declare(strict_types=1);

// POST /api/save  body: { key, data }
// Persists `data` to apps/public/src/data/<file>.json after a lightweight
// schema check.

$body = json_body();
$key = $body['key'] ?? '';
$data = $body['data'] ?? null;

if (!is_string($key) || $key === '') {
    json_response(['ok' => false, 'error' => 'Missing key.'], 400);
}

$schemas = schemas();
if (!isset($schemas[$key])) {
    json_response(['ok' => false, 'error' => "Unknown key: $key"], 400);
}

if ($data === null) {
    json_response(['ok' => false, 'error' => 'Missing data.'], 400);
}

$schema = $schemas[$key];
if (!empty($schema['root_is_list'])) {
    if (!is_array($data) || (count($data) > 0 && array_keys($data) !== range(0, count($data) - 1))) {
        json_response(['ok' => false, 'error' => 'Root must be a list.'], 400);
    }
} else {
    if (!is_array($data) || (count($data) > 0 && array_keys($data) === range(0, count($data) - 1))) {
        json_response(['ok' => false, 'error' => 'Root must be an object.'], 400);
    }
}

try {
    $mtime = data_save($key, $data);
    json_response(['ok' => true, 'mtime' => $mtime]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 500);
}
