<?php
/**
 * Vendor Port - Main Navigation Page
 *
 * This page serves as the main entry point for the Vendor Port application,
 * providing navigation to all major features.
 *
 * @package Vendor_Port
 */

// Start session
session_start();

// Include database configuration and installation script
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/install.php';

// Check if tables exist, if not they will be created by install.php
$db_status = check_database_status();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Port Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        
        .container {
            width: 80%;
            margin: 20px auto;
            background: white;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 5px;
        }
        
        h1 {
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
        }
        
        .btn:hover {
            background-color: #45a049;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>Vendor Port Dashboard</h1>
        
        <?php if (isset($db_status)): ?>
            <div class="status-message <?php echo $db_status['type']; ?>">
                <?php echo $db_status['message']; ?>
            </div>
        <?php endif; ?>
        
        <div class="action-buttons">
            <a href="upload.php" class="btn">Upload New Data</a>
            <a href="view_data.php?nocache=true" class="btn">View All Imports</a>
        </div>
    </div>
</body>
</html> 