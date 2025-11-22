<?php
/**
 * Plugin Name: Alumni Bulk Email
 * Plugin URI: https://github.com/mattbaya/alumni-bulk-email-plugin
 * Description: Send bulk emails to alumni with Mailgun integration, CSV logging, and bounce tracking.
 * Version: 0.5.0
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
define('ALUMNI_BULK_EMAIL_VERSION', '0.5.0');
define('ALUMNI_BULK_EMAIL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ALUMNI_BULK_EMAIL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ALUMNI_BULK_EMAIL_GITHUB_REPO', 'mattbaya/alumni-bulk-email-plugin');

// Main plugin class
class AlumniBulkEmail {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_send_test_email', array($this, 'handle_test_email'));
        add_action('wp_ajax_send_test_campaign', array($this, 'handle_test_campaign'));
        add_action('wp_ajax_send_bulk_email', array($this, 'handle_bulk_email'));
        add_action('wp_ajax_upload_csv', array($this, 'handle_csv_upload'));
        add_action('wp_ajax_save_campaign', array($this, 'handle_save_campaign'));
        add_action('wp_ajax_load_campaign', array($this, 'handle_load_campaign'));
        add_action('wp_ajax_view_campaign', array($this, 'handle_view_campaign'));
        add_action('wp_ajax_delete_campaign', array($this, 'handle_delete_campaign'));
        add_action('wp_ajax_copy_campaign', array($this, 'handle_copy_campaign'));
        add_action('wp_ajax_load_recipient_list', array($this, 'handle_load_recipient_list'));
        add_action('wp_ajax_upload_save_csv', array($this, 'handle_upload_save_csv'));
        add_action('wp_ajax_create_recipient_list', array($this, 'handle_create_recipient_list'));
        add_action('wp_ajax_combine_recipient_lists', array($this, 'handle_combine_recipient_lists'));
        add_action('wp_ajax_view_recipient_list', array($this, 'handle_view_recipient_list'));
        add_action('wp_ajax_edit_recipient_row', array($this, 'handle_edit_recipient_row'));
        add_action('wp_ajax_bulk_edit_recipients', array($this, 'handle_bulk_edit_recipients'));
        add_action('wp_ajax_bulk_delete_recipients', array($this, 'handle_bulk_delete_recipients'));
        add_action('wp_ajax_get_list_columns', array($this, 'handle_get_list_columns'));
        add_action('wp_ajax_merge_csv_to_list', array($this, 'handle_merge_csv_to_list'));
        add_action('wp_ajax_export_recipient_list', array($this, 'handle_export_recipient_list'));
        add_action('wp_ajax_nopriv_handle_alumni_webhook', array($this, 'handle_mailgun_webhook'));
        add_action('wp_ajax_handle_alumni_webhook', array($this, 'handle_mailgun_webhook'));
        add_action('wp_ajax_recreate_tables', array($this, 'handle_recreate_tables'));
        
        // Unsubscribe functionality (public and admin access)
        add_action('wp_ajax_handle_unsubscribe', array($this, 'handle_unsubscribe'));
        add_action('wp_ajax_nopriv_handle_unsubscribe', array($this, 'handle_unsubscribe'));
        add_action('init', array($this, 'handle_unsubscribe_page'));
        
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
        
        // Email campaigns table
        $table_campaigns = $wpdb->prefix . 'alumni_email_campaigns';
        $sql_campaigns = "CREATE TABLE IF NOT EXISTS $table_campaigns (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            campaign_name varchar(255) NOT NULL,
            subject varchar(500) NOT NULL,
            html_content longtext NOT NULL,
            recipients_data longtext,
            recipients_count int DEFAULT 0,
            sent_count int DEFAULT 0,
            bounce_count int DEFAULT 0,
            open_count int DEFAULT 0,
            click_count int DEFAULT 0,
            status varchar(50) DEFAULT 'draft',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            sent_at datetime,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Email logs table  
        $table_logs = $wpdb->prefix . 'alumni_email_logs';
        $sql_logs = "CREATE TABLE IF NOT EXISTS $table_logs (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            campaign_id mediumint(9) NOT NULL,
            recipient_email varchar(255) NOT NULL,
            recipient_name varchar(255),
            status varchar(50) DEFAULT 'pending',
            sent_at datetime,
            opened_at datetime,
            clicked_at datetime,
            bounce_reason varchar(500),
            mailgun_message_id varchar(255),
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY recipient_email (recipient_email),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        // Unsubscribe table
        $table_unsubscribes = $wpdb->prefix . 'alumni_email_unsubscribes';
        $sql_unsubscribes = "CREATE TABLE IF NOT EXISTS $table_unsubscribes (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            unsubscribe_token varchar(255) NOT NULL,
            unsubscribed_at datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45),
            user_agent text,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            UNIQUE KEY unsubscribe_token (unsubscribe_token)
        ) $charset_collate;";
        
        // Recipients lists table
        $table_recipient_lists = $wpdb->prefix . 'alumni_recipient_lists';
        $sql_recipient_lists = "CREATE TABLE IF NOT EXISTS $table_recipient_lists (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            list_name varchar(255) NOT NULL,
            description text,
            recipients_data longtext NOT NULL,
            total_count int DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY list_name (list_name)
        ) $charset_collate;";
        
        dbDelta($sql_campaigns);
        dbDelta($sql_logs);
        dbDelta($sql_unsubscribes);
        dbDelta($sql_recipient_lists);
        
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
            'edit_posts',
            'alumni-bulk-email',
            array($this, 'admin_page'),
            'dashicons-email-alt',
            30
        );
        
        add_submenu_page(
            'alumni-bulk-email',
            'Recipients Lists',
            'Recipients Lists',
            'edit_posts',
            'alumni-recipient-lists',
            array($this, 'recipient_lists_page')
        );
        
        add_submenu_page(
            'alumni-bulk-email',
            'Unsubscribed',
            'Unsubscribed',
            'edit_posts',
            'alumni-unsubscribed',
            array($this, 'unsubscribed_page')
        );
        
        add_submenu_page(
            'alumni-bulk-email',
            'Bounced Emails',
            'Bounced Emails',
            'edit_posts',
            'alumni-bounced',
            array($this, 'bounced_page')
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
        // Check if Mailgun is configured
        if (!$this->is_mailgun_configured()) {
            ?>
            <div class="wrap">
                <h1>Alumni Bulk Email</h1>
                
                <div class="notice notice-error">
                    <p>⚠️ <strong>Mailgun not configured!</strong> Please <a href="<?php echo admin_url('admin.php?page=alumni-bulk-email-settings'); ?>">configure your Mailgun settings</a> before sending emails.</p>
                </div>
            </div>
            <?php
            return;
        }
        
        // Get campaigns for display - separate draft and completed
        global $wpdb;
        $saved_campaigns = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}alumni_email_campaigns 
            WHERE status = 'draft'
            ORDER BY created_at DESC
        ");
        
        $completed_campaigns = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}alumni_email_campaigns 
            WHERE status IN ('completed', 'sending', 'failed')
            ORDER BY sent_at DESC 
            LIMIT 10
        ");
        ?>
        <div class="wrap">
            <h1>Alumni Bulk Email</h1>
            
            <div style="margin-bottom: 20px;">
                <button type="button" id="new_campaign" class="button button-secondary">
                    ➕ New Campaign
                </button>
                <span style="margin-left: 10px; color: #666;">
                    Clear form to start a new campaign
                </span>
            </div>
            
            <!-- Create New Campaign -->
            <div class="postbox" style="margin: 20px 0;">
                <h2 class="hndle">📧 Send New Email Campaign</h2>
                <div class="inside">
                    <form id="bulk-email-form" enctype="multipart/form-data">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="campaign_name">Campaign Name</label>
                                </th>
                                <td>
                                    <input type="text" id="campaign_name" name="campaign_name" class="regular-text" required 
                                           placeholder="e.g., Spring 2025 Newsletter" />
                                    <p class="description">Give your campaign a memorable name for tracking</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label>Recipients Source</label>
                                </th>
                                <td>
                                    <div style="margin-bottom: 15px;">
                                        <label>
                                            <input type="radio" name="recipients_source" value="saved_list" checked>
                                            Use Saved Recipients List
                                        </label>
                                        <br>
                                        <label style="margin-top: 10px; display: inline-block;">
                                            <input type="radio" name="recipients_source" value="upload_csv">
                                            Upload New CSV File
                                        </label>
                                    </div>
                                    
                                    <div id="saved_list_section">
                                        <select id="saved_recipients_list" name="saved_recipients_list" style="width: 300px;">
                                            <option value="">Select a saved list...</option>
                                            <?php
                                            global $wpdb;
                                            $saved_lists = $wpdb->get_results("SELECT id, list_name, total_count FROM {$wpdb->prefix}alumni_recipient_lists ORDER BY updated_at DESC");
                                            foreach ($saved_lists as $list) {
                                                echo '<option value="' . $list->id . '">' . esc_html($list->list_name) . ' (' . $list->total_count . ' recipients)</option>';
                                            }
                                            ?>
                                        </select>
                                        <button type="button" id="preview_saved_list" class="button button-small" style="margin-left: 10px;">Preview List</button>
                                        
                                        <div id="email_column_selection" style="display: none; margin-top: 15px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                                            <label for="email_column_select"><strong>Select Email Column:</strong></label>
                                            <select id="email_column_select" name="email_column" style="width: 200px; margin-left: 10px;">
                                                <option value="">Choose email column...</option>
                                            </select>
                                            <p class="description" style="margin-top: 5px;">
                                                Choose which column contains the email addresses for this campaign.
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div id="upload_csv_section" style="display: none;">
                                        <input type="file" id="csv_file" name="csv_file" accept=".csv" />
                                        <input type="text" id="new_list_name" name="new_list_name" placeholder="List name (e.g., 'Alumni News 11-22-2024')" style="width: 300px; margin-left: 10px;" />
                                        <p class="description">
                                            CSV with columns: <strong>email</strong> (required), name, first_name, last_name<br>
                                            <button type="button" id="preview_csv" class="button button-small" style="margin-top: 5px;">Preview & Save List</button>
                                        </p>
                                    </div>
                                    
                                    <div id="csv_preview" style="display: none; margin-top: 10px;"></div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="subject">Email Subject</label>
                                </th>
                                <td>
                                    <input type="text" id="subject" name="subject" class="large-text" required 
                                           placeholder="Your subject line here..." />
                                    <p class="description">Use {name}, {first_name}, {last_name} for personalization</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="html_content">Email Content</label>
                                </th>
                                <td>
                                    <div style="margin-bottom: 15px;">
                                        <label>
                                            <input type="radio" name="content_method" value="editor" id="content_method_editor" checked />
                                            Compose using editor
                                        </label>
                                        <span style="margin: 0 20px;">|</span>
                                        <label>
                                            <input type="radio" name="content_method" value="upload" id="content_method_upload" />
                                            Upload HTML file
                                        </label>
                                    </div>
                                    
                                    <div id="content_editor_section">
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
                                    </div>
                                    
                                    <div id="content_upload_section" style="display: none;">
                                        <input type="file" id="html_file" name="html_file" accept=".html,.htm" />
                                        <button type="button" id="preview_html_file" class="button button-small" style="margin-left: 10px;">Preview HTML File</button>
                                        <div id="html_file_preview" style="margin-top: 15px; display: none;">
                                            <h4>HTML Content Preview:</h4>
                                            <div style="border: 1px solid #ddd; padding: 15px; background: #f9f9f9; max-height: 300px; overflow-y: auto;">
                                                <div id="html_preview_content"></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <p class="description">Use {name}, {first_name}, {last_name}, {email} for personalization</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="attachments">Attachments</label>
                                </th>
                                <td>
                                    <input type="file" id="attachments" name="attachments[]" multiple />
                                    <p class="description">Optional: Select files to attach (PDFs, images, etc.)</p>
                                </td>
                            </tr>
                        </table>
                        
                        <div style="margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa;">
                            <h3 style="margin-top: 0;">📊 Campaign Preview</h3>
                            <div id="campaign_summary">
                                <p><strong>Campaign:</strong> <span id="preview_campaign_name">-</span></p>
                                <p><strong>Recipients:</strong> <span id="preview_recipient_count">0</span></p>
                                <p><strong>Subject:</strong> <span id="preview_subject">-</span></p>
                            </div>
                        </div>
                        
                        <div id="test_email_section" style="margin: 20px 0; padding: 15px; background: #f0f8ff; border-left: 4px solid #0073aa;">
                            <h3 style="margin-top: 0;">📨 Test Your Campaign</h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="test_email_address">Test Email Address</label>
                                    </th>
                                    <td>
                                        <input type="email" id="test_email_address" name="test_email_address" 
                                               class="regular-text" placeholder="your@email.com" autocomplete="off" />
                                        <input type="button" id="send_test_campaign" class="button button-secondary" 
                                               value="📧 Send Test Email" style="margin-left: 10px;" />
                                        <p class="description">Send a test version of this campaign to verify content and formatting before sending to all recipients.</p>
                                    </td>
                                </tr>
                            </table>
                            <div id="test_campaign_result" style="margin-top: 10px;"></div>
                        </div>
                        
                        <p class="submit">
                            <input type="button" id="save_campaign" class="button button-secondary button-large" 
                                   value="💾 Save Campaign" style="margin-right: 10px;" />
                            <input type="button" id="send_campaign" class="button button-primary button-large" 
                                   value="🚀 Send Campaign" />
                            <input type="hidden" id="campaign_id" name="campaign_id" value="" />
                            <span style="margin-left: 15px; color: #666;">
                                Save as draft or send emails to all recipients in your CSV file.
                            </span>
                        </p>
                    </form>
                </div>
            </div>
            
            <!-- Campaign Progress -->
            <div id="campaign_progress" style="display: none; margin: 20px 0;">
                <div class="postbox">
                    <h2 class="hndle">📈 Campaign Progress</h2>
                    <div class="inside">
                        <div class="progress-bar" style="width: 100%; height: 25px; background: #f1f1f1; border-radius: 12px; overflow: hidden;">
                            <div id="progress_fill" style="height: 100%; background: linear-gradient(90deg, #0073aa, #00a0d2); width: 0%; transition: width 0.3s ease;"></div>
                        </div>
                        <p id="progress_text" style="margin: 10px 0; font-weight: bold;">Preparing to send...</p>
                        <div id="progress_log" style="max-height: 200px; overflow-y: auto; background: #f9f9f9; padding: 10px; border: 1px solid #ddd; font-family: monospace; font-size: 12px;"></div>
                    </div>
                </div>
            </div>
            
            <!-- Saved Campaigns -->
            <?php if (!empty($saved_campaigns)): ?>
            <div class="postbox">
                <h2 class="hndle">💾 Saved Campaign Drafts</h2>
                <div class="inside">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Campaign Name</th>
                                <th>Subject</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($saved_campaigns as $campaign): ?>
                            <tr>
                                <td><strong><?php echo esc_html($campaign->campaign_name); ?></strong></td>
                                <td><?php echo esc_html($campaign->subject); ?></td>
                                <td><?php echo esc_html(date('M j, Y H:i', strtotime($campaign->created_at))); ?></td>
                                <td>
                                    <button type="button" class="button button-small load-campaign" 
                                            data-campaign-id="<?php echo $campaign->id; ?>">
                                        📝 Load & Edit
                                    </button>
                                    <button type="button" class="button button-small button-link-delete delete-campaign" 
                                            data-campaign-id="<?php echo $campaign->id; ?>" 
                                            style="margin-left: 5px;">
                                        🗑️ Delete
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Completed Campaigns -->
            <?php if (!empty($completed_campaigns)): ?>
            <div class="postbox">
                <h2 class="hndle">📋 Completed Campaigns</h2>
                <div class="inside">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Campaign Name</th>
                                <th>Subject</th>
                                <th>Recipients</th>
                                <th>Sent</th>
                                <th>Status</th>
                                <th>Date Sent</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($completed_campaigns as $campaign): ?>
                            <tr>
                                <td><strong><?php echo esc_html($campaign->campaign_name); ?></strong></td>
                                <td><?php echo esc_html($campaign->subject); ?></td>
                                <td><?php echo intval($campaign->recipients_count); ?></td>
                                <td><?php echo intval($campaign->sent_count); ?></td>
                                <td>
                                    <span class="status-<?php echo esc_attr($campaign->status); ?>">
                                        <?php echo esc_html(ucfirst($campaign->status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    echo $campaign->sent_at ? 
                                        esc_html(date('M j, Y H:i', strtotime($campaign->sent_at))) : 
                                        esc_html(date('M j, Y H:i', strtotime($campaign->created_at))); 
                                    ?>
                                </td>
                                <td>
                                    <button type="button" class="button button-small view-campaign" 
                                            data-campaign-id="<?php echo $campaign->id; ?>">
                                        👁️ View
                                    </button>
                                    <button type="button" class="button button-small load-completed-campaign" 
                                            data-campaign-id="<?php echo $campaign->id; ?>" 
                                            style="margin-left: 5px;">
                                        📝 Load & Reuse
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            let csvData = [];
            
            // Recipients source radio button handling
            $('input[name="recipients_source"]').change(function() {
                if ($(this).val() === 'saved_list') {
                    $('#saved_list_section').show();
                    $('#upload_csv_section').hide();
                    $('#csv_file').removeAttr('required');
                } else {
                    $('#saved_list_section').hide();
                    $('#upload_csv_section').show();
                    $('#csv_file').attr('required', 'required');
                }
            });
            
            // Load email column options when saved list is selected
            $('#saved_recipients_list').change(function() {
                var listId = $(this).val();
                if (listId) {
                    loadEmailColumnOptions(listId);
                    $('#email_column_selection').show();
                } else {
                    $('#email_column_selection').hide();
                    $('#email_column_select').empty().append('<option value="">Choose email column...</option>');
                }
            });
            
            // Preview saved list
            $('#preview_saved_list').click(function() {
                var listId = $('#saved_recipients_list').val();
                if (!listId) {
                    alert('Please select a list first.');
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'load_recipient_list',
                        nonce: '<?php echo wp_create_nonce('load_recipient_list'); ?>',
                        list_id: listId
                    },
                    success: function(response) {
                        if (response.success) {
                            csvData = response.data.recipients;
                            displayRecipientsSummary();
                            $('#csv_preview').show();
                        } else {
                            alert('Error loading list: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred while loading the list.');
                    }
                });
            });
            
            // Update campaign preview
            function updatePreview() {
                $('#preview_campaign_name').text($('#campaign_name').val() || '-');
                $('#preview_subject').text($('#subject').val() || '-');
                $('#preview_recipient_count').text(csvData.length || '0');
            }
            
            $('#campaign_name, #subject').on('input', updatePreview);
            
            // Content method switching
            $('input[name="content_method"]').change(function() {
                if ($(this).val() === 'editor') {
                    $('#content_editor_section').show();
                    $('#content_upload_section').hide();
                    $('#html_file_preview').hide();
                } else {
                    $('#content_editor_section').hide();
                    $('#content_upload_section').show();
                }
            });
            
            // Preview HTML file
            $('#preview_html_file').click(function() {
                var fileInput = $('#html_file')[0];
                if (!fileInput.files[0]) {
                    alert('Please select an HTML file first.');
                    return;
                }
                
                var reader = new FileReader();
                reader.onload = function(e) {
                    var htmlContent = e.target.result;
                    $('#html_preview_content').html(htmlContent);
                    $('#html_file_preview').show();
                    
                    // Also update the hidden editor content
                    if (tinyMCE.get('html_content')) {
                        tinyMCE.get('html_content').setContent(htmlContent);
                    }
                };
                reader.readAsText(fileInput.files[0]);
            });
            
            // Prevent test email input from affecting other fields
            $('#test_email_address').on('input change keyup', function(e) {
                e.stopPropagation();
                // This input should not trigger any other events
            });
            
            // New Campaign - Clear form
            $('#new_campaign').click(function() {
                $('#campaign_id').val('');
                $('#campaign_name').val('');
                $('#subject').val('');
                $('#csv_file').val('');
                $('#test_email_address').val('');
                $('#test_campaign_result').html('');
                tinyMCE.get('html_content').setContent('');
                $('#csv_preview').hide();
                csvData = [];
                updatePreview();
                
                $('html, body').animate({
                    scrollTop: $('#bulk-email-form').offset().top - 100
                }, 500);
            });
            
            // Save Campaign
            $('#save_campaign').click(function() {
                if (!$('#campaign_name').val() || !$('#subject').val()) {
                    alert('Please fill in the campaign name and subject.');
                    return;
                }
                
                if (!tinyMCE.get('html_content').getContent()) {
                    alert('Please write your email content.');
                    return;
                }
                
                var formData = new FormData();
                formData.append('action', 'save_campaign');
                formData.append('nonce', '<?php echo wp_create_nonce('save_campaign'); ?>');
                formData.append('campaign_id', $('#campaign_id').val());
                formData.append('campaign_name', $('#campaign_name').val());
                formData.append('subject', $('#subject').val());
                formData.append('html_content', tinyMCE.get('html_content').getContent());
                
                $('#save_campaign').prop('disabled', true).val('Saving...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            alert('Campaign saved successfully!');
                            $('#campaign_id').val(response.data.campaign_id);
                            location.reload();
                        } else {
                            alert('Error saving campaign: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred while saving the campaign.');
                    },
                    complete: function() {
                        $('#save_campaign').prop('disabled', false).val('💾 Save Campaign');
                    }
                });
            });
            
            // Load Campaign
            $('.load-campaign').click(function() {
                var campaignId = $(this).data('campaign-id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'load_campaign',
                        nonce: '<?php echo wp_create_nonce('load_campaign'); ?>',
                        campaign_id: campaignId
                    },
                    success: function(response) {
                        if (response.success) {
                            var campaign = response.data.campaign;
                            $('#campaign_id').val(campaign.id);
                            $('#campaign_name').val(campaign.campaign_name);
                            $('#subject').val(campaign.subject);
                            tinyMCE.get('html_content').setContent(campaign.html_content);
                            updatePreview();
                            
                            $('html, body').animate({
                                scrollTop: $('#bulk-email-form').offset().top - 100
                            }, 500);
                        } else {
                            alert('Error loading campaign: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred while loading the campaign.');
                    }
                });
            });
            
            // Delete Campaign
            $('.delete-campaign').click(function() {
                var campaignId = $(this).data('campaign-id');
                
                if (!confirm('Are you sure you want to delete this campaign? This action cannot be undone.')) {
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'delete_campaign',
                        nonce: '<?php echo wp_create_nonce('delete_campaign'); ?>',
                        campaign_id: campaignId
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error deleting campaign: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred while deleting the campaign.');
                    }
                });
            });
            
            // Send Test Campaign
            $('#send_test_campaign').click(function() {
                var testEmail = $('#test_email_address').val();
                var campaignName = $('#campaign_name').val();
                var subject = $('#subject').val();
                var htmlContent = tinyMCE.get('html_content').getContent();
                var button = $(this);
                var resultDiv = $('#test_campaign_result');
                
                if (!testEmail) {
                    resultDiv.html('<div class="notice notice-error"><p>Please enter a test email address.</p></div>');
                    return;
                }
                
                if (!campaignName || !subject) {
                    resultDiv.html('<div class="notice notice-error"><p>Please fill in campaign name and subject.</p></div>');
                    return;
                }
                
                if (!htmlContent) {
                    resultDiv.html('<div class="notice notice-error"><p>Please write your email content.</p></div>');
                    return;
                }
                
                button.prop('disabled', true).val('Sending...');
                resultDiv.html('<div class="notice notice-info"><p>Sending test email...</p></div>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'send_test_campaign',
                        nonce: '<?php echo wp_create_nonce('send_test_campaign'); ?>',
                        test_email: testEmail,
                        campaign_name: campaignName,
                        subject: subject,
                        html_content: htmlContent
                    },
                    success: function(response) {
                        if (response.success) {
                            resultDiv.html('<div class="notice notice-success"><p>✅ ' + response.data.message + '</p></div>');
                        } else {
                            resultDiv.html('<div class="notice notice-error"><p>❌ Error: ' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        resultDiv.html('<div class="notice notice-error"><p>❌ An error occurred while sending the test email.</p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).val('📧 Send Test Email');
                    }
                });
            });
            
            // View Completed Campaign
            $('.view-campaign').click(function() {
                var campaignId = $(this).data('campaign-id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'view_campaign',
                        nonce: '<?php echo wp_create_nonce('view_campaign'); ?>',
                        campaign_id: campaignId
                    },
                    success: function(response) {
                        if (response.success) {
                            var campaign = response.data.campaign;
                            var modalHtml = '<div id="campaign-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000;">' +
                                '<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; max-width: 800px; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">' +
                                '<h2>📧 Campaign: ' + campaign.campaign_name + '</h2>' +
                                '<p><strong>Subject:</strong> ' + campaign.subject + '</p>' +
                                '<p><strong>Recipients:</strong> ' + campaign.recipients_count + ' | <strong>Sent:</strong> ' + campaign.sent_count + ' | <strong>Status:</strong> ' + campaign.status + '</p>' +
                                '<p><strong>Date Sent:</strong> ' + (campaign.sent_at || campaign.created_at) + '</p>' +
                                '<div style="border: 1px solid #ddd; padding: 15px; margin: 20px 0; background: #f9f9f9;"><strong>Email Content:</strong><div style="margin-top: 10px; border: 1px solid #ccc; padding: 15px; background: white; max-height: 400px; overflow-y: auto;">' + campaign.html_content + '</div></div>' +
                                '<button type="button" id="close-modal" class="button button-primary" style="margin-right: 10px;">Close</button>' +
                                '<button type="button" class="button button-secondary load-this-campaign" data-campaign-id="' + campaign.id + '">Load & Reuse This Campaign</button>' +
                                '<button type="button" class="button button-secondary copy-this-campaign" data-campaign-id="' + campaign.id + '" style="margin-left: 10px;">Copy to New Campaign</button>' +
                                '</div></div>';
                            
                            $('body').append(modalHtml);
                            
                            $('#close-modal, #campaign-modal').click(function(e) {
                                if (e.target === this) {
                                    $('#campaign-modal').remove();
                                }
                            });
                        } else {
                            alert('Error viewing campaign: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred while viewing the campaign.');
                    }
                });
            });
            
            // Load Completed Campaign for Reuse
            $('.load-completed-campaign').click(function() {
                var campaignId = $(this).data('campaign-id');
                loadCampaignForReuse(campaignId);
            });
            
            // Handle load campaign from modal
            $(document).on('click', '.load-this-campaign', function() {
                var campaignId = $(this).data('campaign-id');
                $('#campaign-modal').remove();
                loadCampaignForReuse(campaignId);
            });
            
            // Handle copy campaign from modal
            $(document).on('click', '.copy-this-campaign', function() {
                var campaignId = $(this).data('campaign-id');
                $('#campaign-modal').remove();
                copyCampaignToNew(campaignId);
            });
            
            function loadCampaignForReuse(campaignId) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'load_campaign',
                        nonce: '<?php echo wp_create_nonce('load_campaign'); ?>',
                        campaign_id: campaignId
                    },
                    success: function(response) {
                        if (response.success) {
                            var campaign = response.data.campaign;
                            
                            // Clear campaign ID to create new draft
                            $('#campaign_id').val('');
                            
                            // Load campaign data with " (Copy)" suffix
                            $('#campaign_name').val(campaign.campaign_name + ' (Copy)');
                            $('#subject').val(campaign.subject);
                            tinyMCE.get('html_content').setContent(campaign.html_content);
                            updatePreview();
                            
                            // Clear CSV and test email fields since this is a reuse
                            $('#csv_file').val('');
                            $('#test_email_address').val('');
                            $('#test_campaign_result').html('');
                            $('#csv_preview').hide();
                            csvData = [];
                            
                            $('html, body').animate({
                                scrollTop: $('#bulk-email-form').offset().top - 100
                            }, 500);
                            
                            // Show success message
                            alert('Campaign loaded successfully! Ready to customize and send to new recipients.');
                        } else {
                            alert('Error loading campaign: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred while loading the campaign.');
                    }
                });
            }
            
            function copyCampaignToNew(campaignId) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'copy_campaign',
                        nonce: '<?php echo wp_create_nonce('copy_campaign'); ?>',
                        campaign_id: campaignId
                    },
                    success: function(response) {
                        if (response.success) {
                            var campaign = response.data.campaign;
                            var recipients = response.data.recipients;
                            
                            // Clear campaign ID to create new campaign
                            $('#campaign_id').val('');
                            $('#campaign_name').val(campaign.campaign_name + ' (Copy)');
                            $('#subject').val(campaign.subject);
                            tinyMCE.get('html_content').setContent(campaign.html_content);
                            updatePreview();
                            
                            // Load recipients if available
                            if (recipients && recipients.length > 0) {
                                csvData = recipients;
                                displayRecipientsSummary();
                                $('#csv_preview').show();
                            } else {
                                $('#csv_file').val('');
                                $('#csv_preview').hide();
                                csvData = [];
                            }
                            
                            $('#test_email_address').val('');
                            $('#test_campaign_result').html('');
                            
                            $('html, body').animate({
                                scrollTop: $('#bulk-email-form').offset().top - 100
                            }, 500);
                            
                            // Show success message
                            alert('Campaign copied successfully with recipients! Ready to customize and send.');
                        } else {
                            alert('Error copying campaign: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred while copying the campaign.');
                    }
                });
            }
            
            function displayRecipientsSummary() {
                var html = '<div class="notice notice-success"><p><strong>Recipients loaded from copied campaign:</strong> ' + csvData.length + ' recipients</p></div>';
                html += '<table class="wp-list-table widefat fixed striped">';
                html += '<thead><tr><th>Name</th><th>Email</th></tr></thead>';
                
                csvData.slice(0, 10).forEach(function(row) {
                    html += '<tr><td>' + (row.name || '') + '</td><td>' + (row.email || '') + '</td></tr>';
                });
                
                if (csvData.length > 10) {
                    html += '<tr><td colspan="2"><em>... and ' + (csvData.length - 10) + ' more</em></td></tr>';
                }
                html += '</table>';
                
                $('#csv_preview').html(html);
            }
            
            // Load email column options for selected list
            function loadEmailColumnOptions(listId) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'get_list_columns',
                        nonce: '<?php echo wp_create_nonce('get_list_columns'); ?>',
                        list_id: listId
                    },
                    success: function(response) {
                        if (response.success) {
                            var select = $('#email_column_select');
                            select.empty();
                            select.append('<option value="">Choose email column...</option>');
                            
                            // Add all columns as options
                            response.data.columns.forEach(function(column) {
                                var displayName = column.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                                var selected = '';
                                
                                // Auto-select likely email columns
                                var columnLower = column.toLowerCase();
                                if (columnLower.includes('email') || columnLower.includes('e_mail') || columnLower.includes('e-mail')) {
                                    selected = ' selected';
                                }
                                
                                select.append('<option value="' + column + '"' + selected + '>' + displayName + '</option>');
                            });
                        } else {
                            alert('Error loading list columns: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred while loading list columns.');
                    }
                });
            }
            
            // CSV Preview & Save
            $('#preview_csv').click(function() {
                var fileInput = $('#csv_file')[0];
                if (!fileInput.files[0]) {
                    alert('Please select a CSV file first.');
                    return;
                }
                
                var listName = $('#new_list_name').val().trim();
                if (!listName) {
                    alert('Please enter a name for this recipients list.');
                    return;
                }
                
                var formData = new FormData();
                formData.append('csv_file', fileInput.files[0]);
                formData.append('list_name', listName);
                formData.append('action', 'upload_save_csv');
                formData.append('nonce', '<?php echo wp_create_nonce('upload_save_csv'); ?>');
                
                $('#csv_preview').html('<p>Processing and saving list...</p>').show();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            csvData = response.data.recipients;
                            updatePreview();
                            
                            var html = '<h4>✅ Recipients List Saved: "' + listName + '" (' + csvData.length + ' recipients)</h4>';
                            html += '<div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">';
                            html += '<table style="width: 100%; border-collapse: collapse;">';
                            html += '<tr style="background: #f9f9f9;"><th>Email</th><th>Name</th></tr>';
                            
                            csvData.slice(0, 10).forEach(function(recipient) {
                                html += '<tr><td>' + recipient.email + '</td><td>' + (recipient.name || recipient.first_name + ' ' + recipient.last_name) + '</td></tr>';
                            });
                            
                            if (csvData.length > 10) {
                                html += '<tr><td colspan="2"><em>... and ' + (csvData.length - 10) + ' more</em></td></tr>';
                            }
                            html += '</table></div>';
                            
                            $('#csv_preview').html(html);
                        } else {
                            $('#csv_preview').html('<div class="notice notice-error"><p>Error: ' + response.data.message + '</p></div>');
                        }
                    }
                });
            });
            
            // Send Campaign
            $('#send_campaign').click(function() {
                if (!csvData.length) {
                    alert('Please upload and preview your CSV file first.');
                    return;
                }
                
                if (!$('#campaign_name').val() || !$('#subject').val()) {
                    alert('Please fill in the campaign name and subject.');
                    return;
                }
                
                // Check content based on composition method
                var contentMethod = $('input[name="content_method"]:checked').val();
                if (contentMethod === 'editor') {
                    if (!tinyMCE.get('html_content').getContent()) {
                        alert('Please write your email content.');
                        return;
                    }
                } else if (contentMethod === 'upload') {
                    if (!$('#html_file')[0].files.length) {
                        alert('Please upload an HTML file.');
                        return;
                    }
                }
                
                var confirmed = confirm('Send email campaign to ' + csvData.length + ' recipients?\\n\\nThis action cannot be undone.');
                if (!confirmed) return;
                
                var formData = new FormData($('#bulk-email-form')[0]);
                formData.append('action', 'send_bulk_email');
                formData.append('nonce', '<?php echo wp_create_nonce('send_bulk_email'); ?>');
                
                // Append content based on method
                var contentMethod = $('input[name="content_method"]:checked').val();
                if (contentMethod === 'editor') {
                    formData.append('html_content', tinyMCE.get('html_content').getContent());
                } else if (contentMethod === 'upload') {
                    formData.append('html_file', $('#html_file')[0].files[0]);
                }
                formData.append('content_method', contentMethod);
                formData.append('campaign_id', $('#campaign_id').val());
                
                $('#send_campaign').prop('disabled', true);
                $('#campaign_progress').show();
                
                startCampaignProgress();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            $('#progress_text').text('✅ Campaign completed successfully!');
                            $('#progress_fill').css('width', '100%').css('background', 'linear-gradient(90deg, #46b450, #5cbf60)');
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            $('#progress_text').text('❌ Campaign failed: ' + response.data.message);
                            $('#progress_fill').css('background', 'linear-gradient(90deg, #dc3232, #e65054)');
                        }
                    },
                    complete: function() {
                        $('#send_campaign').prop('disabled', false);
                    }
                });
            });
            
            function startCampaignProgress() {
                let progress = 0;
                const interval = setInterval(function() {
                    progress += Math.random() * 15;
                    if (progress > 95) progress = 95;
                    
                    $('#progress_fill').css('width', progress + '%');
                    $('#progress_text').text('Sending emails... ' + Math.round(progress) + '%');
                    
                    if (progress >= 95) {
                        clearInterval(interval);
                    }
                }, 500);
            }
        });
        </script>
        
        <style>
        .status-draft { color: #666; }
        .status-sending { color: #0073aa; font-weight: bold; }
        .status-completed { color: #46b450; font-weight: bold; }
        .status-failed { color: #dc3232; font-weight: bold; }
        .postbox { background: white; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,0.04); }
        .postbox .hndle { padding: 12px; background: #f1f1f1; border-bottom: 1px solid #ccd0d4; font-size: 14px; font-weight: 600; }
        .postbox .inside { padding: 20px; }
        </style>
        <?php
    }
    
    private function is_mailgun_configured() {
        $api_key = get_option('alumni_mailgun_api_key', '');
        $domain = get_option('alumni_mailgun_domain', '');
        $from_email = get_option('alumni_from_email', '');
        return !empty($api_key) && !empty($domain) && !empty($from_email);
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
                
                <h2>Plugin Information</h2>
                <p>Repository: <code>https://github.com/<?php echo ALUMNI_BULK_EMAIL_GITHUB_REPO; ?></code></p>
                <p>Current version: <strong><?php echo ALUMNI_BULK_EMAIL_VERSION; ?></strong></p>
                <p class="description">Download the latest version manually from GitHub when updates are available.</p>
                
                <h2>Database</h2>
                <p>If you're experiencing issues with saving campaigns, you can recreate the database tables:</p>
                <button type="button" id="recreate_tables" class="button button-secondary">
                    🔧 Recreate Database Tables
                </button>
                <div id="recreate_tables_result" style="margin-top: 10px;"></div>
                
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
            
            // Recreate Tables
            $('#recreate_tables').click(function() {
                var button = $(this);
                var resultDiv = $('#recreate_tables_result');
                
                if (!confirm('This will recreate the database tables. Are you sure?')) {
                    return;
                }
                
                button.prop('disabled', true).text('Recreating...');
                resultDiv.html('<div class="notice notice-info"><p>Recreating database tables...</p></div>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'recreate_tables',
                        nonce: '<?php echo wp_create_nonce('recreate_tables'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            resultDiv.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                        } else {
                            resultDiv.html('<div class="notice notice-error"><p>Error: ' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        resultDiv.html('<div class="notice notice-error"><p>An error occurred while recreating tables.</p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('🔧 Recreate Database Tables');
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
    
    public function handle_test_campaign() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'send_test_campaign') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        $test_email = sanitize_email($_POST['test_email']);
        $campaign_name = sanitize_text_field($_POST['campaign_name']);
        $subject = sanitize_text_field($_POST['subject']);
        $html_content = wp_kses_post($_POST['html_content']);
        
        if (!is_email($test_email)) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Invalid email address')));
            exit;
        }
        
        if (empty($campaign_name) || empty($subject) || empty($html_content)) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Please fill in all campaign fields')));
            exit;
        }
        
        if (!$this->is_mailgun_configured()) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Mailgun not configured. Please check your settings.')));
            exit;
        }
        
        // Create sample recipient data for personalization testing
        $sample_recipient = array(
            'name' => 'Test User',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $test_email
        );
        
        // Personalize the content with test data
        $personalized_subject = $this->personalize_content($subject, $sample_recipient);
        $personalized_html = $this->personalize_content($html_content, $sample_recipient);
        
        // Add test indicators to the email
        $test_subject = "[TEST] {$personalized_subject}";
        $test_html = '<div style="background: #ffebee; color: #c62828; padding: 15px; border: 2px solid #ef5350; margin-bottom: 20px; text-align: center; font-weight: bold;">
            🧪 THIS IS A TEST EMAIL for campaign: "' . esc_html($campaign_name) . '"
        </div>' . $personalized_html;
        
        // Add unsubscribe links to test email
        $test_html = $this->add_unsubscribe_links($test_html, $test_email);
        
        // Send via Mailgun
        $result = $this->send_mailgun_email($test_email, $test_subject, $test_html);
        
        if ($result['success']) {
            echo json_encode(array('success' => true, 'data' => array('message' => 'Test email sent successfully! Check your inbox for "' . $test_subject . '"')));
        } else {
            echo json_encode(array('success' => false, 'data' => array('message' => $result['error'])));
        }
        
        exit;
    }
    
    public function handle_csv_upload() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'upload_csv') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'No file uploaded or upload error')));
            exit;
        }
        
        $file = $_FILES['csv_file']['tmp_name'];
        $recipients = $this->parse_csv_file($file);
        
        if (empty($recipients)) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'No valid email addresses found in CSV')));
            exit;
        }
        
        echo json_encode(array(
            'success' => true, 
            'data' => array(
                'recipients' => $recipients,
                'count' => count($recipients)
            )
        ));
        exit;
    }
    
    public function handle_bulk_email() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'send_bulk_email') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        if (!$this->is_mailgun_configured()) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Mailgun not configured')));
            exit;
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        $campaign_name = sanitize_text_field($_POST['campaign_name']);
        $subject = sanitize_text_field($_POST['subject']);
        $content_method = sanitize_text_field($_POST['content_method']);
        $recipients_source = sanitize_text_field($_POST['recipients_source']);
        $saved_recipients_list = intval($_POST['saved_recipients_list']);
        $email_column = sanitize_text_field($_POST['email_column']);
        
        // Handle content based on method
        if ($content_method === 'upload' && isset($_FILES['html_file'])) {
            $html_content = file_get_contents($_FILES['html_file']['tmp_name']);
            $html_content = wp_kses_post($html_content);
        } else {
            $html_content = wp_kses_post($_POST['html_content']);
        }
        
        // If we have a campaign_id, load from saved campaign
        if ($campaign_id > 0) {
            global $wpdb;
            $saved_campaign = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}alumni_email_campaigns WHERE id = %d AND status = 'draft'",
                $campaign_id
            ));
            
            if ($saved_campaign) {
                $campaign_name = $saved_campaign->campaign_name;
                $subject = $saved_campaign->subject;
                $html_content = $saved_campaign->html_content;
            }
        }
        
        if (empty($campaign_name) || empty($subject) || empty($html_content)) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Missing required fields')));
            exit;
        }
        
        // Get recipients based on source
        $recipients = array();
        $list_id_used = null;
        
        if ($recipients_source === 'saved_list') {
            // Load from saved list
            if (!$saved_recipients_list || !$email_column) {
                echo json_encode(array('success' => false, 'data' => array('message' => 'Saved list and email column are required')));
                exit;
            }
            
            global $wpdb;
            $list = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}alumni_recipient_lists WHERE id = %d",
                $saved_recipients_list
            ));
            
            if (!$list || !$list->recipients_data) {
                echo json_encode(array('success' => false, 'data' => array('message' => 'Saved list not found')));
                exit;
            }
            
            $recipients = json_decode($list->recipients_data, true);
            if (empty($recipients)) {
                echo json_encode(array('success' => false, 'data' => array('message' => 'No recipients in saved list')));
                exit;
            }
            
            // Validate email column exists
            if (!isset($recipients[0][$email_column])) {
                echo json_encode(array('success' => false, 'data' => array('message' => 'Email column not found in list')));
                exit;
            }
            
            $list_id_used = $saved_recipients_list;
            
        } else {
            // Parse CSV file
            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(array('success' => false, 'data' => array('message' => 'CSV file required')));
                exit;
            }
            
            $recipients = $this->parse_csv_file($_FILES['csv_file']['tmp_name']);
            if (empty($recipients)) {
                echo json_encode(array('success' => false, 'data' => array('message' => 'No valid recipients in CSV')));
                exit;
            }
        }
        
        // Create or update campaign record
        global $wpdb;
        if ($campaign_id > 0) {
            // Update existing campaign for sending
            $wpdb->update(
                $wpdb->prefix . 'alumni_email_campaigns',
                array(
                    'recipients_count' => count($recipients),
                    'status' => 'sending'
                ),
                array('id' => $campaign_id),
                array('%d', '%s'),
                array('%d')
            );
            $sending_campaign_id = $campaign_id;
        } else {
            // Create new campaign record
            $sending_campaign_id = $this->create_campaign_record($campaign_name, $subject, $html_content, count($recipients), $recipients);
            
            if (!$sending_campaign_id) {
                echo json_encode(array('success' => false, 'data' => array('message' => 'Failed to create campaign record')));
                exit;
            }
        }
        
        // Send emails
        $sent_count = 0;
        $failed_count = 0;
        
        foreach ($recipients as $recipient) {
            // Skip if email is unsubscribed
            if ($this->is_email_unsubscribed($recipient['email'])) {
                // Log as skipped
                $this->log_email_attempt($sending_campaign_id, $recipient, 'skipped', array('error' => 'Email unsubscribed'));
                continue;
            }
            
            // Skip if email has bounced too many times
            if ($this->is_email_bounced($recipient['email'])) {
                // Log as skipped
                $this->log_email_attempt($sending_campaign_id, $recipient, 'skipped', array('error' => 'Email bounced'));
                continue;
            }
            
            $personalized_subject = $this->personalize_content($subject, $recipient);
            $personalized_html = $this->personalize_content($html_content, $recipient);
            
            // Add unsubscribe links to the email
            $html_with_unsubscribe = $this->add_unsubscribe_links($personalized_html, $recipient['email']);
            
            $result = $this->send_mailgun_email(
                $recipient['email'],
                $personalized_subject,
                $html_with_unsubscribe
            );
            
            // Log email attempt
            $this->log_email_attempt($sending_campaign_id, $recipient, $result['success'] ? 'sent' : 'failed', $result);
            
            if ($result['success']) {
                $sent_count++;
            } else {
                $failed_count++;
            }
            
            // Small delay to avoid overwhelming Mailgun
            usleep(100000); // 0.1 second delay
        }
        
        // Update campaign statistics
        $wpdb->update(
            $wpdb->prefix . 'alumni_email_campaigns',
            array(
                'sent_count' => $sent_count,
                'status' => 'completed',
                'sent_at' => current_time('mysql')
            ),
            array('id' => $sending_campaign_id),
            array('%d', '%s', '%s'),
            array('%d')
        );
        
        // Auto-tag recipients if using a saved list
        if ($list_id_used && $sent_count > 0) {
            $this->add_campaign_tags_to_list($list_id_used, $campaign_name, current_time('Y-m-d'));
        }
        
        echo json_encode(array(
            'success' => true,
            'data' => array(
                'message' => "Campaign completed! Sent: {$sent_count}, Failed: {$failed_count}",
                'sent_count' => $sent_count,
                'failed_count' => $failed_count
            )
        ));
        exit;
    }
    
    private function parse_csv_file($file_path) {
        $recipients = array();
        
        if (($handle = fopen($file_path, "r")) !== FALSE) {
            $header = fgetcsv($handle, 1000, ",");
            if (!$header) {
                fclose($handle);
                return $recipients;
            }
            
            // Debug logging
            error_log('Alumni Bulk Email - CSV Header: ' . print_r($header, true));
            
            // Clean up header names - just store all columns as-is
            $cleaned_headers = array();
            
            foreach ($header as $index => $column) {
                $cleaned_header = trim($column);
                $cleaned_headers[$index] = $cleaned_header;
            }
            
            error_log('Alumni Bulk Email - CSV columns detected: ' . print_r($cleaned_headers, true));
            
            $row_count = 0;
            $valid_recipients = 0;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $row_count++;
                
                // Debug log each row
                error_log('Alumni Bulk Email - Row ' . $row_count . ': ' . print_r($data, true));
                
                // Allow rows with fewer columns - they might just have empty trailing fields
                if (count($data) == 0 || (count($data) == 1 && trim($data[0]) == '')) {
                    // Skip completely empty rows
                    error_log('Alumni Bulk Email - Skipping empty row ' . $row_count);
                    continue;
                }
                
                // Store all rows regardless of email validation
                $valid_recipients++;
                $recipient = array();
                
                // Store all columns dynamically
                foreach ($cleaned_headers as $index => $column_name) {
                    $value = isset($data[$index]) ? trim($data[$index]) : '';
                    $recipient[$column_name] = $value;
                }
                
                // Add tags column (initially empty for all recipients)
                $recipient['tags'] = '';
                
                // Generate standard fields for backward compatibility (only if we can extract them)
                $recipient['name'] = $this->extract_name_field($recipient);
                $recipient['first_name'] = $this->extract_first_name($recipient);
                $recipient['last_name'] = $this->extract_last_name($recipient);
                
                $recipients[] = $recipient;
            }
            fclose($handle);
            
            error_log('Alumni Bulk Email - CSV parsing complete. Total rows: ' . $row_count . ', Valid recipients: ' . $valid_recipients);
        }
        
        return $recipients;
    }
    
    private function add_campaign_tags_to_list($list_id, $campaign_name, $date) {
        global $wpdb;
        
        $list = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}alumni_recipient_lists WHERE id = %d",
            $list_id
        ));
        
        if (!$list || !$list->recipients_data) {
            error_log('Alumni Bulk Email - Failed to load list for campaign tagging: ' . $list_id);
            return false;
        }
        
        $recipients = json_decode($list->recipients_data, true);
        if (empty($recipients)) {
            error_log('Alumni Bulk Email - No recipients data for campaign tagging: ' . $list_id);
            return false;
        }
        
        // Create campaign tag
        $campaign_tag = $campaign_name . ' (' . $date . ')';
        
        // Add tag to all recipients
        foreach ($recipients as &$recipient) {
            $current_tags = isset($recipient['tags']) ? $recipient['tags'] : '';
            
            if ($current_tags) {
                // Split existing tags, add new tag, and deduplicate
                $tag_array = array_map('trim', explode(',', $current_tags));
                $tag_array[] = $campaign_tag;
                $unique_tags = array_unique($tag_array);
                $recipient['tags'] = implode(', ', $unique_tags);
            } else {
                $recipient['tags'] = $campaign_tag;
            }
        }
        
        // Save back to database
        $result = $wpdb->update(
            $wpdb->prefix . 'alumni_recipient_lists',
            array(
                'recipients_data' => json_encode($recipients),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $list_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            error_log('Alumni Bulk Email - Failed to save campaign tags for list: ' . $list_id . ' - ' . $wpdb->last_error);
            return false;
        }
        
        error_log('Alumni Bulk Email - Successfully added campaign tag "' . $campaign_tag . '" to list ' . $list_id . ' (' . count($recipients) . ' recipients)');
        return true;
    }
    
    private function merge_and_deduplicate_recipients($existing_recipients, $new_recipients) {
        $merged_recipients = $existing_recipients;
        $new_recipients_added = array();
        $duplicate_count = 0;
        $added_count = 0;
        
        // Create a lookup array of existing emails for quick duplicate checking
        $existing_emails = array();
        foreach ($existing_recipients as $recipient) {
            // Try multiple email field variations
            $email = $this->extract_email_from_recipient($recipient);
            if ($email) {
                $existing_emails[strtolower($email)] = true;
            }
        }
        
        // Process new recipients
        foreach ($new_recipients as $new_recipient) {
            $email = $this->extract_email_from_recipient($new_recipient);
            
            if (!$email) {
                continue; // Skip recipients without email
            }
            
            $email_key = strtolower($email);
            
            if (isset($existing_emails[$email_key])) {
                // Duplicate found
                $duplicate_count++;
            } else {
                // New recipient - add to merged list
                $merged_recipients[] = $new_recipient;
                $new_recipients_added[] = $new_recipient;
                $existing_emails[$email_key] = true;
                $added_count++;
            }
        }
        
        return array(
            'merged_recipients' => $merged_recipients,
            'new_recipients' => $new_recipients_added,
            'added_count' => $added_count,
            'duplicate_count' => $duplicate_count
        );
    }
    
    private function extract_email_from_recipient($recipient) {
        // Try multiple possible email field names
        $email_fields = array('email', 'email_address', 'emailaddress', 'e_mail', 'e-mail', 'Email', 'Email_Address');
        
        foreach ($email_fields as $field) {
            if (isset($recipient[$field]) && !empty($recipient[$field])) {
                $email = trim($recipient[$field]);
                if (is_email($email)) {
                    return $email;
                }
            }
        }
        
        return null;
    }
    
    private function extract_name_field($recipient) {
        // Priority order for name field
        $name_fields = array('name', 'full_name', 'fullname', 'Name', 'Full Name');
        
        foreach ($name_fields as $field) {
            if (!empty($recipient[$field])) {
                return $recipient[$field];
            }
        }
        
        // Fallback: combine first and last name
        $first = $this->extract_first_name($recipient);
        $last = $this->extract_last_name($recipient);
        return trim($first . ' ' . $last);
    }
    
    private function extract_first_name($recipient) {
        $first_name_fields = array('first_name', 'firstname', 'fname', 'First Name', 'FirstName');
        
        foreach ($first_name_fields as $field) {
            if (!empty($recipient[$field])) {
                return $recipient[$field];
            }
        }
        
        return '';
    }
    
    private function extract_last_name($recipient) {
        $last_name_fields = array('last_name', 'lastname', 'lname', 'Last Name', 'LastName');
        
        foreach ($last_name_fields as $field) {
            if (!empty($recipient[$field])) {
                return $recipient[$field];
            }
        }
        
        return '';
    }
    
    private function get_list_columns($list_id) {
        global $wpdb;
        
        $list = $wpdb->get_row($wpdb->prepare(
            "SELECT recipients_data FROM {$wpdb->prefix}alumni_recipient_lists WHERE id = %d",
            $list_id
        ));
        
        if (!$list || !$list->recipients_data) {
            return array();
        }
        
        $recipients = json_decode($list->recipients_data, true);
        if (empty($recipients)) {
            return array();
        }
        
        // Get all unique column names from the first recipient
        $sample_recipient = $recipients[0];
        $columns = array();
        
        foreach ($sample_recipient as $column_name => $value) {
            // Skip internal fields
            if (!in_array($column_name, array('email', 'name', 'first_name', 'last_name'))) {
                $columns[] = $column_name;
            }
        }
        
        return $columns;
    }
    
    private function get_all_available_columns() {
        global $wpdb;
        
        $all_lists = $wpdb->get_results("SELECT recipients_data FROM {$wpdb->prefix}alumni_recipient_lists");
        $all_columns = array();
        
        foreach ($all_lists as $list) {
            if ($list->recipients_data) {
                $recipients = json_decode($list->recipients_data, true);
                if (!empty($recipients)) {
                    $sample_recipient = $recipients[0];
                    foreach ($sample_recipient as $column_name => $value) {
                        if (!in_array($column_name, array('email', 'name', 'first_name', 'last_name'))) {
                            $all_columns[$column_name] = true;
                        }
                    }
                }
            }
        }
        
        return array_keys($all_columns);
    }
    
    private function create_campaign_record($campaign_name, $subject, $html_content, $recipients_count, $recipients_data = null) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'alumni_email_campaigns',
            array(
                'campaign_name' => $campaign_name,
                'subject' => $subject,
                'html_content' => $html_content,
                'recipients_data' => $recipients_data ? json_encode($recipients_data) : null,
                'recipients_count' => $recipients_count,
                'status' => 'sending',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    private function log_email_attempt($campaign_id, $recipient, $status, $result) {
        global $wpdb;
        
        $mailgun_message_id = '';
        if ($status === 'sent' && isset($result['response'])) {
            $response_data = json_decode($result['response'], true);
            if (isset($response_data['id'])) {
                $mailgun_message_id = $response_data['id'];
            }
        }
        
        $wpdb->insert(
            $wpdb->prefix . 'alumni_email_logs',
            array(
                'campaign_id' => $campaign_id,
                'recipient_email' => $recipient['email'],
                'recipient_name' => $recipient['name'],
                'status' => $status,
                'sent_at' => current_time('mysql'),
                'bounce_reason' => $status === 'failed' ? $result['error'] : null,
                'mailgun_message_id' => $mailgun_message_id
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    private function personalize_content($content, $recipient) {
        $replacements = array(
            '{email}' => $recipient['email'],
            '{name}' => $recipient['name'] ?: $recipient['email'],
            '{first_name}' => $recipient['first_name'] ?: '',
            '{last_name}' => $recipient['last_name'] ?: ''
        );
        
        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }
    
    public function handle_save_campaign() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'save_campaign') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        $campaign_name = sanitize_text_field($_POST['campaign_name']);
        $subject = sanitize_text_field($_POST['subject']);
        $html_content = wp_kses_post($_POST['html_content']);
        $campaign_id = intval($_POST['campaign_id']);
        
        if (empty($campaign_name) || empty($subject) || empty($html_content)) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Campaign name, subject, and content are required')));
            exit;
        }
        
        global $wpdb;
        
        // Check if table exists first
        $table_name = $wpdb->prefix . 'alumni_email_campaigns';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if (!$table_exists) {
            // Create tables if they don't exist
            $this->create_tables();
        }
        
        if ($campaign_id > 0) {
            // Update existing campaign
            $result = $wpdb->update(
                $wpdb->prefix . 'alumni_email_campaigns',
                array(
                    'campaign_name' => $campaign_name,
                    'subject' => $subject,
                    'html_content' => $html_content,
                    'status' => 'draft'
                ),
                array('id' => $campaign_id, 'status' => 'draft'),
                array('%s', '%s', '%s', '%s'),
                array('%d', '%s')
            );
            
            if ($result === false) {
                $error_msg = $wpdb->last_error ? $wpdb->last_error : 'Failed to update campaign';
                echo json_encode(array('success' => false, 'data' => array('message' => $error_msg)));
                exit;
            }
            
            $saved_campaign_id = $campaign_id;
        } else {
            // Create new campaign
            $result = $wpdb->insert(
                $wpdb->prefix . 'alumni_email_campaigns',
                array(
                    'campaign_name' => $campaign_name,
                    'subject' => $subject,
                    'html_content' => $html_content,
                    'recipients_count' => 0,
                    'status' => 'draft',
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%d', '%s', '%s')
            );
            
            if (!$result) {
                $error_msg = $wpdb->last_error ? $wpdb->last_error : 'Failed to save campaign';
                echo json_encode(array('success' => false, 'data' => array('message' => $error_msg)));
                exit;
            }
            
            $saved_campaign_id = $wpdb->insert_id;
        }
        
        echo json_encode(array(
            'success' => true,
            'data' => array(
                'message' => 'Campaign saved successfully',
                'campaign_id' => $saved_campaign_id
            )
        ));
        exit;
    }
    
    public function handle_load_campaign() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'load_campaign') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        
        if (!$campaign_id) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Invalid campaign ID')));
            exit;
        }
        
        global $wpdb;
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}alumni_email_campaigns WHERE id = %d",
            $campaign_id
        ));
        
        if (!$campaign) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Campaign not found')));
            exit;
        }
        
        echo json_encode(array(
            'success' => true,
            'data' => array(
                'campaign' => $campaign
            )
        ));
        exit;
    }
    
    public function handle_view_campaign() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'view_campaign') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        
        if (!$campaign_id) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Invalid campaign ID')));
            exit;
        }
        
        global $wpdb;
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}alumni_email_campaigns WHERE id = %d",
            $campaign_id
        ));
        
        if (!$campaign) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Campaign not found')));
            exit;
        }
        
        echo json_encode(array(
            'success' => true,
            'data' => array(
                'campaign' => $campaign
            )
        ));
        exit;
    }
    
    public function handle_delete_campaign() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'delete_campaign') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        
        if (!$campaign_id) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Invalid campaign ID')));
            exit;
        }
        
        global $wpdb;
        $result = $wpdb->delete(
            $wpdb->prefix . 'alumni_email_campaigns',
            array('id' => $campaign_id, 'status' => 'draft'),
            array('%d', '%s')
        );
        
        if ($result === false) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Failed to delete campaign')));
            exit;
        }
        
        echo json_encode(array(
            'success' => true,
            'data' => array('message' => 'Campaign deleted successfully')
        ));
        exit;
    }
    
    public function handle_copy_campaign() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'copy_campaign') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        
        if (!$campaign_id) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Invalid campaign ID')));
            exit;
        }
        
        global $wpdb;
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}alumni_email_campaigns WHERE id = %d",
            $campaign_id
        ));
        
        if (!$campaign) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Campaign not found')));
            exit;
        }
        
        // Decode recipients data if available
        $recipients = array();
        if ($campaign->recipients_data) {
            $recipients = json_decode($campaign->recipients_data, true);
            if (!$recipients) {
                $recipients = array();
            }
        }
        
        echo json_encode(array(
            'success' => true,
            'data' => array(
                'campaign' => array(
                    'campaign_name' => $campaign->campaign_name,
                    'subject' => $campaign->subject,
                    'html_content' => $campaign->html_content
                ),
                'recipients' => $recipients
            )
        ));
        exit;
    }
    
    public function handle_load_recipient_list() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'load_recipient_list') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        $list_id = intval($_POST['list_id']);
        
        if (!$list_id) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Invalid list ID')));
            exit;
        }
        
        global $wpdb;
        $list = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}alumni_recipient_lists WHERE id = %d",
            $list_id
        ));
        
        if (!$list) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'List not found')));
            exit;
        }
        
        $recipients = json_decode($list->recipients_data, true);
        if (!$recipients) {
            $recipients = array();
        }
        
        echo json_encode(array(
            'success' => true,
            'data' => array(
                'recipients' => $recipients,
                'list_name' => $list->list_name
            )
        ));
        exit;
    }
    
    public function handle_upload_save_csv() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'upload_save_csv') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'No file uploaded or upload error')));
            exit;
        }
        
        $list_name = sanitize_text_field($_POST['list_name']);
        if (empty($list_name)) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'List name is required')));
            exit;
        }
        
        // Parse CSV
        $recipients = $this->parse_csv_file($_FILES['csv_file']['tmp_name']);
        
        if (empty($recipients)) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'No valid recipients found in CSV')));
            exit;
        }
        
        // Save to database
        global $wpdb;
        $result = $wpdb->insert(
            $wpdb->prefix . 'alumni_recipient_lists',
            array(
                'list_name' => $list_name,
                'recipients_data' => json_encode($recipients),
                'total_count' => count($recipients)
            ),
            array('%s', '%s', '%d')
        );
        
        if ($result === false) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Failed to save list')));
            exit;
        }
        
        echo json_encode(array(
            'success' => true,
            'data' => array(
                'recipients' => $recipients,
                'list_id' => $wpdb->insert_id,
                'list_name' => $list_name
            )
        ));
        exit;
    }
    
    public function handle_create_recipient_list() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'create_recipient_list') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        $list_name = sanitize_text_field($_POST['list_name']);
        $method = sanitize_text_field($_POST['method']);
        
        if (empty($list_name)) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'List name is required')));
            exit;
        }
        
        $recipients = array();
        
        if ($method === 'upload') {
            // Handle CSV upload
            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(array('success' => false, 'data' => array('message' => 'No file uploaded or upload error')));
                exit;
            }
            
            $recipients = $this->parse_csv_file($_FILES['csv_file']['tmp_name']);
        } else {
            // Handle manual entry
            $manual_data = sanitize_textarea_field($_POST['manual_data']);
            if (empty($manual_data)) {
                echo json_encode(array('success' => false, 'data' => array('message' => 'Manual data is required')));
                exit;
            }
            
            $recipients = $this->parse_manual_data($manual_data);
        }
        
        if (empty($recipients)) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'No valid recipients found')));
            exit;
        }
        
        // Save to database
        global $wpdb;
        
        // Verify table exists
        $table_name = $wpdb->prefix . 'alumni_recipient_lists';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        if (!$table_exists) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Database table does not exist. Please deactivate and reactivate the plugin.')));
            exit;
        }
        
        // Check JSON size
        $json_data = json_encode($recipients);
        if ($json_data === false) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Failed to encode recipient data as JSON')));
            exit;
        }
        
        $json_size_mb = strlen($json_data) / (1024 * 1024);
        if ($json_size_mb > 16) { // MySQL longtext limit is ~16MB
            echo json_encode(array('success' => false, 'data' => array('message' => 'Recipient data too large (' . number_format($json_size_mb, 2) . 'MB). Please reduce list size.')));
            exit;
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'alumni_recipient_lists',
            array(
                'list_name' => $list_name,
                'recipients_data' => $json_data,
                'total_count' => count($recipients)
            ),
            array('%s', '%s', '%d')
        );
        
        if ($result === false) {
            // Log the error for debugging
            error_log('Alumni Bulk Email - Failed to create list: ' . $wpdb->last_error);
            echo json_encode(array('success' => false, 'data' => array('message' => 'Failed to save list: ' . $wpdb->last_error)));
            exit;
        }
        
        echo json_encode(array(
            'success' => true,
            'data' => array(
                'list_id' => $wpdb->insert_id,
                'list_name' => $list_name,
                'total_count' => count($recipients)
            )
        ));
        exit;
    }
    
    private function parse_manual_data($data) {
        $recipients = array();
        $lines = array_filter(array_map('trim', explode("\n", $data)));
        
        foreach ($lines as $line) {
            if (empty($line)) continue;
            
            // Check if line contains comma (email,name format)
            if (strpos($line, ',') !== false) {
                $parts = array_map('trim', explode(',', $line, 2));
                $email = sanitize_email($parts[0]);
                $name = isset($parts[1]) ? sanitize_text_field($parts[1]) : '';
                
                if (is_email($email)) {
                    $recipients[] = array(
                        'email' => $email,
                        'name' => $name,
                        'first_name' => $name ? explode(' ', $name)[0] : '',
                        'last_name' => $name && strpos($name, ' ') ? substr($name, strpos($name, ' ') + 1) : '',
                        'tags' => ''
                    );
                }
            } else {
                // Just email format
                $email = sanitize_email(trim($line));
                if (is_email($email)) {
                    $recipients[] = array(
                        'email' => $email,
                        'name' => '',
                        'first_name' => '',
                        'last_name' => '',
                        'tags' => ''
                    );
                }
            }
        }
        
        return $recipients;
    }
    
    public function handle_combine_recipient_lists() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'combine_recipient_lists') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        $list_ids = array_map('intval', $_POST['list_ids']);
        $new_list_name = sanitize_text_field($_POST['new_list_name']);
        
        if (empty($list_ids) || empty($new_list_name)) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Missing required data')));
            exit;
        }
        
        global $wpdb;
        
        // Get all recipients from the specified lists
        $all_recipients = array();
        $email_tracker = array(); // To track duplicates
        
        foreach ($list_ids as $list_id) {
            $list = $wpdb->get_row($wpdb->prepare(
                "SELECT recipients_data FROM {$wpdb->prefix}alumni_recipient_lists WHERE id = %d",
                $list_id
            ));
            
            if ($list && $list->recipients_data) {
                $recipients = json_decode($list->recipients_data, true);
                if (is_array($recipients)) {
                    foreach ($recipients as $recipient) {
                        $email = strtolower($recipient['email']);
                        // Only add if not already added (remove duplicates)
                        if (!isset($email_tracker[$email])) {
                            $all_recipients[] = $recipient;
                            $email_tracker[$email] = true;
                        }
                    }
                }
            }
        }
        
        if (empty($all_recipients)) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'No recipients found in selected lists')));
            exit;
        }
        
        // Save combined list
        $result = $wpdb->insert(
            $wpdb->prefix . 'alumni_recipient_lists',
            array(
                'list_name' => $new_list_name,
                'description' => 'Combined from ' . count($list_ids) . ' lists',
                'recipients_data' => json_encode($all_recipients),
                'total_count' => count($all_recipients)
            ),
            array('%s', '%s', '%s', '%d')
        );
        
        if ($result === false) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Failed to save combined list')));
            exit;
        }
        
        echo json_encode(array(
            'success' => true,
            'data' => array(
                'list_id' => $wpdb->insert_id,
                'list_name' => $new_list_name,
                'total_recipients' => count($all_recipients)
            )
        ));
        exit;
    }
    
    public function handle_view_recipient_list() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'view_recipient_list') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        $list_id = intval($_POST['list_id']);
        
        if (!$list_id) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Invalid list ID')));
            exit;
        }
        
        global $wpdb;
        $list = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}alumni_recipient_lists WHERE id = %d",
            $list_id
        ));
        
        if (!$list) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'List not found')));
            exit;
        }
        
        $recipients = json_decode($list->recipients_data, true);
        if (!$recipients) {
            $recipients = array();
        }
        
        // Get all column names from the first recipient
        $columns = array();
        if (!empty($recipients)) {
            $columns = array_keys($recipients[0]);
        }
        
        echo json_encode(array(
            'success' => true,
            'data' => array(
                'list' => array(
                    'id' => $list->id,
                    'list_name' => $list->list_name,
                    'total_count' => $list->total_count,
                    'created_at' => $list->created_at,
                    'updated_at' => $list->updated_at
                ),
                'recipients' => $recipients,
                'columns' => $columns
            )
        ));
        exit;
    }
    
    public function handle_edit_recipient_row() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'edit_recipient_row') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        $list_id = intval($_POST['list_id']);
        $row_index = intval($_POST['row_index']);
        $updated_data = $_POST['updated_data']; // Array of field => value pairs
        
        if (!$list_id || $row_index < 0) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Invalid list ID or row index')));
            exit;
        }
        
        global $wpdb;
        $list = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}alumni_recipient_lists WHERE id = %d",
            $list_id
        ));
        
        if (!$list) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'List not found')));
            exit;
        }
        
        $recipients = json_decode($list->recipients_data, true);
        if (!$recipients || !isset($recipients[$row_index])) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Recipient not found')));
            exit;
        }
        
        // Sanitize and validate the updated data
        $sanitized_data = array();
        foreach ($updated_data as $field => $value) {
            $sanitized_field = sanitize_text_field($field);
            $sanitized_value = sanitize_text_field($value);
            
            // Special validation for email field
            if (strtolower($sanitized_field) === 'email') {
                if (!is_email($sanitized_value)) {
                    echo json_encode(array('success' => false, 'data' => array('message' => 'Invalid email address')));
                    exit;
                }
            }
            
            $sanitized_data[$sanitized_field] = $sanitized_value;
        }
        
        // Update the recipient data
        foreach ($sanitized_data as $field => $value) {
            $recipients[$row_index][$field] = $value;
        }
        
        // Regenerate standard fields if they were affected
        $recipients[$row_index]['name'] = $this->extract_name_field($recipients[$row_index]);
        $recipients[$row_index]['first_name'] = $this->extract_first_name($recipients[$row_index]);
        $recipients[$row_index]['last_name'] = $this->extract_last_name($recipients[$row_index]);
        
        // Save back to database
        $result = $wpdb->update(
            $wpdb->prefix . 'alumni_recipient_lists',
            array(
                'recipients_data' => json_encode($recipients),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $list_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Failed to update recipient: ' . $wpdb->last_error)));
            exit;
        }
        
        echo json_encode(array(
            'success' => true,
            'data' => array(
                'message' => 'Recipient updated successfully',
                'updated_recipient' => $recipients[$row_index]
            )
        ));
        exit;
    }
    
    public function handle_bulk_edit_recipients() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'bulk_edit_recipients') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        $list_id = intval($_POST['list_id']);
        $bulk_updates = $_POST['bulk_updates']; // Array of row_index => {field: value}
        
        if (!$list_id) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Invalid list ID')));
            exit;
        }
        
        global $wpdb;
        $list = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}alumni_recipient_lists WHERE id = %d",
            $list_id
        ));
        
        if (!$list) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'List not found')));
            exit;
        }
        
        $recipients = json_decode($list->recipients_data, true);
        if (!$recipients) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Invalid recipients data')));
            exit;
        }
        
        // Apply bulk updates
        foreach ($bulk_updates as $row_index => $updates) {
            $row_index = intval($row_index);
            if (isset($recipients[$row_index])) {
                foreach ($updates as $field => $value) {
                    $sanitized_field = sanitize_text_field($field);
                    $sanitized_value = sanitize_text_field($value);
                    $recipients[$row_index][$sanitized_field] = $sanitized_value;
                }
            }
        }
        
        // Save back to database
        $result = $wpdb->update(
            $wpdb->prefix . 'alumni_recipient_lists',
            array(
                'recipients_data' => json_encode($recipients),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $list_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Failed to update recipients: ' . $wpdb->last_error)));
            exit;
        }
        
        echo json_encode(array(
            'success' => true,
            'data' => array('message' => 'Recipients updated successfully')
        ));
        exit;
    }
    
    public function handle_bulk_delete_recipients() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'bulk_delete_recipients') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        $list_id = intval($_POST['list_id']);
        $recipients_data = $_POST['recipients_data']; // JSON string of updated recipients array
        
        if (!$list_id) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Invalid list ID')));
            exit;
        }
        
        $recipients = json_decode($recipients_data, true);
        if (!is_array($recipients)) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Invalid recipients data')));
            exit;
        }
        
        global $wpdb;
        
        // Save the updated recipients data
        $result = $wpdb->update(
            $wpdb->prefix . 'alumni_recipient_lists',
            array(
                'recipients_data' => json_encode($recipients),
                'total_count' => count($recipients),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $list_id),
            array('%s', '%d', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Failed to delete recipients: ' . $wpdb->last_error)));
            exit;
        }
        
        echo json_encode(array(
            'success' => true,
            'data' => array('message' => 'Recipients deleted successfully', 'new_count' => count($recipients))
        ));
        exit;
    }
    
    public function handle_get_list_columns() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'get_list_columns') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        $list_id = intval($_POST['list_id']);
        
        if (!$list_id) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Invalid list ID')));
            exit;
        }
        
        global $wpdb;
        $list = $wpdb->get_row($wpdb->prepare(
            "SELECT recipients_data FROM {$wpdb->prefix}alumni_recipient_lists WHERE id = %d",
            $list_id
        ));
        
        if (!$list || !$list->recipients_data) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'List not found or empty')));
            exit;
        }
        
        $recipients = json_decode($list->recipients_data, true);
        if (empty($recipients)) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'No recipients data found')));
            exit;
        }
        
        // Get all column names from the first recipient
        $columns = array_keys($recipients[0]);
        
        echo json_encode(array(
            'success' => true,
            'data' => array('columns' => $columns)
        ));
        exit;
    }
    
    public function handle_merge_csv_to_list() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'merge_csv_to_list') || !current_user_can('edit_posts')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        $list_id = intval($_POST['list_id']);
        $preview_only = $_POST['preview_only'] === '1';
        
        if (!$list_id) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Invalid list ID')));
            exit;
        }
        
        // Check if CSV file was uploaded
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'CSV file required')));
            exit;
        }
        
        global $wpdb;
        $existing_list = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}alumni_recipient_lists WHERE id = %d",
            $list_id
        ));
        
        if (!$existing_list) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Target list not found')));
            exit;
        }
        
        // Parse the new CSV
        $new_recipients = $this->parse_csv_file($_FILES['csv_file']['tmp_name']);
        if (empty($new_recipients)) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'No valid recipients found in CSV')));
            exit;
        }
        
        // Parse existing recipients
        $existing_recipients = json_decode($existing_list->recipients_data, true);
        if (!$existing_recipients) {
            $existing_recipients = array();
        }
        
        // Deduplicate and merge
        $merge_result = $this->merge_and_deduplicate_recipients($existing_recipients, $new_recipients);
        
        if ($preview_only) {
            // Return preview data
            echo json_encode(array(
                'success' => true,
                'data' => array(
                    'preview' => true,
                    'new_count' => $merge_result['added_count'],
                    'duplicate_count' => $merge_result['duplicate_count'],
                    'preview_data' => array_slice($merge_result['new_recipients'], 0, 10)
                )
            ));
            exit;
        }
        
        // Save the merged list
        $total_recipients = count($merge_result['merged_recipients']);
        $result = $wpdb->update(
            $wpdb->prefix . 'alumni_recipient_lists',
            array(
                'recipients_data' => json_encode($merge_result['merged_recipients']),
                'total_count' => $total_recipients,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $list_id),
            array('%s', '%d', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Failed to save merged list: ' . $wpdb->last_error)));
            exit;
        }
        
        echo json_encode(array(
            'success' => true,
            'data' => array(
                'message' => 'Lists merged successfully',
                'added_count' => $merge_result['added_count'],
                'duplicate_count' => $merge_result['duplicate_count'],
                'total_count' => $total_recipients
            )
        ));
        exit;
    }
    
    public function handle_export_recipient_list() {
        if (!wp_verify_nonce($_POST['nonce'], 'export_recipient_list') || !current_user_can('edit_posts')) {
            wp_die('Unauthorized', 'Error', array('response' => 403));
        }
        
        $list_id = intval($_POST['list_id']);
        $exclude_bounced = $_POST['exclude_bounced'] === '1';
        $exclude_unsubscribed = $_POST['exclude_unsubscribed'] === '1';
        
        if (!$list_id) {
            wp_die('Invalid list ID', 'Error', array('response' => 400));
        }
        
        global $wpdb;
        $list = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}alumni_recipient_lists WHERE id = %d",
            $list_id
        ));
        
        if (!$list || !$list->recipients_data) {
            wp_die('List not found', 'Error', array('response' => 404));
        }
        
        $recipients = json_decode($list->recipients_data, true);
        if (empty($recipients)) {
            wp_die('No recipients found in list', 'Error', array('response' => 404));
        }
        
        // Filter recipients based on bounced/unsubscribed status
        $filtered_recipients = $this->filter_recipients_for_export($recipients, $exclude_bounced, $exclude_unsubscribed);
        
        if (empty($filtered_recipients)) {
            wp_die('No recipients remain after applying filters', 'Error', array('response' => 404));
        }
        
        // Generate CSV content
        $csv_content = $this->generate_csv_content($filtered_recipients);
        
        // Prepare filename
        $safe_list_name = sanitize_file_name($list->list_name);
        $timestamp = current_time('Y-m-d_H-i-s');
        $filename = $safe_list_name . '_export_' . $timestamp . '.csv';
        
        // Set headers for download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Output CSV content
        echo $csv_content;
        exit;
    }
    
    private function filter_recipients_for_export($recipients, $exclude_bounced, $exclude_unsubscribed) {
        $filtered = array();
        
        foreach ($recipients as $recipient) {
            $email = $this->extract_email_from_recipient($recipient);
            
            if (!$email) {
                continue; // Skip recipients without email
            }
            
            // Check if should exclude bounced emails
            if ($exclude_bounced && $this->is_email_bounced($email)) {
                continue;
            }
            
            // Check if should exclude unsubscribed emails  
            if ($exclude_unsubscribed && $this->is_email_unsubscribed($email)) {
                continue;
            }
            
            $filtered[] = $recipient;
        }
        
        return $filtered;
    }
    
    private function generate_csv_content($recipients) {
        if (empty($recipients)) {
            return '';
        }
        
        // Get all column headers from the first recipient
        $headers = array_keys($recipients[0]);
        
        $output = fopen('php://temp', 'w');
        
        // Write headers
        fputcsv($output, $headers);
        
        // Write data rows
        foreach ($recipients as $recipient) {
            $row = array();
            foreach ($headers as $header) {
                $row[] = isset($recipient[$header]) ? $recipient[$header] : '';
            }
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);
        
        return $csv_content;
    }
    
    public function handle_recreate_tables() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'recreate_tables') || !current_user_can('manage_options')) {
            echo json_encode(array('success' => false, 'data' => array('message' => 'Unauthorized')));
            exit;
        }
        
        try {
            $this->create_tables();
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
    
    private function generate_unsubscribe_token($email) {
        // Generate a consistent token for the same email
        return hash('sha256', $email . wp_salt() . get_option('alumni_from_email', ''));
    }
    
    private function add_unsubscribe_links($html_content, $email) {
        $unsubscribe_token = $this->generate_unsubscribe_token($email);
        
        // Store unsubscribe token if not exists  
        global $wpdb;
        
        // Check if token already exists for this email
        $existing_token = $wpdb->get_var($wpdb->prepare(
            "SELECT unsubscribe_token FROM {$wpdb->prefix}alumni_email_unsubscribes WHERE email = %s",
            $email
        ));
        
        if ($existing_token && !empty($existing_token)) {
            // Use existing token
            $unsubscribe_token = $existing_token;
        } else {
            // Create new token
            $wpdb->replace(
                $wpdb->prefix . 'alumni_email_unsubscribes',
                array(
                    'email' => $email,
                    'unsubscribe_token' => $unsubscribe_token,
                    'unsubscribed_at' => null
                ),
                array('%s', '%s', '%s')
            );
        }
        
        $unsubscribe_url = add_query_arg(array(
            'action' => 'alumni_unsubscribe',
            'token' => $unsubscribe_token
        ), home_url());
        
        // Add unsubscribe link to email content
        $unsubscribe_footer = '<div style="margin-top: 40px; padding: 20px; background: #f9f9f9; border-top: 1px solid #ddd; text-align: center; font-size: 12px; color: #666;">
            <p>You received this email because you are subscribed to our alumni mailing list.</p>
            <p><a href="' . esc_url($unsubscribe_url) . '" style="color: #666; text-decoration: underline;">Unsubscribe from future emails</a></p>
        </div>';
        
        return $html_content . $unsubscribe_footer;
    }
    
    private function is_email_unsubscribed($email) {
        global $wpdb;
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}alumni_email_unsubscribes WHERE email = %s AND unsubscribed_at IS NOT NULL",
            $email
        ));
        return $result > 0;
    }
    
    private function is_email_bounced($email) {
        global $wpdb;
        // Count recent bounces (last 30 days)
        $bounce_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}alumni_email_logs 
             WHERE recipient_email = %s 
             AND status IN ('failed', 'bounced') 
             AND bounce_reason IS NOT NULL 
             AND sent_at > DATE_SUB(NOW(), INTERVAL 30 DAY)",
            $email
        ));
        
        // Consider email bounced if 3+ bounces in last 30 days
        return $bounce_count >= 3;
    }
    
    public function handle_unsubscribe_page() {
        if (isset($_GET['action']) && $_GET['action'] === 'alumni_unsubscribe' && isset($_GET['token'])) {
            $this->show_unsubscribe_page($_GET['token']);
            exit;
        }
    }
    
    private function show_unsubscribe_page($token) {
        global $wpdb;
        
        // Ensure unsubscribe table exists
        $table_name = $wpdb->prefix . 'alumni_email_unsubscribes';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if (!$table_exists) {
            $this->create_tables();
        }
        
        $email_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}alumni_email_unsubscribes WHERE unsubscribe_token = %s",
            $token
        ));
        
        if (!$email_data) {
            wp_die(
                'Invalid unsubscribe link. This link may have expired or is malformed. Please contact us directly if you need to unsubscribe.',
                'Unsubscribe Error',
                array('response' => 400)
            );
        }
        
        $email = $email_data->email;
        $already_unsubscribed = !is_null($email_data->unsubscribed_at);
        
        if (isset($_POST['confirm_unsubscribe']) && $_POST['confirm_unsubscribe'] === 'yes') {
            // Process unsubscribe
            $wpdb->update(
                $wpdb->prefix . 'alumni_email_unsubscribes',
                array(
                    'unsubscribed_at' => current_time('mysql'),
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT']
                ),
                array('unsubscribe_token' => $token),
                array('%s', '%s', '%s'),
                array('%s')
            );
            $already_unsubscribed = true;
        }
        
        // Show unsubscribe page
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Unsubscribe - Alumni Email List</title>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <style>
                body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; line-height: 1.6; }
                .container { background: #f9f9f9; padding: 30px; border-radius: 8px; text-align: center; }
                .success { color: #4CAF50; }
                .button { background: #dc3232; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
                .button:hover { background: #a23232; }
            </style>
        </head>
        <body>
            <div class="container">
                <?php if ($already_unsubscribed): ?>
                    <h1 class="success">✅ Unsubscribed Successfully</h1>
                    <p>The email address <strong><?php echo esc_html($email); ?></strong> has been removed from our mailing list.</p>
                    <p>You will no longer receive emails from us.</p>
                <?php else: ?>
                    <h1>Unsubscribe from Alumni Emails</h1>
                    <p>Are you sure you want to unsubscribe <strong><?php echo esc_html($email); ?></strong> from our alumni mailing list?</p>
                    <form method="post">
                        <input type="hidden" name="confirm_unsubscribe" value="yes">
                        <button type="submit" class="button">Yes, Unsubscribe Me</button>
                    </form>
                    <p style="margin-top: 20px; font-size: 14px; color: #666;">
                        If you clicked this link by mistake, simply close this page.
                    </p>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
    }
    
    public function handle_unsubscribe() {
        // This handles AJAX unsubscribe if needed
        if (!isset($_POST['token'])) {
            wp_die('Invalid request', 'Error', array('response' => 400));
        }
        
        $token = sanitize_text_field($_POST['token']);
        global $wpdb;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'alumni_email_unsubscribes',
            array(
                'unsubscribed_at' => current_time('mysql'),
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT']
            ),
            array('unsubscribe_token' => $token),
            array('%s', '%s', '%s'),
            array('%s')
        );
        
        if ($result) {
            echo json_encode(array('success' => true, 'message' => 'Successfully unsubscribed'));
        } else {
            echo json_encode(array('success' => false, 'message' => 'Invalid unsubscribe token'));
        }
        exit;
    }
    
    public function handle_mailgun_webhook() {
        // Get the raw POST data
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (!$data) {
            http_response_code(400);
            exit('Invalid JSON');
        }
        
        // Verify webhook signature (optional but recommended)
        // For now, we'll process without verification
        
        $event_type = $data['event-data']['event'] ?? '';
        $recipient = $data['event-data']['recipient'] ?? '';
        $message_id = $data['event-data']['message']['headers']['message-id'] ?? '';
        
        global $wpdb;
        
        // Update email log based on event type
        switch ($event_type) {
            case 'delivered':
                $wpdb->update(
                    $wpdb->prefix . 'alumni_email_logs',
                    array('status' => 'delivered'),
                    array('mailgun_message_id' => $message_id),
                    array('%s'),
                    array('%s')
                );
                break;
                
            case 'failed':
            case 'bounced':
                $reason = $data['event-data']['delivery-status']['description'] ?? 'Unknown error';
                $wpdb->update(
                    $wpdb->prefix . 'alumni_email_logs',
                    array(
                        'status' => $event_type,
                        'bounce_reason' => $reason
                    ),
                    array('mailgun_message_id' => $message_id),
                    array('%s', '%s'),
                    array('%s')
                );
                break;
                
            case 'opened':
                // Update open count
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}alumni_email_logs SET open_count = open_count + 1 WHERE mailgun_message_id = %s",
                    $message_id
                ));
                break;
                
            case 'clicked':
                // Update click count
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}alumni_email_logs SET click_count = click_count + 1 WHERE mailgun_message_id = %s",
                    $message_id
                ));
                break;
        }
        
        http_response_code(200);
        exit('OK');
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
    
    public function recipient_lists_page() {
        global $wpdb;
        $saved_lists = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}alumni_recipient_lists ORDER BY updated_at DESC");
        $all_custom_columns = $this->get_all_available_columns();
        ?>
        <div class="wrap">
            <h1>📧 Recipients Lists</h1>
            
            <div class="notice notice-info">
                <p><strong>Manage your recipient lists:</strong> View, edit, and delete saved recipient lists used in campaigns.</p>
                <?php if (!empty($all_custom_columns)): ?>
                    <p><strong>Available custom columns across all lists:</strong> <?php echo implode(', ', array_map('esc_html', $all_custom_columns)); ?></p>
                <?php endif; ?>
            </div>
            
            <div style="margin: 20px 0;">
                <button type="button" id="create-new-list" class="button button-primary">
                    ➕ Create a List
                </button>
            </div>
            
            <?php if (empty($saved_lists)): ?>
                <div class="notice notice-warning">
                    <p>No saved recipient lists found. Create lists when uploading CSV files in campaigns.</p>
                </div>
            <?php else: ?>
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <p class="search-box">
                            <label class="screen-reader-text" for="list-search-input">Search Lists:</label>
                            <input type="search" id="list-search-input" name="s" value="" placeholder="Search lists...">
                            <input type="submit" id="search-submit" class="button" value="Search Lists">
                        </p>
                    </div>
                    <div class="alignright" id="filtered-actions" style="display: none;">
                        <button type="button" id="create-from-filtered" class="button button-secondary">
                            📋 Create List from Filtered Results
                        </button>
                        <span id="filtered-count" style="margin-left: 10px; color: #666;"></span>
                    </div>
                </div>
                
                <table class="wp-list-table widefat fixed striped" id="recipients-table">
                    <thead>
                        <tr>
                            <th scope="col" class="sortable" data-sort="list_name">
                                List Name <span class="sort-indicator"></span>
                            </th>
                            <th scope="col" class="sortable" data-sort="total_count">
                                Recipients Count <span class="sort-indicator"></span>
                            </th>
                            <th scope="col" class="sortable" data-sort="created_at">
                                Created <span class="sort-indicator"></span>
                            </th>
                            <th scope="col" class="sortable" data-sort="updated_at">
                                Last Updated <span class="sort-indicator"></span>
                            </th>
                            <th scope="col">Custom Columns</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($saved_lists as $list): 
                            $list_custom_columns = $this->get_list_columns($list->id);
                        ?>
                            <tr>
                                <td><strong><?php echo esc_html($list->list_name); ?></strong></td>
                                <td><?php echo number_format($list->total_count); ?> recipients</td>
                                <td><?php echo date('M j, Y g:i A', strtotime($list->created_at)); ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($list->updated_at)); ?></td>
                                <td>
                                    <?php if (!empty($list_custom_columns)): ?>
                                        <span style="font-size: 12px; color: #666;">
                                            <?php echo implode(', ', array_map('esc_html', array_slice($list_custom_columns, 0, 3))); ?>
                                            <?php if (count($list_custom_columns) > 3): ?>
                                                <span title="<?php echo esc_attr(implode(', ', $list_custom_columns)); ?>">
                                                    +<?php echo count($list_custom_columns) - 3; ?> more
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #999; font-style: italic;">Standard fields only</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="button view-list" data-list-id="<?php echo $list->id; ?>">View</button>
                                    <button class="button add-to-list" data-list-id="<?php echo $list->id; ?>" data-list-name="<?php echo esc_attr($list->list_name); ?>">Add to List</button>
                                    <button class="button export-list" data-list-id="<?php echo $list->id; ?>" data-list-name="<?php echo esc_attr($list->list_name); ?>">Export CSV</button>
                                    <button class="button edit-list-name" data-list-id="<?php echo $list->id; ?>" data-current-name="<?php echo esc_attr($list->list_name); ?>">Rename</button>
                                    <button class="button button-link-delete delete-list" data-list-id="<?php echo $list->id; ?>">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <style>
        .sortable {
            cursor: pointer;
            position: relative;
            padding-right: 20px !important;
        }
        .sortable:hover {
            background-color: #f0f0f0;
        }
        .sort-indicator {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0.3;
        }
        .sort-indicator::before {
            content: "↕";
        }
        .sortable.sort-asc .sort-indicator {
            opacity: 1;
        }
        .sortable.sort-asc .sort-indicator::before {
            content: "↑";
        }
        .sortable.sort-desc .sort-indicator {
            opacity: 1;
        }
        .sortable.sort-desc .sort-indicator::before {
            content: "↓";
        }
        #list-search-input {
            width: 200px;
        }
        .search-highlight {
            background-color: yellow;
            font-weight: bold;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Create new list modal
            $('#create-new-list').click(function() {
                showCreateListModal();
            });
            
            // Table sorting functionality
            $('.sortable').click(function() {
                var column = $(this).data('sort');
                var currentSort = $(this).hasClass('sort-asc') ? 'asc' : ($(this).hasClass('sort-desc') ? 'desc' : 'none');
                var newSort = currentSort === 'asc' ? 'desc' : 'asc';
                
                // Remove all sort classes
                $('.sortable').removeClass('sort-asc sort-desc');
                
                // Add new sort class
                $(this).addClass('sort-' + newSort);
                
                // Sort the table
                sortTable(column, newSort);
            });
            
            // Search functionality
            $('#list-search-input').on('input', function() {
                var searchTerm = $(this).val().toLowerCase();
                filterTable(searchTerm);
            });
            
            $('#search-submit').click(function(e) {
                e.preventDefault();
                var searchTerm = $('#list-search-input').val().toLowerCase();
                filterTable(searchTerm);
            });
            
            // Create list from filtered results
            $('#create-from-filtered').click(function() {
                var visibleRows = $('#recipients-table tbody tr:visible');
                if (visibleRows.length === 0) {
                    alert('No lists match the current filter.');
                    return;
                }
                
                // Get all recipients from visible lists
                var allRecipients = [];
                var listNames = [];
                
                visibleRows.each(function() {
                    listNames.push($(this).find('td:eq(0)').text().trim());
                });
                
                // Show modal to name the new combined list
                showCombineListsModal(listNames);
            });
            
            // View list with dynamic columns
            $('.view-list').click(function() {
                var listId = $(this).data('list-id');
                showListView(listId);
            });
            
            // Add to existing list
            $('.add-to-list').click(function() {
                var listId = $(this).data('list-id');
                var listName = $(this).data('list-name');
                showAddToListModal(listId, listName);
            });
            
            // Export list as CSV
            $('.export-list').click(function() {
                var listId = $(this).data('list-id');
                var listName = $(this).data('list-name');
                showExportModal(listId, listName);
            });
            
            // Rename list
            $('.edit-list-name').click(function() {
                var listId = $(this).data('list-id');
                var currentName = $(this).data('current-name');
                var newName = prompt('Enter new name for this list:', currentName);
                
                if (newName && newName !== currentName) {
                    // TODO: Implement rename functionality
                    alert('Rename functionality coming soon');
                }
            });
            
            // Delete list
            $('.delete-list').click(function() {
                var listId = $(this).data('list-id');
                if (confirm('Are you sure you want to delete this recipients list? This cannot be undone.')) {
                    // TODO: Implement delete functionality
                    alert('Delete functionality coming soon');
                }
            });
            
            // Show create list modal
            function showCreateListModal() {
                var modalHtml = '<div id="create-list-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000;">' +
                    '<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; max-width: 600px; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">' +
                    '<h2>➕ Create New Recipients List</h2>' +
                    
                    '<div style="margin-bottom: 20px;">' +
                    '<label><input type="radio" name="create_method" value="upload" checked style="margin-right: 8px;"> Upload CSV File</label><br>' +
                    '<label style="margin-top: 10px; display: inline-block;"><input type="radio" name="create_method" value="manual" style="margin-right: 8px;"> Manual Entry</label>' +
                    '</div>' +
                    
                    '<div style="margin-bottom: 20px;">' +
                    '<label for="new-list-name" style="font-weight: bold;">List Name:</label><br>' +
                    '<input type="text" id="new-list-name" placeholder="e.g., Alumni Newsletter Dec 2024" style="width: 100%; padding: 8px; margin-top: 5px;" />' +
                    '</div>' +
                    
                    '<div id="upload-section">' +
                    '<label for="csv-upload" style="font-weight: bold;">CSV File:</label><br>' +
                    '<input type="file" id="csv-upload" accept=".csv" style="margin-top: 5px;" />' +
                    '<p style="margin-top: 10px; font-size: 14px; color: #666;">CSV should have columns: <strong>email</strong> (required), name, first_name, last_name</p>' +
                    '</div>' +
                    
                    '<div id="manual-section" style="display: none;">' +
                    '<label for="manual-recipients" style="font-weight: bold;">Recipients:</label><br>' +
                    '<textarea id="manual-recipients" rows="8" placeholder="Enter recipients one per line in format:\nemail@example.com,John Doe\nemail2@example.com,Jane Smith\n\nOr just email addresses:\nemail@example.com\nemail2@example.com" style="width: 100%; padding: 8px; margin-top: 5px; font-family: monospace;"></textarea>' +
                    '<p style="margin-top: 10px; font-size: 14px; color: #666;">Format: <code>email@example.com,Full Name</code> or just <code>email@example.com</code></p>' +
                    '</div>' +
                    
                    '<div style="margin-top: 20px; text-align: right;">' +
                    '<button type="button" id="cancel-create-list" class="button" style="margin-right: 10px;">Cancel</button>' +
                    '<button type="button" id="save-new-list" class="button button-primary">Create List</button>' +
                    '</div>' +
                    '</div></div>';
                
                $('body').append(modalHtml);
                
                // Modal event handlers
                $('#create-list-modal input[name="create_method"]').change(function() {
                    if ($(this).val() === 'upload') {
                        $('#upload-section').show();
                        $('#manual-section').hide();
                    } else {
                        $('#upload-section').hide();
                        $('#manual-section').show();
                    }
                });
                
                $('#cancel-create-list').click(function() {
                    $('#create-list-modal').remove();
                });
                
                $('#save-new-list').click(function() {
                    saveNewList();
                });
            }
            
            // Save new list function
            function saveNewList() {
                var listName = $('#new-list-name').val().trim();
                var method = $('#create-list-modal input[name="create_method"]:checked').val();
                
                if (!listName) {
                    alert('Please enter a list name.');
                    return;
                }
                
                var formData = new FormData();
                formData.append('action', 'create_recipient_list');
                formData.append('nonce', '<?php echo wp_create_nonce('create_recipient_list'); ?>');
                formData.append('list_name', listName);
                formData.append('method', method);
                
                if (method === 'upload') {
                    var fileInput = $('#csv-upload')[0];
                    if (!fileInput.files[0]) {
                        alert('Please select a CSV file.');
                        return;
                    }
                    formData.append('csv_file', fileInput.files[0]);
                } else {
                    var manualData = $('#manual-recipients').val().trim();
                    if (!manualData) {
                        alert('Please enter recipient data.');
                        return;
                    }
                    formData.append('manual_data', manualData);
                }
                
                $('#save-new-list').prop('disabled', true).text('Creating...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            $('#create-list-modal').remove();
                            alert('List created successfully!');
                            location.reload(); // Refresh to show new list
                        } else {
                            alert('Error creating list: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred while creating the list.');
                    },
                    complete: function() {
                        $('#save-new-list').prop('disabled', false).text('Create List');
                    }
                });
            }
            
            // Sort table function
            function sortTable(column, direction) {
                var table = $('#recipients-table');
                var tbody = table.find('tbody');
                var rows = tbody.find('tr').toArray();
                
                rows.sort(function(a, b) {
                    var aVal, bVal;
                    
                    switch(column) {
                        case 'list_name':
                            aVal = $(a).find('td:eq(0)').text().toLowerCase();
                            bVal = $(b).find('td:eq(0)').text().toLowerCase();
                            break;
                        case 'total_count':
                            aVal = parseInt($(a).find('td:eq(1)').text().replace(/[^\d]/g, ''));
                            bVal = parseInt($(b).find('td:eq(1)').text().replace(/[^\d]/g, ''));
                            break;
                        case 'created_at':
                        case 'updated_at':
                            var colIndex = column === 'created_at' ? 2 : 3;
                            aVal = new Date($(a).find('td:eq(' + colIndex + ')').text());
                            bVal = new Date($(b).find('td:eq(' + colIndex + ')').text());
                            break;
                        default:
                            return 0;
                    }
                    
                    if (direction === 'asc') {
                        return aVal > bVal ? 1 : (aVal < bVal ? -1 : 0);
                    } else {
                        return aVal < bVal ? 1 : (aVal > bVal ? -1 : 0);
                    }
                });
                
                tbody.empty().append(rows);
            }
            
            // Filter table function
            function filterTable(searchTerm) {
                var table = $('#recipients-table tbody');
                var rows = table.find('tr');
                var visibleCount = 0;
                
                rows.each(function() {
                    var row = $(this);
                    var listName = row.find('td:eq(0)').text().toLowerCase();
                    var count = row.find('td:eq(1)').text().toLowerCase();
                    var created = row.find('td:eq(2)').text().toLowerCase();
                    var updated = row.find('td:eq(3)').text().toLowerCase();
                    
                    // Check if search term matches any column
                    var matches = listName.includes(searchTerm) || 
                                 count.includes(searchTerm) || 
                                 created.includes(searchTerm) || 
                                 updated.includes(searchTerm);
                    
                    if (matches || searchTerm === '') {
                        row.show();
                        visibleCount++;
                        // Highlight matching text
                        if (searchTerm !== '') {
                            highlightText(row, searchTerm);
                        } else {
                            removeHighlight(row);
                        }
                    } else {
                        row.hide();
                    }
                });
                
                // Show/hide filtered actions
                if (searchTerm !== '' && visibleCount > 1) {
                    $('#filtered-actions').show();
                    $('#filtered-count').text('(' + visibleCount + ' lists found)');
                } else {
                    $('#filtered-actions').hide();
                }
            }
            
            // Highlight search terms
            function highlightText(row, searchTerm) {
                row.find('td:lt(4)').each(function() { // Skip the Actions column
                    var cell = $(this);
                    var text = cell.text();
                    var regex = new RegExp('(' + searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
                    var highlightedText = text.replace(regex, '<span class="search-highlight">$1</span>');
                    if (highlightedText !== text) {
                        cell.html(highlightedText);
                    }
                });
            }
            
            // Remove highlighting
            function removeHighlight(row) {
                row.find('.search-highlight').each(function() {
                    $(this).replaceWith($(this).text());
                });
            }
            
            // Show combine lists modal
            function showCombineListsModal(listNames) {
                var modalHtml = '<div id="combine-lists-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000;">' +
                    '<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; max-width: 500px; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">' +
                    '<h2>📋 Create Combined List</h2>' +
                    '<p>Create a new list by combining recipients from the following filtered lists:</p>' +
                    '<ul style="margin: 15px 0; padding-left: 20px; max-height: 150px; overflow-y: auto; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 10px;">';
                
                listNames.forEach(function(name) {
                    modalHtml += '<li>' + name + '</li>';
                });
                
                modalHtml += '</ul>' +
                    '<div style="margin: 20px 0;">' +
                    '<label for="combined-list-name" style="font-weight: bold;">New List Name:</label><br>' +
                    '<input type="text" id="combined-list-name" placeholder="e.g., Combined Alumni List - Dec 2024" style="width: 100%; padding: 8px; margin-top: 5px;" />' +
                    '</div>' +
                    '<p style="font-size: 14px; color: #666; margin: 15px 0;">Note: Duplicate email addresses will be automatically removed.</p>' +
                    '<div style="margin-top: 20px; text-align: right;">' +
                    '<button type="button" id="cancel-combine" class="button" style="margin-right: 10px;">Cancel</button>' +
                    '<button type="button" id="save-combined-list" class="button button-primary">Create Combined List</button>' +
                    '</div>' +
                    '</div></div>';
                
                $('body').append(modalHtml);
                
                $('#cancel-combine').click(function() {
                    $('#combine-lists-modal').remove();
                });
                
                $('#save-combined-list').click(function() {
                    var newListName = $('#combined-list-name').val().trim();
                    if (!newListName) {
                        alert('Please enter a name for the combined list.');
                        return;
                    }
                    
                    // Get list IDs from visible rows
                    var listIds = [];
                    $('#recipients-table tbody tr:visible').each(function() {
                        var listId = $(this).find('.view-list').data('list-id');
                        if (listId) {
                            listIds.push(listId);
                        }
                    });
                    
                    combineLists(listIds, newListName);
                });
            }
            
            // Combine multiple lists into one
            function combineLists(listIds, newListName) {
                $('#save-combined-list').prop('disabled', true).text('Creating...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'combine_recipient_lists',
                        nonce: '<?php echo wp_create_nonce('combine_recipient_lists'); ?>',
                        list_ids: listIds,
                        new_list_name: newListName
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#combine-lists-modal').remove();
                            alert('Combined list created successfully with ' + response.data.total_recipients + ' unique recipients!');
                            location.reload();
                        } else {
                            alert('Error creating combined list: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred while creating the combined list.');
                    },
                    complete: function() {
                        $('#save-combined-list').prop('disabled', false).text('Create Combined List');
                    }
                });
            }
            
            // Show dynamic list view
            function showListView(listId) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'view_recipient_list',
                        nonce: '<?php echo wp_create_nonce('view_recipient_list'); ?>',
                        list_id: listId
                    },
                    success: function(response) {
                        if (response.success) {
                            var listData = response.data.list;
                            var recipients = response.data.recipients;
                            var columns = response.data.columns;
                            
                            showDynamicListModal(listData, recipients, columns);
                        } else {
                            alert('Error loading list: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred while loading the list.');
                    }
                });
            }
            
            // Show dynamic list modal with sortable/filterable table
            function showDynamicListModal(listData, recipients, columns) {
                var modalHtml = '<div id="dynamic-list-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000;">' +
                    '<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 8px; width: 90%; max-width: 1200px; max-height: 90vh; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">' +
                    '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #ddd; padding-bottom: 15px;">' +
                    '<div>' +
                    '<h2 style="margin: 0;">📋 ' + listData.list_name + '</h2>' +
                    '<p style="margin: 5px 0 0 0; color: #666;">' + listData.total_count + ' recipients • Created: ' + new Date(listData.created_at).toLocaleDateString() + '</p>' +
                    '</div>' +
                    '<button type="button" id="close-dynamic-modal" class="button button-primary">Close</button>' +
                    '</div>' +
                    
                    '<div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">' +
                    '<div>' +
                    '<input type="text" id="dynamic-search" placeholder="Search all columns..." style="width: 300px; padding: 8px;" />' +
                    '<button type="button" id="clear-dynamic-search" class="button" style="margin-left: 10px;">Clear</button>' +
                    '</div>' +
                    '<div>' +
                    '<button type="button" id="bulk-actions-btn" class="button button-secondary" disabled>Bulk Actions</button>' +
                    '</div>' +
                    '</div>' +
                    
                    '<div id="bulk-actions-panel" style="display: none; background: #f9f9f9; padding: 15px; margin-bottom: 15px; border-radius: 4px; border-left: 4px solid #0073aa;">' +
                    '<div style="margin-bottom: 10px;"><strong>Bulk Actions for Selected Rows:</strong></div>' +
                    '<div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">' +
                    '<div>' +
                    '<label style="margin-right: 5px;">Add Tag:</label>' +
                    '<input type="text" id="bulk-tag-input" placeholder="Tag name" style="padding: 5px; width: 150px;" />' +
                    '<button type="button" id="apply-bulk-tag" class="button button-primary" style="margin-left: 5px;">Add to Selected</button>' +
                    '</div>' +
                    '<div>' +
                    '<button type="button" id="bulk-delete" class="button" style="background: #dc3545; color: white;">Delete Selected</button>' +
                    '</div>' +
                    '</div>' +
                    '</div>' +
                    
                    '<div style="overflow: auto; max-height: 500px;">' +
                    '<table id="dynamic-recipients-table" class="wp-list-table widefat fixed striped" style="margin: 0;">' +
                    '<thead><tr>';
                
                // Add checkbox column
                modalHtml += '<th style="width: 40px;"><input type="checkbox" id="select-all-rows" /></th>';
                
                // Add column headers
                columns.forEach(function(column) {
                    var displayName = column.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    modalHtml += '<th class="dynamic-sortable" data-column="' + column + '" style="cursor: pointer; position: relative; padding-right: 20px;">' +
                                displayName + ' <span class="dynamic-sort-indicator" style="position: absolute; right: 5px;">↕</span></th>';
                });
                
                // Add actions column
                modalHtml += '<th style="width: 80px;">Actions</th>';
                
                modalHtml += '</tr></thead><tbody id="dynamic-recipients-body">';
                
                // Add data rows
                recipients.forEach(function(recipient, index) {
                    modalHtml += '<tr class="dynamic-recipient-row" data-row-index="' + index + '">';
                    modalHtml += '<td><input type="checkbox" class="row-checkbox" data-row-index="' + index + '" /></td>';
                    columns.forEach(function(column) {
                        var value = recipient[column] || '';
                        modalHtml += '<td class="editable-cell" data-column="' + column + '">' + $('<div>').text(value).html() + '</td>'; // Escape HTML
                    });
                    modalHtml += '<td><button type="button" class="button button-small edit-row-btn" data-row-index="' + index + '">Edit</button></td>';
                    modalHtml += '</tr>';
                });
                
                modalHtml += '</tbody></table></div>' +
                    '<div style="margin-top: 15px; text-align: right; border-top: 1px solid #ddd; padding-top: 15px;">' +
                    '<span id="dynamic-filter-count" style="color: #666; margin-right: 20px;"></span>' +
                    '<button type="button" id="create-filtered-sublist" class="button button-secondary" style="display: none;">Create Sublist from Results</button>' +
                    '</div>' +
                    '</div></div>';
                
                $('body').append(modalHtml);
                
                // Store data for sorting/filtering
                window.dynamicListData = {
                    listData: listData,
                    recipients: recipients,
                    columns: columns,
                    filteredRecipients: recipients
                };
                
                // Event handlers
                $('#close-dynamic-modal').click(function() {
                    $('#dynamic-list-modal').remove();
                });
                
                $('#dynamic-search').on('input', function() {
                    filterDynamicList($(this).val().toLowerCase());
                });
                
                $('#clear-dynamic-search').click(function() {
                    $('#dynamic-search').val('');
                    filterDynamicList('');
                });
                
                $('.dynamic-sortable').click(function() {
                    var column = $(this).data('column');
                    sortDynamicList(column);
                });
                
                // Edit row functionality
                $('.edit-row-btn').click(function() {
                    var rowIndex = $(this).data('row-index');
                    showEditRowModal(rowIndex);
                });
                
                // Bulk selection functionality
                $('#select-all-rows').change(function() {
                    $('.row-checkbox').prop('checked', $(this).is(':checked'));
                    updateBulkActionsState();
                });
                
                $('.row-checkbox').change(function() {
                    updateBulkActionsState();
                    
                    // Update select-all checkbox state
                    var totalCheckboxes = $('.row-checkbox').length;
                    var checkedCheckboxes = $('.row-checkbox:checked').length;
                    $('#select-all-rows').prop('checked', checkedCheckboxes === totalCheckboxes);
                });
                
                // Bulk actions button
                $('#bulk-actions-btn').click(function() {
                    $('#bulk-actions-panel').toggle();
                });
                
                // Apply bulk tag
                $('#apply-bulk-tag').click(function() {
                    var tag = $('#bulk-tag-input').val().trim();
                    if (!tag) {
                        alert('Please enter a tag name');
                        return;
                    }
                    applyBulkTag(tag);
                });
                
                // Bulk delete
                $('#bulk-delete').click(function() {
                    if (confirm('Are you sure you want to delete the selected recipients? This action cannot be undone.')) {
                        bulkDeleteRows();
                    }
                });
                
                // Initial count
                updateDynamicFilterCount();
            }
            
            // Filter dynamic list
            function filterDynamicList(searchTerm) {
                var allRows = $('#dynamic-recipients-body tr');
                var filteredRecipients = [];
                var visibleCount = 0;
                
                allRows.each(function() {
                    var row = $(this);
                    var matches = false;
                    
                    row.find('td').each(function() {
                        if ($(this).text().toLowerCase().includes(searchTerm)) {
                            matches = true;
                            return false; // Break loop
                        }
                    });
                    
                    if (matches || searchTerm === '') {
                        row.show();
                        visibleCount++;
                        
                        // Track filtered recipients for sublisting
                        var recipientIndex = row.index();
                        if (window.dynamicListData && window.dynamicListData.recipients[recipientIndex]) {
                            filteredRecipients.push(window.dynamicListData.recipients[recipientIndex]);
                        }
                    } else {
                        row.hide();
                    }
                });
                
                if (window.dynamicListData) {
                    window.dynamicListData.filteredRecipients = filteredRecipients;
                }
                
                updateDynamicFilterCount();
                
                // Show/hide create sublist button
                if (searchTerm !== '' && visibleCount > 0 && visibleCount < window.dynamicListData.recipients.length) {
                    $('#create-filtered-sublist').show();
                } else {
                    $('#create-filtered-sublist').hide();
                }
            }
            
            // Sort dynamic list
            function sortDynamicList(column) {
                var tbody = $('#dynamic-recipients-body');
                var rows = tbody.find('tr').toArray();
                var header = $('.dynamic-sortable[data-column="' + column + '"]');
                
                // Determine sort direction
                var currentSort = header.hasClass('sort-asc') ? 'asc' : (header.hasClass('sort-desc') ? 'desc' : 'none');
                var newSort = currentSort === 'asc' ? 'desc' : 'asc';
                
                // Update sort indicators
                $('.dynamic-sortable').removeClass('sort-asc sort-desc');
                $('.dynamic-sort-indicator').text('↕');
                
                header.addClass('sort-' + newSort);
                header.find('.dynamic-sort-indicator').text(newSort === 'asc' ? '↑' : '↓');
                
                // Get column index
                var columnIndex = window.dynamicListData.columns.indexOf(column);
                
                // Sort rows
                rows.sort(function(a, b) {
                    var aVal = $(a).find('td:eq(' + columnIndex + ')').text().toLowerCase();
                    var bVal = $(b).find('td:eq(' + columnIndex + ')').text().toLowerCase();
                    
                    // Try to parse as numbers if possible
                    var aNum = parseFloat(aVal);
                    var bNum = parseFloat(bVal);
                    
                    if (!isNaN(aNum) && !isNaN(bNum)) {
                        return newSort === 'asc' ? aNum - bNum : bNum - aNum;
                    } else {
                        if (newSort === 'asc') {
                            return aVal > bVal ? 1 : (aVal < bVal ? -1 : 0);
                        } else {
                            return aVal < bVal ? 1 : (aVal > bVal ? -1 : 0);
                        }
                    }
                });
                
                tbody.empty().append(rows);
            }
            
            // Update filter count
            function updateDynamicFilterCount() {
                var totalCount = window.dynamicListData ? window.dynamicListData.recipients.length : 0;
                var visibleCount = $('#dynamic-recipients-body tr:visible').length;
                
                if (visibleCount === totalCount) {
                    $('#dynamic-filter-count').text('Showing all ' + totalCount + ' recipients');
                } else {
                    $('#dynamic-filter-count').text('Showing ' + visibleCount + ' of ' + totalCount + ' recipients');
                }
            }
            
            // Show edit row modal
            function showEditRowModal(rowIndex) {
                if (!window.dynamicListData || !window.dynamicListData.recipients[rowIndex]) {
                    alert('Recipient data not found');
                    return;
                }
                
                var recipient = window.dynamicListData.recipients[rowIndex];
                var columns = window.dynamicListData.columns;
                
                var modalHtml = '<div id="edit-row-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10001;">' +
                    '<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 25px; border-radius: 8px; width: 90%; max-width: 500px; max-height: 80vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.4);">' +
                    '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #ddd; padding-bottom: 15px;">' +
                    '<h3 style="margin: 0;">✏️ Edit Recipient</h3>' +
                    '<button type="button" id="close-edit-modal" class="button">Cancel</button>' +
                    '</div>' +
                    '<form id="edit-row-form">';
                
                // Create form fields for each column
                columns.forEach(function(column) {
                    var value = recipient[column] || '';
                    var displayName = column.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    
                    modalHtml += '<div style="margin-bottom: 15px;">' +
                        '<label for="edit-' + column + '" style="display: block; font-weight: bold; margin-bottom: 5px;">' + displayName + ':</label>' +
                        '<input type="text" id="edit-' + column + '" name="' + column + '" value="' + $('<div>').text(value).html() + '" ' +
                        'style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" />' +
                        '</div>';
                });
                
                modalHtml += '<div style="margin-top: 20px; text-align: right; border-top: 1px solid #ddd; padding-top: 15px;">' +
                    '<button type="button" id="save-row-changes" class="button button-primary">Save Changes</button> ' +
                    '<button type="button" id="cancel-row-changes" class="button">Cancel</button>' +
                    '</div>' +
                    '</form>' +
                    '</div></div>';
                
                $('body').append(modalHtml);
                
                // Store row index for saving
                $('#edit-row-modal').data('row-index', rowIndex);
                
                // Event handlers
                $('#close-edit-modal, #cancel-row-changes').click(function() {
                    $('#edit-row-modal').remove();
                });
                
                $('#save-row-changes').click(function() {
                    saveRowChanges(rowIndex);
                });
                
                // Focus first field
                $('#edit-row-form input:first').focus();
            }
            
            // Save row changes
            function saveRowChanges(rowIndex) {
                var updatedData = {};
                var isValid = true;
                
                // Collect form data
                $('#edit-row-form input').each(function() {
                    var field = $(this).attr('name');
                    var value = $(this).val().trim();
                    updatedData[field] = value;
                    
                    // Basic email validation
                    if (field.toLowerCase() === 'email' && value) {
                        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailRegex.test(value)) {
                            alert('Please enter a valid email address');
                            $(this).focus();
                            isValid = false;
                            return false;
                        }
                    }
                });
                
                if (!isValid) return;
                
                // Disable save button
                var saveButton = $('#save-row-changes');
                saveButton.prop('disabled', true).text('Saving...');
                
                // Send AJAX request
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'edit_recipient_row',
                        nonce: '<?php echo wp_create_nonce('edit_recipient_row'); ?>',
                        list_id: window.dynamicListData.listData.id,
                        row_index: rowIndex,
                        updated_data: updatedData
                    },
                    success: function(response) {
                        if (response.success) {
                            // Update the local data
                            window.dynamicListData.recipients[rowIndex] = response.data.updated_recipient;
                            
                            // Update the table row
                            var row = $('tr[data-row-index="' + rowIndex + '"]');
                            window.dynamicListData.columns.forEach(function(column, colIndex) {
                                var newValue = response.data.updated_recipient[column] || '';
                                row.find('.editable-cell').eq(colIndex).text(newValue);
                            });
                            
                            $('#edit-row-modal').remove();
                            
                            // Show success message briefly
                            var successMsg = $('<div style="position: fixed; top: 50px; right: 20px; background: #46b450; color: white; padding: 10px 15px; border-radius: 4px; z-index: 10002;">✅ Recipient updated successfully</div>');
                            $('body').append(successMsg);
                            setTimeout(function() {
                                successMsg.fadeOut(500, function() { $(this).remove(); });
                            }, 2000);
                            
                        } else {
                            alert('Error saving changes: ' + response.data.message);
                            saveButton.prop('disabled', false).text('Save Changes');
                        }
                    },
                    error: function() {
                        alert('An error occurred while saving changes');
                        saveButton.prop('disabled', false).text('Save Changes');
                    }
                });
            }
            
            // Update bulk actions state
            function updateBulkActionsState() {
                var checkedCount = $('.row-checkbox:checked').length;
                var bulkBtn = $('#bulk-actions-btn');
                
                if (checkedCount > 0) {
                    bulkBtn.prop('disabled', false).text('Bulk Actions (' + checkedCount + ' selected)');
                } else {
                    bulkBtn.prop('disabled', true).text('Bulk Actions');
                    $('#bulk-actions-panel').hide();
                }
            }
            
            // Apply bulk tag
            function applyBulkTag(tag) {
                var selectedRows = $('.row-checkbox:checked');
                var rowIndices = [];
                
                selectedRows.each(function() {
                    rowIndices.push($(this).data('row-index'));
                });
                
                if (rowIndices.length === 0) {
                    alert('No rows selected');
                    return;
                }
                
                // Disable the button
                $('#apply-bulk-tag').prop('disabled', true).text('Applying...');
                
                // Update each selected recipient's tags
                var updatedData = {};
                rowIndices.forEach(function(rowIndex) {
                    var currentTags = window.dynamicListData.recipients[rowIndex].tags || '';
                    var newTags = currentTags ? currentTags + ', ' + tag : tag;
                    
                    // Remove duplicates and clean up
                    var tagArray = newTags.split(',').map(function(t) { return t.trim(); }).filter(function(t) { return t; });
                    var uniqueTags = [...new Set(tagArray)];
                    window.dynamicListData.recipients[rowIndex].tags = uniqueTags.join(', ');
                    
                    updatedData[rowIndex] = {tags: window.dynamicListData.recipients[rowIndex].tags};
                });
                
                // Send bulk update to server
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'bulk_edit_recipients',
                        nonce: '<?php echo wp_create_nonce('bulk_edit_recipients'); ?>',
                        list_id: window.dynamicListData.listData.id,
                        bulk_updates: updatedData
                    },
                    success: function(response) {
                        if (response.success) {
                            // Update table cells
                            rowIndices.forEach(function(rowIndex) {
                                var row = $('tr[data-row-index="' + rowIndex + '"]');
                                var tagsColumnIndex = window.dynamicListData.columns.indexOf('tags');
                                if (tagsColumnIndex >= 0) {
                                    row.find('.editable-cell').eq(tagsColumnIndex).text(window.dynamicListData.recipients[rowIndex].tags);
                                }
                            });
                            
                            // Clear selection and input
                            $('.row-checkbox').prop('checked', false);
                            $('#select-all-rows').prop('checked', false);
                            $('#bulk-tag-input').val('');
                            $('#bulk-actions-panel').hide();
                            updateBulkActionsState();
                            
                            // Show success message
                            var successMsg = $('<div style="position: fixed; top: 50px; right: 20px; background: #46b450; color: white; padding: 10px 15px; border-radius: 4px; z-index: 10002;">✅ Tag applied to ' + rowIndices.length + ' recipients</div>');
                            $('body').append(successMsg);
                            setTimeout(function() {
                                successMsg.fadeOut(500, function() { $(this).remove(); });
                            }, 3000);
                            
                        } else {
                            alert('Error applying tags: ' + response.data.message);
                        }
                        $('#apply-bulk-tag').prop('disabled', false).text('Add to Selected');
                    },
                    error: function() {
                        alert('An error occurred while applying tags');
                        $('#apply-bulk-tag').prop('disabled', false).text('Add to Selected');
                    }
                });
            }
            
            // Bulk delete rows
            function bulkDeleteRows() {
                var selectedRows = $('.row-checkbox:checked');
                var rowIndices = [];
                
                selectedRows.each(function() {
                    rowIndices.push(parseInt($(this).data('row-index')));
                });
                
                if (rowIndices.length === 0) {
                    alert('No rows selected');
                    return;
                }
                
                // Disable the button
                $('#bulk-delete').prop('disabled', true).text('Deleting...');
                
                // Remove from local data (in reverse order to maintain indices)
                rowIndices.sort(function(a, b) { return b - a; });
                rowIndices.forEach(function(rowIndex) {
                    window.dynamicListData.recipients.splice(rowIndex, 1);
                });
                
                // Send update to server
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'bulk_delete_recipients',
                        nonce: '<?php echo wp_create_nonce('bulk_delete_recipients'); ?>',
                        list_id: window.dynamicListData.listData.id,
                        recipients_data: JSON.stringify(window.dynamicListData.recipients)
                    },
                    success: function(response) {
                        if (response.success) {
                            // Remove rows from table
                            selectedRows.closest('tr').remove();
                            
                            // Reindex remaining rows
                            $('#dynamic-recipients-body tr').each(function(index) {
                                $(this).attr('data-row-index', index);
                                $(this).find('.row-checkbox').attr('data-row-index', index);
                                $(this).find('.edit-row-btn').attr('data-row-index', index);
                            });
                            
                            $('#bulk-actions-panel').hide();
                            updateBulkActionsState();
                            updateDynamicFilterCount();
                            
                            // Show success message
                            var successMsg = $('<div style="position: fixed; top: 50px; right: 20px; background: #46b450; color: white; padding: 10px 15px; border-radius: 4px; z-index: 10002;">✅ Deleted ' + rowIndices.length + ' recipients</div>');
                            $('body').append(successMsg);
                            setTimeout(function() {
                                successMsg.fadeOut(500, function() { $(this).remove(); });
                            }, 3000);
                            
                        } else {
                            alert('Error deleting recipients: ' + response.data.message);
                        }
                        $('#bulk-delete').prop('disabled', false).text('Delete Selected');
                    },
                    error: function() {
                        alert('An error occurred while deleting recipients');
                        $('#bulk-delete').prop('disabled', false).text('Delete Selected');
                    }
                });
            }
            
            // Show add to list modal
            function showAddToListModal(listId, listName) {
                var modalHtml = '<div id="add-to-list-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000;">' +
                    '<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 25px; border-radius: 8px; width: 90%; max-width: 500px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">' +
                    '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #ddd; padding-bottom: 15px;">' +
                    '<h3 style="margin: 0;">📂 Add Recipients to "' + listName + '"</h3>' +
                    '<button type="button" id="close-add-modal" class="button">Cancel</button>' +
                    '</div>' +
                    '<form id="add-to-list-form" enctype="multipart/form-data">' +
                    '<input type="hidden" id="target-list-id" value="' + listId + '" />' +
                    '<div style="margin-bottom: 15px;">' +
                    '<label for="merge-csv-file" style="display: block; font-weight: bold; margin-bottom: 5px;">Upload CSV File:</label>' +
                    '<input type="file" id="merge-csv-file" name="csv_file" accept=".csv" required style="width: 100%;" />' +
                    '<p class="description" style="margin-top: 5px;">CSV will be merged with existing list. Duplicates will be removed based on email addresses.</p>' +
                    '</div>' +
                    '<div style="margin-bottom: 15px;">' +
                    '<label>' +
                    '<input type="checkbox" id="preview-before-merge" checked /> Preview data before merging' +
                    '</label>' +
                    '</div>' +
                    '<div style="margin-top: 20px; text-align: right; border-top: 1px solid #ddd; padding-top: 15px;">' +
                    '<button type="button" id="process-merge" class="button button-primary">Upload & Merge</button> ' +
                    '<button type="button" id="cancel-merge" class="button">Cancel</button>' +
                    '</div>' +
                    '</form>' +
                    '<div id="merge-preview" style="display: none; margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd;"></div>' +
                    '</div></div>';
                
                $('body').append(modalHtml);
                
                // Event handlers
                $('#close-add-modal, #cancel-merge').click(function() {
                    $('#add-to-list-modal').remove();
                });
                
                $('#process-merge').click(function() {
                    processCsvMerge();
                });
            }
            
            // Process CSV merge
            function processCsvMerge() {
                var fileInput = $('#merge-csv-file')[0];
                var listId = $('#target-list-id').val();
                var previewMode = $('#preview-before-merge').is(':checked');
                
                if (!fileInput.files[0]) {
                    alert('Please select a CSV file');
                    return;
                }
                
                var formData = new FormData();
                formData.append('action', 'merge_csv_to_list');
                formData.append('nonce', '<?php echo wp_create_nonce('merge_csv_to_list'); ?>');
                formData.append('list_id', listId);
                formData.append('csv_file', fileInput.files[0]);
                formData.append('preview_only', previewMode ? '1' : '0');
                
                var button = $('#process-merge');
                button.prop('disabled', true).text(previewMode ? 'Processing...' : 'Merging...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            if (previewMode && response.data.preview) {
                                showMergePreview(response.data);
                                button.prop('disabled', false).text('Confirm Merge');
                                $('#preview-before-merge').prop('checked', false);
                            } else {
                                $('#add-to-list-modal').remove();
                                alert('Success! Added ' + response.data.added_count + ' new recipients to the list. ' + 
                                      response.data.duplicate_count + ' duplicates were skipped.');
                                location.reload(); // Refresh to show updated counts
                            }
                        } else {
                            alert('Error: ' + response.data.message);
                            button.prop('disabled', false).text('Upload & Merge');
                        }
                    },
                    error: function() {
                        alert('An error occurred while processing the file');
                        button.prop('disabled', false).text('Upload & Merge');
                    }
                });
            }
            
            // Show merge preview
            function showMergePreview(data) {
                var previewDiv = $('#merge-preview');
                var html = '<h4>Merge Preview:</h4>' +
                    '<p><strong>' + data.new_count + ' new recipients</strong> will be added to the list.</p>' +
                    '<p><strong>' + data.duplicate_count + ' duplicates</strong> found and will be skipped.</p>';
                
                if (data.preview_data && data.preview_data.length > 0) {
                    html += '<table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">' +
                        '<thead><tr>';
                    
                    // Add headers
                    var headers = Object.keys(data.preview_data[0]);
                    headers.forEach(function(header) {
                        html += '<th>' + header.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) + '</th>';
                    });
                    html += '</tr></thead><tbody>';
                    
                    // Add sample rows
                    data.preview_data.slice(0, 5).forEach(function(row) {
                        html += '<tr>';
                        headers.forEach(function(header) {
                            html += '<td>' + (row[header] || '') + '</td>';
                        });
                        html += '</tr>';
                    });
                    
                    if (data.preview_data.length > 5) {
                        html += '<tr><td colspan="' + headers.length + '"><em>... and ' + (data.preview_data.length - 5) + ' more</em></td></tr>';
                    }
                    html += '</tbody></table>';
                }
                
                previewDiv.html(html).show();
            }
            
            // Show export modal
            function showExportModal(listId, listName) {
                var modalHtml = '<div id="export-list-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000;">' +
                    '<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 25px; border-radius: 8px; width: 90%; max-width: 500px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">' +
                    '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #ddd; padding-bottom: 15px;">' +
                    '<h3 style="margin: 0;">📥 Export "' + listName + '" as CSV</h3>' +
                    '<button type="button" id="close-export-modal" class="button">Cancel</button>' +
                    '</div>' +
                    '<form id="export-list-form">' +
                    '<input type="hidden" id="export-list-id" value="' + listId + '" />' +
                    
                    '<div style="margin-bottom: 20px;">' +
                    '<h4 style="margin: 0 0 10px 0;">Export Options:</h4>' +
                    '<div style="margin-bottom: 10px;">' +
                    '<label style="display: block; margin-bottom: 5px;">' +
                    '<input type="checkbox" id="exclude-bounced" checked /> Exclude bounced email addresses' +
                    '</label>' +
                    '<p class="description" style="margin-left: 20px; margin-top: 5px; color: #666; font-size: 12px;">Remove recipients whose emails have bounced multiple times</p>' +
                    '</div>' +
                    '<div style="margin-bottom: 10px;">' +
                    '<label style="display: block; margin-bottom: 5px;">' +
                    '<input type="checkbox" id="exclude-unsubscribed" checked /> Exclude unsubscribed email addresses' +
                    '</label>' +
                    '<p class="description" style="margin-left: 20px; margin-top: 5px; color: #666; font-size: 12px;">Remove recipients who have unsubscribed from emails</p>' +
                    '</div>' +
                    '</div>' +
                    
                    '<div style="margin-bottom: 15px; padding: 15px; background: #f9f9f9; border-radius: 4px;">' +
                    '<h4 style="margin: 0 0 10px 0;">File Format:</h4>' +
                    '<p style="margin: 0; font-size: 12px; color: #666;">CSV file will include all columns from the original import plus any tags that have been added.</p>' +
                    '</div>' +
                    
                    '<div style="margin-top: 20px; text-align: right; border-top: 1px solid #ddd; padding-top: 15px;">' +
                    '<button type="button" id="start-export" class="button button-primary">Export CSV</button> ' +
                    '<button type="button" id="cancel-export" class="button">Cancel</button>' +
                    '</div>' +
                    '</form>' +
                    '</div></div>';
                
                $('body').append(modalHtml);
                
                // Event handlers
                $('#close-export-modal, #cancel-export').click(function() {
                    $('#export-list-modal').remove();
                });
                
                $('#start-export').click(function() {
                    processListExport();
                });
            }
            
            // Process list export
            function processListExport() {
                var listId = $('#export-list-id').val();
                var excludeBounced = $('#exclude-bounced').is(':checked');
                var excludeUnsubscribed = $('#exclude-unsubscribed').is(':checked');
                
                var button = $('#start-export');
                button.prop('disabled', true).text('Preparing export...');
                
                // Create a form and submit it to trigger download
                var form = $('<form>', {
                    'method': 'POST',
                    'action': ajaxurl,
                    'target': '_blank'
                }).append(
                    $('<input>', {'type': 'hidden', 'name': 'action', 'value': 'export_recipient_list'}),
                    $('<input>', {'type': 'hidden', 'name': 'nonce', 'value': '<?php echo wp_create_nonce('export_recipient_list'); ?>'}),
                    $('<input>', {'type': 'hidden', 'name': 'list_id', 'value': listId}),
                    $('<input>', {'type': 'hidden', 'name': 'exclude_bounced', 'value': excludeBounced ? '1' : '0'}),
                    $('<input>', {'type': 'hidden', 'name': 'exclude_unsubscribed', 'value': excludeUnsubscribed ? '1' : '0'})
                );
                
                $('body').append(form);
                form.submit();
                form.remove();
                
                // Close modal after brief delay
                setTimeout(function() {
                    $('#export-list-modal').remove();
                }, 1000);
            }
        });
        </script>
        <?php
    }
    
    public function unsubscribed_page() {
        global $wpdb;
        
        // Get unsubscribed emails with pagination
        $per_page = 50;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        $unsubscribed = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}alumni_email_unsubscribes 
             WHERE unsubscribed_at IS NOT NULL 
             ORDER BY unsubscribed_at DESC 
             LIMIT %d OFFSET %d",
            $per_page, $offset
        ));
        
        $total_unsubscribed = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}alumni_email_unsubscribes WHERE unsubscribed_at IS NOT NULL"
        );
        
        ?>
        <div class="wrap">
            <h1>🚫 Unsubscribed Emails</h1>
            
            <div class="notice notice-info">
                <p><strong>Unsubscribed List:</strong> These email addresses have opted out and will be automatically excluded from future campaigns.</p>
            </div>
            
            <?php if (empty($unsubscribed)): ?>
                <div class="notice notice-success">
                    <p>No unsubscribed emails found. Great engagement!</p>
                </div>
            <?php else: ?>
                <div class="tablenav top">
                    <div class="alignleft actions">
                        <span class="displaying-num"><?php echo number_format($total_unsubscribed); ?> unsubscribed email<?php echo $total_unsubscribed !== 1 ? 's' : ''; ?></span>
                    </div>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col">Email Address</th>
                            <th scope="col">Unsubscribed Date</th>
                            <th scope="col">IP Address</th>
                            <th scope="col">User Agent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unsubscribed as $unsub): ?>
                            <tr>
                                <td><strong><?php echo esc_html($unsub->email); ?></strong></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($unsub->unsubscribed_at)); ?></td>
                                <td><?php echo esc_html($unsub->ip_address ?: 'Unknown'); ?></td>
                                <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;"><?php echo esc_html(substr($unsub->user_agent ?: 'Unknown', 0, 50)) . (strlen($unsub->user_agent) > 50 ? '...' : ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php
                // Simple pagination
                $total_pages = ceil($total_unsubscribed / $per_page);
                if ($total_pages > 1):
                ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        $page_links = paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo; Previous',
                            'next_text' => 'Next &raquo;',
                            'total' => $total_pages,
                            'current' => $current_page
                        ));
                        echo $page_links;
                        ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function bounced_page() {
        global $wpdb;
        
        // Get bounced emails with recent bounce counts
        $per_page = 50;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        $bounced_emails = $wpdb->get_results($wpdb->prepare(
            "SELECT recipient_email, 
                    COUNT(*) as bounce_count,
                    MAX(sent_at) as last_bounce,
                    bounce_reason
             FROM {$wpdb->prefix}alumni_email_logs 
             WHERE status IN ('failed', 'bounced') 
             AND bounce_reason IS NOT NULL
             AND sent_at > DATE_SUB(NOW(), INTERVAL 90 DAY)
             GROUP BY recipient_email 
             ORDER BY bounce_count DESC, last_bounce DESC 
             LIMIT %d OFFSET %d",
            $per_page, $offset
        ));
        
        $total_bounced = $wpdb->get_var(
            "SELECT COUNT(DISTINCT recipient_email) 
             FROM {$wpdb->prefix}alumni_email_logs 
             WHERE status IN ('failed', 'bounced') 
             AND bounce_reason IS NOT NULL
             AND sent_at > DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
        
        ?>
        <div class="wrap">
            <h1>📧 Bounced Emails (Last 90 Days)</h1>
            
            <div class="notice notice-warning">
                <p><strong>Bounce Management:</strong> Emails with 3+ bounces are automatically excluded from campaigns. Consider cleaning your lists regularly.</p>
            </div>
            
            <?php if (empty($bounced_emails)): ?>
                <div class="notice notice-success">
                    <p>No bounced emails found in the last 90 days. Excellent list quality!</p>
                </div>
            <?php else: ?>
                <div class="tablenav top">
                    <div class="alignleft actions">
                        <span class="displaying-num"><?php echo number_format($total_bounced); ?> email<?php echo $total_bounced !== 1 ? 's' : ''; ?> with bounces</span>
                    </div>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col">Email Address</th>
                            <th scope="col">Bounce Count</th>
                            <th scope="col">Status</th>
                            <th scope="col">Last Bounce</th>
                            <th scope="col">Bounce Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bounced_emails as $bounce): ?>
                            <tr>
                                <td><strong><?php echo esc_html($bounce->recipient_email); ?></strong></td>
                                <td>
                                    <span class="bounce-count <?php echo $bounce->bounce_count >= 3 ? 'high' : 'medium'; ?>">
                                        <?php echo $bounce->bounce_count; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($bounce->bounce_count >= 3): ?>
                                        <span style="color: #d63384; font-weight: bold;">🚫 Blocked</span>
                                    <?php else: ?>
                                        <span style="color: #fd7e14;">⚠️ Warning</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($bounce->last_bounce)); ?></td>
                                <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis;"><?php echo esc_html($bounce->bounce_reason); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php
                // Simple pagination
                $total_pages = ceil($total_bounced / $per_page);
                if ($total_pages > 1):
                ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        $page_links = paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo; Previous',
                            'next_text' => 'Next &raquo;',
                            'total' => $total_pages,
                            'current' => $current_page
                        ));
                        echo $page_links;
                        ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <style>
        .bounce-count {
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }
        .bounce-count.high {
            background-color: #f8d7da;
            color: #721c24;
        }
        .bounce-count.medium {
            background-color: #fff3cd;
            color: #856404;
        }
        </style>
        <?php
    }
    
}

// Initialize the plugin
new AlumniBulkEmail();
?>