<?php
/**
 * CMS config — copy to config.php and edit.
 * config.php is gitignored; this template is committed.
 */

return [
    // Where you log in
    'auth' => [
        // password_hash() output. Generate with:
        //   php -r "echo password_hash('your-password-here', PASSWORD_DEFAULT) . PHP_EOL;"
        'password_hash' => '$2y$12$REPLACE_ME_WITH_GENERATED_HASH',

        // Random string used to sign the session cookie. Generate with:
        //   php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
        'session_secret' => 'REPLACE_ME_WITH_RANDOM_64_CHAR_HEX',

        // How long a login lasts (seconds). 30 days by default.
        'session_lifetime' => 60 * 60 * 24 * 30,
    ],

    // Paths on the server
    'paths' => [
        // Where the website repo is checked out on the server.
        // The CMS reads/writes apps/public/src/data/*.json inside this.
        'site_repo' => '/var/www/website',

        // Where the live built site is served from (your Nginx/Apache root).
        // The publish step builds into a temp dir and atomically swaps it here.
        'web_root' => '/var/www/html',

        // Where uploaded images get written. Should be inside the site's
        // static folder so they're picked up by the Astro build.
        // Files placed here are referenced as /img/uploads/<filename>.
        'uploads_dir' => '/var/www/website/apps/public/public/img/uploads',
    ],

    // Binaries (use full paths if not on the web user's PATH)
    'bin' => [
        'git' => 'git',
        'npm' => 'npm',
    ],

    // Publish behaviour
    'publish' => [
        // Run `git pull --ff-only` before building. Set false if you publish
        // edits that aren't committed.
        'git_pull' => false,

        // Keep the previous web root as <web_root>.prev so you can roll back
        // by swapping the dirs back.
        'keep_previous' => true,
    ],
];
