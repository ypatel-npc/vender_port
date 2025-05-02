<?php
/**
 * Database Configuration
 * 
 * Centralized database configuration for the Vendor Port application
 */

// Load environment variables
$env_file = __DIR__ . '/../.env';
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        // Remove quotes if present
        if (strpos($value, '"') === 0 || strpos($value, "'") === 0) {
            $value = substr($value, 1, -1);
        }
        
        define($name, $value);
    }
}

// Database connection functions
function get_vendor_db_connection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            error_log("Failed to connect to vendor database: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    return $pdo;
}

function get_npc_db1_connection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . NPC_DB1_HOST . ";dbname=" . NPC_DB1_NAME . ";charset=utf8mb4",
                NPC_DB1_USER,
                NPC_DB1_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            error_log("Failed to connect to NPC database 1: " . $e->getMessage());
            throw new Exception("NPC database 1 connection failed");
        }
    }
    
    return $pdo;
}

function get_npc_website_connection() {
    static $pdo = null;
    
    if ($pdo === null && defined('NPC_WEBSITE_HOST')) {
        try {
            $pdo = new PDO(
                "mysql:host=" . NPC_WEBSITE_HOST . ";dbname=" . NPC_WEBSITE_NAME . ";charset=utf8mb4",
                NPC_WEBSITE_USER,
                NPC_WEBSITE_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            error_log("Failed to connect to NPC website database: " . $e->getMessage());
            throw new Exception("NPC website database connection failed");
        }
    }
    
    return $pdo;
}

// Add more database connection functions as needed 