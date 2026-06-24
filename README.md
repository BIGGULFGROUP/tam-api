# The African Mail — API

Backend API for [The African Mail](https://theafricanmail.com) — a video-first digital media platform covering African history, politics, culture, sports, and more.

## Stack

- **Framework:** Laravel 11 (PHP 8.3)
- **Database:** PostgreSQL
- **Auth:** Laravel Sanctum (token-based)
- **Queue:** Sync (database driver available)
- **Hosting:** Render (Docker)

## Architecture

```
tam-api (this repo)
  ├── Public API (/api/public/*)        → consumed by tam-web + tam-app
  ├── Frontend Admin (/api/frontend-admin/*) → consumed by tam-admin
  ├── Backend Admin (/api/backend-admin/*)   → consumed by tam-admin
  └── Scheduler                          → YouTube autofetch (every 15 min)
```

### Key Systems

| System | Description |
|---|---|
| Content Management | Articles, videos, shorts with draft/review/published workflow |
| Categories | 10 niche categories with per-category YouTube channel config |
| YouTube Autofetch | Automated video ingestion via YouTube Data API v3 with `forHandle`/`forUsername`/channel ID support |
| Comments | Public comment submission with admin moderation queue |
| Newsletters | Campaign management, popup templates, per-category subscriptions |
| User Auth | Sanctum tokens, 11 role tiers with granular permissions |
| Site Settings | Singleton configuration: branding, social links, analytics IDs, ad placements, SEO |
| Media | Upload and management via local storage |
| Analytics | Dashboard metrics for content, engagement, and traffic |

## Prerequisites

- PHP 8.3+
- Composer
- PostgreSQL 15+
- YouTube Data API v3 key (for autofetch)

## Environment Variables

Copy `.env.example` to `.env` and configure:

| Variable | Required | Description |
|---|---|---|
| `APP_URL` | Yes | API base URL (e.g. `https://tam-api.onrender.com`) |
| `DB_CONNECTION` | Yes | `pgsql` |
| `DB_URL` | Yes | PostgreSQL connection string |
| `FRONTEND_URL` | Yes | Comma-separated frontend origins for CORS |
| `SANCTUM_STATEFUL_DOMAINS` | Yes | Domains for SPA auth |
| `YOUTUBE_API_KEY` | No | YouTube Data API v3 key |
| `CRON_SECRET` | No | Header secret for cron-triggered autofetch |
| `ADMIN_SECRET` | No | Bypass token for admin operations |

## Local Development

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

## Scripts

| Command | Purpose |
|---|---|
| `php artisan migrate --seed` | Run migrations + seed categories, admin profile, site settings |
| `php artisan tam:youtube-autofetch` | Manually trigger YouTube content ingestion |
| `php artisan schedule:work` | Start the cron scheduler (runs autofetch every 15 min) |

## Deploy

Deployed via Docker on Render. The `start.sh` script handles:
1. `.env` generation from Render env vars
2. Config/route cache clearing
3. Database migrations
4. Config/route caching
5. Background seeding

### Render Config

```yaml
services:
  - type: web
    name: tam-api
    runtime: docker
    preDeployCommand: "php artisan migrate --force"
    healthCheckPath: /up
```

## License

Proprietary — © 2026 BIGGULFGROUP. All rights reserved.
