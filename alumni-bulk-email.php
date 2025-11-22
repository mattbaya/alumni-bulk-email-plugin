<?php
/**
 * Plugin Name: Alumni Bulk Email
 * Plugin URI: https://github.com/mattbaya/alumni-bulk-email-plugin
 * Description: Send bulk emails to alumni with Mailgun integration, CSV logging, and bounce tracking. Auto-updates from GitHub.
 * Version: 1.0.0
 * Author: Matt Baya
 * Author URI: https://mattbaya.net
 * License: GPL v2 or later
 * Text Domain: alumni-bulk-email
 * Update URI: https://github.com/mattbaya/alumni-bulk-email-plugin
 * GitHub Plugin URI: mattbaya/alumni-bulk-email-plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('ALUMNI_BULK_EMAIL_VERSION', '1.0.0');
define('ALUMNI_BULK_EMAIL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ALUMNI_BULK_EMAIL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ALUMNI_BULK_EMAIL_GITHUB_REPO', 'mattbaya/alumni-bulk-email-plugin');

// Load GitHub Updater if not already loaded
if (!class_exists('Puc_v4_Factory')) {
    require_once plugin_dir_path(__FILE__) . 'vendor/plugin-update-checker/plugin-update-checker.php';
}

// Initialize automatic updates from GitHub
use Puc_v4_Factory;
$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
    'https://github.com/' . ALUMNI_BULK_EMAIL_GITHUB_REPO . '/',
    __FILE__,
    'alumni-bulk-email'
);

// Main plugin class
class AlumniBulkEmail {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_send_bulk_email', array($this, 'handle_bulk_email'));
        add_action('wp_ajax_handle_mailgun_webhook', array($this, 'handle_mailgun_webhook'));
        add_action('wp_ajax_nopriv_handle_mailgun_webhook', array($this, 'handle_mailgun_webhook'));
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Create tables on activation
        register_activation_hook(__FILE__, array($this, 'create_tables'));
    }
    
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('alumni-bulk-email', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function admin_notices() {
        // Check if Mailgun is configured
        if (!$this->is_mailgun_configured() && isset($_GET['page']) && strpos($_GET['page'], 'alumni-bulk-email') === 0) {
            echo '<div class="notice notice-warning"><p>';
            echo sprintf(
                __('Alumni Bulk Email: Please configure your Mailgun settings in the <a href="%s">Settings page</a>.', 'alumni-bulk-email'),
                admin_url('admin.php?page=alumni-bulk-email-settings')
            );
            echo '</p></div>';
        }
    }
    
    private function is_mailgun_configured() {
        $api_key = get_option('alumni_mailgun_api_key', '');
        $domain = get_option('alumni_mailgun_domain', '');
        return !empty($api_key) && !empty($domain);
    }
    
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Email lists table (saved recipient lists)
        $table_name_lists = $wpdb->prefix . 'alumni_email_lists';
        $sql_lists = "CREATE TABLE $table_name_lists (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            list_name varchar(255) NOT NULL,
            description text,
            total_subscribers int DEFAULT 0,
            active_subscribers int DEFAULT 0,
            bounced_subscribers int DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY list_name (list_name)
        ) $charset_collate;";
        
        // Email subscribers table (individual contacts in lists)
        $table_name_subscribers = $wpdb->prefix . 'alumni_email_subscribers';
        $sql_subscribers = "CREATE TABLE $table_name_subscribers (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            list_id mediumint(9) NOT NULL,
            email varchar(255) NOT NULL,
            name varchar(255),
            first_name varchar(255),
            last_name varchar(255),
            status varchar(50) DEFAULT 'active',
            bounce_count int DEFAULT 0,
            last_bounce_reason varchar(500),
            last_bounced_at datetime,
            subscribed_at datetime DEFAULT CURRENT_TIMESTAMP,
            unsubscribed_at datetime,
            notes text,
            PRIMARY KEY (id),
            UNIQUE KEY list_email_unique (list_id, email),
            KEY email (email),
            KEY status (status),
            KEY list_id (list_id)
        ) $charset_collate;";
        
        // Email campaigns table
        $table_name = $wpdb->prefix . 'alumni_email_campaigns';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            campaign_name varchar(255) NOT NULL,
            list_id mediumint(9),
            subject varchar(500) NOT NULL,
            html_content longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            sent_count int DEFAULT 0,
            bounce_count int DEFAULT 0,
            click_count int DEFAULT 0,
            open_count int DEFAULT 0,
            PRIMARY KEY (id),
            KEY list_id (list_id)
        ) $charset_collate;";
        
        // Email logs table
        $table_name_logs = $wpdb->prefix . 'alumni_email_logs';
        $sql_logs = "CREATE TABLE $table_name_logs (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            campaign_id mediumint(9) NOT NULL,
            subscriber_id mediumint(9),
            recipient_email varchar(255) NOT NULL,
            recipient_name varchar(255),
            status varchar(50) NOT NULL,
            sent_at datetime DEFAULT CURRENT_TIMESTAMP,
            bounce_reason varchar(500),
            mailgun_message_id varchar(255),
            opened_at datetime,
            clicked_at datetime,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY subscriber_id (subscriber_id),
            KEY recipient_email (recipient_email),
            KEY status (status),
            KEY mailgun_message_id (mailgun_message_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_lists);
        dbDelta($sql_subscribers);
        dbDelta($sql);
        dbDelta($sql_logs);
        
        // Set default options
        add_option('alumni_mailgun_domain', '');
        add_option('alumni_from_email', '');
        add_option('alumni_from_name', 'Alumni Association');
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Bulk Email', 'alumni-bulk-email'),
            __('Bulk Email', 'alumni-bulk-email'),
            'manage_options',
            'alumni-bulk-email',
            array($this, 'admin_page'),
            'dashicons-email-alt',
            30
        );
        
        add_submenu_page(
            'alumni-bulk-email',
            __('Email Lists', 'alumni-bulk-email'),
            __('Email Lists', 'alumni-bulk-email'),
            'manage_options',
            'alumni-bulk-email-lists',
            array($this, 'email_lists_page')
        );
        
        add_submenu_page(
            'alumni-bulk-email',
            __('Email Logs', 'alumni-bulk-email'),
            __('Email Logs', 'alumni-bulk-email'),
            'manage_options',
            'alumni-bulk-email-logs',
            array($this, 'logs_page')
        );
        
        add_submenu_page(
            'alumni-bulk-email',
            __('Settings', 'alumni-bulk-email'),
            __('Settings', 'alumni-bulk-email'),
            'manage_options',
            'alumni-bulk-email-settings',
            array($this, 'settings_page')
        );
    }
    
    public function admin_page() {
        global $wpdb;
        
        // Get available email lists
        $email_lists = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}alumni_email_lists ORDER BY created_at DESC");
        ?>
        <div class="wrap">
            <h1><?php _e('Alumni Bulk Email', 'alumni-bulk-email'); ?></h1>
            
            <?php if (!$this->is_mailgun_configured()): ?>
            <div class="notice notice-error">
                <p>
                    <?php _e('Mailgun is not configured. Please set up your API credentials in the Settings tab.', 'alumni-bulk-email'); ?>
                    <a href="<?php echo admin_url('admin.php?page=alumni-bulk-email-settings'); ?>" class="button button-primary">
                        <?php _e('Configure Now', 'alumni-bulk-email'); ?>
                    </a>
                </p>
            </div>
            <?php endif; ?>
            
            <form id="bulk-email-form" enctype="multipart/form-data">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="campaign_name"><?php _e('Campaign Name', 'antioch-bulk-email'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="campaign_name" name="campaign_name" class="regular-text" required />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php _e('Recipient Source', 'antioch-bulk-email'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="radio" name="recipient_source" value="existing_list" id="existing_list_radio" />
                                <?php _e('Use Existing Email List', 'antioch-bulk-email'); ?>
                            </label><br>
                            <select id="existing_list_id" name="existing_list_id" style="margin-left: 20px; display: none;">
                                <option value=""><?php _e('Select Email List', 'antioch-bulk-email'); ?></option>
                                <?php foreach ($email_lists as $list): ?>
                                <option value="<?php echo $list->id; ?>">
                                    <?php echo esc_html($list->list_name); ?> 
                                    (<?php echo $list->active_subscribers; ?> active)
                                </option>
                                <?php endforeach; ?>
                            </select><br><br>
                            
                            <label>
                                <input type="radio" name="recipient_source" value="upload_csv" id="upload_csv_radio" checked />
                                <?php _e('Upload New CSV File', 'antioch-bulk-email'); ?>
                            </label><br>
                            <div id="csv_upload_section" style="margin-left: 20px;">
                                <input type="file" id="csv_file" name="csv_file" accept=".csv" />
                                <p class="description">
                                    <?php _e('CSV file with email addresses. Headers: email, name, first_name, last_name', 'antioch-bulk-email'); ?>
                                </p>
                                
                                <label>
                                    <input type="checkbox" id="save_as_list" name="save_as_list" value="1" />
                                    <?php _e('Save uploaded emails as a new list', 'antioch-bulk-email'); ?>
                                </label><br>
                                
                                <div id="new_list_section" style="display: none; margin-left: 20px;">
                                    <input type="text" id="new_list_name" name="new_list_name" placeholder="<?php _e('List Name (e.g., Alumni 2025)', 'antioch-bulk-email'); ?>" class="regular-text" /><br>
                                    <textarea id="new_list_description" name="new_list_description" placeholder="<?php _e('Optional description', 'antioch-bulk-email'); ?>" rows="2" class="large-text"></textarea>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="subject"><?php _e('Email Subject', 'antioch-bulk-email'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="subject" name="subject" class="regular-text" required />
                            <p class="description">
                                <?php _e('Use {name}, {first_name}, {last_name} for personalization', 'antioch-bulk-email'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="html_content"><?php _e('Email Content (HTML)', 'antioch-bulk-email'); ?></label>
                        </th>
                        <td>
                            <?php 
                            wp_editor('', 'html_content', array(
                                'media_buttons' => true,
                                'textarea_rows' => 15,
                                'teeny' => false,
                                'tinymce' => array(
                                    'plugins' => 'lists,link,image,paste,textcolor',
                                    'toolbar1' => 'bold,italic,underline,link,unlink,forecolor,alignleft,aligncenter,alignright,bullist,numlist',
                                    'toolbar2' => 'undo,redo,image,removeformat,code'
                                )
                            )); 
                            ?>
                            <p class="description">
                                <?php _e('Use {name}, {first_name}, {last_name}, {email} for personalization', 'antioch-bulk-email'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="attachments"><?php _e('Attachments', 'antioch-bulk-email'); ?></label>
                        </th>
                        <td>
                            <input type="file" id="attachments" name="attachments[]" multiple />
                            <p class="description">
                                <?php _e('Optional: Select files to attach to emails', 'antioch-bulk-email'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="send_method"><?php _e('Sending Method', 'antioch-bulk-email'); ?></label>
                        </th>
                        <td>
                            <select id="send_method" name="send_method">
                                <option value="api"><?php _e('Mailgun API (Recommended)', 'antioch-bulk-email'); ?></option>
                                <option value="smtp"><?php _e('SMTP', 'antioch-bulk-email'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="delay"><?php _e('Delay Between Emails (seconds)', 'antioch-bulk-email'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="delay" name="delay" value="1" min="0" step="0.1" />
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Send Bulk Email', 'antioch-bulk-email'), 'primary', 'submit', false, array('id' => 'send-bulk-email')); ?>
            </form>
            
            <div id="bulk-email-progress" style="display: none;">
                <h3><?php _e('Sending Progress', 'antioch-bulk-email'); ?></h3>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 0%;"></div>
                </div>
                <div id="progress-text">0 / 0 emails sent</div>
                <div id="progress-log"></div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Radio button logic
            $('input[name="recipient_source"]').change(function() {
                if ($(this).val() === 'existing_list') {
                    $('#existing_list_id').show().prop('required', true);
                    $('#csv_upload_section').hide();
                    $('#csv_file').prop('required', false);
                } else {
                    $('#existing_list_id').hide().prop('required', false);
                    $('#csv_upload_section').show();
                    $('#csv_file').prop('required', true);
                }
            });
            
            // Save as list checkbox logic
            $('#save_as_list').change(function() {
                if ($(this).is(':checked')) {
                    $('#new_list_section').show();
                    $('#new_list_name').prop('required', true);
                } else {
                    $('#new_list_section').hide();
                    $('#new_list_name').prop('required', false);
                }
            });
            
            $('#send-bulk-email').click(function(e) {
                e.preventDefault();
                
                if (!confirm('<?php _e('Are you sure you want to send this bulk email campaign?', 'antioch-bulk-email'); ?>')) {
                    return;
                }
                
                var formData = new FormData($('#bulk-email-form')[0]);
                formData.append('action', 'send_bulk_email');
                formData.append('nonce', '<?php echo wp_create_nonce('antioch_bulk_email_nonce'); ?>');
                
                $('#bulk-email-progress').show();
                $('#send-bulk-email').prop('disabled', true);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            alert('<?php _e('Bulk email campaign completed!', 'antioch-bulk-email'); ?>');
                            location.reload();
                        } else {
                            alert('<?php _e('Error: ', 'antioch-bulk-email'); ?>' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('<?php _e('An error occurred while sending emails.', 'antioch-bulk-email'); ?>');
                    },
                    complete: function() {
                        $('#send-bulk-email').prop('disabled', false);
                    }
                });
            });
        });
        </script>
        
        <style>
        .progress-bar {
            width: 100%;
            height: 20px;
            background-color: #f1f1f1;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        .progress-fill {
            height: 100%;
            background-color: #4CAF50;
            transition: width 0.3s ease;
        }
        #progress-log {
            max-height: 300px;
            overflow-y: auto;
            background: #f9f9f9;
            padding: 10px;
            border: 1px solid #ddd;
            margin-top: 10px;
            font-family: monospace;
            font-size: 12px;
        }
        </style>
        <?php
    }
    
    // [Rest of the methods - email_lists_page, logs_page, settings_page, etc. - remain the same but get settings from WordPress options instead of .env]
    
    public function settings_page() {
        if (isset($_POST['submit'])) {
            update_option('antioch_mailgun_api_key', sanitize_text_field($_POST['mailgun_api_key']));
            update_option('antioch_mailgun_domain', sanitize_text_field($_POST['mailgun_domain']));
            update_option('antioch_from_email', sanitize_email($_POST['from_email']));
            update_option('antioch_from_name', sanitize_text_field($_POST['from_name']));
            update_option('antioch_smtp_username', sanitize_text_field($_POST['smtp_username']));
            update_option('antioch_smtp_password', sanitize_text_field($_POST['smtp_password']));
            
            echo '<div class="notice notice-success"><p>' . __('Settings saved!', 'antioch-bulk-email') . '</p></div>';
        }
        
        $api_key = get_option('antioch_mailgun_api_key', '');
        $domain = get_option('antioch_mailgun_domain', 'alumni.antiochians.org');
        $from_email = get_option('antioch_from_email', 'antiochalumni@alumni.antiochians.org');
        $from_name = get_option('antioch_from_name', 'Antioch Alumni Association');
        $smtp_username = get_option('antioch_smtp_username', '');
        $smtp_password = get_option('antioch_smtp_password', '');
        ?>
        <div class="wrap">
            <h1><?php _e('Bulk Email Settings', 'antioch-bulk-email'); ?></h1>
            
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="mailgun_api_key"><?php _e('Mailgun API Key', 'antioch-bulk-email'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="mailgun_api_key" name="mailgun_api_key" 
                                   value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
                            <p class="description"><?php _e('Your Mailgun production API key', 'antioch-bulk-email'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mailgun_domain"><?php _e('Mailgun Domain', 'antioch-bulk-email'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="mailgun_domain" name="mailgun_domain" 
                                   value="<?php echo esc_attr($domain); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="from_email"><?php _e('From Email', 'antioch-bulk-email'); ?></label>
                        </th>
                        <td>
                            <input type="email" id="from_email" name="from_email" 
                                   value="<?php echo esc_attr($from_email); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="from_name"><?php _e('From Name', 'antioch-bulk-email'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="from_name" name="from_name" 
                                   value="<?php echo esc_attr($from_name); ?>" class="regular-text" />
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('SMTP Settings (Optional)', 'antioch-bulk-email'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="smtp_username"><?php _e('SMTP Username', 'antioch-bulk-email'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="smtp_username" name="smtp_username" 
                                   value="<?php echo esc_attr($smtp_username); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="smtp_password"><?php _e('SMTP Password', 'antioch-bulk-email'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="smtp_password" name="smtp_password" 
                                   value="<?php echo esc_attr($smtp_password); ?>" class="regular-text" />
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('Webhook URL for Bounce Tracking', 'antioch-bulk-email'); ?></h2>
                <p><?php _e('Add this URL to your Mailgun webhook settings:', 'antioch-bulk-email'); ?></p>
                <code><?php echo admin_url('admin-ajax.php?action=handle_mailgun_webhook'); ?></code>
                
                <h2><?php _e('Auto-Updates', 'antioch-bulk-email'); ?></h2>
                <p><?php _e('This plugin automatically checks for updates from:', 'antioch-bulk-email'); ?></p>
                <code>https://github.com/<?php echo ANTIOCH_BULK_EMAIL_GITHUB_REPO; ?></code>
                <p class="description"><?php _e('Updates will appear in your WordPress admin when available.', 'antioch-bulk-email'); ?></p>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    // Add placeholder methods - you can copy the full implementations from the original file
    public function email_lists_page() { /* Implementation here */ }
    public function logs_page() { /* Implementation here */ }
    public function handle_mailgun_webhook() { /* Implementation here */ }
    public function handle_bulk_email() { /* Implementation here */ }
}

// Initialize the plugin
new AlumniBulkEmail();
?>