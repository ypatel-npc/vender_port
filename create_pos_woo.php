<?php

/**
 * Create POS in WooCommerce with ATUM POS integration
 *
 * @package CSV_Import
 */

// Include utility functions
require_once __DIR__ . '/utils.php';

session_start();

// Check if table parameter is provided
if (!isset($_POST['table']) || empty($_POST['table'])) {
	header('Content-Type: application/json');
	echo json_encode(['success' => false, 'message' => 'Missing required parameter: table']);
	exit;
}

log_debug("create_pos_woo.php");
log_debug("post: " . print_r($_POST, true));

// Extract unique npc_hardware values from session data
$unique_hardware = [];
$hardware_with_details = [];

if (isset($_SESSION['match_results']) && is_array($_SESSION['match_results'])) {
	foreach ($_SESSION['match_results'] as $item) {
		if (isset($item['npc_hardware']) && !empty($item['npc_hardware'])) {
			$hardware = trim($item['npc_hardware']);
			
			// Only add if it's not already in our list
			if (!isset($unique_hardware[$hardware])) {
				$unique_hardware[$hardware] = true;
				
				// Collect additional details for ATUM POS
				$hardware_with_details[] = [
					'sku' => $hardware,
					'name' => isset($item['ID_16136965']) ? trim($item['ID_16136965']) : 'Hardware ' . $hardware,
					'model' => isset($item['CAPRICE']) ? trim($item['CAPRICE']) : '',
					'year' => isset($item['1991']) ? trim($item['1991']) : '',
					'holander' => isset($item['npc_holander']) ? trim($item['npc_holander']) : '',
					'retail_price' => isset($item['25_00']) ? trim($item['25_00']) : '0.00',
					'wholesale_price' => isset($item['63_00']) ? trim($item['63_00']) : '0.00',
					'stock_quantity' => isset($item['1']) ? intval($item['1']) : 1,
					'location' => isset($item['AI_137']) ? trim($item['AI_137']) : '',
					'part_type' => isset($item['Eng_Motor_Cont_Mod']) ? trim($item['Eng_Motor_Cont_Mod']) : '',
					'need_3_month' => isset($item['3_month_need']) ? intval($item['3_month_need']) : 0,
					'need_6_month' => isset($item['6_month_need']) ? intval($item['6_month_need']) : 0
				];
			}
		}
	}
}

// Get the list of unique hardware numbers
$hardware_list = array_keys($unique_hardware);

// Log the results
log_debug('Found ' . count($hardware_list) . ' unique hardware numbers');
log_debug('First 5 hardware items with details: ' . print_r(array_slice($hardware_with_details, 0, 5), true));

// Prepare data for ATUM POS integration
$table_name = $_POST['table'];

// Return the results as JSON for now
header('Content-Type: application/json');
echo json_encode([
	'success' => true,
	'message' => 'Found ' . count($hardware_list) . ' unique hardware numbers ready for ATUM POS',
	'hardware_count' => count($hardware_list),
	'hardware_sample' => array_slice($hardware_with_details, 0, 10), // Send first 10 as sample
	'table' => $table_name
]);

// For actual ATUM POS integration, you would:
// 1. Connect to WordPress/WooCommerce API
// 2. Create or update products with the hardware data
// 3. Set ATUM-specific attributes like stock status, locations, etc.

/*
// Example code for ATUM POS integration (would need WordPress environment)
function create_products_for_atum_pos($products) {
	// This would be implemented in a WordPress environment with ATUM POS installed
	
	foreach ($products as $product_data) {
		// Check if product exists by SKU
		$product_id = wc_get_product_id_by_sku($product_data['sku']);
		
		if ($product_id) {
			// Update existing product
			$product = wc_get_product($product_id);
		} else {
			// Create new product
			$product = new WC_Product_Simple();
			$product->set_sku($product_data['sku']);
		}
		
		// Set basic product data
		$product->set_name($product_data['name']);
		$product->set_regular_price($product_data['retail_price']);
		$product->set_sale_price($product_data['wholesale_price']);
		$product->set_stock_quantity($product_data['stock_quantity']);
		$product->set_manage_stock(true);
		
		// Save the product
		$product_id = $product->save();
		
		// Set ATUM specific data
		if (function_exists('update_post_meta') && class_exists('AtumProductData')) {
			// Set ATUM location
			update_post_meta($product_id, '_atum_location', $product_data['location']);
			
			// Set other ATUM fields
			update_post_meta($product_id, '_atum_supplier', ''); // Set your supplier ID
			update_post_meta($product_id, '_purchase_price', $product_data['wholesale_price']);
			
			// Add custom attributes for year, model, etc.
			$attributes = [];
			$attributes['year'] = $product_data['year'];
			$attributes['model'] = $product_data['model'];
			$attributes['part_type'] = $product_data['part_type'];
			$attributes['holander'] = $product_data['holander'];
			
			update_post_meta($product_id, '_product_attributes', $attributes);
		}
	}
	
	return true;
}
*/

exit;
?> 