<?php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'vendor_port');

try {
    // Connect to database first
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    if (isset($_GET['table'])) {
        // Debug: Check if table parameter exists
        if (!isset($_GET['table'])) {
            throw new Exception('No table parameter provided');
        }

        $table_name = $_GET['table'];
        
        // Debug: Check if table exists
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ?");
        $stmt->execute([DB_NAME, $table_name]);
        if (!$stmt->fetch()) {
            throw new Exception("Table '$table_name' does not exist in database");
        }

        // Get import details
        $stmt = $pdo->prepare("SELECT v.vendor_name, v.contact_person, ih.imported_at, ih.total_records 
                              FROM import_history ih 
                              JOIN vendors v ON v.id = ih.vendor_id 
                              WHERE ih.imported_table = ?");
        $stmt->execute([$table_name]);
        $import_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$import_info) {
            throw new Exception("No import history found for table '$table_name'");
        }

        // Get table columns
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table_name`");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Setup pagination with proper sanitization
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $per_page = 50;
        $offset = ($page - 1) * $per_page;

        // Prepare table name safely
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `" . str_replace('`', '``', $table_name) . "`");
        $stmt->execute();
        $total_rows = $stmt->fetchColumn();
        $total_pages = ceil($total_rows / $per_page);

        // Get data with pagination using prepared statement
        $stmt = $pdo->prepare("SELECT * FROM `" . str_replace('`', '``', $table_name) . "` LIMIT :offset, :per_page");
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Get list of all imported tables with their details
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

} catch (Exception $e) {
    // Log the error
    error_log("Error in view_data.php: " . $e->getMessage());
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Imported Data</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .import-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px 8px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background: #f5f5f5;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        .pagination {
            margin: 20px 0;
            text-align: center;
        }
        .pagination a {
            padding: 8px 12px;
            margin: 0 5px;
            border: 1px solid #ddd;
            text-decoration: none;
            color: #333;
            border-radius: 4px;
        }
        .pagination a.active {
            background: #4CAF50;
            color: white;
            border-color: #4CAF50;
        }
        .pagination a:hover:not(.active) {
            background: #f5f5f5;
        }
        .back-btn {
            background: #2196F3;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }
        .error {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
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
        
        .match-btn {
            background: #FF5722;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
            margin: 20px 0;
            cursor: pointer;
        }
        
        .match-btn:hover {
            background: #F4511E;
        }
        
        .match-results {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .match-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .match-table th,
        .match-table td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
        }
        
        .match-table th {
            background-color: #f4f4f4;
            font-weight: bold;
        }
        
        .match-table tr:nth-child(even) {
            background-color: #f8f8f8;
        }
        
        .match-table tr:hover {
            background-color: #f0f0f0;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="upload.php" class="back-btn">Back to Upload</a>
        
        <h2>Imported Data</h2>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif (!isset($_GET['table'])): ?>
            <!-- Show list of imports -->
            <div class="imports-list">
                <?php if (empty($imports)): ?>
                    <p>No imports found. Please upload a CSV file first.</p>
                <?php else: ?>
                    <?php foreach ($imports as $import): ?>
                        <div class="import-card">
                            <div class="import-info">
                                <h3><?php echo htmlspecialchars($import['vendor_name']); ?></h3>
                                <p>Contact: <?php echo htmlspecialchars($import['contact_person']); ?></p>
                                <p>Records: <?php echo htmlspecialchars($import['total_records']); ?></p>
                                <p class="import-date">Imported: <?php echo htmlspecialchars($import['imported_at']); ?></p>
                            </div>
                            <a href="?table=<?php echo urlencode($import['imported_table']); ?>" class="view-btn">View Data</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php if ($import_info): ?>
            <div class="import-info">
                <h3>Import Details</h3>
                <p>Vendor: <?php echo htmlspecialchars($import_info['vendor_name']); ?></p>
                <p>Contact: <?php echo htmlspecialchars($import_info['contact_person']); ?></p>
                <p>Import Date: <?php echo htmlspecialchars($import_info['imported_at']); ?></p>
                <p>Total Records: <?php echo htmlspecialchars($import_info['total_records']); ?></p>
            </div>
            <form action="match_npc.php" method="POST">
                <input type="hidden" name="table" value="<?php echo htmlspecialchars($table_name); ?>">
                
                <!-- Add dropdown menu for category selection -->
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="category" style="display: block; margin-bottom: 5px; font-weight: bold;">Select Category:</label>
                    <select name="category" id="category" style="padding: 8px; border-radius: 4px; border: 1px solid #ddd; width: 200px;">
                        <option value="">-- Select Category --</option>
                        <option value="590">590</option>
                        <option value="hardware">Hardware</option>
                        <option value="software">Software</option>
                    </select>
                </div>
                
                <button type="submit" class="match-btn">Find Matching with NPC</button>
            </form>
            <button id="create-pos-woo" class="btn btn-success" data-table="<?php echo $table_name; ?>">Create POS in WooCommerce</button>
            <?php endif; ?>

            <?php if (isset($_GET['matched']) && $_GET['matched'] == '1' && isset($_SESSION['match_results'])): ?>
                <!-- Get match results from session -->
                <?php
                $match_results = $_SESSION['match_results'];
                $match_count = $_SESSION['match_count'];
                
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
                    <table class="data-table">
                        <!-- Table headers for matched results -->
                        <tr>
                            <?php foreach (array_keys($paginated_matches[0]) as $header): ?>
                            <th><?php echo htmlspecialchars($header); ?></th>
                            <?php endforeach; ?>
                        </tr>
                        
                        <!-- Display matched data -->
                        <?php foreach ($paginated_matches as $row): ?>
                        <tr>
                            <?php foreach ($row as $value): ?>
                            <td><?php echo htmlspecialchars($value); ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    
                    <!-- Pagination for matched results -->
                    <?php render_pagination($match_page, $total_match_pages, $table_name, '&matched=1', 'match_page'); ?>
                </div>
            <?php else: ?>
                <!-- Show original data -->
                <table>
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
            <?php endif; ?>

            <?php render_pagination($page, $total_pages, $table_name); ?>
        <?php endif; ?>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const createPosButton = document.getElementById('create-pos-woo');
        if (createPosButton) {
            createPosButton.addEventListener('click', function() {
                const tableName = this.getAttribute('data-table');
                createPosInWooCommerce(tableName);
            });
        }
        
        function createPosInWooCommerce(tableName) {
            // Show loading state
            const button = document.getElementById('create-pos-woo');
            const originalText = button.textContent;
            button.textContent = 'Processing...';
            button.disabled = true;
            
            // Make AJAX call to the server
            fetch('create_pos_woo.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'table=' + encodeURIComponent(tableName)
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                // Reset button
                button.textContent = originalText;
                button.disabled = false;
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while processing your request.');
                // Reset button
                button.textContent = originalText;
                button.disabled = false;
            });
        }
    });
    </script>
</body>
</html>

<?php
// Pagination rendering function to avoid code duplication
function render_pagination($current_page, $total_pages, $table_name, $additional_params = '', $page_param = 'page') {
    // Only show a window of pages around current page
    $window = 5;
    $start_page = max(1, $current_page - $window);
    $end_page = min($total_pages, $current_page + $window);
    
    echo '<div class="pagination">';
    
    // First page and previous
    if ($current_page > 1) {
        echo '<a href="?table=' . urlencode($table_name) . $additional_params . '&' . $page_param . '=1">First</a>';
        echo '<a href="?table=' . urlencode($table_name) . $additional_params . '&' . $page_param . '=' . ($current_page - 1) . '">Prev</a>';
    }
    
    // Page numbers
    for ($i = $start_page; $i <= $end_page; $i++) {
        echo '<a href="?table=' . urlencode($table_name) . $additional_params . '&' . $page_param . '=' . $i . '" ' . 
             ($current_page === $i ? 'class="active"' : '') . '>' . $i . '</a>';
    }
    
    // Next and last page
    if ($current_page < $total_pages) {
        echo '<a href="?table=' . urlencode($table_name) . $additional_params . '&' . $page_param . '=' . ($current_page + 1) . '">Next</a>';
        echo '<a href="?table=' . urlencode($table_name) . $additional_params . '&' . $page_param . '=' . $total_pages . '">Last</a>';
    }
    
    echo '</div>';
} 