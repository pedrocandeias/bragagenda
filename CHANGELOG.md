# Changelog

All notable changes to Braga Agenda are documented here.

---

## [Unreleased]

### Added
- `evento.php` — single event detail page: hero (title + image), description with HTML support, meta sidebar (date, location, category, external link), and related events grid (2026-02-20)
- `admin/configs.php` — unified settings page with Bootstrap tabs: SMTP and Facebook API (2026-02-20)
- `includes/phpmailer/` — bundled PHPMailer 6.9.x (Exception.php, PHPMailer.php, SMTP.php) for SMTP email sending without Composer (2026-02-19)
- `includes/Mailer.php` — thin wrapper over PHPMailer; reads SMTP config from the `settings` table; `isConfigured()` / `send()` static API (2026-02-19)
- `admin/smtp-settings.php` — admin-only SMTP configuration page (host, port, encryption, credentials, from address); includes AJAX test-email button (2026-02-19)
- `admin/confirm-email.php` — public token-validation page; activates user accounts on valid token; shows error on expired/missing tokens (2026-02-19)
- `admin/register.php` — self-service registration page; new accounts get `role='user'` and `active=0` (pending admin approval before login is permitted) (2026-02-19)
- `scrapers/MosteiroDeTibaesScraper.php` — scrape upcoming events from the Mosteiro de Tibães WordPress blog posts; extracts event date and time from Portuguese free-text using regex patterns; detects category from keywords (2026-02-19)
- `assets/placeholder-event.svg` — neutral SVG placeholder displayed when an event has no image (2026-02-19)
- `scripts/migrate-forum-braga-images.php` — one-time migration to download previously-remote Forum Braga images to local storage and null out genuine 404 URLs (2026-02-19)
- `includes/Auth.php` — session-based authentication helper; `requireLogin()`, `requireRole()`, AJAX variants, `hasMinRole()`, `login()`, `logout()` (2026-02-19)
- `admin/login.php` — Bootstrap login form; redirects to `setup.php` when no users exist; link to `register.php` (2026-02-19)
- `admin/logout.php` — destroys session and redirects to login (2026-02-19)
- `admin/setup.php` — first-run admin account creation; only accessible when the users table is empty (2026-02-19)
- `admin/users.php` — user management page (list, add, edit role/status, reset password, delete); admin-only (2026-02-19)
- `admin/_nav.php` — shared admin nav partial with username badge, role badge, and role-conditional links (2026-02-19)

### Changed
- `index.php` — single event links now redirect to `/evento/{id}` via nginx fallback handler (2026-02-20)
- `index.php` — event cards without an external URL now link to `/evento/{id}` instead of `#` (2026-02-20)
- `.htaccess` — add rewrite rule for `/evento/{id}` → `evento.php?id={id}` (2026-02-20)
- `assets/css/style.css` — add single event page styles (hero grid, meta sidebar, related grid, responsive breakpoints) (2026-02-20)
- `admin/add-event.php` — complete rewrite: Bootstrap card layout with `_nav.php`, image URL field, start/end date fields for multi-day events, category/location dropdowns, Quill WYSIWYG for description, redirect to index on success (2026-02-20)
- `includes/Event.php` — `createEvent()` now accepts optional `$startDate` and `$endDate` parameters (2026-02-20)
- `admin/_nav.php` — replace "Facebook API" + "SMTP" buttons with a single "Configs" button (2026-02-20)
- `admin/smtp-settings.php` — now a thin redirect to `configs.php?tab=smtp` (2026-02-20)
- `admin/facebook-settings.php` — now a thin redirect to `configs.php?tab=facebook` (2026-02-20)
- `admin/register.php` — after INSERT, generate a 32-byte confirmation token (24 h expiry), store it on the user row, and send a confirmation email via SMTP if configured; falls back to admin-approval message when SMTP is unconfigured (2026-02-19)
- `admin/login.php` — display `?message=` query-string as a success alert above the login form (2026-02-19)
- `includes/Database.php` — auto-migration adds `confirmation_token TEXT` and `token_expires_at DATETIME` columns to the `users` table (2026-02-19)
- `config.php` — bump `APP_VERSION` to `0.6.0` (2026-02-19)
- `index.php` — always render the event image block; fall back to placeholder SVG when image is null; removed conditional category chip branch (2026-02-19)
- `assets/css/style.css` — add `.event-img--placeholder` rule (contain + padding + grey background) (2026-02-19)
- `scrapers/BaseScraper.php` — add `maybeUpdateDate()`: when a URL-matched duplicate is found and the source website has rescheduled the event, update `event_date`, `start_date`, `end_date`, and `event_hash` in the database automatically (2026-02-19)
- `scrapers/TheatroCircoScraper.php` — complete rewrite; extract events from the `window.TC_EVENTS` JSON array embedded in `/programa/` (filter `category == "Agenda"`); add Portuguese date-string parser with year inference (2026-02-19)
- `scrapers/GnrationScraper.php` — complete rewrite; parse card HTML from `/ver-todos/` (`card__date`, `card__extra-date`, `card__hour`, `card__image`, `project__category`); no longer requires per-event page requests (2026-02-19)
- `includes/Database.php` — add `users` table and `created_by` column on events (auto-migration) (2026-02-19)
- `includes/Event.php` — `createEvent()` accepts optional `$createdBy` parameter (2026-02-19)
- `config.php` — add `APP_VERSION` constant (2026-02-19)
- All 13 admin PHP files — auth guards added; nav replaced with `_nav.php`; ownership checks (`created_by`) applied to edit/delete/toggle actions for contributor role (2026-02-19)
- `index.php` — clean URLs: all generated links now use `/{MM}/{YYYY}[/{category-slug}]` paths; `slugify()` resolves incoming slugs back to real category names; search form action updated to use clean path; `buildNavUrl()` rewritten; categories/locations loaded before event query for slug resolution (2026-02-19)
- `.htaccess` — two rewrite rules routing `/MM/YYYY[/slug]` to `index.php` (skips real files/dirs) (2026-02-19)

---

## [0.4.0] — 2025-09-05

### Added
- `scrapers/ConservatorioScraper.php` — scrape events from Conservatório de Braga
- `scrapers/MeetupScraper.php` — scrape events from Braga Meetup groups
- `scrapers/MuseuDDiogoScraper.php` — scrape events from Museu D. Diogo de Sousa
- `tests/test-conservatorio-scraper.php`, `tests/test-meetup-scraper.php`, `tests/test-museu-ddiogo-scraper.php` — test scripts for new scrapers
- `admin/venues.php` — venues management page in admin panel

---

## [0.3.0] — 2025-09-04

### Added
- `scrapers/BragaMediaArtsScraper.php` — scrape events from Braga Media Arts
- `scrapers/CentesimaScraper.php` — scrape events from Centésima Página; uses headless Node.js (`render-centesima.js`) to extract Firebase-rendered images
- `tests/test-braga-media-arts-scraper.php` — test script for Braga Media Arts scraper

### Removed
- Deprecated setup and test files for EspacoVita, Example, Gnration, and TheatroCirco scrapers
- Legacy image-extraction test scripts

### Changed
- `run-scrapers.php` — updated file permissions and scraper references
- `render-centesima.js` — increase wait time and add selector checks for more reliable image extraction

---

## [0.2.0] — 2025-09-03

### Added
- `install.sh` / `install.php` — automated installation and database setup scripts
- `setup/` directory with per-scraper setup scripts (`setup-gnration-scraper.php`, `setup-espaco-vita-scraper.php`)
- `check-requirements.php` — verify PHP version, extensions, and directory permissions before install
- `cleanup-installer.php` — remove installer files after setup

### Changed
- Fixed typo in location placeholder; updated scraper class references

---

## [0.1.0] — 2025-09-02

### Added
- Initial release of Braga Agenda
- SQLite database (`includes/Database.php`) with `events` and `sources` tables; MD5 hash duplicate detection; date-range support (`start_date`, `end_date`)
- `includes/Event.php` — event CRUD with pagination, filtering by category/location/search, and month navigation
- `index.php` — Bootstrap 5 public frontend with mosaic and list view, pagination (12 per page), category and location filters
- `admin/` — admin panel with event add/edit/delete, batch delete, and category manager (`admin/categories.php`)
- `scrapers/BaseScraper.php` — abstract base class with `saveEvent()`, `fetchUrl()`, `downloadImage()`, `updateLastScraped()`
- `scrapers/ScraperManager.php` — orchestrates multiple scrapers; `addSource()` helper
- `scrapers/GnrationScraper.php` — initial scraper for Gnration venue
- `scrapers/EspacoVitaScraper.php` — scraper for Espaço Vita venue
- `scrapers/ForumBragaScraper.php` — scraper for Forum Braga venue
- `scrapers/ExampleBragaScraper.php` — reference scraper template
- `run-scrapers.php` — CLI entry point to run all active scrapers
- `README.md`, `INSTALL.md`, `VERSION` — project documentation
