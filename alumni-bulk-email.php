<?php
/**
 * Plugin Name: Alumni Bulk Email
 * Plugin URI: https://github.com/mattbaya/alumni-bulk-email-plugin
 * Description: Send bulk emails to alumni with Mailgun integration, CSV logging, and bounce tracking.
 * Version: 0.1.6
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
define('ALUMNI_BULK_EMAIL_VERSION', '0.1.6');
define('ALUMNI_BULK_EMAIL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ALUMNI_BULK_EMAIL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ALUMNI_BULK_EMAIL_GITHUB_REPO', 'mattbaya/alumni-bulk-email-plugin');

// Load GitHub Updater if available (optional for basic functionality)
if (file_exists(plugin_dir_path(__FILE__) . 'vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php')) {
    require_once plugin_dir_path(__FILE__) . 'vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php';
    
    // Initialize automatic updates from GitHub
    if (class_exists('YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
        $myUpdateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/' . ALUMNI_BULK_EMAIL_GITHUB_REPO . '/',
            __FILE__,
            'alumni-bulk-email'
        );
        
        // Set the branch that contains the stable release (optional)
        $myUpdateChecker->setBranch('main');
    }
}

// Main plugin class
class AlumniBulkEmail {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_send_test_email', array($this, 'handle_test_email'));
        add_action('wp_ajax_send_bulk_email', array($this, 'handle_bulk_email'));
        add_action('wp_ajax_upload_csv', array($this, 'handle_csv_upload'));
        add_action('wp_ajax_save_campaign', array($this, 'handle_save_campaign'));
        add_action('wp_ajax_load_campaign', array($this, 'handle_load_campaign'));
        add_action('wp_ajax_delete_campaign', array($this, 'handle_delete_campaign'));
        add_action('wp_ajax_recreate_tables', array($this, 'handle_recreate_tables'));
        
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
        dbDelta($sql_campaigns);
        dbDelta($sql_logs);
        
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
        
        // Get recent campaigns and saved drafts for display
        global $wpdb;
        $recent_campaigns = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}alumni_email_campaigns 
            WHERE status IN ('completed', 'sending', 'failed')
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        
        $saved_campaigns = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}alumni_email_campaigns 
            WHERE status = 'draft'
            ORDER BY created_at DESC
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
                                    <label for="csv_file">Recipients CSV File</label>
                                </th>
                                <td>
                                    <input type="file" id="csv_file" name="csv_file" accept=".csv" required />
                                    <p class="description">
                                        CSV with columns: <strong>email</strong> (required), name, first_name, last_name<br>
                                        <button type="button" id="preview_csv" class="button button-small" style="margin-top: 5px;">Preview Recipients</button>
                                    </p>
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
            
            <!-- Recent Campaigns -->
            <?php if (!empty($recent_campaigns)): ?>
            <div class="postbox">
                <h2 class="hndle">📋 Recent Campaigns</h2>
                <div class="inside">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Campaign Name</th>
                                <th>Subject</th>
                                <th>Recipients</th>
                                <th>Sent</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_campaigns as $campaign): ?>
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
                                <td><?php echo esc_html(date('M j, Y H:i', strtotime($campaign->created_at))); ?></td>
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
            
            // Update campaign preview
            function updatePreview() {
                $('#preview_campaign_name').text($('#campaign_name').val() || '-');
                $('#preview_subject').text($('#subject').val() || '-');
                $('#preview_recipient_count').text(csvData.length || '0');
            }
            
            $('#campaign_name, #subject').on('input', updatePreview);
            
            // New Campaign - Clear form
            $('#new_campaign').click(function() {
                $('#campaign_id').val('');
                $('#campaign_name').val('');
                $('#subject').val('');
                $('#csv_file').val('');
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
            
            // CSV Preview
            $('#preview_csv').click(function() {
                var fileInput = $('#csv_file')[0];
                if (!fileInput.files[0]) {
                    alert('Please select a CSV file first.');
                    return;
                }
                
                var formData = new FormData();
                formData.append('csv_file', fileInput.files[0]);
                formData.append('action', 'upload_csv');
                formData.append('nonce', '<?php echo wp_create_nonce('upload_csv'); ?>');
                
                $('#csv_preview').html('<p>Loading preview...</p>').show();
                
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
                            
                            var html = '<h4>✅ CSV Preview (' + csvData.length + ' recipients)</h4>';
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
                
                if (!tinyMCE.get('html_content').getContent()) {
                    alert('Please write your email content.');
                    return;
                }
                
                var confirmed = confirm('Send email campaign to ' + csvData.length + ' recipients?\\n\\nThis action cannot be undone.');
                if (!confirmed) return;
                
                var formData = new FormData($('#bulk-email-form')[0]);
                formData.append('action', 'send_bulk_email');
                formData.append('nonce', '<?php echo wp_create_nonce('send_bulk_email'); ?>');
                formData.append('html_content', tinyMCE.get('html_content').getContent());
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
                
                <h2>Auto-Updates</h2>
                <p>This plugin automatically checks for updates from:</p>
                <code>https://github.com/<?php echo ALUMNI_BULK_EMAIL_GITHUB_REPO; ?></code>
                <p class="description">Updates will appear in your WordPress admin when available. Current version: <strong><?php echo ALUMNI_BULK_EMAIL_VERSION; ?></strong></p>
                
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
    
    public function handle_csv_upload() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'upload_csv') || !current_user_can('manage_options')) {
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
        
        if (!wp_verify_nonce($_POST['nonce'], 'send_bulk_email') || !current_user_can('manage_options')) {
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
        $html_content = wp_kses_post($_POST['html_content']);
        
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
            $sending_campaign_id = $this->create_campaign_record($campaign_name, $subject, $html_content, count($recipients));
            
            if (!$sending_campaign_id) {
                echo json_encode(array('success' => false, 'data' => array('message' => 'Failed to create campaign record')));
                exit;
            }
        }
        
        // Send emails
        $sent_count = 0;
        $failed_count = 0;
        
        foreach ($recipients as $recipient) {
            $personalized_subject = $this->personalize_content($subject, $recipient);
            $personalized_html = $this->personalize_content($html_content, $recipient);
            
            $result = $this->send_mailgun_email(
                $recipient['email'],
                $personalized_subject,
                $personalized_html
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
            
            // Find email column (case insensitive)
            $email_col = false;
            $name_col = false;
            $first_name_col = false;
            $last_name_col = false;
            
            foreach ($header as $index => $column) {
                $column_lower = strtolower(trim($column));
                if (in_array($column_lower, array('email', 'email_address', 'emailaddress'))) {
                    $email_col = $index;
                } elseif (in_array($column_lower, array('name', 'full_name', 'fullname'))) {
                    $name_col = $index;
                } elseif (in_array($column_lower, array('first_name', 'firstname', 'fname'))) {
                    $first_name_col = $index;
                } elseif (in_array($column_lower, array('last_name', 'lastname', 'lname'))) {
                    $last_name_col = $index;
                }
            }
            
            if ($email_col === false) {
                fclose($handle);
                return $recipients;
            }
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $email = trim($data[$email_col]);
                if (is_email($email)) {
                    $name = '';
                    if ($name_col !== false && isset($data[$name_col])) {
                        $name = trim($data[$name_col]);
                    } elseif ($first_name_col !== false && $last_name_col !== false) {
                        $first = isset($data[$first_name_col]) ? trim($data[$first_name_col]) : '';
                        $last = isset($data[$last_name_col]) ? trim($data[$last_name_col]) : '';
                        $name = trim($first . ' ' . $last);
                    }
                    
                    $recipients[] = array(
                        'email' => $email,
                        'name' => $name,
                        'first_name' => $first_name_col !== false && isset($data[$first_name_col]) ? trim($data[$first_name_col]) : '',
                        'last_name' => $last_name_col !== false && isset($data[$last_name_col]) ? trim($data[$last_name_col]) : ''
                    );
                }
            }
            fclose($handle);
        }
        
        return $recipients;
    }
    
    private function create_campaign_record($campaign_name, $subject, $html_content, $recipients_count) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'alumni_email_campaigns',
            array(
                'campaign_name' => $campaign_name,
                'subject' => $subject,
                'html_content' => $html_content,
                'recipients_count' => $recipients_count,
                'status' => 'sending',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s')
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
        
        if (!wp_verify_nonce($_POST['nonce'], 'save_campaign') || !current_user_can('manage_options')) {
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
        
        if (!wp_verify_nonce($_POST['nonce'], 'load_campaign') || !current_user_can('manage_options')) {
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
            "SELECT * FROM {$wpdb->prefix}alumni_email_campaigns WHERE id = %d AND status = 'draft'",
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
        
        if (!wp_verify_nonce($_POST['nonce'], 'delete_campaign') || !current_user_can('manage_options')) {
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