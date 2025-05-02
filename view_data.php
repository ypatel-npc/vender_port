<?php
session_start();

// Include database configuration
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/utils.php';

// Error suppression
// error_reporting(0);
// ini_set('display_errors', 0);

try {
	// Connect to database using the connection function from database.php
	$pdo = get_vendor_db_connection();

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
			font-family: 'Segoe UI', Arial, sans-serif;
			margin: 0;
			padding: 0;
			background: #f8f9fa;
			color: #333;
		}

		.container {
			max-width: 1200px;
			margin: 20px auto;
			background: white;
			padding: 25px;
			border-radius: 10px;
			box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
		}

		.import-info {
			background: #f0f4f8;
			padding: 20px;
			border-radius: 8px;
			margin-bottom: 25px;
			border-left: 4px solid #2196F3;
		}

		.import-info h3 {
			margin-top: 0;
			color: #1565c0;
		}

		table {
			width: 100%;
			border-collapse: collapse;
			margin: 25px 0;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
			border-radius: 8px;
			overflow: hidden;
		}

		th,
		td {
			padding: 14px 10px;
			border: 1px solid #e0e0e0;
			text-align: left;
		}

		th {
			background: #2196F3;
			color: white;
			font-weight: 600;
			position: sticky;
			top: 0;
		}

		tr:nth-child(even) {
			background: #f5f7fa;
		}

		tr:hover {
			background: #e8f4fe;
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
			box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
			transform: translateY(-2px);
		}

		.error {
			color: #721c24;
			background-color: #f8d7da;
			border: 1px solid #f5c6cb;
			padding: 15px;
			border-radius: 6px;
			margin-bottom: 25px;
			font-weight: 500;
		}

		/* Table-based imports list instead of cards */
		.imports-table {
			width: 100%;
			border-collapse: collapse;
			margin: 25px 0;
			box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
			border-radius: 8px;
			overflow: hidden;
		}

		.imports-table th {
			background: #2196F3;
			color: white;
			font-weight: 600;
			padding: 15px;
			text-align: left;
		}

		.imports-table td {
			padding: 15px;
			border-bottom: 1px solid #e0e0e0;
		}

		.imports-table tr:last-child td {
			border-bottom: none;
		}

		.imports-table tr:hover {
			background-color: #e8f4fe;
		}

		.view-btn {
			background: #2196F3;
			color: white;
			padding: 8px 16px;
			border-radius: 4px;
			text-decoration: none;
			display: inline-block;
			text-align: center;
			font-weight: 600;
			transition: all 0.3s ease;
			box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
		}

		.view-btn:hover {
			background: #1976D2;
			box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
		}

		.match-btn {
			background: #FF5722;
			color: white;
			padding: 12px 24px;
			border: none;
			border-radius: 6px;
			text-decoration: none;
			display: inline-block;
			margin: 20px 0;
			cursor: pointer;
			font-weight: 600;
			transition: all 0.3s ease;
			box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
		}

		.match-btn:hover {
			background: #E64A19;
			box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
			transform: translateY(-2px);
		}

		.match-results {
			margin-top: 25px;
			padding: 20px;
			background: #f0f4f8;
			border-radius: 8px;
			border-left: 4px solid #FF5722;
		}

		.match-table {
			width: 100%;
			border-collapse: collapse;
			margin-top: 20px;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
			border-radius: 8px;
			overflow: hidden;
		}

		.match-table th,
		.match-table td {
			padding: 14px 10px;
			border: 1px solid #e0e0e0;
			text-align: left;
		}

		.match-table th {
			background: #FF5722;
			color: white;
			font-weight: 600;
		}

		.match-table tr:nth-child(even) {
			background-color: #fff8f6;
		}

		.match-table tr:hover {
			background-color: #ffede8;
		}

		.action-buttons {
			margin: 15px 0 20px 0;
		}

		.export-btn {
			display: inline-block;
			padding: 10px 20px;
			background-color: #4CAF50;
			color: white;
			text-decoration: none;
			border-radius: 6px;
			font-weight: 600;
			transition: all 0.3s ease;
			box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
		}

		.export-btn:hover {
			background-color: #388E3C;
			box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
			transform: translateY(-2px);
		}

		h2 {
			color: #1565c0;
			border-bottom: 2px solid #e0e0e0;
			padding-bottom: 10px;
			margin-top: 0;
		}

		.data-table-container {
			overflow-x: auto;
			margin: 20px 0;
			border-radius: 8px;
			box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
		}

		.no-data {
			text-align: center;
			padding: 30px;
			color: #666;
			font-style: italic;
			background: #f5f5f5;
			border-radius: 8px;
			margin: 20px 0;
		}

		@media (max-width: 768px) {
			.container {
				padding: 15px;
				margin: 10px;
			}

			th,
			td {
				padding: 10px 8px;
				font-size: 0.9rem;
			}
		}

		.delete-btn {
			background: #f44336;
			color: white;
			padding: 8px 16px;
			border-radius: 4px;
			text-decoration: none;
			display: inline-block;
			text-align: center;
			font-weight: 600;
			transition: all 0.3s ease;
			box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
			margin-left: 8px;
		}

		.delete-btn:hover {
			background: #d32f2f;
			box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
		}

		.action-cell {
			white-space: nowrap;
		}

		.confirm-dialog {
			position: fixed;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background: rgba(0, 0, 0, 0.5);
			display: flex;
			align-items: center;
			justify-content: center;
			z-index: 1000;
			display: none;
		}

		.confirm-box {
			background: white;
			padding: 25px;
			border-radius: 8px;
			max-width: 400px;
			width: 100%;
			box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
		}

		.confirm-box h3 {
			margin-top: 0;
			color: #d32f2f;
		}

		.confirm-buttons {
			display: flex;
			justify-content: flex-end;
			margin-top: 20px;
		}

		.confirm-buttons button {
			padding: 10px 20px;
			border: none;
			border-radius: 4px;
			cursor: pointer;
			font-weight: 600;
			margin-left: 10px;
		}

		.cancel-btn {
			background: #e0e0e0;
		}

		.cancel-btn:hover {
			background: #bdbdbd;
		}

		.confirm-delete-btn {
			background: #f44336;
			color: white;
		}

		.confirm-delete-btn:hover {
			background: #d32f2f;
		}

		.table-actions {
			margin: 20px 0;
			background: #f8f9fa;
			padding: 20px;
			border-radius: 8px;
			border-left: 4px solid #FF5722;
			box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
		}

		.match-form {
			display: flex;
			align-items: center;
			gap: 15px;
		}

		.match-type-select {
			padding: 12px 15px;
			border: 1px solid #ddd;
			border-radius: 6px;
			background-color: white;
			font-size: 15px;
			min-width: 250px;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
			transition: all 0.3s ease;
		}

		.match-type-select:focus {
			border-color: #FF5722;
			outline: none;
			box-shadow: 0 0 0 3px rgba(255, 87, 34, 0.2);
		}

		.match-button {
			background: #FF5722;
			color: white;
			padding: 12px 24px;
			border: none;
			border-radius: 6px;
			display: inline-flex;
			align-items: center;
			gap: 8px;
			font-weight: 600;
			transition: all 0.3s ease;
			box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
			cursor: pointer;
		}

		.match-button:hover {
			background: #E64A19;
			box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
			transform: translateY(-2px);
		}

		.match-button i {
			font-size: 16px;
		}

		@media (max-width: 768px) {
			.match-form {
				flex-direction: column;
				align-items: stretch;
			}
			
			.match-type-select, 
			.match-button {
				width: 100%;
			}
		}
	</style>
</head>

<body>
	<div class="container">
		<a href="upload.php" class="back-btn">Back to Upload</a>
		<a href="view_data.php?nocache=true" class="back-btn">back to list</a>
		<a href="index.php" class="back-btn">home</a>
		<h2>Imported Data</h2>

		<?php if (isset($error)): ?>
			<div class="error"><?php echo htmlspecialchars($error); ?></div>
		<?php elseif (!isset($_GET['table'])): ?>
			<!-- Show list of imports -->
			<?php if (empty($imports)): ?>
				<div class="no-data">
					<p>No imports found. Please upload a CSV file first.</p>
				</div>
			<?php else: ?>
				<table class="imports-table">
					<thead>
						<tr>
							<th>Vendor Name</th>
							<th>Contact Person</th>
							<th>Table Name</th>
							<th>Records</th>
							<th>Import Date</th>
							<th>Action</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($imports as $import): ?>
							<tr>
								<td><?php echo htmlspecialchars($import['vendor_name']); ?></td>
								<td><?php echo htmlspecialchars($import['contact_person']); ?></td>
								<td><?php echo htmlspecialchars($import['imported_table']); ?></td>
								<td><?php echo htmlspecialchars($import['total_records']); ?></td>
								<td><?php echo htmlspecialchars($import['imported_at']); ?></td>
								<td class="action-cell">
									<a href="?table=<?php echo urlencode($import['imported_table']); ?>" class="view-btn">View Data</a>
									<a href="#" class="delete-btn" data-table="<?php echo htmlspecialchars($import['imported_table']); ?>" data-vendor="<?php echo htmlspecialchars($import['vendor_name']); ?>">Delete</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php else: ?>
			<?php if ($import_info): ?>
				<div class="import-info">
					<h3>Import Details</h3>
					<p><strong>Vendor:</strong> <?php echo htmlspecialchars($import_info['vendor_name']); ?></p>
					<p><strong>Contact:</strong> <?php echo htmlspecialchars($import_info['contact_person']); ?></p>
					<p><strong>Import Date:</strong> <?php echo htmlspecialchars($import_info['imported_at']); ?></p>
					<p><strong>Total Records:</strong> <?php echo htmlspecialchars($import_info['total_records']); ?></p>
				</div>
				<div class="table-actions">
					<form action="match_npc.php" method="post" class="match-form">
						<input type="hidden" name="table" value="<?php echo htmlspecialchars($table_name); ?>">
						
						<select name="match_type" class="match-type-select">
							<?php
							// Get the current match type from session or default to '590'
							$current_match_type = isset($_SESSION['match_type']) ? $_SESSION['match_type'] : '590';
							?>
							<option value="590" <?php echo ($current_match_type === '590') ? 'selected' : ''; ?>>Match by 590 (Hollander)</option>
							<option value="software" <?php echo ($current_match_type === 'software') ? 'selected' : ''; ?>>Match by Software</option>
							<option value="hardware" <?php echo ($current_match_type === 'hardware') ? 'selected' : ''; ?>>Match by Hardware</option>
						</select>
						
						<button type="submit" class="match-button">
							<i class="fas fa-exchange-alt"></i> Find Matching with NPC
						</button>
					</form>
				</div>
				<!-- <button id="create-pos-woo" class="btn btn-success" data-table="<?php echo $table_name; ?>">Create POS in WooCommerce</button> -->
			<?php endif; ?>

			<?php if (isset($_GET['matched']) && $_GET['matched'] == '1' && isset($_SESSION['match_results'])): ?>
				<!-- Get match results from session -->
				<?php
				$match_results = $_SESSION['match_results'];
				$match_count = isset($_SESSION['match_count']) ? $_SESSION['match_count'] : count($match_results);

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
					<!-- Add Export to CSV button -->
					<div class="action-buttons">
						<a href="export_csv.php?table=<?php echo urlencode($table_name); ?>" class="export-btn">Export to CSV</a>
					</div>
					<div class="data-table-container">
						<table class="match-table">
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
					</div>

					<!-- Pagination for matched results -->
					<?php render_pagination($match_page, $total_match_pages, $table_name, '&matched=1', 'match_page'); ?>
				</div>
			<?php else: ?>
				<!-- Show original data -->
				<div class="data-table-container">
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
				</div>
			<?php endif; ?>

			<?php if (isset($_GET['matched']) && $_GET['matched'] == '1'): ?>
				<div class="alert alert-success">
					<?php 
					$match_count = isset($_SESSION['match_count']) ? $_SESSION['match_count'] : 0;
					$match_type = isset($_SESSION['match_type']) ? $_SESSION['match_type'] : '590';
					$match_type_label = '';
					
					switch($match_type) {
						case 'hardware': $match_type_label = 'Hardware'; break;
						case 'software': $match_type_label = 'Software'; break;
						case '590': default: $match_type_label = 'Hollander (590)'; break;
					}
					
					echo "Found $match_count matches using $match_type_label matching.";
					?>
				</div>
			<?php endif; ?>

			<?php render_pagination($page, $total_pages, $table_name); ?>
		<?php endif; ?>
	</div>
	<!-- Add this confirmation dialog -->
	<div id="delete-confirm" class="confirm-dialog">
		<div class="confirm-box">
			<h3>Confirm Deletion</h3>
			<p>Are you sure you want to delete the import <strong id="delete-table-name"></strong> and all associated vendor details?</p>
			<p>This action cannot be undone.</p>
			<div class="confirm-buttons">
				<button class="cancel-btn" id="cancel-delete">Cancel</button>
				<button class="confirm-delete-btn" id="confirm-delete">Delete</button>
			</div>
		</div>
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

			// Handle delete button clicks
			const deleteButtons = document.querySelectorAll('.delete-btn');
			const deleteConfirm = document.getElementById('delete-confirm');
			const deleteTableName = document.getElementById('delete-table-name');
			const cancelDelete = document.getElementById('cancel-delete');
			const confirmDelete = document.getElementById('confirm-delete');
			let tableToDelete = '';

			deleteButtons.forEach(button => {
				button.addEventListener('click', function(e) {
					e.preventDefault();
					tableToDelete = this.getAttribute('data-table');
					const vendorName = this.getAttribute('data-vendor');
					deleteTableName.textContent = `${tableToDelete} (${vendorName})`;
					deleteConfirm.style.display = 'flex';
				});
			});

			cancelDelete.addEventListener('click', function() {
				deleteConfirm.style.display = 'none';
			});

			confirmDelete.addEventListener('click', function() {
				// Show loading state
				this.textContent = 'Deleting...';
				this.disabled = true;

				// Make AJAX call to delete the import
				fetch('delete_imports.php', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded',
						},
						body: 'table=' + encodeURIComponent(tableToDelete)
					})
					.then(response => response.json())
					.then(data => {
						if (data.success) {
							// Reload the page to show updated list
							window.location.reload();
						} else {
							alert('Error: ' + data.message);
							deleteConfirm.style.display = 'none';
							confirmDelete.textContent = 'Delete';
							confirmDelete.disabled = false;
						}
					})
					.catch(error => {
						console.error('Error:', error);
						alert('An error occurred while processing your request.');
						deleteConfirm.style.display = 'none';
						confirmDelete.textContent = 'Delete';
						confirmDelete.disabled = false;
					});
			});
		});
	</script>
</body>

</html>

<?php
// Pagination rendering function to avoid code duplication
function render_pagination($current_page, $total_pages, $table_name, $additional_params = '', $page_param = 'page')
{
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
