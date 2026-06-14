# MyRiset Production Deployment Checklist

Target domain: `https://myriset.net`

This checklist prepares MyRiset for production deployment. It is provider-neutral and does not include real secrets, DNS changes, SSH commands, or destructive database operations.

## 1. Server Requirement

- PHP `^8.2`, matching `composer.json`.
- Composer available on the server or in the build pipeline.
- Node.js and npm compatible with the current Vite/Tailwind toolchain in `package.json`.
- PostgreSQL for the application database.
- Nginx or Apache configured to serve Laravel from the `public/` directory.
- HTTPS/SSL certificate for `myriset.net`.
- Cron access for Laravel scheduler if scheduled tasks are added.
- Queue worker process if `QUEUE_CONNECTION` is not `sync`.
- Log rotation configured at the OS or hosting level.

## 2. Domain and SSL

- Point DNS for `myriset.net` to the production server.
- Issue and install a valid SSL certificate.
- Force HTTPS at the web server or proxy layer.
- Confirm `APP_URL=https://myriset.net`.
- Confirm no mixed-content warnings appear in the browser.

## 3. Environment Variables

- Create the real `.env` only on the server.
- Use `.env.production.example` or `docs/MYRISET_PRODUCTION_ENV_GUIDE.md` as the template.
- Generate `APP_KEY` on the server with `php artisan key:generate`.
- Keep `APP_ENV=production`.
- Keep `APP_DEBUG=false`.
- Use strong database credentials.
- Do not commit `.env`, `.env.*` with real values, private keys, service account files, token dumps, or credential exports.

## 4. Database

- Create a PostgreSQL database and dedicated application user.
- Grant only the permissions needed by the app.
- Verify connection using safe Laravel checks before migration.
- Back up the database before every production migration.
- Run migrations with:

```bash
php artisan migrate --force
```

- Do not run `php artisan migrate:fresh`.
- Do not run `php artisan db:wipe`.
- Do not run `dropdb`.

## 5. File Permissions

- The web server user must be able to write to:
  - `storage/`
  - `bootstrap/cache/`
- The web server should not have write access to application source files unless the deployment process requires it.
- Do not expose `storage/app/private` directly through the web server.

## 6. Storage Link

- If public disk assets are needed, run:

```bash
php artisan storage:link
```

- Confirm `public/storage` exists.
- Do not expose private academic files, token dumps, or `.env` through the public disk.

## 7. Build Assets

Build frontend assets during deployment:

```bash
npm ci
npm run build
```

Commit source assets, not `node_modules/`.

## 8. Laravel Optimization

After dependencies, environment, and assets are ready:

```bash
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If config changes, clear and rebuild caches:

```bash
php artisan optimize:clear
php artisan optimize
```

## 9. Migrations and Seed Strategy

- Run migrations with `--force`.
- Do not run `MyRisetDemoSeeder` in production.
- Do not seed fake/demo projects, validators, respondents, survey answers, or public links.
- Baseline role/permission/category seeders may be run intentionally if the production setup requires them.
- Create the first production admin intentionally through the CLI-only command in the First Admin Setup section.

## 10. Queue, Scheduler, and Logs

- Current recommended placeholder:

```env
QUEUE_CONNECTION=database
```

- Run a queue worker when queued jobs are used:

```bash
php artisan queue:work --tries=3
```

- Add Laravel scheduler cron only when scheduled jobs are used:

```cron
* * * * * cd /path/to/myriset && php artisan schedule:run >> /dev/null 2>&1
```

- Configure log rotation.
- Keep `LOG_LEVEL=warning` or stricter for production unless troubleshooting.
- Never log passwords, Google OAuth tokens, database credentials, review tokens, token hashes, or respondent identity dumps.

## 11. Google Drive OAuth Production Notes

- Google Drive is optional for production readiness.
- Core MyRiset must still work when Google Drive is not configured.
- Drive features should be disabled with clear guidance until OAuth is ready.
- If enabled, create a Google Cloud OAuth Web Application client.
- Production redirect URI must match the current application route:

```text
https://myriset.net/auth/google/drive/callback
```

- Optional naming alignment fallback values use the same canonical callback:

```env
GOOGLE_DRIVE_CLIENT_ID=
GOOGLE_DRIVE_CLIENT_SECRET=
GOOGLE_DRIVE_REDIRECT_URI=https://myriset.net/auth/google/drive/callback
```

- In Google Cloud Console, add authorized redirect URIs:

```text
http://127.0.0.1:8001/auth/google/drive/callback
https://myriset.net/auth/google/drive/callback
```

- Optional compatibility alias, not primary:

```text
https://myriset.net/google/drive/callback
```

- If Google shows `redirect_uri_mismatch`, copy the canonical redirect URI from MyRiset Google Drive Settings and match protocol, domain or `127.0.0.1`, port, and path exactly.

- Use HTTPS.
- Use least required scopes. Current app expects:

```text
https://www.googleapis.com/auth/drive.file
```

- Store `GOOGLE_CLIENT_SECRET` only in the server `.env`.
- Do not commit OAuth client secrets or token payloads.
- It is acceptable for Google Drive variables to stay empty until OAuth is configured.
- MyRiset remains the source of truth for workflow and metadata; Google Drive stores files, folders, and exports.
- See `docs/MYRISET_GOOGLE_DRIVE_MANAGEMENT_BLUEPRINT.md` and `docs/MYRISET_GOOGLE_DRIVE_FOLDER_MAPPING.md` before expanding Drive features.

## 12. Security Checklist

- `APP_ENV=production`.
- `APP_DEBUG=false`.
- `APP_URL=https://myriset.net`.
- HTTPS enabled and enforced.
- Real `.env` not committed.
- Logs do not expose secrets.
- Public links do not expose raw tokens or token hashes.
- Public survey and review links do not expose respondent identity or project private data.
- `storage/` and `bootstrap/cache/` permissions are correct.
- Database credentials are strong and unique.
- Backups are configured.
- Admin password changed from local/demo values.
- First admin was created with `php artisan myriset:create-admin`; no password was stored in docs, chat, shell history, or source control.
- OAuth secrets stored only on the server.
- Authorization tests pass before release.

## 13. First Admin Setup

After migrations and baseline role/permission seeding are complete, create the first production admin through the CLI only:

```bash
php artisan myriset:create-admin --email=admin@myriset.net --name="MyRiset Admin"
```

Then enter the password interactively when prompted.

Safety rules:

- Do not pass the password as a command-line option.
- Do not paste the password into chat.
- Do not store the password in docs.
- Do not commit the password or `.env`.
- Do not run `MyRisetDemoSeeder` in production.
- If the user already exists, the command will not overwrite the password by default.
- Use `--promote-existing` only when you intentionally want to assign `super_admin` to an existing user.
- Use `--reset-password` only when you intentionally want to set a new password through hidden prompts.

## 14. VPS Git Pull Deployment

Use `docs/MYRISET_VPS_DEPLOYMENT_RUNBOOK.md` for the practical first VPS deployment flow.

- First clone:

```bash
git clone git@github.com:farhamzah/researchhub.git /var/www/myriset
```

- Future git pull:

```bash
cd /var/www/myriset
git pull origin main
```

- Nginx root must be `/var/www/myriset/public`.
- Use `docs/MYRISET_NGINX_EXAMPLE.conf` as a placeholder-only example.
- Enable HTTPS with Certbot only after DNS points to the VPS and HTTP works.
- Run migrations and `RolePermissionSeeder` before creating the first admin.
- Create the first admin with `php artisan myriset:create-admin`; enter the password interactively.
- Run the Smoke test checklist after deployment.
- Keep Rollback notes ready before updates, especially when migrations are included.
- Do not run `MyRisetDemoSeeder`, `migrate:fresh`, `db:wipe`, or database drop commands in production.

## 15. Backup Checklist

- Take a PostgreSQL backup before migration.
- Configure scheduled database backups.
- Back up `storage/` if user-uploaded files are stored locally.
- Define retention windows.
- Test restore into a non-production environment.
- Keep backups encrypted and access controlled.

## 16. Smoke Test Checklist

After deployment:

- Open `https://myriset.net`.
- Open `https://myriset.net/admin/login`.
- Login as the production admin.
- Open dashboard.
- Open project templates.
- Create a project from a template.
- Open project journey.
- Open documents.
- Open survey builder.
- Generate and open a public validation link in a safe test project.
- Generate and open a public supervision link in a safe test project.
- Confirm no Laravel debug page appears.
- Confirm HTTPS works and session cookies are secure.
- Confirm no raw token, token hash, Drive ID, OAuth data, `.env`, or private path appears in page source.

## 17. Rollback Checklist

- Keep the previous release available if using release folders.
- Back up the database before migration.
- If migration fails, stop deployment and restore the database backup.
- Do not run destructive repair commands.
- Clear caches only when needed.
- Repoint the web server to the previous release if application code rollback is needed.

## Suggested Production Deploy Sequence

Documented commands only. Run on the production server or deployment pipeline after real env values are configured:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan myriset:create-admin --email=admin@myriset.net --name="MyRiset Admin"
php artisan myriset:production-check
```

Never include these in production deployment:

```bash
php artisan migrate:fresh
php artisan db:wipe
php artisan db:seed --class=MyRisetDemoSeeder
```
