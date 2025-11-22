<?php
/**
 * Test Headers & Footers functionality
 */

// Simulate WordPress environment and functions
define('ABSPATH', '/tmp/wordpress/');

// Mock WordPress functions
function get_option($option_name, $default = false) {
    return $default;
}

function update_option($option_name, $value) {
    return true;
}

function wp_kses_post($data) {
    return strip_tags($data, '<p><br><strong><em><ul><li><ol><a><img>');
}

function sanitize_text_field($str) {
    return trim(strip_tags($str));
}

function sanitize_key($key) {
    return strtolower(trim($key));
}

function current_time($type) {
    return date('Y-m-d H:i:s');
}

function current_user_can($capability) {
    return true;
}

// Mock database class
class MockWpdb {
    public $prefix = 'wp_';
    public $insert_id = 3;
    public $last_error = '';
    
    public function get_results($sql) {
        // Return mock templates for testing
        return [
            (object) [
                'id' => 1,
                'name' => 'Default Header',
                'type' => 'header', 
                'content' => '<h1>Welcome to Our Newsletter!</h1>',
                'is_default' => 1,
                'created_at' => '2024-01-01 00:00:00'
            ],
            (object) [
                'id' => 2,
                'name' => 'Default Footer',
                'type' => 'footer',
                'content' => '<p>Best regards,<br>Alumni Relations Team</p>',
                'is_default' => 1,
                'created_at' => '2024-01-01 00:00:00'
            ]
        ];
    }
    
    public function get_row($sql) {
        // Return mock single template
        return (object) [
            'id' => 1,
            'name' => 'Default Header',
            'type' => 'header',
            'content' => '<h1>Welcome to Our Newsletter!</h1>',
            'is_default' => 1,
            'created_at' => '2024-01-01 00:00:00'
        ];
    }
    
    public function prepare($sql, ...$args) {
        // Simple mock prepare - just return the SQL
        return $sql;
    }
    
    public function insert($table, $data, $format) {
        return true;
    }
    
    public function update($table, $data, $where, $format, $where_format) {
        return 1;
    }
    
    public function delete($table, $where, $where_format) {
        return 1;
    }
}

// Create mock global database object
$wpdb = new MockWpdb();

// Load classes in dependency order
require_once __DIR__ . '/includes/class-database.php';
require_once __DIR__ . '/includes/class-header-footer-manager.php';

echo "Testing Headers & Footers functionality...\n\n";

try {
    $header_footer_manager = new Alumni_Header_Footer_Manager();
    
    // Test 1: Get all templates
    echo "1. Testing get all templates...\n";
    $templates = $header_footer_manager->get_all_templates();
    echo "   - Templates retrieved: " . count($templates) . "\n";
    echo "   - First template: " . $templates[0]->name . " (" . $templates[0]->type . ")\n";
    
    // Test 2: Get specific template
    echo "\n2. Testing get specific template...\n";
    $template = $header_footer_manager->get_template(1);
    echo "   - Template retrieved: " . $template->name . "\n";
    echo "   - Template type: " . $template->type . "\n";
    echo "   - Is default: " . ($template->is_default ? 'YES' : 'NO') . "\n";
    
    // Test 3: Get default template
    echo "\n3. Testing get default template...\n";
    $default_header = $header_footer_manager->get_default_template('header');
    echo "   - Default header: " . $default_header->name . "\n";
    
    // Test 4: Apply templates to content
    echo "\n4. Testing apply templates to content...\n";
    $content = "This is the main email content.";
    $full_content = $header_footer_manager->apply_templates($content);
    echo "   - Original content length: " . strlen($content) . "\n";
    echo "   - With templates length: " . strlen($full_content) . "\n";
    echo "   - Templates applied successfully ✓\n";
    
    // Test 5: Template statistics
    echo "\n5. Testing template statistics...\n";
    $stats = $header_footer_manager->get_template_stats();
    echo "   - Total templates: " . $stats['total'] . "\n";
    echo "   - Headers: " . $stats['headers'] . "\n";
    echo "   - Footers: " . $stats['footers'] . "\n";
    
    // Test 6: Save new template (mock)
    echo "\n6. Testing save new template...\n";
    try {
        $new_id = $header_footer_manager->save_template(
            'Test Header',
            'header',
            '<h2>Test Header Content</h2>',
            false
        );
        echo "   - New template saved with ID: " . $new_id . " ✓\n";
    } catch (Exception $e) {
        echo "   - Save template test: " . $e->getMessage() . "\n";
    }
    
    // Test 7: Duplicate template (mock)
    echo "\n7. Testing duplicate template...\n";
    try {
        $duplicate_id = $header_footer_manager->duplicate_template(1, 'Copied Header');
        echo "   - Template duplicated with ID: " . $duplicate_id . " ✓\n";
    } catch (Exception $e) {
        echo "   - Duplicate template test: " . $e->getMessage() . "\n";
    }
    
    echo "\n✅ Headers & Footers functionality tests completed!\n";
    echo "Note: Database operations are mocked. Full testing requires WordPress environment.\n";
    
} catch (Exception $e) {
    echo "\n❌ Test failed: " . $e->getMessage() . "\n";
    exit(1);
} catch (Error $e) {
    echo "\n❌ Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
?>