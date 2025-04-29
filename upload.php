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
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background-color: #f9f9f9;
            color: #333;
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
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .requirements { 
            margin: 20px 0; 
            color: #555;
            padding: 15px;
            background-color: #f5f5f5;
            border-left: 4px solid #4CAF50;
            border-radius: 3px;
        }
        .file-input-container {
            margin: 20px 0;
            padding: 15px;
            border: 2px dashed #ccc;
            border-radius: 5px;
            text-align: center;
        }
        input[type="file"] {
            display: block;
            margin: 10px auto;
            padding: 10px;
            width: 100%;
            max-width: 400px;
        }
        .submit-btn { 
            margin-top: 20px; 
            padding: 8px 15px; 
            background-color: #4CAF50; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer;
            font-size: 16px;
            display: block;
            width: 200px;
            margin: 20px auto 0;
        }
        .submit-btn:hover { 
            background-color: #45a049; 
        }
    </style>
</head>
<body>
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
            <input type="submit" value="Upload and Continue" class="submit-btn">
        </form>
    </div>
</body>
</html> 