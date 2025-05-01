<?php

// use Atum\PurchaseOrders\Models\PurchaseOrder;
// use Atum\PurchaseOrders\Items\POItemProduct;
// use Atum\PurchaseOrders\PurchaseOrders;
// use Atum\MultiInventory\Models\InventoryProduct;
// // use Atum\Components\AtumOrders\Items\AtumOrderItemProduct;
// use Atum\Components\AtumOrders\Items\AtumOrderItemFactory;
// use Atum\Components\AtumOrders\Items\AtumOrderItemProduct;
// use Atum\Components\AtumOrders\Models\AtumOrderItemModel;
// use Atum\Inc\Helpers;
// // use Atum\MultiInventory\Models\Inventory;
// use AtumMultiInventory\Models\Inventory;

// use Atum\MultiInventory\Models\InventoryHelpers;
// use AtumMultiInventory\Inc\Helpers as MIHelpers;



// use WC_Product;


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
// log_debug("post: " . print_r($_POST, true));

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
// log_debug('First 5 hardware items with details: ' . print_r(array_slice($hardware_with_details, 0, 5), true));

// Prepare data for ATUM POS integration
$table_name = $_POST['table'];

// Return the results as JSON for now
// header('Content-Type: application/json');
// echo json_encode([
// 	'success' => true,
// 	'message' => 'Found ' . count($hardware_list) . ' unique hardware numbers ready for ATUM POS',
// 	'hardware_count' => count($hardware_list),
// 	'hardware_sample' => array_slice($hardware_with_details, 0, 10), // Send first 10 as sample
// 	'table' => $table_name
// ]);


require_once '/Applications/XAMPP/xamppfiles/htdocs/npc/woo/wp-load.php';

use AtumPO\Models\POExtended;
use AtumMultiInventory\Inc\Helpers as MIHelpers;
use AtumMultiInventory\Models\Inventory;

// === Safety checks ===
if (!class_exists(POExtended::class)) die("❌ ATUM PO not loaded\n");
if (!class_exists(Inventory::class)) die("❌ ATUM MI not loaded\n");

echo "✅ ATUM PO + MI loaded.\n";

// === Step 1: Create PO ===
$po = new POExtended();
$po->set_status('atum_pending');
$po->set_date_created(current_time('mysql', true));
$po->set_date_expected(current_time('mysql'));
$po->set_description('Test PO with MI support');
$po->set_supplier(54519); // ✅ your supplier ID
$po->save();
echo "✅ PO created: #{$po->get_id()}\n";

// === Step 2: Add product ===
$product_id = 53696;
$product = wc_get_product($product_id);
if (!$product) die("❌ Product not found\n");

$po->add_product($product, 1); // this sets all required internal hooks
$po->save();

echo "✅ Product {$product->get_name()} added\n";

// === Step 3: Link inventory ===
$line_items = $po->get_items(['line_item']);
$line_item_id = null;

foreach ($line_items as $item) {
	if ((int)$item->get_product_id() === (int)$product_id) {
		$line_item_id = $item->get_id();
		break;
	}
}
if (!$line_item_id) die("❌ Line item not found\n");
echo "✅ Line item ID: $line_item_id\n";

// === Step 4: Link to Main Inventory ===
$inventories = MIHelpers::get_product_inventories_sorted($product_id);
$main_inventory = null;

foreach ($inventories as $inv) {
	if ($inv->is_main()) {
		$main_inventory = $inv;
		break;
	}
}
if (!$main_inventory) die("❌ No main inventory found\n");
echo "✅ Main Inventory ID: {$main_inventory->id}\n";

// === Step 5: Link inventory properly ===
$inventory_obj = new Inventory($main_inventory->id);
$extra_data = maybe_serialize([
	'name'           => $main_inventory->name,
	'sku'            => $product->get_sku(),
	'supplier_sku'   => $main_inventory->supplier_sku,
	'inventory_date' => current_time('mysql'),
	'is_main'        => 'yes',
	'location'       => [],
	'expiry_threshold' => 0,
]);

$inventory_obj->save_order_item_inventory($line_item_id, $main_inventory->id, [
	'qty'        => 1,
	'subtotal'   => $product->get_price(),
	'total'      => $product->get_price(),
	'extra_data' => $extra_data,
]);

echo "✅ Linked line item $line_item_id to main inventory\n";

exit;
die();
return;




require_once '/Applications/XAMPP/xamppfiles/htdocs/npc/woo/wp-load.php';

// use Atum\PurchaseOrders\Models\PurchaseOrder;
// use AtumMultiInventory\Inc\Helpers as MIHelpers;
// use AtumMultiInventory\Models\Inventory;

// Sanity checks
if (!class_exists(PurchaseOrder::class)) die("❌ ATUM not loaded\n");
if (!class_exists(Inventory::class)) die("❌ ATUM Multi-Inventory not loaded\n");

echo "✅ ATUM loaded!\n";
echo "✅ ATUM Multi-Inventory loaded.\n";

// === Step 1: Create Purchase Order ===
$po = new PurchaseOrder();
$po->set_status('atum_pending');
$po->set_date_created(current_time('mysql', true));
$po->set_date_expected(current_time('mysql'));
$po->set_description('Programmatic PO');
$po->set_supplier(54519); // ✅ Replace with real supplier ID
$po->save();
echo "✅ Created PO #{$po->get_id()}\n";

// === Step 2: Add product to PO using MI hook ===
$po->save(); // Must ensure PO has an ID before adding inventory

$product_id = 53696;
$product = wc_get_product($product_id);
if (!$product) die("❌ Product not found\n");

// === Get inventories ===
$inventories = MIHelpers::get_product_inventories_sorted($product_id);
$main_inventory = null;
foreach ($inventories as $inv) {
	if ($inv->is_main()) {
		$main_inventory = $inv;
		break;
	}
}
if (!$main_inventory) die("❌ No main inventory found\n");

// === Add product manually to the PO ===
$item = new \Atum\PurchaseOrders\Items\POItemProduct();
$item->set_product_id($product_id);
$item->set_quantity(1);
$item->set_subtotal($product->get_price());
$item->set_total($product->get_price());
$item->set_order_id($po->get_id());
$item->save();

$line_item_id = $item->get_id();
echo "✅ Manually created line item ID: $line_item_id\n";

// === Link inventory properly using object method ===
$inventory_obj = new Inventory($main_inventory->id);
$extra_data = maybe_serialize([
	'name'           => $main_inventory->name,
	'sku'            => $product->get_sku(),
	'supplier_sku'   => $main_inventory->supplier_sku,
	'inventory_date' => current_time('mysql'),
	'is_main'        => 'yes',
	'location'       => [],  // Required or empty
	'expiry_threshold' => 0,
]);

// call non-static method (properly!)
$inventory_obj->save_order_item_inventory($line_item_id, $main_inventory->id, [
	'qty'        => 1,
	'subtotal'   => $product->get_price(),
	'total'      => $product->get_price(),
	'extra_data' => $extra_data,
]);

echo "✅ Linked inventory to PO properly.\n";

die();
// === Step 2: Add product to PO ===
$product_id = 53696; // ✅ Replace with your real product ID
$product = wc_get_product($product_id);
if (!$product) die("❌ Product not found\n");

// Add initial item (quantity doesn't matter here)
$po->add_product($product, 1);
$po->save();
echo "✅ Added product {$product->get_name()} (ID: $product_id)\n";

// === Step 3: Get the line item we just added ===
$line_items = $po->get_items(['line_item']);
$line_item_id = null;

foreach ($line_items as $item) {
	if ((int)$item->get_product_id() === (int)$product_id) {
		$line_item_id = $item->get_id();
		break;
	}
}
if (!$line_item_id) die("❌ Line item not found for product ID $product_id\n");

echo "ℹ️ Found line item ID $line_item_id\n";

// === Step 4: Get Main Inventory ===
$inventories = MIHelpers::get_product_inventories_sorted($product_id);
if (empty($inventories)) die("❌ No inventories found\n");

$main_inventory = null;
foreach ($inventories as $inv) {
	if ($inv->is_main()) {
		$main_inventory = $inv;
		break;
	}
}
if (!$main_inventory) die("❌ Main inventory not found\n");

echo "✅ Main Inventory ID: {$main_inventory->id}\n";

// === Step 5: Link inventory to line item ===
$main_inventory->save_order_item_inventory($line_item_id, $product_id, [
	'qty'        => 1,
	'subtotal'   => $product->get_price(),
	'total'      => $product->get_price(),
	'extra_data' => maybe_serialize([
		'name'           => $main_inventory->name,
		'sku'            => $product->get_sku(),
		'supplier_sku'   => $main_inventory->supplier_sku,
		'inventory_date' => current_time('mysql'),
		'is_main'        => $main_inventory->is_main() ? 'yes' : 'no',
	]),
]);

echo "✅ Linked line item ID $line_item_id to inventory ID {$main_inventory->id}\n";

return;
die();
// Load WordPress
require_once '/Applications/XAMPP/xamppfiles/htdocs/npc/woo/wp-load.php';
if (class_exists('\Atum\PurchaseOrders\Models\PurchaseOrder')) {
	echo "✅ ATUM loaded!";
} else {
	die("❌ ATUM plugin not loaded.");
}
if (! class_exists('\AtumMultiInventory\Models\Inventory')) {
	die("❌ ATUM Multi-Inventory not loaded.\n");
}else{
	echo "✅ ATUM Multi-Inventory loaded.\n";
}

// 1. Create the PO object
$po = new PurchaseOrder();

// 2. Set basic PO properties
$po->set_status('atum_pending'); // or any valid ATUM status like 'atum_ordered', etc.
$po->set_date_created(current_time('mysql', true));
$po->set_date_expected(current_time('mysql'));
$po->set_description('PO created programmatically');
$po->set_supplier(54519); // Replace with a real supplier post ID
$po->set_multiple_suppliers(false);

// 3. Save the PO (this creates the actual post)
$po->save();
echo "✅ Created PO #{$po->get_id()}\n";


$product_id = 53696; // Replace with a real WooCommerce product ID
$product    = wc_get_product($product_id);
$po->add_product($product, 1);
$po->save();
echo "✅ Added product {$product->get_name()} (ID: $product_id)\n";

// ////////////////////////////////////////////////////
// === STEP 3: Get PO line item ===
$line_items = $po->get_items(['line_item']);
if (empty($line_items)) {
	die("❌ No line items found in PO.\n");
}
foreach ($line_items as $item) {
	echo "ℹ️ Found line item ID {$item->get_id()} for product ID {$item->get_product_id()}\n";

	if ((int) $item->get_product_id() !== (int) $product_id) {
		echo "⚠️ Skipping item not matching product ID $product_id\n";
		continue;
	}

	// === STEP 4: Get Inventories ===
	$inventories = MIHelpers::get_product_inventories_sorted($product_id);
	echo "ℹ️ Found " . count($inventories) . " inventories for product\n";

	if (empty($inventories)) {
		die("❌ No inventories found for product $product_id\n");
	}

	foreach ($inventories as $inv) {
		echo "- Inventory ID: {$inv->id}, Is Main: " . ($inv->is_main() ? 'Yes' : 'No') . "\n";
	}

	// === STEP 5: Link Main Inventory ===
	$main_inventory = null;
	foreach ($inventories as $inv) {
		if ($inv->is_main()) {
			$main_inventory = $inv;
			break;
		}
	}

	if (!$main_inventory) {
		die("❌ No main inventory found for product $product_id\n");
	}

	echo "✅ Main Inventory ID: {$main_inventory->id}\n";
	$line_item_id = $item->get_id(); // <-- ADD THIS

	// Save inventory to line item
	// Link line item to inventory
	if ($line_item_id && $main_inventory->id) {
		$inventory_obj = new Inventory($main_inventory->id);
		$inventory_obj->save_order_item_inventory($line_item_id, $main_inventory->id, 0);
		echo "✅ Linked line item ID $line_item_id to inventory ID $main_inventory->id\n";
	} else {
		echo "❌ Failed to find line item or main inventory\n";
	}


	break;
}



// === STEP 3: Get Line Item ID ===
// $items = $po->get_items();
// $line_item = end($items);
// $line_item_id = $line_item->get_id();

// echo "✅ Line item ID: $line_item_id\n";
// // === STEP 4: Attach Main Inventory ===
// $inventories = MI_Helpers::get_product_inventories_sorted($product_id);
// if (empty($inventories)) {
// 	die("❌ No MI inventories found for product.\n");
// }

// $main_inventory = reset($inventories);
// $inventory_id = $main_inventory->get_id();

// // Link line item to inventory
// MI_Inventory::save_order_item_inventory($line_item_id, $inventory_id, 0);

// echo "✅ Assigned Inventory ID: $inventory_id to Line Item #$line_item_id\n";

return;


// Load WordPress environment

require_once '/Applications/XAMPP/xamppfiles/htdocs/npc/woo/wp-load.php';

if ( class_exists( '\Atum\PurchaseOrders\Models\PurchaseOrder' ) ) {
    echo "✅ ATUM loaded!";
} else {
    die("❌ ATUM plugin not loaded.");
}



// Ensure you're in a proper WordPress context (e.g., plugin or function hooked into admin_init or wp_loaded)

// 1. Create the PO object
$po = new PurchaseOrder();

// 2. Set basic PO properties
$po->set_status('atum_pending'); // or any valid ATUM status like 'atum_ordered', etc.
$po->set_date_created(current_time('mysql', true));
$po->set_date_expected(current_time('mysql'));
$po->set_description('PO created programmatically');
$po->set_supplier(54519); // Replace with a real supplier post ID
$po->set_multiple_suppliers(false);

// 3. Save the PO (this creates the actual post)
$po->save();

// 4. Add one product as a line item
$product_id = 53696; // Replace with a real WooCommerce product ID
$product    = wc_get_product($product_id);
// log_debug("Product exists" . print_r($product, true));
if ($product && $product->exists()) {






	// log_debug("Product exists insde if");
	$item = new POItemProduct();
	// log_debug("Item created" . print_r($item, true));
	$item->set_props([
		'order_id'   => $po->get_id(),
		'product_id' => $product_id,
		'name'       => $product->get_name(),
		'quantity'   => 0,
		'total'      => 0,
	]);
	$item->save(); // ✅ This is necessary
	$po->add_item($item); // Optional (for internal linkage)

	$po->save(); // Save PO again to store item references

	echo "✅ PO and line item created.";
}

// 5. Save again to store the item
$po->save();


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