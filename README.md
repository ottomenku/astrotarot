# Tarot

Minimal Laravel 12 app with bilingual UI, role-based users, templates, and Swiss Ephemeris positions.

> Laravel 13 needs PHP 8.3+. This machine has PHP 8.2 (XAMPP), so the project uses Laravel 12.

## Requirements

- PHP 8.2+
- MySQL (database: `tarot`, user: `root`, empty password)
- Python 3.11+ with project venv (`.venv`)
- Fixed-star catalog at `python/ephe/sefstars.txt` (already included)

## Setup

```bash
composer install
copy .env.example .env
php artisan key:generate
python -m venv .venv
.\.venv\Scripts\python.exe -m pip install -r requirements.txt
php artisan migrate --seed
php artisan serve
```

Default admin is imported from Astro (`vs_cline`) when available — same email/password as there (`admin@example.com`).

## Admin

- `/admin/ephemeris` – object positions (sign, degree, fixed-star conjunction)
- `/admin/users` – users and roles (`user`, `pro`, `expert`, `admin`)
- `/admin/templates` – template selector

## Tests

```bash
php artisan test
```
