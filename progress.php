<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

echo "<!-- DEBUG: Script started -->\n";

// Include database configuration
require_once __DIR__ . '/config/database.php';
echo "<!-- DEBUG: Database config loaded -->\n";

require_once __DIR__ . '/utils.php';
echo "<!-- DEBUG: Utils loaded -->\n";

// Check for required session data and redirect if missing
if (!isset($_SESSION['csv_file']) || !isset($_SESSION['mapping'])) {
    echo "<!-- DEBUG: Missing session data, redirecting -->\n";
    header('Location: upload.php');
    exit();
}

echo "<!-- DEBUG: Session data exists -->\n";
echo "<!-- DEBUG: CSV file: " . (isset($_SESSION['csv_file']) ? $_SESSION['csv_file'] : 'NOT SET') . " -->\n";
echo "<!-- DEBUG: Mapping: " . (isset($_SESSION['mapping']) ? json_encode($_SESSION['mapping']) : 'NOT SET') . " -->\n";

// Handle form submission for processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_processing'])) {
    echo "<!-- DEBUG: Form submitted, starting processing -->\n";
    try {
        // Ensure vendor_id is set
        if (!isset($_SESSION['vendor_id'])) {
            $pdo = get_vendor_db_connection();
            $stmt = $pdo->query("SELECT id FROM vendors ORDER BY id ASC LIMIT 1");
            $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($vendor) {
                $_SESSION['vendor_id'] = $vendor['id'];
            } else {
                // Create a default vendor if none exists
                $stmt = $pdo->prepare("INSERT INTO vendors (vendor_name, contact_person, email, phone, address) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute(['Default Vendor', '', '', '', '']);
                $_SESSION['vendor_id'] = $pdo->lastInsertId();
            }
        }

        echo "<!-- DEBUG: Processing CSV file -->\n";
        // Process the CSV file
        $result = process_csv_file($_SESSION['csv_file'], $_SESSION['mapping'], $_SESSION['vendor_id']);
        
        echo "<!-- DEBUG: Processing complete, redirecting -->\n";
        // Store result in session for display
        $_SESSION['processing_result'] = $result;
        
        // Redirect to results page
        header('Location: processing_results.php');
        exit();
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        echo "<!-- DEBUG: Error occurred: " . $error_message . " -->\n";
    }
}

echo "<!-- DEBUG: About to output HTML -->\n";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process CSV File</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            background: #f5f5f5; 
            margin: 0; 
            padding: 20px;
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            background: #fff; 
            border-radius: 8px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.1); 
            padding: 30px; 
            text-align: center; 
        }
        .info-box {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: left;
        }
        .info-box h3 {
            margin-top: 0;
            color: #1976d2;
        }
        .btn { 
            display: inline-block; 
            padding: 12px 24px; 
            border: none; 
            border-radius: 6px; 
            background: #4CAF50; 
            color: white; 
            text-decoration: none; 
            font-weight: bold; 
            cursor: pointer; 
            font-size: 16px;
            margin: 10px;
        }
        .btn:hover { 
            background: #45a049; 
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .error {
            color: #b71c1c;
            background: #ffebee;
            border: 1px solid #ffcdd2;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Ready to Process CSV File</h2>
        
        <div class="info-box">
            <h3>📋 Processing Summary</h3>
            <p><strong>File:</strong> <?php echo htmlspecialchars(basename($_SESSION['csv_file'])); ?></p>
            <p><strong>Columns to Import:</strong> <?php echo count($_SESSION['mapping']); ?></p>
            <p><strong>Vendor ID:</strong> <?php echo isset($_SESSION['vendor_id']) ? $_SESSION['vendor_id'] : 'Will be set automatically'; ?></p>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="error">
                <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="info-box">
            <h3>⚠️ Important Notes</h3>
            <ul>
                <li>This process will import all data from your CSV file</li>
                <li>590 numbers will be automatically extracted where applicable</li>
                <li>The original CSV file will be deleted after processing</li>
                <li>Processing may take a few moments depending on file size</li>
            </ul>
        </div>

        <form method="POST" action="">
            <input type="submit" name="start_processing" value="🚀 Start Processing" class="btn">
            <a href="preview.php" class="btn btn-secondary">← Back to Preview</a>
        </form>
    </div>
</body>
</html>

<?php
// Function to process CSV file
function process_csv_file($csv_file, $mapping, $vendor_id) {
    $pdo = get_vendor_db_connection();
    
    // Create table based on mapped fields
    $table_name = 'imported_data_' . date('Y_m_d_His');
    
    $create_table_sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,";
    
    // Add columns based on mapping
    foreach ($mapping as $field => $csv_index) {
        $sanitized_field = sanitize_field_name($field);
        $create_table_sql .= "\n`$sanitized_field` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,";
    }
    $create_table_sql = rtrim($create_table_sql, ',') . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;";
    
    // Create the table
    $pdo->exec($create_table_sql);

    // Prepare insert statement
    $columns = array_keys($mapping);
    $db_columns = array_map('sanitize_field_name', $columns);
    
    $placeholders = str_repeat('?,', count($mapping));
    $placeholders = rtrim($placeholders, ',');
    $insert_sql = "INSERT INTO `$table_name` (`" . implode('`, `', $db_columns) . "`) VALUES ($placeholders)";
    $stmt = $pdo->prepare($insert_sql);

    // Process CSV
    $processed = 0;
    $total_rows = 0;
    $errors = [];

    // Count total rows
    $handle = fopen($csv_file, 'r');
    if (!$handle) {
        throw new Exception("Cannot open CSV file: " . $csv_file);
    }
    
    while (($data = fgetcsv($handle)) !== false) {
        $total_rows++;
    }
    fclose($handle);
    $total_rows--; // Subtract header row

    // Process data
    $handle = fopen($csv_file, 'r');
    if (!$handle) {
        throw new Exception("Cannot open CSV file for processing: " . $csv_file);
    }
    
    fgetcsv($handle); // Skip header row

    while (($data = fgetcsv($handle)) !== false) {
        if ($data) {
            // Map data according to field mapping
            $mapped_data = [];
            foreach ($mapping as $field => $csv_index) {
                $value = isset($data[$csv_index]) ? $data[$csv_index] : null;
                
                // Apply 590 extraction for the 590 field
                if (trim($field) === '590' && !empty($value)) {
                    // Clean the value (remove newlines and extra spaces)
                    $clean_value = trim(str_replace(["\n", "\r"], ' ', $value));
                    
                    $extraction_result = advanced_590_extraction($clean_value);
                    $mapped_data[] = $extraction_result['extracted'];
                } else {
                    $mapped_data[] = $value;
                }
            }

            try {
                $stmt->execute($mapped_data);
                $processed++;
            } catch (PDOException $e) {
                $errors[] = "Row " . ($processed + 1) . ": " . $e->getMessage();
            }
        }
    }

    fclose($handle);

    // Clean up CSV file
    if (file_exists($csv_file)) {
        unlink($csv_file);
    }

    // Add to import history
    try {
        $stmt = $pdo->prepare("INSERT INTO import_history (vendor_id, imported_table, file_name, total_records) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $vendor_id,
            $table_name,
            basename($csv_file),
            $processed
        ]);
    } catch (PDOException $e) {
        // Don't fail the whole process for this
    }

    // Clear session data
    unset($_SESSION['csv_file']);
    unset($_SESSION['csv_headers']);
    unset($_SESSION['mapping']);

    return [
        'success' => true,
        'table_name' => $table_name,
        'processed' => $processed,
        'total_rows' => $total_rows,
        'errors' => $errors
    ];
}

// Function to sanitize field names
function sanitize_field_name($field) {
    $field = str_replace(' ', '_', $field);
    $field = rtrim($field, ':;.,');
    $field = preg_replace('/[^a-zA-Z0-9_]/', '_', $field);
    return $field;
}
?> 