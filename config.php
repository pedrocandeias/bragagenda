<?php
define('DB_PATH', __DIR__ . '/data/events.db');
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('BASE_URL', '/');

// Create data directory if it doesn't exist
if (!file_exists(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0755, true);
}

// Create uploads directory if it doesn't exist
if (!file_exists(__DIR__ . '/uploads')) {
    mkdir(__DIR__ . '/uploads', 0755, true);
}