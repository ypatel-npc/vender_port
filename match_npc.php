<?php
session_start();

// Include database configuration
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/utils.php';

// Test logging
log_debug('=== Starting new matching process ===');

try {
    // Debug log the table name
    if (!isset($_POST['table'])) {
        throw new Exception('No table specified');
    }

    $table_name = $_POST['table'];
    log_debug("Processing table: " . $table_name);
    
    // Get match type (default to 590 if not specified)
    $match_type = isset($_POST['match_type']) ? $_POST['match_type'] : '590';
    log_debug("Match type selected: " . $match_type);
    
    // Get database connections
    $vendor_db = get_vendor_db_connection();
    $npc_db1 = get_npc_db1_connection();
    $npc_website_db = get_npc_website_connection();
    log_debug("Database connections established");

    // Verify table exists
    $stmt = $vendor_db->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ?");
    $stmt->execute([DB_NAME, $table_name]);
    if (!$stmt->fetch()) {
        log_debug("Table verification failed: Table '$table_name' does not exist");
        throw new Exception("Invalid table");
    }
    log_debug("Table verification successful");

    // Get all matches from each database
    $all_matches = [];
    $total_matches = 0;

    // Build query based on match type
    log_debug("Building query for match type: $match_type");
    switch ($match_type) {
		case '591':
			log_debug("Using 591 matching query");
			$match_query = "
            SELECT DISTINCT i.*, h.hollander_no as npc_holander, i2.inventory_no as npc_hardware, 
            sds.Need_3mo as 3_month_need, sds.Need_6mo as 6_month_need,
            '591 Match' as match_type
            FROM " . DB_NAME . ".`$table_name` i 
            INNER JOIN `" . NPC_DB1_NAME . "`.hollander h ON h.hollander_no = i.`590` 
            INNER JOIN `" . NPC_DB1_NAME . "`.inventory_hollander_map ihm ON ihm.hollander_id = h.hollander_id
            INNER JOIN `" . NPC_DB1_NAME . "`.inventory i2 ON i2.inventory_id = ihm.inventory_id
            LEFT JOIN `" . NPC_WEBSITE_NAME . "`.sales_demand_summary sds ON sds.SKU COLLATE utf8mb4_unicode_520_ci = i2.inventory_no COLLATE utf8mb4_unicode_520_ci
            WHERE (h.hollander_no LIKE '591-%') AND (sds.Need_3mo > 0 OR sds.Need_6mo > 0)
			";
			break;

		
        case 'hardware':
            log_debug("Using hardware matching query");
			$match_query = "
            SELECT DISTINCT i.*, 
			i2.inventory_no as npc_hardware,
            sds.Need_3mo as 3_month_need, sds.Need_6mo as 6_month_need,
            'Hardware Match' as match_type
            FROM " . DB_NAME . ".`$table_name` i 
			inner join `" . NPC_DB1_NAME . "`.inventory AS i2 on
			i.`590` = i2.inventory_no
			left join `" . NPC_WEBSITE_NAME . "`.sales_demand_summary sds on
			sds.SKU COLLATE utf8mb4_unicode_520_ci = i2.inventory_no COLLATE utf8mb4_unicode_520_ci
			WHERE (sds.Need_3mo > 0 OR sds.Need_6mo > 0)



            ";
            break;
            
        case 'software':
            log_debug("Using software matching query");
            $match_query = "
            SELECT DISTINCT i.*, s.mfr_software_no as npc_software,
            i2.inventory_no as npc_hardware,
            sds.Need_3mo as 3_month_need, sds.Need_6mo as 6_month_need,
            'Software Match' as match_type
            FROM " . DB_NAME . ".`$table_name` i 
            
			inner join `" . NPC_DB1_NAME . "`.software as s on
			i.`590` = s.mfr_software_no
			inner join `" . NPC_DB1_NAME . "`.inventory AS i2 on
			s.inventory_id = i2.inventory_id
			left join `" . NPC_WEBSITE_NAME . "`.sales_demand_summary sds on
			sds.SKU COLLATE utf8mb4_unicode_520_ci = i2.inventory_no COLLATE utf8mb4_unicode_520_ci
			WHERE (sds.Need_3mo > 0 OR sds.Need_6mo > 0)



            ";
            break;
            
        case '590':
        default:
            log_debug("Using 590 (default) matching query");
            $match_query = "
            SELECT DISTINCT i.*, h.hollander_no as npc_holander, i2.inventory_no as npc_hardware, 
            sds.Need_3mo as 3_month_need, sds.Need_6mo as 6_month_need,
            '590 Match' as match_type
            FROM " . DB_NAME . ".`$table_name` i 
            INNER JOIN `" . NPC_DB1_NAME . "`.hollander h ON h.hollander_no = i.`590` 
            INNER JOIN `" . NPC_DB1_NAME . "`.inventory_hollander_map ihm ON ihm.hollander_id = h.hollander_id
            INNER JOIN `" . NPC_DB1_NAME . "`.inventory i2 ON i2.inventory_id = ihm.inventory_id
            LEFT JOIN `" . NPC_WEBSITE_NAME . "`.sales_demand_summary sds ON sds.SKU COLLATE utf8mb4_unicode_520_ci = i2.inventory_no COLLATE utf8mb4_unicode_520_ci
            WHERE (sds.Need_3mo > 0 OR sds.Need_6mo > 0)
            ";

			// for safty
// 			$match_query1 = "
// select DISTINCT i.*, h.hollander_no as npc_holander , i2.inventory_no as npc_hardware , sds.Need_3mo as 3_month_need , sds.Need_6mo as 6_month_need
// from " . VENDOR_DB_NAME . ".`$table_name`  i 
// inner join `" . NPC_DB1_NAME . "`.hollander h on h.hollander_no = i.`590` 
// inner join `test_play`.inventory_hollander_map ihm on ihm.hollander_id = h.hollander_id
// inner join `test_play`.inventory i2 on i2.inventory_id = ihm.inventory_id
// left join  `npcwebsite`.sales_demand_summary sds on sds.SKU COLLATE utf8mb4_unicode_520_ci = i2.inventory_no COLLATE utf8mb4_unicode_520_ci
// where sds.Need_3mo > 0 or sds.Need_6mo > 0
//     ";
            break;
    }
    
    // Execute the query
    log_debug("Executing match query: " . substr($match_query, 0, 900) . "...");
    try {
        $result = $npc_db1->query($match_query);
        if (!$result) {
            $error_info = $npc_db1->errorInfo();
            log_debug("Query execution failed: " . $error_info[2]);
            throw new Exception("Database query failed: " . $error_info[2]);
        }
        $matches = $result->fetchAll(PDO::FETCH_ASSOC);
        log_debug("Query executed successfully");
    } catch (PDOException $e) {
        log_debug("PDO Exception during query execution: " . $e->getMessage());
        throw new Exception("Database error: " . $e->getMessage());
    }
    
    log_debug("Found matches: " . count($matches));
    
    $all_matches = $matches;
    log_debug("Total matches: " . count($all_matches));
    
    // Store results in session
    $_SESSION['match_results'] = $all_matches;
    $_SESSION['match_count'] = count($all_matches);
    $_SESSION['match_type'] = $match_type;
    log_debug("Results stored in session");
    
    // Redirect back with results
    log_debug("Redirecting to results page");
    header("Location: view_data.php?table=" . urlencode($table_name) . "&matched=1&type=" . urlencode($match_type));
    exit();

} catch (Exception $e) {
    log_debug("Match Error: " . $e->getMessage());
    log_debug("Stack trace: " . $e->getTraceAsString());
    
    // If it's a database-related exception, log more details
    if ($e instanceof PDOException && isset($npc_db1)) {
        $error_info = $npc_db1->errorInfo();
        log_debug("Database error code: " . $error_info[0]);
        log_debug("Database error message: " . $error_info[2]);
    }
    
    header("Location: view_data.php?table=" . urlencode($_POST['table']) . "&error=" . urlencode($e->getMessage()));
    exit();
}
?> 