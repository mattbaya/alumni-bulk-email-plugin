<?php
/**
 * List management class for Alumni Bulk Email plugin
 * 
 * Handles recipient list operations, merging, tagging, and management
 */

if (!defined('ABSPATH')) {
    exit;
}

class Alumni_List_Manager {
    
    private $file_processor;
    private $email_service;
    
    public function __construct($file_processor = null, $email_service = null) {
        $this->file_processor = $file_processor ?: new Alumni_File_Processor();
        $this->email_service = $email_service ?: new Alumni_Email_Service($this->file_processor);
    }
    
    /**
     * Create a new recipient list
     */
    public function create_list($list_name, $recipients, $description = '') {
        global $wpdb;
        
        if (empty($list_name)) {
            throw new Exception('List name is required');
        }
        
        if (empty($recipients) || !is_array($recipients)) {
            throw new Exception('Recipients data is required');
        }
        
        // Check if list name already exists
        $table = Alumni_Database::get_table_name('recipient_lists');
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE list_name = %s",
            $list_name
        ));
        
        if ($existing > 0) {
            throw new Exception('A list with this name already exists');
        }
        
        // Insert new list
        $result = $wpdb->insert(
            $table,
            array(
                'list_name' => $list_name,
                'description' => $description,
                'recipients_data' => json_encode($recipients),
                'total_count' => count($recipients)
            ),
            array('%s', '%s', '%s', '%d')
        );
        
        if ($result === false) {
            throw new Exception('Failed to create list: ' . $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get recipient list by ID
     */
    public function get_list($list_id) {
        global $wpdb;
        
        $table = Alumni_Database::get_table_name('recipient_lists');
        $list = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $list_id
        ));
        
        if (!$list) {
            throw new Exception('List not found');
        }
        
        // Decode recipients data
        $list->recipients = json_decode($list->recipients_data, true);
        
        return $list;
    }
    
    /**
     * Get all recipient lists
     */
    public function get_all_lists() {
        global $wpdb;
        
        $table = Alumni_Database::get_table_name('recipient_lists');
        $lists = $wpdb->get_results(
            "SELECT id, list_name, description, total_count, created_at FROM $table ORDER BY created_at DESC"
        );
        
        return $lists ?: array();
    }
    
    /**
     * Update recipient list
     */
    public function update_list($list_id, $recipients, $list_name = null) {
        global $wpdb;
        
        $table = Alumni_Database::get_table_name('recipient_lists');
        
        $update_data = array(
            'recipients_data' => json_encode($recipients),
            'total_count' => count($recipients),
            'updated_at' => current_time('mysql')
        );
        
        if ($list_name !== null) {
            $update_data['list_name'] = $list_name;
        }
        
        $result = $wpdb->update(
            $table,
            $update_data,
            array('id' => $list_id),
            array('%s', '%d', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            throw new Exception('Failed to update list: ' . $wpdb->last_error);
        }
        
        return true;
    }
    
    /**
     * Delete recipient list
     */
    public function delete_list($list_id) {
        global $wpdb;
        
        $table = Alumni_Database::get_table_name('recipient_lists');
        $result = $wpdb->delete(
            $table,
            array('id' => $list_id),
            array('%d')
        );
        
        if ($result === false) {
            throw new Exception('Failed to delete list: ' . $wpdb->last_error);
        }
        
        return true;
    }
    
    /**
     * Merge two recipient lists with deduplication
     */
    public function merge_lists($target_list_id, $new_recipients) {
        // Get existing list
        $existing_list = $this->get_list($target_list_id);
        $existing_recipients = $existing_list->recipients;
        
        // Merge and deduplicate
        $merged_recipients = $this->merge_and_deduplicate_recipients($existing_recipients, $new_recipients);
        
        // Update the list
        $this->update_list($target_list_id, $merged_recipients);
        
        return array(
            'total_recipients' => count($merged_recipients),
            'new_recipients' => count($merged_recipients) - count($existing_recipients),
            'duplicates_removed' => (count($existing_recipients) + count($new_recipients)) - count($merged_recipients)
        );
    }
    
    /**
     * Add campaign tags to a recipient list
     */
    public function add_campaign_tags_to_list($list_id, $campaign_name, $date) {
        $list = $this->get_list($list_id);
        $recipients = $list->recipients;
        
        if (empty($recipients)) {
            throw new Exception('No recipients found in list');
        }
        
        $campaign_tag = $campaign_name . ' (' . $date . ')';
        
        foreach ($recipients as &$recipient) {
            $current_tags = isset($recipient['tags']) ? $recipient['tags'] : '';
            if ($current_tags) {
                $tag_array = array_map('trim', explode(',', $current_tags));
                $tag_array[] = $campaign_tag;
                $unique_tags = array_unique($tag_array);
                $recipient['tags'] = implode(', ', $unique_tags);
            } else {
                $recipient['tags'] = $campaign_tag;
            }
        }
        
        // Update the list with new tags
        $this->update_list($list_id, $recipients);
        
        return true;
    }
    
    /**
     * Bulk edit recipients in a list
     */
    public function bulk_edit_recipients($list_id, $recipient_indices, $updates) {
        $list = $this->get_list($list_id);
        $recipients = $list->recipients;
        
        foreach ($recipient_indices as $index) {
            if (isset($recipients[$index])) {
                foreach ($updates as $field => $value) {
                    if ($field === 'tags' && !empty($value)) {
                        // Add tags instead of replacing
                        $current_tags = isset($recipients[$index]['tags']) ? $recipients[$index]['tags'] : '';
                        if ($current_tags) {
                            $tag_array = array_map('trim', explode(',', $current_tags));
                            $new_tags = array_map('trim', explode(',', $value));
                            $all_tags = array_merge($tag_array, $new_tags);
                            $recipients[$index]['tags'] = implode(', ', array_unique($all_tags));
                        } else {
                            $recipients[$index]['tags'] = $value;
                        }
                    } else {
                        $recipients[$index][$field] = $value;
                    }
                }
            }
        }
        
        $this->update_list($list_id, $recipients);
        
        return count($recipient_indices);
    }
    
    /**
     * Delete recipients from a list
     */
    public function bulk_delete_recipients($list_id, $recipient_indices) {
        $list = $this->get_list($list_id);
        $recipients = $list->recipients;
        
        // Sort indices in descending order to avoid index shifting issues
        rsort($recipient_indices);
        
        foreach ($recipient_indices as $index) {
            if (isset($recipients[$index])) {
                unset($recipients[$index]);
            }
        }
        
        // Re-index the array
        $recipients = array_values($recipients);
        
        $this->update_list($list_id, $recipients);
        
        return count($recipient_indices);
    }
    
    /**
     * Search recipients in a list
     */
    public function search_recipients($list_id, $search_term, $search_column = null) {
        $list = $this->get_list($list_id);
        $recipients = $list->recipients;
        
        if (empty($search_term)) {
            return $recipients;
        }
        
        $search_term = strtolower($search_term);
        $filtered_recipients = array();
        
        foreach ($recipients as $index => $recipient) {
            $match_found = false;
            
            if ($search_column && isset($recipient[$search_column])) {
                // Search in specific column
                if (strpos(strtolower($recipient[$search_column]), $search_term) !== false) {
                    $match_found = true;
                }
            } else {
                // Search in all columns
                foreach ($recipient as $field => $value) {
                    if (strpos(strtolower((string)$value), $search_term) !== false) {
                        $match_found = true;
                        break;
                    }
                }
            }
            
            if ($match_found) {
                $filtered_recipients[] = $recipient;
            }
        }
        
        return $filtered_recipients;
    }
    
    /**
     * Get available columns from a list
     */
    public function get_list_columns($list_id) {
        $list = $this->get_list($list_id);
        $recipients = $list->recipients;
        
        if (empty($recipients)) {
            return array();
        }
        
        // Get columns from first recipient (all should have same structure)
        $columns = array_keys($recipients[0]);
        
        return $columns;
    }
    
    /**
     * Get all available columns across all lists
     */
    public function get_all_available_columns() {
        global $wpdb;
        
        $table = Alumni_Database::get_table_name('recipient_lists');
        $all_lists = $wpdb->get_results("SELECT recipients_data FROM $table");
        
        $all_columns = array();
        
        foreach ($all_lists as $list) {
            $recipients = json_decode($list->recipients_data, true);
            if (!empty($recipients) && is_array($recipients)) {
                $columns = array_keys($recipients[0]);
                $all_columns = array_merge($all_columns, $columns);
            }
        }
        
        return array_unique($all_columns);
    }
    
    /**
     * Create sublist from filtered results
     */
    public function create_sublist_from_results($sublist_name, $filtered_recipients, $parent_list_id = null) {
        if (empty($filtered_recipients)) {
            throw new Exception('No recipients to create sublist');
        }
        
        $description = 'Sublist created from filtered results';
        if ($parent_list_id) {
            $description .= ' of list ID ' . $parent_list_id;
        }
        
        return $this->create_list($sublist_name, $filtered_recipients, $description);
    }
    
    /**
     * Export list with filtering options
     */
    public function export_list($list_id, $exclude_bounced = false, $exclude_unsubscribed = false, $format = 'csv') {
        $list = $this->get_list($list_id);
        $recipients = $list->recipients;
        
        // Apply filters
        $filtered_recipients = $this->filter_recipients_for_export($recipients, $exclude_bounced, $exclude_unsubscribed);
        
        if (empty($filtered_recipients)) {
            throw new Exception('No recipients remain after applying filters');
        }
        
        // Generate filename
        $safe_list_name = sanitize_file_name($list->list_name);
        $timestamp = current_time('Y-m-d_H-i-s');
        
        if ($format === 'excel') {
            $filename = $safe_list_name . '_export_' . $timestamp . '.xlsx';
            $content = $this->file_processor->generate_excel_content($filtered_recipients);
            $content_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        } else {
            $filename = $safe_list_name . '_export_' . $timestamp . '.csv';
            $content = $this->file_processor->generate_csv_content($filtered_recipients);
            $content_type = 'text/csv; charset=utf-8';
        }
        
        return array(
            'filename' => $filename,
            'content' => $content,
            'content_type' => $content_type,
            'recipient_count' => count($filtered_recipients)
        );
    }
    
    /**
     * Filter recipients for export based on bounced/unsubscribed status
     */
    private function filter_recipients_for_export($recipients, $exclude_bounced, $exclude_unsubscribed) {
        $filtered = array();
        
        foreach ($recipients as $recipient) {
            $email = $this->file_processor->extract_email_from_recipient($recipient);
            
            if (!$email) {
                continue; // Skip recipients without email
            }
            
            // Check if should exclude bounced emails
            if ($exclude_bounced && $this->email_service->is_email_bounced($email)) {
                continue;
            }
            
            // Check if should exclude unsubscribed emails  
            if ($exclude_unsubscribed && $this->email_service->is_email_unsubscribed($email)) {
                continue;
            }
            
            $filtered[] = $recipient;
        }
        
        return $filtered;
    }
    
    /**
     * Merge and deduplicate recipients
     */
    private function merge_and_deduplicate_recipients($existing_recipients, $new_recipients) {
        $merged = $existing_recipients;
        $existing_emails = array();
        
        // Build index of existing emails
        foreach ($existing_recipients as $recipient) {
            $email = $this->file_processor->extract_email_from_recipient($recipient);
            if ($email) {
                $existing_emails[strtolower($email)] = true;
            }
        }
        
        // Add new recipients if they don't already exist
        foreach ($new_recipients as $recipient) {
            $email = $this->file_processor->extract_email_from_recipient($recipient);
            if ($email && !isset($existing_emails[strtolower($email)])) {
                $merged[] = $recipient;
                $existing_emails[strtolower($email)] = true;
            }
        }
        
        return $merged;
    }
    
    /**
     * Get list statistics
     */
    public function get_list_stats($list_id) {
        $list = $this->get_list($list_id);
        $recipients = $list->recipients;
        
        $total = count($recipients);
        $with_email = 0;
        $bounced = 0;
        $unsubscribed = 0;
        
        foreach ($recipients as $recipient) {
            $email = $this->file_processor->extract_email_from_recipient($recipient);
            if ($email) {
                $with_email++;
                
                if ($this->email_service->is_email_bounced($email)) {
                    $bounced++;
                }
                
                if ($this->email_service->is_email_unsubscribed($email)) {
                    $unsubscribed++;
                }
            }
        }
        
        return array(
            'total' => $total,
            'with_email' => $with_email,
            'active' => $with_email - $bounced - $unsubscribed,
            'bounced' => $bounced,
            'unsubscribed' => $unsubscribed
        );
    }
}