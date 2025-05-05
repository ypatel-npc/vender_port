<?php
session_start();
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '256M');

// Remove the conflicting JSON header and keep only SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // For nginx

// Include database configuration
require_once __DIR__ . '/config/database.php';

// Verify required session data
if (!isset($_SESSION['csv_file']) || !isset($_SESSION['mapping'])) {
    echo "data: " . json_encode(['error' => 'Missing required session data']) . "\n\n";
    exit();
}

// Debug logging
error_log("CSV File: " . (isset($_SESSION['csv_file']) ? $_SESSION['csv_file'] : 'not set'));
error_log("Mapping: " . (isset($_SESSION['mapping']) ? json_encode($_SESSION['mapping']) : 'not set'));

try {
    // Create database connection using the standardized function
    $pdo = get_vendor_db_connection();

    $csv_file = $_SESSION['csv_file'];
    $mapping = $_SESSION['mapping'];

    // First, create a function to standardize field name transformation
    function sanitize_field_name($field) {
        // First replace spaces with underscores
        $field = str_replace(' ', '_', $field);
        // Then remove trailing special characters
        $field = rtrim($field, ':;.,');
        // Finally replace any remaining non-alphanumeric chars with underscores
        $field = preg_replace('/[^a-zA-Z0-9_]/', '_', $field);
        return $field;
    }

    // Create table based on mapped fields
    $table_name = 'imported_data_' . date('Y_m_d_His');
    $create_table_sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,";
    
    // Add columns based on mapping
    foreach ($mapping as $field => $csv_index) {
        $sanitized_field = sanitize_field_name($field);
        $create_table_sql .= "\n`$sanitized_field` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,";
    }
    $create_table_sql = rtrim($create_table_sql, ',') . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_520_ci;";
    
    // Create the table
    $pdo->exec($create_table_sql);

    // Prepare insert statement
    $columns = array_keys($mapping);
    
    // Convert column names with spaces to match the database column names with underscores
    // Also remove any trailing colons or other special characters
    $db_columns = array_map('sanitize_field_name', $columns);
    
    $placeholders = str_repeat('?,', count($mapping));
    $placeholders = rtrim($placeholders, ',');
    $insert_sql = "INSERT INTO `$table_name` (`" . implode('`, `', $db_columns) . "`) VALUES ($placeholders)";
    $stmt = $pdo->prepare($insert_sql);

    // Process CSV in chunks
    $chunk_size = 1000;
    $processed = 0;
    $total_rows = 0;

    // Count total rows
    $handle = fopen($csv_file, 'r');
    while (fgetcsv($handle)) {
        $total_rows++;
    }
    fclose($handle);
    $total_rows--; // Subtract header row

    // Process data
    $handle = fopen($csv_file, 'r');
    // Skip header row
    fgetcsv($handle);

    $pdo->beginTransaction();
    $chunk_count = 0;

    while (!feof($handle)) {
        $data = fgetcsv($handle);
        if ($data) {
            // Map data according to field mapping
            $mapped_data = [];
            foreach ($mapping as $field => $csv_index) {
                $mapped_data[] = isset($data[$csv_index]) ? $data[$csv_index] : null;
            }

            try {
                $stmt->execute($mapped_data);
                $processed++;
                $chunk_count++;

                // Commit every chunk_size records
                if ($chunk_count >= $chunk_size) {
                    $pdo->commit();
                    $pdo->beginTransaction();
                    $chunk_count = 0;

                    // Output progress in SSE format
                    $progress = min(100, round(($processed / $total_rows) * 100));
                    echo "data: " . json_encode([
                        'progress' => $progress,
                        'processed' => $processed,
                        'total' => $total_rows
                    ]) . "\n\n";
                    ob_flush();
                    flush();
                }
            } catch (PDOException $e) {
                error_log("Error importing row $processed: " . $e->getMessage());
                // Continue with next row on error
                continue;
            }
        }
    }

    // Commit any remaining records
    if ($chunk_count > 0) {
        $pdo->commit();
    }

    fclose($handle);

    // Clean up
    unlink($csv_file);
    unset($_SESSION['csv_file']);
    unset($_SESSION['csv_headers']);
    unset($_SESSION['mapping']);

    // Add after successful table creation
    $stmt = $pdo->prepare("INSERT INTO import_history (vendor_id, imported_table, file_name, total_records) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['vendor_id'],
        $table_name,
        basename($csv_file),
        $total_rows
    ]);

    // Final output in SSE format
    echo "data: " . json_encode([
        'progress' => 100,
        'processed' => $processed,
        'total' => $total_rows,
        'complete' => true,
        'table' => $table_name
    ]) . "\n\n";

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo "data: " . json_encode([
        'error' => 'Database error occurred. Please check error logs.'
    ]) . "\n\n";
} 