<?php
/**
 * API رهگیری.
 *
 * @package my-fa-stats
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class My_FA_Stats_REST {

	/**
	 * راه‌اندازی.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * ثبت مسیر API.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			'my-fa-stats/v1',
			'/hit',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_hit' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * پردازش hit دریافتی.
	 *
	 * @param WP_REST_Request $request درخواست.
	 * @return WP_REST_Response
	 */
	public static function handle_hit( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 400 );
		}

		$event = isset( $payload['event'] ) ? sanitize_key( (string) $payload['event'] ) : '';
		if ( ! in_array( $event, array( 'pageview', 'heartbeat', 'exit' ), true ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'رویداد نامعتبر است.' ), 400 );
		}

		$sid = self::sanitize_token( $payload['sid'] ?? '' );
		$cid = self::sanitize_token( $payload['cid'] ?? '' );
		if ( '' === $sid || '' === $cid ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'شناسه نشست یا بازدیدکننده نامعتبر است.' ), 400 );
		}

		$path      = self::sanitize_path( $payload['path'] ?? '/' );
		$referrer  = isset( $payload['referrer'] ) ? esc_url_raw( (string) $payload['referrer'] ) : '';
		$ref_domain = self::extract_domain( $referrer );

		$utm_source   = self::sanitize_text( $payload['utm_source'] ?? '' );
		$utm_medium   = self::sanitize_text( $payload['utm_medium'] ?? '' );
		$utm_campaign = self::sanitize_text( $payload['utm_campaign'] ?? '' );
		$utm_term     = self::sanitize_text( $payload['utm_term'] ?? '' );
		$utm_content  = self::sanitize_text( $payload['utm_content'] ?? '' );
		$click_id     = self::sanitize_text( $payload['click_id'] ?? '' );

		$settings = my_fa_stats_get_settings();
		$timeout  = max( 5, min( 120, (int) $settings['session_timeout_minutes'] ) );

		$source_medium = self::resolve_source_medium(
			$utm_source,
			$utm_medium,
			$ref_domain
		);

		global $wpdb;
		$table = $wpdb->prefix . 'my_fa_stats_sessions';
		$now   = current_time( 'mysql' );

		$sql      = $wpdb->prepare( "SELECT * FROM {$table} WHERE sid = %s LIMIT 1", $sid );
		$existing = $wpdb->get_row( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		$renew_session = false;
		if ( $existing ) {
			$last_seen_ts = strtotime( $existing->last_seen );
			if ( false === $last_seen_ts || ( time() - $last_seen_ts ) > ( $timeout * MINUTE_IN_SECONDS ) ) {
				$renew_session = true;
			}
		}

		$user_id = is_user_logged_in() ? get_current_user_id() : null;
		$ua_hash = '';
		if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$ua_hash = hash( 'sha256', wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		}

		$ip_hash = null;
		if ( ! empty( $settings['store_ip_hash'] ) && isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$raw_ip  = wp_unslash( $_SERVER['REMOTE_ADDR'] );
			$ip_hash = hash_hmac( 'sha256', $raw_ip, wp_salt( 'auth' ) );
		}

		if ( ! $existing || $renew_session ) {
			$insert = $wpdb->insert(
				$table,
				array(
					'sid'          => $sid,
					'cid'          => $cid,
					'user_id'      => $user_id,
					'entry_time'   => $now,
					'last_seen'    => $now,
					'landing_path' => $path,
					'exit_path'    => $path,
					'pageviews'    => 'pageview' === $event ? 1 : 0,
					'ref_domain'   => $ref_domain,
					'ref_url'      => $referrer,
					'source'       => $source_medium['source'],
					'medium'       => $source_medium['medium'],
					'campaign'     => $utm_campaign,
					'utm_source'   => $utm_source,
					'utm_medium'   => $utm_medium,
					'utm_campaign' => $utm_campaign,
					'utm_term'     => $utm_term,
					'utm_content'  => $utm_content,
					'click_id'     => $click_id,
					'ip_hash'      => $ip_hash,
					'ua_hash'      => $ua_hash,
					'duration_sec' => 0,
				),
				array(
					'%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d',
					'%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
				)
			);

			if ( false === $insert ) {
				return new WP_REST_Response( array( 'ok' => false ), 500 );
			}
		} else {
			$pageviews_inc = 'pageview' === $event ? 1 : 0;
			$entry_ts      = strtotime( $existing->entry_time );
			$duration      = $entry_ts ? max( 0, time() - $entry_ts ) : (int) $existing->duration_sec;

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table}
					 SET last_seen = %s,
						exit_path = %s,
						pageviews = pageviews + %d,
						duration_sec = %d
					 WHERE sid = %s",
					$now,
					$path,
					$pageviews_inc,
					$duration,
					$sid
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * تعیین source/medium.
	 *
	 * @param string $utm_source utm_source.
	 * @param string $utm_medium utm_medium.
	 * @param string $ref_domain دامنه ارجاع.
	 * @return array<string, string>
	 */
	private static function resolve_source_medium( $utm_source, $utm_medium, $ref_domain ) {
		if ( '' !== $utm_source ) {
			return array(
				'source' => $utm_source,
				'medium' => '' !== $utm_medium ? $utm_medium : 'utm',
			);
		}

		if ( '' === $ref_domain ) {
			return array(
				'source' => 'direct',
				'medium' => 'none',
			);
		}

		$site_domain = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( $site_domain && self::normalize_domain( $site_domain ) === self::normalize_domain( $ref_domain ) ) {
			return array(
				'source' => 'direct',
				'medium' => 'internal',
			);
		}

		return array(
			'source' => $ref_domain,
			'medium' => 'referral',
		);
	}

	/**
	 * استخراج دامنه.
	 *
	 * @param string $url آدرس.
	 * @return string
	 */
	private static function extract_domain( $url ) {
		if ( '' === $url ) {
			return '';
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) ) {
			return '';
		}

		return self::normalize_domain( $host );
	}

	/**
	 * نرمال‌سازی دامنه.
	 *
	 * @param string $domain دامنه.
	 * @return string
	 */
	private static function normalize_domain( $domain ) {
		$domain = strtolower( trim( (string) $domain ) );
		if ( 0 === strpos( $domain, 'www.' ) ) {
			$domain = substr( $domain, 4 );
		}

		return substr( sanitize_text_field( $domain ), 0, 191 );
	}

	/**
	 * پاک‌سازی token.
	 *
	 * @param string $value مقدار.
	 * @return string
	 */
	private static function sanitize_token( $value ) {
		$value = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $value );
		if ( ! is_string( $value ) ) {
			return '';
		}

		return substr( $value, 0, 64 );
	}

	/**
	 * پاک‌سازی مسیر.
	 *
	 * @param string $path مسیر.
	 * @return string
	 */
	private static function sanitize_path( $path ) {
		$path = (string) $path;
		if ( '' === $path ) {
			$path = '/';
		}

		$path = wp_parse_url( $path, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			$path = '/';
		}

		return substr( sanitize_text_field( $path ), 0, 255 );
	}

	/**
	 * پاک‌سازی رشته کوتاه.
	 *
	 * @param string $value مقدار.
	 * @return string
	 */
	private static function sanitize_text( $value ) {
		return substr( sanitize_text_field( (string) $value ), 0, 191 );
	}
}
