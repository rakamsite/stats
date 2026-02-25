<?php
/**
 * Plugin Name: آمار سبک فارسی
 * Plugin URI: https://example.com
 * Description: افزونه سبک تحلیل نشست‌ها با تمرکز بر منبع ورودی، مدت ماندگاری و صفحه خروج.
 * Version: 1.0.0
 * Author: تیم توسعه
 * Text Domain: my-fa-stats
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MY_FA_STATS_VERSION', '1.0.0' );
define( 'MY_FA_STATS_FILE', __FILE__ );
define( 'MY_FA_STATS_PATH', plugin_dir_path( __FILE__ ) );
define( 'MY_FA_STATS_URL', plugin_dir_url( __FILE__ ) );
define( 'MY_FA_STATS_BASENAME', plugin_basename( __FILE__ ) );

require_once MY_FA_STATS_PATH . 'includes/class-activator.php';
require_once MY_FA_STATS_PATH . 'includes/class-changelog.php';
require_once MY_FA_STATS_PATH . 'includes/class-rest.php';
require_once MY_FA_STATS_PATH . 'includes/class-tracker.php';
require_once MY_FA_STATS_PATH . 'includes/class-admin.php';

register_activation_hook( __FILE__, array( 'My_FA_Stats_Activator', 'activate' ) );
register_uninstall_hook( __FILE__, array( 'My_FA_Stats_Activator', 'uninstall' ) );

/**
 * بازیابی تنظیمات افزونه.
 *
 * @return array<string, mixed>
 */
function my_fa_stats_get_settings() {
	$defaults = array(
		'session_timeout_minutes' => 30,
		'heartbeat_interval'      => 20,
		'retention_days'          => 0,
		'store_ip_hash'           => 0,
	);

	$settings = get_option( 'my_fa_stats_settings', array() );
	if ( ! is_array( $settings ) ) {
		$settings = array();
	}

	return wp_parse_args( $settings, $defaults );
}

/**
 * تابع عمومی افزودن رکورد به گزارش به‌روزرسانی‌ها.
 *
 * @param string $version نسخه.
 * @param string $changes_text_persian شرح فارسی تغییرات.
 * @return bool
 */
function my_fa_stats_add_changelog( $version, $changes_text_persian ) {
	return My_FA_Stats_Changelog::add_entry( $version, $changes_text_persian );
}

/**
 * بارگذاری افزونه.
 */
function my_fa_stats_bootstrap() {
	load_plugin_textdomain( 'my-fa-stats', false, dirname( MY_FA_STATS_BASENAME ) . '/languages' );

	My_FA_Stats_Changelog::init();
	My_FA_Stats_REST::init();
	My_FA_Stats_Tracker::init();
	My_FA_Stats_Admin::init();
}
add_action( 'plugins_loaded', 'my_fa_stats_bootstrap' );
