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
    
    // Pattern 1: Direct 590-XXXXX format (preserve as is, including any letters)
    if (preg_match('/^590-(\d+[A-Za-z]*)$/', $input, $matches)) {
        return '590-' . $matches[1];
    }
    
    // Pattern 2: 590-XXXXX with additional text (preserve as is, including any letters)
    if (preg_match('/590-(\d+[A-Za-z]*)/', $input, $matches)) {
        return '590-' . $matches[1];
    }
    
    // Pattern 2.5: 591-XXXXX format (preserve as is, including any letters)
    if (preg_match('/591-(\d+[A-Za-z]*)/', $input, $matches)) {
        return '591-' . $matches[1];
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
    
    // Pattern 9: Look for 590 numbers in any format within the string (preserve letters)
    if (preg_match('/\b590[-\s]*(\d+[A-Za-z]*)\b/', $input, $matches)) {
        return '590-' . $matches[1];
    }
    
    // Pattern 9.5: Look for 591 numbers in any format within the string (preserve letters)
    if (preg_match('/\b591[-\s]*(\d+[A-Za-z]*)\b/', $input, $matches)) {
        return '591-' . $matches[1];
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
    // Only process if the input doesn't already contain a 590/591 pattern
    if (!preg_match('/\b(590|591)-\d+/', $input)) {
        if (preg_match('/\b(\d{5})\b/', $input, $matches)) {
            $number = $matches[1];
            $words = preg_split('/[\s,;:]+/', $input);
            if (in_array($number, $words) || strpos($input, $number . ':') === 0) {
                return '590-' . $number;
            }
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
function get_sample_data($file_path, $mapping, $sample_rows = 3, $bypass_590 = false) {
    $sample_data = [];
    
    // Debug logging
    error_log("get_sample_data called with bypass_590 = " . ($bypass_590 ? 'true' : 'false'));
    error_log("get_sample_data mapping = " . print_r($mapping, true));
    
    if (($handle = fopen($file_path, 'r')) !== false) {
        $header = fgetcsv($handle);
        error_log("CSV header: " . print_r($header, true));
        
        $row_count = 0;
        while (($data = fgetcsv($handle)) !== false && $row_count < $sample_rows) {
            error_log("Processing row $row_count: " . print_r($data, true));
            $mapped_row = [];
            $has_590_field = false;
            
            foreach ($mapping as $field => $csv_index) {
                $value = isset($data[$csv_index]) ? $data[$csv_index] : '';
                
                if (trim($field) === '590' && !empty($value) && !$bypass_590) {
                    $clean_value = trim(str_replace(["\n", "\r"], ' ', $value));
                    $extraction_result = advanced_590_extraction($clean_value);
                    $mapped_row[$field] = $extraction_result['extracted'];
                    $mapped_row['original_description'] = $clean_value;
                    $has_590_field = true;
                } else {
                    $mapped_row[$field] = $value;
                }
            }
            
            if (!$bypass_590 && isset($mapping['590']) && !$has_590_field) {
                $mapped_row['original_description'] = '';
            }
            
            error_log("Mapped row $row_count: " . print_r($mapped_row, true));
            $sample_data[] = $mapped_row;
            $row_count++;
        }
        fclose($handle);
    }
    
    // Debug logging
    error_log("get_sample_data returning " . count($sample_data) . " rows");
    
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

/**
 * Compare price with budget and return CSS class
 */
function get_price_budget_class($price, $budget) {
    if (empty($price) || empty($budget)) {
        return '';
    }
    
    $price = floatval($price);
    $budget = floatval($budget);
    
    if ($price < $budget) {
        return 'price-under-budget';
    } elseif ($price > $budget) {
        return 'price-over-budget';
    } else {
        return 'price-equal-budget';
    }
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
        $optional_fields = ['price'];
        
        // Debug: Log CSV headers
        error_log("CSV Headers: " . print_r($csv_headers, true));
        
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
            // Debug: Log the POST data
            error_log("Mapping POST data: " . print_r($_POST, true));
            
            $mapping = $_POST['mapping'];
            error_log("Raw mapping: " . print_r($mapping, true));
            
            // Filter out empty/unmapped fields
            $filtered_mapping = array();
            foreach ($mapping as $field => $csv_index) {
                if ($csv_index !== '' && $csv_index !== null) {
                    $filtered_mapping[$field] = $csv_index;
                }
            }
            
            error_log("Filtered mapping: " . print_r($filtered_mapping, true));
            
            $_SESSION['mapping'] = $filtered_mapping;
            $_SESSION['bypass_590'] = isset($_POST['bypass_590']) ? true : false;
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
        $bypass_590 = isset($_SESSION['bypass_590']) ? $_SESSION['bypass_590'] : false;
        $sample_data = get_sample_data($csv_file, $mapping, 3, $bypass_590);
        
        // Debug information
        error_log("Preview - bypass_590: " . ($bypass_590 ? 'true' : 'false'));
        error_log("Preview - mapping: " . print_r($mapping, true));
        error_log("Preview - sample_data count: " . count($sample_data));
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
                    
                    if (trim($field) === '590' && !empty($value) && !$_SESSION['bypass_590']) {
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
                            COALESCE(sds.Purchase_Price, (SELECT sds2.Purchase_Price FROM `" . NPC_WEBSITE_NAME . "`.sales_demand_summary sds2 WHERE sds2.SKU COLLATE utf8mb4_unicode_520_ci = i2.inventory_no COLLATE utf8mb4_unicode_520_ci AND sds2.Purchase_Price IS NOT NULL LIMIT 1)) as budget,
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
                            COALESCE(sds.Purchase_Price, (SELECT sds2.Purchase_Price FROM `" . NPC_WEBSITE_NAME . "`.sales_demand_summary sds2 WHERE sds2.SKU COLLATE utf8mb4_unicode_520_ci = i2.inventory_no COLLATE utf8mb4_unicode_520_ci AND sds2.Purchase_Price IS NOT NULL LIMIT 1)) as budget,
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
                            COALESCE(sds.Purchase_Price, (SELECT sds2.Purchase_Price FROM `" . NPC_WEBSITE_NAME . "`.sales_demand_summary sds2 WHERE sds2.SKU COLLATE utf8mb4_unicode_520_ci = i2.inventory_no COLLATE utf8mb4_unicode_520_ci AND sds2.Purchase_Price IS NOT NULL LIMIT 1)) as budget,
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
                            COALESCE(sds.Purchase_Price, (SELECT sds2.Purchase_Price FROM `" . NPC_WEBSITE_NAME . "`.sales_demand_summary sds2 WHERE sds2.SKU COLLATE utf8mb4_unicode_520_ci = i2.inventory_no COLLATE utf8mb4_unicode_520_ci AND sds2.Purchase_Price IS NOT NULL LIMIT 1)) as budget,
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
                    
                    // Store the query for debugging/display
                    $_SESSION['last_match_query'] = $match_query;
                    
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
            
        case 'compare_data':
            // This case is handled in the HTML output section
            break;
            
        case 'export_compare':
            if (isset($_SESSION['compare_results']) && !empty($_SESSION['compare_results'])) {
                $data = $_SESSION['compare_results'];
                $tables = isset($_SESSION['compare_tables']) ? $_SESSION['compare_tables'] : [];
                $filename = 'compare_results_' . implode('_vs_', array_slice($tables, 0, 2)) . '_' . date('Y-m-d_H-i-s') . '.csv';
                
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
            } else {
                header('Location: all.php?action=compare_data');
                exit();
            }
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
        
    case 'sku_inventory_report':
        // Connect to NPC Website Database and fetch sales_demand_summary data
        try {
            // Get NPC Website Database connection
            $npc_website_db = get_npc_website_connection();
            
            if ($npc_website_db) {
                // Fetch data from sales_demand_summary table with pagination
                $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $per_page = 50;
                $offset = ($page - 1) * $per_page;
                
                // Get total count
                $stmt = $npc_website_db->prepare("SELECT COUNT(*) FROM sales_demand_summary");
                $stmt->execute();
                $total_records = $stmt->fetchColumn();
                $total_pages = ceil($total_records / $per_page);
                
                // Get data with pagination
                $stmt = $npc_website_db->prepare("SELECT * FROM sales_demand_summary LIMIT :offset, :per_page");
                $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
                $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
                $stmt->execute();
                $sku_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get column names
                $stmt = $npc_website_db->query("SHOW COLUMNS FROM sales_demand_summary");
                $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Set database and table info for display
                $external_dbname = NPC_WEBSITE_NAME;
                $external_table = 'sales_demand_summary';
            } else {
                $error = 'NPC Website Database connection not available';
            }
            
        } catch (PDOException $e) {
            $error = 'Failed to connect to NPC Website Database: ' . $e->getMessage();
        }
        break;
        
    case 'export_sku_report':
        // Export all sales_demand_summary data to CSV
        try {
            $npc_website_db = get_npc_website_connection();
            
            if ($npc_website_db) {
                // Get all data from sales_demand_summary table
                $stmt = $npc_website_db->prepare("SELECT * FROM sales_demand_summary");
                $stmt->execute();
                $export_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($export_data)) {
                    die('No data to export');
                }
                
                // Set headers for CSV download
                $filename = 'sales_demand_summary_' . date('Y-m-d_H-i-s') . '.csv';
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Pragma: no-cache');
                header('Expires: 0');
                
                // Output CSV
                $output = fopen('php://output', 'w');
                
                // Headers
                fputcsv($output, array_keys($export_data[0]));
                
                // Data
                foreach ($export_data as $row) {
                    fputcsv($output, $row);
                }
                
                fclose($output);
                exit();
            } else {
                die('Database connection not available');
            }
            
        } catch (PDOException $e) {
            die('Export failed: ' . $e->getMessage());
        }
        break;
        
    case 'export_sku_matching':
        // Export ALL SKU matching results to CSV (no LIMIT, no session data)
        try {
            $npc_db1 = get_npc_db1_connection();
            
            if ($npc_db1) {
                // Always run the full query to get ALL results without any LIMIT
                $export_query = "
                SELECT DISTINCT 
                    sds.SKU,
                    sds.Need_3mo,
                    sds.Need_6mo,
                    sds.Purchase_Price,
                    h.hollander_no as matched_590,
                    i.inventory_no as hardware_number,
                    s.mfr_software_no as software_number,
                    'Software Match' as match_type
                FROM `" . NPC_WEBSITE_NAME . "`.sales_demand_summary sds
                INNER JOIN `" . NPC_DB1_NAME . "`.inventory i 
                    ON i.inventory_no COLLATE utf8mb4_unicode_520_ci = sds.SKU COLLATE utf8mb4_unicode_520_ci
                INNER JOIN `" . NPC_DB1_NAME . "`.software s 
                    ON s.inventory_id = i.inventory_id
                INNER JOIN `" . NPC_DB1_NAME . "`.hollander_software_map hsm 
                    ON hsm.software_id = s.software_id
                INNER JOIN `" . NPC_DB1_NAME . "`.hollander h 
                    ON h.hollander_id = hsm.hollander_id
                WHERE h.hollander_no IS NOT NULL

                UNION ALL

                SELECT DISTINCT 
                    sds.SKU,
                    sds.Need_3mo,
                    sds.Need_6mo,
                    sds.Purchase_Price,
                    h.hollander_no as matched_590,
                    i.inventory_no as hardware_number,
                    '' as software_number,
                    'Hardware Match' as match_type
                FROM `" . NPC_WEBSITE_NAME . "`.sales_demand_summary sds
                INNER JOIN `" . NPC_DB1_NAME . "`.inventory i 
                    ON i.inventory_no COLLATE utf8mb4_unicode_520_ci = sds.SKU COLLATE utf8mb4_unicode_520_ci
                INNER JOIN `" . NPC_DB1_NAME . "`.inventory_hollander_map ihm 
                    ON ihm.inventory_id = i.inventory_id
                INNER JOIN `" . NPC_DB1_NAME . "`.hollander h 
                    ON h.hollander_id = ihm.hollander_id
                WHERE h.hollander_no IS NOT NULL
                
                ORDER BY Need_3mo DESC, Need_6mo DESC
                ";
                
                error_log("Export Query: " . $export_query);
                
                $result = $npc_db1->query($export_query);
                if (!$result) {
                    die('Export query failed');
                }
                $export_data = $result->fetchAll(PDO::FETCH_ASSOC);
                $filename = 'sku_matching_results_' . date('Y-m-d_H-i-s') . '.csv';
                
                error_log("Export Data Count: " . count($export_data));
            } else {
                die('Database connection not available');
            }
            
            if (empty($export_data)) {
                die('No matching data to export');
            }
            
            // Set headers for CSV download
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            // Output CSV
            $output = fopen('php://output', 'w');
            
            // Headers
            fputcsv($output, array_keys($export_data[0]));
            
            // Data
            foreach ($export_data as $row) {
                fputcsv($output, $row);
            }
            
            fclose($output);
            exit();
            
        } catch (Exception $e) {
            die('Export failed: ' . $e->getMessage());
        }
        break;
        
    case 'find_sku_matching':
        // Find matching 590 numbers from hardware/software to 590 numbers using NPC Database 1
        try {
            // Get NPC Database 1 connection
            $npc_db1 = get_npc_db1_connection();
            
            if ($npc_db1) {
                // Build the matching query to find 590 numbers from hardware/software - OPTIMIZED with UNION ALL
                $matching_query = "
                SELECT DISTINCT 
                    sds.SKU,
                    sds.Need_3mo,
                    sds.Need_6mo,
                    sds.Purchase_Price,
                    h.hollander_no as matched_590,
                    i.inventory_no as hardware_number,
                    s.mfr_software_no as software_number,
                    'Software Match' as match_type
                FROM `" . NPC_WEBSITE_NAME . "`.sales_demand_summary sds
                INNER JOIN `" . NPC_DB1_NAME . "`.inventory i 
                    ON i.inventory_no COLLATE utf8mb4_unicode_520_ci = sds.SKU COLLATE utf8mb4_unicode_520_ci
                INNER JOIN `" . NPC_DB1_NAME . "`.software s 
                    ON s.inventory_id = i.inventory_id
                INNER JOIN `" . NPC_DB1_NAME . "`.hollander_software_map hsm 
                    ON hsm.software_id = s.software_id
                INNER JOIN `" . NPC_DB1_NAME . "`.hollander h 
                    ON h.hollander_id = hsm.hollander_id
                WHERE h.hollander_no IS NOT NULL

                UNION ALL

                SELECT DISTINCT 
                    sds.SKU,
                    sds.Need_3mo,
                    sds.Need_6mo,
                    sds.Purchase_Price,
                    h.hollander_no as matched_590,
                    i.inventory_no as hardware_number,
                    '' as software_number,
                    'Hardware Match' as match_type
                FROM `" . NPC_WEBSITE_NAME . "`.sales_demand_summary sds
                INNER JOIN `" . NPC_DB1_NAME . "`.inventory i 
                    ON i.inventory_no COLLATE utf8mb4_unicode_520_ci = sds.SKU COLLATE utf8mb4_unicode_520_ci
                INNER JOIN `" . NPC_DB1_NAME . "`.inventory_hollander_map ihm 
                    ON ihm.inventory_id = i.inventory_id
                INNER JOIN `" . NPC_DB1_NAME . "`.hollander h 
                    ON h.hollander_id = ihm.hollander_id
                WHERE h.hollander_no IS NOT NULL
                
                ORDER BY Need_3mo DESC, Need_6mo DESC
                ";
                
                // Debug: Log the query
                error_log("SKU Matching Query: " . $matching_query);
                
                // Debug: Check if we're in a loop
                if (isset($_SESSION['sku_matching_debug_count'])) {
                    $_SESSION['sku_matching_debug_count']++;
                } else {
                    $_SESSION['sku_matching_debug_count'] = 1;
                }
                
                error_log("SKU Matching Debug Count: " . $_SESSION['sku_matching_debug_count']);
                
                // Debug: Test simple query first
                try {
                    $test_query = "SELECT COUNT(*) as count FROM `" . NPC_WEBSITE_NAME . "`.sales_demand_summary";
                    error_log("Test Query: " . $test_query);
                    $test_result = $npc_db1->query($test_query);
                    if ($test_result) {
                        $test_count = $test_result->fetchColumn();
                        error_log("Test Query Result: " . $test_count . " records in sales_demand_summary");
                        
                        // For large datasets, use a simpler approach
                        if ($test_count > 1000) {
                            error_log("Large dataset detected: " . $test_count . " records. Using simplified query");
                            // Use a simpler, faster query for large datasets with both hardware and software matching
                            $matching_query = "
                            SELECT DISTINCT 
                                sds.SKU,
                                sds.Need_3mo,
                                sds.Need_6mo,
                                sds.Purchase_Price,
                                h.hollander_no as matched_590,
                                i.inventory_no as hardware_number,
                                s.mfr_software_no as software_number,
                                'Software Match' as match_type
                            FROM `" . NPC_WEBSITE_NAME . "`.sales_demand_summary sds
                            INNER JOIN `" . NPC_DB1_NAME . "`.inventory i 
                                ON i.inventory_no COLLATE utf8mb4_unicode_520_ci = sds.SKU COLLATE utf8mb4_unicode_520_ci
                            INNER JOIN `" . NPC_DB1_NAME . "`.software s 
                                ON s.inventory_id = i.inventory_id
                            INNER JOIN `" . NPC_DB1_NAME . "`.hollander_software_map hsm 
                                ON hsm.software_id = s.software_id
                            INNER JOIN `" . NPC_DB1_NAME . "`.hollander h 
                                ON h.hollander_id = hsm.hollander_id
                            WHERE h.hollander_no IS NOT NULL
                            AND (sds.Need_3mo > 0 OR sds.Need_6mo > 0)

                            UNION ALL

                            SELECT DISTINCT 
                                sds.SKU,
                                sds.Need_3mo,
                                sds.Need_6mo,
                                sds.Purchase_Price,
                                h.hollander_no as matched_590,
                                i.inventory_no as hardware_number,
                                '' as software_number,
                                'Hardware Match' as match_type
                            FROM `" . NPC_WEBSITE_NAME . "`.sales_demand_summary sds
                            INNER JOIN `" . NPC_DB1_NAME . "`.inventory i 
                                ON i.inventory_no COLLATE utf8mb4_unicode_520_ci = sds.SKU COLLATE utf8mb4_unicode_520_ci
                            INNER JOIN `" . NPC_DB1_NAME . "`.inventory_hollander_map ihm 
                                ON ihm.inventory_id = i.inventory_id
                            INNER JOIN `" . NPC_DB1_NAME . "`.hollander h 
                                ON h.hollander_id = ihm.hollander_id
                            WHERE h.hollander_no IS NOT NULL
                            AND (sds.Need_3mo > 0 OR sds.Need_6mo > 0)
                            
                            ORDER BY Need_3mo DESC, Need_6mo DESC
                            ";
                        }
                    } else {
                        error_log("Test Query Failed");
                    }
                } catch (Exception $e) {
                    error_log("Test Query Error: " . $e->getMessage());
                }
                
                // Execute the query
                $result = $npc_db1->query($matching_query);
                if (!$result) {
                    $error_info = $npc_db1->errorInfo();
                    throw new Exception("Database query failed: " . $error_info[2]);
                }
                $matching_results = $result->fetchAll(PDO::FETCH_ASSOC);
                
                error_log("SKU Matching Results Count: " . count($matching_results));
                
                // Store results in session
                $_SESSION['sku_matching_results'] = $matching_results;
                $_SESSION['sku_matching_count'] = count($matching_results);
                $_SESSION['sku_matching_query'] = $matching_query;
                
                // Simple pagination without COUNT query for speed
                $per_page = 50;
                $current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $offset = ($current_page - 1) * $per_page;
                
                // Add pagination to the main query
                $matching_query .= " LIMIT $per_page OFFSET $offset";
                error_log("Final Query with Pagination: " . $matching_query);
                
                // Execute the paginated query
                $result = $npc_db1->query($matching_query);
                if (!$result) {
                    $error_info = $npc_db1->errorInfo();
                    throw new Exception("Database query failed: " . $error_info[2]);
                }
                $paginated_results = $result->fetchAll(PDO::FETCH_ASSOC);
                
                // Simple pagination info (no total count needed)
                $total_results = count($paginated_results) + $offset; // Estimate
                $total_pages = $current_page + 10; // Show more pages
                if (count($paginated_results) < $per_page) {
                    $total_pages = $current_page; // Last page
                }
                
                // Store pagination info in session
                $_SESSION['sku_matching_pagination'] = [
                    'total_results' => $total_results,
                    'total_pages' => $total_pages,
                    'per_page' => $per_page,
                    'current_page' => $current_page
                ];
                
                error_log("SKU Matching Pagination: Page $current_page of $total_pages, Showing " . count($paginated_results) . " results");
                
            } else {
                $error = 'NPC Database 1 connection not available';
            }
            
        } catch (Exception $e) {
            error_log("SKU matching error: " . $e->getMessage());
            $error = "Matching Error: " . $e->getMessage();
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
            background-color: #4CAF50;
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
            background: linear-gradient(135deg, #4CAF50, #45a049);
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
        
        /* Budget comparison styles */
        .price-under-budget {
            background: #d4edda !important;
            color: #155724 !important;
            font-weight: bold;
        }
        
        .price-over-budget {
            background: #f8d7da !important;
            color: #721c24 !important;
            font-weight: bold;
        }
        
        .price-equal-budget {
            background: #fff3cd !important;
            color: #856404 !important;
            font-weight: bold;
        }
        
        /* SKU Inventory Report styles */
        .sku-report-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .sku-report-table th,
        .sku-report-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .sku-report-table th {
            background-color: #28a745;
            color: white;
            font-weight: bold;
        }
        
        .sku-report-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .sku-report-header {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        
        .sku-report-header h3 {
            margin: 0 0 10px 0;
            color: #28a745;
        }
        
        .sku-report-header p {
            margin: 5px 0;
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
                <a href="all.php?action=compare_data" class="btn" style="background-color: #ff8c00;">Compare Data</a>
                <a href="all.php?action=sku_inventory_report" class="btn" style="background-color: #28a745;">Sales Demand Summary</a>
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
                            <label><?php echo ucfirst($field); ?>: *</label>
                            <select name="mapping[<?php echo $field; ?>]" required>
                                <option value="">Select CSV Column</option>
                                <?php foreach ($csv_headers as $index => $header): ?>
                                    <option value="<?php echo $index; ?>"><?php echo htmlspecialchars($header); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php foreach ($optional_fields as $field): ?>
                        <div class="field-map">
                            <label><?php echo ucfirst($field); ?>: (Optional)</label>
                            <select name="mapping[<?php echo $field; ?>]">
                                <option value="">Select CSV Column (Optional)</option>
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
                    
                    <div class="bypass-option" style="margin: 30px 0; padding: 20px; background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); border: 2px solid #ffc107; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                        <div style="display: flex; align-items: center; margin-bottom: 15px;">
                            <div style="background-color: #ffc107; color: #000; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px; font-size: 20px; font-weight: bold;">
                                ⚠️
                            </div>
                            <div>
                                <h4 style="margin: 0 0 5px 0; color: #856404; font-size: 18px;">Important: Data Type Selection</h4>
                                <p style="margin: 0; color: #856404; font-size: 14px;">Choose how your numbers should be processed</p>
                            </div>
                        </div>
                        
                        <div style="background-color: white; padding: 15px; border-radius: 6px; border: 1px solid #dee2e6;">
                            <label style="display: flex; align-items: flex-start; cursor: pointer; margin-bottom: 10px;">
                                <input type="checkbox" name="bypass_590" value="1" style="margin-right: 12px; margin-top: 2px; transform: scale(1.2);">
                                <div>
                                    <strong style="color: #dc3545; font-size: 16px;">This data contains NON-590 numbers</strong>
                                    <p style="margin: 5px 0 0 0; color: #6c757d; font-size: 14px;">
                                        Check this if you're uploading hardware numbers, software numbers, part numbers, or any other type of numbers that are NOT 590 numbers.
                                    </p>
                                </div>
                            </label>
                            
                            <div style="background-color: #f8f9fa; padding: 12px; border-radius: 4px; border-left: 4px solid #28a745;">
                                <div style="display: flex; align-items: center; margin-bottom: 8px;">
                                    <span style="background-color: #28a745; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; margin-right: 10px;">INFO</span>
                                    <strong style="color: #155724;">Processing Options:</strong>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; font-size: 14px;">
                                    <div style="background-color: white; padding: 10px; border-radius: 4px; border: 1px solid #dee2e6;">
                                        <strong style="color: #dc3545;">✓ When CHECKED:</strong><br>
                                        <span style="color: #6c757d;">Numbers will be used as-is without any 590 processing</span>
                                    </div>
                                    <div style="background-color: white; padding: 10px; border-radius: 4px; border: 1px solid #dee2e6;">
                                        <strong style="color: #28a745;">✓ When UNCHECKED:</strong><br>
                                        <span style="color: #6c757d;">Numbers will be processed as 590 numbers (default)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
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
                                    <option value="590" <?php echo (isset($_SESSION['match_type']) && $_SESSION['match_type'] == '590') ? 'selected' : ''; ?>>Match by 590 (Hollander)</option>
                                    <option value="591" <?php echo (isset($_SESSION['match_type']) && $_SESSION['match_type'] == '591') ? 'selected' : ''; ?>>Match by 591 (Hollander)</option>
                                    <option value="software" <?php echo (isset($_SESSION['match_type']) && $_SESSION['match_type'] == 'software') ? 'selected' : ''; ?>>Match by Software</option>
                                    <option value="hardware" <?php echo (isset($_SESSION['match_type']) && $_SESSION['match_type'] == 'hardware') ? 'selected' : ''; ?>>Match by Hardware</option>
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
                                <div class="legend-item">
                                    <div class="legend-color" style="background: #d4edda;"></div>
                                    <span>Price Under Budget</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color" style="background: #f8d7da;"></div>
                                    <span>Price Over Budget</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color" style="background: #fff3cd;"></div>
                                    <span>Price Equal to Budget</span>
                                </div>
                            </div>
                            
                            <div class="action-buttons">
                                <a href="all.php?action=export_csv&table=<?php echo urlencode($table_name); ?>&matched=1" class="btn" style="background-color: #ff8c00; color: white;">Export Matched Data</a>
                                <button onclick="toggleQueryDisplay()" class="btn" style="background-color: #17a2b8; color: white;">Show/Hide SQL Query</button>
                            </div>
                            
                            <!-- SQL Query Display -->
                            <div id="query-display" style="display: none; margin: 20px 0; padding: 15px; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px;">
                                <h4>Executed SQL Query:</h4>
                                <pre style="background: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 4px; overflow-x: auto; white-space: pre-wrap; font-size: 12px;"><?php echo isset($_SESSION['last_match_query']) ? htmlspecialchars($_SESSION['last_match_query']) : 'No query available'; ?></pre>
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
                                                    
                                                    // Add budget comparison for price field
                                                    if ($header === 'price' && isset($row['budget'])) {
                                                        $budget_class = get_price_budget_class($value, $row['budget']);
                                                        if (!empty($budget_class)) {
                                                            $cell_class .= ' ' . $budget_class;
                                                        }
                                                    }
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
                    <a href="all.php?action=compare_data" class="btn" style="background-color: #ff8c00;">Compare Data</a>
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
                
                function toggleQueryDisplay() {
                    const queryDisplay = document.getElementById('query-display');
                    if (queryDisplay.style.display === 'none') {
                        queryDisplay.style.display = 'block';
                    } else {
                        queryDisplay.style.display = 'none';
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
            
        <?php elseif ($action === 'compare_data'): ?>
            <h2>Compare Vendor Data</h2>
            
            <?php if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['compare_tables'])): ?>
                <?php
                $selected_tables = $_POST['compare_tables'];
                $price_column = $_POST['price_column'] ?? 'price';
                
                if (count($selected_tables) >= 2) {
                    try {
                        // Get database connections
                        $vendor_db = get_vendor_db_connection();
                        $npc_db1 = get_npc_db1_connection();
                        $npc_website_db = get_npc_website_connection();
                        
                        // Build the comparison query
                        $union_parts = [];
                        $join_parts = [];
                        $vendor_count = 1;
                        
                        foreach ($selected_tables as $table_name) {
                            $union_parts[] = "SELECT `590` AS part_number FROM " . DB_NAME . ".`$table_name`";
                            $join_parts[] = "LEFT JOIN " . DB_NAME . ".`$table_name` v$vendor_count \n    ON v$vendor_count.`590` = p.part_number";
                            $vendor_count++;
                        }
                        
                        $union_query = implode("\n        UNION ALL\n        ", $union_parts);
                        $join_query = implode("\n\n", $join_parts);
                        
                        // Get vendor names for the selected tables
                        $vendor_names = [];
                        foreach ($selected_tables as $table_name) {
                            $stmt = $pdo->prepare("
                                SELECT v.vendor_name 
                                FROM import_history ih 
                                JOIN vendors v ON v.id = ih.vendor_id 
                                WHERE ih.imported_table = ?
                            ");
                            $stmt->execute([$table_name]);
                            $vendor_name = $stmt->fetchColumn();
                            $vendor_names[] = $vendor_name ?: 'Unknown_Vendor';
                        }
                        
                        // Build vendor price columns with actual vendor names
                        $vendor_price_columns = [];
                        for ($i = 1; $i <= count($selected_tables); $i++) {
                            $vendor_name = str_replace(' ', '_', $vendor_names[$i-1]);
                            $vendor_price_columns[] = "v$i.$price_column AS {$vendor_name}_price";
                        }
                        
                        // Build dynamic CASE conditions for cheapest vendor with actual vendor names
                        $case_conditions = [];
                        for ($i = 1; $i <= count($selected_tables); $i++) {
                            $conditions = [];
                            for ($j = 1; $j <= count($selected_tables); $j++) {
                                if ($j != $i) {
                                    $conditions[] = "CAST(v$i.$price_column AS DECIMAL(10,2)) <= CAST(v$j.$price_column AS DECIMAL(10,2))";
                                }
                            }
                            $vendor_name = $vendor_names[$i-1];
                            $case_conditions[] = "WHEN v$i.$price_column IS NOT NULL AND (" . implode(" AND ", $conditions) . ") THEN '$vendor_name'";
                        }
                        
                        $compare_query = "SELECT 
    p.part_number,
    " . implode(",\n    ", $vendor_price_columns) . ",
    inv.inventory_no AS npc_inventory,
    sds.Need_3mo,
    sds.Need_6mo,
    sds.Purchase_Price AS npc_budget,
    
    CASE
        " . implode("\n        ", $case_conditions) . "
        ELSE 'No Price'
    END AS cheapest_vendor,
    
    (sds.Purchase_Price - LEAST(" . implode(", ", array_map(function($i) use ($price_column) {
        return "IFNULL(CAST(v$i.$price_column AS DECIMAL(10,2)), 999999)";
    }, range(1, count($selected_tables)))) . ")) AS price_difference

FROM (
    SELECT DISTINCT part_number FROM (
        " . $union_query . "
    ) AS combined_parts
    WHERE part_number IS NOT NULL AND part_number != ''
) AS p

" . $join_query . "

JOIN `" . NPC_DB1_NAME . "`.hollander h 
    ON h.hollander_no = p.part_number

JOIN `" . NPC_DB1_NAME . "`.inventory_hollander_map ihm 
    ON ihm.hollander_id = h.hollander_id

JOIN `" . NPC_DB1_NAME . "`.inventory inv 
    ON inv.inventory_id = ihm.inventory_id

JOIN `" . NPC_WEBSITE_NAME . "`.sales_demand_summary sds 
    ON sds.SKU COLLATE utf8mb4_unicode_520_ci = inv.inventory_no COLLATE utf8mb4_unicode_520_ci

WHERE p.part_number IS NOT NULL AND p.part_number != ''";
                        
                        // Execute the query
                        $result = $npc_db1->query($compare_query);
                        if (!$result) {
                            $error_info = $npc_db1->errorInfo();
                            throw new Exception("Database query failed: " . $error_info[2]);
                        }
                        $compare_results = $result->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Store results in session for export
                        $_SESSION['compare_results'] = $compare_results;
                        $_SESSION['compare_tables'] = $selected_tables;
                        $_SESSION['compare_query'] = $compare_query;
                        
                        // Setup pagination
                        $per_page = 50;
                        $total_results = count($compare_results);
                        $total_pages = ceil($total_results / $per_page);
                        $current_page = isset($_GET['page']) ? max(1, min((int)$_GET['page'], $total_pages)) : 1;
                        $offset = ($current_page - 1) * $per_page;
                        
                        // Store pagination info in session
                        $_SESSION['compare_pagination'] = [
                            'total_results' => $total_results,
                            'total_pages' => $total_pages,
                            'per_page' => $per_page,
                            'current_page' => $current_page
                        ];
                        
                        // Get paginated results
                        $paginated_results = array_slice($compare_results, $offset, $per_page);
                        
                    } catch (Exception $e) {
                        error_log("Compare data error: " . $e->getMessage());
                        $error = "Compare Error: " . $e->getMessage();
                    }
                } else {
                    $error = "Please select at least 2 tables to compare.";
                }
                ?>
                
                <?php if (isset($paginated_results) && !empty($paginated_results)): ?>
                    <div class="compare-results">
                        <h3>Comparison Results (<?php echo $total_results; ?> matches found) - Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></h3>
                        
                        <div class="action-buttons">
                            <button onclick="exportCompareResults()" class="btn" style="background-color: #ff8c00; color: white;">Export Comparison Results</button>
                            <button onclick="toggleCompareQuery()" class="btn" style="background-color: #17a2b8; color: white;">Show/Hide SQL Query</button>
                        </div>
                        
                        <!-- SQL Query Display -->
                        <div id="compare-query-display" style="display: none; margin: 20px 0; padding: 15px; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px;">
                            <h4>Executed SQL Query:</h4>
                            <pre style="background: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 4px; overflow-x: auto; white-space: pre-wrap; font-size: 12px;"><?php echo isset($compare_query) ? htmlspecialchars($compare_query) : 'No query available'; ?></pre>
                        </div>
                        
                        <div class="preview-container">
                            <table class="npc-matching-table">
                                <thead>
                                    <tr>
                                        <?php foreach (array_keys($paginated_results[0]) as $header): ?>
                                            <th><?php echo htmlspecialchars($header); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($paginated_results as $row): ?>
                                        <tr>
                                            <?php foreach ($row as $header => $value): ?>
                                                <td class="<?php echo $header === 'cheapest_vendor' ? 'matched-data' : 'original-data'; ?>">
                                                    <?php echo htmlspecialchars($value); ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <?php if ($current_page > 1): ?>
                                    <a href="?action=compare_data&page=1" class="btn btn-secondary">First</a>
                                    <a href="?action=compare_data&page=<?php echo $current_page - 1; ?>" class="btn btn-secondary">Previous</a>
                                <?php endif; ?>
                                
                                <?php
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <a href="?action=compare_data&page=<?php echo $i; ?>" 
                                       class="<?php echo $i == $current_page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($current_page < $total_pages): ?>
                                    <a href="?action=compare_data&page=<?php echo $current_page + 1; ?>" class="btn btn-secondary">Next</a>
                                    <a href="?action=compare_data&page=<?php echo $total_pages; ?>" class="btn btn-secondary">Last</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <script>
                        function exportCompareResults() {
                            window.location.href = 'all.php?action=export_compare';
                        }
                        
                        function toggleCompareQuery() {
                            const queryDisplay = document.getElementById('compare-query-display');
                            if (queryDisplay.style.display === 'none') {
                                queryDisplay.style.display = 'block';
                            } else {
                                queryDisplay.style.display = 'none';
                            }
                        }
                    </script>
                <?php endif; ?>
                
            <?php else: ?>
                <?php
                // Check if we have stored results and pagination info
                if (isset($_SESSION['compare_results']) && isset($_SESSION['compare_pagination']) && isset($_GET['page'])) {
                    $compare_results = $_SESSION['compare_results'];
                    $pagination = $_SESSION['compare_pagination'];
                    $total_results = $pagination['total_results'];
                    $total_pages = $pagination['total_pages'];
                    $per_page = $pagination['per_page'];
                    $current_page = max(1, min((int)$_GET['page'], $total_pages));
                    $offset = ($current_page - 1) * $per_page;
                    $paginated_results = array_slice($compare_results, $offset, $per_page);
                    
                    // Update current page in session
                    $_SESSION['compare_pagination']['current_page'] = $current_page;
                } else {
                    // Get all available import tables
                    $stmt = $pdo->query("
                        SELECT 
                            ih.imported_table,
                            ih.imported_at,
                            ih.total_records,
                            v.vendor_name
                        FROM import_history ih
                        JOIN vendors v ON v.id = ih.vendor_id
                        ORDER BY ih.imported_at DESC
                    ");
                    $available_tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                ?>
                
                <?php if (isset($paginated_results) && !empty($paginated_results)): ?>
                    <div class="compare-results">
                        <h3>Comparison Results (<?php echo $total_results; ?> matches found) - Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></h3>
                        
                        <div class="action-buttons">
                            <button onclick="exportCompareResults()" class="btn" style="background-color: #ff8c00; color: white;">Export Comparison Results</button>
                            <button onclick="toggleCompareQuery()" class="btn" style="background-color: #17a2b8; color: white;">Show/Hide SQL Query</button>
                        </div>
                        
                        <!-- SQL Query Display -->
                        <div id="compare-query-display" style="display: none; margin: 20px 0; padding: 15px; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px;">
                            <h4>Executed SQL Query:</h4>
                            <pre style="background: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 4px; overflow-x: auto; white-space: pre-wrap; font-size: 12px;"><?php echo isset($_SESSION['compare_query']) ? htmlspecialchars($_SESSION['compare_query']) : 'No query available'; ?></pre>
                        </div>
                        
                        <div class="preview-container">
                            <table class="npc-matching-table">
                                <thead>
                                    <tr>
                                        <?php foreach (array_keys($paginated_results[0]) as $header): ?>
                                            <th><?php echo htmlspecialchars($header); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($paginated_results as $row): ?>
                                        <tr>
                                            <?php foreach ($row as $header => $value): ?>
                                                <td class="<?php echo $header === 'cheapest_vendor' ? 'matched-data' : 'original-data'; ?>">
                                                    <?php echo htmlspecialchars($value); ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <?php if ($current_page > 1): ?>
                                    <a href="?action=compare_data&page=1" class="btn btn-secondary">First</a>
                                    <a href="?action=compare_data&page=<?php echo $current_page - 1; ?>" class="btn btn-secondary">Previous</a>
                                <?php endif; ?>
                                
                                <?php
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <a href="?action=compare_data&page=<?php echo $i; ?>" 
                                       class="<?php echo $i == $current_page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($current_page < $total_pages): ?>
                                    <a href="?action=compare_data&page=<?php echo $current_page + 1; ?>" class="btn btn-secondary">Next</a>
                                    <a href="?action=compare_data&page=<?php echo $total_pages; ?>" class="btn btn-secondary">Last</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <script>
                        function exportCompareResults() {
                            window.location.href = 'all.php?action=export_compare';
                        }
                        
                        function toggleCompareQuery() {
                            const queryDisplay = document.getElementById('compare-query-display');
                            if (queryDisplay.style.display === 'none') {
                                queryDisplay.style.display = 'block';
                            } else {
                                queryDisplay.style.display = 'none';
                            }
                        }
                    </script>
                <?php else: ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="compare_tables">Select Tables to Compare (at least 2):</label>
                        <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
                            <?php foreach ($available_tables as $table): ?>
                                <div style="margin-bottom: 10px;">
                                    <input type="checkbox" name="compare_tables[]" value="<?php echo htmlspecialchars($table['imported_table']); ?>" id="table_<?php echo htmlspecialchars($table['imported_table']); ?>">
                                    <label for="table_<?php echo htmlspecialchars($table['imported_table']); ?>">
                                        <strong><?php echo htmlspecialchars($table['vendor_name']); ?></strong> 
                                        (<?php echo htmlspecialchars($table['total_records']); ?> records) - 
                                        <?php echo htmlspecialchars($table['imported_at']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="price_column">Price Column Name:</label>
                        <input type="text" name="price_column" value="price" placeholder="Enter the column name containing prices">
                        <small>Default is 'price'. Make sure this column exists in all selected tables.</small>
                    </div>
                    
                    <button type="submit" class="btn">Compare Data</button>
                </form>
            <?php endif; ?>
            
        <?php endif; ?>
            
        <?php elseif ($action === 'sku_inventory_report'): ?>
            <h2>Sales Demand Summary Report</h2>
            
            <?php if (isset($error)): ?>
                <div class="status-message status-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php elseif (isset($sku_data) && !empty($sku_data)): ?>
                <div class="sku-report-header">
                    <h3>NPC Website Database: <?php echo htmlspecialchars($external_dbname); ?></h3>
                    <p><strong>Table:</strong> <?php echo htmlspecialchars($external_table); ?></p>
                    <p><strong>Total Records:</strong> <?php echo htmlspecialchars($total_records); ?></p>
                    <p><strong>Showing:</strong> Page <?php echo htmlspecialchars($page); ?> of <?php echo htmlspecialchars($total_pages); ?></p>
                </div>
                
                <div class="action-buttons">
                    <a href="all.php?action=export_sku_report" class="btn" style="background-color: #28a745; color: white;">Export All Data to CSV</a>
                    <a href="all.php?action=find_sku_matching" class="btn" style="background-color: #007bff; color: white;">Find Matching</a>
                </div>
                
                <div class="preview-container">
                    <table class="sku-report-table">
                        <thead>
                            <tr>
                                <?php foreach ($columns as $column): ?>
                                    <th><?php echo htmlspecialchars($column); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sku_data as $row): ?>
                                <tr>
                                    <?php foreach ($columns as $column): ?>
                                        <td><?php echo htmlspecialchars($row[$column] ?? ''); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?action=sku_inventory_report&page=1" class="btn btn-secondary">First</a>
                            <a href="?action=sku_inventory_report&page=<?php echo $page - 1; ?>" class="btn btn-secondary">Previous</a>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <a href="?action=sku_inventory_report&page=<?php echo $i; ?>" 
                               class="<?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?action=sku_inventory_report&page=<?php echo $page + 1; ?>" class="btn btn-secondary">Next</a>
                            <a href="?action=sku_inventory_report&page=<?php echo $total_pages; ?>" class="btn btn-secondary">Last</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="status-message status-info">
                    <p>No SKU inventory data found or connection failed.</p>
                </div>
            <?php endif; ?>
            
        <?php elseif ($action === 'find_sku_matching'): ?>
            <h2>SKU Matching Results</h2>
            
            <?php if (isset($error)): ?>
                <div class="status-message status-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php elseif (isset($paginated_results) && !empty($paginated_results)): ?>
                <div class="sku-report-header">
                    <h3>Matching Results (<?php echo $total_results; ?> matches found)</h3>
                    <p><strong>Database:</strong> NPC Database 1 (test_play)</p>
                    <p><strong>Showing:</strong> Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></p>
                </div>
                
                <div class="action-buttons">
                    <a href="all.php?action=export_sku_matching" class="btn" style="background-color: #28a745; color: white;">Export All Matching Results</a>
                    <button onclick="toggleSkuMatchingQuery()" class="btn" style="background-color: #17a2b8; color: white;">Show/Hide SQL Query</button>
                </div>
                
                <!-- Debug Information -->
                <div style="background: #f8f9fa; padding: 15px; border: 1px solid #e9ecef; border-radius: 8px; margin: 20px 0;">
                    <h4>Debug Information:</h4>
                    <p><strong>Debug Count:</strong> <?php echo isset($_SESSION['sku_matching_debug_count']) ? $_SESSION['sku_matching_debug_count'] : 'Not set'; ?></p>
                    <p><strong>Total Results:</strong> <?php echo $total_results; ?></p>
                    <p><strong>Current Page:</strong> <?php echo $current_page; ?></p>
                    <p><strong>Total Pages:</strong> <?php echo $total_pages; ?></p>
                    <p><strong>Results on this page:</strong> <?php echo count($paginated_results); ?></p>
                </div>
                
                <!-- SQL Query Display -->
                <div id="sku-matching-query-display" style="display: none; margin: 20px 0; padding: 15px; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px;">
                    <h4>Executed SQL Query:</h4>
                    <pre style="background: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 4px; overflow-x: auto; white-space: pre-wrap; font-size: 12px;"><?php echo isset($_SESSION['sku_matching_query']) ? htmlspecialchars($_SESSION['sku_matching_query']) : 'No query available'; ?></pre>
                </div>
                
                <div class="preview-container">
                    <table class="sku-report-table">
                        <thead>
                            <tr>
                                <?php foreach (array_keys($paginated_results[0]) as $header): ?>
                                    <th><?php echo htmlspecialchars($header); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paginated_results as $row): ?>
                                <tr>
                                    <?php foreach ($row as $header => $value): ?>
                                        <td class="<?php echo $header === 'match_type' ? 'matched-data' : 'original-data'; ?>">
                                            <?php echo htmlspecialchars($value); ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($current_page > 1): ?>
                            <a href="?action=find_sku_matching&page=1" class="btn btn-secondary">First</a>
                            <a href="?action=find_sku_matching&page=<?php echo $current_page - 1; ?>" class="btn btn-secondary">Previous</a>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <a href="?action=find_sku_matching&page=<?php echo $i; ?>" 
                               class="<?php echo $i == $current_page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?action=find_sku_matching&page=<?php echo $current_page + 1; ?>" class="btn btn-secondary">Next</a>
                            <a href="?action=find_sku_matching&page=<?php echo $total_pages; ?>" class="btn btn-secondary">Last</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <script>
                    function toggleSkuMatchingQuery() {
                        const queryDisplay = document.getElementById('sku-matching-query-display');
                        if (queryDisplay.style.display === 'none') {
                            queryDisplay.style.display = 'block';
                        } else {
                            queryDisplay.style.display = 'none';
                        }
                    }
                </script>
                
            <?php else: ?>
                <div class="status-message status-info">
                    <p>No matching results found or connection failed.</p>
                </div>
            <?php endif; ?>
            
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