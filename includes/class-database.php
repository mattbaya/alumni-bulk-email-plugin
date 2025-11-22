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
        
        // Execute all table creation queries
        dbDelta($sql_campaigns);
        dbDelta($sql_logs);
        dbDelta($sql_unsubscribes);
        dbDelta($sql_recipient_lists);
        dbDelta($sql_headers_footers);
        
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
            'alumni_headers_footers'
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
            'alumni_headers_footers'
        ];
        
        foreach ($tables as $table_suffix) {
            $table_name = $wpdb->prefix . $table_suffix;
            $wpdb->query("DROP TABLE IF EXISTS $table_name");
        }
        
        error_log('Alumni Bulk Email - Database tables dropped');
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