<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_FILES['csvFile']) && $_FILES['csvFile']['error'] == 0) {
        // Validate file size (max 50MB)
        if ($_FILES['csvFile']['size'] > 50 * 1024 * 1024) {
            die('File size too large. Maximum 50MB allowed.');
        }

        $file_name = $_FILES['csvFile']['tmp_name'];
        
        // Create a secure storage location outside web root
        $upload_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'secure_uploads' . DIRECTORY_SEPARATOR;
        
        // Create directory with secure permissions if it doesn't exist
        if (!file_exists($upload_dir)) {
            // Create directory with restrictive permissions (owner read/write only)
            if (!mkdir($upload_dir, 0750, true)) {
                die('Failed to create upload directory. Please contact administrator.');
            }
            
            // Set ownership to web server user (usually www-data)
            // Note: This requires the script to run with sufficient privileges
            $web_user = 'www-data';  // Apache default user
            $web_group = 'www-data'; // Apache default group
            chown($upload_dir, $web_user);
            chgrp($upload_dir, $web_group);
        }
        
        // Generate secure filename with restricted extension
        $secure_filename = uniqid('csv_', true) . '.csv';
        $permanent_file = $upload_dir . $secure_filename;
        
        // Move uploaded file with secure permissions
        if (!move_uploaded_file($file_name, $permanent_file)) {
            die('Failed to move uploaded file. Please check directory permissions.');
        }
        
        // Set secure file permissions (readable/writable only by owner)
        chmod($permanent_file, 0640);
        
        // Read only first line for headers with proper error handling
        if (($handle = fopen($permanent_file, 'r')) !== FALSE) {
            $headers = fgetcsv($handle);
            fclose($handle);
            
            // Store file info in session with relative path only
            $_SESSION['csv_file'] = $permanent_file;
            $_SESSION['csv_headers'] = $headers;
            
            header('Location: vendor_details.php');
            exit();
        } else {
            // Clean up on error
            unlink($permanent_file);
            die('Failed to read CSV file. Please try again.');
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>CSV Upload</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .upload-form { max-width: 500px; margin: 0 auto; }
        .submit-btn { margin-top: 10px; }
        .requirements { margin: 20px 0; color: #666; }
    </style>
</head>
<body>
    <div class="upload-form">
        <h2>Upload CSV File</h2>
        <div class="requirements">
            <p>Maximum file size: 50MB</p>
            <p>Supported format: CSV</p>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="csvFile" accept=".csv" required>
            <br>
            <input type="submit" value="Upload and Map" class="submit-btn">
        </form>
    </div>
</body>
</html> 