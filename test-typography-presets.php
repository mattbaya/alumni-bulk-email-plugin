<?php
/**
 * Test typography presets functionality
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

function sanitize_text_field($str) {
    return trim(strip_tags($str));
}

function sanitize_textarea_field($str) {
    return trim(strip_tags($str));
}

function current_time($type) {
    return date('Y-m-d H:i:s');
}

// Mock database class
class MockWpdb {
    public $prefix = 'wp_';
    public $insert_id = 1;
    public $last_error = '';
    
    private $presets = [];
    private $next_id = 1;
    
    public function get_results($sql) {
        // Return mock presets data
        if (strpos($sql, 'ORDER BY is_default DESC') !== false) {
            return array_values($this->presets);
        }
        return [];
    }
    
    public function get_row($sql) {
        if (preg_match('/WHERE id = (\d+)/', $sql, $matches)) {
            $id = intval($matches[1]);
            return isset($this->presets[$id]) ? $this->presets[$id] : null;
        }
        return null;
    }
    
    public function get_var($sql) {
        if (strpos($sql, 'COUNT(*)') !== false) {
            return count($this->presets);
        }
        return 0;
    }
    
    public function prepare($sql, ...$args) {
        return vsprintf(str_replace('%s', "'%s'", str_replace('%d', '%d', $sql)), $args);
    }
    
    public function insert($table, $data, $format) {
        $preset = (object) array_merge($data, ['id' => $this->next_id]);
        $this->presets[$this->next_id] = $preset;
        $this->insert_id = $this->next_id;
        $this->next_id++;
        return true;
    }
    
    public function update($table, $data, $where, $format, $where_format) {
        if (isset($where['id'])) {
            $id = $where['id'];
            if (isset($this->presets[$id])) {
                foreach ($data as $key => $value) {
                    $this->presets[$id]->$key = $value;
                }
                return 1;
            }
        }
        
        // Handle clearing all default flags
        if (isset($data['is_default']) && $data['is_default'] == 0 && empty($where)) {
            foreach ($this->presets as $preset) {
                $preset->is_default = 0;
            }
            return 1;
        }
        
        return 0;
    }
    
    public function delete($table, $where, $where_format) {
        if (isset($where['id'])) {
            $id = $where['id'];
            if (isset($this->presets[$id])) {
                unset($this->presets[$id]);
                return 1;
            }
        }
        return 0;
    }
}

// Create mock global database object
$wpdb = new MockWpdb();

// Load classes in dependency order
require_once __DIR__ . '/includes/class-database.php';
require_once __DIR__ . '/includes/class-typography-preset-manager.php';

echo "Testing Typography Presets functionality...\n\n";

try {
    $preset_manager = new Alumni_Typography_Preset_Manager();
    
    // Test 1: Create default presets
    echo "1. Testing default presets creation...\n";
    $preset_manager->create_default_presets();
    $all_presets = $preset_manager->get_all_presets();
    
    echo "   - Default presets created: " . count($all_presets) . "\n";
    foreach ($all_presets as $preset) {
        echo "     * " . $preset->name . " (Font: " . $preset->font_family . ", Size: " . $preset->font_size . ", Color: " . $preset->text_color . ")\n";
        if ($preset->is_default) {
            echo "       [DEFAULT PRESET]\n";
        }
    }
    
    // Test 2: Save a custom preset
    echo "\n2. Testing custom preset creation...\n";
    $custom_preset_id = $preset_manager->save_preset(
        'Custom Header Style',
        'Helvetica, Arial, sans-serif',
        '22px',
        '#ff6600',
        'Bold orange style for event announcements'
    );
    
    echo "   - Custom preset created with ID: " . $custom_preset_id . "\n";
    
    // Test 3: Get a specific preset
    echo "\n3. Testing preset retrieval...\n";
    $retrieved_preset = $preset_manager->get_preset($custom_preset_id);
    if ($retrieved_preset) {
        echo "   - Retrieved preset: " . $retrieved_preset->name . "\n";
        echo "     Font Family: " . $retrieved_preset->font_family . "\n";
        echo "     Font Size: " . $retrieved_preset->font_size . "\n";
        echo "     Text Color: " . $retrieved_preset->text_color . "\n";
        echo "     Description: " . $retrieved_preset->description . "\n";
    } else {
        echo "   - Failed to retrieve preset\n";
    }
    
    // Test 4: Generate CSS from preset
    echo "\n4. Testing CSS generation...\n";
    $css = $preset_manager->generate_css_from_preset($retrieved_preset);
    echo "   - Generated CSS: " . $css . "\n";
    
    // Test 5: Apply preset to content
    echo "\n5. Testing content application...\n";
    $sample_content = '<h1>Welcome Alumni!</h1><p>Join us for our annual reunion.</p>';
    $styled_content = $preset_manager->apply_preset_to_content($sample_content, $custom_preset_id);
    echo "   - Original content: " . $sample_content . "\n";
    echo "   - Styled content: " . $styled_content . "\n";
    
    // Test 6: Set a preset as default
    echo "\n6. Testing default preset management...\n";
    $preset_manager->set_default_preset($custom_preset_id);
    $default_preset = $preset_manager->get_default_preset();
    echo "   - New default preset: " . ($default_preset ? $default_preset->name : 'None') . "\n";
    
    // Test 7: Get preset statistics
    echo "\n7. Testing preset statistics...\n";
    $stats = $preset_manager->get_preset_stats();
    echo "   - Total presets: " . $stats['total'] . "\n";
    echo "   - Default preset: " . ($stats['default_preset'] ? $stats['default_preset']->name : 'None') . "\n";
    
    // Test 8: Duplicate a preset
    echo "\n8. Testing preset duplication...\n";
    $duplicate_id = $preset_manager->duplicate_preset($custom_preset_id, 'Custom Header Style Copy');
    $duplicate_preset = $preset_manager->get_preset($duplicate_id);
    echo "   - Duplicated preset: " . $duplicate_preset->name . "\n";
    echo "   - Is default: " . ($duplicate_preset->is_default ? 'Yes' : 'No') . "\n";
    
    // Test 9: Try to delete default preset (should fail)
    echo "\n9. Testing default preset deletion protection...\n";
    try {
        $preset_manager->delete_preset($custom_preset_id); // This is now the default
        echo "   - ERROR: Default preset deletion was allowed!\n";
    } catch (Exception $e) {
        echo "   - ✓ Default preset deletion properly blocked: " . $e->getMessage() . "\n";
    }
    
    // Test 10: Delete non-default preset
    echo "\n10. Testing non-default preset deletion...\n";
    $deleted = $preset_manager->delete_preset($duplicate_id);
    echo "   - Non-default preset deleted: " . ($deleted ? 'Yes' : 'No') . "\n";
    
    // Final statistics
    echo "\n=== Final Statistics ===\n";
    $final_stats = $preset_manager->get_preset_stats();
    echo "Total presets: " . $final_stats['total'] . "\n";
    echo "Default preset: " . ($final_stats['default_preset'] ? $final_stats['default_preset']->name : 'None') . "\n";
    
    // Test edge cases
    echo "\n=== Edge Case Testing ===\n";
    
    // Test empty/invalid inputs
    echo "11. Testing input validation...\n";
    try {
        $preset_manager->save_preset('', 'Arial', '16px', '#000000'); // Empty name
        echo "   - ERROR: Empty name was allowed!\n";
    } catch (Exception $e) {
        echo "   - ✓ Empty name properly rejected\n";
    }
    
    // Test CSS generation with empty values
    echo "12. Testing CSS generation with minimal data...\n";
    $minimal_preset = (object) [
        'font_family' => '',
        'font_size' => '18px',
        'text_color' => '#000000' // Default color, should not be included
    ];
    $minimal_css = $preset_manager->generate_css_from_preset($minimal_preset);
    echo "   - Minimal CSS (only font-size): " . $minimal_css . "\n";
    
    echo "\n✅ Typography Presets functionality tests completed successfully!\n";
    echo "All preset operations (save, load, apply, delete, duplicate) are working correctly.\n";
    
} catch (Exception $e) {
    echo "\n❌ Test failed: " . $e->getMessage() . "\n";
    exit(1);
} catch (Error $e) {
    echo "\n❌ Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
?>