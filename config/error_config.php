<?php
/**
 * Error handling configuration for the entire application
 * Controls error display based on environment settings
 */

// Load environment variables if not already loaded
if (!defined('DB_HOST')) {
    $env_file = __DIR__ . '/../.env';
    if (file_exists($env_file)) {
        $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                
                // Remove quotes if present
                if (strpos($value, '"') === 0 || strpos($value, "'") === 0) {
                    $value = substr($value, 1, -1);
                }
                
                // Define constant if not already defined
                if (!defined($name)) {
                    define($name, $value);
                }
            }
        }
    }
}

// Check if SHOW_ERRORS is defined in .env, default to development mode (true) if not set
if (!defined('SHOW_ERRORS')) {
    define('SHOW_ERRORS', true); // Default to development mode
}

// Configure error reporting based on environment
if (SHOW_ERRORS === 'true' || SHOW_ERRORS === true) {
    // Development environment: Show all errors
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    // Production environment: Hide errors from users
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    
    // Ensure error logging is enabled
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
    
    // Create logs directory if it doesn't exist
    if (!is_dir(__DIR__ . '/../logs')) {
        mkdir(__DIR__ . '/../logs', 0755, true);
    }
}

// Set up custom exception handler
set_exception_handler(function ($exception) {
    // Log the exception
    error_log("Uncaught Exception: " . $exception->getMessage() . 
              " in " . $exception->getFile() . 
              " on line " . $exception->getLine() . 
              "\nStack trace: " . $exception->getTraceAsString());
    
    // In development, show detailed error
    if (SHOW_ERRORS === 'true' || SHOW_ERRORS === true) {
        echo "<h1>Application Error</h1>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($exception->getFile()) . "</p>";
        echo "<p><strong>Line:</strong> " . $exception->getLine() . "</p>";
        echo "<h2>Stack Trace:</h2>";
        echo "<pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
    } else {
        // Show generic message in production
        echo "An error occurred. Please try again or contact support.";
    }
    
    exit(1);
}); 