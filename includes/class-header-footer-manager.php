<?php
/**
 * Headers and Footers management class for Alumni Bulk Email plugin
 * 
 * Handles creation, editing, deletion, and management of email templates
 */

if (!defined('ABSPATH')) {
    exit;
}

class Alumni_Header_Footer_Manager {
    
    /**
     * Get all headers and footers
     */
    public function get_all_templates($type = null) {
        global $wpdb;
        
        $table_name = Alumni_Database::get_table_name('headers_footers');
        
        if ($type) {
            $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE type = %s ORDER BY is_default DESC, name ASC", $type);
        } else {
            $sql = "SELECT * FROM $table_name ORDER BY type, is_default DESC, name ASC";
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get a specific template by ID
     */
    public function get_template($id) {
        global $wpdb;
        
        $table_name = Alumni_Database::get_table_name('headers_footers');
        $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id);
        
        return $wpdb->get_row($sql);
    }
    
    /**
     * Get default template for a type
     */
    public function get_default_template($type) {
        global $wpdb;
        
        $table_name = Alumni_Database::get_table_name('headers_footers');
        $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE type = %s AND is_default = 1 LIMIT 1", $type);
        
        return $wpdb->get_row($sql);
    }
    
    /**
     * Save a header or footer template
     */
    public function save_template($name, $type, $content, $is_default = false, $id = null) {
        global $wpdb;
        
        $table_name = Alumni_Database::get_table_name('headers_footers');
        
        // Validate type
        if (!in_array($type, ['header', 'footer'])) {
            throw new Exception('Invalid template type. Must be header or footer.');
        }
        
        // If setting as default, remove default flag from other templates of same type
        if ($is_default) {
            $wpdb->update(
                $table_name,
                array('is_default' => 0),
                array('type' => $type),
                array('%d'),
                array('%s')
            );
        }
        
        $data = array(
            'name' => sanitize_text_field($name),
            'type' => $type,
            'content' => wp_kses_post($content),
            'is_default' => $is_default ? 1 : 0,
            'updated_at' => current_time('mysql')
        );
        
        if ($id) {
            // Update existing template
            $result = $wpdb->update(
                $table_name,
                $data,
                array('id' => $id),
                array('%s', '%s', '%s', '%d', '%s'),
                array('%d')
            );
            
            if ($result === false) {
                throw new Exception('Failed to update template: ' . $wpdb->last_error);
            }
            
            return $id;
        } else {
            // Create new template
            $data['created_at'] = current_time('mysql');
            
            $result = $wpdb->insert(
                $table_name,
                $data,
                array('%s', '%s', '%s', '%d', '%s', '%s')
            );
            
            if ($result === false) {
                throw new Exception('Failed to create template: ' . $wpdb->last_error);
            }
            
            return $wpdb->insert_id;
        }
    }
    
    /**
     * Delete a template
     */
    public function delete_template($id) {
        global $wpdb;
        
        $table_name = Alumni_Database::get_table_name('headers_footers');
        
        // Get the template to check if it's default
        $template = $this->get_template($id);
        if (!$template) {
            throw new Exception('Template not found');
        }
        
        // Don't allow deletion of default templates
        if ($template->is_default) {
            throw new Exception('Cannot delete default template. Please set another template as default first.');
        }
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $id),
            array('%d')
        );
        
        if ($result === false) {
            throw new Exception('Failed to delete template: ' . $wpdb->last_error);
        }
        
        return $result > 0;
    }
    
    /**
     * Set a template as default
     */
    public function set_default_template($id) {
        global $wpdb;
        
        $table_name = Alumni_Database::get_table_name('headers_footers');
        
        // Get the template
        $template = $this->get_template($id);
        if (!$template) {
            throw new Exception('Template not found');
        }
        
        // Remove default flag from other templates of same type
        $wpdb->update(
            $table_name,
            array('is_default' => 0),
            array('type' => $template->type),
            array('%d'),
            array('%s')
        );
        
        // Set this template as default
        $result = $wpdb->update(
            $table_name,
            array('is_default' => 1, 'updated_at' => current_time('mysql')),
            array('id' => $id),
            array('%d', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            throw new Exception('Failed to set default template: ' . $wpdb->last_error);
        }
        
        return true;
    }
    
    /**
     * Apply templates to email content
     */
    public function apply_templates($content, $header_id = null, $footer_id = null) {
        $header_content = '';
        $footer_content = '';
        
        // Get header
        if ($header_id) {
            $header = $this->get_template($header_id);
            if ($header && $header->type === 'header') {
                $header_content = $header->content;
            }
        } else {
            // Use default header
            $default_header = $this->get_default_template('header');
            if ($default_header) {
                $header_content = $default_header->content;
            }
        }
        
        // Get footer
        if ($footer_id) {
            $footer = $this->get_template($footer_id);
            if ($footer && $footer->type === 'footer') {
                $footer_content = $footer->content;
            }
        } else {
            // Use default footer
            $default_footer = $this->get_default_template('footer');
            if ($default_footer) {
                $footer_content = $default_footer->content;
            }
        }
        
        // Combine header + content + footer
        return $header_content . "\n\n" . $content . "\n\n" . $footer_content;
    }
    
    /**
     * Get template statistics
     */
    public function get_template_stats() {
        global $wpdb;
        
        $table_name = Alumni_Database::get_table_name('headers_footers');
        
        $stats = array(
            'total' => 0,
            'headers' => 0,
            'footers' => 0,
            'default_header' => null,
            'default_footer' => null
        );
        
        $results = $wpdb->get_results("SELECT type, is_default, COUNT(*) as count FROM $table_name GROUP BY type, is_default");
        
        foreach ($results as $result) {
            $stats['total'] += $result->count;
            
            if ($result->type === 'header') {
                $stats['headers'] += $result->count;
                if ($result->is_default) {
                    $stats['default_header'] = $this->get_default_template('header');
                }
            } else {
                $stats['footers'] += $result->count;
                if ($result->is_default) {
                    $stats['default_footer'] = $this->get_default_template('footer');
                }
            }
        }
        
        return $stats;
    }
    
    /**
     * Duplicate a template
     */
    public function duplicate_template($id, $new_name = null) {
        $original = $this->get_template($id);
        if (!$original) {
            throw new Exception('Template not found');
        }
        
        if (!$new_name) {
            $new_name = $original->name . ' (Copy)';
        }
        
        return $this->save_template(
            $new_name,
            $original->type,
            $original->content,
            false // Don't set copy as default
        );
    }
}