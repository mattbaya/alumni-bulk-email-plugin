<?php
/**
 * Plugin Name: Alumni Bulk Email
 * Plugin URI: https://github.com/mattbaya/alumni-bulk-email-plugin
 * Description: Send bulk emails to alumni with Mailgun integration, CSV/Excel support, dynamic column detection, campaign tracking, and comprehensive bounce management.
 * Version: 0.6.0
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
define('ALUMNI_BULK_EMAIL_VERSION', '0.6.0');
define('ALUMNI_BULK_EMAIL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ALUMNI_BULK_EMAIL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ALUMNI_BULK_EMAIL_GITHUB_REPO', 'mattbaya/alumni-bulk-email-plugin');

// Load Composer autoloader
if (file_exists(ALUMNI_BULK_EMAIL_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once ALUMNI_BULK_EMAIL_PLUGIN_DIR . 'vendor/autoload.php';
}

// Load plugin classes
require_once ALUMNI_BULK_EMAIL_PLUGIN_DIR . 'includes/class-database.php';
require_once ALUMNI_BULK_EMAIL_PLUGIN_DIR . 'includes/class-file-processor.php';
require_once ALUMNI_BULK_EMAIL_PLUGIN_DIR . 'includes/class-email-service.php';
require_once ALUMNI_BULK_EMAIL_PLUGIN_DIR . 'includes/class-list-manager.php';
require_once ALUMNI_BULK_EMAIL_PLUGIN_DIR . 'includes/class-campaign-manager.php';
require_once ALUMNI_BULK_EMAIL_PLUGIN_DIR . 'includes/class-header-footer-manager.php';
require_once ALUMNI_BULK_EMAIL_PLUGIN_DIR . 'includes/class-typography-preset-manager.php';
require_once ALUMNI_BULK_EMAIL_PLUGIN_DIR . 'includes/class-ajax-handlers.php';

// Main plugin class
class AlumniBulkEmail {
    
    private $ajax_handlers;
    
    public function __construct() {
        $this->ajax_handlers = new Alumni_Ajax_Handlers();
        
        // Initialize plugin
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register AJAX handlers
        $this->ajax_handlers->register_handlers();
        
        // Initialize update checker
        $this->init_update_checker();
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Create database tables
        if (!Alumni_Database::tables_exist()) {
            Alumni_Database::create_tables();
        }
        
        // Set plugin version
        if (get_option('alumni_bulk_email_version') !== ALUMNI_BULK_EMAIL_VERSION) {
            update_option('alumni_bulk_email_version', ALUMNI_BULK_EMAIL_VERSION);
        }
    }
    
    /**
     * Add admin menu pages
     */
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
            'Email Lists',
            'Email Lists',
            'edit_posts',
            'alumni-email-lists',
            array($this, 'email_lists_page')
        );
        
        add_submenu_page(
            'alumni-bulk-email',
            'Campaign History',
            'Campaign History',
            'edit_posts',
            'alumni-campaign-history',
            array($this, 'campaign_history_page')
        );
        
        add_submenu_page(
            'alumni-bulk-email',
            'Email Logs',
            'Email Logs',
            'edit_posts',
            'alumni-email-logs',
            array($this, 'email_logs_page')
        );
        
        add_submenu_page(
            'alumni-bulk-email',
            'Headers & Footers',
            'Headers & Footers',
            'edit_posts',
            'alumni-headers-footers',
            array($this, 'headers_footers_page')
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
    
    /**
     * Main admin page - Create Campaigns
     */
    public function admin_page() {
        echo '<div class="wrap">';
        echo '<h1>Alumni Bulk Email - Create Campaign</h1>';
        
        // Check if Mailgun is configured
        $email_service = new Alumni_Email_Service();
        if (!$email_service->is_mailgun_configured()) {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>Mailgun not configured!</strong> Please go to <a href="admin.php?page=alumni-bulk-email-settings">Settings</a> to configure your Mailgun API credentials.</p>';
            echo '</div>';
        }
        
        // Load the admin UI
        $this->render_campaign_creation_form();
        
        echo '</div>';
    }
    
    /**
     * Email Lists page
     */
    public function email_lists_page() {
        echo '<div class="wrap">';
        echo '<h1>Email Lists</h1>';
        
        // Load and display email lists
        $list_manager = new Alumni_List_Manager();
        $lists = $list_manager->get_all_lists();
        
        $this->render_email_lists_table($lists);
        
        echo '</div>';
    }
    
    /**
     * Campaign History page
     */
    public function campaign_history_page() {
        echo '<div class="wrap">';
        echo '<h1>Campaign History</h1>';
        
        // Load and display campaigns
        $campaign_manager = new Alumni_Campaign_Manager();
        $campaigns = $campaign_manager->get_all_campaigns();
        
        $this->render_campaigns_table($campaigns);
        
        echo '</div>';
    }
    
    /**
     * Email Logs page
     */
    public function email_logs_page() {
        echo '<div class="wrap">';
        echo '<h1>Email Logs</h1>';
        
        // Load and display email logs
        $campaign_manager = new Alumni_Campaign_Manager();
        $logs = $campaign_manager->get_all_logs(100);
        
        $this->render_email_logs_table($logs);
        
        echo '</div>';
    }
    
    /**
     * Headers & Footers page
     */
    public function headers_footers_page() {
        echo '<div class="wrap">';
        echo '<h1>Headers & Footers</h1>';
        echo '<p>Create reusable email templates for consistent branding across all campaigns.</p>';
        
        // Load header/footer manager for data
        $header_footer_manager = new Alumni_Header_Footer_Manager();
        $templates = $header_footer_manager->get_all_templates();
        $stats = $header_footer_manager->get_template_stats();
        
        // Load typography preset manager for typography options
        $typography_preset_manager = new Alumni_Typography_Preset_Manager();
        $typography_presets = $typography_preset_manager->get_all_presets();
        $preset_stats = $typography_preset_manager->get_preset_stats();
        
        ?>
        <div class="template-management">
            <!-- Template Statistics -->
            <div class="template-stats" style="display: flex; gap: 20px; margin: 20px 0;">
                <div class="stat-box" style="background: #f0f0f1; padding: 15px; border-radius: 5px;">
                    <h3>Total Templates: <?php echo $stats['total']; ?></h3>
                </div>
                <div class="stat-box" style="background: #f0f0f1; padding: 15px; border-radius: 5px;">
                    <h3>Headers: <?php echo $stats['headers']; ?></h3>
                </div>
                <div class="stat-box" style="background: #f0f0f1; padding: 15px; border-radius: 5px;">
                    <h3>Footers: <?php echo $stats['footers']; ?></h3>
                </div>
            </div>

            <!-- Create New Template Button -->
            <div style="margin: 20px 0;">
                <button type="button" class="button button-primary" onclick="showCreateTemplateForm()">
                    Create New Template
                </button>
                <button type="button" class="button" onclick="loadTemplates()">
                    Refresh Templates
                </button>
            </div>

            <!-- Create/Edit Template Form -->
            <div id="template-form" style="display: none; background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin: 20px 0;">
                <h3 id="form-title">Create New Template</h3>
                <form id="template-form-data">
                    <input type="hidden" id="template-id" name="id" value="">
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="template-name">Template Name</label></th>
                            <td><input type="text" id="template-name" name="name" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th><label for="template-type">Type</label></th>
                            <td>
                                <select id="template-type" name="type" required>
                                    <option value="">Select Type</option>
                                    <option value="header">Header</option>
                                    <option value="footer">Footer</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="template-typography">Typography</label></th>
                            <td>
                                <!-- Typography Presets Section -->
                                <div style="margin-bottom: 20px; padding: 15px; background: #f8f8f9; border: 1px solid #e1e1e1; border-radius: 5px;">
                                    <h4 style="margin: 0 0 10px 0;">Typography Presets</h4>
                                    <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 10px;">
                                        <select id="typography-preset-selector" style="min-width: 200px;">
                                            <option value="">Choose a preset...</option>
                                            <?php foreach ($typography_presets as $preset): ?>
                                                <option value="<?php echo $preset->id; ?>" 
                                                        data-font-family="<?php echo esc_attr($preset->font_family); ?>"
                                                        data-font-size="<?php echo esc_attr($preset->font_size); ?>"
                                                        data-text-color="<?php echo esc_attr($preset->text_color); ?>">
                                                    <?php echo esc_html($preset->name); ?>
                                                    <?php if ($preset->is_default): ?>
                                                        (Default)
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" class="button button-small" onclick="loadPreset()">Load Preset</button>
                                        <button type="button" class="button button-small" onclick="showSavePresetForm()">Save Current as Preset</button>
                                    </div>
                                    
                                    <!-- Save Preset Form (Hidden by default) -->
                                    <div id="save-preset-form" style="display: none; margin-top: 15px; padding: 15px; background: white; border: 1px solid #ddd; border-radius: 3px;">
                                        <h5>Save Typography Preset</h5>
                                        <div style="margin-bottom: 10px;">
                                            <label for="preset-name">Preset Name:</label>
                                            <input type="text" id="preset-name" placeholder="e.g., Bold Header Style" style="width: 250px; margin-left: 10px;">
                                        </div>
                                        <div style="margin-bottom: 10px;">
                                            <label for="preset-description">Description (optional):</label>
                                            <input type="text" id="preset-description" placeholder="Brief description of this style" style="width: 300px; margin-left: 10px;">
                                        </div>
                                        <div>
                                            <button type="button" class="button button-primary" onclick="savePreset()">Save Preset</button>
                                            <button type="button" class="button" onclick="hideSavePresetForm()">Cancel</button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Manual Typography Controls -->
                                <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                                    <div>
                                        <label for="template-font-family" style="display: block; margin-bottom: 5px; font-weight: 600;">Font Family:</label>
                                        <select id="template-font-family" name="font_family" style="min-width: 180px;">
                                            <option value="">Default</option>
                                            <option value="Arial, sans-serif">Arial</option>
                                            <option value="Helvetica, Arial, sans-serif">Helvetica</option>
                                            <option value="'Times New Roman', Times, serif">Times New Roman</option>
                                            <option value="Georgia, serif">Georgia</option>
                                            <option value="'Courier New', monospace">Courier New</option>
                                            <option value="Verdana, sans-serif">Verdana</option>
                                            <option value="'Trebuchet MS', sans-serif">Trebuchet MS</option>
                                            <option value="'Comic Sans MS', cursive">Comic Sans MS</option>
                                            <option value="Impact, sans-serif">Impact</option>
                                            <option value="'Lucida Console', monospace">Lucida Console</option>
                                            <option value="Tahoma, sans-serif">Tahoma</option>
                                            <option value="'Palatino Linotype', serif">Palatino</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="template-font-size" style="display: block; margin-bottom: 5px; font-weight: 600;">Font Size:</label>
                                        <select id="template-font-size" name="font_size" style="min-width: 120px;">
                                            <option value="">Default</option>
                                            <option value="10px">10px (Tiny)</option>
                                            <option value="12px">12px (Small)</option>
                                            <option value="14px">14px (Normal)</option>
                                            <option value="16px">16px (Medium)</option>
                                            <option value="18px">18px (Large)</option>
                                            <option value="20px">20px (X-Large)</option>
                                            <option value="24px">24px (XX-Large)</option>
                                            <option value="28px">28px (Huge)</option>
                                            <option value="32px">32px (Giant)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="template-text-color" style="display: block; margin-bottom: 5px; font-weight: 600;">Text Color:</label>
                                        <input type="color" id="template-text-color" name="text_color" value="#000000" style="width: 60px; height: 35px; border: 1px solid #ccc; border-radius: 3px;">
                                    </div>
                                </div>
                                <div style="margin-bottom: 15px;">
                                    <button type="button" class="button button-small" onclick="applyTypography()" style="margin-right: 10px;">Apply Typography to Content</button>
                                    <button type="button" class="button button-small" onclick="resetTypography()">Reset to Default</button>
                                </div>
                                <p class="description">Use presets for quick styling, or manually select typography settings and click "Apply Typography" to update your template content.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="template-content">Content</label></th>
                            <td>
                                <?php 
                                wp_editor('', 'template-content', array(
                                    'textarea_name' => 'content',
                                    'media_buttons' => true,
                                    'textarea_rows' => 10,
                                    'teeny' => false,
                                    'dfw' => false,
                                    'tinymce' => array(
                                        'resize' => true,
                                        'wp_autoresize_on' => true,
                                    ),
                                    'quicktags' => true
                                ));
                                ?>
                                <p class="description">Use the rich text editor to create professional email templates with formatting, links, and images.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="template-default">Set as Default</label></th>
                            <td>
                                <input type="checkbox" id="template-default" name="is_default" value="1">
                                <span class="description">Make this the default template for its type</span>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary">Save Template</button>
                        <button type="button" class="button" onclick="hideCreateTemplateForm()">Cancel</button>
                    </p>
                </form>
            </div>

            <!-- Templates List -->
            <div id="templates-list">
                <h3>Existing Templates</h3>
                <div id="templates-container">
                    <?php if (empty($templates)): ?>
                        <p>No templates found. Create your first template above.</p>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                            <?php foreach ($templates as $template): ?>
                                <div class="template-card" style="border: 1px solid #ccd0d4; padding: 15px; border-radius: 5px; background: #fff;">
                                    <h4><?php echo esc_html($template->name); ?>
                                        <?php if ($template->is_default): ?>
                                            <span style="background: #46b450; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; margin-left: 10px;">DEFAULT</span>
                                        <?php endif; ?>
                                    </h4>
                                    <p><strong>Type:</strong> <?php echo ucfirst($template->type); ?></p>
                                    <div style="max-height: 100px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin: 10px 0; background: #f9f9f9;">
                                        <?php echo wp_kses_post($template->content); ?>
                                    </div>
                                    <div class="template-actions">
                                        <button type="button" class="button button-small" onclick="editTemplate(<?php echo $template->id; ?>)">Edit</button>
                                        <button type="button" class="button button-small" onclick="duplicateTemplate(<?php echo $template->id; ?>)">Duplicate</button>
                                        <?php if (!$template->is_default): ?>
                                            <button type="button" class="button button-small" onclick="setDefaultTemplate(<?php echo $template->id; ?>)">Set Default</button>
                                            <button type="button" class="button button-small" style="color: #d63638;" onclick="deleteTemplate(<?php echo $template->id; ?>)">Delete</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
        // Template management JavaScript functions
        function showCreateTemplateForm() {
            document.getElementById('template-form').style.display = 'block';
            document.getElementById('form-title').textContent = 'Create New Template';
            document.getElementById('template-form-data').reset();
            document.getElementById('template-id').value = '';
            
            // Clear WordPress editor content
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('template-content')) {
                tinyMCE.get('template-content').setContent('');
            }
        }

        function hideCreateTemplateForm() {
            document.getElementById('template-form').style.display = 'none';
        }

        function loadTemplates() {
            location.reload(); // Simple refresh for now
        }

        function editTemplate(id) {
            // Fetch template data and populate form
            const data = new FormData();
            data.append('action', 'get_template');
            data.append('nonce', '<?php echo wp_create_nonce("get_template"); ?>');
            data.append('id', id);

            fetch(ajaxurl, {
                method: 'POST',
                body: data
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    const template = result.data.template;
                    document.getElementById('template-id').value = template.id;
                    document.getElementById('template-name').value = template.name;
                    document.getElementById('template-type').value = template.type;
                    
                    // Set content in WordPress editor
                    if (typeof tinyMCE !== 'undefined' && tinyMCE.get('template-content')) {
                        tinyMCE.get('template-content').setContent(template.content);
                    } else {
                        // Fallback to textarea if TinyMCE not available
                        const textarea = document.getElementById('template-content');
                        if (textarea) {
                            textarea.value = template.content;
                        }
                    }
                    
                    document.getElementById('template-default').checked = template.is_default == 1;
                    document.getElementById('form-title').textContent = 'Edit Template';
                    
                    // Load typography settings from template
                    loadTypographyFromTemplate(template);
                    
                    document.getElementById('template-form').style.display = 'block';
                } else {
                    alert('Error loading template: ' + result.data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error);
            });
        }

        function duplicateTemplate(id) {
            const newName = prompt('Enter name for duplicated template:');
            if (!newName) return;

            const data = new FormData();
            data.append('action', 'duplicate_template');
            data.append('nonce', '<?php echo wp_create_nonce("duplicate_template"); ?>');
            data.append('id', id);
            data.append('new_name', newName);

            fetch(ajaxurl, {
                method: 'POST',
                body: data
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('Template duplicated successfully!');
                    loadTemplates();
                } else {
                    alert('Error: ' + result.data.message);
                }
            });
        }

        function setDefaultTemplate(id) {
            if (!confirm('Set this template as default for its type?')) return;

            const data = new FormData();
            data.append('action', 'set_default_header_footer');
            data.append('nonce', '<?php echo wp_create_nonce("set_default_header_footer"); ?>');
            data.append('id', id);

            fetch(ajaxurl, {
                method: 'POST',
                body: data
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('Default template set successfully!');
                    loadTemplates();
                } else {
                    alert('Error: ' + result.data.message);
                }
            });
        }

        function deleteTemplate(id) {
            if (!confirm('Are you sure you want to delete this template?')) return;

            const data = new FormData();
            data.append('action', 'delete_header_footer');
            data.append('nonce', '<?php echo wp_create_nonce("delete_header_footer"); ?>');
            data.append('id', id);

            fetch(ajaxurl, {
                method: 'POST',
                body: data
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('Template deleted successfully!');
                    loadTemplates();
                } else {
                    alert('Error: ' + result.data.message);
                }
            });
        }

        // Handle form submission
        document.getElementById('template-form-data').addEventListener('submit', function(e) {
            e.preventDefault();

            // Sync TinyMCE content to textarea before submission
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('template-content')) {
                tinyMCE.get('template-content').save();
            }

            const formData = new FormData(this);
            formData.append('action', 'save_header_footer');
            formData.append('nonce', '<?php echo wp_create_nonce("save_header_footer"); ?>');

            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('Template saved successfully!');
                    hideCreateTemplateForm();
                    loadTemplates();
                } else {
                    alert('Error: ' + result.data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error);
            });
        });

        // Typography preset functions
        function loadPreset() {
            const selector = document.getElementById('typography-preset-selector');
            const selectedOption = selector.options[selector.selectedIndex];
            
            if (!selectedOption.value) {
                alert('Please select a preset to load.');
                return;
            }
            
            // Load preset values into typography controls
            const fontFamily = selectedOption.getAttribute('data-font-family');
            const fontSize = selectedOption.getAttribute('data-font-size');
            const textColor = selectedOption.getAttribute('data-text-color');
            
            if (fontFamily) document.getElementById('template-font-family').value = fontFamily;
            if (fontSize) document.getElementById('template-font-size').value = fontSize;
            if (textColor) document.getElementById('template-text-color').value = textColor;
            
            // Apply the typography to the content
            applyTypography();
        }

        function showSavePresetForm() {
            document.getElementById('save-preset-form').style.display = 'block';
        }

        function hideSavePresetForm() {
            document.getElementById('save-preset-form').style.display = 'none';
            document.getElementById('preset-name').value = '';
            document.getElementById('preset-description').value = '';
        }

        function savePreset() {
            const presetName = document.getElementById('preset-name').value.trim();
            if (!presetName) {
                alert('Please enter a name for the preset.');
                return;
            }
            
            const fontFamily = document.getElementById('template-font-family').value;
            const fontSize = document.getElementById('template-font-size').value;
            const textColor = document.getElementById('template-text-color').value;
            const description = document.getElementById('preset-description').value.trim();
            
            const data = new FormData();
            data.append('action', 'save_typography_preset');
            data.append('nonce', '<?php echo wp_create_nonce("save_typography_preset"); ?>');
            data.append('name', presetName);
            data.append('font_family', fontFamily);
            data.append('font_size', fontSize);
            data.append('text_color', textColor);
            data.append('description', description);

            fetch(ajaxurl, {
                method: 'POST',
                body: data
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('Typography preset saved successfully!');
                    hideSavePresetForm();
                    // Reload the page to refresh the presets dropdown
                    location.reload();
                } else {
                    alert('Error: ' + result.data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error);
            });
        }

        // Typography application functions
        function applyTypography() {
            const fontFamily = document.getElementById('template-font-family').value;
            const fontSize = document.getElementById('template-font-size').value;
            const textColor = document.getElementById('template-text-color').value;
            
            // Get content from editor
            let content = '';
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('template-content')) {
                content = tinyMCE.get('template-content').getContent();
            } else {
                const textarea = document.getElementById('template-content');
                if (textarea) {
                    content = textarea.value;
                }
            }
            
            if (!content.trim()) {
                alert('Please add some content first before applying typography.');
                return;
            }
            
            // Build CSS styles
            const styles = [];
            if (fontFamily) styles.push('font-family: ' + fontFamily);
            if (fontSize) styles.push('font-size: ' + fontSize);
            if (textColor && textColor !== '#000000') styles.push('color: ' + textColor);
            
            // Apply styles to content
            if (styles.length > 0) {
                const styleString = styles.join('; ');
                const styledContent = '<div style="' + styleString + '">' + content + '</div>';
                
                // Set content back to editor
                if (typeof tinyMCE !== 'undefined' && tinyMCE.get('template-content')) {
                    tinyMCE.get('template-content').setContent(styledContent);
                } else {
                    const textarea = document.getElementById('template-content');
                    if (textarea) {
                        textarea.value = styledContent;
                    }
                }
                
                alert('Typography applied to content successfully!');
            } else {
                alert('Please select typography settings first.');
            }
        }

        function resetTypography() {
            document.getElementById('template-font-family').value = '';
            document.getElementById('template-font-size').value = '';
            document.getElementById('template-text-color').value = '#000000';
            document.getElementById('typography-preset-selector').value = '';
        }

        function loadTypographyFromTemplate(template) {
            // Extract typography settings from template content
            if (!template.content) return;
            
            // Look for font-family in content
            const fontFamilyMatch = template.content.match(/font-family:\s*([^;]+)/i);
            if (fontFamilyMatch) {
                document.getElementById('template-font-family').value = fontFamilyMatch[1].trim();
            }
            
            // Look for font-size in content  
            const fontSizeMatch = template.content.match(/font-size:\s*(\d+px)/i);
            if (fontSizeMatch) {
                document.getElementById('template-font-size').value = fontSizeMatch[1];
            }
            
            // Look for color in content
            const colorMatch = template.content.match(/color:\s*(#[a-fA-F0-9]{6}|#[a-fA-F0-9]{3})/i);
            if (colorMatch) {
                document.getElementById('template-text-color').value = colorMatch[1];
            }
        }
        </script>
        <?php
        
        echo '</div>';
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        // Handle form submission
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['settings_nonce'], 'save_alumni_settings')) {
            $this->save_settings();
            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }
        
        echo '<div class="wrap">';
        echo '<h1>Alumni Bulk Email Settings</h1>';
        
        $this->render_settings_form();
        
        echo '</div>';
    }
    
    /**
     * Render campaign creation form
     */
    private function render_campaign_creation_form() {
        // Get saved lists for dropdown
        $list_manager = new Alumni_List_Manager();
        $saved_lists = $list_manager->get_all_lists();
        
        // Get available templates for header/footer selection
        $header_footer_manager = new Alumni_Header_Footer_Manager();
        $headers = $header_footer_manager->get_all_templates('header');
        $footers = $header_footer_manager->get_all_templates('footer');
        
        // Get typography presets
        $typography_preset_manager = new Alumni_Typography_Preset_Manager();
        $typography_presets = $typography_preset_manager->get_all_presets();
        
        // Get campaign manager for loading saved campaigns
        $file_processor = new Alumni_File_Processor();
        $email_service = new Alumni_Email_Service($file_processor);
        $campaign_manager = new Alumni_Campaign_Manager($email_service, $list_manager);
        
        ?>
        <form id="bulk-email-form" enctype="multipart/form-data">
            <?php wp_nonce_field('send_bulk_email', 'nonce'); ?>
            
            <div class="postbox">
                <h2 class="hndle">Campaign Details</h2>
                <div class="inside">
                    <table class="form-table">
                        <tr>
                            <th><label for="campaign_name">Campaign Name</label></th>
                            <td><input type="text" id="campaign_name" name="campaign_name" class="regular-text" placeholder="e.g., Alumni Newsletter - March 2024" required /></td>
                        </tr>
                        <tr>
                            <th><label for="subject">Subject</label></th>
                            <td><input type="text" id="subject" name="subject" class="regular-text" placeholder="Email subject line" required /></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="postbox">
                <h2 class="hndle">Recipients</h2>
                <div class="inside">
                    <h4>Choose recipient source:</h4>
                    <p>
                        <label>
                            <input type="radio" name="recipients_source" value="saved_list" checked />
                            Use saved recipient list
                        </label>
                    </p>
                    <div id="saved_list_section">
                        <select id="saved_recipients_list" name="saved_list_id">
                            <option value="">Select a saved list</option>
                            <?php foreach ($saved_lists as $list): ?>
                                <option value="<?php echo $list->id; ?>"><?php echo esc_html($list->list_name); ?> (<?php echo $list->total_count; ?> recipients)</option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="preview_saved_list" class="button" style="margin-left: 10px;">Preview List</button>
                    </div>
                    
                    <p>
                        <label>
                            <input type="radio" name="recipients_source" value="upload_csv" />
                            Upload new CSV/Excel file
                        </label>
                    </p>
                    <div id="upload_csv_section" style="display: none;">
                        <input type="file" id="csv_file" name="csv_file" accept=".csv,.xlsx,.xls" />
                        <input type="text" id="new_list_name" name="new_list_name" placeholder="List name (optional)" style="width: 300px; margin-left: 10px;" />
                        <p class="description">
                            CSV/Excel with any column structure. You'll choose the email column in the next step.<br>
                            <button type="button" id="preview_csv" class="button button-small" style="margin-top: 5px;">Preview & Validate</button>
                        </p>
                    </div>
                    
                    <p>
                        <label>
                            <input type="radio" name="recipients_source" value="manual" />
                            Enter recipients manually
                        </label>
                    </p>
                    <div id="manual_recipients_section" style="display: none;">
                        <textarea id="manual_recipients" name="manual_recipients" rows="10" cols="50" placeholder="Enter email addresses, one per line:&#10;john@example.com&#10;Jane Doe &lt;jane@example.com&gt;&#10;bob.smith@example.com"></textarea>
                    </div>
                    
                    <div id="recipient_preview" style="display: none; margin-top: 15px;">
                        <!-- Preview content will be loaded here -->
                    </div>
                    
                    <div id="email_column_selection" style="display: none; margin-top: 15px;">
                        <h4>Select Email Column:</h4>
                        <select id="email_column" name="email_column">
                            <!-- Options populated dynamically -->
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="postbox">
                <h2 class="hndle">Email Content</h2>
                <div class="inside">
                    <h4>Content method:</h4>
                    <p>
                        <label>
                            <input type="radio" name="content_method" value="compose" checked />
                            Compose email content
                        </label>
                    </p>
                    <div id="compose_content_section">
                        <?php 
                        wp_editor('', 'email_content', array(
                            'textarea_name' => 'content',
                            'media_buttons' => true,
                            'textarea_rows' => 15,
                            'teeny' => false,
                            'tinymce' => true
                        )); 
                        ?>
                        <p class="description">
                            <strong>Personalization:</strong> Use {name}, {first_name}, {last_name}, {email} or any custom column name like {graduation_year}
                        </p>
                    </div>
                    
                    <p style="margin-top: 20px;">
                        <label>
                            <input type="radio" name="content_method" value="upload_html" />
                            Upload HTML file
                        </label>
                    </p>
                    <div id="upload_html_section" style="display: none;">
                        <input type="file" id="html_file" name="html_file" accept=".html,.htm" />
                        <button type="button" id="preview_html_file" class="button" style="margin-left: 10px;">Preview HTML</button>
                        <div id="html_preview" style="margin-top: 10px;"></div>
                    </div>
                </div>
            </div>
            
            <div class="postbox">
                <h2 class="hndle">Email Templates</h2>
                <div class="inside">
                    <table class="form-table">
                        <tr>
                            <th><label for="header_template">Header Template</label></th>
                            <td>
                                <select id="header_template" name="header_template">
                                    <option value="">None - No header</option>
                                    <?php foreach ($headers as $header): ?>
                                        <option value="<?php echo $header->id; ?>" <?php echo $header->is_default ? 'selected' : ''; ?>>
                                            <?php echo esc_html($header->name); ?>
                                            <?php if ($header->is_default): ?>
                                                (Default)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="button button-small" onclick="previewTemplate('header', document.getElementById('header_template').value)" style="margin-left: 10px;">Preview</button>
                                <p class="description">Select a header template to add consistent branding to the top of your email</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="footer_template">Footer Template</label></th>
                            <td>
                                <select id="footer_template" name="footer_template">
                                    <option value="">None - No footer</option>
                                    <?php foreach ($footers as $footer): ?>
                                        <option value="<?php echo $footer->id; ?>" <?php echo $footer->is_default ? 'selected' : ''; ?>>
                                            <?php echo esc_html($footer->name); ?>
                                            <?php if ($footer->is_default): ?>
                                                (Default)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="button button-small" onclick="previewTemplate('footer', document.getElementById('footer_template').value)" style="margin-left: 10px;">Preview</button>
                                <p class="description">Select a footer template to add consistent information to the bottom of your email</p>
                            </td>
                        </tr>
                    </table>
                    
                    <!-- Template preview modal -->
                    <div id="template-preview-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 100000;">
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 5px; max-width: 600px; max-height: 80%; overflow-y: auto;">
                            <h3 id="preview-title">Template Preview</h3>
                            <div id="preview-content" style="border: 1px solid #ddd; padding: 15px; background: #f9f9f9;"></div>
                            <p style="margin-top: 15px;">
                                <button type="button" class="button" onclick="closeTemplatePreview()">Close</button>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="postbox">
                <h2 class="hndle">Test & Send</h2>
                <div class="inside">
                    <h4>Test Email</h4>
                    <p>
                        <input type="email" id="test_email_address" placeholder="your.email@example.com" style="width: 250px;" />
                        <button type="button" id="send_test_email" class="button">Send Test Email</button>
                        <span id="test_email_status" style="margin-left: 10px;"></span>
                    </p>
                    
                    <h4 style="margin-top: 30px;">Campaign Management</h4>
                    <p>
                        <strong>Load Saved Campaign:</strong><br>
                        <?php
                        $all_campaigns = $campaign_manager->get_all_campaigns();
                        $saved_campaigns = array_filter($all_campaigns, function($campaign) {
                            return $campaign->status === 'draft';
                        });
                        if (!empty($saved_campaigns)): ?>
                            <select id="load_campaign_select" style="min-width: 250px;">
                                <option value="">Choose a saved campaign...</option>
                                <?php foreach ($saved_campaigns as $campaign): ?>
                                    <option value="<?php echo $campaign->id; ?>"><?php echo esc_html($campaign->campaign_name); ?> (<?php echo date('M j, Y', strtotime($campaign->created_at)); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" id="load_campaign" class="button">Load Campaign</button>
                        <?php else: ?>
                            <em>No saved campaigns found.</em>
                        <?php endif; ?>
                    </p>
                    
                    <h4 style="margin-top: 20px;">Campaign Actions</h4>
                    <p>
                        <button type="button" id="save_campaign" class="button button-secondary">Save Campaign</button>
                        <button type="submit" id="send_campaign" class="button button-primary">Send Campaign Now</button>
                    </p>
                </div>
            </div>
        </form>
        
        <?php $this->render_campaign_javascript(); ?>
        <?php
    }
    
    /**
     * Render JavaScript for campaign form
     */
    private function render_campaign_javascript() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Form interactions
            $('input[name="recipients_source"]').change(function() {
                $('.recipients-section').hide();
                if ($(this).val() === 'saved_list') {
                    $('#saved_list_section').show();
                } else if ($(this).val() === 'upload_csv') {
                    $('#upload_csv_section').show();
                } else if ($(this).val() === 'manual') {
                    $('#manual_recipients_section').show();
                }
            });
            
            $('input[name="content_method"]').change(function() {
                if ($(this).val() === 'compose') {
                    $('#compose_content_section').show();
                    $('#upload_html_section').hide();
                } else {
                    $('#compose_content_section').hide();
                    $('#upload_html_section').show();
                }
            });
            
            // Test email
            $('#send_test_email').click(function() {
                var testEmail = $('#test_email_address').val();
                var subject = $('#subject').val();
                var content = '';
                
                // Get content from the selected method
                if ($('input[name="content_method"]:checked').val() === 'compose') {
                    // Get content from TinyMCE editor
                    if (typeof tinyMCE !== 'undefined' && tinyMCE.get('email_content')) {
                        content = tinyMCE.get('email_content').getContent();
                    } else {
                        // Fallback to textarea if TinyMCE not available
                        content = $('#email_content').val();
                    }
                } else {
                    // For uploaded HTML files, get the file content
                    var htmlFile = document.getElementById('html_file');
                    if (htmlFile && htmlFile.files.length > 0) {
                        var reader = new FileReader();
                        reader.onload = function(e) {
                            content = e.target.result;
                            sendTestEmail(testEmail, subject, content);
                        };
                        reader.readAsText(htmlFile.files[0]);
                        return; // Exit here, sendTestEmail will be called from FileReader
                    } else {
                        alert('Please select an HTML file first');
                        return;
                    }
                }
                
                sendTestEmail(testEmail, subject, content);
            });
            
            function sendTestEmail(testEmail, subject, content) {
                if (!testEmail || !subject || !content) {
                    alert('Please fill in test email, subject, and content');
                    return;
                }
                
                var button = $('#send_test_email');
                button.prop('disabled', true).text('Sending...');
                
                $.post(ajaxurl, {
                    action: 'send_test_email',
                    nonce: '<?php echo wp_create_nonce('send_test_email'); ?>',
                    test_email: testEmail,
                    subject: subject,
                    content: content,
                    header_template: $('#header_template').val(),
                    footer_template: $('#footer_template').val()
                }).done(function(response) {
                    $('#test_email_status').text(response.data.message)
                        .css('color', response.success ? 'green' : 'red');
                }).always(function() {
                    button.prop('disabled', false).text('Send Test Email');
                });
            }
            
            // Preview HTML file
            $('#preview_html_file').click(function() {
                var htmlFile = document.getElementById('html_file');
                if (htmlFile && htmlFile.files.length > 0) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        var htmlContent = e.target.result;
                        $('#html_preview').html('<h4>HTML Preview:</h4><div style="border: 1px solid #ddd; padding: 10px; max-height: 400px; overflow-y: auto; background: white;">' + htmlContent + '</div>');
                    };
                    reader.readAsText(htmlFile.files[0]);
                } else {
                    alert('Please select an HTML file first');
                }
            });
            
            // Save Campaign
            $('#save_campaign').click(function() {
                var campaignName = $('#campaign_name').val();
                var subject = $('#subject').val();
                var content = '';
                
                // Get content from TinyMCE editor
                if (typeof tinyMCE !== 'undefined' && tinyMCE.get('email_content')) {
                    content = tinyMCE.get('email_content').getContent();
                } else {
                    content = $('#email_content').val();
                }
                
                if (!campaignName || !subject || !content) {
                    alert('Please fill in campaign name, subject, and content before saving');
                    return;
                }
                
                var button = $(this);
                button.prop('disabled', true).text('Saving...');
                
                $.post(ajaxurl, {
                    action: 'save_campaign',
                    nonce: '<?php echo wp_create_nonce('save_campaign'); ?>',
                    campaign_name: campaignName,
                    subject: subject,
                    content: content
                }).done(function(response) {
                    if (response.success) {
                        alert('Campaign saved successfully!');
                        // Reload the page to refresh the load campaign dropdown
                        window.location.reload();
                    } else {
                        alert('Error saving campaign: ' + response.data.message);
                    }
                }).always(function() {
                    button.prop('disabled', false).text('Save Campaign');
                });
            });
            
            // Load Campaign
            $('#load_campaign').click(function() {
                var campaignId = $('#load_campaign_select').val();
                if (!campaignId) {
                    alert('Please select a campaign to load');
                    return;
                }
                
                if (!confirm('Loading a campaign will replace your current form data. Continue?')) {
                    return;
                }
                
                var button = $(this);
                button.prop('disabled', true).text('Loading...');
                
                $.post(ajaxurl, {
                    action: 'load_campaign',
                    nonce: '<?php echo wp_create_nonce('load_campaign'); ?>',
                    campaign_id: campaignId
                }).done(function(response) {
                    if (response.success) {
                        var campaign = response.data.campaign;
                        
                        // Populate form fields
                        $('#campaign_name').val(campaign.campaign_name);
                        $('#subject').val(campaign.subject);
                        
                        // Set content in TinyMCE editor
                        if (typeof tinyMCE !== 'undefined' && tinyMCE.get('email_content')) {
                            tinyMCE.get('email_content').setContent(campaign.content);
                        } else {
                            $('#email_content').val(campaign.content);
                        }
                        
                        alert('Campaign loaded successfully!');
                    } else {
                        alert('Error loading campaign: ' + response.data.message);
                    }
                }).always(function() {
                    button.prop('disabled', false).text('Load Campaign');
                });
            });
            
            // Form submission
            $('#bulk-email-form').submit(function(e) {
                e.preventDefault();
                
                if (!confirm('Are you sure you want to send this campaign?')) {
                    return;
                }
                
                // Sync TinyMCE content before submitting
                if (typeof tinyMCE !== 'undefined' && tinyMCE.get('email_content')) {
                    tinyMCE.get('email_content').save();
                }
                
                var formData = new FormData(this);
                formData.append('action', 'send_bulk_email');
                
                $('#send_campaign').prop('disabled', true).text('Sending...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false
                }).done(function(response) {
                    alert(response.data.message);
                    if (response.success) {
                        window.location.reload();
                    }
                }).always(function() {
                    $('#send_campaign').prop('disabled', false).text('Send Campaign Now');
                });
            });
        });
        
        // Template preview functions
        function previewTemplate(type, templateId) {
            if (!templateId) {
                alert('Please select a template to preview');
                return;
            }
            
            var data = new FormData();
            data.append('action', 'get_template');
            data.append('nonce', '<?php echo wp_create_nonce("get_template"); ?>');
            data.append('id', templateId);

            fetch(ajaxurl, {
                method: 'POST',
                body: data
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    const template = result.data.template;
                    document.getElementById('preview-title').textContent = template.name + ' (' + template.type + ')';
                    document.getElementById('preview-content').innerHTML = template.content;
                    document.getElementById('template-preview-modal').style.display = 'block';
                } else {
                    alert('Error loading template: ' + result.data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error);
            });
        }
        
        function closeTemplatePreview() {
            document.getElementById('template-preview-modal').style.display = 'none';
        }
        
        // Close modal when clicking background
        document.getElementById('template-preview-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeTemplatePreview();
            }
        });
        
        // Typography functions
        function applyTypography() {
            const fontFamily = document.getElementById('template-font-family').value;
            const fontSize = document.getElementById('template-font-size').value;
            const textColor = document.getElementById('template-text-color').value;
            
            // Get current content
            let currentContent = '';
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('template-content')) {
                currentContent = tinyMCE.get('template-content').getContent();
            } else {
                const textarea = document.getElementById('template-content');
                if (textarea) {
                    currentContent = textarea.value;
                }
            }
            
            // If no content, create a basic template structure
            if (!currentContent || currentContent.trim() === '') {
                const templateType = document.getElementById('template-type').value;
                if (templateType === 'header') {
                    currentContent = '<h1>Your Header Title</h1><p>Add your header content here...</p>';
                } else if (templateType === 'footer') {
                    currentContent = '<p>Best regards,<br>Your Organization</p><p>Contact information and links</p>';
                } else {
                    currentContent = '<p>Your template content here...</p>';
                }
            }
            
            // Build CSS styles
            let styles = [];
            if (fontFamily) styles.push('font-family: ' + fontFamily);
            if (fontSize) styles.push('font-size: ' + fontSize);
            if (textColor && textColor !== '#000000') styles.push('color: ' + textColor);
            
            if (styles.length > 0) {
                const styleString = styles.join('; ');
                
                // Wrap content in a div with the styles
                const styledContent = '<div style="' + styleString + '">' + currentContent + '</div>';
                
                // Set the styled content back
                if (typeof tinyMCE !== 'undefined' && tinyMCE.get('template-content')) {
                    tinyMCE.get('template-content').setContent(styledContent);
                } else {
                    const textarea = document.getElementById('template-content');
                    if (textarea) {
                        textarea.value = styledContent;
                    }
                }
                
                alert('Typography applied successfully!');
            } else {
                alert('Please select at least one typography option.');
            }
        }
        
        function resetTypography() {
            document.getElementById('template-font-family').value = '';
            document.getElementById('template-font-size').value = '';
            document.getElementById('template-text-color').value = '#000000';
            
            alert('Typography settings reset to default.');
        }
        
        // Load typography settings when editing existing template
        function loadTypographyFromTemplate(template) {
            // Try to extract existing styles from template content
            const content = template.content;
            
            // Look for font-family in style attributes
            const fontFamilyMatch = content.match(/font-family:\s*([^;]+)/i);
            if (fontFamilyMatch) {
                const fontFamily = fontFamilyMatch[1].trim().replace(/['"]/g, '');
                const fontSelect = document.getElementById('template-font-family');
                for (let option of fontSelect.options) {
                    if (option.value.includes(fontFamily) || fontFamily.includes(option.text)) {
                        fontSelect.value = option.value;
                        break;
                    }
                }
            }
            
            // Look for font-size in style attributes
            const fontSizeMatch = content.match(/font-size:\s*(\d+px)/i);
            if (fontSizeMatch) {
                document.getElementById('template-font-size').value = fontSizeMatch[1];
            }
            
            // Look for color in style attributes
            const colorMatch = content.match(/color:\s*(#[a-fA-F0-9]{6}|#[a-fA-F0-9]{3}|rgb\([^)]+\))/i);
            if (colorMatch) {
                let color = colorMatch[1];
                // Convert to hex if needed
                if (color.startsWith('rgb')) {
                    // Simple conversion for basic colors - full implementation would need more logic
                    color = '#000000';
                }
                document.getElementById('template-text-color').value = color;
            }
        }
        </script>
        <?php
    }
    
    /**
     * Render email lists table
     */
    private function render_email_lists_table($lists) {
        ?>
        <div class="tablenav top">
            <div class="alignleft actions">
                <button class="button action" onclick="location.href='admin.php?page=alumni-bulk-email'">Create New Campaign</button>
            </div>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>List Name</th>
                    <th>Recipients</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lists)): ?>
                    <tr>
                        <td colspan="4">No email lists found. <a href="admin.php?page=alumni-bulk-email">Create your first campaign</a> to start building lists.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($lists as $list): ?>
                        <tr>
                            <td><strong><?php echo esc_html($list->list_name); ?></strong></td>
                            <td><?php echo number_format($list->total_count); ?></td>
                            <td><?php echo date('M j, Y g:i A', strtotime($list->created_at)); ?></td>
                            <td>
                                <button class="button view-list" data-list-id="<?php echo $list->id; ?>">View</button>
                                <button class="button export-list" data-list-id="<?php echo $list->id; ?>">Export</button>
                                <button class="button button-link-delete delete-list" data-list-id="<?php echo $list->id; ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Render campaigns table
     */
    private function render_campaigns_table($campaigns) {
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Campaign Name</th>
                    <th>Subject</th>
                    <th>Recipients</th>
                    <th>Status</th>
                    <th>Sent</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($campaigns)): ?>
                    <tr>
                        <td colspan="6">No campaigns found. <a href="admin.php?page=alumni-bulk-email">Create your first campaign</a>.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($campaigns as $campaign): ?>
                        <tr>
                            <td><strong><?php echo esc_html($campaign->campaign_name); ?></strong></td>
                            <td><?php echo esc_html($campaign->subject); ?></td>
                            <td><?php echo number_format($campaign->total_recipients); ?></td>
                            <td><span class="status-<?php echo $campaign->status; ?>"><?php echo ucfirst($campaign->status); ?></span></td>
                            <td><?php echo $campaign->sent_at ? date('M j, Y g:i A', strtotime($campaign->sent_at)) : '-'; ?></td>
                            <td>
                                <button class="button view-campaign" data-campaign-id="<?php echo $campaign->id; ?>">View</button>
                                <button class="button copy-campaign" data-campaign-id="<?php echo $campaign->id; ?>">Copy</button>
                                <button class="button button-link-delete delete-campaign" data-campaign-id="<?php echo $campaign->id; ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Render email logs table
     */
    private function render_email_logs_table($logs) {
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Campaign</th>
                    <th>Recipient</th>
                    <th>Status</th>
                    <th>Sent</th>
                    <th>Opened</th>
                    <th>Clicked</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6">No email logs found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo esc_html($log->campaign_name ?: 'Campaign #' . $log->campaign_id); ?></td>
                            <td>
                                <?php echo esc_html($log->recipient_name ?: $log->recipient_email); ?>
                                <br><small><?php echo esc_html($log->recipient_email); ?></small>
                            </td>
                            <td><span class="status-<?php echo $log->status; ?>"><?php echo ucfirst($log->status); ?></span></td>
                            <td><?php echo $log->sent_at ? date('M j g:i A', strtotime($log->sent_at)) : '-'; ?></td>
                            <td><?php echo $log->opened_at ? date('M j g:i A', strtotime($log->opened_at)) : '-'; ?></td>
                            <td><?php echo $log->clicked_at ? date('M j g:i A', strtotime($log->clicked_at)) : '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Render settings form
     */
    private function render_settings_form() {
        $mailgun_api_key = get_option('alumni_bulk_email_mailgun_api_key', '');
        $mailgun_domain = get_option('alumni_bulk_email_mailgun_domain', '');
        $from_email = get_option('alumni_bulk_email_from_email', '');
        $from_name = get_option('alumni_bulk_email_from_name', '');
        
        // Debug: Log current settings for troubleshooting
        error_log('Alumni Bulk Email - Current settings loaded: API Key=' . (!empty($mailgun_api_key) ? 'SET' : 'EMPTY') . 
                  ', Domain=' . (!empty($mailgun_domain) ? 'SET' : 'EMPTY') . 
                  ', From Email=' . (!empty($from_email) ? 'SET' : 'EMPTY') . 
                  ', From Name=' . (!empty($from_name) ? 'SET' : 'EMPTY'));
        
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('save_alumni_settings', 'settings_nonce'); ?>
            
            <div class="postbox">
                <h2 class="hndle">Mailgun Configuration</h2>
                <div class="inside">
                    <table class="form-table">
                        <tr>
                            <th><label for="mailgun_api_key">Mailgun API Key</label></th>
                            <td>
                                <input type="password" id="mailgun_api_key" name="mailgun_api_key" value="<?php echo esc_attr($mailgun_api_key); ?>" class="regular-text" />
                                <p class="description">Your Mailgun private API key (starts with "key-")</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="mailgun_domain">Mailgun Domain</label></th>
                            <td>
                                <input type="text" id="mailgun_domain" name="mailgun_domain" value="<?php echo esc_attr($mailgun_domain); ?>" class="regular-text" />
                                <p class="description">Your verified Mailgun domain (e.g., mail.yourdomain.com)</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="from_email">From Email Address</label></th>
                            <td>
                                <input type="email" id="from_email" name="from_email" value="<?php echo esc_attr($from_email); ?>" class="regular-text" />
                                <p class="description">The email address campaigns will be sent from</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="from_name">From Name</label></th>
                            <td>
                                <input type="text" id="from_name" name="from_name" value="<?php echo esc_attr($from_name); ?>" class="regular-text" />
                                <p class="description">The name that will appear in the "From" field</p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button-primary" value="Save Settings" />
            </p>
        </form>
        
        <div class="postbox">
            <h2 class="hndle">Mailgun Connection Test</h2>
            <div class="inside">
                <p>Test your Mailgun configuration by sending a test email to yourself:</p>
                <table class="form-table">
                    <tr>
                        <th><label for="test_mailgun_email">Test Email Address</label></th>
                        <td>
                            <input type="email" id="test_mailgun_email" placeholder="your.email@example.com" class="regular-text" />
                            <button type="button" id="test_mailgun_connection" class="button">Test Mailgun Connection</button>
                        </td>
                    </tr>
                </table>
                <div id="mailgun_test_result" style="margin-top: 10px;"></div>
            </div>
        </div>
        
        <div class="postbox">
            <h2 class="hndle">Settings Verification</h2>
            <div class="inside">
                <p>Current settings status:</p>
                <table class="form-table">
                    <tr>
                        <th>Mailgun API Key</th>
                        <td><?php echo !empty($mailgun_api_key) ? '<span style="color: green;">✓ Set</span>' : '<span style="color: red;">✗ Empty</span>'; ?></td>
                    </tr>
                    <tr>
                        <th>Mailgun Domain</th>
                        <td><?php echo !empty($mailgun_domain) ? '<span style="color: green;">✓ Set</span>' : '<span style="color: red;">✗ Empty</span>'; ?></td>
                    </tr>
                    <tr>
                        <th>From Email</th>
                        <td><?php echo !empty($from_email) ? '<span style="color: green;">✓ Set (' . esc_html($from_email) . ')</span>' : '<span style="color: red;">✗ Empty</span>'; ?></td>
                    </tr>
                    <tr>
                        <th>From Name</th>
                        <td><?php echo !empty($from_name) ? '<span style="color: green;">✓ Set (' . esc_html($from_name) . ')</span>' : '<span style="color: red;">✗ Empty</span>'; ?></td>
                    </tr>
                </table>
                <p class="description">Check your error logs for detailed troubleshooting information.</p>
            </div>
        </div>
        
        <div class="postbox">
            <h2 class="hndle">Database Management</h2>
            <div class="inside">
                <p>If you're experiencing database issues, you can recreate the plugin tables:</p>
                <button type="button" id="recreate-tables" class="button button-secondary">Recreate Database Tables</button>
                <p class="description"><strong>Warning:</strong> This will not delete existing data, but will ensure all tables exist with the correct structure.</p>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#test_mailgun_connection').click(function() {
                var testEmail = $('#test_mailgun_email').val();
                if (!testEmail) {
                    alert('Please enter an email address for testing');
                    return;
                }
                
                var button = $(this);
                button.prop('disabled', true).text('Testing...');
                
                $.post(ajaxurl, {
                    action: 'test_mailgun_connection',
                    nonce: '<?php echo wp_create_nonce('test_mailgun_connection'); ?>',
                    test_email: testEmail
                }).done(function(response) {
                    $('#mailgun_test_result').html(
                        '<div class="notice notice-' + (response.success ? 'success' : 'error') + '"><p>' +
                        response.data.message + '</p></div>'
                    );
                }).fail(function() {
                    $('#mailgun_test_result').html(
                        '<div class="notice notice-error"><p>Failed to test Mailgun connection</p></div>'
                    );
                }).always(function() {
                    button.prop('disabled', false).text('Test Mailgun Connection');
                });
            });
            
            $('#recreate-tables').click(function() {
                if (!confirm('Are you sure you want to recreate the database tables?')) {
                    return;
                }
                
                var button = $(this);
                button.prop('disabled', true).text('Processing...');
                
                $.post(ajaxurl, {
                    action: 'recreate_tables',
                    nonce: '<?php echo wp_create_nonce('recreate_tables'); ?>'
                }).done(function(response) {
                    alert(response.data.message);
                }).always(function() {
                    button.prop('disabled', false).text('Recreate Database Tables');
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        $api_key = sanitize_text_field($_POST['mailgun_api_key']);
        $domain = sanitize_text_field($_POST['mailgun_domain']);
        $from_email = sanitize_email($_POST['from_email']);
        $from_name = sanitize_text_field($_POST['from_name']);
        
        update_option('alumni_bulk_email_mailgun_api_key', $api_key);
        update_option('alumni_bulk_email_mailgun_domain', $domain);
        update_option('alumni_bulk_email_from_email', $from_email);
        update_option('alumni_bulk_email_from_name', $from_name);
        
        // Debug: Log settings save
        error_log('Alumni Bulk Email - Settings saved: API Key=' . (!empty($api_key) ? 'SET' : 'EMPTY') . 
                  ', Domain=' . (!empty($domain) ? 'SET' : 'EMPTY') . 
                  ', From Email=' . (!empty($from_email) ? 'SET' : 'EMPTY') . 
                  ', From Name=' . (!empty($from_name) ? 'SET' : 'EMPTY'));
    }
    
    /**
     * Initialize update checker
     */
    private function init_update_checker() {
        if (class_exists('Puc_v4_Factory')) {
            $updateChecker = Puc_v4_Factory::buildUpdateChecker(
                'https://github.com/' . ALUMNI_BULK_EMAIL_GITHUB_REPO . '/',
                __FILE__,
                'alumni-bulk-email'
            );
        }
    }
}

// Initialize plugin
new AlumniBulkEmail();

// Activation and deactivation hooks
register_activation_hook(__FILE__, function() {
    Alumni_Database::create_tables();
    
    // Create default typography presets
    $typography_preset_manager = new Alumni_Typography_Preset_Manager();
    $typography_preset_manager->create_default_presets();
});

register_deactivation_hook(__FILE__, function() {
    // Clean up any temporary data if needed
});