<?php

namespace FormVibes\Classes;

class DbTables{

	public static function fv_plugin_activated() {
		// 0.1 default
		// 0.1.1 meta value column data type to text
		// 0.1.2 alter table
		// 0.1.3 user agent,status columns added to entry table
		// 0.1.3 logs table
		// 0.1.5 check table exist, updated option only if all table exist
		// 0.1.6 update Undefined to undefined,
        // 0.1.7 delete status column
		$fv_db_version = "0.1.7";
		if ( get_option( 'fv_db_version' ) !== $fv_db_version) {
			self::create_db_table();
		}

	}

	public static function create_db_table(){
		global $wpdb;

		$table_name = $wpdb->prefix . 'fv_enteries';

		$wpdb_collate = $wpdb->collate;
		$query = "CREATE TABLE {$table_name} (
					  `id` int(11) UNIQUE KEY AUTO_INCREMENT NOT NULL,
					  `form_plugin` varchar(50) NOT NULL,
					  `form_id` varchar(100) NOT NULL,
					  `captured` varchar(50) NOT NULL,
					  `captured_gmt` varchar(50) NOT NULL,
					  `url` text NULL,
					  `user_agent` text NULL,
					  `fv_status` varchar(100) NOT NULL DEFAULT 'undefined'
					)collate {$wpdb_collate};";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($query,true);

		//update Undefined to undefined
		/*$wpdb->query("ALTER TABLE {$table_name} MODIFY COLUMN status VARCHAR(100) NOT NULL DEFAULT 'undefined'");
		$wpdb->query("UPDATE {$table_name} SET status='undefined' WHERE status='Undefined'");*/
		//$wpdb->query("ALTER TABLE {$table_name} CHANGE status fv_status varchar(100)");
		$wpdb->query("ALTER TABLE {$table_name} DROP COLUMN status");
		$table_name = $wpdb->prefix . 'fv_entry_meta';

		$wpdb_collate = $wpdb->collate;
		$query = "CREATE TABLE {$table_name} (
					  `id` int(11) UNIQUE KEY AUTO_INCREMENT NOT NULL,
					  `data_id` varchar(50) NOT NULL,
					  `meta_key` varchar(100) NOT NULL,
					  `meta_value` text NOT NULL
					)collate {$wpdb_collate}";

		dbDelta($query,true);

		$table_name = $wpdb->prefix . 'fv_logs';

		$wpdb_collate = $wpdb->collate;
		$query = "CREATE TABLE {$table_name} (
					  `id` int(11) UNIQUE KEY AUTO_INCREMENT NOT NULL,
					  `event` varchar(50) NOT NULL,
					  `user_id` varchar(20) NOT NULL,
					  `description` text NOT NULL,
					  `export_time` varchar(50) NOT NULL,
					  `export_time_gmt` varchar(50) NOT NULL
					)collate {$wpdb_collate}";

		dbDelta($query,true);

		if($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}fv_enteries'") == '' || $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}fv_entry_meta'") == '' || $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}fv_logs'") == '' ){
			update_option('fv_db_version','0');
		}
		else{
			update_option('fv_db_version','0.1.7');
		}
	}
}