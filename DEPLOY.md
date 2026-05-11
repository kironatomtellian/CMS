# Deploy

Step-by-step server install. Assumes the dad doing this has a Linux box with
Apache (or Nginx) + PHP 8.0+ and access to git, npm, and node ≥ 18.

## 1. Get the files onto the server

```bash
sudo mkdir -p /var/www/cms
sudo chown $USER /var/www/cms
git clone https://github.com/kironatomtellian/cms.git /var/www/cms
```

You can also copy the folder by SFTP — there's no build step.

## 2. Make sure the website repo is also checked out

The CMS reads & writes JSON files inside a working copy of the website repo.
Anywhere on the same box works:

```bash
sudo mkdir -p /var/www/website
sudo chown $USER /var/www/website
git clone https://github.com/kironatomtellian/website.git /var/www/website
cd /var/www/website/apps/public
npm ci         # install Astro deps once so Publish is fast
```

## 3. Configure the CMS

```bash
cd /var/www/cms
cp config.example.php config.php
```

Generate a password hash and a session secret:

```bash
# password hash
php -r "echo password_hash('your-password-here', PASSWORD_DEFAULT) . PHP_EOL;"

# session secret
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

Paste the outputs into `config.php`. Then edit the paths:

```php
'paths' => [
    'site_repo'   => '/var/www/website',
    'web_root'    => '/var/www/html',          // where the live site is served
    'uploads_dir' => '/var/www/website/apps/public/public/img/uploads',
],
```

If `npm` and `git` aren't on the web user's `PATH`, use full paths in `'bin'`.

## 4. Permissions

The web user (`www-data` on Debian/Ubuntu, `apache` on RHEL) needs to:

- read & write the CMS folder
- read & write the website repo's `apps/public/src/data/` (for JSON saves)
- read & write the website repo's `apps/public/public/img/uploads/` (for image uploads)
- read & write the `apps/public/dist/` build output
- read & write the `web_root` directory (for the atomic swap)
- run `git`, `npm`, and `node`

A typical setup:

```bash
sudo chown -R www-data:www-data /var/www/cms /var/www/website /var/www/html
sudo chmod 600 /var/www/cms/config.php   # contains a password hash
sudo chown www-data:www-data /var/www/cms/config.php
```

Verify the web user can run npm (it sometimes can't reach `~/.npm` or `~/.node-gyp`):

```bash
sudo -u www-data -H bash -c 'cd /var/www/website/apps/public && npm --version && npm ci --dry-run'
```

If npm complains about `EACCES` on a cache dir, set `npm config set cache /var/cache/npm` as `www-data`, or point `HOME` somewhere writable in the Apache config.

## 5. Point Apache at it

Add a vhost (or a sub-location) that serves `/var/www/cms` and allows
`.htaccess` overrides:

```apache
<Directory /var/www/cms>
  AllowOverride All
  Require all granted
</Directory>

# If serving as a subdir of an existing site:
Alias /cms /var/www/cms
```

Or as a standalone vhost (recommended — easier auth & TLS):

```apache
<VirtualHost *:443>
  ServerName cms.kironatomtellian.com
  DocumentRoot /var/www/cms
  <Directory /var/www/cms>
    AllowOverride All
    Require all granted
  </Directory>
  SSLEngine on
  SSLCertificateFile      /etc/letsencrypt/live/cms.kironatomtellian.com/fullchain.pem
  SSLCertificateKeyFile   /etc/letsencrypt/live/cms.kironatomtellian.com/privkey.pem
</VirtualHost>
```

Reload Apache:

```bash
sudo systemctl reload apache2
```

### Nginx variant

```nginx
server {
  listen 443 ssl http2;
  server_name cms.kironatomtellian.com;
  root /var/www/cms;
  index index.php;

  location / {
    try_files $uri $uri/ /index.php?$query_string;
  }

  location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
  }

  location ~ ^/(inc|config\.example\.php|config\.php) {
    deny all;
    return 404;
  }
}
```

## 6. Smoke test

Visit `https://cms.kironatomtellian.com/`. You should see the login form. Sign
in with the password you hashed in step 3. The overview should list all seven
sections with a "Last edited" timestamp next to each.

Open one (e.g. Press), tweak a quote, hit **Save**. The on-disk JSON file
should reflect your change immediately. Then hit **Publish to website** — it
should run `npm ci && npm run build` and atomically swap `dist/` into the web
root. The output log appears in a dialog; close it when done.

## Backups / versioning

The CMS writes JSON in place — it does not commit anything to git. To keep a
version history, run a periodic cron that commits & pushes the website repo:

```cron
*/30 * * * * cd /var/www/website && git add apps/public/src/data && git diff --cached --quiet || git commit -m "[cms] auto-commit" && git push
```

Set this up only after deploy keys / a git credential helper are in place for
the `www-data` user.

## Rollback

If a publish breaks something, the previous web root sits at
`<web_root>.prev`. To revert:

```bash
WEB=/var/www/html
sudo mv "$WEB" "$WEB.bad" && sudo mv "$WEB.prev" "$WEB"
```

Only the most recent previous version is kept.
