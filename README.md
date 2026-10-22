# Kiron Atom Tellian CMS

A small, single-user CMS for editing the JSON files that drive
[`kironatomtellian/website`](https://github.com/kironatomtellian/website). One
password, simple forms, one "Publish to website" button that rebuilds the Astro
site and atomically swaps it into the web root.

## What it edits

Each editor maps 1:1 to a file in `apps/public/src/data/` on the website repo:

| Section | File | Page on the site |
| --- | --- | --- |
| Home | `home.json` | `/` |
| Schedule | `concerts-upcoming.json` | `/schedule` |
| Past concerts | `concerts-past.json` | `/schedule-archive` |
| Biography | `about.json` | `/about` |
| Press | `press.json` | `/press` |
| Media | `media.json` | `/media` |
| Gallery | `gallery.json` | `/gallery` |
| Contact (also the footer) | `contact.json` | `/contact` |
| For presenters | `presenters.json` | `/presenters` |

Fields the forms don't show are kept as they are when you save, so nothing the
site reads is lost. Concert lists are saved in date order, and gallery photos
get their pixel size from the file. Long lists open folded with a one-line
summary and a search box; Cmd/Ctrl+S saves.

Save → the JSON is rewritten in-place on disk.
Publish → `npm ci && npm run build` runs inside `apps/public/`, and the new
`dist/` is moved into the live web root (the previous version is kept at
`<webroot>.prev` for one-shot rollback).

## Stack

PHP 8+ on the server, vanilla JS in the browser. No build step, no Composer,
no npm. Drop it onto any PHP-capable server (Apache + `mod_rewrite`, or any
LAMP/LEMP setup).

## Folder tour

```
cms/
├── index.php              # router & page rendering
├── api/                   # JSON endpoints (save, publish, upload, …)
├── inc/                   # private PHP libs (auth, data, schemas, publish)
├── assets/css/app.css     # one stylesheet
├── assets/js/app.js       # one JS file — schema-driven form renderer
├── config.example.php     # copy to config.php and fill in
├── .htaccess              # URL rewriting + protects inc/ from the web
└── DEPLOY.md              # server install instructions
```

See `DEPLOY.md` for installation.

## Auto-deploy

Pushing to `main` triggers `.github/workflows/deploy.yml`, which rsyncs the
repo to the server over SSH. See DEPLOY.md for the required secrets.

## Adding a new field or section

Everything is driven by `inc/schemas.php`. Add a field there and the renderer
will pick it up automatically. Supported field types: `text`, `textarea`,
`select`, `image`, `date`, `object`, `list` (with `one_of` discriminated unions
or `scalar` raw-value items).

## License

Private. © Kiron Atom Tellian.
