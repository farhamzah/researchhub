# ResearchHub

ResearchHub is a Laravel and Filament foundation for a secure, multi-user research management system for dissertation and academic research workflows.

## Foundation

- Laravel application scaffold
- Filament admin panel at `/admin`
- PostgreSQL as the intended database
- UUID strategy documented in the contract pack before ResearchHub domain migrations
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
DB_DATABASE=researchhub
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

TASK 01 establishes the application skeleton only. ResearchHub business modules such as projects, documents, Google Drive integration, review links, surveys, respondents, analysis, and reports are intentionally not implemented yet.
