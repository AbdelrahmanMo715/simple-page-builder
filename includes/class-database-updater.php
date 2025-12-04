<?php
class SPB_Database_Updater {
    
    public static function update_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . SPB_TABLE_API_KEYS;
        
        // Check if api_key_encrypted column exists
        $columns = $wpdb->get_col("DESC $table_name", 0);
        
        if (!in_array('api_key_encrypted', $columns)) {
            // Add the new column
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN api_key_encrypted text NULL AFTER api_key_hash");
            
            // If you have existing keys, you might want to encrypt them here
            // For now, they'll remain without full key display
        }
    }
    
    public static function run_on_activation() {
        self::update_tables();
    }
}