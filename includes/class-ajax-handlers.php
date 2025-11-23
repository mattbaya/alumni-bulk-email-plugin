<?php
/**
 * AJAX handlers class for Alumni Bulk Email plugin
 * 
 * Handles all AJAX requests and coordinates between services
 */

if (!defined('ABSPATH')) {
    exit;
}

class Alumni_Ajax_Handlers {
    
    private $file_processor;
    private $email_service;
    private $list_manager;
    private $campaign_manager;
    private $header_footer_manager;
    private $typography_preset_manager;
    
    public function __construct() {
        $this->file_processor = new Alumni_File_Processor();
        $this->email_service = new Alumni_Email_Service($this->file_processor);
        $this->list_manager = new Alumni_List_Manager($this->file_processor, $this->email_service);
        $this->campaign_manager = new Alumni_Campaign_Manager($this->email_service, $this->list_manager);
        $this->header_footer_manager = new Alumni_Header_Footer_Manager();
        $this->typography_preset_manager = new Alumni_Typography_Preset_Manager();
    }
    
    /**
     * Register all AJAX handlers
     */
    public function register_handlers() {
        // Campaign handlers
        add_action('wp_ajax_send_test_email', array($this, 'handle_test_email'));
        add_action('wp_ajax_send_test_campaign', array($this, 'handle_test_campaign'));
        add_action('wp_ajax_send_bulk_email', array($this, 'handle_bulk_email'));
        add_action('wp_ajax_save_campaign', array($this, 'handle_save_campaign'));
        add_action('wp_ajax_load_campaign', array($this, 'handle_load_campaign'));
        add_action('wp_ajax_view_campaign', array($this, 'handle_view_campaign'));
        add_action('wp_ajax_delete_campaign', array($this, 'handle_delete_campaign'));
        add_action('wp_ajax_copy_campaign', array($this, 'handle_copy_campaign'));
        
        // File upload handlers
        add_action('wp_ajax_upload_csv', array($this, 'handle_csv_upload'));
        add_action('wp_ajax_upload_save_csv', array($this, 'handle_upload_save_csv'));
        
        // List management handlers
        add_action('wp_ajax_load_recipient_list', array($this, 'handle_load_recipient_list'));
        add_action('wp_ajax_create_recipient_list', array($this, 'handle_create_recipient_list'));
        add_action('wp_ajax_combine_recipient_lists', array($this, 'handle_combine_recipient_lists'));
        add_action('wp_ajax_view_recipient_list', array($this, 'handle_view_recipient_list'));
        add_action('wp_ajax_edit_recipient_row', array($this, 'handle_edit_recipient_row'));
        add_action('wp_ajax_bulk_edit_recipients', array($this, 'handle_bulk_edit_recipients'));
        add_action('wp_ajax_bulk_delete_recipients', array($this, 'handle_bulk_delete_recipients'));
        add_action('wp_ajax_get_list_columns', array($this, 'handle_get_list_columns'));
        add_action('wp_ajax_merge_csv_to_list', array($this, 'handle_merge_csv_to_list'));
        add_action('wp_ajax_export_recipient_list', array($this, 'handle_export_recipient_list'));
        add_action('wp_ajax_create_sublist_from_results', array($this, 'handle_create_sublist_from_results'));
        
        // Template handlers
        add_action('wp_ajax_load_templates', array($this, 'handle_load_templates'));
        add_action('wp_ajax_get_template', array($this, 'handle_get_template'));
        add_action('wp_ajax_save_header_footer', array($this, 'handle_save_header_footer'));
        add_action('wp_ajax_delete_header_footer', array($this, 'handle_delete_header_footer'));
        add_action('wp_ajax_set_default_header_footer', array($this, 'handle_set_default_header_footer'));
        add_action('wp_ajax_duplicate_template', array($this, 'handle_duplicate_template'));
        add_action('wp_ajax_save_typography_preset', array($this, 'handle_save_typography_preset'));
        add_action('wp_ajax_test_mailgun_connection', array($this, 'handle_test_mailgun_connection'));
        
        // Utility handlers
        add_action('wp_ajax_recreate_tables', array($this, 'handle_recreate_tables'));
        
        // Public handlers (no authentication required)
        add_action('wp_ajax_nopriv_handle_alumni_webhook', array($this, 'handle_mailgun_webhook'));
        add_action('wp_ajax_handle_alumni_webhook', array($this, 'handle_mailgun_webhook'));
        add_action('wp_ajax_nopriv_handle_unsubscribe', array($this, 'handle_unsubscribe'));
        add_action('wp_ajax_handle_unsubscribe', array($this, 'handle_unsubscribe'));
    }
    
    /**
     * Send test email
     */
    public function handle_test_email() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'send_test_email') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            $test_email = sanitize_email($_POST['test_email']);
            $subject = sanitize_text_field($_POST['subject']);
            $content = wp_kses_post($_POST['content']);
            
            if (!$test_email || !$subject || !$content) {
                throw new Exception('Missing required fields');
            }
            
            // Apply header and footer templates if selected
            $header_id = isset($_POST['header_template']) ? intval($_POST['header_template']) : null;
            $footer_id = isset($_POST['footer_template']) ? intval($_POST['footer_template']) : null;
            
            if ($header_id || $footer_id) {
                $content = $this->header_footer_manager->apply_templates($content, $header_id, $footer_id);
            }
            
            $result = $this->campaign_manager->send_test_email($test_email, $subject, $content);
            
            echo json_encode(array(
                'success' => true,
                'data' => array('message' => 'Test email sent successfully!')
            ));
            
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'data' => array('message' => 'Error: ' . $e->getMessage())
            ));
        }
        
        exit;
    }
    
    /**
     * Handle CSV upload and preview
     */
    public function handle_csv_upload() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'csv_upload') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File upload error');
            }
            
            $this->file_processor->validate_file_upload($_FILES['csv_file']);
            $recipients = $this->file_processor->parse_file($_FILES['csv_file']['tmp_name'], null);
            
            if (empty($recipients)) {
                throw new Exception('No valid recipients found in file');
            }
            
            echo json_encode(array(
                'success' => true,
                'data' => array(
                    'recipients' => array_slice($recipients, 0, 10), // Preview first 10
                    'total_count' => count($recipients),
                    'columns' => !empty($recipients) ? array_keys($recipients[0]) : array()
                )
            ));
            
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'data' => array('message' => 'Error: ' . $e->getMessage())
            ));
        }
        
        exit;
    }
    
    /**
     * Handle bulk email sending
     */
    public function handle_bulk_email() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'send_bulk_email') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            $subject = sanitize_text_field($_POST['subject']);
            $content = wp_kses_post($_POST['content']);
            $campaign_name = sanitize_text_field($_POST['campaign_name']);
            $recipients_source = sanitize_text_field($_POST['recipients_source']);
            
            // Detailed validation with specific error messages
            $missing_fields = array();
            if (!$subject) $missing_fields[] = 'subject';
            if (!$content) $missing_fields[] = 'content';
            if (!$campaign_name) $missing_fields[] = 'campaign name';
            
            if (!empty($missing_fields)) {
                throw new Exception('Missing required fields: ' . implode(', ', $missing_fields));
            }
            
            $recipients = array();
            $email_column = 'email';
            
            if ($recipients_source === 'saved_list') {
                $list_id = intval($_POST['saved_list_id']);
                $email_column = sanitize_text_field($_POST['email_column']);
                
                if (!$list_id || !$email_column) {
                    throw new Exception('Invalid list selection or email column');
                }
                
                $list = $this->list_manager->get_list($list_id);
                $recipients = $list->recipients;
                
                // Add campaign tags to the list
                $this->list_manager->add_campaign_tags_to_list($list_id, $campaign_name, current_time('Y-m-d'));
                
            } elseif ($recipients_source === 'upload_csv') {
                if (!isset($_FILES['csv_file'])) {
                    throw new Exception('No file uploaded');
                }
                
                $this->file_processor->validate_file_upload($_FILES['csv_file']);
                $recipients = $this->file_processor->parse_file($_FILES['csv_file']['tmp_name'], null);
                
                if (isset($_POST['email_column_upload'])) {
                    $email_column = sanitize_text_field($_POST['email_column_upload']);
                }
                
            } elseif ($recipients_source === 'manual') {
                $manual_recipients = sanitize_textarea_field($_POST['manual_recipients']);
                $recipients = $this->parse_manual_recipients($manual_recipients);
                
            } else {
                throw new Exception('Invalid recipients source');
            }
            
            if (empty($recipients)) {
                throw new Exception('No recipients found');
            }
            
            // Apply header and footer templates if selected
            $header_id = isset($_POST['header_template']) ? intval($_POST['header_template']) : null;
            $footer_id = isset($_POST['footer_template']) ? intval($_POST['footer_template']) : null;
            
            if ($header_id || $footer_id) {
                $content = $this->header_footer_manager->apply_templates($content, $header_id, $footer_id);
            }
            
            // Send campaign
            $result = $this->campaign_manager->send_campaign($recipients, $subject, $content, $campaign_name, $email_column);
            
            echo json_encode(array(
                'success' => true,
                'data' => array(
                    'message' => "Campaign sent! {$result['sent']} emails sent, {$result['failed']} failed.",
                    'sent' => $result['sent'],
                    'failed' => $result['failed'],
                    'campaign_id' => $result['campaign_id']
                )
            ));
            
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'data' => array('message' => 'Error: ' . $e->getMessage())
            ));
        }
        
        exit;
    }
    
    /**
     * Save campaign
     */
    public function handle_save_campaign() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'save_campaign') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            $campaign_name = sanitize_text_field($_POST['campaign_name']);
            $subject = sanitize_text_field($_POST['subject']);
            $content = wp_kses_post($_POST['content']);
            $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : null;
            
            if (!$campaign_name || !$subject || !$content) {
                throw new Exception('Missing required fields');
            }
            
            $saved_id = $this->campaign_manager->save_campaign($campaign_name, $subject, $content, $campaign_id);
            
            echo json_encode(array(
                'success' => true,
                'data' => array(
                    'message' => 'Campaign saved successfully!',
                    'campaign_id' => $saved_id
                )
            ));
            
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'data' => array('message' => 'Error: ' . $e->getMessage())
            ));
        }
        
        exit;
    }
    
    /**
     * Load campaign
     */
    public function handle_load_campaign() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'load_campaign') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            $campaign_id = intval($_POST['campaign_id']);
            
            if (!$campaign_id) {
                throw new Exception('Invalid campaign ID');
            }
            
            $campaign = $this->campaign_manager->get_campaign($campaign_id);
            
            echo json_encode(array(
                'success' => true,
                'data' => array(
                    'campaign' => array(
                        'id' => $campaign->id,
                        'campaign_name' => $campaign->campaign_name,
                        'subject' => $campaign->subject,
                        'content' => $campaign->content
                    )
                )
            ));
            
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'data' => array('message' => 'Error: ' . $e->getMessage())
            ));
        }
        
        exit;
    }
    
    /**
     * View campaign details
     */
    public function handle_view_campaign() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'view_campaign') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            $campaign_id = intval($_POST['campaign_id']);
            
            if (!$campaign_id) {
                throw new Exception('Invalid campaign ID');
            }
            
            $campaign = $this->campaign_manager->get_campaign($campaign_id);
            $stats = $this->campaign_manager->get_campaign_stats($campaign_id);
            $logs = $this->campaign_manager->get_campaign_logs($campaign_id, 50);
            
            echo json_encode(array(
                'success' => true,
                'data' => array(
                    'campaign' => $campaign,
                    'stats' => $stats,
                    'logs' => $logs
                )
            ));
            
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'data' => array('message' => 'Error: ' . $e->getMessage())
            ));
        }
        
        exit;
    }
    
    /**
     * Delete campaign
     */
    public function handle_delete_campaign() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'delete_campaign') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            $campaign_id = intval($_POST['campaign_id']);
            
            if (!$campaign_id) {
                throw new Exception('Invalid campaign ID');
            }
            
            $this->campaign_manager->delete_campaign($campaign_id);
            
            echo json_encode(array(
                'success' => true,
                'data' => array('message' => 'Campaign deleted successfully')
            ));
            
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'data' => array('message' => 'Error: ' . $e->getMessage())
            ));
        }
        
        exit;
    }
    
    /**
     * Copy campaign
     */
    public function handle_copy_campaign() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'copy_campaign') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            $campaign_id = intval($_POST['campaign_id']);
            
            if (!$campaign_id) {
                throw new Exception('Invalid campaign ID');
            }
            
            $new_campaign_id = $this->campaign_manager->copy_campaign($campaign_id);
            
            echo json_encode(array(
                'success' => true,
                'data' => array(
                    'message' => 'Campaign copied successfully',
                    'new_campaign_id' => $new_campaign_id
                )
            ));
            
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'data' => array('message' => 'Error: ' . $e->getMessage())
            ));
        }
        
        exit;
    }
    
    /**
     * Load recipient list
     */
    public function handle_load_recipient_list() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'load_recipient_list') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            $list_id = intval($_POST['list_id']);
            
            if (!$list_id) {
                throw new Exception('Invalid list ID');
            }
            
            $list = $this->list_manager->get_list($list_id);
            $columns = $this->list_manager->get_list_columns($list_id);
            
            echo json_encode(array(
                'success' => true,
                'data' => array(
                    'recipients' => array_slice($list->recipients, 0, 10), // Preview first 10
                    'total_count' => count($list->recipients),
                    'columns' => $columns,
                    'list_name' => $list->list_name
                )
            ));
            
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'data' => array('message' => 'Error: ' . $e->getMessage())
            ));
        }
        
        exit;
    }
    
    /**
     * Upload and save CSV as recipient list
     */
    public function handle_upload_save_csv() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'upload_save_csv') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            $list_name = sanitize_text_field($_POST['list_name']);
            
            if (empty($list_name)) {
                throw new Exception('List name is required');
            }
            
            if (!isset($_FILES['csv_file'])) {
                throw new Exception('No file uploaded');
            }
            
            $this->file_processor->validate_file_upload($_FILES['csv_file']);
            $recipients = $this->file_processor->parse_file($_FILES['csv_file']['tmp_name'], null);
            
            if (empty($recipients)) {
                throw new Exception('No valid recipients found in file');
            }
            
            $list_id = $this->list_manager->create_list($list_name, $recipients);
            
            echo json_encode(array(
                'success' => true,
                'data' => array(
                    'message' => "List '{$list_name}' created successfully with " . count($recipients) . ' recipients',
                    'list_id' => $list_id,
                    'recipient_count' => count($recipients)
                )
            ));
            
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'data' => array('message' => 'Error: ' . $e->getMessage())
            ));
        }
        
        exit;
    }
    
    /**
     * Export recipient list
     */
    public function handle_export_recipient_list() {
        if (!wp_verify_nonce($_POST['nonce'], 'export_recipient_list') || !current_user_can('edit_posts')) {
            wp_die('Unauthorized', 'Error', array('response' => 403));
        }
        
        $list_id = intval($_POST['list_id']);
        $exclude_bounced = $_POST['exclude_bounced'] === '1';
        $exclude_unsubscribed = $_POST['exclude_unsubscribed'] === '1';
        $export_format = isset($_POST['export_format']) ? $_POST['export_format'] : 'csv';
        
        if (!$list_id) {
            wp_die('Invalid list ID', 'Error', array('response' => 400));
        }
        
        try {
            $export_data = $this->list_manager->export_list($list_id, $exclude_bounced, $exclude_unsubscribed, $export_format);
            
            // Set headers for download
            header('Content-Type: ' . $export_data['content_type']);
            header('Content-Disposition: attachment; filename="' . $export_data['filename'] . '"');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            // Output content
            echo $export_data['content'];
            exit;
            
        } catch (Exception $e) {
            wp_die('Error generating export: ' . $e->getMessage(), 'Error', array('response' => 500));
        }
    }
    
    /**
     * Handle Mailgun webhook
     */
    public function handle_mailgun_webhook() {
        $webhook_data = json_decode(file_get_contents('php://input'), true);
        
        if (!$webhook_data) {
            http_response_code(400);
            echo 'Invalid webhook data';
            exit;
        }
        
        try {
            $this->email_service->handle_webhook($webhook_data);
            http_response_code(200);
            echo 'OK';
            
        } catch (Exception $e) {
            error_log('Alumni Bulk Email - Webhook error: ' . $e->getMessage());
            http_response_code(500);
            echo 'Error processing webhook';
        }
        
        exit;
    }
    
    /**
     * Handle unsubscribe request
     */
    public function handle_unsubscribe() {
        $token = sanitize_text_field($_GET['token']);
        
        if (empty($token)) {
            wp_die('Invalid unsubscribe link', 'Unsubscribe Error');
        }
        
        try {
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            
            $success = $this->email_service->process_unsubscribe($token, $ip_address, $user_agent);
            
            if ($success) {
                wp_die('You have been successfully unsubscribed from our mailing list.', 'Unsubscribed');
            } else {
                wp_die('Invalid unsubscribe link or email already unsubscribed.', 'Unsubscribe Error');
            }
            
        } catch (Exception $e) {
            error_log('Alumni Bulk Email - Unsubscribe error: ' . $e->getMessage());
            wp_die('An error occurred while processing your unsubscribe request.', 'Unsubscribe Error');
        }
    }
    
    /**
     * Recreate database tables
     */
    public function handle_recreate_tables() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'recreate_tables') || !current_user_can('manage_options')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            Alumni_Database::create_tables();
            echo json_encode(array(
                'success' => true,
                'data' => array('message' => 'Database tables recreated successfully')
            ));
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'data' => array('message' => 'Error recreating tables: ' . $e->getMessage())
            ));
        }
        
        exit;
    }
    
    /**
     * Parse manual recipients input
     */
    private function parse_manual_recipients($manual_input) {
        $recipients = array();
        $lines = explode("\n", $manual_input);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Parse formats like "Name <email@example.com>" or just "email@example.com"
            if (preg_match('/^(.+?)\s*<(.+?)>$/', $line, $matches)) {
                $name = trim($matches[1]);
                $email = trim($matches[2]);
            } else {
                $email = trim($line);
                $name = '';
            }
            
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $recipient = array(
                    'email' => $email,
                    'name' => $name,
                    'tags' => ''
                );
                
                // Extract first/last name from full name if possible
                if ($name) {
                    $name_parts = explode(' ', $name, 2);
                    $recipient['first_name'] = $name_parts[0];
                    $recipient['last_name'] = isset($name_parts[1]) ? $name_parts[1] : '';
                }
                
                $recipients[] = $recipient;
            }
        }
        
        return $recipients;
    }
    
    /**
     * Send test campaign
     */
    public function handle_test_campaign() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'send_test_campaign') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            $campaign_id = intval($_POST['campaign_id']);
            $test_email = sanitize_email($_POST['test_email']);
            
            if (!$campaign_id || !$test_email) {
                throw new Exception('Missing campaign ID or test email');
            }
            
            $campaign = $this->campaign_manager->get_campaign($campaign_id);
            $result = $this->campaign_manager->send_test_email($test_email, $campaign->subject, $campaign->content);
            
            echo json_encode(array(
                'success' => true,
                'data' => array('message' => 'Test email sent successfully!')
            ));
            
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'data' => array('message' => 'Error: ' . $e->getMessage())
            ));
        }
        
        exit;
    }
    
    /**
     * Create recipient list
     */
    public function handle_create_recipient_list() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'create_recipient_list') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            $list_name = sanitize_text_field($_POST['list_name']);
            $description = sanitize_textarea_field($_POST['description']);
            $recipients_json = sanitize_textarea_field($_POST['recipients']);
            
            if (empty($list_name) || empty($recipients_json)) {
                throw new Exception('List name and recipients are required');
            }
            
            $recipients = json_decode($recipients_json, true);
            if (!is_array($recipients)) {
                throw new Exception('Invalid recipients data');
            }
            
            $list_id = $this->list_manager->create_list($list_name, $recipients, $description);
            
            echo json_encode(array(
                'success' => true,
                'data' => array(
                    'message' => 'List created successfully',
                    'list_id' => $list_id
                )
            ));
            
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'data' => array('message' => 'Error: ' . $e->getMessage())
            ));
        }
        
        exit;
    }
    
    /**
     * Combine recipient lists
     */
    public function handle_combine_recipient_lists() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'combine_recipient_lists') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            $target_list_id = intval($_POST['target_list_id']);
            $source_list_ids = array_map('intval', $_POST['source_list_ids']);
            
            if (!$target_list_id || empty($source_list_ids)) {
                throw new Exception('Target list and source lists are required');
            }
            
            // Get all source recipients
            $all_recipients = array();
            foreach ($source_list_ids as $source_id) {
                $source_list = $this->list_manager->get_list($source_id);
                $all_recipients = array_merge($all_recipients, $source_list->recipients);
            }
            
            // Merge with target list
            $result = $this->list_manager->merge_lists($target_list_id, $all_recipients);
            
            echo json_encode(array(
                'success' => true,
                'data' => array(
                    'message' => "Lists combined successfully. {$result['new_recipients']} new recipients added, {$result['duplicates_removed']} duplicates removed.",
                    'total_recipients' => $result['total_recipients']
                )
            ));
            
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'data' => array('message' => 'Error: ' . $e->getMessage())
            ));
        }
        
        exit;
    }
    
    /**
     * View recipient list details
     */
    public function handle_view_recipient_list() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'view_recipient_list') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            $list_id = intval($_POST['list_id']);
            $page = intval($_POST['page']) ?: 1;
            $per_page = intval($_POST['per_page']) ?: 50;
            $search = sanitize_text_field($_POST['search']) ?: '';
            
            if (!$list_id) {
                throw new Exception('Invalid list ID');
            }
            
            $list = $this->list_manager->get_list($list_id);
            $recipients = $list->recipients;
            
            // Apply search filter if provided
            if ($search) {
                $recipients = $this->list_manager->search_recipients($list_id, $search);
            }
            
            // Calculate pagination
            $total = count($recipients);
            $offset = ($page - 1) * $per_page;
            $paged_recipients = array_slice($recipients, $offset, $per_page);
            
            // Get list statistics
            $stats = $this->list_manager->get_list_stats($list_id);
            
            echo json_encode(array(
                'success' => true,
                'data' => array(
                    'list' => array(
                        'id' => $list->id,
                        'name' => $list->list_name,
                        'description' => $list->description
                    ),
                    'recipients' => $paged_recipients,
                    'pagination' => array(
                        'page' => $page,
                        'per_page' => $per_page,
                        'total' => $total,
                        'pages' => ceil($total / $per_page)
                    ),
                    'stats' => $stats,
                    'columns' => $this->list_manager->get_list_columns($list_id)
                )
            ));
            
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'data' => array('message' => 'Error: ' . $e->getMessage())
            ));
        }
        
        exit;
    }
    
    /**
     * Edit recipient row
     */
    public function handle_edit_recipient_row() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'edit_recipient_row') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            $list_id = intval($_POST['list_id']);
            $row_index = intval($_POST['row_index']);
            $updated_data = $_POST['recipient_data']; // Should be sanitized array
            
            if (!$list_id || $row_index < 0) {
                throw new Exception('Invalid list ID or row index');
            }
            
            // Sanitize updated data
            $sanitized_data = array();
            foreach ($updated_data as $key => $value) {
                $sanitized_data[sanitize_key($key)] = sanitize_text_field($value);
            }
            
            // Get current list
            $list = $this->list_manager->get_list($list_id);
            $recipients = $list->recipients;
            
            if (!isset($recipients[$row_index])) {
                throw new Exception('Recipient not found');
            }
            
            // Update the recipient data
            foreach ($sanitized_data as $field => $value) {
                $recipients[$row_index][$field] = $value;
            }
            
            // Save updated list
            $this->list_manager->update_list($list_id, $recipients);
            
            echo json_encode(array(
                'success' => true,
                'data' => array('message' => 'Recipient updated successfully')
            ));
            
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'data' => array('message' => 'Error: ' . $e->getMessage())
            ));
        }
        
        exit;
    }
    
    /**
     * Bulk edit recipients
     */
    public function handle_bulk_edit_recipients() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'bulk_edit_recipients') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            $list_id = intval($_POST['list_id']);
            $recipient_indices = array_map('intval', $_POST['recipient_indices']);
            $updates = $_POST['updates']; // Should be sanitized array
            
            if (!$list_id || empty($recipient_indices)) {
                throw new Exception('List ID and recipient indices are required');
            }
            
            // Sanitize updates
            $sanitized_updates = array();
            foreach ($updates as $field => $value) {
                $sanitized_updates[sanitize_key($field)] = sanitize_text_field($value);
            }
            
            $updated_count = $this->list_manager->bulk_edit_recipients($list_id, $recipient_indices, $sanitized_updates);
            
            echo json_encode(array(
                'success' => true,
                'data' => array(
                    'message' => "{$updated_count} recipients updated successfully",
                    'updated_count' => $updated_count
                )
            ));
            
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'data' => array('message' => 'Error: ' . $e->getMessage())
            ));
        }
        
        exit;
    }
    
    /**
     * Bulk delete recipients
     */
    public function handle_bulk_delete_recipients() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'bulk_delete_recipients') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            $list_id = intval($_POST['list_id']);
            $recipient_indices = array_map('intval', $_POST['recipient_indices']);
            
            if (!$list_id || empty($recipient_indices)) {
                throw new Exception('List ID and recipient indices are required');
            }
            
            $deleted_count = $this->list_manager->bulk_delete_recipients($list_id, $recipient_indices);
            
            echo json_encode(array(
                'success' => true,
                'data' => array(
                    'message' => "{$deleted_count} recipients deleted successfully",
                    'deleted_count' => $deleted_count
                )
            ));
            
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'data' => array('message' => 'Error: ' . $e->getMessage())
            ));
        }
        
        exit;
    }
    
    /**
     * Get list columns
     */
    public function handle_get_list_columns() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'get_list_columns') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            $list_id = intval($_POST['list_id']);
            
            if (!$list_id) {
                throw new Exception('Invalid list ID');
            }
            
            $columns = $this->list_manager->get_list_columns($list_id);
            
            echo json_encode(array(
                'success' => true,
                'data' => array('columns' => $columns)
            ));
            
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'data' => array('message' => 'Error: ' . $e->getMessage())
            ));
        }
        
        exit;
    }
    
    /**
     * Merge CSV to existing list
     */
    public function handle_merge_csv_to_list() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'merge_csv_to_list') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            $list_id = intval($_POST['list_id']);
            
            if (!$list_id) {
                throw new Exception('Invalid list ID');
            }
            
            if (!isset($_FILES['csv_file'])) {
                throw new Exception('No file uploaded');
            }
            
            $this->file_processor->validate_file_upload($_FILES['csv_file']);
            $new_recipients = $this->file_processor->parse_file($_FILES['csv_file']['tmp_name'], null);
            
            if (empty($new_recipients)) {
                throw new Exception('No valid recipients found in file');
            }
            
            $result = $this->list_manager->merge_lists($list_id, $new_recipients);
            
            echo json_encode(array(
                'success' => true,
                'data' => array(
                    'message' => "File merged successfully. {$result['new_recipients']} new recipients added, {$result['duplicates_removed']} duplicates removed.",
                    'total_recipients' => $result['total_recipients'],
                    'new_recipients' => $result['new_recipients'],
                    'duplicates_removed' => $result['duplicates_removed']
                )
            ));
            
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'data' => array('message' => 'Error: ' . $e->getMessage())
            ));
        }
        
        exit;
    }
    
    /**
     * Create sublist from results
     */
    public function handle_create_sublist_from_results() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'create_sublist_from_results') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            $list_id = intval($_POST['list_id']);
            $sublist_name = sanitize_text_field($_POST['sublist_name']);
            $filtered_recipients_json = sanitize_textarea_field($_POST['filtered_recipients']);
            
            if (!$list_id || empty($sublist_name) || empty($filtered_recipients_json)) {
                throw new Exception('Missing required data');
            }
            
            $filtered_recipients = json_decode($filtered_recipients_json, true);
            if (empty($filtered_recipients) || !is_array($filtered_recipients)) {
                throw new Exception('Invalid recipients data');
            }
            
            $new_list_id = $this->list_manager->create_sublist_from_results($sublist_name, $filtered_recipients, $list_id);
            
            echo json_encode(array(
                'success' => true,
                'data' => array(
                    'message' => 'Sublist "' . $sublist_name . '" created successfully with ' . count($filtered_recipients) . ' recipients',
                    'list_id' => $new_list_id,
                    'list_name' => $sublist_name
                )
            ));
            
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'data' => array('message' => 'Error creating sublist: ' . $e->getMessage())
            ));
        }
        
        exit;
    }
    
    /**
     * Load all templates
     */
    public function handle_load_templates() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'load_templates') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : null;
            
            $templates = $this->header_footer_manager->get_all_templates($type);
            $stats = $this->header_footer_manager->get_template_stats();
            
            echo json_encode(array(
                'success' => true,
                'data' => array(
                    'templates' => $templates,
                    'stats' => $stats
                )
            ));
            
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'data' => array('message' => 'Error: ' . $e->getMessage())
            ));
        }
        
        exit;
    }
    
    /**
     * Get a specific template
     */
    public function handle_get_template() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'get_template') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            $id = intval($_POST['id']);
            
            if (!$id) {
                throw new Exception('Template ID is required');
            }
            
            $template = $this->header_footer_manager->get_template($id);
            
            if (!$template) {
                throw new Exception('Template not found');
            }
            
            echo json_encode(array(
                'success' => true,
                'data' => array('template' => $template)
            ));
            
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'data' => array('message' => 'Error: ' . $e->getMessage())
            ));
        }
        
        exit;
    }
    
    /**
     * Save header/footer template
     */
    public function handle_save_header_footer() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'save_header_footer') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            $name = sanitize_text_field($_POST['name']);
            $type = sanitize_text_field($_POST['type']);
            $content = wp_kses_post($_POST['content']);
            $is_default = isset($_POST['is_default']) && $_POST['is_default'] == '1';
            $id = isset($_POST['id']) ? intval($_POST['id']) : null;
            
            if (empty($name) || empty($type) || empty($content)) {
                throw new Exception('Name, type, and content are required');
            }
            
            $template_id = $this->header_footer_manager->save_template($name, $type, $content, $is_default, $id);
            
            echo json_encode(array(
                'success' => true,
                'data' => array(
                    'message' => $id ? 'Template updated successfully' : 'Template created successfully',
                    'template_id' => $template_id
                )
            ));
            
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'data' => array('message' => 'Error: ' . $e->getMessage())
            ));
        }
        
        exit;
    }
    
    /**
     * Delete header/footer template
     */
    public function handle_delete_header_footer() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'delete_header_footer') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            $id = intval($_POST['id']);
            
            if (!$id) {
                throw new Exception('Template ID is required');
            }
            
            $result = $this->header_footer_manager->delete_template($id);
            
            if ($result) {
                echo json_encode(array(
                    'success' => true,
                    'data' => array('message' => 'Template deleted successfully')
                ));
            } else {
                throw new Exception('Failed to delete template');
            }
            
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'data' => array('message' => 'Error: ' . $e->getMessage())
            ));
        }
        
        exit;
    }
    
    /**
     * Set default header/footer template
     */
    public function handle_set_default_header_footer() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'set_default_header_footer') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            $id = intval($_POST['id']);
            
            if (!$id) {
                throw new Exception('Template ID is required');
            }
            
            $result = $this->header_footer_manager->set_default_template($id);
            
            if ($result) {
                echo json_encode(array(
                    'success' => true,
                    'data' => array('message' => 'Default template set successfully')
                ));
            } else {
                throw new Exception('Failed to set default template');
            }
            
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'data' => array('message' => 'Error: ' . $e->getMessage())
            ));
        }
        
        exit;
    }
    
    /**
     * Duplicate template
     */
    public function handle_duplicate_template() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'duplicate_template') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            $id = intval($_POST['id']);
            $new_name = isset($_POST['new_name']) ? sanitize_text_field($_POST['new_name']) : null;
            
            if (!$id) {
                throw new Exception('Template ID is required');
            }
            
            $new_template_id = $this->header_footer_manager->duplicate_template($id, $new_name);
            
            echo json_encode(array(
                'success' => true,
                'data' => array(
                    'message' => 'Template duplicated successfully',
                    'template_id' => $new_template_id
                )
            ));
            
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'data' => array('message' => 'Error: ' . $e->getMessage())
            ));
        }
        
        exit;
    }
    
    /**
     * Save typography preset
     */
    public function handle_save_typography_preset() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'save_typography_preset') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            $name = sanitize_text_field($_POST['name']);
            $font_family = sanitize_text_field($_POST['font_family']);
            $font_size = sanitize_text_field($_POST['font_size']);
            $text_color = sanitize_text_field($_POST['text_color']);
            $description = sanitize_textarea_field($_POST['description']);
            
            if (!$name) {
                throw new Exception('Preset name is required');
            }
            
            $preset_id = $this->typography_preset_manager->save_preset(
                $name, 
                $font_family, 
                $font_size, 
                $text_color, 
                $description
            );
            
            echo json_encode(array(
                'success' => true,
                'data' => array(
                    'message' => 'Typography preset saved successfully',
                    'preset_id' => $preset_id
                )
            ));
            
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'data' => array('message' => 'Error: ' . $e->getMessage())
            ));
        }
        
        exit;
    }
    
    /**
     * Test Mailgun connection
     */
    public function handle_test_mailgun_connection() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'test_mailgun_connection') || !current_user_can('manage_options')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            $test_email = sanitize_email($_POST['test_email']);
            
            if (!$test_email) {
                throw new Exception('Valid test email address is required');
            }
            
            // Test email content
            $subject = 'Alumni Bulk Email - Mailgun Connection Test';
            $content = '<h2>Mailgun Connection Test</h2>
                       <p>This is a test email to verify that your Mailgun configuration is working correctly.</p>
                       <p><strong>Sent at:</strong> ' . current_time('F j, Y g:i A') . '</p>
                       <p>If you received this email, your Mailgun settings are properly configured!</p>';
            
            // Use the email service to send the test
            $result = $this->email_service->send_email($test_email, $subject, $content);
            
            echo json_encode(array(
                'success' => true,
                'data' => array(
                    'message' => 'Test email sent successfully! Check your inbox at ' . $test_email,
                    'mailgun_message_id' => isset($result['id']) ? $result['id'] : 'Unknown'
                )
            ));
            
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'data' => array('message' => 'Mailgun test failed: ' . $e->getMessage())
            ));
        }
        
        exit;
    }
}