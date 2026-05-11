<?php
declare(strict_types=1);

/**
 * Publish: rebuild the Astro site and atomically swap dist/ into the web root.
 *
 * Mirrors the original publish.php flow:
 *   1. (Optional) git pull --ff-only in the repo
 *   2. npm ci && npm run build inside apps/public/
 *   3. mv current web root → web_root.prev, mv dist → web_root
 *
 * Output is captured and returned to the client so failures are visible.
 */

function publish_run(): array {
    global $config;

    $log = [];
    $error = null;

    try {
        publish_steps($log);
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $log[] = '✗ ' . $error;
    }

    return [
        'ok' => $error === null,
        'log' => $log,
        'error' => $error,
        'time' => time(),
    ];
}

function publish_steps(array &$log): void {
    global $config;

    $repoRoot = rtrim($config['paths']['site_repo'], '/');
    $webRoot = rtrim($config['paths']['web_root'], '/');
    $appDir = $repoRoot . '/apps/public';
    $distDir = $appDir . '/dist';
    $git = $config['bin']['git'];
    $npm = $config['bin']['npm'];

    $run = function (string $cmd, string $cwd) use (&$log): void {
        $log[] = '$ ' . $cmd;
        $full = 'cd ' . escapeshellarg($cwd) . ' && ' . $cmd . ' 2>&1';
        exec($full, $out, $rc);
        $log[] = implode("\n", $out);
        if ($rc !== 0) {
            throw new RuntimeException("Command failed (exit $rc): $cmd");
        }
    };

    if (!is_dir($appDir)) {
        throw new RuntimeException("Site repo not found at $appDir");
    }
    if (!is_dir(dirname($webRoot))) {
        throw new RuntimeException('Web root parent does not exist: ' . dirname($webRoot));
    }

    if (!empty($config['publish']['git_pull'])) {
        $run("$git pull --ff-only", $repoRoot);
    }

    $run("$npm ci --no-audit --no-fund --silent", $appDir);
    $run("$npm run build --silent", $appDir);

    if (!is_dir($distDir)) {
        throw new RuntimeException("Build did not produce a dist/ folder at $distDir");
    }

    // Atomic swap: stage current root to .swap, move new dist in, then sweep
    // the old root to .prev (for one-shot rollback).
    $prev = $webRoot . '.prev';
    $swap = $webRoot . '.swap-' . bin2hex(random_bytes(4));

    if (is_dir($webRoot)) {
        if (!rename($webRoot, $swap)) {
            throw new RuntimeException('Could not move current web root aside.');
        }
    }
    if (!rename($distDir, $webRoot)) {
        if (is_dir($swap)) {
            @rename($swap, $webRoot);
        }
        throw new RuntimeException("Could not move $distDir to $webRoot");
    }

    if (is_dir($swap)) {
        if (!empty($config['publish']['keep_previous'])) {
            if (is_dir($prev)) {
                exec('rm -rf ' . escapeshellarg($prev));
            }
            rename($swap, $prev);
        } else {
            exec('rm -rf ' . escapeshellarg($swap));
        }
    }

    $log[] = '✓ Published.';
}
