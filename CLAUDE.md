# CLAUDE.md - Alumni Bulk Email Plugin Technical Documentation

This file contains technical details, dependencies, architecture information, and development notes for the Alumni Bulk Email WordPress plugin. This information is intended for developers, system administrators, and AI assistants working with this codebase.

## 🏗️ Plugin Architecture

### Core Plugin Structure
```
alumni-bulk-email-plugin/
├── alumni-bulk-email.php          # Main plugin file (5,400+ lines)
├── composer.json                  # PHP dependencies
├── composer.lock                  # Locked dependency versions
├── vendor/                        # Composer dependencies
├── README.md                      # User documentation
└── CLAUDE.md                      # Technical documentation (this file)
```

### Main Plugin File (`alumni-bulk-email.php`)
- **Single-file architecture** for simplicity and portability
- **WordPress plugin standards** compliance
- **Object-oriented design** with the `AlumniBulkEmail` class
- **Hook-based integration** with WordPress

## 📦 Dependencies & Requirements

### PHP Dependencies (Composer)
```json
{
  "require": {
    "yahnis-elsts/plugin-update-checker": "^5.0",
    "phpoffice/phpspreadsheet": "^1.29"
  }
}
```

#### PHPSpreadsheet (^1.29)
- **Purpose**: Excel file import/export functionality
- **Size**: ~50MB installed
- **Dependencies**: Multiple sub-packages for Excel processing
- **Usage**: Handles .xlsx and .xls file formats
- **Location**: Loaded via Composer autoloader

#### Plugin Update Checker (^5.0)
- **Purpose**: Automatic plugin updates from GitHub
- **Author**: Yahnis Elsts
- **Functionality**: Checks GitHub releases for new versions
- **Integration**: Seamless WordPress update notifications

### System Requirements
- **WordPress**: 5.0+ (tested up to 6.4)
- **PHP**: 7.4+ (recommended 8.0+)
- **MySQL**: 5.6+ (for WordPress compatibility)
- **PHP Extensions Required**:
  - `json` (for recipient data storage)
  - `curl` (for Mailgun API calls)
  - `openssl` (for secure API communications)
  - `zip` (for PHPSpreadsheet Excel processing)
  - `xml` (for PHPSpreadsheet Excel processing)
  - `gd` or `imagick` (optional, for image processing in emails)

### External Service Dependencies
- **Mailgun API**: Email delivery service
  - API endpoint: `https://api.mailgun.net/v3/`
  - Webhook support required
  - Custom domain configuration recommended

## 🗄️ Database Schema

### Tables Created
```sql
-- Recipient Lists (main data storage)
CREATE TABLE wp_alumni_recipient_lists (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    list_name varchar(255) NOT NULL,
    description text,
    recipients_data longtext NOT NULL,  -- JSON formatted
    total_count int DEFAULT 0,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY list_name (list_name)
);

-- Email Campaigns
CREATE TABLE wp_alumni_email_campaigns (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    campaign_name varchar(255) NOT NULL,
    subject varchar(255) NOT NULL,
    content longtext NOT NULL,
    recipients_data longtext,  -- JSON formatted
    sent_at datetime,
    total_recipients int DEFAULT 0,
    status varchar(50) DEFAULT 'draft',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY campaign_name (campaign_name),
    KEY status (status),
    KEY sent_at (sent_at)
);

-- Email Sending Logs
CREATE TABLE wp_alumni_email_logs (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    campaign_id mediumint(9),
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
);

-- Unsubscribe Management
CREATE TABLE wp_alumni_email_unsubscribes (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    email varchar(255) NOT NULL,
    unsubscribe_token varchar(255) NOT NULL,
    unsubscribed_at datetime DEFAULT CURRENT_TIMESTAMP,
    ip_address varchar(45),
    user_agent text,
    PRIMARY KEY (id),
    UNIQUE KEY email (email),
    UNIQUE KEY unsubscribe_token (unsubscribe_token)
);

-- Headers and Footers Templates
CREATE TABLE wp_alumni_headers_footers (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    type enum('header', 'footer') NOT NULL,
    content longtext NOT NULL,
    is_default tinyint(1) DEFAULT 0,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY name (name),
    KEY type (type),
    KEY is_default (is_default)
);
```

### Data Storage Patterns

#### JSON Storage for Recipients
Recipients are stored as JSON in the `recipients_data` column:
```json
[
  {
    "email": "john@example.com",
    "name": "John Smith",
    "first_name": "John",
    "last_name": "Smith",
    "graduation_year": "1995",
    "major": "Computer Science",
    "tags": "Alumni Newsletter (2024-03-15), Class Reunion (2024-06-01)",
    // ... any additional custom columns
  }
]
```

**Benefits:**
- **Unlimited columns**: No schema changes needed for new fields
- **Flexible structure**: Each list can have different columns
- **Performance**: Single query retrieves complete recipient data
- **Maintainability**: Easy to add features without migrations

## 🔧 Core Functionality

### File Processing Architecture

#### CSV Processing (`parse_csv_file()`)
- **Library**: Native PHP `fgetcsv()`
- **Memory efficient**: Streams large files
- **Error handling**: Graceful failure with logging
- **Character encoding**: UTF-8 support

#### Excel Processing (`parse_excel_file()`)
- **Library**: PHPSpreadsheet
- **Formats supported**: .xlsx, .xls
- **Memory usage**: Loads entire file into memory
- **Error handling**: Exception-based with detailed messages

#### Unified Processing (`parse_file()`)
- **Auto-detection**: File extension-based format detection
- **Fallback logic**: Defaults to CSV if detection fails
- **Error propagation**: Consistent error handling across formats

### Email Delivery Architecture

#### Mailgun Integration
- **API Method**: REST API calls via `wp_remote_post()`
- **Authentication**: API key-based
- **Rate limiting**: Built-in delays between sends
- **Error handling**: Comprehensive logging and retry logic

#### Webhook Processing (`handle_mailgun_webhook()`)
- **Security**: Signature verification
- **Events handled**: delivered, failed, bounced, opened, clicked
- **Database updates**: Real-time status updates
- **Logging**: Detailed webhook event logging

### Security Implementation

#### Input Sanitization
- **WordPress functions**: `sanitize_text_field()`, `sanitize_textarea_field()`
- **File uploads**: `wp_handle_upload()` with type validation
- **SQL queries**: Prepared statements throughout

#### Permission Checks
- **Capability required**: `edit_posts` for most operations
- **Admin operations**: `manage_options` for settings
- **Nonce verification**: All AJAX requests protected

#### Unsubscribe Security
- **Token-based**: Unique tokens prevent unauthorized unsubscribes
- **Time-based validation**: Optional token expiration
- **IP logging**: Audit trail for unsubscribe actions

## 🔄 AJAX Architecture

### AJAX Handlers
```php
// Core handlers registered in constructor
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
add_action('wp_ajax_create_sublist_from_results', array($this, 'handle_create_sublist_from_results'));
add_action('wp_ajax_save_header_footer', array($this, 'handle_save_header_footer'));
add_action('wp_ajax_delete_header_footer', array($this, 'handle_delete_header_footer'));
add_action('wp_ajax_set_default_header_footer', array($this, 'handle_set_default_header_footer'));
add_action('wp_ajax_recreate_tables', array($this, 'handle_recreate_tables'));

// Public handlers (no authentication required)
add_action('wp_ajax_nopriv_handle_alumni_webhook', array($this, 'handle_mailgun_webhook'));
add_action('wp_ajax_nopriv_handle_unsubscribe', array($this, 'handle_unsubscribe'));
```

### Response Format
All AJAX handlers return consistent JSON responses:
```json
{
  "success": true|false,
  "data": {
    "message": "Human readable message",
    "additional_data": "Any additional response data"
  }
}
```

## 🧪 Testing & Quality Assurance

### Error Logging
Comprehensive logging throughout the application:
```php
error_log('Alumni Bulk Email - Operation: ' . $details);
```

### Development Tools

#### WordPress Debug Mode
```php
// wp-config.php settings for development
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

#### Useful Commands
```bash
# Install dependencies
composer install

# Update dependencies
composer update

# Check WordPress coding standards (if PHPCS installed)
phpcs --standard=WordPress alumni-bulk-email.php

# Check PHP syntax
php -l alumni-bulk-email.php
```

## 🚀 Performance Considerations

### Database Optimization
- **Indexed columns**: All frequently queried columns have indexes
- **JSON storage**: Reduces table complexity but limits SQL querying
- **Pagination**: Large recipient lists are paginated in the UI

### Memory Management
- **CSV streaming**: Large CSV files processed line by line
- **Excel limitations**: Large Excel files may cause memory issues
- **Batch processing**: Email sending in configurable batches

### Caching
- **WordPress transients**: Used for temporary data storage
- **Static variables**: Prevent repeated database queries within requests

## 🔄 Update Mechanism

### Automatic Updates
The plugin includes automatic update checking via the Plugin Update Checker library:

```php
// Update checker initialization
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
    $updateChecker = Puc_v4_Factory::buildUpdateChecker(
        'https://github.com/mattbaya/alumni-bulk-email-plugin/',
        __FILE__,
        'alumni-bulk-email'
    );
}
```

### Version Management
- **Semantic versioning**: MAJOR.MINOR.PATCH format
- **GitHub releases**: Tagged releases trigger update notifications
- **Changelog**: Maintained for user visibility

## 🔧 Configuration Management

### WordPress Options
Settings stored in `wp_options` table:
- `alumni_bulk_email_mailgun_api_key`
- `alumni_bulk_email_mailgun_domain`
- `alumni_bulk_email_from_email`
- `alumni_bulk_email_from_name`
- `alumni_bulk_email_smtp_*` (SMTP settings)

### Constants
```php
define('ALUMNI_BULK_EMAIL_VERSION', '0.5.0');
define('ALUMNI_BULK_EMAIL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ALUMNI_BULK_EMAIL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ALUMNI_BULK_EMAIL_GITHUB_REPO', 'mattbaya/alumni-bulk-email-plugin');
```

## 🐛 Known Issues & Limitations

### Current Limitations
1. **Excel file size**: Large Excel files may cause memory issues
2. **Webhook delays**: Mailgun webhooks may have delays in processing
3. **Single-file architecture**: All code in one file (design choice for simplicity)
4. **No built-in scheduling**: Campaigns must be sent immediately

### Potential Improvements
1. **Background processing**: Use WordPress cron for large campaigns
2. **File chunking**: Process large files in chunks
3. **Caching layer**: Add Redis/Memcached support
4. **Multi-file architecture**: Split into multiple files for better organization

## 🛠️ Development Guidelines

### Code Standards
- **WordPress Coding Standards**: Follow WordPress PHP coding standards
- **Security First**: Sanitize all inputs, validate all outputs
- **Error Handling**: Comprehensive error logging and user feedback
- **Documentation**: Inline comments for complex logic

### Git Workflow
```bash
# Development workflow
git checkout -b feature/new-feature
# Make changes
git add .
git commit -m "Add new feature with detailed description"
git push origin feature/new-feature
# Create pull request

# Release workflow
git tag v1.0.0
git push --tags
# Create GitHub release
```

### Testing Checklist
- [ ] Test with various CSV/Excel formats
- [ ] Test email sending with different recipient counts
- [ ] Test webhook processing with Mailgun events
- [ ] Test unsubscribe functionality
- [ ] Test export functionality with filtering
- [ ] Test bulk operations with large datasets
- [ ] Test update mechanism from GitHub

## 📞 Support & Maintenance

### Monitoring
- **WordPress error logs**: Monitor for PHP errors
- **Mailgun dashboard**: Monitor delivery rates and bounces
- **Database performance**: Monitor query performance
- **User feedback**: GitHub issues and support requests

### Regular Maintenance
- **Dependency updates**: Keep Composer packages updated
- **Security patches**: Monitor for WordPress security updates
- **Database cleanup**: Periodic cleanup of old logs
- **Performance optimization**: Regular performance reviews

## 🔗 External Integrations

### Mailgun API Endpoints Used
- `POST /v3/{domain}/messages` - Send emails
- `GET /v3/{domain}/events` - Retrieve events (if needed)
- Webhook endpoint for real-time event processing

### WordPress Hooks Utilized
- `admin_menu` - Add admin menu pages
- `wp_ajax_*` - AJAX request handling
- `init` - Plugin initialization
- `wp_enqueue_scripts` - Load admin assets

---

## 📝 Notes for AI Assistants

When working with this codebase:

1. **File Structure**: Everything is in `alumni-bulk-email.php` - it's intentionally a single-file plugin
2. **Database**: Uses JSON storage for recipient data - very flexible but requires careful handling
3. **Dependencies**: PHPSpreadsheet is large but essential for Excel support
4. **Security**: Always verify nonces and capabilities before making changes
5. **Testing**: Use small recipient lists for testing to avoid rate limits
6. **Logging**: Add `error_log()` statements for debugging - the plugin has extensive logging
7. **Git Workflow**: Always commit and push after significant changes per user preference

### Common Commands
```bash
# Run linting/validation
npm run lint        # If package.json exists
npm run typecheck   # If package.json exists

# Git workflow
git add -A && git commit -m "Description" && git push
```

### Key Function Locations
- Email sending: `handle_bulk_email()` around line 1400
- CSV parsing: `parse_csv_file()` around line 1778
- Excel parsing: `parse_excel_file()` around line 1844
- Export functionality: `handle_export_recipient_list()` around line 3158
- List management: Various `handle_*` functions around lines 2400-3000

This plugin is production-ready and actively maintained. All changes should maintain backward compatibility and follow WordPress security best practices.