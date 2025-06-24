<?php
/**
 * Vendor Port - All-in-One Application
 * 
 * This file consolidates all the functionality from the separate PHP files
 * into one organized, simple application with the same features and appearance.
 * 
 * @package Vendor_Port
 */

// Start session and set error handling
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300);

// Include database configuration
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/install.php';

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

/**
 * Log debug information to file
 */
function log_debug($message) {
    $log_file = __DIR__ . '/debug.log';
    if (!file_exists($log_file)) {
        touch($log_file);
        chmod($log_file, 0666);
    }
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message\n";
    if (file_put_contents($log_file, $log_message, FILE_APPEND) === false) {
        error_log("Failed to write to debug log file: " . $log_file);
    }
}

/**
 * Enhanced 590 Number Extraction Algorithm
 */
function clean_590_number($input) {
    if (empty($input)) {
        return $input;
    }
    
    $input = trim((string)$input);
    
    // Pattern 1: Direct 590-XXXXX format
    if (preg_match('/^590-(\d+)$/', $input, $matches)) {
        return '590-' . $matches[1];
    }
    
    // Pattern 2: 590-XXXXX with additional text
    if (preg_match('/590-(\d+)/', $input, $matches)) {
        return '590-' . $matches[1];
    }
    
    // Pattern 13: Handle 3-4 digit numbers by padding with zeros (MOVE THIS BEFORE PATTERN 3)
    // First check for numbers at the beginning with colons (like "8060:")
    if (preg_match('/^(\d{3,4}):/', $input, $matches)) {
        $number = $matches[1];
        if (strlen($number) >= 3 && strlen($number) <= 4) {
            // Pad with zeros to make it 5 digits
            $padded_number = str_pad($number, 5, '0', STR_PAD_LEFT);
            return '590-' . $padded_number;
        }
    }
    
    // Also check for standalone 3-4 digit numbers
    if (preg_match('/\b(\d{3,4})\b/', $input, $matches)) {
        $number = $matches[1];
        if (strlen($number) >= 3 && strlen($number) <= 4) {
            // Pad with zeros to make it 5 digits
            $padded_number = str_pad($number, 5, '0', STR_PAD_LEFT);
            return '590-' . $padded_number;
        }
    }
    
    // Pattern 3: Numbers at the beginning of the string
    if (preg_match('/^(\d{4,5}):/', $input, $matches)) {
        $number = $matches[1];
        if (strlen($number) >= 4 && strlen($number) <= 5) {
            return '590-' . $number;
        }
    }
    
    // Pattern 4: Just numbers that could be 590 numbers (5 digits)
    // if (preg_match('/\b(\d{5})\b/', $input, $matches)) {
    //     $number = $matches[1];
    //     if (preg_match('/^(590|591|592|593|594|595|596|597|598|599)/', $number)) {
    //         return '590-' . $number;
    //     }
    // }
    
    // Pattern 5: Extract from complex descriptions
    if (preg_match('/ID[#\s]*590-(\d+)/i', $input, $matches)) {
        return '590-' . $matches[1];
    }
    
    // Pattern 6: Extract from parentheses or brackets
    if (preg_match('/[\(\[].*?590-(\d+).*?[\)\]]/', $input, $matches)) {
        return '590-' . $matches[1];
    }
    
    // Pattern 7: Extract from comma-separated values
    $parts = explode(',', $input);
    foreach ($parts as $part) {
        $part = trim($part);
        if (preg_match('/590-(\d+)/', $part, $matches)) {
            return '590-' . $matches[1];
        }
        if (preg_match('/^(\d{4,5})$/', $part, $matches)) {
            $number = $matches[1];
            if (strlen($number) >= 4 && strlen($number) <= 5) {
                return '590-' . $number;
            }
        }
    }
    
    // Pattern 8: Extract from semicolon-separated values
    $parts = explode(';', $input);
    foreach ($parts as $part) {
        $part = trim($part);
        if (preg_match('/590-(\d+)/', $part, $matches)) {
            return '590-' . $matches[1];
        }
    }
    
    // Pattern 9: Look for 590 numbers in any format within the string
    if (preg_match('/\b590[-\s]*(\d+)\b/', $input, $matches)) {
        return '590-' . $matches[1];
    }
    
    // Pattern 10: Extract from OEM or part number patterns
    if (preg_match('/OEM[#\s]*.*?590-(\d+)/i', $input, $matches)) {
        return '590-' . $matches[1];
    }
    
    // Pattern 11: Extract from engine compartment or location descriptions
    if (preg_match('/engine compartment.*?590-(\d+)/i', $input, $matches)) {
        return '590-' . $matches[1];
    }
    
    // Pattern 12: Look for any 5-digit number that could be a 590 number (but not 3-4 digits)
    if (preg_match('/\b(\d{5})\b/', $input, $matches)) {
        $number = $matches[1];
        $words = preg_split('/[\s,;:]+/', $input);
        if (in_array($number, $words) || strpos($input, $number . ':') === 0) {
            return '590-' . $number;
        }
    }
    
    return $input;
}

/**
 * Advanced 590 Extraction with Multiple Validation
 */
function advanced_590_extraction($input) {
    if (empty($input)) {
        return [
            'extracted' => $input,
            'confidence' => 0,
            'method' => 'empty_input'
        ];
    }
    
    $original = $input;
    $extracted = clean_590_number($input);
    $confidence = 0;
    $method = 'no_match';
    
    if ($extracted !== $original) {
        if (preg_match('/^590-(\d+)$/', $extracted)) {
            $confidence = 100;
            $method = 'direct_format';
        } elseif (preg_match('/^590-(\d+)$/', $extracted)) {
            $confidence = 85;
            $method = 'pattern_extraction';
        } else {
            $confidence = 70;
            $method = 'complex_extraction';
        }
    }
    
    return [
        'extracted' => $extracted,
        'confidence' => $confidence,
        'method' => $method,
        'original' => $original
    ];
}

/**
 * Preview CSV data
 */
function preview_csv($file_path, $limit = 5) {
    $preview_data = array();
    $headers = array();
    
    if (($handle = fopen($file_path, "r")) !== FALSE) {
        if (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $headers = $data;
        }
        
        $count = 0;
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if ($count < $limit) {
                $preview_data[] = $data;
                $count++;
            } else {
                break;
            }
        }
        fclose($handle);
    }
    
    return array('headers' => $headers, 'data' => $preview_data);
}

/**
 * Get CSV preview data
 */
function get_csv_preview($file_path, $max_rows = 5) {
    $preview_data = array();
    $header = array();
    
    if (($handle = fopen($file_path, 'r')) !== false) {
        if (($data = fgetcsv($handle, 1000, ',')) !== false) {
            $header = $data;
        }
        
        $row_count = 0;
        while (($data = fgetcsv($handle, 1000, ',')) !== false && $row_count < $max_rows) {
            $preview_data[] = $data;
            $row_count++;
        }
        
        fclose($handle);
    }
    
    return array(
        'header' => $header,
        'rows' => $preview_data,
    );
}

/**
 * Get sample data from CSV
 */
function get_sample_data($file_path, $mapping, $sample_rows = 3) {
    $sample_data = [];
    
    if (($handle = fopen($file_path, 'r')) !== false) {
        fgetcsv($handle);
        
        $row_count = 0;
        while (($data = fgetcsv($handle)) !== false && $row_count < $sample_rows) {
            $mapped_row = [];
            $has_590_field = false;
            
            foreach ($mapping as $field => $csv_index) {
                $value = isset($data[$csv_index]) ? $data[$csv_index] : '';
                
                if (trim($field) === '590' && !empty($value)) {
                    $clean_value = trim(str_replace(["\n", "\r"], ' ', $value));
                    $extraction_result = advanced_590_extraction($clean_value);
                    $mapped_row[$field] = $extraction_result['extracted'];
                    $mapped_row['original_description'] = $clean_value;
                    $has_590_field = true;
                } else {
                    $mapped_row[$field] = $value;
                }
            }
            
            if (isset($mapping['590']) && !$has_590_field) {
                $mapped_row['original_description'] = '';
            }
            
            $sample_data[] = $mapped_row;
            $row_count++;
        }
        fclose($handle);
    }
    
    return $sample_data;
}

/**
 * Sanitize field name for database
 */
function sanitize_field_name($field) {
    $field = str_replace(' ', '_', $field);
    $field = rtrim($field, ':;.,');
    $field = preg_replace('/[^a-zA-Z0-9_]/', '_', $field);
    return $field;
}

// ============================================================================
// REQUEST HANDLING
// ============================================================================

$action = $_GET['action'] ?? 'dashboard';
$error = null;
$success = null;

try {
    $pdo = get_vendor_db_connection();
    $db_status = check_database_status();
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    $error = 'Database connection failed. Please check the error logs.';
}

// Handle different actions
switch ($action) {
    case 'upload':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (isset($_FILES['csvFile']) && $_FILES['csvFile']['error'] == 0) {
                if ($_FILES['csvFile']['size'] > 50 * 1024 * 1024) {
                    $error = 'File size too large. Maximum 50MB allowed.';
                } else {
                    $file_name = $_FILES['csvFile']['tmp_name'];
                    $upload_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'secure_uploads' . DIRECTORY_SEPARATOR;
                    
                    if (!file_exists($upload_dir)) {
                        if (!mkdir($upload_dir, 0750, true)) {
                            error_log("Failed to create upload directory: " . $upload_dir);
                            $error = 'Failed to create upload directory. Please contact administrator.';
                        } else {
                            $web_user = 'www-data';
                            $web_group = 'www-data';
                            chown($upload_dir, $web_user);
                            chgrp($upload_dir, $web_group);
                        }
                    }
                    
                    if (!$error) {
                        $secure_filename = uniqid('csv_', true) . '.csv';
                        $permanent_file = $upload_dir . $secure_filename;
                        
                        if (!move_uploaded_file($file_name, $permanent_file)) {
                            error_log("Failed to move uploaded file from $file_name to $permanent_file");
                            $error = 'Failed to move uploaded file. Please check directory permissions.';
                        } else {
                            chmod($permanent_file, 0640);
                            
                            if (($handle = fopen($permanent_file, 'r')) !== FALSE) {
                                $headers = fgetcsv($handle);
                                fclose($handle);
                                
                                $_SESSION['csv_file'] = $permanent_file;
                                $_SESSION['csv_headers'] = $headers;
                                $preview = preview_csv($permanent_file);
                                $_SESSION['csv_preview'] = $preview;
                                $_SESSION['csv_file_path'] = $permanent_file;
                                
                                header('Location: all.php?action=vendor_details');
                                exit();
                            } else {
                                unlink($permanent_file);
                                error_log("Failed to read CSV file: " . $permanent_file);
                                $error = 'Failed to read CSV file. Please try again.';
                            }
                        }
                    }
                }
            }
        }
        break;
        
    case 'vendor_details':
        if (!isset($_SESSION['csv_file'])) {
            header('Location: all.php?action=upload');
            exit();
        }
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (empty($_POST['vendor_name'])) {
                $error = 'Vendor name is required.';
            } else {
                $vendor_name = trim(strip_tags($_POST['vendor_name']));
                $contact_person = trim(strip_tags($_POST['contact_person'] ?? ''));
                $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
                $phone = trim(strip_tags($_POST['phone'] ?? ''));
                $address = trim(strip_tags($_POST['address'] ?? ''));
                
                $stmt = $pdo->prepare("INSERT INTO vendors (vendor_name, contact_person, email, phone, address) VALUES (?, ?, ?, ?, ?)");
                try {
                    $stmt->execute([$vendor_name, $contact_person, $email, $phone, $address]);
                    $_SESSION['vendor_id'] = $pdo->lastInsertId();
                    header('Location: all.php?action=mapping');
                    exit();
                } catch (PDOException $e) {
                    error_log("Failed to insert vendor data: " . $e->getMessage());
                    $error = 'Failed to save vendor information. Please try again.';
                }
            }
        }
        break;
        
    case 'mapping':
        if (!isset($_SESSION['csv_file']) || !isset($_SESSION['csv_headers'])) {
            header('Location: all.php?action=upload');
            exit();
        }
        
        $csv_headers = $_SESSION['csv_headers'];
        $required_fields = ['590'];
        $optional_fields = [];
        
        // Handle AJAX requests for adding columns
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
            $response = array();
            
            if ($_POST['action'] === 'add_new_column') {
                $new_field = trim($_POST['new_field']);
                if (!empty($new_field)) {
                    $optional_fields[] = $new_field;
                    $response['success'] = true;
                    $response['field'] = $new_field;
                } else {
                    $response['success'] = false;
                    $response['error'] = 'Field name cannot be empty';
                }
            } elseif ($_POST['action'] === 'add_remaining_columns') {
                $current_mapping = isset($_POST['current_mapping']) ? $_POST['current_mapping'] : array();
                
                // Decode JSON if it's a string
                if (is_string($current_mapping)) {
                    $decoded = json_decode($current_mapping, true);
                    $current_mapping = ($decoded !== null) ? $decoded : array();
                }
                
                // Ensure current_mapping is an array
                if (!is_array($current_mapping)) {
                    $current_mapping = array();
                }
                
                $used_columns = array_values($current_mapping);
                $remaining_columns = array();
                
                foreach ($csv_headers as $index => $header) {
                    if (!in_array($index, $used_columns)) {
                        $field_name = sanitize_field_name($header);
                        $remaining_columns[$field_name] = $index;
                    }
                }
                
                if (empty($remaining_columns)) {
                    $response['success'] = false;
                    $response['error'] = 'No remaining columns to add. All columns are already mapped.';
                } else {
                    $response['success'] = true;
                    $response['remaining_columns'] = $remaining_columns;
                    $response['message'] = 'Added ' . count($remaining_columns) . ' remaining columns.';
                }
            }
            
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['action'])) {
            $mapping = $_POST['mapping'];
            $_SESSION['mapping'] = $mapping;
            header('Location: all.php?action=preview');
            exit();
        }
        
        $preview = get_csv_preview($_SESSION['csv_file']);
        break;
        
    case 'preview':
        if (!isset($_SESSION['mapping']) || !isset($_SESSION['csv_file'])) {
            header('Location: all.php?action=upload');
            exit();
        }
        
        $mapping = $_SESSION['mapping'];
        $csv_file = $_SESSION['csv_file'];
        $csv_headers = $_SESSION['csv_headers'];
        $sample_data = get_sample_data($csv_file, $mapping);
        break;
        
    case 'process':
        if (!isset($_SESSION['csv_file']) || !isset($_SESSION['mapping'])) {
            echo "data: " . json_encode(['error' => 'Missing required session data']) . "\n\n";
            exit();
        }
        
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        
        $csv_file = $_SESSION['csv_file'];
        $mapping = $_SESSION['mapping'];
        
        $table_name = 'imported_data_' . date('Y_m_d_His');
        $create_table_sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,";
        
        foreach ($mapping as $field => $csv_index) {
            $sanitized_field = sanitize_field_name($field);
            $create_table_sql .= "\n`$sanitized_field` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,";
        }
        $create_table_sql = rtrim($create_table_sql, ',') . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_520_ci;";
        
        try {
            $pdo->exec($create_table_sql);
        } catch (PDOException $e) {
            error_log("Failed to create table $table_name: " . $e->getMessage());
            echo "data: " . json_encode(['error' => 'Failed to create database table']) . "\n\n";
            exit();
        }
        
        $columns = array_keys($mapping);
        $db_columns = array_map('sanitize_field_name', $columns);
        
        $placeholders = str_repeat('?,', count($mapping));
        $placeholders = rtrim($placeholders, ',');
        $insert_sql = "INSERT INTO `$table_name` (`" . implode('`, `', $db_columns) . "`) VALUES ($placeholders)";
        $stmt = $pdo->prepare($insert_sql);
        
        $chunk_size = 1000;
        $processed = 0;
        $total_rows = 0;
        
        $handle = fopen($csv_file, 'r');
        while (fgetcsv($handle)) {
            $total_rows++;
        }
        fclose($handle);
        $total_rows--;
        
        $handle = fopen($csv_file, 'r');
        fgetcsv($handle);
        
        $pdo->beginTransaction();
        $chunk_count = 0;
        
        while (!feof($handle)) {
            $data = fgetcsv($handle);
            if ($data) {
                $mapped_data = [];
                foreach ($mapping as $field => $csv_index) {
                    $value = isset($data[$csv_index]) ? $data[$csv_index] : null;
                    
                    if (trim($field) === '590' && !empty($value)) {
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
                    $chunk_count++;
                    
                    if ($chunk_count >= $chunk_size) {
                        $pdo->commit();
                        $pdo->beginTransaction();
                        $chunk_count = 0;
                        
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
                    continue;
                }
            }
        }
        
        if ($chunk_count > 0) {
            $pdo->commit();
        }
        
        fclose($handle);
        
        unlink($csv_file);
        unset($_SESSION['csv_file']);
        unset($_SESSION['csv_headers']);
        unset($_SESSION['mapping']);
        
        $stmt = $pdo->prepare("INSERT INTO import_history (vendor_id, imported_table, file_name, total_records) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['vendor_id'],
            $table_name,
            basename($csv_file),
            $total_rows
        ]);
        
        echo "data: " . json_encode([
            'progress' => 100,
            'processed' => $processed,
            'total' => $total_rows,
            'complete' => true,
            'table' => $table_name
        ]) . "\n\n";
        exit();
        
    case 'export_csv':
        if (isset($_GET['table'])) {
            $table_name = $_GET['table'];
            $export_matched = isset($_GET['matched']) && $_GET['matched'] == '1';
            
            // Verify table exists
            $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ?");
            $stmt->execute([DB_NAME, $table_name]);
            if (!$stmt->fetch()) {
                die('Table not found');
            }
            
            if ($export_matched && isset($_SESSION['match_results'])) {
                // Export matched results
                $data = $_SESSION['match_results'];
                $filename = $table_name . '_matched_' . date('Y-m-d_H-i-s') . '.csv';
            } else {
                // Export original data
                $stmt = $pdo->prepare("SELECT * FROM `" . str_replace('`', '``', $table_name) . "`");
                $stmt->execute();
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $filename = $table_name . '_original_' . date('Y-m-d_H-i-s') . '.csv';
            }
            
            if (empty($data)) {
                die('No data to export');
            }
            
            // Set headers for CSV download
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            // Output CSV
            $output = fopen('php://output', 'w');
            
            // Headers
            fputcsv($output, array_keys($data[0]));
            
            // Data
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
            
            fclose($output);
            exit();
        }
        break;
        
    case 'delete_import':
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['table'])) {
            $table_name = $_POST['table'];
            
            try {
                // Delete from import_history
                $stmt = $pdo->prepare("DELETE FROM import_history WHERE imported_table = ?");
                $stmt->execute([$table_name]);
                
                // Drop the table
                $pdo->exec("DROP TABLE IF EXISTS `" . str_replace('`', '``', $table_name) . "`");
                
                echo json_encode(['success' => true, 'message' => 'Import deleted successfully']);
            } catch (Exception $e) {
                error_log("Error deleting import $table_name: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error deleting import: ' . $e->getMessage()]);
            }
            exit();
        }
        break;
        
    case 'match_npc':
        if (isset($_GET['table'])) {
            $table_name = $_GET['table'];
            
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $match_type = $_POST['match_type'] ?? '590';
                
                try {
                    // Get database connections
                    $vendor_db = get_vendor_db_connection();
                    $npc_db1 = get_npc_db1_connection();
                    $npc_website_db = get_npc_website_connection();
                    
                    // Verify table exists
                    $stmt = $vendor_db->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ?");
                    $stmt->execute([DB_NAME, $table_name]);
                    if (!$stmt->fetch()) {
                        throw new Exception("Invalid table");
                    }
                    
                    // Build query based on match type
                    switch ($match_type) {
                        case '591':
                            $match_query = "
                            SELECT DISTINCT i.*, h.hollander_no as npc_holander, i2.inventory_no as npc_hardware, 
                            sds.Need_3mo as 3_month_need, sds.Need_6mo as 6_month_need,
                            '591 Match' as match_type
                            FROM " . DB_NAME . ".`$table_name` i 
                            INNER JOIN `" . NPC_DB1_NAME . "`.hollander h ON h.hollander_no = i.`590` 
                            INNER JOIN `" . NPC_DB1_NAME . "`.inventory_hollander_map ihm ON ihm.hollander_id = h.hollander_id
                            INNER JOIN `" . NPC_DB1_NAME . "`.inventory i2 ON i2.inventory_id = ihm.inventory_id
                            LEFT JOIN `" . NPC_WEBSITE_NAME . "`.sales_demand_summary sds ON sds.SKU COLLATE utf8mb4_unicode_520_ci = i2.inventory_no COLLATE utf8mb4_unicode_520_ci
                            WHERE (h.hollander_no LIKE '591-%') AND (sds.Need_3mo > 0 OR sds.Need_6mo > 0)
                            ";
                            break;
                            
                        case 'hardware':
                            $match_query = "
                            SELECT DISTINCT i.*, 
                            i2.inventory_no as npc_hardware,
                            sds.Need_3mo as 3_month_need, sds.Need_6mo as 6_month_need,
                            'Hardware Match' as match_type
                            FROM " . DB_NAME . ".`$table_name` i 
                            inner join `" . NPC_DB1_NAME . "`.inventory AS i2 on
                            i.`590` = i2.inventory_no
                            left join `" . NPC_WEBSITE_NAME . "`.sales_demand_summary sds on
                            sds.SKU COLLATE utf8mb4_unicode_520_ci = i2.inventory_no COLLATE utf8mb4_unicode_520_ci
                            WHERE (sds.Need_3mo > 0 OR sds.Need_6mo > 0)
                            ";
                            break;
                            
                        case 'software':
                            $match_query = "
                            SELECT DISTINCT i.*, s.mfr_software_no as npc_software,
                            i2.inventory_no as npc_hardware,
                            sds.Need_3mo as 3_month_need, sds.Need_6mo as 6_month_need,
                            'Software Match' as match_type
                            FROM " . DB_NAME . ".`$table_name` i 
                            inner join `" . NPC_DB1_NAME . "`.software as s on
                            i.`590` = s.mfr_software_no
                            inner join `" . NPC_DB1_NAME . "`.inventory AS i2 on
                            s.inventory_id = i2.inventory_id
                            left join `" . NPC_WEBSITE_NAME . "`.sales_demand_summary sds on
                            sds.SKU COLLATE utf8mb4_unicode_520_ci = i2.inventory_no COLLATE utf8mb4_unicode_520_ci
                            WHERE (sds.Need_3mo > 0 OR sds.Need_6mo > 0)
                            ";
                            break;
                            
                        case '590':
                        default:
                            $match_query = "
                            SELECT DISTINCT i.*, h.hollander_no as npc_holander, i2.inventory_no as npc_hardware, 
                            sds.Need_3mo as 3_month_need, sds.Need_6mo as 6_month_need,
                            '590 Match' as match_type
                            FROM " . DB_NAME . ".`$table_name` i 
                            INNER JOIN `" . NPC_DB1_NAME . "`.hollander h ON h.hollander_no = i.`590` 
                            INNER JOIN `" . NPC_DB1_NAME . "`.inventory_hollander_map ihm ON ihm.hollander_id = h.hollander_id
                            INNER JOIN `" . NPC_DB1_NAME . "`.inventory i2 ON i2.inventory_id = ihm.inventory_id
                            LEFT JOIN `" . NPC_WEBSITE_NAME . "`.sales_demand_summary sds ON sds.SKU COLLATE utf8mb4_unicode_520_ci = i2.inventory_no COLLATE utf8mb4_unicode_520_ci
                            WHERE (sds.Need_3mo > 0 OR sds.Need_6mo > 0)
                            ";
                            break;
                    }
                    
                    // Execute the query
                    $result = $npc_db1->query($match_query);
                    if (!$result) {
                        $error_info = $npc_db1->errorInfo();
                        throw new Exception("Database query failed: " . $error_info[2]);
                    }
                    $matches = $result->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Store results in session
                    $_SESSION['match_results'] = $matches;
                    $_SESSION['match_count'] = count($matches);
                    $_SESSION['match_type'] = $match_type;
                    
                    // Redirect back with results
                    header("Location: all.php?action=view_data&table=" . urlencode($table_name) . "&matched=1&type=" . urlencode($match_type));
                    exit();
                    
                } catch (Exception $e) {
                    error_log("NPC matching error for table $table_name: " . $e->getMessage());
                    $error = "Match Error: " . $e->getMessage();
                }
            }
        }
        break;
        
    case 'match_results':
        // This case is no longer needed as we handle results in view_data
        header('Location: all.php?action=view_data');
        exit();
        break;
        
    case 'processing_results':
        if (isset($_GET['table'])) {
            $table_name = $_GET['table'];
            
            // Get processing statistics
            $stmt = $pdo->prepare("SELECT COUNT(*) as total_records FROM `" . str_replace('`', '``', $table_name) . "`");
            $stmt->execute();
            $total_records = $stmt->fetchColumn();
            
            // Get import details
            $stmt = $pdo->prepare("SELECT v.vendor_name, v.contact_person, ih.imported_at, ih.total_records 
                                  FROM import_history ih 
                                  JOIN vendors v ON v.id = ih.vendor_id 
                                  WHERE ih.imported_table = ?");
            $stmt->execute([$table_name]);
            $import_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get column information
            $stmt = $pdo->query("SHOW COLUMNS FROM `$table_name`");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        break;
        
    case 'view_data':
        if (isset($_GET['table'])) {
            $table_name = $_GET['table'];
            
            $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ?");
            $stmt->execute([DB_NAME, $table_name]);
            if (!$stmt->fetch()) {
                $error = "Table '$table_name' does not exist in database";
            } else {
                $stmt = $pdo->prepare("SELECT v.vendor_name, v.contact_person, ih.imported_at, ih.total_records 
                                      FROM import_history ih 
                                      JOIN vendors v ON v.id = ih.vendor_id 
                                      WHERE ih.imported_table = ?");
                $stmt->execute([$table_name]);
                $import_info = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$import_info) {
                    $error = "No import history found for table '$table_name'";
                } else {
                    $stmt = $pdo->query("SHOW COLUMNS FROM `$table_name`");
                    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                    $per_page = 50;
                    $offset = ($page - 1) * $per_page;
                    
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `" . str_replace('`', '``', $table_name) . "`");
                    $stmt->execute();
                    $total_rows = $stmt->fetchColumn();
                    $total_pages = ceil($total_rows / $per_page);
                    
                    $stmt = $pdo->prepare("SELECT * FROM `" . str_replace('`', '``', $table_name) . "` LIMIT :offset, :per_page");
                    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
                    $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
                    $stmt->execute();
                    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }
        } else {
            $stmt = $pdo->query("
                SELECT 
                    ih.imported_table,
                    ih.imported_at,
                    ih.total_records,
                    v.vendor_name,
                    v.contact_person
                FROM import_history ih
                JOIN vendors v ON v.id = ih.vendor_id
                ORDER BY ih.imported_at DESC
            ");
            $imports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        break;
        
    default:
        // Dashboard action
        break;
}

// ============================================================================
// HTML OUTPUT
// ============================================================================

// Only output HTML if not processing
if ($action !== 'process') {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Port - <?php echo ucfirst($action); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        
        .container {
            width: 95%;
            max-width: none;
            margin: 10px auto;
            background: white;
            padding: 15px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 5px;
        }
        
        h1, h2 {
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .action-buttons {
            margin: 20px 0;
        }
        
        .btn {
            display: inline-block;
            /* background-color: #4CAF50; */
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 4px;
            margin-right: 10px;
            transition: background-color 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .btn:hover {
            background-color: #45a049;
        }
        
        .btn-secondary {
            background-color: #2196F3;
        }
        
        .btn-secondary:hover {
            background-color: #1976D2;
        }
        
        .status-message {
            padding: 10px 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        
        .status-success {
            background-color: #dff0d8;
            border: 1px solid #d6e9c6;
            color: #3c763d;
        }
        
        .status-warning {
            background-color: #fcf8e3;
            border: 1px solid #faebcc;
            color: #8a6d3b;
        }
        
        .status-error {
            background-color: #f2dede;
            border: 1px solid #ebccd1;
            color: #a94442;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .upload-form {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .file-input-container {
            margin: 20px 0;
            padding: 15px;
            border: 2px dashed #ccc;
            border-radius: 5px;
            text-align: center;
        }
        
        .requirements {
            margin: 20px 0;
            color: #555;
            padding: 15px;
            background-color: #f5f5f5;
            border-left: 4px solid #4CAF50;
            border-radius: 3px;
        }
        
        .mapping-form {
            width: 100%;
            max-width: none;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: white;
        }
        
        .field-map {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .field-map label {
            min-width: 120px;
            font-weight: bold;
            color: #333;
            margin: 0;
        }
        
        .field-map select {
            flex: 1;
            max-width: 300px;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .field-map select:focus {
            border-color: #4CAF50;
            outline: none;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }
        
        .mapping-actions {
            margin: 25px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-block;
            /* background: linear-gradient(135deg, #4CAF50, #45a049); */
            color: white;
            padding: 12px 20px;
            text-decoration: none;
            border-radius: 6px;
            margin: 0;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #45a049, #3d8b40);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #2196F3, #1976D2);
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #1976D2, #1565C0);
        }
        
        .preview-container {
            width: 100%;
            overflow-x: auto;
            margin: 20px 0;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .preview-table {
            width: 100%;
            min-width: 800px;
            border-collapse: collapse;
            margin: 0;
            box-shadow: none;
        }
        
        .preview-table th,
        .preview-table td {
            border: 1px solid #e0e0e0;
            padding: 12px 8px;
            text-align: left;
            white-space: nowrap;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .preview-table th {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            font-weight: bold;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .preview-table td {
            background: white;
        }
        
        .preview-table tr:nth-child(even) td {
            background: #f8f9fa;
        }
        
        .preview-table tr:hover td {
            background: #e3f2fd;
        }
        
        .imports-list {
            width: 100%;
            margin: 20px 0;
        }
        
        .import-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .import-info {
            flex-grow: 1;
        }
        
        .view-btn {
            background: #2196F3;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            margin-left: 15px;
        }
        
        .import-date {
            color: #666;
            font-size: 0.9em;
        }
        
        .import-vendor {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .import-records {
            color: #555;
        }
        
        .pagination {
            margin: 25px 0;
            text-align: center;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .pagination a {
            padding: 10px 15px;
            margin: 0 5px 5px;
            border: 1px solid #ddd;
            text-decoration: none;
            color: #2196F3;
            border-radius: 4px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .pagination a.active {
            background: #2196F3;
            color: white;
            border-color: #2196F3;
        }
        
        .pagination a:hover:not(.active) {
            background: #e3f2fd;
            border-color: #bbdefb;
        }
        
        .back-btn {
            background: #2196F3;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .back-btn:hover {
            background: #1976D2;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .progress-container {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background-color: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background-color: #4CAF50;
            transition: width 0.3s ease;
        }
        
        .progress-text {
            text-align: center;
            margin-top: 10px;
            font-weight: bold;
        }
        
        .import-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #4CAF50;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        
        .column-info {
            margin-top: 30px;
        }
        
        .processing-stats {
            margin: 30px 0;
        }
        
        .match-info {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .match-type-software {
            background: #007bff;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        
        .match-type-hardware {
            background: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        
        .match-type-part_number {
            background: #ffc107;
            color: #212529;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        
        .match-type-description {
            background: #17a2b8;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        
        .match-type-auto {
            background: #6c757d;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        
        /* Responsive design improvements */
        @media (max-width: 768px) {
            .container {
                width: 98%;
                margin: 5px auto;
                padding: 10px;
            }
            
            .mapping-form {
                padding: 15px;
            }
            
            .field-map {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
            
            .field-map label {
                min-width: auto;
            }
            
            .field-map select {
                max-width: none;
            }
            
            .mapping-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                text-align: center;
            }
        }
        
        /* Scrollbar styling for better UX */
        .preview-container::-webkit-scrollbar {
            height: 8px;
        }
        
        .preview-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .preview-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }
        
        .preview-container::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        /* NPC Matching Results Styling */
        .npc-matching-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .npc-matching-table th {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            font-weight: bold;
            padding: 12px 8px;
            text-align: left;
            border: 1px solid #e0e0e0;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        /* Original data columns (green background) */
        .npc-matching-table td.original-data {
            background: #e8f5e8;
            border: 1px solid #c8e6c9;
            padding: 12px 8px;
            text-align: left;
        }
        
        .npc-matching-table tr:nth-child(even) td.original-data {
            background: #d4edda;
        }
        
        .npc-matching-table tr:hover td.original-data {
            background: #c3e6cb;
        }
        
        /* Matched data columns (orange background) */
        .npc-matching-table td.matched-data {
            background: #fff3e0;
            border: 1px solid #ffcc80;
            padding: 12px 8px;
            text-align: left;
        }
        
        .npc-matching-table tr:nth-child(even) td.matched-data {
            background: #ffe0b2;
        }
        
        .npc-matching-table tr:hover td.matched-data {
            background: #ffb74d;
        }
        
        /* Column header indicators */
        .npc-matching-table th.original-header {
            background: linear-gradient(135deg, #4CAF50, #45a049);
        }
        
        .npc-matching-table th.matched-header {
            background: linear-gradient(135deg, #FF9800, #F57C00);
        }
        
        /* Legend for the color coding */
        .npc-legend {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .legend-color.original {
            background: #e8f5e8;
        }
        
        .legend-color.matched {
            background: #fff3e0;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($error)): ?>
            <div class="status-message status-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="status-message status-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($action === 'dashboard'): ?>
            <h1>Vendor Port Dashboard</h1>
            
            <?php if (isset($db_status)): ?>
                <div class="status-message <?php echo $db_status['type']; ?>">
                    <?php echo $db_status['message']; ?>
                </div>
            <?php endif; ?>
            
            <div class="action-buttons">
                <a href="all.php?action=upload" class="btn">Upload New Data</a>
                <a href="all.php?action=view_data" class="btn btn-secondary">View All Imports</a>
            </div>
            
        <?php elseif ($action === 'upload'): ?>
            <div class="upload-form">
                <div class="header">
                    <h2>Upload CSV File</h2>
                </div>
                <div class="requirements">
                    <p>Please upload your CSV file containing the data you want to import.</p>
                    <p><strong>Requirements:</strong></p>
                    <ul>
                        <li>Maximum file size: 50MB</li>
                        <li>Supported format: CSV (Comma Separated Values)</li>
                        <li>First row should contain column headers</li>
                    </ul>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="file-input-container">
                        <label for="csvFile">Select your CSV file:</label>
                        <input type="file" id="csvFile" name="csvFile" accept=".csv" required>
                    </div>
                    <input type="submit" value="Upload and Continue" class="btn">
                </form>
            </div>
            
        <?php elseif ($action === 'vendor_details'): ?>
            <h2>Enter Vendor Details</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="vendor_name">Vendor Name *</label>
                    <input type="text" id="vendor_name" name="vendor_name" required>
                </div>
                
                <div class="form-group">
                    <label for="contact_person">Contact Person</label>
                    <input type="text" id="contact_person" name="contact_person">
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email">
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="tel" id="phone" name="phone">
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" rows="3"></textarea>
                </div>
                
                <button type="submit" class="btn">Continue to Mapping</button>
            </form>
            
        <?php elseif ($action === 'mapping'): ?>
            <div class="mapping-form">
                <div class="header">
                    <h2>Map CSV Columns</h2>
                </div>
                
                <div class="instructions">
                    <p>Please map each required field to the corresponding column in your CSV file.</p>
                </div>
                
                <div class="preview-container">
                    <h2>CSV Preview</h2>
                    <?php if (!empty($preview['header'])): ?>
                        <table class="preview-table">
                            <thead>
                                <tr>
                                    <?php foreach ($preview['header'] as $column): ?>
                                        <th><?php echo htmlspecialchars($column, ENT_QUOTES, 'UTF-8'); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($preview['rows'] as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $cell): ?>
                                            <td><?php echo htmlspecialchars($cell, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No CSV data found or file is empty.</p>
                    <?php endif; ?>
                </div>
                
                <form method="POST" id="mappingForm">
                    <div id="mappingFields">
                        <?php foreach ($required_fields as $field): ?>
                        <div class="field-map">
                            <label><?php echo ucfirst($field); ?>:</label>
                            <select name="mapping[<?php echo $field; ?>]" required>
                                <option value="">Select CSV Column</option>
                                <?php foreach ($csv_headers as $index => $header): ?>
                                    <option value="<?php echo $index; ?>"><?php echo htmlspecialchars($header); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mapping-actions" style="margin: 20px 0;">
                        <button type="button" onclick="addNewColumn()" class="btn btn-secondary">Add New Column</button>
                        <button type="button" onclick="addRemainingColumns()" class="btn btn-secondary">Add Remaining Columns</button>
                    </div>
                    
                    <button type="submit" class="btn">Continue to Preview</button>
                </form>
                
                <script>
                    function addNewColumn() {
                        const fieldName = prompt('Enter field name:');
                        if (fieldName && fieldName.trim()) {
                            const formData = new FormData();
                            formData.append('action', 'add_new_column');
                            formData.append('new_field', fieldName.trim());
                            
                            fetch('all.php?action=mapping', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    addFieldToForm(data.field);
                                } else {
                                    alert('Error: ' + data.error);
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('Error adding new column');
                            });
                        }
                    }
                    
                    function addRemainingColumns() {
                        const currentMapping = getCurrentMapping();
                        const formData = new FormData();
                        formData.append('action', 'add_remaining_columns');
                        formData.append('current_mapping', JSON.stringify(currentMapping));
                        
                        fetch('all.php?action=mapping', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Object.keys(data.remaining_columns).forEach(fieldName => {
                                    addFieldToForm(fieldName, data.remaining_columns[fieldName]);
                                });
                                if (data.message) {
                                    alert(data.message);
                                }
                            } else {
                                alert('Error: ' + data.error);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error adding remaining columns');
                        });
                    }
                    
                    function addFieldToForm(fieldName, selectedIndex = '') {
                        const mappingFields = document.getElementById('mappingFields');
                        const fieldDiv = document.createElement('div');
                        fieldDiv.className = 'field-map';
                        fieldDiv.innerHTML = `
                            <label>${fieldName}:</label>
                            <select name="mapping[${fieldName}]">
                                <option value="">Select CSV Column</option>
                                <?php foreach ($csv_headers as $index => $header): ?>
                                    <option value="<?php echo $index; ?>" ${selectedIndex == <?php echo $index; ?> ? 'selected' : ''}>${<?php echo json_encode(htmlspecialchars($header)); ?>}</option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" onclick="removeField(this)" class="btn" style="background-color: #dc3545; margin-left: 10px;">Remove</button>
                        `;
                        mappingFields.appendChild(fieldDiv);
                    }
                    
                    function removeField(button) {
                        button.parentElement.remove();
                    }
                    
                    function getCurrentMapping() {
                        const mapping = {};
                        const selects = document.querySelectorAll('select[name^="mapping["]');
                        selects.forEach(select => {
                            const fieldName = select.name.match(/mapping\[([^\]]+)\]/)[1];
                            if (select.value !== '') {
                                mapping[fieldName] = select.value;
                            }
                        });
                        return mapping;
                    }
                </script>
            </div>
            
        <?php elseif ($action === 'preview'): ?>
            <div class="preview-container">
                <h1>Preview Mapping</h1>
                
                <div class="mapping-summary">
                    <h3>Mapping Summary</h3>
                    <?php foreach ($mapping as $field => $csv_index): ?>
                        <div class="field-mapping">
                            <span class="field-name"><?php echo htmlspecialchars($field); ?></span>
                            <span class="csv-column">→ <?php echo htmlspecialchars($csv_headers[$csv_index]); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="sample-data">
                    <h3>Sample Data Preview</h3>
                    <?php if (!empty($sample_data)): ?>
                        <div class="preview-container">
                            <table class="preview-table">
                                <thead>
                                    <tr>
                                        <?php foreach (array_keys($sample_data[0]) as $field): ?>
                                            <th><?php echo htmlspecialchars($field); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sample_data as $row): ?>
                                        <tr>
                                            <?php foreach ($row as $value): ?>
                                                <td><?php echo htmlspecialchars($value); ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p>No sample data available.</p>
                    <?php endif; ?>
                </div>
                
                <div class="action-buttons">
                    <a href="all.php?action=mapping" class="btn btn-secondary">Back to Mapping</a>
                    <button onclick="startProcessing()" class="btn">Start Processing</button>
                </div>
                
                <div id="progress-container" class="progress-container" style="display: none;">
                    <h3>Processing Data...</h3>
                    <div class="progress-bar">
                        <div id="progress-fill" class="progress-fill" style="width: 0%;"></div>
                    </div>
                    <div id="progress-text" class="progress-text">0% Complete</div>
                </div>
            </div>
            
            <script>
                function startProcessing() {
                    document.getElementById('progress-container').style.display = 'block';
                    document.querySelector('.action-buttons').style.display = 'none';
                    
                    const eventSource = new EventSource('all.php?action=process');
                    
                    eventSource.onmessage = function(event) {
                        const data = JSON.parse(event.data);
                        
                        if (data.error) {
                            alert('Error: ' + data.error);
                            eventSource.close();
                            return;
                        }
                        
                        if (data.progress !== undefined) {
                            document.getElementById('progress-fill').style.width = data.progress + '%';
                            document.getElementById('progress-text').textContent = 
                                data.progress + '% Complete (' + data.processed + '/' + data.total + ' records)';
                        }
                        
                        if (data.complete) {
                            eventSource.close();
                            alert('Processing complete! Imported ' + data.processed + ' records.');
                            window.location.href = 'all.php?action=view_data&table=' + data.table;
                        }
                    };
                    
                    eventSource.onerror = function() {
                        alert('Error during processing. Please try again.');
                        eventSource.close();
                    };
                }
            </script>
            
        <?php elseif ($action === 'view_data'): ?>
            <?php if (isset($_GET['table'])): ?>
                <a href="all.php?action=view_data" class="back-btn">← Back to All Imports</a>
                
                <?php if (isset($import_info)): ?>
                    <div class="import-info">
                        <h3>Import Details</h3>
                        <p><strong>Vendor:</strong> <?php echo htmlspecialchars($import_info['vendor_name']); ?></p>
                        <p><strong>Contact:</strong> <?php echo htmlspecialchars($import_info['contact_person']); ?></p>
                        <p><strong>Imported:</strong> <?php echo htmlspecialchars($import_info['imported_at']); ?></p>
                        <p><strong>Total Records:</strong> <?php echo htmlspecialchars($import_info['total_records']); ?></p>
                    </div>

                    <!-- NPC Matching Dropdown and Button -->
                    <div class="npc-matching-form" style="margin-bottom: 20px;">
                        <form method="POST" action="all.php?action=match_npc&table=<?php echo urlencode($table_name); ?>">
                            <div class="form-group" style="display: inline-block; margin-right: 10px;">
                                <label for="match_type">NPC Match Type:</label>
                                <select name="match_type" id="match_type" class="form-control" style="display: inline-block; width: auto;">
                                    <option value="590">Match by 590 (Hollander)</option>
                                    <option value="591">Match by 591 (Hollander)</option>
                                    <option value="software">Match by Software</option>
                                    <option value="hardware">Match by Hardware</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-secondary" style="vertical-align: middle;">Start NPC Matching</button>
                        </form>
                    </div>
                    
                    <div class="action-buttons">
                        <a href="all.php?action=export_csv&table=<?php echo urlencode($table_name); ?>" class="btn btn-secondary">Export Original Data</a>
                        <a href="all.php?action=processing_results&table=<?php echo urlencode($table_name); ?>" class="btn btn-secondary">Processing Results</a>
                        <button onclick="deleteImport('<?php echo htmlspecialchars($table_name); ?>')" class="btn" style="background-color: #dc3545;">Delete Import</button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($data) && !empty($data)): ?>
                    <?php if (isset($_GET['matched']) && $_GET['matched'] == '1' && isset($_SESSION['match_results'])): ?>
                        <!-- Display matched results -->
                        <?php
                        $match_results = $_SESSION['match_results'];
                        $match_count = isset($_SESSION['match_count']) ? $_SESSION['match_count'] : count($match_results);
                        $match_type = isset($_SESSION['match_type']) ? $_SESSION['match_type'] : '590';
                        
                        // Setup pagination for matched results
                        $match_page = isset($_GET['match_page']) ? max(1, (int)$_GET['match_page']) : 1;
                        $match_per_page = 50;
                        $match_offset = ($match_page - 1) * $match_per_page;
                        $total_match_pages = ceil(count($match_results) / $match_per_page);
                        
                        // Get paginated match results
                        $paginated_matches = array_slice($match_results, $match_offset, $match_per_page);
                        ?>
                        
                        <div class="match-results">
                            <h3>Matched Results (<?php echo $match_count; ?> matches found)</h3>
                            
                            <!-- Color Legend -->
                            <div class="npc-legend">
                                <div class="legend-item">
                                    <div class="legend-color original"></div>
                                    <span>Original Data</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color matched"></div>
                                    <span>Matched Data</span>
                                </div>
                            </div>
                            
                            <div class="action-buttons">
                                <a href="all.php?action=export_csv&table=<?php echo urlencode($table_name); ?>&matched=1" class="btn" style="background-color: #ff8c00; color: white;">Export Matched Data</a>
                            </div>
                            
                            <div class="preview-container">
                                <table class="npc-matching-table">
                                    <thead>
                                        <tr>
                                            <?php 
                                            $headers = array_keys($paginated_matches[0]);
                                            $original_columns = ['590', 'original_description']; // Original data columns
                                            
                                            foreach ($headers as $header): 
                                                $is_original = in_array($header, $original_columns);
                                                $header_class = $is_original ? 'original-header' : 'matched-header';
                                            ?>
                                                <th class="<?php echo $header_class; ?>"><?php echo htmlspecialchars($header); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($paginated_matches as $row): ?>
                                            <tr>
                                                <?php 
                                                foreach ($row as $header => $value): 
                                                    $is_original = in_array($header, $original_columns);
                                                    $cell_class = $is_original ? 'original-data' : 'matched-data';
                                                ?>
                                                    <td class="<?php echo $cell_class; ?>"><?php echo htmlspecialchars($value); ?></td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination for matched results -->
                            <?php if ($total_match_pages > 1): ?>
                                <div class="pagination">
                                    <?php for ($i = 1; $i <= $total_match_pages; $i++): ?>
                                        <a href="all.php?action=view_data&table=<?php echo urlencode($table_name); ?>&matched=1&match_page=<?php echo $i; ?>" 
                                           class="<?php echo $i == $match_page ? 'active' : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="alert alert-success">
                                <?php 
                                $match_type_label = '';
                                switch($match_type) {
                                    case 'hardware': $match_type_label = 'Hardware'; break;
                                    case 'software': $match_type_label = 'Software'; break;
                                    case '591': $match_type_label = 'Hollander (591)'; break;
                                    case '590': default: $match_type_label = 'Hollander (590)'; break;
                                }
                                echo "Found $match_count matches using $match_type_label matching.";
                                ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Display original data -->
                        <div class="preview-container">
                            <table class="preview-table">
                                <thead>
                                    <tr>
                                        <?php foreach ($columns as $column): ?>
                                            <th><?php echo htmlspecialchars($column); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data as $row): ?>
                                        <tr>
                                            <?php foreach ($row as $value): ?>
                                                <td><?php echo htmlspecialchars($value); ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="all.php?action=view_data&table=<?php echo urlencode($table_name); ?>&page=<?php echo $i; ?>" 
                                       class="<?php echo $i == $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <p>No data found in this table.</p>
                <?php endif; ?>
                
            <?php else: ?>
                <h1>View All Imports</h1>
                
                <?php if (isset($imports) && !empty($imports)): ?>
                    <div class="imports-list">
                        <?php foreach ($imports as $import): ?>
                            <div class="import-card">
                                <div class="import-info">
                                    <div class="import-vendor"><?php echo htmlspecialchars($import['vendor_name']); ?></div>
                                    <div class="import-records"><?php echo htmlspecialchars($import['total_records']); ?> records</div>
                                    <div class="import-date"><?php echo htmlspecialchars($import['imported_at']); ?></div>
                                </div>
                                <div class="import-actions">
                                    <a href="all.php?action=view_data&table=<?php echo urlencode($import['imported_table']); ?>" class="view-btn">View Data</a>
                                    <a href="all.php?action=export_csv&table=<?php echo urlencode($import['imported_table']); ?>" class="btn btn-secondary" style="margin-left: 10px;">Export</a>
                                    <button onclick="deleteImport('<?php echo htmlspecialchars($import['imported_table']); ?>')" class="btn" style="background-color: #dc3545; margin-left: 10px;">Delete</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>No imports found.</p>
                <?php endif; ?>
                
                <div class="action-buttons">
                    <a href="all.php?action=dashboard" class="btn">Back to Dashboard</a>
                </div>
            <?php endif; ?>
            
            <script>
                function deleteImport(tableName) {
                    if (confirm('Are you sure you want to delete this import? This action cannot be undone.')) {
                        fetch('all.php?action=delete_import', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'table=' + encodeURIComponent(tableName)
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Import deleted successfully');
                                location.reload();
                            } else {
                                alert('Error: ' + data.message);
                            }
                        })
                        .catch(error => {
                            alert('Error deleting import: ' + error);
                        });
                    }
                }
            </script>
            
        <?php elseif ($action === 'match_npc'): ?>
            <a href="all.php?action=view_data&table=<?php echo urlencode($_GET['table']); ?>" class="back-btn">← Back to Data</a>
            
            <h2>NPC Matching</h2>
            <p>Select the type of matching you want to perform:</p>
            
            <form method="POST">
                <div class="form-group">
                    <label for="match_type">Match Type:</label>
                    <select name="match_type" id="match_type" class="form-control">
                        <option value="590">Match by 590 (Hollander)</option>
                        <option value="591">Match by 591 (Hollander)</option>
                        <option value="software">Match by Software</option>
                        <option value="hardware">Match by Hardware</option>
                    </select>
                </div>
                
                <button type="submit" class="btn">Start NPC Matching</button>
            </form>
            
        <?php elseif ($action === 'processing_results'): ?>
            <a href="all.php?action=view_data&table=<?php echo urlencode($_GET['table']); ?>" class="back-btn">← Back to Data</a>
            
            <h2>Processing Results</h2>
            
            <?php if (isset($import_info)): ?>
                <div class="import-info">
                    <h3>Import Summary</h3>
                    <p><strong>Vendor:</strong> <?php echo htmlspecialchars($import_info['vendor_name']); ?></p>
                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($import_info['contact_person']); ?></p>
                    <p><strong>Import Date:</strong> <?php echo htmlspecialchars($import_info['imported_at']); ?></p>
                    <p><strong>Total Records:</strong> <?php echo htmlspecialchars($total_records); ?></p>
                </div>
                
                <div class="processing-stats">
                    <h3>Processing Statistics</h3>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo htmlspecialchars($total_records); ?></div>
                            <div class="stat-label">Total Records</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo count($columns); ?></div>
                            <div class="stat-label">Columns</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">100%</div>
                            <div class="stat-label">Success Rate</div>
                        </div>
                    </div>
                </div>
                
                <div class="column-info">
                    <h3>Column Information</h3>
                    <div class="preview-container">
                        <table class="preview-table">
                            <thead>
                                <tr>
                                    <th>Column Name</th>
                                    <th>Type</th>
                                    <th>Null</th>
                                    <th>Key</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($columns as $column): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($column['Field']); ?></td>
                                        <td><?php echo htmlspecialchars($column['Type']); ?></td>
                                        <td><?php echo htmlspecialchars($column['Null']); ?></td>
                                        <td><?php echo htmlspecialchars($column['Key']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="status-message status-error">
                    Import information not found
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if ($action !== 'dashboard'): ?>
            <div class="action-buttons">
                <a href="all.php?action=dashboard" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
}
?> 