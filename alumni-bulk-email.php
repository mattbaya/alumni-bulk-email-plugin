<?php
/**
 * Plugin Name: Alumni Bulk Email
 * Plugin URI: https://github.com/mattbaya/alumni-bulk-email-plugin
 * Description: Send bulk emails to alumni with Mailgun integration, CSV logging, and bounce tracking.
 * Version: 1.0.1
 * Author: Matt Baya
 * Author URI: https://mattbaya.net
 * License: GPL v2 or later
 * Text Domain: alumni-bulk-email
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('ALUMNI_BULK_EMAIL_VERSION', '1.0.1');
define('ALUMNI_BULK_EMAIL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ALUMNI_BULK_EMAIL_PLUGIN_URL', plugin_dir_url(__FILE__));

// Main plugin class
class AlumniBulkEmail {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
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
            
            <div class="notice notice-warning">
                <p><strong>Note:</strong> This is a basic version. Full email sending features will be added in future updates.</p>
            </div>
        </div>
        <?php
    }
}

// Initialize the plugin
new AlumniBulkEmail();
?>