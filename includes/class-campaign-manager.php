<?php
/**
 * Campaign management class for Alumni Bulk Email plugin
 * 
 * Handles email campaign operations, history, and tracking
 */

if (!defined('ABSPATH')) {
    exit;
}

class Alumni_Campaign_Manager {
    
    private $email_service;
    private $list_manager;
    
    public function __construct($email_service = null, $list_manager = null) {
        $this->email_service = $email_service ?: new Alumni_Email_Service();
        $this->list_manager = $list_manager ?: new Alumni_List_Manager();
    }
    
    /**
     * Create a new campaign record
     */
    public function create_campaign($campaign_name, $subject, $content, $recipients_count, $recipients_data = null) {
        global $wpdb;
        
        $table = Alumni_Database::get_table_name('email_campaigns');
        
        $result = $wpdb->insert(
            $table,
            array(
                'campaign_name' => $campaign_name,
                'subject' => $subject,
                'content' => $content,
                'total_recipients' => $recipients_count,
                'recipients_data' => $recipients_data ? json_encode($recipients_data) : null,
                'status' => 'draft'
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s')
        );
        
        if ($result === false) {
            // Check if error is due to missing column and try to fix it
            if (strpos($wpdb->last_error, "Unknown column") !== false) {
                error_log('Alumni Bulk Email - Attempting to fix missing columns in campaigns table');
                Alumni_Database::update_table_schema();
                
                // Retry the insert after schema update
                $result = $wpdb->insert(
                    $table,
                    array(
                        'campaign_name' => $campaign_name,
                        'subject' => $subject,
                        'content' => $content,
                        'total_recipients' => $recipients_count,
                        'recipients_data' => $recipients_data ? json_encode($recipients_data) : null,
                        'status' => 'draft'
                    ),
                    array('%s', '%s', '%s', '%d', '%s', '%s')
                );
                
                if ($result === false) {
                    throw new Exception('Failed to create campaign even after schema update: ' . $wpdb->last_error);
                }
            } else {
                throw new Exception('Failed to create campaign: ' . $wpdb->last_error);
            }
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Save/update campaign
     */
    public function save_campaign($campaign_name, $subject, $content, $campaign_id = null) {
        global $wpdb;
        
        $table = Alumni_Database::get_table_name('email_campaigns');
        
        if ($campaign_id) {
            // Update existing campaign
            $result = $wpdb->update(
                $table,
                array(
                    'campaign_name' => $campaign_name,
                    'subject' => $subject,
                    'content' => $content,
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $campaign_id),
                array('%s', '%s', '%s', '%s'),
                array('%d')
            );
            
            if ($result === false) {
                // Check if error is due to missing column and try to fix it
                if (strpos($wpdb->last_error, "Unknown column") !== false) {
                    error_log('Alumni Bulk Email - Attempting to fix missing columns in campaigns table for update');
                    Alumni_Database::update_table_schema();
                    
                    // Retry the update after schema update
                    $result = $wpdb->update(
                        $table,
                        array(
                            'campaign_name' => $campaign_name,
                            'subject' => $subject,
                            'content' => $content,
                            'updated_at' => current_time('mysql')
                        ),
                        array('id' => $campaign_id),
                        array('%s', '%s', '%s', '%s'),
                        array('%d')
                    );
                    
                    if ($result === false) {
                        throw new Exception('Failed to update campaign even after schema update: ' . $wpdb->last_error);
                    }
                } else {
                    throw new Exception('Failed to update campaign: ' . $wpdb->last_error);
                }
            }
            
            return $campaign_id;
        } else {
            // Create new campaign
            return $this->create_campaign($campaign_name, $subject, $content, 0);
        }
    }
    
    /**
     * Get campaign by ID
     */
    public function get_campaign($campaign_id) {
        global $wpdb;
        
        $table = Alumni_Database::get_table_name('email_campaigns');
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $campaign_id
        ));
        
        if (!$campaign) {
            throw new Exception('Campaign not found');
        }
        
        // Decode recipients data if exists
        if ($campaign->recipients_data) {
            $campaign->recipients = json_decode($campaign->recipients_data, true);
        }
        
        return $campaign;
    }
    
    /**
     * Get all campaigns
     */
    public function get_all_campaigns() {
        global $wpdb;
        
        $table = Alumni_Database::get_table_name('email_campaigns');
        $campaigns = $wpdb->get_results(
            "SELECT id, campaign_name, subject, total_recipients, status, sent_at, created_at 
             FROM $table 
             ORDER BY created_at DESC"
        );
        
        return $campaigns ?: array();
    }
    
    /**
     * Delete campaign
     */
    public function delete_campaign($campaign_id) {
        global $wpdb;
        
        $table = Alumni_Database::get_table_name('email_campaigns');
        $result = $wpdb->delete(
            $table,
            array('id' => $campaign_id),
            array('%d')
        );
        
        if ($result === false) {
            throw new Exception('Failed to delete campaign: ' . $wpdb->last_error);
        }
        
        // Also delete related email logs
        $logs_table = Alumni_Database::get_table_name('email_logs');
        $wpdb->delete(
            $logs_table,
            array('campaign_id' => $campaign_id),
            array('%d')
        );
        
        return true;
    }
    
    /**
     * Copy/duplicate campaign
     */
    public function copy_campaign($campaign_id, $new_name = null) {
        $campaign = $this->get_campaign($campaign_id);
        
        if (!$new_name) {
            $new_name = $campaign->campaign_name . ' (Copy)';
        }
        
        return $this->create_campaign(
            $new_name,
            $campaign->subject,
            $campaign->content,
            0 // Reset recipient count for copy
        );
    }
    
    /**
     * Queue campaign for background processing
     */
    public function queue_campaign($recipients, $subject, $content, $campaign_name, $email_column = 'email') {
        // Create campaign record
        $campaign_id = $this->create_campaign($campaign_name, $subject, $content, count($recipients), $recipients);
        
        // Store additional campaign metadata
        global $wpdb;
        $table = Alumni_Database::get_table_name('email_campaigns');
        $wpdb->update(
            $table,
            array(
                'status' => 'queued',
                'email_column' => $email_column,
                'batch_size' => 50, // Process 50 emails at a time
                'processed_count' => 0,
                'batch_current' => 0
            ),
            array('id' => $campaign_id),
            array('%s', '%s', '%d', '%d', '%d'),
            array('%d')
        );
        
        // Schedule first batch for immediate processing
        wp_schedule_single_event(time() + 10, 'alumni_process_campaign_batch', array($campaign_id));
        
        return array(
            'campaign_id' => $campaign_id,
            'status' => 'queued',
            'total_recipients' => count($recipients)
        );
    }
    
    /**
     * Queue existing campaign for background processing
     */
    public function queue_campaign_by_id($campaign_id) {
        global $wpdb;
        $table = Alumni_Database::get_table_name('email_campaigns');
        
        // Get campaign details
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $campaign_id
        ));
        
        if (!$campaign) {
            throw new Exception('Campaign not found');
        }
        
        if ($campaign->status !== 'draft') {
            throw new Exception('Campaign is not in draft status');
        }
        
        // Update campaign status to queued
        $wpdb->update(
            $table,
            array(
                'status' => 'queued',
                'updated_at' => current_time('mysql')
            ),
            array('id' => $campaign_id),
            array('%s', '%s'),
            array('%d')
        );
        
        // Schedule first batch for immediate processing
        wp_schedule_single_event(time() + 10, 'alumni_process_campaign_batch', array($campaign_id));
        
        return array(
            'campaign_id' => $campaign_id,
            'status' => 'queued',
            'total_recipients' => $campaign->total_recipients
        );
    }
    
    /**
     * Send campaign to recipients (legacy synchronous method)
     */
    public function send_campaign($recipients, $subject, $content, $campaign_name, $email_column = 'email') {
        // Create campaign record
        $campaign_id = $this->create_campaign($campaign_name, $subject, $content, count($recipients), $recipients);
        
        try {
            // Mark campaign as sending
            $this->update_campaign_status($campaign_id, 'sending');
            
            // Send emails
            $result = $this->email_service->send_bulk_campaign($recipients, $subject, $content, $campaign_id, $email_column);
            
            // Mark campaign as completed
            $this->update_campaign_status($campaign_id, 'completed', current_time('mysql'));
            
            return array(
                'campaign_id' => $campaign_id,
                'sent' => $result['sent'],
                'failed' => $result['failed'],
                'errors' => $result['errors']
            );
            
        } catch (Exception $e) {
            // Mark campaign as failed
            $this->update_campaign_status($campaign_id, 'failed');
            throw $e;
        }
    }
    
    /**
     * Process a batch of campaign emails
     */
    public function process_campaign_batch($campaign_id) {
        global $wpdb;
        $table = Alumni_Database::get_table_name('email_campaigns');
        
        // Get campaign details
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $campaign_id
        ));
        
        if (!$campaign || $campaign->status === 'completed') {
            return false;
        }
        
        // Parse recipients data
        $all_recipients = json_decode($campaign->recipients_data, true);
        if (!$all_recipients) {
            $this->update_campaign_status($campaign_id, 'failed');
            return false;
        }
        
        // Calculate batch range
        $batch_size = $campaign->batch_size ?: 50;
        $start_index = $campaign->processed_count;
        $end_index = min($start_index + $batch_size, count($all_recipients));
        $batch_recipients = array_slice($all_recipients, $start_index, $batch_size);
        
        if (empty($batch_recipients)) {
            // No more recipients to process
            $this->update_campaign_status($campaign_id, 'completed', current_time('mysql'));
            return true;
        }
        
        // Mark as processing if not already
        if ($campaign->status === 'queued') {
            $this->update_campaign_status($campaign_id, 'sending');
        }
        
        $sent_count = 0;
        $failed_count = 0;
        $errors = array();
        
        // Process this batch
        foreach ($batch_recipients as $recipient) {
            try {
                // Extract email address using specified column
                $email_column = $campaign->email_column ?: 'email';
                $email = isset($recipient[$email_column]) ? trim($recipient[$email_column]) : '';
                
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $failed_count++;
                    continue;
                }
                
                // Check if email is unsubscribed or bounced
                if ($this->email_service->is_email_unsubscribed($email) || 
                    $this->email_service->is_email_bounced($email)) {
                    $failed_count++;
                    continue;
                }
                
                // Personalize content
                $personalized_subject = $this->email_service->personalize_content($campaign->subject, $recipient);
                $personalized_content = $this->email_service->personalize_content($campaign->content, $recipient);
                
                // Add unsubscribe link
                $personalized_content = $this->email_service->add_unsubscribe_link($personalized_content, $email);
                
                // Send email
                $result = $this->email_service->send_email($email, $personalized_subject, $personalized_content);
                
                // Log success
                $this->email_service->log_email_attempt($campaign_id, $recipient, 'sent', $result);
                $sent_count++;
                
                // Small delay to avoid overwhelming Mailgun
                usleep(200000); // 0.2 seconds = ~5 emails per second
                
            } catch (Exception $e) {
                $failed_count++;
                $errors[] = "Failed to send to {$email}: " . $e->getMessage();
                error_log("Alumni Bulk Email Batch - " . end($errors));
                
                // Log failure
                $this->email_service->log_email_attempt($campaign_id, $recipient, 'failed', array('error' => $e->getMessage()));
            }
        }
        
        // Update campaign progress
        $new_processed_count = $start_index + count($batch_recipients);
        $wpdb->update(
            $table,
            array(
                'processed_count' => $new_processed_count,
                'batch_current' => $campaign->batch_current + 1,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $campaign_id),
            array('%d', '%d', '%s'),
            array('%d')
        );
        
        // Schedule next batch if there are more recipients
        if ($new_processed_count < $campaign->total_recipients) {
            wp_schedule_single_event(time() + 30, 'alumni_process_campaign_batch', array($campaign_id));
        } else {
            // Campaign completed
            $this->update_campaign_status($campaign_id, 'completed', current_time('mysql'));
        }
        
        return array(
            'batch_sent' => $sent_count,
            'batch_failed' => $failed_count,
            'total_processed' => $new_processed_count,
            'total_recipients' => $campaign->total_recipients,
            'errors' => $errors
        );
    }
    
    /**
     * Get campaign progress
     */
    public function get_campaign_progress($campaign_id) {
        global $wpdb;
        $table = Alumni_Database::get_table_name('email_campaigns');
        
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT id, campaign_name, status, processed_count, total_recipients, 
                    batch_current, batch_size, created_at, updated_at
             FROM $table WHERE id = %d",
            $campaign_id
        ));
        
        if (!$campaign) {
            return false;
        }
        
        // Get send statistics from logs
        $logs_table = Alumni_Database::get_table_name('email_logs');
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_attempts,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count
            FROM $logs_table 
            WHERE campaign_id = %d
        ", $campaign_id), ARRAY_A);
        
        $progress_percentage = $campaign->total_recipients > 0 
            ? round(($campaign->processed_count / $campaign->total_recipients) * 100, 1)
            : 0;
        
        return array(
            'campaign_id' => $campaign->id,
            'campaign_name' => $campaign->campaign_name,
            'status' => $campaign->status,
            'processed_count' => $campaign->processed_count,
            'total_recipients' => $campaign->total_recipients,
            'progress_percentage' => $progress_percentage,
            'batch_current' => $campaign->batch_current,
            'batch_size' => $campaign->batch_size,
            'sent_count' => $stats['sent_count'] ?: 0,
            'failed_count' => $stats['failed_count'] ?: 0,
            'created_at' => $campaign->created_at,
            'updated_at' => $campaign->updated_at
        );
    }
    
    /**
     * Send test email
     */
    public function send_test_email($test_email, $subject, $content) {
        if (!filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid test email address');
        }
        
        // Create test recipient
        $test_recipient = array(
            'email' => $test_email,
            'name' => 'Test User',
            'first_name' => 'Test',
            'last_name' => 'User'
        );
        
        // Personalize content
        $personalized_subject = $this->email_service->personalize_content($subject, $test_recipient);
        $personalized_content = $this->email_service->personalize_content($content, $test_recipient);
        
        // Add test prefix to subject
        $personalized_subject = '[TEST] ' . $personalized_subject;
        
        return $this->email_service->send_email($test_email, $personalized_subject, $personalized_content);
    }
    
    /**
     * Update campaign status
     */
    private function update_campaign_status($campaign_id, $status, $sent_at = null) {
        global $wpdb;
        
        $table = Alumni_Database::get_table_name('email_campaigns');
        
        $update_data = array(
            'status' => $status,
            'updated_at' => current_time('mysql')
        );
        
        if ($sent_at) {
            $update_data['sent_at'] = $sent_at;
        }
        
        $wpdb->update(
            $table,
            $update_data,
            array('id' => $campaign_id),
            array('%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Get campaign statistics
     */
    public function get_campaign_stats($campaign_id) {
        return $this->email_service->get_campaign_stats($campaign_id);
    }
    
    /**
     * Get campaign logs
     */
    public function get_campaign_logs($campaign_id, $limit = 100, $offset = 0) {
        global $wpdb;
        
        $table = Alumni_Database::get_table_name('email_logs');
        
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE campaign_id = %d 
             ORDER BY sent_at DESC 
             LIMIT %d OFFSET %d",
            $campaign_id,
            $limit,
            $offset
        ));
        
        return $logs ?: array();
    }
    
    /**
     * Get all email logs (for admin view)
     */
    public function get_all_logs($limit = 100, $offset = 0) {
        global $wpdb;
        
        $logs_table = Alumni_Database::get_table_name('email_logs');
        $campaigns_table = Alumni_Database::get_table_name('email_campaigns');
        
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT l.*, c.campaign_name 
             FROM $logs_table l 
             LEFT JOIN $campaigns_table c ON l.campaign_id = c.id 
             ORDER BY l.sent_at DESC 
             LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
        
        return $logs ?: array();
    }
    
    /**
     * Export campaign logs
     */
    public function export_campaign_logs($campaign_id, $format = 'csv') {
        $logs = $this->get_campaign_logs($campaign_id, 10000); // Get all logs
        
        if (empty($logs)) {
            throw new Exception('No logs found for this campaign');
        }
        
        // Convert logs to array format
        $export_data = array();
        foreach ($logs as $log) {
            $export_data[] = array(
                'Campaign ID' => $log->campaign_id,
                'Email' => $log->recipient_email,
                'Name' => $log->recipient_name,
                'Status' => $log->status,
                'Sent At' => $log->sent_at,
                'Opened At' => $log->opened_at,
                'Clicked At' => $log->clicked_at,
                'Bounce Reason' => $log->bounce_reason,
                'Message ID' => $log->mailgun_message_id
            );
        }
        
        // Get campaign info for filename
        $campaign = $this->get_campaign($campaign_id);
        $safe_campaign_name = sanitize_file_name($campaign->campaign_name);
        $timestamp = current_time('Y-m-d_H-i-s');
        
        if ($format === 'excel') {
            $filename = $safe_campaign_name . '_logs_' . $timestamp . '.xlsx';
            $file_processor = new Alumni_File_Processor();
            $content = $file_processor->generate_excel_content($export_data);
            $content_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        } else {
            $filename = $safe_campaign_name . '_logs_' . $timestamp . '.csv';
            $file_processor = new Alumni_File_Processor();
            $content = $file_processor->generate_csv_content($export_data);
            $content_type = 'text/csv; charset=utf-8';
        }
        
        return array(
            'filename' => $filename,
            'content' => $content,
            'content_type' => $content_type,
            'log_count' => count($export_data)
        );
    }
    
    /**
     * Get campaign performance summary
     */
    public function get_performance_summary() {
        global $wpdb;
        
        $campaigns_table = Alumni_Database::get_table_name('email_campaigns');
        $logs_table = Alumni_Database::get_table_name('email_logs');
        
        $summary = $wpdb->get_row("
            SELECT 
                COUNT(DISTINCT c.id) as total_campaigns,
                SUM(c.total_recipients) as total_emails_sent,
                COUNT(CASE WHEN l.status = 'delivered' THEN 1 END) as total_delivered,
                COUNT(CASE WHEN l.status IN ('bounced', 'failed') THEN 1 END) as total_bounced,
                COUNT(CASE WHEN l.opened_at IS NOT NULL THEN 1 END) as total_opened,
                COUNT(CASE WHEN l.clicked_at IS NOT NULL THEN 1 END) as total_clicked
            FROM $campaigns_table c
            LEFT JOIN $logs_table l ON c.id = l.campaign_id
            WHERE c.status = 'completed'
        ", ARRAY_A);
        
        // Calculate rates
        if ($summary['total_emails_sent'] > 0) {
            $summary['delivery_rate'] = round(($summary['total_delivered'] / $summary['total_emails_sent']) * 100, 2);
            $summary['bounce_rate'] = round(($summary['total_bounced'] / $summary['total_emails_sent']) * 100, 2);
        }
        
        if ($summary['total_delivered'] > 0) {
            $summary['open_rate'] = round(($summary['total_opened'] / $summary['total_delivered']) * 100, 2);
            $summary['click_rate'] = round(($summary['total_clicked'] / $summary['total_delivered']) * 100, 2);
        }
        
        return $summary;
    }
}