# Installation Guide - Braga Agenda

## System Requirements

- PHP 7.4 or higher
- SQLite3 support (php-sqlite3 package)
- Apache or Nginx web server
- Write permissions for data/ and uploads/ directories

## Installation Steps

### 1. Install Required PHP Extensions

**Ubuntu/Debian:**
```bash
sudo apt update
sudo apt install php php-sqlite3 php-curl php-mbstring
```

**CentOS/RHEL:**
```bash
sudo yum install php php-pdo php-sqlite php-curl php-mbstring
```

**Or using dnf:**
```bash
sudo dnf install php php-pdo php-sqlite php-curl php-mbstring
```

### 2. Upload Files

Upload all project files to your web server document root or subdirectory.

### 3. Set Permissions

```bash
chmod 755 data/
chmod 755 uploads/
chmod 644 config.php
```

### 4. Test Installation

Visit your site URL in a browser. The database will be created automatically.

### 5. Set Up Scrapers

```bash
# Set up the Gnration scraper
php setup-gnration-scraper.php

# Test the scraper
php test-gnration-scraper.php

# Run scrapers manually
php run-scrapers.php
```

### 6. Automate Scraping (Optional)

Add to crontab to run scrapers every hour:
```bash
crontab -e
```

Add this line:
```bash
0 * * * * cd /path/to/your/site && php run-scrapers.php >> /var/log/braga-agenda-scraper.log 2>&1
```

## Troubleshooting

### SQLite Driver Not Found
If you get "could not find driver" error:
```bash
sudo apt install php-sqlite3
# or
sudo yum install php-pdo
```

### Permission Issues
```bash
sudo chown -R www-data:www-data /path/to/site/
# or
sudo chown -R apache:apache /path/to/site/
```

### Access Admin Panel
Navigate to `/admin/` and start adding events manually or wait for scrapers to run.

## Configuration

Edit `config.php` to customize:
- Database path
- Upload directory
- Base URL

## Security

- The admin panel has no authentication by default
- Consider adding HTTP basic auth via .htaccess
- Regularly backup the SQLite database
- Keep PHP and server software updated