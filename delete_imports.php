<?php
/**
 * Delete Imports
 * 
 * This file handles the deletion of imported tables and vendor details.
 */

// Start session
session_start();

// Replace hardcoded credentials with config
require_once __DIR__ . '/config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Check if table parameter exists
    if (!isset($_POST['table'])) {
        throw new Exception('No table parameter provided');
    }
    
    $table_name = $_POST['table'];
    
    // Connect to database using the connection function
    $pdo = get_vendor_db_connection();
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Get vendor ID from import history
    $stmt = $pdo->prepare("SELECT vendor_id FROM import_history WHERE imported_table = ?");
    $stmt->execute([$table_name]);
    $vendor_id = $stmt->fetchColumn();
    
    if (!$vendor_id) {
        throw new Exception("No import history found for table '$table_name'");
    }
    
    // Delete from import_history
    $stmt = $pdo->prepare("DELETE FROM import_history WHERE imported_table = ?");
    $stmt->execute([$table_name]);
    
    // Check if this vendor has other imports
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM import_history WHERE vendor_id = ?");
    $stmt->execute([$vendor_id]);
    $other_imports = $stmt->fetchColumn();
    
    // If no other imports, delete vendor
    if ($other_imports == 0) {
        $stmt = $pdo->prepare("DELETE FROM vendors WHERE id = ?");
        $stmt->execute([$vendor_id]);
    }
    
    // Drop the imported table
    $stmt = $pdo->prepare("DROP TABLE IF EXISTS `" . str_replace('`', '``', $table_name) . "`");
    $stmt->execute();
    
    // Commit transaction
    $pdo->commit();
    
    // Return success
    echo json_encode([
        'success' => true,
        'message' => "Successfully deleted import '$table_name' and associated data."
    ]);
    
} catch (Exception $e) {
    // Rollback transaction if active
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log the error
    error_log("Error in delete_imports.php: " . $e->getMessage());
    
    // Return error
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 