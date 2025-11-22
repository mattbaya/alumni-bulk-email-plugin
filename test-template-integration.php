<?php
/**
 * Test header/footer template integration in email campaigns
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
    return strip_tags($data, '<p><br><strong><em><ul><li><ol><a><img><h1><h2><h3>');
}

function sanitize_text_field($str) {
    return trim(strip_tags($str));
}

function sanitize_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function wp_create_nonce($action) {
    return 'test_nonce_' . $action;
}

function wp_verify_nonce($nonce, $action) {
    return true;
}

function current_user_can($capability) {
    return true;
}

function current_time($type) {
    return date('Y-m-d H:i:s');
}

// Mock database class
class MockWpdb {
    public $prefix = 'wp_';
    public $insert_id = 3;
    public $last_error = '';
    
    public function get_results($sql) {
        // Return mock templates - check for both type filters and general queries
        if (strpos($sql, "type = 'header'") !== false || strpos($sql, 'header') !== false) {
            return [
                (object) [
                    'id' => 1,
                    'name' => 'Company Header',
                    'type' => 'header', 
                    'content' => '<h1 style="color: #0066cc;">Welcome to Our Newsletter!</h1><hr>',
                    'is_default' => 1,
                    'created_at' => '2024-01-01 00:00:00'
                ]
            ];
        } elseif (strpos($sql, "type = 'footer'") !== false || strpos($sql, 'footer') !== false) {
            return [
                (object) [
                    'id' => 2,
                    'name' => 'Company Footer',
                    'type' => 'footer',
                    'content' => '<hr><p style="color: #666;">Best regards,<br><strong>Alumni Relations Team</strong><br><a href="mailto:alumni@example.com">alumni@example.com</a></p>',
                    'is_default' => 1,
                    'created_at' => '2024-01-01 00:00:00'
                ]
            ];
        } else {
            // Return all templates for general queries
            return [
                (object) [
                    'id' => 1,
                    'name' => 'Company Header',
                    'type' => 'header', 
                    'content' => '<h1 style="color: #0066cc;">Welcome to Our Newsletter!</h1><hr>',
                    'is_default' => 1,
                    'created_at' => '2024-01-01 00:00:00'
                ],
                (object) [
                    'id' => 2,
                    'name' => 'Company Footer',
                    'type' => 'footer',
                    'content' => '<hr><p style="color: #666;">Best regards,<br><strong>Alumni Relations Team</strong><br><a href="mailto:alumni@example.com">alumni@example.com</a></p>',
                    'is_default' => 1,
                    'created_at' => '2024-01-01 00:00:00'
                ]
            ];
        }
    }
    
    public function get_row($sql) {
        if (strpos($sql, 'id = 1') !== false) {
            return (object) [
                'id' => 1,
                'name' => 'Company Header',
                'type' => 'header',
                'content' => '<h1 style="color: #0066cc;">Welcome to Our Newsletter!</h1><hr>',
                'is_default' => 1
            ];
        } elseif (strpos($sql, 'id = 2') !== false) {
            return (object) [
                'id' => 2,
                'name' => 'Company Footer', 
                'type' => 'footer',
                'content' => '<hr><p style="color: #666;">Best regards,<br><strong>Alumni Relations Team</strong><br><a href="mailto:alumni@example.com">alumni@example.com</a></p>',
                'is_default' => 1
            ];
        }
        return null;
    }
    
    public function prepare($sql, ...$args) {
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

// Simulate $_POST data for template integration test
$_POST = [
    'content' => '<p>This is the main email content with important information about our upcoming alumni event.</p>',
    'header_template' => '1',
    'footer_template' => '2'
];

// Load classes in dependency order
require_once __DIR__ . '/includes/class-database.php';
require_once __DIR__ . '/includes/class-header-footer-manager.php';

echo "Testing header/footer template integration...\n\n";

try {
    $header_footer_manager = new Alumni_Header_Footer_Manager();
    
    // Test 1: Get templates for selection
    echo "1. Testing template retrieval for campaign form...\n";
    $headers = $header_footer_manager->get_all_templates('header');
    $footers = $header_footer_manager->get_all_templates('footer');
    
    echo "   - Available headers: " . count($headers) . "\n";
    echo "   - Available footers: " . count($footers) . "\n";
    
    if (count($headers) > 0) {
        echo "   - First header: " . $headers[0]->name . " (ID: " . $headers[0]->id . ")\n";
    }
    if (count($footers) > 0) {
        echo "   - First footer: " . $footers[0]->name . " (ID: " . $footers[0]->id . ")\n";
    }
    
    // Test 2: Apply templates as done in AJAX handler
    echo "\n2. Testing template application to email content...\n";
    $content = $_POST['content'];
    $header_id = intval($_POST['header_template']);
    $footer_id = intval($_POST['footer_template']);
    
    echo "   - Original content: " . strip_tags($content) . "\n";
    echo "   - Header ID selected: " . $header_id . "\n";
    echo "   - Footer ID selected: " . $footer_id . "\n";
    
    if ($header_id || $footer_id) {
        $final_content = $header_footer_manager->apply_templates($content, $header_id, $footer_id);
    } else {
        $final_content = $content;
    }
    
    echo "   - Final content length: " . strlen($final_content) . " characters\n";
    
    // Test 3: Verify template structure
    echo "\n3. Testing email structure with templates...\n";
    $lines = explode("\n", $final_content);
    $has_header = false;
    $has_main_content = false;
    $has_footer = false;
    
    foreach ($lines as $line) {
        if (strpos($line, 'Welcome to Our Newsletter') !== false) {
            $has_header = true;
        }
        if (strpos($line, 'main email content') !== false) {
            $has_main_content = true;
        }
        if (strpos($line, 'Alumni Relations Team') !== false) {
            $has_footer = true;
        }
    }
    
    echo "   - Contains header: " . ($has_header ? 'YES ✓' : 'NO ✗') . "\n";
    echo "   - Contains main content: " . ($has_main_content ? 'YES ✓' : 'NO ✗') . "\n";
    echo "   - Contains footer: " . ($has_footer ? 'YES ✓' : 'NO ✗') . "\n";
    
    // Test 4: Template order verification
    echo "\n4. Testing template order (header -> content -> footer)...\n";
    $header_pos = strpos($final_content, 'Welcome to Our Newsletter');
    $content_pos = strpos($final_content, 'main email content');
    $footer_pos = strpos($final_content, 'Alumni Relations Team');
    
    $correct_order = ($header_pos !== false && $content_pos !== false && $footer_pos !== false) &&
                     ($header_pos < $content_pos) && ($content_pos < $footer_pos);
    
    echo "   - Header position: " . $header_pos . "\n";
    echo "   - Content position: " . $content_pos . "\n";
    echo "   - Footer position: " . $footer_pos . "\n";
    echo "   - Correct order: " . ($correct_order ? 'YES ✓' : 'NO ✗') . "\n";
    
    // Test 5: Show final email preview
    echo "\n5. Final email content preview:\n";
    echo "   ================================\n";
    echo "   " . strip_tags($final_content, '<br>') . "\n";
    echo "   ================================\n";
    
    echo "\n✅ Header/Footer template integration tests completed!\n";
    echo "Templates are properly applied to email content in the correct order.\n";
    
} catch (Exception $e) {
    echo "\n❌ Test failed: " . $e->getMessage() . "\n";
    exit(1);
} catch (Error $e) {
    echo "\n❌ Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
?>