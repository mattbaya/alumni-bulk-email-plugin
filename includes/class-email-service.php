<?php
/**
 * Email service class for Alumni Bulk Email plugin
 * 
 * Handles Mailgun integration, email sending, webhook processing, and bounce management
 */

if (!defined('ABSPATH')) {
    exit;
}

class Alumni_Email_Service {
    
    private $file_processor;
    
    public function __construct($file_processor = null) {
        $this->file_processor = $file_processor ?: new Alumni_File_Processor();
    }
    
    /**
     * Check if Mailgun is properly configured
     */
    public function is_mailgun_configured() {
        $api_key = get_option('alumni_bulk_email_mailgun_api_key');
        $domain = get_option('alumni_bulk_email_mailgun_domain');
        $from_email = get_option('alumni_bulk_email_from_email');
        
        return !empty($api_key) && !empty($domain) && !empty($from_email);
    }
    
    /**
     * Validate Mailgun configuration
     */
    public function validate_mailgun_config() {
        $api_key = get_option('alumni_bulk_email_mailgun_api_key');
        $domain = get_option('alumni_bulk_email_mailgun_domain');
        $from_email = get_option('alumni_bulk_email_from_email');
        
        $errors = array();
        
        if (empty($api_key)) {
            $errors[] = 'Mailgun API key is missing';
        } elseif (!preg_match('/^[a-f0-9]{32}-[a-f0-9]{8}-[a-f0-9]{8}$/', $api_key)) {
            $errors[] = 'Mailgun API key format is invalid (should be like: xxxxxxxx-xxxx-xxxx where x is a lowercase letter or number)';
        }
        
        if (empty($domain)) {
            $errors[] = 'Mailgun domain is missing';
        } elseif (strpos($domain, 'http') !== false) {
            $errors[] = 'Mailgun domain should not include http:// or https://';
        }
        
        if (empty($from_email)) {
            $errors[] = 'From email address is missing';
        } elseif (!filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'From email address is invalid';
        }
        
        return $errors;
    }
    
    /**
     * Send a single email via Mailgun
     */
    public function send_email($to, $subject, $content, $from_name = null, $attachments = null) {
        if (!$this->is_mailgun_configured()) {
            throw new Exception('Mailgun is not properly configured');
        }
        
        $api_key = get_option('alumni_bulk_email_mailgun_api_key');
        $domain = get_option('alumni_bulk_email_mailgun_domain');
        $from_email = get_option('alumni_bulk_email_from_email');
        
        if (!$from_name) {
            $from_name = get_option('alumni_bulk_email_from_name', 'Alumni Association');
        }
        
        // Debug logging for troubleshooting
        error_log('Alumni Bulk Email - Sending email to: ' . $to);
        error_log('Alumni Bulk Email - Mailgun domain: ' . (!empty($domain) ? $domain : 'EMPTY'));
        error_log('Alumni Bulk Email - API key: ' . (!empty($api_key) ? 'SET (' . strlen($api_key) . ' chars)' : 'EMPTY'));
        error_log('Alumni Bulk Email - From email: ' . (!empty($from_email) ? $from_email : 'EMPTY'));
        
        $url = "https://api.mailgun.net/v3/{$domain}/messages";
        
        $data = array(
            'from' => "{$from_name} <{$from_email}>",
            'to' => $to,
            'subject' => $subject,
            'html' => $content,
            'text' => wp_strip_all_tags($content)
        );
        
        // Handle attachments
        if ($attachments && !empty($attachments)) {
            // Note: Attachment handling would need to be implemented based on requirements
            // This is a placeholder for the structure
        }
        
        $headers = array(
            'Authorization' => 'Basic ' . base64_encode('api:' . $api_key)
        );
        
        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body' => $data,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('Failed to send email: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['message']) ? $error_data['message'] : 'Unknown error';
            
            // Enhanced error logging
            error_log('Alumni Bulk Email - Mailgun API Error:');
            error_log('  Response Code: ' . $response_code);
            error_log('  Response Body: ' . $response_body);
            error_log('  Parsed Error: ' . $error_message);
            error_log('  Request URL: ' . $url);
            
            // Provide specific guidance for common errors
            $helpful_message = $error_message;
            if ($response_code === 401) {
                $helpful_message = 'Authentication failed. Please verify your Mailgun API key is correct';
            } elseif ($response_code === 404) {
                $helpful_message = 'Domain not found. Verify your Mailgun domain name (without http://)';
            } elseif ($response_code === 403) {
                $helpful_message = 'Access forbidden. Your domain may not be verified in Mailgun';
            }
            
            // Include response code and helpful message
            throw new Exception('Mailgun API error (HTTP ' . $response_code . '): ' . $helpful_message);
        }
        
        $result = json_decode($response_body, true);
        
        // Log successful sends
        if (isset($result['id'])) {
            error_log('Alumni Bulk Email - Email sent successfully. Message ID: ' . $result['id']);
        } else {
            error_log('Alumni Bulk Email - Email sent but no message ID returned. Response: ' . $response_body);
        }
        
        return $result;
    }
    
    /**
     * Send bulk email campaign
     */
    public function send_bulk_campaign($recipients, $subject, $content, $campaign_id = null, $email_column = 'email') {
        if (empty($recipients)) {
            throw new Exception('No recipients provided');
        }
        
        $sent_count = 0;
        $failed_count = 0;
        $errors = array();
        
        foreach ($recipients as $recipient) {
            try {
                // Extract email address using specified column
                $email = isset($recipient[$email_column]) ? trim($recipient[$email_column]) : '';
                
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $failed_count++;
                    $errors[] = "Invalid email: " . $email;
                    continue;
                }
                
                // Check if email is unsubscribed
                if ($this->is_email_unsubscribed($email)) {
                    $failed_count++;
                    $errors[] = "Email unsubscribed: " . $email;
                    continue;
                }
                
                // Check if email has bounced too many times
                if ($this->is_email_bounced($email)) {
                    $failed_count++;
                    $errors[] = "Email bounced: " . $email;
                    continue;
                }
                
                // Personalize content
                $personalized_subject = $this->personalize_content($subject, $recipient);
                $personalized_content = $this->personalize_content($content, $recipient);
                
                // Add unsubscribe link
                $personalized_content = $this->add_unsubscribe_link($personalized_content, $email);
                
                // Send email
                $result = $this->send_email($email, $personalized_subject, $personalized_content);
                
                // Log success
                if ($campaign_id) {
                    $this->log_email_attempt($campaign_id, $recipient, 'sent', $result);
                }
                
                $sent_count++;
                
                // Small delay to avoid overwhelming Mailgun
                usleep(100000); // 0.1 seconds
                
            } catch (Exception $e) {
                $failed_count++;
                $error_msg = "Failed to send to {$email}: " . $e->getMessage();
                $errors[] = $error_msg;
                error_log("Alumni Bulk Email - " . $error_msg);
                
                // Log failure
                if ($campaign_id) {
                    $this->log_email_attempt($campaign_id, $recipient, 'failed', array('error' => $e->getMessage()));
                }
            }
        }
        
        return array(
            'sent' => $sent_count,
            'failed' => $failed_count,
            'errors' => $errors
        );
    }
    
    /**
     * Personalize email content with merge tags
     */
    public function personalize_content($content, $recipient) {
        $replacements = array();
        
        // Standard replacements
        $replacements['{email}'] = $this->file_processor->extract_email_from_recipient($recipient);
        $replacements['{name}'] = isset($recipient['name']) ? $recipient['name'] : '';
        $replacements['{first_name}'] = isset($recipient['first_name']) ? $recipient['first_name'] : '';
        $replacements['{last_name}'] = isset($recipient['last_name']) ? $recipient['last_name'] : '';
        
        // Dynamic replacements for any other fields
        foreach ($recipient as $key => $value) {
            if (!in_array($key, ['name', 'first_name', 'last_name', 'tags'])) {
                $replacements['{' . $key . '}'] = $value;
            }
        }
        
        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }
    
    /**
     * Add unsubscribe link to email content
     */
    private function add_unsubscribe_link($content, $email) {
        $token = $this->generate_unsubscribe_token($email);
        $unsubscribe_url = admin_url('admin-ajax.php?action=handle_unsubscribe&token=' . $token);
        
        // Replace {unsubscribe_url} placeholder if it exists
        $content = str_replace('{unsubscribe_url}', $unsubscribe_url, $content);
        
        // If no placeholder exists, add unsubscribe footer
        if (strpos($content, $unsubscribe_url) === false) {
            $unsubscribe_footer = '<br><br><hr><p style="font-size: 12px; color: #888;">
                <a href="' . $unsubscribe_url . '">Unsubscribe from these emails</a>
            </p>';
            $content .= $unsubscribe_footer;
        }
        
        return $content;
    }
    
    /**
     * Generate unsubscribe token for email
     */
    private function generate_unsubscribe_token($email) {
        return wp_hash($email . wp_salt('auth'));
    }
    
    /**
     * Check if email is unsubscribed
     */
    public function is_email_unsubscribed($email) {
        global $wpdb;
        
        $table = Alumni_Database::get_table_name('email_unsubscribes');
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE email = %s",
            $email
        ));
        
        return $result > 0;
    }
    
    /**
     * Check if email has bounced too many times
     */
    public function is_email_bounced($email) {
        global $wpdb;
        
        $table = Alumni_Database::get_table_name('email_logs');
        $bounce_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE recipient_email = %s AND status IN ('bounced', 'failed')",
            $email
        ));
        
        return $bounce_count >= 3; // Mark as bounced after 3 failures
    }
    
    /**
     * Log email sending attempt
     */
    public function log_email_attempt($campaign_id, $recipient, $status, $result) {
        global $wpdb;
        
        $table = Alumni_Database::get_table_name('email_logs');
        $email = $this->file_processor->extract_email_from_recipient($recipient);
        $name = isset($recipient['name']) ? $recipient['name'] : '';
        
        $data = array(
            'campaign_id' => $campaign_id,
            'recipient_email' => $email,
            'recipient_name' => $name,
            'status' => $status,
            'mailgun_message_id' => isset($result['id']) ? $result['id'] : null
        );
        
        if ($status === 'sent') {
            $data['sent_at'] = current_time('mysql');
        }
        
        $wpdb->insert($table, $data);
        
        if ($wpdb->last_error) {
            error_log('Alumni Bulk Email - Database error logging email: ' . $wpdb->last_error);
        }
    }
    
    /**
     * Handle Mailgun webhook events
     */
    public function handle_webhook($event_data) {
        if (!isset($event_data['event-data'])) {
            error_log('Alumni Bulk Email - Invalid webhook data received');
            return false;
        }
        
        $event = $event_data['event-data'];
        $event_type = $event['event'];
        $message_id = isset($event['message']['headers']['message-id']) ? $event['message']['headers']['message-id'] : '';
        $recipient_email = isset($event['recipient']) ? $event['recipient'] : '';
        
        error_log('Alumni Bulk Email - Webhook received: ' . $event_type . ' for ' . $recipient_email);
        
        global $wpdb;
        $table = Alumni_Database::get_table_name('email_logs');
        
        switch ($event_type) {
            case 'delivered':
                $wpdb->update(
                    $table,
                    array('status' => 'delivered'),
                    array('recipient_email' => $recipient_email, 'status' => 'sent'),
                    array('%s'),
                    array('%s', '%s')
                );
                break;
                
            case 'opened':
                $wpdb->update(
                    $table,
                    array('opened_at' => current_time('mysql')),
                    array('recipient_email' => $recipient_email),
                    array('%s'),
                    array('%s')
                );
                break;
                
            case 'clicked':
                $wpdb->update(
                    $table,
                    array('clicked_at' => current_time('mysql')),
                    array('recipient_email' => $recipient_email),
                    array('%s'),
                    array('%s')
                );
                break;
                
            case 'bounced':
            case 'failed':
                $bounce_reason = isset($event['delivery-status']['description']) ? 
                    $event['delivery-status']['description'] : 'Unknown bounce reason';
                
                $wpdb->update(
                    $table,
                    array(
                        'status' => 'bounced',
                        'bounce_reason' => $bounce_reason
                    ),
                    array('recipient_email' => $recipient_email),
                    array('%s', '%s'),
                    array('%s')
                );
                break;
        }
        
        return true;
    }
    
    /**
     * Process unsubscribe request
     */
    public function process_unsubscribe($token, $ip_address = null, $user_agent = null) {
        // Find email by token validation
        $emails = $this->get_all_emails_from_campaigns();
        
        foreach ($emails as $email) {
            if ($this->generate_unsubscribe_token($email) === $token) {
                return $this->unsubscribe_email($email, $ip_address, $user_agent);
            }
        }
        
        return false;
    }
    
    /**
     * Unsubscribe an email address
     */
    private function unsubscribe_email($email, $ip_address = null, $user_agent = null) {
        global $wpdb;
        
        $table = Alumni_Database::get_table_name('email_unsubscribes');
        
        // Check if already unsubscribed
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE email = %s",
            $email
        ));
        
        if ($existing > 0) {
            return true; // Already unsubscribed
        }
        
        // Add unsubscribe record
        $result = $wpdb->insert(
            $table,
            array(
                'email' => $email,
                'unsubscribe_token' => $this->generate_unsubscribe_token($email),
                'ip_address' => $ip_address,
                'user_agent' => $user_agent
            ),
            array('%s', '%s', '%s', '%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Get all email addresses from campaigns (for token validation)
     */
    private function get_all_emails_from_campaigns() {
        global $wpdb;
        
        $table = Alumni_Database::get_table_name('email_logs');
        $emails = $wpdb->get_col("SELECT DISTINCT recipient_email FROM $table");
        
        return $emails ?: array();
    }
    
    /**
     * Get campaign statistics
     */
    public function get_campaign_stats($campaign_id) {
        global $wpdb;
        
        $table = Alumni_Database::get_table_name('email_logs');
        
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_sent,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = 'bounced' OR status = 'failed' THEN 1 ELSE 0 END) as bounced,
                SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened,
                SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked
            FROM $table 
            WHERE campaign_id = %d
        ", $campaign_id), ARRAY_A);
        
        return $stats;
    }
}