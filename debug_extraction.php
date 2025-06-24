<?php
/**
 * Debug script to test 590 extraction with preview data
 */
require_once __DIR__ . '/utils.php';

// Test with the exact data from the preview
$test_inputs = [
    "10479: Electronic Control Module; (LH engine compartment), AT, ID 590-10479,10479,23D68698,ACCENT,2019,OEM# 39199-2BAC0 1.6L",
    "50923: Electronic Control Module; 4x2, 3.36 ratio, from 1/06",
    "8060: Electronic Control Module; (LH engine compartment), 2.0L"
];

echo "<h2>Debug: 590 Extraction Test</h2>\n";

foreach ($test_inputs as $index => $input) {
    echo "<h3>Test " . ($index + 1) . "</h3>\n";
    echo "<p><strong>Input:</strong> " . htmlspecialchars($input) . "</p>\n";
    
    $result = advanced_590_extraction($input);
    
    echo "<p><strong>Extracted:</strong> " . htmlspecialchars($result['extracted']) . "</p>\n";
    echo "<p><strong>Confidence:</strong> " . $result['confidence'] . "%</p>\n";
    echo "<p><strong>Method:</strong> " . $result['method'] . "</p>\n";
    echo "<p><strong>Changed:</strong> " . ($result['extracted'] !== $input ? 'YES' : 'NO') . "</p>\n";
    echo "<hr>\n";
}

// Test the specific pattern that should work
echo "<h2>Testing Specific Pattern</h2>\n";
$test = "10479: Electronic Control Module";
echo "<p><strong>Testing:</strong> " . htmlspecialchars($test) . "</p>\n";

if (preg_match('/^(\d{4,5}):/', $test, $matches)) {
    echo "<p><strong>Pattern match:</strong> YES</p>\n";
    echo "<p><strong>Number found:</strong> " . $matches[1] . "</p>\n";
    echo "<p><strong>Length:</strong> " . strlen($matches[1]) . "</p>\n";
} else {
    echo "<p><strong>Pattern match:</strong> NO</p>\n";
}
?> 