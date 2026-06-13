# MyRiset Production Environment Guide

Target domain: `https://myriset.net`

Create the real `.env` on the production server only. Never commit real values.

## Required Production Values

Use `.env.production.example` as the source template. Replace placeholders only on the server.

```env
APP_NAME=MyRiset
APP_ENV=production
APP_KEY=base64:GENERATE_ON_SERVER
APP_DEBUG=false
APP_URL=https://myriset.net

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=myriset
DB_USERNAME=myriset_user
DB_PASSWORD=CHANGE_ME_ON_SERVER
DB_SSLMODE=prefer

CACHE_STORE=file
SESSION_DRIVER=file
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
QUEUE_CONNECTION=database

FILESYSTEM_DISK=local

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=https://myriset.net/auth/google/drive/callback
GOOGLE_DRIVE_SCOPES=https://www.googleapis.com/auth/drive.file

# Optional aliases for future naming standardization.
# Current MyRiset code reads GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REDIRECT_URI.
GOOGLE_DRIVE_CLIENT_ID=
GOOGLE_DRIVE_CLIENT_SECRET=
GOOGLE_DRIVE_REDIRECT_URI=https://myriset.net/auth/google/drive/callback

VITE_APP_NAME="${APP_NAME}"
```

## Important Notes

- Generate `APP_KEY` on the server with `php artisan key:generate`.
- Keep `APP_DEBUG=false` in production.
- Keep the real database password only on the server.
- Google Drive credentials can remain empty until OAuth is configured.
- MyRiset currently reads Google Drive OAuth from `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and `GOOGLE_REDIRECT_URI`.
- `GOOGLE_DRIVE_CLIENT_ID`, `GOOGLE_DRIVE_CLIENT_SECRET`, and `GOOGLE_DRIVE_REDIRECT_URI` are documented as placeholder aliases only unless the code is later updated to read them.
- Do not commit `.env`, `.env.production`, credential JSON files, token dumps, private keys, or service account files.

## Google Drive OAuth

Google Drive is optional. If enabled, configure Google Cloud OAuth with this redirect URI:

```text
https://myriset.net/auth/google/drive/callback
```

Use the least required scope:

```text
https://www.googleapis.com/auth/drive.file
```

Do not commit the client secret. Do not print OAuth access tokens or refresh tokens in logs.

## Production Check Command

After configuring the server `.env`, run:

```bash
php artisan myriset:production-check
```

The command reports pass/warn/fail checks without printing secrets. Google Drive missing configuration is a warning, not a blocker.

## Production Admin

Do not use local/demo credentials in production. Create the first production admin intentionally, rotate the password immediately, and assign the required role according to the role/permission policy.

## Demo Data Warning

Do not run:

```bash
php artisan db:seed --class=MyRisetDemoSeeder
```

on production. Demo data is for local QA only.
