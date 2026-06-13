# MyRiset VPS Git Pull Deployment Runbook

Target domain: `https://myriset.net`

This runbook documents a safe first VPS deployment using GitHub pull-based deployment. It is a documentation runbook only; do not paste real secrets into this file and do not run these commands from a development machine against production without reviewing them on the VPS.

## 1. Deployment Overview

Use this high-level order:

1. Step 1 - VPS and SSH ready
2. Step 2 - Install server stack
3. Step 3 - Prepare GitHub deploy key
4. Step 4 - Clone MyRiset repository
5. Step 5 - Install dependencies and build assets
6. Step 6 - Create production `.env`
7. Step 7 - Configure PostgreSQL
8. Step 8 - Run migrations and role seeder
9. Step 9 - Set Laravel storage/cache permissions
10. Step 10 - Configure Nginx
11. Step 11 - Enable HTTPS/SSL
12. Step 12 - Run production optimization/check
13. Step 13 - Create first admin
14. Step 14 - Smoke test

First pull means `git clone`. Future updates use `git pull origin main`.

## 2. Server Prerequisites

Step 1 - VPS and SSH ready:

- VPS is active.
- SSH access works.
- DNS control for `myriset.net` is available.
- You can run `sudo` on the VPS.

Step 2 - Install server stack:

```bash
sudo apt update
sudo apt install -y nginx git unzip curl
sudo apt install -y php php-fpm php-cli php-pgsql php-mbstring php-xml php-curl php-zip php-bcmath php-intl php-gd
```

Also install:

- Composer
- Node.js and npm
- PostgreSQL
- Certbot and the Nginx plugin

Do not lock the runbook to one PHP version unless the server requires it. MyRiset currently requires PHP `^8.2` in `composer.json`.

Check the PHP-FPM socket before writing the Nginx config:

```bash
ls /run/php/
```

## 3. DNS Preparation

Point DNS records for `myriset.net` and `www.myriset.net` to the VPS before running Certbot.

Suggested records:

```text
myriset.net      A      VPS_PUBLIC_IP_PLACEHOLDER
www.myriset.net  A      VPS_PUBLIC_IP_PLACEHOLDER
```

Use the real IP only in the DNS provider control panel. Do not commit real IPs into this repository unless explicitly approved.

## 4. GitHub Deploy Key Setup

Step 3 - Prepare GitHub deploy key:

Generate a read-only deploy key on the VPS:

```bash
ssh-keygen -t ed25519 -C "myriset-vps-deploy" -f ~/.ssh/myriset_deploy
cat ~/.ssh/myriset_deploy.pub
```

Add the public key in GitHub:

```text
Repository -> Settings -> Deploy keys -> Add deploy key
```

Rules:

- Use a read-only deploy key.
- Do not enable write access.
- Keep the private key on the VPS only.
- Do not commit SSH keys.

Optional SSH config on the VPS:

```text
Host github.com
  HostName github.com
  User git
  IdentityFile ~/.ssh/myriset_deploy
  IdentitiesOnly yes
```

Test GitHub SSH access:

```bash
ssh -T git@github.com
```

GitHub may say shell access is not provided; that is expected when authentication succeeds.

## 5. First Clone From GitHub

Step 4 - Clone MyRiset repository:

```bash
sudo mkdir -p /var/www/myriset
sudo chown -R $USER:www-data /var/www/myriset
git clone git@github.com:farhamzah/researchhub.git /var/www/myriset
cd /var/www/myriset
```

Future updates are not another clone. Use:

```bash
cd /var/www/myriset
git pull origin main
```

## 6. Production .env Setup

Step 6 - Create production `.env`:

```bash
cp .env.production.example .env
nano .env
php artisan key:generate --force
```

Minimum values:

```env
APP_NAME=MyRiset
APP_ENV=production
APP_DEBUG=false
APP_URL=https://myriset.net

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=myriset
DB_USERNAME=myriset_user
DB_PASSWORD=CHANGE_ON_SERVER

CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=https://myriset.net/auth/google/drive/callback

GOOGLE_DRIVE_CLIENT_ID=
GOOGLE_DRIVE_CLIENT_SECRET=
GOOGLE_DRIVE_REDIRECT_URI=https://myriset.net/auth/google/drive/callback
```

Warnings:

- Do not commit `.env`.
- Do not paste the database password in chat.
- Do not paste Google client secrets in chat.
- `APP_DEBUG` must be `false` in production.
- Google Drive can remain empty until OAuth is configured.
- Current MyRiset code reads `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and `GOOGLE_REDIRECT_URI`.

## 7. PostgreSQL Setup

Step 7 - Configure PostgreSQL:

Example only. Replace the password on the server and do not store it in docs.

```bash
sudo -u postgres psql
```

```sql
CREATE DATABASE myriset;
CREATE USER myriset_user WITH PASSWORD 'CHANGE_STRONG_PASSWORD_ON_SERVER';
GRANT ALL PRIVILEGES ON DATABASE myriset TO myriset_user;
\q
```

Notes:

- Use a strong unique password.
- Do not commit or share the password.
- Back up the database before future migrations.
- If PostgreSQL permission rules require schema grants on your server version, apply them intentionally on the VPS after reviewing PostgreSQL privileges.

## 8. Composer and Node Build

Step 5 - Install dependencies and build assets:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

For the first manual deployment, building on the VPS is acceptable. Later, assets can be built in CI and released as part of a controlled deployment artifact.

## 9. Laravel Migration and Role Seeding

Step 8 - Run migrations and role seeder:

```bash
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
php artisan myriset:production-check
```

`RolePermissionSeeder` must run before `myriset:create-admin`, because the create-admin command safely fails when the `super_admin` role is missing.

Do not recommend or run production demo data:

```bash
php artisan db:seed --class=MyRisetDemoSeeder
```

Do not run destructive database commands in production:

```bash
php artisan migrate:fresh
php artisan db:wipe
```

Do not drop database objects as a shortcut. Use backups and reviewed rollback plans instead.

## 10. First Admin Creation

Step 13 - Create first admin:

After migrations and the role seeder have completed, run:

```bash
php artisan myriset:create-admin --email=admin@myriset.net --name="MyRiset Admin"
```

Rules:

- Enter the password interactively.
- Do not pass a password as a command option.
- Do not paste the password into docs or chat.
- Do not run demo seeders in production.
- If the user already exists, the command does not overwrite the password by default.
- Use `--promote-existing` only when assigning `super_admin` to an existing user intentionally.
- Use `--reset-password` only when resetting an existing user password intentionally.

## 11. Nginx Setup

Step 10 - Configure Nginx:

Use `docs/MYRISET_NGINX_EXAMPLE.conf` as a starting point.

```bash
sudo nano /etc/nginx/sites-available/myriset.net
sudo ln -s /etc/nginx/sites-available/myriset.net /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

Important:

- Root must be `/var/www/myriset/public`.
- Replace the PHP-FPM socket based on `ls /run/php/`.
- Do not point Nginx to the repository root.
- Do not expose `.env`, hidden files, or private storage paths.

## 12. SSL With Certbot

Step 11 - Enable HTTPS/SSL:

DNS must already point to the VPS, and the HTTP site should be reachable before running Certbot.

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d myriset.net -d www.myriset.net
sudo certbot renew --dry-run
```

After SSL is active, confirm `APP_URL=https://myriset.net` and keep `SESSION_SECURE_COOKIE=true`.

## 13. Production Optimization

Step 12 - Run production optimization/check:

```bash
php artisan optimize:clear
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan myriset:production-check
```

In current Laravel versions, `php artisan optimize` may already cover some cache commands. Running explicit cache commands is acceptable for the first manual deployment and can be adjusted later.

Step 9 - Set Laravel storage/cache permissions:

```bash
sudo chown -R $USER:www-data /var/www/myriset
sudo chmod -R 775 storage bootstrap/cache
php artisan storage:link
```

Alternative if needed:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
```

Do not use `chmod 777`.

## 14. Production Readiness Check

Run:

```bash
php artisan myriset:production-check
```

Expected production basics:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL` uses HTTPS.
- Database name and username are configured.
- Storage and cache directories exist.
- Google Drive may be a warning until OAuth is configured.
- At least one `super_admin` user exists after first admin creation.

The command is designed not to print secrets.

## 15. Smoke Test

Step 14 - Smoke test:

- Open `https://myriset.net`.
- Open `https://myriset.net/admin/login`.
- Login as the production admin.
- Open Dashboard.
- Open Project Templates.
- Create project from template.
- Open Project Journey.
- Open Documents.
- Open Survey Builder.
- Create or generate one validation link if needed.
- Open the public validation link in an incognito/private browser.
- Create or generate one supervision link if needed.
- Open the public supervision link in an incognito/private browser.
- Run `php artisan myriset:production-check`.
- Confirm `APP_DEBUG=false`.
- Confirm HTTPS is valid.
- Confirm no Laravel debug error page appears.
- Confirm no `.env`, token, token hash, OAuth payload, private path, or respondent identity leak appears.

## 16. Future Update/Pull Workflow

For later releases:

```bash
cd /var/www/myriset

php artisan down || true

git pull origin main

composer install --no-dev --optimize-autoloader
npm ci
npm run build

php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan myriset:production-check

php artisan up

sudo systemctl reload php8.3-fpm
sudo systemctl reload nginx
```

Notes:

- Adjust `php8.3-fpm` to the actual PHP-FPM version shown by `ls /run/php/`.
- Only run seeders intentionally.
- Do not run `MyRisetDemoSeeder`.
- Back up the database before updates when migrations are included.
- Keep the commit hash before pulling so rollback is possible.

## 17. Rollback Notes

Simple rollback model:

1. Record the current commit before update.
2. Back up the database before migrations.
3. If the failure is code-only, check out the previous commit, run Composer install, build assets, and optimize.
4. If the failure involves migration/data changes, restore the database backup.
5. Do not attempt rollback blindly after irreversible migrations.

No complex zero-downtime deployment is required yet.

## 18. What Not To Do

Do not:

- SSH from this repository task into the real server.
- Change DNS from this repository task.
- Commit `.env`.
- Commit SSH private keys.
- Commit server passwords.
- Commit database passwords.
- Commit Google client secrets or OAuth tokens.
- Run `MyRisetDemoSeeder` in production.
- Run `php artisan migrate:fresh`.
- Run `php artisan db:wipe`.
- Drop database objects as a repair shortcut.
- Force push deployment changes.
- Store the first admin password in docs, chat, shell scripts, or source control.
