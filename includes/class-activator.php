<?php
/**
 * فعال‌سازی و حذف افزونه.
 *
 * @package my-fa-stats
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class My_FA_Stats_Activator {

	/**
	 * اجرای فعال‌سازی.
	 *
	 * @return void
	 */
	public static function activate() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$table_sessions  = $wpdb->prefix . 'my_fa_stats_sessions';
		$table_changes   = $wpdb->prefix . 'my_fa_stats_changelog';

		$sql_sessions = "CREATE TABLE {$table_sessions} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			sid VARCHAR(64) NOT NULL,
			cid VARCHAR(64) NOT NULL,
			user_id BIGINT(20) UNSIGNED NULL,
			entry_time DATETIME NOT NULL,
			last_seen DATETIME NOT NULL,
			landing_path VARCHAR(255) NOT NULL,
			exit_path VARCHAR(255) NOT NULL,
			pageviews INT(11) UNSIGNED NOT NULL DEFAULT 0,
			ref_domain VARCHAR(191) NOT NULL DEFAULT '',
			ref_url TEXT NULL,
			source VARCHAR(191) NOT NULL DEFAULT 'direct',
			medium VARCHAR(191) NOT NULL DEFAULT 'none',
			campaign VARCHAR(191) NOT NULL DEFAULT '',
			utm_source VARCHAR(191) NOT NULL DEFAULT '',
			utm_medium VARCHAR(191) NOT NULL DEFAULT '',
			utm_campaign VARCHAR(191) NOT NULL DEFAULT '',
			utm_term VARCHAR(191) NOT NULL DEFAULT '',
			utm_content VARCHAR(191) NOT NULL DEFAULT '',
			click_id VARCHAR(191) NOT NULL DEFAULT '',
			ip_hash CHAR(64) NULL,
			ua_hash CHAR(64) NULL,
			duration_sec INT(11) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY sid (sid),
			KEY cid (cid),
			KEY user_id (user_id),
			KEY entry_time (entry_time),
			KEY last_seen (last_seen),
			KEY source_medium_entry (source(100), medium(100), entry_time),
			KEY source_entry (source(100), entry_time),
			KEY campaign_entry (campaign(100), entry_time),
			KEY cid_entry (cid, entry_time)
		) {$charset_collate};";

		$sql_changes = "CREATE TABLE {$table_changes} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			version VARCHAR(32) NOT NULL,
			created_at DATETIME NOT NULL,
			changes_text TEXT NOT NULL,
			PRIMARY KEY (id),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql_sessions );
		dbDelta( $sql_changes );

		if ( ! get_option( 'my_fa_stats_settings', false ) ) {
			add_option(
				'my_fa_stats_settings',
				array(
					'session_timeout_minutes' => 30,
					'heartbeat_interval'      => 20,
					'retention_days'          => 0,
					'store_ip_hash'           => 0,
				)
			);
		}

		if ( ! get_option( 'my_fa_stats_delete_on_uninstall', false ) ) {
			add_option( 'my_fa_stats_delete_on_uninstall', 0 );
		}

		if ( ! self::has_changelog_entries() ) {
			My_FA_Stats_Changelog::add_entry( '1.0.0', 'انتشار اولیه افزونه آمار سبک فارسی با رهگیری نشست‌ها و گزارش ماهانه.' );
		}
	}

	/**
	 * حذف کامل اطلاعات در صورت فعال بودن گزینه.
	 *
	 * @return void
	 */
	public static function uninstall() {
		$delete = (int) get_option( 'my_fa_stats_delete_on_uninstall', 0 );
		if ( 1 !== $delete ) {
			return;
		}

		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}my_fa_stats_sessions" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}my_fa_stats_changelog" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		delete_option( 'my_fa_stats_settings' );
		delete_option( 'my_fa_stats_delete_on_uninstall' );
	}

	/**
	 * بررسی وجود رکورد گزارش تغییرات.
	 *
	 * @return bool
	 */
	private static function has_changelog_entries() {
		global $wpdb;
		$table = $wpdb->prefix . 'my_fa_stats_changelog';
		$cnt   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		return $cnt > 0;
	}
}
