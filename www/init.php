<?php
require_once __DIR__ . '/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if database needs initialization
if (!check_database_initialization()) {
    // Database needs to be initialized
    if (import_database()) {
        $_SESSION['db_initialized'] = true;
    } else {
        die('Failed to initialize database. Please check your MySQL connection and permissions.');
    }
} 