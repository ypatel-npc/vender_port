<?php
/**
 * Delete Imports
 * 
 * This file handles the deletion of imported tables and vendor details.
 * Compatible with both MySQL and MariaDB
 */

// Start session
session_start();

// Replace hardcoded credentials with config
require_once __DIR__ . '/config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Debug logging for troubleshooting
error_log("Delete imports script started");

try {
    // Check if table parameter exists
    if (!isset($_POST['table'])) {
        throw new Exception('No table parameter provided');
    }
    
    $table_name = $_POST['table'];
    error_log("Attempting to delete table: $table_name");
    
    // Connect to database using the connection function
    $pdo = get_vendor_db_connection();
    
    // We'll handle operations without relying on transactions for cross-database compatibility
    // This is a safer approach for MySQL/MariaDB compatibility
    
    // First, verify the table exists and get vendor ID
    $stmt = $pdo->prepare("SELECT vendor_id FROM import_history WHERE imported_table = ?");
    $stmt->execute([$table_name]);
    $vendor_id = $stmt->fetchColumn();
    
    if (!$vendor_id) {
        error_log("No import history found for table '$table_name'");
        throw new Exception("No import history found for table '$table_name'");
    }
    
    error_log("Found vendor ID: $vendor_id for table $table_name");
    
    // Step 1: Delete from import_history
    $stmt = $pdo->prepare("DELETE FROM import_history WHERE imported_table = ?");
    $success1 = $stmt->execute([$table_name]);
    error_log("Deleted from import_history: " . ($success1 ? "success" : "failed"));
    
    // Step 2: Check if this vendor has other imports
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM import_history WHERE vendor_id = ?");
    $stmt->execute([$vendor_id]);
    $other_imports = $stmt->fetchColumn();
    
    // Step 3: If no other imports, delete vendor
    $success2 = true;
    if ($other_imports == 0) {
        $stmt = $pdo->prepare("DELETE FROM vendors WHERE id = ?");
        $success2 = $stmt->execute([$vendor_id]);
        error_log("Deleted vendor with ID: $vendor_id - " . ($success2 ? "success" : "failed"));
    } else {
        error_log("Vendor has $other_imports other imports, not deleting vendor");
    }
    
    // Step 4: Drop the imported table
    $success3 = false;
    try {
        $sql = "DROP TABLE IF EXISTS `" . str_replace('`', '``', $table_name) . "`";
        $stmt = $pdo->prepare($sql);
        $success3 = $stmt->execute();
        error_log("Dropped table $table_name: " . ($success3 ? "success" : "failed"));
    } catch (Exception $e) {
        error_log("Error dropping table: " . $e->getMessage());
        // If we can't drop the table, still consider it a success if we removed from import_history
    }
    
    // Consider the operation successful if we deleted the import record
    if ($success1) {
        echo json_encode([
            'success' => true,
            'message' => "Successfully deleted import '$table_name'"
                . ($success3 ? " and dropped the table." : " but could not drop the table.")
        ]);
    } else {
        throw new Exception("Failed to delete import record for '$table_name'");
    }
    
} catch (Exception $e) {
    // Log the error
    error_log("Error in delete_imports.php: " . $e->getMessage());
    
    // Return error with user-friendly message
    echo json_encode([
        'success' => false,
        'message' => "Failed to delete: " . $e->getMessage()
    ]);
}