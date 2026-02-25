<?php
/**
 * مدیریت گزارش به‌روزرسانی‌ها.
 *
 * @package my-fa-stats
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class My_FA_Stats_Changelog {

	/**
	 * راه‌اندازی.
	 *
	 * @return void
	 */
	public static function init() {
		// نگهدار برای توسعه‌های آتی.
	}

	/**
	 * افزودن رکورد changelog.
	 *
	 * @param string $version نسخه.
	 * @param string $changes_text_persian شرح فارسی.
	 * @return bool
	 */
	public static function add_entry( $version, $changes_text_persian ) {
		global $wpdb;
		$table = $wpdb->prefix . 'my_fa_stats_changelog';

		$version = sanitize_text_field( $version );
		$changes = wp_kses_post( $changes_text_persian );

		if ( '' === $version || '' === trim( wp_strip_all_tags( $changes ) ) ) {
			return false;
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'version'      => mb_substr( $version, 0, 32 ),
				'created_at'   => current_time( 'mysql' ),
				'changes_text' => $changes,
			),
			array( '%s', '%s', '%s' )
		);

		return false !== $inserted;
	}

	/**
	 * فهرست رکوردهای تغییرات.
	 *
	 * @param int $limit تعداد.
	 * @return array<int, object>
	 */
	public static function get_entries( $limit = 200 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'my_fa_stats_changelog';
		$limit = max( 1, min( 500, (int) $limit ) );

		$sql = $wpdb->prepare( "SELECT id, version, created_at, changes_text FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d", $limit );

		return (array) $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}
}
