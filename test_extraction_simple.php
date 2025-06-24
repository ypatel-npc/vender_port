<?php
require_once __DIR__ . '/utils.php';

// Test with the exact data from debug
$test_input = "10479: Electronic Control Module; (LH engine compartment), AT, ID 590-10479,10479,23D68698,ACCENT,2019,OEM# 39199-2BAC0 1.6L";

echo "<h2>Testing 590 Extraction</h2>\n";
echo "<p><strong>Input:</strong> " . htmlspecialchars($test_input) . "</p>\n";

$result = advanced_590_extraction($test_input);

echo "<p><strong>Extracted:</strong> " . htmlspecialchars($result['extracted']) . "</p>\n";
echo "<p><strong>Confidence:</strong> " . $result['confidence'] . "%</p>\n";
echo "<p><strong>Method:</strong> " . $result['method'] . "</p>\n";
echo "<p><strong>Changed:</strong> " . ($result['extracted'] !== $test_input ? 'YES' : 'NO') . "</p>\n";

// Test the specific pattern
echo "<h3>Testing Pattern Match</h3>\n";
if (preg_match('/^(\d{4,5}):/', $test_input, $matches)) {
    echo "<p>✅ Pattern matched! Number: " . $matches[1] . "</p>\n";
} else {
    echo "<p>❌ Pattern did not match</p>\n";
}
?> 