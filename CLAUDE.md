# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Essential Commands

### Development Server
```bash
# Start PHP development server for testing
php -S localhost:8000
```

### Scraper Operations
```bash
# Run all configured scrapers
php run-scrapers.php

# Test individual scrapers
php tests/test-gnration-scraper.php
php tests/test-espaco-vita-scraper.php
php tests/test-theatro-circo-scraper.php

# Setup new scrapers
php setup/setup-gnration-scraper.php
php setup/setup-espaco-vita-scraper.php
```

### Database Operations
```bash
# Check system requirements
php setup/check-requirements.php

# Various utility scripts in scripts/
php scripts/clean-duplicates.php
php scripts/update-event-hashes.php
php scripts/update-all-images.php
```

### Syntax Validation
```bash
# Check PHP syntax
php -l filename.php
```

## Architecture Overview

### Core Application Structure
This is a PHP-based event aggregation system for Braga, Portugal, with automatic scraping capabilities and a Bootstrap 5 frontend.

**Data Flow**: Scrapers → Database → Admin Panel → Public Frontend

### Key Components

#### Database Layer (`includes/Database.php`)
- SQLite database with automatic table creation
- Two main tables: `events` and `sources`
- Events table includes date ranges (`start_date`, `end_date`) and duplicate detection via `event_hash`

#### Event Model (`includes/Event.php`)
- Handles event CRUD operations with pagination support
- Methods: `getEventsByMonth()`, `getEventsCountByMonth()`, `getAllCategories()`, `getAllLocations()`
- Supports filtering by category, location, and search terms

#### Scraper Framework (`scrapers/`)
- Abstract `BaseScraper` class with common functionality
- Each venue has its own scraper class extending `BaseScraper`
- `ScraperManager` orchestrates multiple scrapers
- Built-in duplicate detection and Portuguese date parsing
- Multi-category support: events with "Cinema / Música / Teatro" create 3 separate database entries

#### Frontend (`index.php`)
- Bootstrap 5 with responsive design
- Two view modes: mosaic (cards) and list view
- Pagination (12 events per page)
- Month navigation with filter preservation
- Category and location filtering

#### Admin Panel (`admin/`)
- Bootstrap 5 admin interface with batch operations
- Event management with search and pagination
- **Category Manager** (`admin/categories.php`): merge, rename, delete categories
- **Batch Delete**: select multiple events for deletion
- Modern UI with confirmations and success/error feedback

### Scraper Implementation Patterns

#### Custom Scraper Development
1. Extend `BaseScraper` class
2. Implement `scrape()` method returning `['events_scraped' => int, 'errors' => array]`
3. Use `$this->saveEvent()` for database storage
4. Handle Portuguese dates and multi-category strings
5. Use `$this->fetchUrl()` for HTTP requests
6. Add to database via `ScraperManager::addSource()`

#### Multi-Category Handling
Scrapers automatically split categories like "Cinema / Música" into separate events:
```php
$categories = $this->splitCategories($categoryString);
foreach ($categories as $category) {
    $this->saveEvent(..., $category, ...);
}
```

#### Date Range Support
Events can have date ranges stored in `start_date` and `end_date` fields, with `event_date` as primary date.

### Database Schema Considerations
- Events use MD5 hash for duplicate detection based on title, date, and URL
- Source tracking via `source_id` foreign key to `sources` table
- Image URLs stored as strings, not uploaded files
- Category field supports Portuguese categories (Música, Teatro, Dança, etc.)

### File Organization
- `setup/`: Installation and scraper setup scripts
- `tests/`: Individual scraper test files  
- `scripts/`: Maintenance and cleanup utilities
- `admin/`: Complete admin interface with category management
- `scrapers/`: Venue-specific scraper implementations

### Current Scrapers
- **GnrationScraper**: Events from Gnration venue with multi-category support
- **EspacoVitaScraper**: Events from Espaço Vita venue  
- **TheatroCircoScraper**: Events from Theatro Circo venue (note: correct spelling with 'h')

### Testing Approach
Each scraper has a corresponding test file in `tests/` that verifies functionality and displays recent events from the database.

### Configuration
- SQLite database path: `data/events.db`
- Image uploads: `uploads/` directory
- No external dependencies or package managers
- Bootstrap 5 and Bootstrap Icons loaded via CDN