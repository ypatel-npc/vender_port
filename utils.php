<?php
/**
 * Utility functions for the CSV Import system
 *
 * @package CSV_Import
 */

/**
 * Log debug information to file
 * 
 * @param string $message Message to log
 * @return void
 */
function log_debug($message) {
    $log_file = __DIR__ . '/debug.log';
    // Try to create log file if it doesn't exist
    if (!file_exists($log_file)) {
        touch($log_file);
        chmod($log_file, 0666); // Set read/write permissions
    }

    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message\n";
    
    // Add error handling for file writing
    if (file_put_contents($log_file, $log_message, FILE_APPEND) === false) {
        error_log("Failed to write to debug log file: " . $log_file);
        error_log("Message was: " . $log_message);
    }
}
?> 