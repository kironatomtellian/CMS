<?php
declare(strict_types=1);

// POST /api/upload  (multipart/form-data with field "file")
// Saves the uploaded image into the site's /img/uploads/ folder. Returns the
// public path you should reference in the JSON (e.g. "/img/uploads/foo.jpg").

global $config;

if (!isset($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
    json_response(['ok' => false, 'error' => 'No file uploaded.'], 400);
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    json_response(['ok' => false, 'error' => "Upload failed (code {$file['error']})."], 400);
}

$maxBytes = 25 * 1024 * 1024;
if ($file['size'] > $maxBytes) {
    json_response(['ok' => false, 'error' => 'File too large (max 25 MB).'], 400);
}

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;
if ($finfo) finfo_close($finfo);
if (!isset($allowed[$mime])) {
    json_response(['ok' => false, 'error' => "Unsupported file type: $mime"], 400);
}
$ext = $allowed[$mime];

$uploadDir = rtrim($config['paths']['uploads_dir'], '/');
if (!is_dir($uploadDir)) {
    if (!@mkdir($uploadDir, 0755, true)) {
        json_response(['ok' => false, 'error' => "Could not create $uploadDir"], 500);
    }
}

// Stable, URL-safe name: <slugified-original>-<short-hash>.<ext>
$origName = pathinfo($file['name'], PATHINFO_FILENAME);
$slug = preg_replace('/[^a-zA-Z0-9-_]+/', '-', $origName);
$slug = trim(strtolower($slug), '-');
if ($slug === '') $slug = 'image';
$slug = substr($slug, 0, 60);
$hash = substr(bin2hex(random_bytes(4)), 0, 6);
$name = "$slug-$hash.$ext";
$dest = "$uploadDir/$name";

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    json_response(['ok' => false, 'error' => 'Could not save uploaded file.'], 500);
}
@chmod($dest, 0644);

json_response([
    'ok' => true,
    'path' => '/img/uploads/' . $name,
    'name' => $name,
    'size' => filesize($dest) ?: 0,
]);
