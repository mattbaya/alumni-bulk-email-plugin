<?php
/**
 * Plugin Name: Alumni Bulk Email
 * Plugin URI: https://github.com/mattbaya/alumni-bulk-email-plugin
 * Description: Send bulk emails to alumni with Mailgun integration, CSV logging, and bounce tracking.
 * Version: 1.1.1
 * Author: Matt Baya
 * Author URI: https://svaha.com
 * License: GPL v2 or later
 * Text Domain: alumni-bulk-email
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('ALUMNI_BULK_EMAIL_VERSION', '1.1.0');
define('ALUMNI_BULK_EMAIL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ALUMNI_BULK_EMAIL_PLUGIN_URL', plugin_dir_url(__FILE__));

// Main plugin class
class AlumniBulkEmail {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_send_test_email', array($this, 'handle_test_email'));
        
        // Create tables on activation
        register_activation_hook(__FILE__, array($this, 'create_tables'));
    }
    
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('alumni-bulk-email', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Email lists table
        $table_name = $wpdb->prefix . 'alumni_email_lists';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            list_name varchar(255) NOT NULL,
            description text,
            total_subscribers int DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Set default options
        add_option('alumni_mailgun_api_key', '');
        add_option('alumni_mailgun_domain', '');
        add_option('alumni_from_email', '');
        add_option('alumni_from_name', 'Alumni Association');
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Alumni Bulk Email',
            'Bulk Email',
            'manage_options',
            'alumni-bulk-email',
            array($this, 'admin_page'),
            'dashicons-email-alt',
            30
        );
        
        add_submenu_page(
            'alumni-bulk-email',
            'Settings',
            'Settings',
            'manage_options',
            'alumni-bulk-email-settings',
            array($this, 'settings_page')
        );
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Alumni Bulk Email</h1>
            
            <div class="notice notice-info">
                <p>Welcome to Alumni Bulk Email! Please configure your <a href="<?php echo admin_url('admin.php?page=alumni-bulk-email-settings'); ?>">Mailgun settings</a> to get started.</p>
            </div>
            
            <h2>Quick Setup</h2>
            <ol>
                <li>Configure your Mailgun API credentials in Settings</li>
                <li>Set your from email and name</li>
                <li>Upload your alumni CSV file</li>
                <li>Send your first campaign!</li>
            </ol>
            
            <p><strong>Status:</strong> Plugin activated successfully! ✅</p>
        </div>
        <?php
    }
    
    public function settings_page() {
        // Handle form submission
        if (isset($_POST['submit']) && check_admin_referer('alumni_bulk_email_settings', 'alumni_bulk_email_nonce')) {
            update_option('alumni_mailgun_api_key', sanitize_text_field($_POST['mailgun_api_key']));
            update_option('alumni_mailgun_domain', sanitize_text_field($_POST['mailgun_domain']));
            update_option('alumni_from_email', sanitize_email($_POST['from_email']));
            update_option('alumni_from_name', sanitize_text_field($_POST['from_name']));
            
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }
        
        // Get current settings
        $api_key = get_option('alumni_mailgun_api_key', '');
        $domain = get_option('alumni_mailgun_domain', '');
        $from_email = get_option('alumni_from_email', '');
        $from_name = get_option('alumni_from_name', 'Alumni Association');
        ?>
        <div class="wrap">
            <h1>Alumni Bulk Email Settings</h1>
            
            <form method="post">
                <?php wp_nonce_field('alumni_bulk_email_settings', 'alumni_bulk_email_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="mailgun_api_key">Mailgun API Key</label>
                        </th>
                        <td>
                            <input type="password" id="mailgun_api_key" name="mailgun_api_key" 
                                   value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
                            <p class="description">Your Mailgun production API key</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mailgun_domain">Mailgun Domain</label>
                        </th>
                        <td>
                            <input type="text" id="mailgun_domain" name="mailgun_domain" 
                                   value="<?php echo esc_attr($domain); ?>" class="regular-text" 
                                   placeholder="e.g., mail.yourdomain.com" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="from_email">From Email</label>
                        </th>
                        <td>
                            <input type="email" id="from_email" name="from_email" 
                                   value="<?php echo esc_attr($from_email); ?>" class="regular-text" 
                                   placeholder="e.g., alumni@yourdomain.com" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="from_name">From Name</label>
                        </th>
                        <td>
                            <input type="text" id="from_name" name="from_name" 
                                   value="<?php echo esc_attr($from_name); ?>" class="regular-text" 
                                   placeholder="e.g., Your Alumni Association" />
                        </td>
                    </tr>
                </table>
                
                <h2>Webhook URL</h2>
                <p>Add this URL to your Mailgun webhook settings for bounce tracking:</p>
                <code><?php echo admin_url('admin-ajax.php?action=handle_alumni_webhook'); ?></code>
                
                <?php submit_button(); ?>
            </form>
            
            <div style="margin-top: 20px;">
                <h2>Test Your Settings</h2>
                <p>Send a test email to verify your Mailgun configuration is working correctly.</p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="test_email">Test Email Address</label>
                        </th>
                        <td>
                            <input type="email" id="test_email" name="test_email" class="regular-text" 
                                   placeholder="your@email.com" />
                            <p class="description">Enter your email address to receive the test email</p>
                        </td>
                    </tr>
                </table>
                
                <button type="button" id="send_test_email" class="button button-secondary">
                    Send Test Email
                </button>
                
                <div id="test_email_result" style="margin-top: 10px;"></div>
            </div>
            
            <div class="notice notice-warning">
                <p><strong>Note:</strong> This is a basic version. Full email sending features will be added in future updates.</p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#send_test_email').click(function() {
                var testEmail = $('#test_email').val();
                var button = $(this);
                var resultDiv = $('#test_email_result');
                
                if (!testEmail) {
                    resultDiv.html('<div class="notice notice-error"><p>Please enter a test email address.</p></div>');
                    return;
                }
                
                button.prop('disabled', true).text('Sending...');
                resultDiv.html('<div class="notice notice-info"><p>Sending test email...</p></div>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'send_test_email',
                        test_email: testEmail,
                        nonce: '<?php echo wp_create_nonce('send_test_email'); ?>'
                    },
                    success: function(response) {
                        console.log('AJAX Response:', response);
                        try {
                            if (typeof response === 'string') {
                                response = JSON.parse(response);
                            }
                            if (response.success) {
                                resultDiv.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                            } else {
                                resultDiv.html('<div class="notice notice-error"><p>Error: ' + response.data.message + '</p></div>');
                            }
                        } catch (e) {
                            console.error('JSON Parse Error:', e);
                            resultDiv.html('<div class="notice notice-error"><p>Error: Invalid response format</p></div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', xhr, status, error);
                        resultDiv.html('<div class="notice notice-error"><p>AJAX Error: ' + status + ' - ' + error + '</p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Send Test Email');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    public function handle_test_email() {
        // Set JSON header
        header('Content-Type: application/json');
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'send_test_email') || !current_user_can('manage_options')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        $test_email = sanitize_email($_POST['test_email']);
        if (!is_email($test_email)) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Invalid email address')));
            exit;
        }
        
        // Get current settings
        $api_key = get_option('alumni_mailgun_api_key', '');
        $domain = get_option('alumni_mailgun_domain', '');
        $from_email = get_option('alumni_from_email', '');
        $from_name = get_option('alumni_from_name', 'Alumni Association');
        
        // Validate settings
        if (empty($api_key) || empty($domain) || empty($from_email)) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Please configure all Mailgun settings first')));
            exit;
        }
        
        // Send test email via Mailgun API
        $result = $this->send_mailgun_email(
            $test_email,
            'Alumni Bulk Email - Test Email',
            '<h2>Test Email Successful! ✅</h2>
            <p>Congratulations! Your Alumni Bulk Email plugin is configured correctly.</p>
            <p><strong>Settings verified:</strong></p>
            <ul>
                <li>Mailgun API Key: ✓ Connected</li>
                <li>Mailgun Domain: ' . esc_html($domain) . '</li>
                <li>From Email: ' . esc_html($from_email) . '</li>
                <li>From Name: ' . esc_html($from_name) . '</li>
            </ul>
            <p>Your plugin is ready to send bulk emails to your alumni!</p>
            <p><em>Sent via Alumni Bulk Email Plugin</em></p>',
            'Test Email Successful! Your Alumni Bulk Email plugin is configured correctly and ready to send bulk emails to your alumni.'
        );
        
        if ($result['success']) {
            echo json_encode(array('success' => true, 'data' => array('message' => 'Test email sent successfully! Check your inbox.')));
        } else {
            echo json_encode(array('success' => false, 'data' => array('message' => $result['error'])));
        }
        exit;
    }
    
    private function send_mailgun_email($to, $subject, $html, $text = '') {
        $api_key = get_option('alumni_mailgun_api_key', '');
        $domain = get_option('alumni_mailgun_domain', '');
        $from_email = get_option('alumni_from_email', '');
        $from_name = get_option('alumni_from_name', 'Alumni Association');
        
        $url = "https://api.mailgun.net/v3/{$domain}/messages";
        
        $data = array(
            'from' => "{$from_name} <{$from_email}>",
            'to' => $to,
            'subject' => $subject,
            'html' => $html
        );
        
        if (!empty($text)) {
            $data['text'] = $text;
        }
        
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode('api:' . $api_key)
            ),
            'body' => $data,
            'timeout' => 30
        );
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            return array('success' => false, 'error' => 'Network error: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code === 200) {
            return array('success' => true, 'response' => $response_body);
        } else {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['message']) ? $error_data['message'] : 'Unknown error';
            return array('success' => false, 'error' => "Mailgun API error ({$response_code}): {$error_message}");
        }
    }
}

// Initialize the plugin
new AlumniBulkEmail();
?>