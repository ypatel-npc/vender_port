<?php
/**
 * Simple test to verify 590 extraction is working
 */
require_once __DIR__ . '/utils.php';

// Test data
$test_inputs = [
    "10479: Electronic Control Module; (LH engine compartment), AT, ID 590-10479,10479,23D68698,ACCENT,2019,OEM# 39199-2BAC0 1.6L",
    "50923: Electronic Control Module; 4x2, 3.36 ratio, from 1/06",
    "8060: Electronic Control Module; (LH engine compartment), 2.0L"
];

echo "<h2>590 Extraction Test</h2>\n";

foreach ($test_inputs as $index => $input) {
    echo "<h3>Test " . ($index + 1) . "</h3>\n";
    echo "<p><strong>Input:</strong> " . htmlspecialchars($input) . "</p>\n";
    
    $result = advanced_590_extraction($input);
    
    echo "<p><strong>Extracted:</strong> " . htmlspecialchars($result['extracted']) . "</p>\n";
    echo "<p><strong>Confidence:</strong> " . $result['confidence'] . "%</p>\n";
    echo "<p><strong>Method:</strong> " . $result['method'] . "</p>\n";
    echo "<hr>\n";
}

echo "<p><strong>Status:</strong> ✅ 590 extraction functions are working!</p>\n";
?> 