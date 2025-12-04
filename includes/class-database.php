<?php
class SPB_Database {
    
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table for API keys - Update the SQL to add api_key_encrypted field
            $table_api_keys = $wpdb->prefix . SPB_TABLE_API_KEYS;
            $sql_api_keys = "CREATE TABLE IF NOT EXISTS $table_api_keys (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                key_name varchar(100) NOT NULL,
                api_key_hash varchar(255) NOT NULL,
                api_key_encrypted text NULL, -- NEW FIELD: Store encrypted full key
                secret_key_hash varchar(255) NOT NULL,
                status varchar(20) DEFAULT 'active',
                permissions text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                expires_at datetime NULL,
                last_used datetime NULL,
                request_count bigint(20) DEFAULT 0,
                rate_limit_hourly int DEFAULT 100,
                user_id bigint(20),
                PRIMARY KEY (id),
                UNIQUE KEY api_key_hash (api_key_hash),
                KEY status (status),
                KEY expires_at (expires_at)
            ) $charset_collate;";
                    
        // Table for activity logs
        $table_activity_logs = $wpdb->prefix . SPB_TABLE_ACTIVITY_LOGS;
        $sql_activity_logs = "CREATE TABLE IF NOT EXISTS $table_activity_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            api_key_id bigint(20),
            endpoint varchar(100) NOT NULL,
            method varchar(10) NOT NULL,
            status_code int(3) NOT NULL,
            request_body text,
            response_body text,
            pages_created int DEFAULT 0,
            response_time float,
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY api_key_id (api_key_id),
            KEY endpoint (endpoint),
            KEY status_code (status_code),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Table for webhook logs
        $table_webhook_logs = $wpdb->prefix . SPB_TABLE_WEBHOOK_LOGS;
        $sql_webhook_logs = "CREATE TABLE IF NOT EXISTS $table_webhook_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            request_id varchar(100),
            webhook_url text NOT NULL,
            payload text,
            signature varchar(255),
            status_code int(3),
            response_body text,
            error_message text,
            retry_count int DEFAULT 0,
            delivered_at datetime NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY request_id (request_id),
            KEY status_code (status_code),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_api_keys);
        dbDelta($sql_activity_logs);
        dbDelta($sql_webhook_logs);
    }
    
    public static function cleanup_old_logs($days = 90) {
        global $wpdb;
        
        $table_activity = $wpdb->prefix . SPB_TABLE_ACTIVITY_LOGS;
        $table_webhook = $wpdb->prefix . SPB_TABLE_WEBHOOK_LOGS;
        $date = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        // Delete old activity logs
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_activity WHERE created_at < %s",
                $date
            )
        );
        
        // Delete old webhook logs
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_webhook WHERE created_at < %s",
                $date
            )
        );
    }
}