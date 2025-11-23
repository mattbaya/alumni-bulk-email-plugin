<?php
/**
 * Database management class for Alumni Bulk Email plugin
 * 
 * Handles database table creation, schema management, and utility functions
 */

if (!defined('ABSPATH')) {
    exit;
}

class Alumni_Database {
    
    /**
     * Create all required database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Email campaigns table
        $table_campaigns = $wpdb->prefix . 'alumni_email_campaigns';
        $sql_campaigns = "CREATE TABLE IF NOT EXISTS $table_campaigns (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            campaign_name varchar(255) NOT NULL,
            subject varchar(255) NOT NULL,
            content longtext NOT NULL,
            recipients_data longtext,
            sent_at datetime,
            total_recipients int DEFAULT 0,
            status varchar(50) DEFAULT 'draft',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_name (campaign_name),
            KEY status (status),
            KEY sent_at (sent_at)
        ) $charset_collate;";
        
        // Email logs table
        $table_logs = $wpdb->prefix . 'alumni_email_logs';
        $sql_logs = "CREATE TABLE IF NOT EXISTS $table_logs (
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
        
        // Headers and Footers table
        $table_headers_footers = $wpdb->prefix . 'alumni_headers_footers';
        $sql_headers_footers = "CREATE TABLE IF NOT EXISTS $table_headers_footers (
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
        ) $charset_collate;";
        
        // Typography Presets table
        $table_typography_presets = $wpdb->prefix . 'alumni_typography_presets';
        $sql_typography_presets = "CREATE TABLE IF NOT EXISTS $table_typography_presets (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            font_family varchar(255) DEFAULT '',
            font_size varchar(50) DEFAULT '',
            text_color varchar(50) DEFAULT '',
            description text DEFAULT '',
            is_default tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY name (name),
            KEY is_default (is_default)
        ) $charset_collate;";
        
        // Execute all table creation queries
        dbDelta($sql_campaigns);
        dbDelta($sql_logs);
        dbDelta($sql_unsubscribes);
        dbDelta($sql_recipient_lists);
        dbDelta($sql_headers_footers);
        dbDelta($sql_typography_presets);
        
        error_log('Alumni Bulk Email - Database tables created/updated successfully');
    }
    
    /**
     * Get table name with WordPress prefix
     */
    public static function get_table_name($table_suffix) {
        global $wpdb;
        return $wpdb->prefix . 'alumni_' . $table_suffix;
    }
    
    /**
     * Check if all required tables exist
     */
    public static function tables_exist() {
        global $wpdb;
        
        $required_tables = [
            'alumni_email_campaigns',
            'alumni_email_logs', 
            'alumni_email_unsubscribes',
            'alumni_recipient_lists',
            'alumni_headers_footers',
            'alumni_typography_presets'
        ];
        
        foreach ($required_tables as $table_suffix) {
            $table_name = $wpdb->prefix . $table_suffix;
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            
            if ($table_exists != $table_name) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Drop all plugin tables (for cleanup)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = [
            'alumni_email_campaigns',
            'alumni_email_logs',
            'alumni_email_unsubscribes', 
            'alumni_recipient_lists',
            'alumni_headers_footers',
            'alumni_typography_presets'
        ];
        
        foreach ($tables as $table_suffix) {
            $table_name = $wpdb->prefix . $table_suffix;
            $wpdb->query("DROP TABLE IF EXISTS $table_name");
        }
        
        error_log('Alumni Bulk Email - Database tables dropped');
    }
    
    /**
     * Update existing tables to match current schema
     */
    public static function update_table_schema() {
        global $wpdb;
        
        $campaigns_table = $wpdb->prefix . 'alumni_email_campaigns';
        
        // Check if campaigns table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$campaigns_table'");
        if (!$table_exists) {
            error_log('Alumni Bulk Email - Campaigns table does not exist, creating it');
            self::create_tables();
            return;
        }
        
        // Get current columns
        $columns = $wpdb->get_results("DESCRIBE $campaigns_table");
        $column_names = array_column($columns, 'Field');
        
        error_log('Alumni Bulk Email - Current campaigns table columns: ' . implode(', ', $column_names));
        
        // Add missing columns one by one
        $missing_columns_added = [];
        
        if (!in_array('content', $column_names)) {
            $wpdb->query("ALTER TABLE $campaigns_table ADD COLUMN content longtext NOT NULL AFTER subject");
            $missing_columns_added[] = 'content';
        }
        
        if (!in_array('recipients_data', $column_names)) {
            $wpdb->query("ALTER TABLE $campaigns_table ADD COLUMN recipients_data longtext");
            $missing_columns_added[] = 'recipients_data';
        }
        
        if (!in_array('sent_at', $column_names)) {
            $wpdb->query("ALTER TABLE $campaigns_table ADD COLUMN sent_at datetime");
            $missing_columns_added[] = 'sent_at';
        }
        
        if (!in_array('total_recipients', $column_names)) {
            $wpdb->query("ALTER TABLE $campaigns_table ADD COLUMN total_recipients int DEFAULT 0");
            $missing_columns_added[] = 'total_recipients';
        }
        
        if (!in_array('status', $column_names)) {
            $wpdb->query("ALTER TABLE $campaigns_table ADD COLUMN status varchar(50) DEFAULT 'draft'");
            $missing_columns_added[] = 'status';
        }
        
        if (!in_array('created_at', $column_names)) {
            $wpdb->query("ALTER TABLE $campaigns_table ADD COLUMN created_at datetime DEFAULT CURRENT_TIMESTAMP");
            $missing_columns_added[] = 'created_at';
        }
        
        if (!in_array('updated_at', $column_names)) {
            $wpdb->query("ALTER TABLE $campaigns_table ADD COLUMN updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            $missing_columns_added[] = 'updated_at';
        }
        
        if (!in_array('email_column', $column_names)) {
            $wpdb->query("ALTER TABLE $campaigns_table ADD COLUMN email_column varchar(255) DEFAULT 'email'");
            $missing_columns_added[] = 'email_column';
        }
        
        if (!in_array('batch_size', $column_names)) {
            $wpdb->query("ALTER TABLE $campaigns_table ADD COLUMN batch_size int DEFAULT 50");
            $missing_columns_added[] = 'batch_size';
        }
        
        if (!in_array('processed_count', $column_names)) {
            $wpdb->query("ALTER TABLE $campaigns_table ADD COLUMN processed_count int DEFAULT 0");
            $missing_columns_added[] = 'processed_count';
        }
        
        if (!in_array('batch_current', $column_names)) {
            $wpdb->query("ALTER TABLE $campaigns_table ADD COLUMN batch_current int DEFAULT 0");
            $missing_columns_added[] = 'batch_current';
        }
        
        if (!empty($missing_columns_added)) {
            error_log('Alumni Bulk Email - Added missing columns to campaigns table: ' . implode(', ', $missing_columns_added));
        } else {
            error_log('Alumni Bulk Email - All required columns already exist in campaigns table');
        }
    }
    
    /**
     * Get database version for migration management
     */
    public static function get_db_version() {
        return get_option('alumni_bulk_email_db_version', '1.0.0');
    }
    
    /**
     * Update database version
     */
    public static function set_db_version($version) {
        update_option('alumni_bulk_email_db_version', $version);
    }
}