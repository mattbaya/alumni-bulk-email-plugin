# Alumni Bulk Email Plugin - Testing Checklist

## Overview
This document outlines comprehensive testing procedures for the refactored Alumni Bulk Email plugin. The plugin was restructured from a 5,541-line monolithic file into a multi-class architecture, requiring thorough validation.

## Pre-Testing Setup Requirements

### WordPress Environment
- [ ] WordPress 5.0+ installed
- [ ] PHP 7.4+ available
- [ ] Required PHP extensions: `json`, `curl`, `openssl`, `zip`, `xml`
- [ ] Write permissions for wp-content/plugins directory

### Dependencies
- [ ] Composer installed and functional
- [ ] PHPSpreadsheet dependency available in vendor/
- [ ] Plugin Update Checker library available

### Test Data
- [ ] Sample CSV file with various column structures
- [ ] Sample Excel (.xlsx) file 
- [ ] Test email addresses for sending
- [ ] Mailgun test credentials (sandbox or real)

## Critical Testing Phases

### Phase 1: Plugin Activation & Core Functionality

#### 1.1 Plugin Installation
- [ ] Plugin activates without fatal errors
- [ ] All required database tables created
- [ ] WordPress admin menus appear correctly
- [ ] No PHP warnings/notices in debug log

**Commands to verify:**
```bash
# Check WordPress debug log
tail -f /path/to/wordpress/wp-content/debug.log

# Verify database tables exist
SELECT TABLE_NAME FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'wordpress_db' AND TABLE_NAME LIKE '%alumni_%';
```

#### 1.2 Admin Interface Access
- [ ] Main "Bulk Email" menu item appears
- [ ] All submenu items load without errors:
  - [ ] Create Campaign (main page)
  - [ ] Email Lists
  - [ ] Campaign History
  - [ ] Email Logs
  - [ ] Headers & Footers
  - [ ] Settings

#### 1.3 Settings Configuration
- [ ] Settings page loads correctly
- [ ] Can save Mailgun configuration
- [ ] "Recreate Tables" button works
- [ ] Configuration validation works

**Test Settings:**
```
Mailgun API Key: key-test123 (fake for testing)
Mailgun Domain: sandbox123.mailgun.org
From Email: test@sandbox123.mailgun.org  
From Name: Test Sender
```

### Phase 2: File Processing & Data Management

#### 2.1 CSV File Upload
- [ ] CSV file upload accepts .csv files
- [ ] File validation rejects invalid file types
- [ ] Large file handling (test with 1000+ row CSV)
- [ ] Dynamic column detection works
- [ ] Preview functionality displays correctly
- [ ] Recipients data stored correctly in database

**Test CSV Structure:**
```csv
email_address,full_name,graduation_year,major,current_location
john@test.com,John Smith,1995,Computer Science,New York
mary@test.com,Mary Johnson,1998,Business Admin,California
bob@test.com,Bob Wilson,2001,Engineering,Texas
```

#### 2.2 Excel File Upload
- [ ] Excel .xlsx file upload works
- [ ] Excel .xls file upload works  
- [ ] Column detection matches CSV behavior
- [ ] Data extraction preserves formatting
- [ ] Error handling for corrupted Excel files

#### 2.3 Data Processing
- [ ] Name field extraction works correctly
- [ ] First/last name parsing functions
- [ ] Email validation and extraction
- [ ] Tag system initialization (empty tags)
- [ ] Backward compatibility fields generated

### Phase 3: List Management Operations

#### 3.1 List Creation & Storage
- [ ] New lists created with proper names
- [ ] Recipient count calculated correctly
- [ ] JSON data storage format correct
- [ ] List appears in admin interface

#### 3.2 List Operations
- [ ] View list functionality works
- [ ] List search and filtering works
- [ ] Bulk edit operations function
- [ ] Tag addition/modification works
- [ ] List merging and deduplication works
- [ ] Sublist creation from filtered results

#### 3.3 Export Functionality  
- [ ] CSV export works with all data
- [ ] Excel export generates properly formatted files
- [ ] Export filtering (bounced/unsubscribed) works
- [ ] Export file naming follows convention

### Phase 4: Email & Campaign Functionality

#### 4.1 Campaign Creation
- [ ] Campaign form loads and functions
- [ ] Subject and content fields work
- [ ] Recipient source selection works
- [ ] Email column selection appears for uploaded files
- [ ] Content editor (TinyMCE) functions properly

#### 4.2 Email Service Integration
- [ ] Mailgun configuration validation works
- [ ] Test email sending functions
- [ ] Personalization tokens work ({name}, {email}, etc.)
- [ ] Unsubscribe link generation works

#### 4.3 Bulk Email Sending
- [ ] Campaign sending works end-to-end
- [ ] Progress tracking functions
- [ ] Email logging works correctly
- [ ] Campaign tagging applied to lists
- [ ] Bounce/unsubscribe checking works

### Phase 5: AJAX & Advanced Features

#### 5.1 AJAX Handler Testing
All AJAX endpoints should return proper JSON responses:

- [ ] `send_test_email` - Test email functionality
- [ ] `upload_csv` - File upload and preview
- [ ] `send_bulk_email` - Campaign sending  
- [ ] `save_campaign` - Campaign save/update
- [ ] `load_campaign` - Campaign loading
- [ ] `view_campaign` - Campaign details view
- [ ] `delete_campaign` - Campaign deletion
- [ ] `copy_campaign` - Campaign duplication
- [ ] `load_recipient_list` - List loading
- [ ] `upload_save_csv` - Save uploaded CSV as list
- [ ] `export_recipient_list` - List export
- [ ] `create_sublist_from_results` - Sublist creation
- [ ] `recreate_tables` - Database table recreation

#### 5.2 Webhook Processing
- [ ] Mailgun webhook endpoint responds
- [ ] Webhook signature validation (if implemented)
- [ ] Email status updates (delivered, bounced, opened, clicked)
- [ ] Unsubscribe processing works

#### 5.3 Advanced Features
- [ ] Campaign analytics and statistics
- [ ] Email log viewing and filtering
- [ ] Headers & footers management (when implemented)
- [ ] Plugin update checking from GitHub

### Phase 6: Error Handling & Edge Cases

#### 6.1 Error Conditions
- [ ] Invalid file uploads handled gracefully
- [ ] Missing Mailgun credentials show appropriate warnings
- [ ] Database connection issues handled
- [ ] Large file processing doesn't cause timeouts
- [ ] Invalid email addresses rejected appropriately

#### 6.2 Security Testing
- [ ] AJAX nonce verification works
- [ ] User capability checks enforced
- [ ] File upload security (no malicious file execution)
- [ ] SQL injection protection (prepared statements)
- [ ] XSS protection in admin interface

#### 6.3 Performance Testing
- [ ] Large recipient lists (1000+ recipients) handled
- [ ] Multiple concurrent uploads work
- [ ] Database queries optimized
- [ ] Memory usage reasonable for large files

## Testing Commands & Debugging

### Enable WordPress Debugging
```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Check Plugin Status
```bash
# Verify all plugin files exist
ls -la wp-content/plugins/alumni-bulk-email-plugin/
ls -la wp-content/plugins/alumni-bulk-email-plugin/includes/

# Check file permissions
find wp-content/plugins/alumni-bulk-email-plugin/ -type f -exec ls -la {} \;
```

### Database Verification
```sql
-- Check all plugin tables exist
SHOW TABLES LIKE '%alumni_%';

-- Verify table structures
DESCRIBE wp_alumni_recipient_lists;
DESCRIBE wp_alumni_email_campaigns; 
DESCRIBE wp_alumni_email_logs;
DESCRIBE wp_alumni_email_unsubscribes;
DESCRIBE wp_alumni_headers_footers;

-- Check sample data
SELECT * FROM wp_alumni_recipient_lists LIMIT 5;
```

### AJAX Testing via Browser Console
```javascript
// Test AJAX endpoint manually
jQuery.post(ajaxurl, {
    action: 'recreate_tables',
    nonce: 'NONCE_VALUE_HERE'
}).done(function(response) {
    console.log('Response:', response);
});
```

## Expected Results

### Successful Testing Indicators
- [ ] No fatal PHP errors during any operation
- [ ] All admin pages load within 3 seconds
- [ ] File uploads complete without timeout
- [ ] Email sending works with test messages
- [ ] Database operations complete successfully  
- [ ] AJAX responses return proper JSON format
- [ ] User interface remains responsive

### Performance Benchmarks
- [ ] Plugin activation: < 5 seconds
- [ ] CSV upload (100 rows): < 10 seconds
- [ ] Excel upload (100 rows): < 15 seconds
- [ ] Campaign send (10 recipients): < 30 seconds
- [ ] List export (100 rows): < 10 seconds

## Issue Documentation

For any discovered issues, document:

1. **Issue Description**: What went wrong?
2. **Steps to Reproduce**: Exact steps that cause the issue
3. **Expected Behavior**: What should happen?
4. **Actual Behavior**: What actually happens?
5. **Error Messages**: Any PHP errors, warnings, or notices
6. **Browser Console**: JavaScript errors (if applicable)
7. **Environment**: PHP version, WordPress version, browser

## Test Data Cleanup

After testing, clean up test data:
- [ ] Delete test campaigns
- [ ] Remove test recipient lists
- [ ] Clear test email logs
- [ ] Reset plugin settings to defaults

## Sign-off

- [ ] All critical functionality tested and working
- [ ] No blocking issues identified
- [ ] Performance within acceptable limits
- [ ] Ready for production deployment

**Tested by:** _______________
**Date:** _______________
**Environment:** _______________
**Issues Found:** _______________