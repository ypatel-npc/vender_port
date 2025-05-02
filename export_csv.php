<?php
/**
 * Export matched data to CSV
 *
 * @package CSV_Import
 */

session_start();

// Check if match results exist in session
if (!isset($_SESSION['match_results']) || empty($_SESSION['match_results'])) {
    header('Content-Type: application/json');
    echo json_encode(array('success' => false, 'message' => 'No match results found to export'));
    exit;
}

// Get table name from URL parameter
$table_name = isset($_GET['table']) ? $_GET['table'] : 'export';

// Get match results from session
$match_results = $_SESSION['match_results'];

// Set headers for CSV download
$filename = $table_name . '_matches_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// If we have results, add headers and data
if (!empty($match_results)) {
    // Add CSV headers (column names)
    fputcsv($output, array_keys($match_results[0]));
    
    // Add data rows
    foreach ($match_results as $row) {
        fputcsv($output, $row);
    }
}

// Close the output stream
fclose($output);
exit;
