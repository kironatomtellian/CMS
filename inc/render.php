<?php
declare(strict_types=1);

/**
 * Templating helpers — thin wrappers, no framework.
 *
 * render_layout() outputs the shared HTML chrome (nav, header, footer) around
 * a body. render_view() is a thin include wrapper that exposes vars to the
 * included file.
 */

function render_layout(string $title, callable $bodyFn, array $opts = []): void {
    $active = $opts['active'] ?? '';
    $bodyAttr = $opts['body_attr'] ?? '';
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= h($title) ?> — Kiron Atom Tellian CMS</title>
  <link rel="stylesheet" href="<?= h(url('/assets/css/app.css')) ?>">
  <link rel="icon" href="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'><text y='13' font-size='14'>·</text></svg>">
</head>
<body<?= $bodyAttr ? ' ' . $bodyAttr : '' ?>>
  <header class="topbar">
    <a class="topbar__brand" href="<?= h(url('/')) ?>">Kiron · CMS</a>
    <nav class="topbar__nav">
      <?php foreach (nav_items() as $item): ?>
        <a href="<?= h(url($item['href'])) ?>"<?= $active === $item['key'] ? ' aria-current="page"' : '' ?>><?= h($item['label']) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="topbar__right">
      <button type="button" class="btn btn--primary" id="publishBtn" data-action="publish">Publish to website</button>
      <a class="btn btn--ghost" href="<?= h(url('/logout')) ?>">Log out</a>
    </div>
  </header>
  <main class="page">
    <?php $bodyFn(); ?>
  </main>
  <div class="toast" id="toast" role="status" aria-live="polite"></div>
  <script>
    window.CMS = {
      base: <?= json_encode(rtrim(url('/'), '/'), JSON_UNESCAPED_SLASHES) ?>,
    };
  </script>
  <script src="<?= h(url('/assets/js/app.js')) ?>"></script>
</body>
</html>
    <?php
}

function nav_items(): array {
    return [
        ['key' => 'dashboard', 'href' => '/', 'label' => 'Overview'],
        ['key' => 'home', 'href' => '/edit/home', 'label' => 'Home'],
        ['key' => 'about', 'href' => '/edit/about', 'label' => 'About'],
        ['key' => 'concerts-upcoming', 'href' => '/edit/concerts-upcoming', 'label' => 'Upcoming'],
        ['key' => 'concerts-past', 'href' => '/edit/concerts-past', 'label' => 'Past'],
        ['key' => 'gallery', 'href' => '/edit/gallery', 'label' => 'Gallery'],
        ['key' => 'media', 'href' => '/edit/media', 'label' => 'Media'],
        ['key' => 'press', 'href' => '/edit/press', 'label' => 'Press'],
    ];
}
