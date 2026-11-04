<?php
/**
 * Activity log for ability calls.
 *
 * @package OneMCP
 */

namespace ONEMCP;

if (!defined('ABSPATH')) {
	exit;
}

class Audit {

	const DB_VERSION = '1';

	/**
	 * Create or update the log table.
	 *
	 * @return void
	 */
	 static function install() {
		global $wpdb;

		if (defined('SITEPAD')) {
			require_once ABSPATH . 'site-admin/includes/upgrade.php';
		} else {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ability varchar(191) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			ok tinyint(1) NOT NULL DEFAULT 1,
			message text NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY ability (ability),
			KEY created_at (created_at)
		) {$charset};";

		dbDelta($sql);
		update_option('onemcp_db_version', self::DB_VERSION, false);
	}

	/**
	 * Install the table when the stored schema version is old.
	 *
	 * @return void
	 */
	 static function maybe_install() {
		if (get_option('onemcp_db_version') !== self::DB_VERSION) {
			self::install();
		}
	}

	/**
	 * Log table name.
	 *
	 * @return string
	 */
	 static function table() {
		global $wpdb;
		return $wpdb->prefix . 'onemcp_log';
	}

	/**
	 * Record one ability call. The message is a short status, never the arguments.
	 *
	 * @param string $ability Ability id.
	 * @param bool   $ok      Whether the call succeeded.
	 * @param string $message Short result.
	 * @return void
	 */
	 static function log($ability, $ok, $message) {
		global $wpdb;

		if (get_option('onemcp_db_version') !== self::DB_VERSION) {
			return;
		}

		$wpdb->insert(
			self::table(),
			array(
				'ability'    => substr(sanitize_text_field($ability), 0, 191),
				'user_id'    => get_current_user_id(),
				'ok'         => $ok ? 1 : 0,
				'message'    => Helper::text($message, 500),
				'created_at' => current_time('mysql', true),
			),
			array('%s', '%d', '%d', '%s', '%s')
		);

		$total = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . esc_sql(self::table()));
		$keep  = (int) apply_filters('onemcp_audit_keep', 100);
		if ($keep < 20) {
			$keep = 20;
		}
		if ($keep > 5000) {
			$keep = 5000;
		}
		if ($total > $keep) {
			$drop = $total - $keep;
			$wpdb->query($wpdb->prepare('DELETE FROM ' . esc_sql(self::table()) . ' ORDER BY id ASC LIMIT %d', $drop));
		}
	}

	/**
	 * Recent log rows, newest first.
	 *
	 * @param int $limit Row count.
	 * @return array<int,array<string,mixed>>
	 */
	static function recent($limit = 50) {
		global $wpdb;

		if (get_option('onemcp_db_version') !== self::DB_VERSION) {
			return array();
		}

		$limit = Helper::limit($limit, 50, 100);
		$rows  = $wpdb->get_results(
			$wpdb->prepare('SELECT id, ability, user_id, ok, message, created_at FROM ' . esc_sql(self::table()) . ' ORDER BY id DESC LIMIT %d', $limit),
			ARRAY_A
		);
		return is_array($rows) ? $rows : array();
	}

	/**
	 * Delete every log row.
	 *
	 * @return void
	 */
	static function clear() {
		global $wpdb;
		if (get_option('onemcp_db_version') === self::DB_VERSION) {
			$wpdb->query('TRUNCATE TABLE ' . esc_sql(self::table()));
		}
	}
}
