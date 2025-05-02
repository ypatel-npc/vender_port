<?php
session_start();

// Include database configuration
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/utils.php';

// Test logging
log_debug("=== Starting new matching process ===");

// Custom error logging function
// function log_debug($message) {
//     $log_file = __DIR__ . '/debug.log';
//     // Try to create log file if it doesn't exist
//     if (!file_exists($log_file)) {
//         touch($log_file);
//         chmod($log_file, 0666); // Set read/write permissions
//     }

//     $timestamp = date('Y-m-d H:i:s');
//     $log_message = "[$timestamp] $message\n";
    
//     // Add error handling for file writing
//     if (file_put_contents($log_file, $log_message, FILE_APPEND) === false) {
//         error_log("Failed to write to debug log file: " . $log_file);
//         error_log("Message was: " . $log_message);
//     }
// }

try {
    // Debug log the table name
    if (!isset($_POST['table'])) {
        throw new Exception('No table specified');
    }

    $table_name = $_POST['table'];
    
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

    // Get all matches from each database
    $all_matches = [];
    $total_matches = 0;

    // Query NPC Database 1
    $match_query1 = "
select DISTINCT i.*, h.hollander_no as npc_holander, i2.inventory_no as npc_hardware, 
sds.Need_3mo as 3_month_need, sds.Need_6mo as 6_month_need
from " . DB_NAME . ".`$table_name` i 
inner join `" . NPC_DB1_NAME . "`.hollander h on h.hollander_no = i.`590` 
inner join `" . NPC_DB1_NAME . "`.inventory_hollander_map ihm on ihm.hollander_id = h.hollander_id
inner join `" . NPC_DB1_NAME . "`.inventory i2 on i2.inventory_id = ihm.inventory_id
left join `" . NPC_WEBSITE_NAME . "`.sales_demand_summary sds on sds.SKU COLLATE utf8mb4_unicode_520_ci = i2.inventory_no COLLATE utf8mb4_unicode_520_ci
where sds.Need_3mo > 0 or sds.Need_6mo > 0
    ";
    $matches1 = $npc_db1->query($match_query1)->fetchAll(PDO::FETCH_ASSOC);
    log_debug("Found matches: " . count($matches1));
    
    $all_matches = array_merge($all_matches, $matches1);
    log_debug("Total matches: " . count($all_matches));
    
    // Store results in session
    $_SESSION['match_results'] = $all_matches;
    $_SESSION['match_count'] = count($all_matches);
    
    // Redirect back with results
    header("Location: view_data.php?table=" . urlencode($table_name) . "&matched=1");
    exit();

} catch (Exception $e) {
    log_debug("Match Error: " . $e->getMessage());
    header("Location: view_data.php?table=" . urlencode($_POST['table']) . "&error=" . urlencode($e->getMessage()));
    exit();
}
?> 