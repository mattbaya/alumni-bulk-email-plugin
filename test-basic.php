<?php
/**
 * Basic testing script for refactored plugin
 * This tests class loading and basic instantiation
 */

// Simulate WordPress environment constants
define('ABSPATH', '/tmp/wordpress/');

// Load all our classes
require_once __DIR__ . '/includes/class-database.php';
require_once __DIR__ . '/includes/class-file-processor.php';
require_once __DIR__ . '/includes/class-email-service.php';
require_once __DIR__ . '/includes/class-list-manager.php';
require_once __DIR__ . '/includes/class-campaign-manager.php';
require_once __DIR__ . '/includes/class-ajax-handlers.php';

echo "Testing class instantiation...\n";

try {
    // Test 1: File Processor
    echo "1. Testing File Processor... ";
    $file_processor = new Alumni_File_Processor();
    echo "✓ OK\n";
    
    // Test 2: Email Service
    echo "2. Testing Email Service... ";
    $email_service = new Alumni_Email_Service($file_processor);
    echo "✓ OK\n";
    
    // Test 3: List Manager
    echo "3. Testing List Manager... ";
    $list_manager = new Alumni_List_Manager($file_processor, $email_service);
    echo "✓ OK\n";
    
    // Test 4: Campaign Manager
    echo "4. Testing Campaign Manager... ";
    $campaign_manager = new Alumni_Campaign_Manager($email_service, $list_manager);
    echo "✓ OK\n";
    
    // Test 5: AJAX Handlers
    echo "5. Testing AJAX Handlers... ";
    $ajax_handlers = new Alumni_Ajax_Handlers();
    echo "✓ OK\n";
    
    // Test 6: Method existence check
    echo "6. Testing critical method existence...\n";
    
    $critical_methods = [
        [$file_processor, 'parse_file'],
        [$email_service, 'is_mailgun_configured'],
        [$list_manager, 'create_list'],
        [$campaign_manager, 'send_campaign'],
        [$ajax_handlers, 'register_handlers']
    ];
    
    foreach ($critical_methods as $i => list($object, $method)) {
        echo "   6." . ($i+1) . " Method $method... ";
        if (method_exists($object, $method)) {
            echo "✓ EXISTS\n";
        } else {
            echo "✗ MISSING\n";
            throw new Exception("Critical method $method is missing");
        }
    }
    
    echo "\n✅ All basic tests passed!\n";
    echo "Class instantiation and method existence verified.\n";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    exit(1);
} catch (Error $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
?>