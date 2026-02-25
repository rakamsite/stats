<?php
/**
 * مدیریت رهگیری سمت کاربر.
 *
 * @package my-fa-stats
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class My_FA_Stats_Tracker {

	/**
	 * راه‌اندازی.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_tracker' ) );
	}

	/**
	 * افزودن فایل JS رهگیر.
	 *
	 * @return void
	 */
	public static function enqueue_tracker() {
		if ( is_admin() || wp_doing_ajax() || wp_is_json_request() ) {
			return;
		}

		$settings  = my_fa_stats_get_settings();
		$heartbeat = max( 10, min( 120, (int) $settings['heartbeat_interval'] ) );
		$timeout   = max( 5, min( 120, (int) $settings['session_timeout_minutes'] ) );

		wp_enqueue_script(
			'my-fa-stats-tracker',
			MY_FA_STATS_URL . 'assets/js/tracker.js',
			array(),
			MY_FA_STATS_VERSION,
			true
		);

		wp_localize_script(
			'my-fa-stats-tracker',
			'MyFAStats',
			array(
				'endpoint'          => esc_url_raw( rest_url( 'my-fa-stats/v1/hit' ) ),
				'heartbeatInterval' => $heartbeat,
				'sessionTimeout'    => $timeout,
				'siteUrl'           => home_url( '/' ),
			)
		);
	}
}
