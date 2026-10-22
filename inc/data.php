<?php
declare(strict_types=1);

/**
 * Read & write the JSON files that the public site builds from.
 *
 * All writes are atomic (write to tempfile, fsync, rename). The on-disk format
 * is human-readable JSON so a stray manual edit doesn't corrupt anything.
 */

function data_file_path(string $key): string {
    global $config;
    $files = schema_files();
    if (!isset($files[$key])) {
        throw new RuntimeException("Unknown data key: $key");
    }
    return rtrim($config['paths']['site_repo'], '/') . '/apps/public/src/data/' . $files[$key];
}

function data_load(string $key): array {
    $path = data_file_path($key);
    if (!file_exists($path)) {
        throw new RuntimeException("Data file not found: $path");
    }
    $raw = file_get_contents($path);
    $decoded = json_decode($raw, true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        $err = json_last_error_msg();
        throw new RuntimeException("Data file is invalid JSON: $path ($err)");
    }
    return ['data' => $decoded, 'mtime' => filemtime($path)];
}

function data_save(string $key, $data): int {
    $path = data_file_path($key);
    $json = json_encode(
        $data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    if ($json === false) {
        throw new RuntimeException('Could not encode data as JSON.');
    }
    // 2-space indent to match the existing files (json_encode uses 4-space).
    $json = preg_replace_callback('/^( +)/m', function ($m) {
        return str_repeat(' ', intdiv(strlen($m[1]), 2));
    }, $json) . "\n";

    $tmp = $path . '.tmp.' . bin2hex(random_bytes(6));
    $bytes = file_put_contents($tmp, $json, LOCK_EX);
    if ($bytes === false) {
        throw new RuntimeException("Could not write to $tmp");
    }
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException("Could not move $tmp to $path");
    }
    return filemtime($path) ?: time();
}

function data_mtime(string $key): int {
    $path = data_file_path($key);
    return file_exists($path) ? (int)filemtime($path) : 0;
}

/** Pixel size of an image the site serves at `/img/...`, or null when it can't be read. */
function site_image_size(string $src): ?array {
    global $config;
    if ($src === '' || $src[0] !== '/' || strpos($src, '..') !== false) {
        return null;
    }
    $path = rtrim($config['paths']['site_repo'], '/') . '/apps/public/public' . $src;
    $info = is_file($path) ? @getimagesize($path) : false;
    return $info ? [(int)$info[0], (int)$info[1]] : null;
}
