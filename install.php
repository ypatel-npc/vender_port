<?php
/**
 * Plugin installation script to create required tables
 *
 * @package Vendor_Port
 */

// Include database configuration
require_once __DIR__ . '/config/database.php';

/**
 * Creates the vendor port tables if they don't exist
 *
 * @return bool Success status
 */
function create_vendor_port_tables() {
	try {
		$pdo = get_vendor_db_connection();

		// Create vendors table
		$create_vendors = '
		CREATE TABLE IF NOT EXISTS `vendors` (
			`id` INT AUTO_INCREMENT PRIMARY KEY,
			`vendor_name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
			`contact_person` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
			`email` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
			`phone` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
			`address` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
			`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_520_ci;';

		// Create import_history table
		$create_import_history = '
		CREATE TABLE IF NOT EXISTS `import_history` (
			`id` INT AUTO_INCREMENT PRIMARY KEY,
			`vendor_id` INT NOT NULL,
			`imported_table` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
			`file_name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
			`total_records` INT NOT NULL,
			`imported_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_520_ci;';

		// Execute table creation
		$pdo->exec( $create_vendors );
		$pdo->exec( $create_import_history );

		// Create a template for imported data tables
		$imported_data_template = '
		CREATE TABLE IF NOT EXISTS `imported_data_template` (
			`id` INT AUTO_INCREMENT PRIMARY KEY,
			`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_520_ci;';

		$pdo->exec( $imported_data_template );

		return true;
	} catch ( PDOException $e ) {
		error_log( 'Failed to create tables: ' . $e->getMessage() );
		return false;
	}
}

/**
 * Updates table collations if needed
 *
 * @return bool Success status
 */
function update_table_collations() {
	try {
		$pdo = get_vendor_db_connection();

		// Update vendors table collation
		$update_vendors = '
		ALTER TABLE `vendors` 
		CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;';

		// Update import_history table collation
		$update_import_history = '
		ALTER TABLE `import_history` 
		CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;';

		// Execute collation updates
		$pdo->exec( $update_vendors );
		$pdo->exec( $update_import_history );

		return true;
	} catch ( PDOException $e ) {
		error_log( 'Failed to update table collations: ' . $e->getMessage() );
		return false;
	}
}

/**
 * Checks if required tables exist in the database
 *
 * @return bool True if all required tables exist
 */
function tables_exist() {
	try {
		$pdo = get_vendor_db_connection();
		$required_tables = ['vendors', 'import_history', 'imported_data_template'];
		
		foreach ($required_tables as $table) {
			$stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ?");
			$stmt->execute([DB_NAME, $table]);
			if (!$stmt->fetch()) {
				return false;
			}
		}
		
		return true;
	} catch (PDOException $e) {
		error_log('Error checking tables: ' . $e->getMessage());
		return false;
	}
}

/**
 * Checks database status and returns appropriate message
 *
 * @return array Status information with type and message
 */
function check_database_status() {
	$tables_existed = tables_exist();
	$status = [];
	
	if ($tables_existed) {
		// Tables already existed
		$status['type'] = 'status-success';
		$status['message'] = 'Database tables are ready.';
	} else {
		// Try to create tables
		$created = create_vendor_port_tables();
		
		if ($created) {
			$status['type'] = 'status-success';
			$status['message'] = 'Database tables were successfully created.';
		} else {
			$status['type'] = 'status-error';
			$status['message'] = 'Failed to create database tables. Please check error logs.';
		}
	}
	
	return $status;
}

/**
 * Activates the vendor port by creating necessary tables
 *
 * @return bool Success status
 */
function activate_vendor_port() {
	if ( ! create_vendor_port_tables() ) {
		// Handle installation failure
		error_log( 'Failed to install vendor port tables' );
		return false;
	}
	
	// Update existing tables if needed
	update_table_collations();
	
	return true;
}

// Auto-run the activation when this file is included
activate_vendor_port(); 