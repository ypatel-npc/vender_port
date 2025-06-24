<?php
/**
 * Preview Page
 * 
 * Shows the user's mapping selections before processing the CSV
 */
session_start();

// Redirect if no mapping data
if (!isset($_SESSION['mapping']) || !isset($_SESSION['csv_file'])) {
    header('Location: upload.php');
    exit();
}

$mapping = $_SESSION['mapping'];
$csv_file = $_SESSION['csv_file'];

// Get CSV headers for display
$csv_headers = $_SESSION['csv_headers'];

// Function to get sample data from CSV
function get_sample_data($file_path, $mapping, $sample_rows = 3) {
    $sample_data = [];
    
    error_log("DEBUG: get_sample_data called with mapping: " . json_encode($mapping));
    
    if (($handle = fopen($file_path, 'r')) !== false) {
        // Skip header row
        fgetcsv($handle);
        
        $row_count = 0;
        while (($data = fgetcsv($handle)) !== false && $row_count < $sample_rows) {
            error_log("DEBUG: Processing row " . $row_count . " with " . count($data) . " columns");
            
            $mapped_row = [];
            $has_590_field = false;
            
            foreach ($mapping as $field => $csv_index) {
                $value = isset($data[$csv_index]) ? $data[$csv_index] : '';
                error_log("DEBUG: Field '$field' (column $csv_index) = '$value'");
                error_log("DEBUG: Field type: " . gettype($field) . ", length: " . strlen($field));
                error_log("DEBUG: Field comparison - field === '590': " . ($field === '590' ? 'TRUE' : 'FALSE'));
                error_log("DEBUG: Field comparison - field == '590': " . ($field == '590' ? 'TRUE' : 'FALSE'));
                error_log("DEBUG: Field comparison - trim(field) === '590': " . (trim($field) === '590' ? 'TRUE' : 'FALSE'));
                
                // Apply 590 extraction for the 590 field
                if (trim($field) === '590' && !empty($value)) {
                    error_log("DEBUG: Applying 590 extraction to: '$value'");
                    require_once __DIR__ . '/utils.php';
                    
                    // Clean the value (remove newlines and extra spaces)
                    $clean_value = trim(str_replace(["\n", "\r"], ' ', $value));
                    
                    $extraction_result = advanced_590_extraction($clean_value);
                    $mapped_row[$field] = $extraction_result['extracted'];
                    $mapped_row['original_description'] = $clean_value;
                    $has_590_field = true;
                    
                    error_log("DEBUG: 590 extraction result - Original: '$clean_value' -> Extracted: '" . $extraction_result['extracted'] . "'");
                } else {
                    $mapped_row[$field] = $value;
                    if (trim($field) === '590') {
                        error_log("DEBUG: 590 field found but value is empty or condition failed");
                    }
                }
            }
            
            // If 590 field is mapped but not found in this row, set empty original_description
            if (isset($mapping['590']) && !$has_590_field) {
                $mapped_row['original_description'] = '';
                error_log("DEBUG: 590 field mapped but not found in row, setting empty original_description");
            }
            
            error_log("DEBUG: Final mapped row: " . json_encode($mapped_row));
            $sample_data[] = $mapped_row;
            $row_count++;
        }
        fclose($handle);
    }
    
    return $sample_data;
}

$sample_data = get_sample_data($csv_file, $mapping);

// Debug output
echo "<!-- DEBUG INFO -->\n";
echo "<!-- Mapping: " . json_encode($mapping) . " -->\n";
echo "<!-- Sample data count: " . count($sample_data) . " -->\n";
if (!empty($sample_data)) {
    echo "<!-- First row: " . json_encode($sample_data[0]) . " -->\n";
}
echo "<!-- END DEBUG INFO -->\n";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview Mapping</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        
        .preview-container {
            max-width: 1000px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 20px;
        }
        
        .header h1 {
            color: #333;
            margin: 0;
        }
        
        .mapping-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 30px;
            border-left: 4px solid #4CAF50;
        }
        
        .mapping-summary h3 {
            margin-top: 0;
            color: #2E7D32;
        }
        
        .field-mapping {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 10px;
            margin: 10px 0;
            padding: 10px;
            background: white;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
        }
        
        .field-name {
            font-weight: bold;
            color: #333;
        }
        
        .csv-column {
            color: #666;
            font-family: monospace;
        }
        
        .sample-data {
            margin-top: 30px;
        }
        
        .sample-data h3 {
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        
        .sample-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .sample-table th,
        .sample-table td {
            border: 1px solid #ddd;
            padding: 12px 8px;
            text-align: left;
        }
        
        .sample-table th {
            background: #4CAF50;
            color: white;
            font-weight: bold;
        }
        
        .sample-table tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        .sample-table tr:hover {
            background: #f0f0f0;
        }
        
        .action-buttons {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        
        .btn {
            padding: 12px 24px;
            margin: 0 10px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #4CAF50;
            color: white;
        }
        
        .btn-primary:hover {
            background: #45a049;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .info-box {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .info-box h4 {
            margin-top: 0;
            color: #1976d2;
        }
    </style>
</head>
<body>
    <div class="preview-container">
        <div class="header">
            <h1>Preview Your Mapping</h1>
            <p>Review your column mappings and sample data before processing</p>
        </div>
        
        <div class="info-box">
            <h4>📋 What happens next?</h4>
            <p>After you confirm, the system will:</p>
            <ul>
                <li>Process your CSV file with the selected mappings</li>
                <li>Extract 590 numbers from complex strings (where applicable)</li>
                <li>Import the data into the database</li>
                <li>Show you the results</li>
            </ul>
        </div>
        
        <div class="mapping-summary">
            <h3>📊 Your Column Mappings</h3>
            <?php foreach ($mapping as $field => $csv_index): ?>
                <div class="field-mapping">
                    <div class="field-name"><?php echo htmlspecialchars(ucfirst($field)); ?></div>
                    <div class="csv-column">
                        Column <?php echo $csv_index; ?>: "<?php echo htmlspecialchars($csv_headers[$csv_index]); ?>"
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="sample-data">
            <h3>👀 Sample Data Preview</h3>
            <p>Here's how your data will look after processing (first 3 rows):</p>
            
            <?php if (!empty($sample_data)): ?>
                <table class="sample-table">
                    <thead>
                        <tr>
                            <?php if (isset($mapping['590'])): ?>
                                <th>590 (Extracted)</th>
                                <th>Original Description</th>
                            <?php else: ?>
                                <?php foreach (array_keys($sample_data[0]) as $field): ?>
                                    <th><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $field))); ?></th>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sample_data as $row): ?>
                            <tr>
                                <?php if (isset($mapping['590'])): ?>
                                    <td style="background: #e8f5e9; font-weight: bold; color: #155724;">
                                        <?php echo htmlspecialchars(isset($row['590']) ? $row['590'] : ''); ?>
                                    </td>
                                    <td style="background: #fff3cd; color: #856404;">
                                        <?php echo htmlspecialchars(isset($row['original_description']) ? $row['original_description'] : ''); ?>
                                    </td>
                                <?php else: ?>
                                    <?php foreach ($row as $field => $value): ?>
                                        <td><?php echo htmlspecialchars($value); ?></td>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: #666; font-style: italic;">No sample data available.</p>
            <?php endif; ?>
        </div>

        <div class="action-buttons">
            <a href="mapping.php" class="btn btn-secondary">← Back to Mapping</a>
            <a href="progress.php" class="btn btn-primary">✅ Start Processing</a>
        </div>
    </div>
</body>
</html>