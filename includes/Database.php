<?php
class Database {
    private $db;
    
    public function __construct() {
        $this->db = new PDO('sqlite:' . __DIR__ . '/../data/events.db');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createTables();
    }
    
    public function getConnection() {
        return $this->db;
    }
    
    private function createTables() {
        $sql = "
        CREATE TABLE IF NOT EXISTS events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT,
            event_date DATETIME NOT NULL,
            category TEXT,
            location TEXT,
            image TEXT,
            url TEXT,
            source_id INTEGER,
            event_hash TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (source_id) REFERENCES sources(id)
        );
        
        CREATE TABLE IF NOT EXISTS sources (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            url TEXT NOT NULL,
            scraper_class TEXT NOT NULL,
            active BOOLEAN DEFAULT 1,
            last_scraped DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE INDEX IF NOT EXISTS idx_events_date ON events(event_date);
        CREATE INDEX IF NOT EXISTS idx_events_category ON events(category);
        CREATE INDEX IF NOT EXISTS idx_events_location ON events(location);
        CREATE UNIQUE INDEX IF NOT EXISTS idx_events_hash ON events(event_hash);
        CREATE INDEX IF NOT EXISTS idx_events_url ON events(url);
        ";
        
        // Add new columns and tables to existing schema
        $this->addEventHashColumn();
        $this->addLocationColumn();
        $this->addDateRangeColumns();
        $this->addVisibilityColumns();
        $this->addSettingsTable();
        $this->addUsersTable();
        $this->addCreatedByColumn();
        $this->addConfirmationTokenColumns();
        
        $this->db->exec($sql);
    }
    
    private function addEventHashColumn() {
        try {
            // Check if event_hash column exists
            $stmt = $this->db->query("PRAGMA table_info(events)");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $hasHashColumn = false;
            foreach ($columns as $column) {
                if ($column['name'] === 'event_hash') {
                    $hasHashColumn = true;
                    break;
                }
            }
            
            if (!$hasHashColumn) {
                $this->db->exec("ALTER TABLE events ADD COLUMN event_hash TEXT");
            }
        } catch (Exception $e) {
            // Column might already exist, ignore error
        }
    }
    
    private function addLocationColumn() {
        try {
            // Check if location column exists
            $stmt = $this->db->query("PRAGMA table_info(events)");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $hasLocationColumn = false;
            foreach ($columns as $column) {
                if ($column['name'] === 'location') {
                    $hasLocationColumn = true;
                    break;
                }
            }
            
            if (!$hasLocationColumn) {
                $this->db->exec("ALTER TABLE events ADD COLUMN location TEXT");
            }
        } catch (Exception $e) {
            // Column might already exist, ignore error
        }
    }
    
    private function addDateRangeColumns() {
        try {
            // Check if start_date and end_date columns exist
            $stmt = $this->db->query("PRAGMA table_info(events)");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $hasStartDate = false;
            $hasEndDate = false;
            foreach ($columns as $column) {
                if ($column['name'] === 'start_date') {
                    $hasStartDate = true;
                }
                if ($column['name'] === 'end_date') {
                    $hasEndDate = true;
                }
            }
            
            if (!$hasStartDate) {
                $this->db->exec("ALTER TABLE events ADD COLUMN start_date DATETIME");
            }
            
            if (!$hasEndDate) {
                $this->db->exec("ALTER TABLE events ADD COLUMN end_date DATETIME");
            }
        } catch (Exception $e) {
            // Columns might already exist, ignore error
        }
    }
    
    private function addSettingsTable() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    private function addUsersTable() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                email TEXT,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'user',
                active BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_login DATETIME
            )
        ");
    }

    private function addConfirmationTokenColumns() {
        try {
            $stmt = $this->db->query("PRAGMA table_info(users)");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $names = array_column($columns, 'name');

            if (!in_array('confirmation_token', $names)) {
                $this->db->exec("ALTER TABLE users ADD COLUMN confirmation_token TEXT");
            }
            if (!in_array('token_expires_at', $names)) {
                $this->db->exec("ALTER TABLE users ADD COLUMN token_expires_at DATETIME");
            }
        } catch (Exception $e) {
            // Ignore — columns may already exist
        }
    }

    private function addCreatedByColumn() {
        try {
            $stmt = $this->db->query("PRAGMA table_info(events)");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $hasColumn = false;
            foreach ($columns as $col) {
                if ($col['name'] === 'created_by') {
                    $hasColumn = true;
                    break;
                }
            }
            if (!$hasColumn) {
                $this->db->exec("ALTER TABLE events ADD COLUMN created_by INTEGER REFERENCES users(id)");
            }
        } catch (Exception $e) {
            // Ignore
        }
    }

    private function addVisibilityColumns() {
        try {
            // Check if hidden and featured columns exist
            $stmt = $this->db->query("PRAGMA table_info(events)");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $hasHidden = false;
            $hasFeatured = false;
            foreach ($columns as $column) {
                if ($column['name'] === 'hidden') {
                    $hasHidden = true;
                }
                if ($column['name'] === 'featured') {
                    $hasFeatured = true;
                }
            }
            
            if (!$hasHidden) {
                $this->db->exec("ALTER TABLE events ADD COLUMN hidden BOOLEAN DEFAULT 0");
            }
            
            if (!$hasFeatured) {
                $this->db->exec("ALTER TABLE events ADD COLUMN featured BOOLEAN DEFAULT 0");
            }
        } catch (Exception $e) {
            // Columns might already exist, ignore error
        }
    }
}