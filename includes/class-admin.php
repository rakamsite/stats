<?php
/**
 * مدیریت بخش ادمین.
 *
 * @package my-fa-stats
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class My_FA_Stats_Admin {

	/**
	 * راه‌اندازی.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_my_fa_stats_export_csv', array( __CLASS__, 'handle_export_csv' ) );
		add_action( 'admin_post_my_fa_stats_save_settings', array( __CLASS__, 'handle_save_settings' ) );
		add_action( 'admin_post_my_fa_stats_add_changelog', array( __CLASS__, 'handle_add_changelog' ) );
	}

	/**
	 * ثبت منوها.
	 *
	 * @return void
	 */
	public static function register_menus() {
		add_menu_page(
			__( 'آمار سبک', 'my-fa-stats' ),
			__( 'آمار سبک', 'my-fa-stats' ),
			'manage_options',
			'my-fa-stats',
			array( __CLASS__, 'render_report_page' ),
			'dashicons-chart-area',
			65
		);

		add_submenu_page(
			'my-fa-stats',
			__( 'گزارش ماهانه', 'my-fa-stats' ),
			__( 'گزارش ماهانه', 'my-fa-stats' ),
			'manage_options',
			'my-fa-stats',
			array( __CLASS__, 'render_report_page' )
		);

		add_submenu_page(
			'my-fa-stats',
			__( 'راهنما', 'my-fa-stats' ),
			__( 'راهنما', 'my-fa-stats' ),
			'manage_options',
			'my-fa-stats-help',
			array( __CLASS__, 'render_help_page' )
		);

		add_submenu_page(
			'my-fa-stats',
			__( 'گزارش به‌روزرسانی‌ها', 'my-fa-stats' ),
			__( 'گزارش به‌روزرسانی‌ها', 'my-fa-stats' ),
			'manage_options',
			'my-fa-stats-changelog',
			array( __CLASS__, 'render_changelog_page' )
		);
	}

	/**
	 * بارگذاری CSS سبک.
	 *
	 * @param string $hook نام هوک.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		$allowed = array(
			'toplevel_page_my-fa-stats',
			'my-fa-stats_page_my-fa-stats-help',
			'my-fa-stats_page_my-fa-stats-changelog',
		);

		if ( ! in_array( $hook, $allowed, true ) ) {
			return;
		}

		wp_enqueue_style(
			'my-fa-stats-admin',
			MY_FA_STATS_URL . 'assets/css/admin.css',
			array(),
			MY_FA_STATS_VERSION
		);
	}

	/**
	 * صفحه گزارش ماهانه.
	 *
	 * @return void
	 */
	public static function render_report_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما دسترسی کافی ندارید.', 'my-fa-stats' ) );
		}

		$month = isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : gmdate( 'Y-m' );
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
			$month = gmdate( 'Y-m' );
		}

		$range            = self::month_range( $month );
		$stats            = self::get_monthly_stats( $range['start'], $range['end'] );
		$top_sources      = self::get_top_sources( $range['start'], $range['end'] );
		$top_campaigns    = self::get_top_campaigns( $range['start'], $range['end'] );
		$top_landing      = self::get_top_landing_pages( $range['start'], $range['end'] );
		$current_settings = my_fa_stats_get_settings();
		?>
		<div class="wrap my-fa-stats-wrap" dir="rtl">
			<h1><?php echo esc_html__( 'گزارش ماهانه آمار سبک', 'my-fa-stats' ); ?></h1>

			<form method="get" class="my-fa-stats-inline-form">
				<input type="hidden" name="page" value="my-fa-stats" />
				<label for="my-fa-stats-month"><strong><?php esc_html_e( 'ماه گزارش:', 'my-fa-stats' ); ?></strong></label>
				<input type="month" id="my-fa-stats-month" name="month" value="<?php echo esc_attr( $month ); ?>" />
				<button type="submit" class="button button-primary"><?php esc_html_e( 'نمایش گزارش', 'my-fa-stats' ); ?></button>
			</form>

			<div class="notice notice-info inline"><p><?php echo esc_html( sprintf( 'بازه گزارش از %s تا %s', $range['start'], $range['end'] ) ); ?></p></div>

			<h2><?php esc_html_e( 'شاخص‌های کلیدی', 'my-fa-stats' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'تعداد نشست‌ها', 'my-fa-stats' ); ?></th>
						<th><?php esc_html_e( 'بازدیدکننده یکتا', 'my-fa-stats' ); ?></th>
						<th><?php esc_html_e( 'میانگین مدت نشست', 'my-fa-stats' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php echo esc_html( number_format_i18n( $stats['sessions'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $stats['unique_visitors'] ) ); ?></td>
						<td><?php echo esc_html( self::format_duration( $stats['avg_duration'] ) ); ?></td>
					</tr>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'منابع/مدیوم برتر', 'my-fa-stats' ); ?></h2>
			<?php self::render_table_sources( $top_sources ); ?>

			<h2><?php esc_html_e( 'کمپین‌های برتر', 'my-fa-stats' ); ?></h2>
			<?php self::render_table_campaigns( $top_campaigns ); ?>

			<h2><?php esc_html_e( 'صفحات فرود برتر', 'my-fa-stats' ); ?></h2>
			<?php self::render_table_landing( $top_landing ); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="my-fa-stats-inline-form">
				<input type="hidden" name="action" value="my_fa_stats_export_csv" />
				<input type="hidden" name="month" value="<?php echo esc_attr( $month ); ?>" />
				<?php wp_nonce_field( 'my_fa_stats_export_csv', 'my_fa_stats_export_nonce' ); ?>
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'خروجی CSV منابع/مدیوم', 'my-fa-stats' ); ?></button>
			</form>

			<hr />
			<h2><?php esc_html_e( 'تنظیمات سبک', 'my-fa-stats' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="my_fa_stats_save_settings" />
				<?php wp_nonce_field( 'my_fa_stats_save_settings', 'my_fa_stats_settings_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="session_timeout_minutes"><?php esc_html_e( 'زمان انقضای نشست (دقیقه)', 'my-fa-stats' ); ?></label></th>
						<td><input type="number" min="5" max="120" id="session_timeout_minutes" name="session_timeout_minutes" value="<?php echo esc_attr( (int) $current_settings['session_timeout_minutes'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="heartbeat_interval"><?php esc_html_e( 'فاصله Heartbeat (ثانیه)', 'my-fa-stats' ); ?></label></th>
						<td><input type="number" min="10" max="120" id="heartbeat_interval" name="heartbeat_interval" value="<?php echo esc_attr( (int) $current_settings['heartbeat_interval'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="retention_days"><?php esc_html_e( 'نگه‌داری داده خام (روز، صفر = بدون حذف)', 'my-fa-stats' ); ?></label></th>
						<td><input type="number" min="0" max="3650" id="retention_days" name="retention_days" value="<?php echo esc_attr( (int) $current_settings['retention_days'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="store_ip_hash"><?php esc_html_e( 'ذخیره هش IP (نه IP خام)', 'my-fa-stats' ); ?></label></th>
						<td><label><input type="checkbox" id="store_ip_hash" name="store_ip_hash" value="1" <?php checked( ! empty( $current_settings['store_ip_hash'] ) ); ?> /> <?php esc_html_e( 'فعال', 'my-fa-stats' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="delete_on_uninstall"><?php esc_html_e( 'حذف کامل داده‌ها هنگام حذف افزونه', 'my-fa-stats' ); ?></label></th>
						<td><label><input type="checkbox" id="delete_on_uninstall" name="delete_on_uninstall" value="1" <?php checked( 1 === (int) get_option( 'my_fa_stats_delete_on_uninstall', 0 ) ); ?> /> <?php esc_html_e( 'فعال', 'my-fa-stats' ); ?></label></td>
					</tr>
				</table>
				<?php submit_button( __( 'ذخیره تنظیمات', 'my-fa-stats' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * صفحه راهنما.
	 *
	 * @return void
	 */
	public static function render_help_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما دسترسی کافی ندارید.', 'my-fa-stats' ) );
		}
		?>
		<div class="wrap my-fa-stats-wrap" dir="rtl">
			<h1><?php esc_html_e( 'راهنمای آمار سبک', 'my-fa-stats' ); ?></h1>
			<h2><?php esc_html_e( 'راهنمای استفاده‌کننده', 'my-fa-stats' ); ?></h2>
			<p><?php esc_html_e( 'این افزونه در سطح نشست، صفحه فرود، صفحه خروج، مدت حضور، تعداد بازدید صفحه و منبع ورودی (UTM یا Referrer) را ذخیره می‌کند. این افزونه رفتار ریزدانه کاربران یا کلیک‌های خروجی را در فاز اول دنبال نمی‌کند.', 'my-fa-stats' ); ?></p>
			<ul>
				<li><?php esc_html_e( 'در گزارش ماهانه، ابتدا شاخص‌های کلی را ببینید: تعداد نشست، بازدیدکننده یکتا و میانگین زمان حضور.', 'my-fa-stats' ); ?></li>
				<li><?php esc_html_e( 'جدول منابع/مدیوم نشان می‌دهد بیشترین ترافیک از کدام کانال آمده است.', 'my-fa-stats' ); ?></li>
				<li><?php esc_html_e( 'برای دقت بهتر Attribution، لینک‌های کمپین را با پارامترهای UTM بسازید.', 'my-fa-stats' ); ?></li>
				<li><?php esc_html_e( 'در برخی اپلیکیشن‌ها/مرورگرها ممکن است referrer ارسال نشود؛ در این حالت ترافیک به direct نسبت داده می‌شود.', 'my-fa-stats' ); ?></li>
			</ul>

			<h2><?php esc_html_e( 'راهنمای توسعه‌دهنده', 'my-fa-stats' ); ?></h2>
			<p><?php esc_html_e( 'ساختار دیتابیس شامل جدول نشست‌ها و جدول گزارش به‌روزرسانی‌ها است. جدول نشست‌ها با کلیدهای index برای گزارش ماهانه بهینه شده است.', 'my-fa-stats' ); ?></p>
			<ul>
				<li><code><?php echo esc_html( '/wp-json/my-fa-stats/v1/hit' ); ?></code> - <?php esc_html_e( 'مسیر REST برای دریافت hitهای pageview/heartbeat/exit.', 'my-fa-stats' ); ?></li>
				<li><code>my_fa_stats_add_changelog( $version, $changes_text_persian )</code> - <?php esc_html_e( 'افزودن برنامه‌نویسی‌شده رکورد گزارش به‌روزرسانی‌ها.', 'my-fa-stats' ); ?></li>
				<li><code>my_fa_stats_get_settings()</code> - <?php esc_html_e( 'خواندن تنظیمات افزونه در سایر توسعه‌ها.', 'my-fa-stats' ); ?></li>
			</ul>
			<p><?php esc_html_e( 'برای توسعه امن، همه ورودی‌ها را sanitize کنید، از nonce برای فرم‌ها استفاده کنید، و فقط با دسترسی manage_options تغییرات مدیریتی انجام دهید.', 'my-fa-stats' ); ?></p>
			<p><?php esc_html_e( 'جزئیات Payload: sid، cid، path، event، referrer، utm_* و click_id. مقادیر طولانی بریده و پاک‌سازی می‌شوند.', 'my-fa-stats' ); ?></p>
		</div>
		<?php
	}

	/**
	 * صفحه changelog.
	 *
	 * @return void
	 */
	public static function render_changelog_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما دسترسی کافی ندارید.', 'my-fa-stats' ) );
		}

		$entries = My_FA_Stats_Changelog::get_entries();
		?>
		<div class="wrap my-fa-stats-wrap" dir="rtl">
			<h1><?php esc_html_e( 'گزارش به‌روزرسانی‌ها', 'my-fa-stats' ); ?></h1>
			<p><?php esc_html_e( 'از این بخش می‌توانید تغییرات نسخه‌ها را ثبت و مشاهده کنید.', 'my-fa-stats' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="my_fa_stats_add_changelog" />
				<?php wp_nonce_field( 'my_fa_stats_add_changelog', 'my_fa_stats_changelog_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="my_fa_stats_version"><?php esc_html_e( 'نسخه', 'my-fa-stats' ); ?></label></th>
						<td><input type="text" id="my_fa_stats_version" name="version" class="regular-text" placeholder="1.2.1" required /></td>
					</tr>
					<tr>
						<th scope="row"><label for="my_fa_stats_changes"><?php esc_html_e( 'شرح تغییرات (فارسی)', 'my-fa-stats' ); ?></label></th>
						<td><textarea id="my_fa_stats_changes" name="changes" rows="5" class="large-text" required></textarea></td>
					</tr>
				</table>
				<?php submit_button( __( 'افزودن به گزارش', 'my-fa-stats' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'فهرست نسخه‌ها', 'my-fa-stats' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'نسخه', 'my-fa-stats' ); ?></th>
						<th><?php esc_html_e( 'تاریخ/زمان', 'my-fa-stats' ); ?></th>
						<th><?php esc_html_e( 'تغییرات', 'my-fa-stats' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $entries ) ) : ?>
						<tr>
							<td colspan="3"><?php esc_html_e( 'هنوز موردی ثبت نشده است.', 'my-fa-stats' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $entries as $entry ) : ?>
							<tr>
								<td><?php echo esc_html( $entry->version ); ?></td>
								<td><?php echo esc_html( $entry->created_at ); ?></td>
								<td><?php echo wp_kses_post( wpautop( $entry->changes_text ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * ذخیره تنظیمات.
	 *
	 * @return void
	 */
	public static function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما دسترسی کافی ندارید.', 'my-fa-stats' ) );
		}

		check_admin_referer( 'my_fa_stats_save_settings', 'my_fa_stats_settings_nonce' );

		$settings = array(
			'session_timeout_minutes' => max( 5, min( 120, (int) ( $_POST['session_timeout_minutes'] ?? 30 ) ) ),
			'heartbeat_interval'      => max( 10, min( 120, (int) ( $_POST['heartbeat_interval'] ?? 20 ) ) ),
			'retention_days'          => max( 0, min( 3650, (int) ( $_POST['retention_days'] ?? 0 ) ) ),
			'store_ip_hash'           => isset( $_POST['store_ip_hash'] ) ? 1 : 0,
		);

		update_option( 'my_fa_stats_settings', $settings, false );
		update_option( 'my_fa_stats_delete_on_uninstall', isset( $_POST['delete_on_uninstall'] ) ? 1 : 0, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'my-fa-stats',
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * ذخیره changelog دستی.
	 *
	 * @return void
	 */
	public static function handle_add_changelog() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما دسترسی کافی ندارید.', 'my-fa-stats' ) );
		}

		check_admin_referer( 'my_fa_stats_add_changelog', 'my_fa_stats_changelog_nonce' );

		$version = sanitize_text_field( wp_unslash( $_POST['version'] ?? '' ) );
		$changes = wp_kses_post( wp_unslash( $_POST['changes'] ?? '' ) );

		if ( '' !== $version && '' !== trim( wp_strip_all_tags( $changes ) ) ) {
			my_fa_stats_add_changelog( $version, $changes );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=my-fa-stats-changelog' ) );
		exit;
	}

	/**
	 * خروجی CSV.
	 *
	 * @return void
	 */
	public static function handle_export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما دسترسی کافی ندارید.', 'my-fa-stats' ) );
		}

		check_admin_referer( 'my_fa_stats_export_csv', 'my_fa_stats_export_nonce' );

		$month = isset( $_POST['month'] ) ? sanitize_text_field( wp_unslash( $_POST['month'] ) ) : gmdate( 'Y-m' );
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
			$month = gmdate( 'Y-m' );
		}

		$range   = self::month_range( $month );
		$sources = self::get_top_sources( $range['start'], $range['end'], 1000 );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="my-fa-stats-' . esc_attr( $month ) . '.csv"' );

		echo "\xEF\xBB\xBF";
		$output = fopen( 'php://output', 'w' );

		fputcsv( $output, array( 'source', 'medium', 'sessions', 'avg_duration_sec' ) );
		foreach ( $sources as $row ) {
			fputcsv(
				$output,
				array(
					$row->source,
					$row->medium,
					(int) $row->sessions,
					(int) $row->avg_duration,
				)
			);
		}

		fclose( $output );
		exit;
	}

	/**
	 * دریافت شاخص‌های کلیدی.
	 */
	private static function get_monthly_stats( $start, $end ) {
		global $wpdb;
		$table = $wpdb->prefix . 'my_fa_stats_sessions';
		$sql   = $wpdb->prepare(
			"SELECT COUNT(*) AS sessions,
					COUNT(DISTINCT cid) AS unique_visitors,
					COALESCE(AVG(duration_sec),0) AS avg_duration
			 FROM {$table}
			 WHERE entry_time >= %s AND entry_time < %s",
			$start,
			$end
		);
		$row   = $wpdb->get_row( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		return array(
			'sessions'        => isset( $row->sessions ) ? (int) $row->sessions : 0,
			'unique_visitors' => isset( $row->unique_visitors ) ? (int) $row->unique_visitors : 0,
			'avg_duration'    => isset( $row->avg_duration ) ? (int) $row->avg_duration : 0,
		);
	}

	/**
	 * منابع برتر.
	 */
	private static function get_top_sources( $start, $end, $limit = 20 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'my_fa_stats_sessions';
		$limit = max( 1, min( 1000, (int) $limit ) );
		$sql   = $wpdb->prepare(
			"SELECT source, medium, COUNT(*) AS sessions, COALESCE(AVG(duration_sec),0) AS avg_duration
			 FROM {$table}
			 WHERE entry_time >= %s AND entry_time < %s
			 GROUP BY source, medium
			 ORDER BY sessions DESC
			 LIMIT %d",
			$start,
			$end,
			$limit
		);

		return (array) $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/**
	 * کمپین‌های برتر.
	 */
	private static function get_top_campaigns( $start, $end ) {
		global $wpdb;
		$table = $wpdb->prefix . 'my_fa_stats_sessions';
		$sql   = $wpdb->prepare(
			"SELECT campaign, COUNT(*) AS sessions
			 FROM {$table}
			 WHERE entry_time >= %s AND entry_time < %s AND campaign <> ''
			 GROUP BY campaign
			 ORDER BY sessions DESC
			 LIMIT 20",
			$start,
			$end
		);

		return (array) $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/**
	 * صفحات فرود برتر.
	 */
	private static function get_top_landing_pages( $start, $end ) {
		global $wpdb;
		$table = $wpdb->prefix . 'my_fa_stats_sessions';
		$sql   = $wpdb->prepare(
			"SELECT landing_path, COUNT(*) AS sessions
			 FROM {$table}
			 WHERE entry_time >= %s AND entry_time < %s
			 GROUP BY landing_path
			 ORDER BY sessions DESC
			 LIMIT 20",
			$start,
			$end
		);

		return (array) $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/**
	 * رندر جدول منابع.
	 */
	private static function render_table_sources( $rows ) {
		?>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'منبع', 'my-fa-stats' ); ?></th><th><?php esc_html_e( 'مدیوم', 'my-fa-stats' ); ?></th><th><?php esc_html_e( 'تعداد نشست', 'my-fa-stats' ); ?></th><th><?php esc_html_e( 'میانگین مدت', 'my-fa-stats' ); ?></th></tr></thead>
			<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="4"><?php esc_html_e( 'داده‌ای برای این بازه وجود ندارد.', 'my-fa-stats' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row->source ); ?></td>
						<td><?php echo esc_html( $row->medium ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $row->sessions ) ); ?></td>
						<td><?php echo esc_html( self::format_duration( (int) $row->avg_duration ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * رندر جدول کمپین.
	 */
	private static function render_table_campaigns( $rows ) {
		?>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'نام کمپین', 'my-fa-stats' ); ?></th><th><?php esc_html_e( 'تعداد نشست', 'my-fa-stats' ); ?></th></tr></thead>
			<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="2"><?php esc_html_e( 'کمپینی برای این بازه ثبت نشده است.', 'my-fa-stats' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row->campaign ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $row->sessions ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * رندر جدول صفحات فرود.
	 */
	private static function render_table_landing( $rows ) {
		?>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'صفحه فرود', 'my-fa-stats' ); ?></th><th><?php esc_html_e( 'تعداد نشست', 'my-fa-stats' ); ?></th></tr></thead>
			<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="2"><?php esc_html_e( 'صفحه فرودی ثبت نشده است.', 'my-fa-stats' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row->landing_path ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $row->sessions ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * بازه زمانی ماه.
	 */
	private static function month_range( $month ) {
		$start = gmdate( 'Y-m-01 00:00:00', strtotime( $month . '-01' ) );
		$end   = gmdate( 'Y-m-01 00:00:00', strtotime( $start . ' +1 month' ) );

		return array(
			'start' => $start,
			'end'   => $end,
		);
	}

	/**
	 * فرمت مدت.
	 */
	private static function format_duration( $seconds ) {
		$seconds = max( 0, (int) $seconds );
		$hours   = floor( $seconds / HOUR_IN_SECONDS );
		$minutes = floor( ( $seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
		$secs    = $seconds % MINUTE_IN_SECONDS;

		if ( $hours > 0 ) {
			return sprintf( '%02d:%02d:%02d', $hours, $minutes, $secs );
		}

		return sprintf( '%02d:%02d', $minutes, $secs );
	}
}
