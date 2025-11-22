<?php
/**
 * Test file processing functionality
 */

// Simulate WordPress environment
define('ABSPATH', '/tmp/wordpress/');

// Load classes
require_once __DIR__ . '/includes/class-file-processor.php';

echo "Testing file processing functionality...\n\n";

try {
    $file_processor = new Alumni_File_Processor();
    
    // Test 1: CSV file processing
    echo "1. Testing CSV file parsing...\n";
    $csv_file = __DIR__ . '/test-sample.csv';
    
    if (!file_exists($csv_file)) {
        throw new Exception("Test CSV file not found: $csv_file");
    }
    
    $recipients = $file_processor->parse_file($csv_file, 'csv');
    
    echo "   - File parsed successfully\n";
    echo "   - Recipients found: " . count($recipients) . "\n";
    
    if (count($recipients) > 0) {
        echo "   - Sample recipient:\n";
        $first_recipient = $recipients[0];
        foreach ($first_recipient as $key => $value) {
            echo "     $key: $value\n";
        }
        
        // Test field extraction
        echo "\n   - Testing field extraction...\n";
        $email = $file_processor->extract_email_from_recipient($first_recipient);
        echo "     Extracted email: $email\n";
        
        if ($email !== 'john@test.com') {
            throw new Exception("Email extraction failed. Expected 'john@test.com', got '$email'");
        }
    }
    
    // Test 2: File validation
    echo "\n2. Testing file validation...\n";
    
    // Create a mock file array for testing
    $valid_file = [
        'tmp_name' => $csv_file,
        'name' => 'test-sample.csv',
        'size' => filesize($csv_file),
        'error' => UPLOAD_ERR_OK
    ];
    
    try {
        $file_processor->validate_file_upload($valid_file);
        echo "   - File validation passed ✓\n";
    } catch (Exception $e) {
        echo "   - File validation failed: " . $e->getMessage() . "\n";
    }
    
    // Test 3: CSV content generation
    echo "\n3. Testing CSV content generation...\n";
    $csv_content = $file_processor->generate_csv_content($recipients);
    
    if (!empty($csv_content)) {
        echo "   - CSV content generated successfully ✓\n";
        echo "   - Content length: " . strlen($csv_content) . " bytes\n";
        
        // Verify header line exists
        $lines = explode("\n", trim($csv_content));
        echo "   - CSV lines: " . count($lines) . " (including header)\n";
        
        if (count($lines) !== count($recipients) + 1) {
            echo "   - Warning: Line count mismatch\n";
        }
    } else {
        throw new Exception("CSV content generation failed");
    }
    
    echo "\n✅ File processing tests passed!\n";
    
} catch (Exception $e) {
    echo "\n❌ Test failed: " . $e->getMessage() . "\n";
    exit(1);
} catch (Error $e) {
    echo "\n❌ Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
?>