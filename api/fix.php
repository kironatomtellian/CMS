<?php
declare(strict_types=1);

// POST /api/fix  body: { key, wrong, right }
// Applies one spelling rule from inc/checks.php across a data file.

$body = json_body();
$key = (string)($body['key'] ?? '');
$wrong = (string)($body['wrong'] ?? '');
$right = (string)($body['right'] ?? '');

if (!isset(schemas()[$key])) {
    json_response(['ok' => false, 'error' => "Unknown key: $key"], 400);
}

try {
    $n = apply_spelling_fix($key, $wrong, $right);
    json_response(['ok' => true, 'changed' => $n]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 400);
}
