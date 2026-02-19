# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Essential Commands

### Development Server
```bash
php -S localhost:8000
```

### Scraper Operations
```bash
# Run all active scrapers
php run-scrapers.php

# Test individual scrapers
php tests/test-gnration-scraper.php
php tests/test-theatro-circo-scraper.php
php tests/test-centesima-scraper.php
php tests/test-espaco-vita-scraper.php
php tests/test-forum-braga-scraper.php
php tests/test-braga-media-arts-scraper.php
php tests/test-conservatorio-scraper.php
php tests/test-meetup-scraper.php
php tests/test-museu-ddiogo-scraper.php

# Register/setup scrapers in the database
php setup/setup-gnration-scraper.php
php setup/setup-centesima-scraper.php
php setup/setup-forum-braga-scraper.php
php setup/setup-espaco-vita-scraper.php
```

### Database & Maintenance
```bash
php setup/check-requirements.php
php scripts/clean-duplicates.php
php scripts/update-event-hashes.php
php scripts/update-all-images.php
php scripts/update-event-locations.php
```

### Syntax Validation
```bash
php -l filename.php
```

## Architecture Overview

### Core Application Structure
PHP-based event aggregation system for Braga, Portugal. No external package managers — Bootstrap 5 and Bootstrap Icons loaded via CDN.

**Data Flow**: Scrapers → SQLite Database → Admin Panel → Public Frontend

### Configuration (`config.php`)
- `DB_PATH`: `data/events.db`
- `UPLOAD_PATH`: `uploads/`
- `BASE_URL`: `/`
- Auto-creates `data/` and `uploads/` directories on load

### Database Layer (`includes/Database.php`)
- SQLite via PDO with automatic table creation and schema migration
- Auto-adds missing columns on every connection (safe to run repeatedly)
- Two tables: `events` and `sources`

**Events table columns**:
```
id, title, description, event_date, start_date, end_date,
category, location, image (URL), url (source), source_id,
event_hash (MD5, UNIQUE), hidden (bool), featured (bool),
created_at, updated_at
```

**Sources table columns**:
```
id, name, url, scraper_class, active (bool), last_scraped, created_at
```

### Event Model (`includes/Event.php`)
- `getEventsByMonth($year, $month, $location, $category, $limit, $offset)`
- `getEventsCountByMonth(...)` — for pagination
- `getAllCategories()`, `getAllLocations()`
- `createEvent()`, `updateEvent()`, `deleteEvent()`, `getEventById()`
- `toggleHidden()`, `toggleFeatured()`, `setHidden()`, `setFeatured()`

### Public Frontend (`index.php`)
- Bootstrap 5, minimalist black/white design
- Month/year navigation with Portuguese month names
- Two view modes: mosaic (cards) and list
- Filters: category, location, search (preserved across page navigation)
- Pagination: 12 events per page
- Multi-day event date range display

### Admin Panel (`admin/`)

| File | Purpose |
|------|---------|
| `index.php` | Event listing, search, filter, batch delete, toggle hidden/featured |
| `categories.php` | Merge, rename, delete categories |
| `venues.php` | Merge, rename, delete venues/locations with event counts |
| `add-event.php` | Add new event manually |
| `edit-event.php` | Edit existing event |
| `edit-event-ajax.php` | AJAX quick edit handler |
| `delete-event.php` | Delete single event |
| `batch-delete.php` | Delete multiple events |
| `toggle-event-status.php` | Toggle hidden/featured via AJAX |

## Scraper Framework

### Base Classes (`scrapers/`)

**`BaseScraper.php`** — Abstract base:
- `scrape()` — Must be implemented; returns `['events_scraped' => int, 'errors' => array]`
- `saveEvent()` — Saves with duplicate detection (hash, URL, title+date)
- `fetchUrl()` — HTTP GET with Mozilla user-agent
- `updateLastScraped()` — Updates `sources.last_scraped`
- Hash: `MD5(strtolower($title) . '|' . date('Y-m-d', $date) . '|' . $url)`

**`ScraperManager.php`**:
- `runAllScrapers()` — Runs all active sources from database
- `addSource($name, $url, $scraperClass)` — Registers scraper in sources table

### Active Scrapers

| Scraper | Venue | Notes |
|---------|-------|-------|
| `GnrationScraper.php` | Gnration | Multi-category support |
| `CentesimaScraper.php` | Centésima | JS rendering via `render-centesima.js` (Node.js); multiple API fallbacks |
| `TheatroCircoScraper.php` | Theatro Circo | Note: 'h' in Theatro |
| `ForumBragaScraper.php` | Forum Braga | Multiple show categories |
| `EspacoVitaScraper.php` | Espaço Vita | |
| `BragaMediaArtsScraper.php` | Braga Media Arts | Hashtag-to-category conversion |
| `ConservatorioScraper.php` | Conservatório de Música | WordPress MEC REST API |
| `MeetupScraper.php` | Meetup.com (Braga) | Parses Next.js embedded JSON |
| `MuseuDDiogoScraper.php` | Museu D. Diogo de Sousa | HTML block parsing |
| `ExampleBragaScraper.php` | — | Template for new scrapers |

### Scraper Patterns

**Multi-category splitting** (automatic):
```php
$categories = $this->splitCategories($categoryString); // splits "Cinema / Música"
foreach ($categories as $category) {
    $this->saveEvent(..., $category, ...);
}
```

**Date range support**: Use `start_date` and `end_date` alongside `event_date`.

**New scraper checklist**:
1. Extend `BaseScraper`, implement `scrape()`
2. Use `$this->saveEvent()` and `$this->fetchUrl()`
3. Create `setup/setup-<name>-scraper.php` to register in DB
4. Create `tests/test-<name>-scraper.php`
5. Add entry to this file's scrapers table

## File Organization

```
bragagenda/
├── index.php              # Public frontend
├── config.php             # Global configuration
├── run-scrapers.php       # CLI scraper runner
├── includes/              # Database.php, Event.php
├── scrapers/              # BaseScraper, ScraperManager, venue scrapers
├── admin/                 # Full admin CMS
├── assets/css/            # style.css (public), admin.css
├── setup/                 # Installation & scraper registration scripts
├── tests/                 # Per-scraper test files
├── scripts/               # Maintenance & cleanup utilities
├── data/                  # events.db (SQLite, gitignored)
└── uploads/               # Event images (gitignored)
```

## Key Conventions
- Portuguese categories: Música, Teatro, Dança, Cinema, Arte, etc.
- Images stored as URL strings, not uploaded files
- All dates stored as `DATETIME` in SQLite; use `Y-m-d H:i:s` format
- `event_hash` is UNIQUE — duplicate events are silently skipped on insert
- `hidden = 1` removes event from public view; `featured = 1` highlights it
- Node.js only needed for Centésima JS rendering (`scrapers/render-centesima.js`)
