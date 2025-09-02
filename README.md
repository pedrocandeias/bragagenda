# Braga Agenda

A minimalistic event aggregation site for the city of Braga.

## Features

- Simple, lightweight design with only black and white colors
- Month navigation for browsing events
- Category filtering for events
- Simple CMS for managing events
- Scraper framework for automatically collecting events from various sources
- SQLite database for easy deployment

## Installation

### Quick Install (Recommended)

**Option 1: Web Installer**
1. Upload all files to your web server
2. Visit `install.php` in your browser
3. Follow the step-by-step installation wizard

**Option 2: Command Line Installer**
```bash
bash install.sh
```

**Option 3: Manual Installation**
1. Ensure PHP 7.4+ with required extensions
2. Run: `php check-requirements.php`
3. Run: `php setup-gnration-scraper.php`
4. Access the site at your domain

### Post-Installation Cleanup
After successful installation, remove installer files:
```bash
php cleanup-installer.php
```

## Directory Structure

```
/
├── index.php              # Main public page
├── config.php            # Configuration file
├── .htaccess             # Apache configuration
├── admin/                # CMS interface
│   ├── index.php         # Admin dashboard
│   ├── add-event.php     # Add new event
│   ├── edit-event.php    # Edit existing event
│   └── delete-event.php  # Delete event
├── assets/css/           # Stylesheets
├── includes/             # PHP classes
│   ├── Database.php      # Database handling
│   └── Event.php         # Event model
├── scrapers/             # Scraper framework
│   ├── BaseScraper.php   # Base scraper class
│   ├── ScraperManager.php # Scraper management
│   └── ExampleBragaScraper.php # Example scraper
├── data/                 # SQLite database (auto-created)
└── uploads/              # Event images
```

## Usage

### Admin Panel
Access `/admin/` to manage events manually through the CMS interface.

### Running Scrapers
Execute scrapers to automatically collect events:
```bash
php run-scrapers.php
```

### Setting up Scrapers
1. Add scraper sources:
```bash
php setup-example-scraper.php
```

2. Create custom scrapers by extending `BaseScraper` class
3. Add scraper sources to database using `ScraperManager`

### Creating Custom Scrapers

1. Create a new scraper class extending `BaseScraper`
2. Implement the `scrape()` method
3. Use `saveEvent()` to store events
4. Add the scraper source using `ScraperManager::addSource()`

Example:
```php
class MyBragaScraper extends BaseScraper {
    public function scrape() {
        // Your scraping logic here
        // Use $this->saveEvent() to save events
    }
}
```

## Automation

Set up a cron job to run scrapers automatically:
```bash
# Run every hour
0 * * * * /usr/bin/php /path/to/your/site/run-scrapers.php
```

## Requirements

- PHP 7.4+
- SQLite support
- Apache with mod_rewrite (optional, for .htaccess)
- Write permissions on `data/` and `uploads/` directories

## Security

- Database files are protected via .htaccess
- Consider adding HTTP authentication to the admin panel
- Regularly backup the SQLite database
- Keep PHP updated