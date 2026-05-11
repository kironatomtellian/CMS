<?php
declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';

$path = current_path();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// --- Routing ---------------------------------------------------------------
//
// Pretty paths (rewritten by .htaccess):
//   GET  /               → dashboard
//   GET  /login          → login form
//   POST /login          → login attempt
//   GET  /logout         → log out
//   GET  /edit/<key>     → editor for content type
//   POST /api/save       → save JSON for a content type
//   POST /api/publish    → rebuild & deploy the site
//   POST /api/upload     → upload an image to /img/uploads/
//   GET  /api/data?key=…→ fetch raw JSON for hydration

if ($path === '/login') {
    if ($method === 'POST') {
        require __DIR__ . '/api/login.php';
        exit;
    }
    route_login();
    exit;
}

if ($path === '/logout') {
    auth_logout();
    header('Location: ' . url('/login'));
    exit;
}

// Everything below requires auth.
$isApi = strpos($path, '/api/') === 0;
if ($isApi) {
    require_auth_api();
} else {
    require_auth_html();
}

if ($path === '/' || $path === '') {
    route_dashboard();
    exit;
}

if (preg_match('#^/edit/([a-z0-9-]+)$#', $path, $m)) {
    route_editor($m[1]);
    exit;
}

if ($path === '/api/save' && $method === 'POST') {
    require __DIR__ . '/api/save.php';
    exit;
}
if ($path === '/api/publish' && $method === 'POST') {
    require __DIR__ . '/api/publish.php';
    exit;
}
if ($path === '/api/upload' && $method === 'POST') {
    require __DIR__ . '/api/upload.php';
    exit;
}
if ($path === '/api/data' && $method === 'GET') {
    require __DIR__ . '/api/data.php';
    exit;
}

http_response_code(404);
render_layout('Not found', function () use ($path) {
    echo '<section class="panel panel--centered"><h1>404</h1><p>No route for <code>' . h($path) . '</code>.</p><p><a class="btn" href="' . h(url('/')) . '">Back to overview</a></p></section>';
});


// --- Route handlers --------------------------------------------------------

function route_login(): void {
    if (is_authed()) {
        header('Location: ' . url('/'));
        exit;
    }
    $err = $_GET['err'] ?? '';
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Sign in — Kiron CMS</title>
  <link rel="stylesheet" href="<?= h(url('/assets/css/app.css')) ?>">
</head>
<body class="login-body">
  <main class="login">
    <form class="login__card" method="post" action="<?= h(url('/login')) ?>">
      <h1 class="login__title">Kiron · CMS</h1>
      <p class="login__sub">Sign in to edit the website.</p>
      <?php if ($err): ?>
        <p class="login__err"><?= h($err) ?></p>
      <?php endif; ?>
      <label class="field">
        <span class="field__label">Password</span>
        <input class="field__input" type="password" name="password" autocomplete="current-password" required autofocus>
      </label>
      <button class="btn btn--primary btn--block" type="submit">Sign in</button>
    </form>
  </main>
</body>
</html>
    <?php
}

function route_dashboard(): void {
    $schemas = schemas();
    $files = schema_files();
    render_layout('Overview', function () use ($schemas, $files) {
        ?>
        <section class="panel">
          <header class="panel__head">
            <h1 class="panel__title">Overview</h1>
            <p class="panel__sub">Edit any section, then hit <strong>Publish to website</strong> to push it live.</p>
          </header>
          <ul class="cards">
            <?php foreach ($schemas as $key => $schema): ?>
              <li class="card">
                <a class="card__link" href="<?= h(url('/edit/' . $key)) ?>">
                  <h2 class="card__title"><?= h($schema['title']) ?></h2>
                  <p class="card__desc"><?= h($schema['description'] ?? '') ?></p>
                  <p class="card__meta">
                    <?php $m = @data_mtime($key); ?>
                    <?php if ($m): ?>
                      Last edited <?= h(date('M j, Y · g:ia', $m)) ?>
                    <?php else: ?>
                      <span class="card__warn">File missing: <?= h($files[$key]) ?></span>
                    <?php endif; ?>
                  </p>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>
        <?php
    }, ['active' => 'dashboard']);
}

function route_editor(string $key): void {
    $schemas = schemas();
    if (!isset($schemas[$key])) {
        http_response_code(404);
        render_layout('Not found', function () use ($key) {
            echo '<section class="panel"><h1>Unknown section</h1><p>No editor for <code>' . h($key) . '</code>.</p></section>';
        });
        return;
    }

    $schema = $schemas[$key];
    try {
        $loaded = data_load($key);
    } catch (Throwable $e) {
        http_response_code(500);
        render_layout('Error', function () use ($e) {
            echo '<section class="panel"><h1>Could not load this section</h1><pre>' . h($e->getMessage()) . '</pre></section>';
        });
        return;
    }

    render_layout($schema['title'], function () use ($key, $schema, $loaded) {
        ?>
        <section class="panel editor" data-editor data-key="<?= h($key) ?>">
          <header class="panel__head">
            <div>
              <h1 class="panel__title"><?= h($schema['title']) ?></h1>
              <p class="panel__sub"><?= h($schema['description'] ?? '') ?></p>
            </div>
            <div class="panel__actions">
              <span class="panel__meta" data-status>Loaded.</span>
              <button type="button" class="btn btn--primary" data-action="save">Save</button>
            </div>
          </header>
          <div class="editor__body" data-editor-body>
            <p class="muted">Loading editor…</p>
          </div>
        </section>
        <script id="editor-data" type="application/json"><?= json_encode([
            'key' => $key,
            'schema' => $schema,
            'data' => $loaded['data'],
            'mtime' => $loaded['mtime'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
        <?php
    }, ['active' => $key, 'body_attr' => 'data-page="editor"']);
}
