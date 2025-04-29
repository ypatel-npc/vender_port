<?php
session_start();
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '256M');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'vendor_port');

// Verify required session data
if (!isset($_SESSION['csv_file']) || !isset($_SESSION['mapping'])) {
    header('Location: upload.php');
    exit();
}

header('Content-Type: application/json');

try {
    // Create database connection
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $csv_file = $_SESSION['csv_file'];
    $mapping = $_SESSION['mapping'];

    // Create table based on mapped fields
    $table_name = 'imported_data_' . date('Y_m_d_His');
    $create_table_sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,";
    
    // Add columns based on mapping
    foreach ($mapping as $field => $csv_index) {
        $field = preg_replace('/[^a-zA-Z0-9_]/', '_', $field);
        $create_table_sql .= "\n`$field` VARCHAR(255),";
    }
    $create_table_sql = rtrim($create_table_sql, ',') . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    // Create the table
    $pdo->exec($create_table_sql);

    // Prepare insert statement
    $columns = array_keys($mapping);
    $placeholders = str_repeat('?,', count($mapping));
    $placeholders = rtrim($placeholders, ',');
    $insert_sql = "INSERT INTO `$table_name` (`" . implode('`, `', $columns) . "`) VALUES ($placeholders)";
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

                    // Output progress
                    $progress = min(100, round(($processed / $total_rows) * 100));
                    echo json_encode([
                        'progress' => $progress,
                        'processed' => $processed,
                        'total' => $total_rows
                    ]) . "\n";
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

    echo json_encode([
        'progress' => 100,
        'processed' => $processed,
        'total' => $total_rows,
        'complete' => true,
        'table' => $table_name
    ]);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode([
        'error' => 'Database error occurred. Please check error logs.'
    ]);
} 