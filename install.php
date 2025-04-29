<?php
/**
 * Plugin installation script to create required tables
 */

function create_vendor_port_tables() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        // Create vendors table
        $create_vendors = "
        CREATE TABLE IF NOT EXISTS `vendors` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `vendor_name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
            `contact_person` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
            `email` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
            `phone` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
            `address` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_520_ci;";

        // Create import_history table
        $create_import_history = "
        CREATE TABLE IF NOT EXISTS `import_history` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `vendor_id` INT NOT NULL,
            `imported_table` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
            `file_name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
            `total_records` INT NOT NULL,
            `imported_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_520_ci;";

        // Execute table creation
        $pdo->exec($create_vendors);
        $pdo->exec($create_import_history);

        // Create a template for imported data tables
        $imported_data_template = "
        CREATE TABLE IF NOT EXISTS `imported_data_template` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_520_ci;";

        $pdo->exec($imported_data_template);

        return true;
    } catch (PDOException $e) {
        error_log("Failed to create tables: " . $e->getMessage());
        return false;
    }
}

// Function to modify existing tables if needed
function update_table_collations() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        // Update vendors table collation
        $update_vendors = "
        ALTER TABLE `vendors` 
        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;";

        // Update import_history table collation
        $update_import_history = "
        ALTER TABLE `import_history` 
        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;";

        // Execute collation updates
        $pdo->exec($update_vendors);
        $pdo->exec($update_import_history);

        return true;
    } catch (PDOException $e) {
        error_log("Failed to update table collations: " . $e->getMessage());
        return false;
    }
}

// Usage in plugin activation hook
function activate_vendor_port() {
    if (!create_vendor_port_tables()) {
        // Handle installation failure
        error_log("Failed to install vendor port tables");
        return false;
    }
    
    // Update existing tables if needed
    update_table_collations();
    
    return true;
} 