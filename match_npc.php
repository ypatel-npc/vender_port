<?php
session_start();

// Test logging
log_debug("=== Starting new matching process ===");

// Custom error logging function
function log_debug($message) {
    $log_file = __DIR__ . '/debug.log';
    // Try to create log file if it doesn't exist
    if (!file_exists($log_file)) {
        touch($log_file);
        chmod($log_file, 0666); // Set read/write permissions
    }

    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message\n";
    
    // Add error handling for file writing
    if (file_put_contents($log_file, $log_message, FILE_APPEND) === false) {
        error_log("Failed to write to debug log file: " . $log_file);
        error_log("Message was: " . $log_message);
    }
}

// Database configurations
define('VENDOR_DB_HOST', 'localhost');
define('VENDOR_DB_USER', 'root');
define('VENDOR_DB_PASS', '');
define('VENDOR_DB_NAME', 'vendor_port');

// NPC Database configurations
define('NPC_DB1_HOST', 'localhost');
define('NPC_DB1_USER', 'root');
define('NPC_DB1_PASS', '');
define('NPC_DB1_NAME', 'test_play');

// define('NPC_DB2_HOST', 'localhost');
// define('NPC_DB2_USER', 'root');
// define('NPC_DB2_PASS', '');
// define('NPC_DB2_NAME', 'npc_database2');

// define('NPC_DB3_HOST', 'localhost');
// define('NPC_DB3_USER', 'root');
// define('NPC_DB3_PASS', '');
// define('NPC_DB3_NAME', 'npc_database3');

try {
    // Debug log the table name
    // log_debug("Attempting to match table: " . $_POST['table']);
    
    if (!isset($_POST['table'])) {
        throw new Exception('No table specified');
    }

    $table_name = $_POST['table'];
    
    // Connect to vendor database
    $vendor_db = new PDO(
        "mysql:host=" . VENDOR_DB_HOST . ";dbname=" . VENDOR_DB_NAME . ";charset=utf8mb4",
        VENDOR_DB_USER,
        VENDOR_DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Connect to NPC databases
    $npc_db1 = new PDO(
        "mysql:host=" . NPC_DB1_HOST . ";dbname=" . NPC_DB1_NAME . ";charset=utf8mb4",
        NPC_DB1_USER,
        NPC_DB1_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // $npc_db2 = new PDO(
    //     "mysql:host=" . NPC_DB2_HOST . ";dbname=" . NPC_DB2_NAME . ";charset=utf8mb4",
    //     NPC_DB2_USER,
    //     NPC_DB2_PASS,
    //     [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    // );

    // $npc_db3 = new PDO(
    //     "mysql:host=" . NPC_DB3_HOST . ";dbname=" . NPC_DB3_NAME . ";charset=utf8mb4",
    //     NPC_DB3_USER,
    //     NPC_DB3_PASS,
    //     [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    // );

    // Verify table exists
    $stmt = $vendor_db->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ?");
    $stmt->execute([VENDOR_DB_NAME, $table_name]);
    if (!$stmt->fetch()) {
        throw new Exception("Invalid table");
    }

    // Get all matches from each database
    $all_matches = [];
    $total_matches = 0;

    // Query NPC Database 1
    $match_query1 = "
select DISTINCT i.*, h.hollander_no as npc_holander , i2.inventory_no as npc_hardware , sds.Need_3mo as 3_month_need , sds.Need_6mo as 6_month_need
from " . VENDOR_DB_NAME . ".`$table_name`  i 
inner join `test_play`.hollander h on h.hollander_no = i.`590` 
inner join `test_play`.inventory_hollander_map ihm on ihm.hollander_id = h.hollander_id
inner join `test_play`.inventory i2 on i2.inventory_id = ihm.inventory_id
left join  `npcwebsite`.sales_demand_summary sds on sds.SKU COLLATE utf8mb4_unicode_520_ci = i2.inventory_no COLLATE utf8mb4_unicode_520_ci
where sds.Need_3mo > 0 or sds.Need_6mo > 0
    ";
    $matches1 = $npc_db1->query($match_query1)->fetchAll(PDO::FETCH_ASSOC);
    log_debug("Found matches: " . count($matches1));
    
    $all_matches = array_merge($all_matches, $matches1);
    log_debug("Total matches: " . count($all_matches));
    
    // Query NPC Database 2
    // $match_query2 = "
    //     SELECT 
    //         i.col_590 as vendor_id,
    //         n2.id as npc_db2_id,
    //         n2.name as npc_db2_name,
    //         'NPC Database 2' as source_db
    //     FROM " . VENDOR_DB_NAME . ".`$table_name` i
    //     INNER JOIN " . NPC_DB2_NAME . ".table2 n2 ON i.col_590 = n2.matching_column
    // ";
    // $matches2 = $npc_db2->query($match_query2)->fetchAll(PDO::FETCH_ASSOC);
    // $all_matches = array_merge($all_matches, $matches2);

    // // Query NPC Database 3
    // $match_query3 = "
    //     SELECT 
    //         i.col_590 as vendor_id,
    //         n3.id as npc_db3_id,
    //         n3.name as npc_db3_name,
    //         'NPC Database 3' as source_db
    //     FROM " . VENDOR_DB_NAME . ".`$table_name` i
    //     INNER JOIN " . NPC_DB3_NAME . ".table3 n3 ON i.col_590 = n3.matching_column
    // ";
    // $matches3 = $npc_db3->query($match_query3)->fetchAll(PDO::FETCH_ASSOC);
    // $all_matches = array_merge($all_matches, $matches3);

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