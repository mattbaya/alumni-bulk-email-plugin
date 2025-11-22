<?php
/**
 * Test AJAX handler registration and method mapping
 */

// Simulate WordPress environment
define('ABSPATH', '/tmp/wordpress/');

// Load classes
require_once __DIR__ . '/includes/class-database.php';
require_once __DIR__ . '/includes/class-file-processor.php';
require_once __DIR__ . '/includes/class-email-service.php';
require_once __DIR__ . '/includes/class-list-manager.php';
require_once __DIR__ . '/includes/class-campaign-manager.php';
require_once __DIR__ . '/includes/class-ajax-handlers.php';

echo "Testing AJAX handler method mapping...\n\n";

try {
    $ajax_handlers = new Alumni_Ajax_Handlers();
    
    // Define all registered actions and their corresponding methods
    $action_method_map = [
        'send_test_email' => 'handle_test_email',
        'send_test_campaign' => 'handle_test_campaign',
        'send_bulk_email' => 'handle_bulk_email',
        'save_campaign' => 'handle_save_campaign',
        'load_campaign' => 'handle_load_campaign',
        'view_campaign' => 'handle_view_campaign',
        'delete_campaign' => 'handle_delete_campaign',
        'copy_campaign' => 'handle_copy_campaign',
        'upload_csv' => 'handle_csv_upload',
        'upload_save_csv' => 'handle_upload_save_csv',
        'load_recipient_list' => 'handle_load_recipient_list',
        'create_recipient_list' => 'handle_create_recipient_list',
        'combine_recipient_lists' => 'handle_combine_recipient_lists',
        'view_recipient_list' => 'handle_view_recipient_list',
        'edit_recipient_row' => 'handle_edit_recipient_row',
        'bulk_edit_recipients' => 'handle_bulk_edit_recipients',
        'bulk_delete_recipients' => 'handle_bulk_delete_recipients',
        'get_list_columns' => 'handle_get_list_columns',
        'merge_csv_to_list' => 'handle_merge_csv_to_list',
        'export_recipient_list' => 'handle_export_recipient_list',
        'create_sublist_from_results' => 'handle_create_sublist_from_results',
        'save_header_footer' => 'handle_save_header_footer',
        'delete_header_footer' => 'handle_delete_header_footer',
        'set_default_header_footer' => 'handle_set_default_header_footer',
        'recreate_tables' => 'handle_recreate_tables',
        'handle_alumni_webhook' => 'handle_mailgun_webhook',
        'handle_unsubscribe' => 'handle_unsubscribe'
    ];
    
    $total_actions = count($action_method_map);
    $missing_methods = [];
    
    foreach ($action_method_map as $action => $method) {
        echo "Testing $action -> $method... ";
        if (method_exists($ajax_handlers, $method)) {
            echo "✓ OK\n";
        } else {
            echo "✗ MISSING\n";
            $missing_methods[] = $method;
        }
    }
    
    echo "\n";
    echo "Results:\n";
    echo "- Total AJAX actions: $total_actions\n";
    echo "- Missing methods: " . count($missing_methods) . "\n";
    
    if (empty($missing_methods)) {
        echo "\n✅ All AJAX handler methods are implemented!\n";
    } else {
        echo "\n❌ Missing methods:\n";
        foreach ($missing_methods as $method) {
            echo "  - $method\n";
        }
        throw new Exception("Some AJAX handler methods are missing");
    }
    
} catch (Exception $e) {
    echo "\n❌ Test failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>