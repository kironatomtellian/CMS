<?php
declare(strict_types=1);

/**
 * Content checks — what the Today screen asks Kiron to fix.
 *
 * Each check returns issues shaped like:
 *   ['level' => 'fix'|'info', 'text' => '…', 'key' => '<schema key>', 'fix' => '<label>'|null]
 * Only checks that can be answered from the JSON files live here; nothing is
 * fetched from the network.
 */

/** Misspellings seen on the live site, wrong => right. */
function spelling_rules(): array {
    return [
        'Columbia' => 'Colombia',
        'Edward Grieg' => 'Edvard Grieg',
        'Orchestra National de Lyon' => 'Orchestre National de Lyon',
        'alltogether' => 'altogether',
        'Harrison Parrott' => 'HarrisonParrott',
        'To be Announced' => 'To be announced',
        'TBA' => 'To be announced',
    ];
}

function today_iso(): string {
    return date('Y-m-d');
}

/** Upcoming concerts from both files, decided by date, soonest first. */
function upcoming_concerts(): array {
    $all = [];
    foreach (['concerts-upcoming', 'concerts-past'] as $key) {
        try {
            $rows = data_load($key)['data'];
        } catch (Throwable $e) {
            continue;
        }
        foreach ((array)$rows as $c) {
            if (is_array($c) && !empty($c['date']) && $c['date'] >= today_iso()) {
                $all[] = $c;
            }
        }
    }
    usort($all, fn($a, $b) => strcmp($a['date'], $b['date']));
    return $all;
}

function concert_place(array $c): string {
    if (!empty($c['city'])) {
        return (string)$c['city'];
    }
    return trim(explode(',', (string)($c['venue'] ?? ''))[0]);
}

function content_checks(): array {
    $issues = [];

    // 1. Upcoming concerts without a ticket link.
    $missing = array_filter(upcoming_concerts(), fn($c) => empty($c['ticketsUrl']));
    if ($missing) {
        $places = array_count_values(array_map('concert_place', $missing));
        $names = [];
        foreach ($places as $place => $n) {
            $names[] = $n > 1 ? "$place × $n" : $place;
        }
        $issues[] = [
            'level' => 'fix',
            'text' => count($missing) . ' upcoming ' . (count($missing) === 1 ? 'concert has' : 'concerts have') . ' no ticket link: ' . implode(', ', $names),
            'key' => 'concerts-upcoming',
            'fix' => 'Add links',
        ];
    }

    // 2. Misspellings anywhere in the site's text.
    $rules = spelling_rules();
    foreach (schema_files() as $key => $file) {
        try {
            $data = data_load($key)['data'];
        } catch (Throwable $e) {
            continue;
        }
        $hits = [];
        array_walk_recursive($data, function ($v) use ($rules, &$hits) {
            if (!is_string($v)) {
                return;
            }
            foreach ($rules as $wrong => $right) {
                if (preg_match('/\b' . preg_quote($wrong, '/') . '\b/u', $v)) {
                    $hits[$wrong] = ($hits[$wrong] ?? 0) + 1;
                }
            }
        });
        foreach ($hits as $wrong => $n) {
            $issues[] = [
                'level' => 'fix',
                'text' => "“{$wrong}” should be “{$rules[$wrong]}” ({$n}× in " . schemas()[$key]['title'] . ')',
                'key' => $key,
                'fix' => 'Fix all',
                'replace' => [$wrong, $rules[$wrong]],
            ];
        }
    }

    // 3. Past dates still in the upcoming file. The site already hides them;
    //    publishing moves them into the archive file.
    try {
        $up = (array)data_load('concerts-upcoming')['data'];
        $stale = array_filter($up, fn($c) => is_array($c) && !empty($c['date']) && $c['date'] < today_iso());
        if ($stale) {
            $issues[] = [
                'level' => 'info',
                'text' => count($stale) . ' past ' . (count($stale) === 1 ? 'concert moves' : 'concerts move') . ' to Past concerts on the next publish',
                'key' => 'concerts-past',
                'fix' => null,
            ];
        }
    } catch (Throwable $e) {
        // A missing file is reported on the section cards.
    }

    // 4. The red word of a big quote has to appear in the quote itself.
    foreach (['home' => ['pressFeature', 'lead'], 'press' => ['lead']] as $key => $path) {
        try {
            $lead = data_load($key)['data'];
        } catch (Throwable $e) {
            continue;
        }
        foreach ($path as $seg) {
            $lead = is_array($lead) ? ($lead[$seg] ?? null) : null;
        }
        if (is_array($lead) && !empty($lead['emphasis']) && strpos((string)($lead['quote'] ?? ''), (string)$lead['emphasis']) === false) {
            $issues[] = [
                'level' => 'fix',
                'text' => "The red word “{$lead['emphasis']}” is not in the big quote on " . schemas()[$key]['title'],
                'key' => $key,
                'fix' => 'Edit quote',
            ];
        }
    }

    // 5. Pictures the site points to but cannot find.
    global $config;
    $public = rtrim($config['paths']['site_repo'], '/') . '/apps/public/public';
    foreach (schema_files() as $key => $file) {
        try {
            $data = data_load($key)['data'];
        } catch (Throwable $e) {
            continue;
        }
        $missingImgs = [];
        array_walk_recursive($data, function ($v) use ($public, &$missingImgs) {
            if (is_string($v) && preg_match('#^/img/[^\s]+\.(jpe?g|png|webp|gif|avif)$#i', $v) && !is_file($public . $v)) {
                $missingImgs[] = basename($v);
            }
        });
        if ($missingImgs) {
            $issues[] = [
                'level' => 'fix',
                'text' => count($missingImgs) . ' missing ' . (count($missingImgs) === 1 ? 'picture' : 'pictures') . ' on ' . schemas()[$key]['title'] . ': ' . implode(', ', array_slice(array_unique($missingImgs), 0, 3)),
                'key' => $key,
                'fix' => 'Upload',
            ];
        }
    }

    // 6. Upcoming concerts without a city show the hall name in big type.
    $noCity = array_filter(upcoming_concerts(), fn($c) => empty($c['city']));
    if ($noCity) {
        $issues[] = [
            'level' => 'info',
            'text' => count($noCity) . ' upcoming ' . (count($noCity) === 1 ? 'concert has' : 'concerts have') . ' no city, so the hall name is shown instead',
            'key' => 'concerts-upcoming',
            'fix' => 'Add cities',
        ];
    }

    return $issues;
}

/**
 * Replace one wrong spelling with the right one across a data file.
 * Returns the number of strings changed.
 */
function apply_spelling_fix(string $key, string $wrong, string $right): int {
    $rules = spelling_rules();
    if (($rules[$wrong] ?? null) !== $right) {
        throw new RuntimeException('Unknown spelling rule.');
    }
    $data = data_load($key)['data'];
    $changed = 0;
    $pattern = '/\b' . preg_quote($wrong, '/') . '\b/u';
    array_walk_recursive($data, function (&$v) use ($pattern, $right, &$changed) {
        if (is_string($v) && preg_match($pattern, $v)) {
            $v = preg_replace($pattern, $right, $v);
            $changed++;
        }
    });
    if ($changed) {
        data_save($key, $data);
    }
    return $changed;
}

/**
 * Move concerts dated before today from the upcoming file into the past file
 * (newest first). Returns how many moved.
 */
function archive_past_concerts(): int {
    $up = (array)data_load('concerts-upcoming')['data'];
    $past = (array)data_load('concerts-past')['data'];
    $today = today_iso();

    $keep = [];
    $moved = [];
    foreach ($up as $c) {
        if (is_array($c) && !empty($c['date']) && $c['date'] < $today) {
            $moved[] = $c;
        } else {
            $keep[] = $c;
        }
    }
    if (!$moved) {
        return 0;
    }
    $past = array_merge($moved, $past);
    usort($past, fn($a, $b) => strcmp((string)($b['date'] ?? ''), (string)($a['date'] ?? '')));
    data_save('concerts-past', array_values($past));
    data_save('concerts-upcoming', array_values($keep));
    return count($moved);
}

/** "May 5, 2027" from "2027-05-05". */
function display_date(string $iso): string {
    $t = strtotime($iso . ' 12:00:00');
    return $t ? date('F j, Y', $t) : $iso;
}
