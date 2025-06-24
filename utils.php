<?php
/**
 * Utility functions for the CSV Import system
 *
 * @package CSV_Import
 */

/**
 * Log debug information to file
 * 
 * @param string $message Message to log
 * @return void
 */
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

/**
 * Enhanced 590 Number Extraction Algorithm
 * 
 * Extracts 590 numbers from complex strings using multiple patterns
 * 
 * @param string $input The input string to extract 590 numbers from
 * @return string The extracted 590 number or original string if no match
 */
function clean_590_number($input) {
    if (empty($input)) {
        return $input;
    }
    
    // Convert to string and trim
    $input = trim((string)$input);
    
    // Pattern 1: Direct 590-XXXXX format
    if (preg_match('/^590-(\d+)$/', $input, $matches)) {
        return '590-' . $matches[1];
    }
    
    // Pattern 2: 590-XXXXX with additional text
    if (preg_match('/590-(\d+)/', $input, $matches)) {
        return '590-' . $matches[1];
    }
    
    // Pattern 3: Numbers at the beginning of the string (common in user's data)
    // Look for patterns like "10479: Electronic Control Module" or "50923: Electronic Control Module"
    if (preg_match('/^(\d{4,5}):/', $input, $matches)) {
        $number = $matches[1];
        // Check if it's likely a 590 number (5 digits, or 4-5 digits that could be 590 numbers)
        if (strlen($number) >= 4 && strlen($number) <= 5) {
            return '590-' . $number;
        }
    }
    
    // Pattern 4: Just numbers that could be 590 numbers (5 digits)
    if (preg_match('/\b(\d{5})\b/', $input, $matches)) {
        $number = $matches[1];
        // Check if it's likely a 590 number (starts with common patterns)
        if (preg_match('/^(590|591|592|593|594|595|596|597|598|599)/', $number)) {
            return '590-' . $number;
        }
    }
    
    // Pattern 5: Extract from complex descriptions
    // Look for patterns like "ID 590-XXXXX" or "ID# 590-XXXXX"
    if (preg_match('/ID[#\s]*590-(\d+)/i', $input, $matches)) {
        return '590-' . $matches[1];
    }
    
    // Pattern 6: Extract from parentheses or brackets
    if (preg_match('/[\(\[].*?590-(\d+).*?[\)\]]/', $input, $matches)) {
        return '590-' . $matches[1];
    }
    
    // Pattern 7: Extract from comma-separated values
    $parts = explode(',', $input);
    foreach ($parts as $part) {
        $part = trim($part);
        if (preg_match('/590-(\d+)/', $part, $matches)) {
            return '590-' . $matches[1];
        }
        // Also check for standalone numbers in comma-separated parts
        if (preg_match('/^(\d{4,5})$/', $part, $matches)) {
            $number = $matches[1];
            if (strlen($number) >= 4 && strlen($number) <= 5) {
                return '590-' . $number;
            }
        }
    }
    
    // Pattern 8: Extract from semicolon-separated values
    $parts = explode(';', $input);
    foreach ($parts as $part) {
        $part = trim($part);
        if (preg_match('/590-(\d+)/', $part, $matches)) {
            return '590-' . $matches[1];
        }
    }
    
    // Pattern 9: Look for 590 numbers in any format within the string
    if (preg_match('/\b590[-\s]*(\d+)\b/', $input, $matches)) {
        return '590-' . $matches[1];
    }
    
    // Pattern 10: Extract from OEM or part number patterns
    if (preg_match('/OEM[#\s]*.*?590-(\d+)/i', $input, $matches)) {
        return '590-' . $matches[1];
    }
    
    // Pattern 11: Extract from engine compartment or location descriptions
    if (preg_match('/engine compartment.*?590-(\d+)/i', $input, $matches)) {
        return '590-' . $matches[1];
    }
    
    // Pattern 12: Look for any 4-5 digit number that could be a 590 number
    // This is a fallback for cases where the number appears without context
    if (preg_match('/\b(\d{4,5})\b/', $input, $matches)) {
        $number = $matches[1];
        // Only apply this if the number is standalone or at the beginning
        if (strlen($number) >= 4 && strlen($number) <= 5) {
            // Check if this number appears to be the main identifier
            $words = preg_split('/[\s,;:]+/', $input);
            if (in_array($number, $words) || strpos($input, $number . ':') === 0) {
                return '590-' . $number;
            }
        }
    }
    
    // If no 590 pattern found, return original input
    return $input;
}

/**
 * Advanced 590 Extraction with Multiple Validation
 * 
 * @param string $input The input string
 * @return array Array with extracted number and confidence level
 */
function advanced_590_extraction($input) {
    if (empty($input)) {
        return [
            'extracted' => $input,
            'confidence' => 0,
            'method' => 'empty_input'
        ];
    }
    
    $original = $input;
    $extracted = clean_590_number($input);
    $confidence = 0;
    $method = 'no_match';
    
    // Calculate confidence based on extraction method
    if ($extracted !== $original) {
        // High confidence for direct 590-XXXXX format
        if (preg_match('/^590-(\d+)$/', $extracted)) {
            $confidence = 100;
            $method = 'direct_format';
        }
        // Medium confidence for extracted patterns
        elseif (preg_match('/^590-(\d+)$/', $extracted)) {
            $confidence = 85;
            $method = 'pattern_extraction';
        }
        // Lower confidence for complex extractions
        else {
            $confidence = 70;
            $method = 'complex_extraction';
        }
    }
    
    return [
        'extracted' => $extracted,
        'confidence' => $confidence,
        'method' => $method,
        'original' => $original
    ];
}
?> 