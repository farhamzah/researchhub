# MyRiset

MyRiset is a Laravel and Filament foundation for a secure, multi-user research management system for dissertation and academic research workflows.

## Foundation

- Laravel application scaffold
- Filament admin panel at `/admin`
- PostgreSQL as the intended database
- UUID strategy documented in the contract pack before MyRiset domain migrations
- Modular monolith folder scaffold under `app/Modules`

## Local Setup

Install dependencies:

```bash
composer install
npm install
```

Prepare a local environment file:

```bash
cp .env.example .env
php artisan key:generate
```

Configure PostgreSQL in `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=MyRiset
DB_USERNAME=postgres
DB_PASSWORD=
```

Run the app:

```bash
php artisan serve
npm run dev
```

Build frontend assets:

```bash
npm run build
```

## Task Boundary

TASK 01 establishes the application skeleton only. MyRiset business modules such as projects, documents, Google Drive integration, review links, surveys, respondents, analysis, and reports are intentionally not implemented yet.

## Public Survey Intro Images

- Survey builders can attach one optional intro illustration stored on the Laravel `public` disk under `surveys/{survey_id}/intro/`.
- Accepted formats are JPG, JPEG, PNG, and WEBP up to 2MB; 16:9 images around 1200x675 or 1600x900 are recommended.
- Alt text is required whenever an intro image is present. Captions and source notes are optional and should stay neutral so the illustration does not bias respondent answers.
- Public survey pages render the image only from the app-managed public storage URL, not from Google Drive hotlinks or private external file links.
