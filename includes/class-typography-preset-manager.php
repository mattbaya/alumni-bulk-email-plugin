<?php
/**
 * Typography Presets management class for Alumni Bulk Email plugin
 * 
 * Handles creation, editing, deletion, and management of typography presets
 */

if (!defined('ABSPATH')) {
    exit;
}

class Alumni_Typography_Preset_Manager {
    
    /**
     * Get all typography presets
     */
    public function get_all_presets() {
        global $wpdb;
        
        $table_name = Alumni_Database::get_table_name('typography_presets');
        $sql = "SELECT * FROM $table_name ORDER BY is_default DESC, name ASC";
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get a specific preset by ID
     */
    public function get_preset($id) {
        global $wpdb;
        
        $table_name = Alumni_Database::get_table_name('typography_presets');
        $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id);
        
        return $wpdb->get_row($sql);
    }
    
    /**
     * Get default preset
     */
    public function get_default_preset() {
        global $wpdb;
        
        $table_name = Alumni_Database::get_table_name('typography_presets');
        $sql = "SELECT * FROM $table_name WHERE is_default = 1 LIMIT 1";
        
        return $wpdb->get_row($sql);
    }
    
    /**
     * Save a typography preset
     */
    public function save_preset($name, $font_family, $font_size, $text_color, $description = '', $is_default = false, $id = null) {
        global $wpdb;
        
        $table_name = Alumni_Database::get_table_name('typography_presets');
        
        // Validate required fields
        if (empty(trim($name))) {
            throw new Exception('Preset name is required');
        }
        
        // If setting as default, remove default flag from other presets
        if ($is_default) {
            $wpdb->update(
                $table_name,
                array('is_default' => 0),
                array(),
                array('%d'),
                array()
            );
        }
        
        $data = array(
            'name' => sanitize_text_field($name),
            'font_family' => sanitize_text_field($font_family),
            'font_size' => sanitize_text_field($font_size),
            'text_color' => sanitize_text_field($text_color),
            'description' => sanitize_textarea_field($description),
            'is_default' => $is_default ? 1 : 0,
            'updated_at' => current_time('mysql')
        );
        
        if ($id) {
            // Update existing preset
            $result = $wpdb->update(
                $table_name,
                $data,
                array('id' => $id),
                array('%s', '%s', '%s', '%s', '%s', '%d', '%s'),
                array('%d')
            );
            
            if ($result === false) {
                throw new Exception('Failed to update preset: ' . $wpdb->last_error);
            }
            
            return $id;
        } else {
            // Create new preset
            $data['created_at'] = current_time('mysql');
            
            $result = $wpdb->insert(
                $table_name,
                $data,
                array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
            );
            
            if ($result === false) {
                throw new Exception('Failed to create preset: ' . $wpdb->last_error);
            }
            
            return $wpdb->insert_id;
        }
    }
    
    /**
     * Delete a preset
     */
    public function delete_preset($id) {
        global $wpdb;
        
        $table_name = Alumni_Database::get_table_name('typography_presets');
        
        // Get the preset to check if it's default
        $preset = $this->get_preset($id);
        if (!$preset) {
            throw new Exception('Preset not found');
        }
        
        // Don't allow deletion of default presets
        if ($preset->is_default) {
            throw new Exception('Cannot delete default preset. Please set another preset as default first.');
        }
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $id),
            array('%d')
        );
        
        if ($result === false) {
            throw new Exception('Failed to delete preset: ' . $wpdb->last_error);
        }
        
        return $result > 0;
    }
    
    /**
     * Set a preset as default
     */
    public function set_default_preset($id) {
        global $wpdb;
        
        $table_name = Alumni_Database::get_table_name('typography_presets');
        
        // Get the preset
        $preset = $this->get_preset($id);
        if (!$preset) {
            throw new Exception('Preset not found');
        }
        
        // Remove default flag from other presets
        $wpdb->update(
            $table_name,
            array('is_default' => 0),
            array(),
            array('%d'),
            array()
        );
        
        // Set this preset as default
        $result = $wpdb->update(
            $table_name,
            array('is_default' => 1, 'updated_at' => current_time('mysql')),
            array('id' => $id),
            array('%d', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            throw new Exception('Failed to set default preset: ' . $wpdb->last_error);
        }
        
        return true;
    }
    
    /**
     * Generate CSS from preset
     */
    public function generate_css_from_preset($preset) {
        $styles = array();
        
        if (!empty($preset->font_family)) {
            $styles[] = 'font-family: ' . $preset->font_family;
        }
        
        if (!empty($preset->font_size)) {
            $styles[] = 'font-size: ' . $preset->font_size;
        }
        
        if (!empty($preset->text_color) && $preset->text_color !== '#000000') {
            $styles[] = 'color: ' . $preset->text_color;
        }
        
        return implode('; ', $styles);
    }
    
    /**
     * Apply preset to content
     */
    public function apply_preset_to_content($content, $preset_id) {
        if (!$preset_id) {
            return $content;
        }
        
        $preset = $this->get_preset($preset_id);
        if (!$preset) {
            return $content;
        }
        
        $css = $this->generate_css_from_preset($preset);
        if ($css) {
            return '<div style="' . $css . '">' . $content . '</div>';
        }
        
        return $content;
    }
    
    /**
     * Get preset statistics
     */
    public function get_preset_stats() {
        global $wpdb;
        
        $table_name = Alumni_Database::get_table_name('typography_presets');
        
        $stats = array(
            'total' => 0,
            'default_preset' => null
        );
        
        // Get total count
        $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // Get default preset
        $stats['default_preset'] = $this->get_default_preset();
        
        return $stats;
    }
    
    /**
     * Duplicate a preset
     */
    public function duplicate_preset($id, $new_name = null) {
        $original = $this->get_preset($id);
        if (!$original) {
            throw new Exception('Preset not found');
        }
        
        if (!$new_name) {
            $new_name = $original->name . ' (Copy)';
        }
        
        return $this->save_preset(
            $new_name,
            $original->font_family,
            $original->font_size,
            $original->text_color,
            $original->description,
            false // Don't set copy as default
        );
    }
    
    /**
     * Create default presets
     */
    public function create_default_presets() {
        // Check if we already have presets
        $existing_presets = $this->get_all_presets();
        if (!empty($existing_presets)) {
            return; // Don't create defaults if presets already exist
        }
        
        $default_presets = array(
            array(
                'name' => 'Professional Header',
                'font_family' => 'Arial, sans-serif',
                'font_size' => '24px',
                'text_color' => '#0066cc',
                'description' => 'Clean, professional style for email headers',
                'is_default' => true
            ),
            array(
                'name' => 'Elegant Footer',
                'font_family' => 'Georgia, serif',
                'font_size' => '14px',
                'text_color' => '#666666',
                'description' => 'Elegant serif style for email footers',
                'is_default' => false
            ),
            array(
                'name' => 'Bold Impact',
                'font_family' => 'Impact, sans-serif',
                'font_size' => '20px',
                'text_color' => '#ff6600',
                'description' => 'Bold, attention-grabbing style for important announcements',
                'is_default' => false
            ),
            array(
                'name' => 'Classic Times',
                'font_family' => "'Times New Roman', Times, serif",
                'font_size' => '16px',
                'text_color' => '#333333',
                'description' => 'Traditional, readable style for formal communications',
                'is_default' => false
            )
        );
        
        foreach ($default_presets as $preset) {
            $this->save_preset(
                $preset['name'],
                $preset['font_family'],
                $preset['font_size'],
                $preset['text_color'],
                $preset['description'],
                $preset['is_default']
            );
        }
    }
}