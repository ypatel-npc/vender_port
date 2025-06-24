<?php
session_start();

// Check if we have processing results
if (!isset($_SESSION['processing_result'])) {
    header('Location: upload.php');
    exit();
}

$result = $_SESSION['processing_result'];
unset($_SESSION['processing_result']); // Clear the result from session
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processing Complete</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            background: #f5f5f5; 
            margin: 0; 
            padding: 20px;
        }
        .container { 
            max-width: 800px; 
            margin: 0 auto; 
            background: #fff; 
            border-radius: 8px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.1); 
            padding: 30px; 
        }
        .success-box {
            background: #e8f5e8;
            border: 1px solid #4caf50;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .success-box h2 {
            color: #2e7d32;
            margin-top: 0;
        }
        .info-box {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .info-box h3 {
            margin-top: 0;
            color: #1976d2;
        }
        .error-box {
            background: #ffebee;
            border: 1px solid #ffcdd2;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .error-box h3 {
            color: #b71c1c;
            margin-top: 0;
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
            margin: 10px 10px 10px 0;
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
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .stat-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            text-align: center;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #4CAF50;
        }
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($result['success']): ?>
            <div class="success-box">
                <h2>✅ Processing Complete!</h2>
                <p>Your CSV file has been successfully processed and imported into the database.</p>
            </div>

            <div class="stats">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $result['processed']; ?></div>
                    <div class="stat-label">Records Processed</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $result['total_rows']; ?></div>
                    <div class="stat-label">Total Rows</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo count($result['errors']); ?></div>
                    <div class="stat-label">Errors</div>
                </div>
            </div>

            <div class="info-box">
                <h3>📊 Import Details</h3>
                <p><strong>Table Name:</strong> <code><?php echo htmlspecialchars($result['table_name']); ?></code></p>
                <p><strong>Success Rate:</strong> <?php echo $result['total_rows'] > 0 ? round(($result['processed'] / $result['total_rows']) * 100, 1) : 0; ?>%</p>
                <p><strong>590 Extraction:</strong> Applied automatically where applicable</p>
            </div>

            <?php if (!empty($result['errors'])): ?>
                <div class="error-box">
                    <h3>⚠️ Processing Errors</h3>
                    <p>The following errors occurred during processing:</p>
                    <ul>
                        <?php foreach ($result['errors'] as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="error-box">
                <h3>❌ Processing Failed</h3>
                <p>An error occurred during processing. Please try again.</p>
                <?php if (isset($result['error'])): ?>
                    <p><strong>Error:</strong> <?php echo htmlspecialchars($result['error']); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div style="margin-top: 30px;">
            <a href="upload.php" class="btn">📁 Upload Another File</a>
            <a href="view_data.php?table=<?php echo urlencode($result['table_name']); ?>" class="btn btn-secondary">📋 View Imported Data</a>
        </div>
    </div>
</body>
</html> 