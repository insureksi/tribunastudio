<?php
/**
 * Plugin Name: Tribuna Studio Booking System
 * Plugin URI:  https://tribunastudio.com
 * Description: Multi-step studio booking system with admin approval, calendar dashboard, dan aturan pembatalan & refund berbasis jam sebelum mulai.
 * Version:     3.13.0
 * Author:      Yafet Santo
 * Author URI:  https://tribunastudio.com
 * Text Domain: tribuna-studio-booking-system
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Tribuna_Studio_Rent_Booking' ) ) :

final class Tribuna_Studio_Rent_Booking {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public $version = '3.11.0';

	/**
	 * Singleton instance.
	 *
	 * @var Tribuna_Studio_Rent_Booking|null
	 */
	protected static $instance = null;

	/**
	 * Loader.
	 *
	 * @var Tribuna_Loader
	 */
	protected $loader;

	/**
	 * Database handler.
	 *
	 * @var Tribuna_Database
	 */
	public $database;

	/**
	 * Get singleton instance.
	 *
	 * @return Tribuna_Studio_Rent_Booking
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
		private function __construct() {
			$this->define_constants();
			$this->includes();

			$this->database = new Tribuna_Database();
			$this->loader   = new Tribuna_Loader();

			$this->set_locale();
			$this->define_admin_hooks();
			$this->define_public_hooks();
			$this->define_ajax_hooks(); // <- pastikan ada $this di sini

			$this->loader->run();
		}


	/**
	 * Define plugin constants.
	 */
	private function define_constants() {
		define( 'TSRB_VERSION', $this->version );
		define( 'TSRB_PLUGIN_FILE', __FILE__ );
		define( 'TSRB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		define( 'TSRB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		define( 'TSRB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		require_once TSRB_PLUGIN_DIR . 'includes/class-tribuna-loader.php';
		require_once TSRB_PLUGIN_DIR . 'includes/class-tribuna-database.php';
		require_once TSRB_PLUGIN_DIR . 'includes/class-tribuna-booking-model.php';
		require_once TSRB_PLUGIN_DIR . 'includes/class-tribuna-coupon-model.php';
		require_once TSRB_PLUGIN_DIR . 'includes/class-tribuna-addon-model.php';
		require_once TSRB_PLUGIN_DIR . 'includes/class-tribuna-studio-model.php';
		require_once TSRB_PLUGIN_DIR . 'includes/class-tribuna-helpers.php';
		require_once TSRB_PLUGIN_DIR . 'includes/class-tribuna-exporter.php';
		require_once TSRB_PLUGIN_DIR . 'includes/class-tribuna-admin.php';
		require_once TSRB_PLUGIN_DIR . 'includes/class-tribuna-frontend.php';
		require_once TSRB_PLUGIN_DIR . 'includes/class-tribuna-booking-log-model.php';

		// Service & AJAX classes.
		require_once TSRB_PLUGIN_DIR . 'includes/class-tribuna-booking-service.php';
		require_once TSRB_PLUGIN_DIR . 'includes/class-tribuna-ajax-admin.php';
		require_once TSRB_PLUGIN_DIR . 'includes/class-tribuna-ajax-public.php';
	}

	/**
	 * Load plugin textdomain.
	 */
	private function set_locale() {
		load_plugin_textdomain(
			'tribuna-studio-rent-booking',
			false,
			dirname( TSRB_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Register admin area hooks.
	 */
	private function define_admin_hooks() {
		$plugin_admin = new Tribuna_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles_scripts' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'register_settings' );

		// admin-post handlers (CRUD).
		$this->loader->add_action( 'admin_post_tsrb_studios_form', $plugin_admin, 'handle_studios_form' );
		$this->loader->add_action( 'admin_post_tsrb_coupons_form', $plugin_admin, 'handle_coupons_form' );
		$this->loader->add_action( 'admin_post_tsrb_addons_form', $plugin_admin, 'handle_addons_form' );

		// Bulk update bookings (bookings-list bulk action).
		$this->loader->add_action( 'admin_post_tsrb_bulk_update_bookings', $plugin_admin, 'handle_bulk_update_bookings' );

		// Export handlers (bookings & members).
		$this->loader->add_action( 'admin_post_tsrb_export_bookings', $plugin_admin, 'handle_export_bookings' );
		$this->loader->add_action( 'admin_post_tsrb_export_members', $plugin_admin, 'handle_export_members' );
	}

	/**
	 * Register public hooks.
	 */
	private function define_public_hooks() {
		$plugin_public = new Tribuna_Frontend( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles_scripts' );
		$this->loader->add_action( 'init', $plugin_public, 'register_shortcodes' );
	}

	/**
	 * Register AJAX hooks (admin & public).
	 *
	 * Nama action dipertahankan agar kompatibel dengan JS existing.
	 */
	private function define_ajax_hooks() {
		$ajax_admin  = new Tribuna_Ajax_Admin();
		$ajax_public = new Tribuna_Ajax_Public();

		// -----------------------
		// Admin AJAX.
		// -----------------------
		$this->loader->add_action( 'wp_ajax_tsrb_get_admin_calendar_events', $ajax_admin, 'get_admin_calendar_events' );
		$this->loader->add_action( 'wp_ajax_tsrb_update_booking_status', $ajax_admin, 'update_booking_status' );
		$this->loader->add_action( 'wp_ajax_tsrb_get_booking_quick_view', $ajax_admin, 'get_booking_quick_view' );

		// Reschedule & availability admin.
		$this->loader->add_action( 'wp_ajax_tsrb_get_admin_availability', $ajax_admin, 'get_admin_availability' );
		$this->loader->add_action( 'wp_ajax_tsrb_admin_reschedule_booking', $ajax_admin, 'admin_reschedule_booking' );

		// -----------------------
		// Public AJAX: availability.
		// -----------------------
		$this->loader->add_action( 'wp_ajax_nopriv_tsrb_get_availability', $ajax_public, 'get_availability' );
		$this->loader->add_action( 'wp_ajax_tsrb_get_availability', $ajax_public, 'get_availability' );

		// Public AJAX: coupon validation.
		$this->loader->add_action( 'wp_ajax_nopriv_tsrb_validate_coupon', $ajax_public, 'validate_coupon' );
		$this->loader->add_action( 'wp_ajax_tsrb_validate_coupon', $ajax_public, 'validate_coupon' );

		// Public AJAX: submit booking (frontend form).
		$this->loader->add_action( 'wp_ajax_nopriv_tsrb_submit_booking', $ajax_public, 'submit_booking' );
		$this->loader->add_action( 'wp_ajax_tsrb_submit_booking', $ajax_public, 'submit_booking' );

		// Public AJAX: auth (login & register) untuk modal di frontend.
		$this->loader->add_action( 'wp_ajax_nopriv_tsrb_register_user', $ajax_public, 'register_user' );
		$this->loader->add_action( 'wp_ajax_tsrb_register_user', $ajax_public, 'register_user' );
		$this->loader->add_action( 'wp_ajax_nopriv_tsrb_login_user', $ajax_public, 'login_user' );
		$this->loader->add_action( 'wp_ajax_tsrb_login_user', $ajax_public, 'login_user' );

		// Public AJAX: refresh nonce setelah login / register via modal.
		$this->loader->add_action( 'wp_ajax_tsrb_refresh_public_nonce', $ajax_public, 'refresh_public_nonce' );
		$this->loader->add_action( 'wp_ajax_nopriv_tsrb_refresh_public_nonce', $ajax_public, 'refresh_public_nonce' );

		// Public AJAX: user dashboard (riwayat booking).
		$this->loader->add_action( 'wp_ajax_tsrb_get_user_bookings', $ajax_public, 'get_user_bookings' );

		// Public AJAX: user profile (popup).
		$this->loader->add_action( 'wp_ajax_tsrb_get_user_profile', $ajax_public, 'get_user_profile' );

		// Public AJAX update profile & change password (modal Profil Saya).
		$this->loader->add_action( 'wp_ajax_tsrb_update_user_profile', $ajax_public, 'update_user_profile' );
		$this->loader->add_action( 'wp_ajax_tsrb_change_password',     $ajax_public, 'change_user_password' );

		// Public AJAX: member request cancellation (pengajuan pembatalan dari dashboard).
		$this->loader->add_action( 'wp_ajax_tsrb_member_request_cancellation', $ajax_public, 'member_request_cancellation' );

		// Public AJAX: download invoice HTML (hanya untuk user login).
		$this->loader->add_action( 'wp_ajax_tsrb_download_invoice_html', $ajax_public, 'download_invoice_html' );
	}

	/**
	 * Get plugin name.
	 *
	 * @return string
	 */
	public function get_plugin_name() {
		return 'tribuna-studio-rent-booking';
	}

	/**
	 * Get version.
	 *
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Setup database, default settings, and roles/capabilities.
	 *
	 * Di sini kita:
	 * - Gunakan satu option key utama: tsrb_settings (baru, snake case).
	 * - Migrasi sekali jalan dari tsrb_settings (legacy camel case) jika masih ada.
	 */
	public static function activate() {
		require_once TSRB_PLUGIN_DIR . 'includes/class-tribuna-database.php';

		$database = new Tribuna_Database();
		$database->create_tables();

		// -------------------------------------------------
		// 1) Migrasi one-time dari option legacy ke baru.
		// -------------------------------------------------
		$legacy_settings = get_option( 'tsrb_settings', null );
		$new_settings    = get_option( 'tsrb_settings', null );

		if ( null === $new_settings && is_array( $legacy_settings ) ) {
			update_option( 'tsrb_settings', $legacy_settings );
			$new_settings = $legacy_settings;
		}

		// -------------------------------------------------
		// 2) Inisialisasi default tsrb_settings jika tetap kosong.
		// -------------------------------------------------
		if ( null === $new_settings || ! is_array( $new_settings ) ) {
			$default_settings = array(
				'hourly_price'        => 75000,
				'currency'            => 'IDR',
				'admin_email'         => get_option( 'admin_email' ),
				'payment_qr_image_id' => 0,
				'timezone'            => 'Asia/Jakarta',
				'operating_hours'     => array(
					'monday'    => array( 'open' => '08:00', 'close' => '22:00' ),
					'tuesday'   => array( 'open' => '08:00', 'close' => '22:00' ),
					'wednesday' => array( 'open' => '08:00', 'close' => '22:00' ),
					'thursday'  => array( 'open' => '08:00', 'close' => '22:00' ),
					'friday'    => array( 'open' => '08:00', 'close' => '22:00' ),
					'saturday'  => array( 'open' => '08:00', 'close' => '22:00' ),
					'sunday'    => array( 'open' => '08:00', 'close' => '22:00' ),
				),
				'blocked_dates'       => array(),
				'workflow'            => array(
					// Auto-cancel & lead time.
					'auto_cancel_unpaid_hours'       => 0,
					'auto_cancel_unpaid_sameday_hours' => 0,
					'min_lead_time_hours'            => 0,
					'require_manual_approval'        => 0,
					// Guard booking.
					'prevent_new_if_pending_payment' => 0,
					'max_active_bookings_per_user'   => 0,
					// Reschedule.
					'allow_member_reschedule'        => 0,
					'reschedule_cutoff_hours'        => 0,
					'reschedule_allow_pending'       => 0,
					'reschedule_admin_only'          => 0,
					// Payment deadline (timer).
					'payment_deadline_hours'         => 0,
					// Cancel & refund.
					'allow_member_cancel'            => 0,
					'refund_full_hours_before'       => 0,
					'refund_partial_hours_before'    => 0,
					'refund_partial_percent'         => 0,
					'refund_no_refund_inside_hours'  => 0,
					// Policy text (bisa diisi di Settings).
					'booking_reschedule_policy_text' => '',
					'cancel_refund_policy_text'      => '',
					'cancellation_policy_text'       => '',
				),
				'emails'              => array(),
				'integrations'        => array(),
			);

			update_option( 'tsrb_settings', $default_settings );
			$new_settings = $default_settings;
		} else {
			if ( ! isset( $new_settings['workflow'] ) || ! is_array( $new_settings['workflow'] ) ) {
				$new_settings['workflow'] = array();
			}

			$workflow = $new_settings['workflow'];

			// Backward compatibility: map key lama -> baru jika ada.
			if ( isset( $workflow['autocancel_unpaid_hours'] ) && ! isset( $workflow['auto_cancel_unpaid_hours'] ) ) {
				$workflow['auto_cancel_unpaid_hours'] = (int) $workflow['autocancel_unpaid_hours'];
				unset( $workflow['autocancel_unpaid_hours'] );
			}
			if ( isset( $workflow['autocancel_unpaid_sameday_hours'] ) && ! isset( $workflow['auto_cancel_unpaid_sameday_hours'] ) ) {
				$workflow['auto_cancel_unpaid_sameday_hours'] = (int) $workflow['autocancel_unpaid_sameday_hours'];
				unset( $workflow['autocancel_unpaid_sameday_hours'] );
			}

			$defaults_workflow = array(
				// Auto-cancel & lead time.
				'auto_cancel_unpaid_hours'         => isset( $workflow['auto_cancel_unpaid_hours'] ) ? (int) $workflow['auto_cancel_unpaid_hours'] : 0,
				'auto_cancel_unpaid_sameday_hours' => isset( $workflow['auto_cancel_unpaid_sameday_hours'] ) ? (int) $workflow['auto_cancel_unpaid_sameday_hours'] : 0,
				'min_lead_time_hours'              => isset( $workflow['min_lead_time_hours'] ) ? (int) $workflow['min_lead_time_hours'] : 0,
				'require_manual_approval'          => isset( $workflow['require_manual_approval'] ) ? (int) $workflow['require_manual_approval'] : 0,
				// Guard booking.
				'prevent_new_if_pending_payment'   => isset( $workflow['prevent_new_if_pending_payment'] ) ? (int) $workflow['prevent_new_if_pending_payment'] : 0,
				'max_active_bookings_per_user'     => isset( $workflow['max_active_bookings_per_user'] ) ? (int) $workflow['max_active_bookings_per_user'] : 0,
				// Reschedule.
				'allow_member_reschedule'          => isset( $workflow['allow_member_reschedule'] ) ? (int) $workflow['allow_member_reschedule'] : 0,
				'reschedule_cutoff_hours'          => isset( $workflow['reschedule_cutoff_hours'] ) ? (int) $workflow['reschedule_cutoff_hours'] : 0,
				'reschedule_allow_pending'         => isset( $workflow['reschedule_allow_pending'] ) ? (int) $workflow['reschedule_allow_pending'] : 0,
				'reschedule_admin_only'            => isset( $workflow['reschedule_admin_only'] ) ? (int) $workflow['reschedule_admin_only'] : 0,
				// Payment deadline (timer).
				'payment_deadline_hours'           => isset( $workflow['payment_deadline_hours'] ) ? (int) $workflow['payment_deadline_hours'] : 0,
				// Cancel & refund.
				'allow_member_cancel'              => isset( $workflow['allow_member_cancel'] ) ? (int) $workflow['allow_member_cancel'] : 0,
				'refund_full_hours_before'         => isset( $workflow['refund_full_hours_before'] ) ? (int) $workflow['refund_full_hours_before'] : 0,
				'refund_partial_hours_before'      => isset( $workflow['refund_partial_hours_before'] ) ? (int) $workflow['refund_partial_hours_before'] : 0,
				'refund_partial_percent'           => isset( $workflow['refund_partial_percent'] ) ? (int) $workflow['refund_partial_percent'] : 0,
				'refund_no_refund_inside_hours'    => isset( $workflow['refund_no_refund_inside_hours'] ) ? (int) $workflow['refund_no_refund_inside_hours'] : 0,
				// Policy text.
				'booking_reschedule_policy_text'   => isset( $workflow['booking_reschedule_policy_text'] ) ? (string) $workflow['booking_reschedule_policy_text'] : '',
				'cancel_refund_policy_text'        => isset( $workflow['cancel_refund_policy_text'] ) ? (string) $workflow['cancel_refund_policy_text'] : '',
				'cancellation_policy_text'         => isset( $workflow['cancellation_policy_text'] ) ? (string) $workflow['cancellation_policy_text'] : '',
			);

			$new_settings['workflow'] = $defaults_workflow;
			update_option( 'tsrb_settings', $new_settings );
		}

		// Setup roles & capabilities.
		self::setup_roles();

		// Jadwalkan cron untuk auto-cancel unpaid booking jika belum ada.
		if ( ! wp_next_scheduled( 'tsrb_auto_cancel_unpaid_event' ) ) {
			wp_schedule_event( time(), 'tsrb_fifteen_minutes', 'tsrb_auto_cancel_unpaid_event' );
		}
	}

	/**
	 * Create / update plugin roles & capabilities.
	 */
	protected static function setup_roles() {
		$cap_all      = 'manage_tsrb_all';
		$cap_bookings = 'manage_tsrb_bookings';

		// Studio Manager / Owner.
		$manager_role = get_role( 'tsrb_manager' );
		if ( ! $manager_role ) {
			$manager_role = add_role(
				'tsrb_manager',
				'Studio Manager',
				array(
					'read'        => true,
					$cap_all      => true,
					$cap_bookings => true,
				)
			);
		} else {
			$manager_role->add_cap( 'read' );
			$manager_role->add_cap( $cap_all );
			$manager_role->add_cap( $cap_bookings );
		}

		// Booking Admin / CS.
		$booking_role = get_role( 'tsrb_booking_admin' );
		if ( ! $booking_role ) {
			$booking_role = add_role(
				'tsrb_booking_admin',
				'Booking Admin',
				array(
					'read'        => true,
					$cap_bookings => true,
				)
			);
		} else {
			$booking_role->add_cap( 'read' );
			$booking_role->add_cap( $cap_bookings );
		}

		// Tribuna Member role (front-end customers).
		$member_role = get_role( 'tribuna_member' );
		if ( ! $member_role ) {
			add_role(
				'tribuna_member',
				'Tribuna Member',
				array(
					'read'    => true,
					'level_0' => true,
				)
			);
		} else {
			$member_role->add_cap( 'read' );
			$member_role->add_cap( 'level_0' );
		}

		// Administrator caps.
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->add_cap( $cap_all );
			$admin_role->add_cap( $cap_bookings );
		}
	}

	/**
	 * Deactivate callback.
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'tsrb_auto_cancel_unpaid_event' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'tsrb_auto_cancel_unpaid_event' );
		}
	}

}

endif;

/**
 * Frontend profile handlers (admin-post).
 */
add_action( 'admin_post_tsrb_update_profile', 'tsrb_handle_update_profile' );
add_action( 'admin_post_nopriv_tsrb_update_profile', 'tsrb_handle_update_profile' );

/**
 * Handle profile update.
 */
function tsrb_handle_update_profile() {
	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	if ( ! isset( $_POST['tsrb_profile_nonce'] ) || ! wp_verify_nonce( $_POST['tsrb_profile_nonce'], 'tsrb_profile_update' ) ) {
		wp_safe_redirect(
			add_query_arg(
				'error',
				rawurlencode( __( 'Security check failed.', 'tribuna-studio-rent-booking' ) ),
				wp_get_referer()
			)
		);
		exit;
	}

	$user_id   = get_current_user_id();
	$full_name = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
	$email     = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$whatsapp  = isset( $_POST['whatsapp'] ) ? sanitize_text_field( wp_unslash( $_POST['whatsapp'] ) ) : '';

	if ( empty( $full_name ) || empty( $email ) || ! is_email( $email ) ) {
		wp_safe_redirect(
			add_query_arg(
				'error',
				rawurlencode( __( 'Please enter a valid name and email.', 'tribuna-studio-rent-booking' ) ),
				wp_get_referer()
			)
		);
		exit;
	}

	$existing = get_user_by( 'email', $email );
	if ( $existing && (int) $existing->ID !== (int) $user_id ) {
		wp_safe_redirect(
			add_query_arg(
				'error',
				rawurlencode( __( 'This email is already used by another account.', 'tribuna-studio-rent-booking' ) ),
				wp_get_referer()
			)
		);
		exit;
	}

	wp_update_user(
		array(
			'ID'           => $user_id,
			'display_name' => $full_name,
			'user_email'   => $email,
		)
	);

	$first_name = '';
	$last_name  = '';

	if ( $full_name ) {
		$parts = preg_split( '/\s+/', trim( $full_name ) );
		if ( ! empty( $parts ) ) {
			$first_name = array_shift( $parts );
			$last_name  = implode( ' ', $parts );
		}
	}

	if ( $first_name ) {
		update_user_meta( $user_id, 'first_name', $first_name );
	}
	if ( $last_name ) {
		update_user_meta( $user_id, 'last_name', $last_name );
	}

	update_user_meta( $user_id, 'tsrb_whatsapp', $whatsapp );

	wp_safe_redirect( add_query_arg( 'updated', '1', wp_get_referer() ) );
	exit;
}

// Change password.
add_action( 'admin_post_tsrb_change_password', 'tsrb_handle_change_password' );
add_action( 'admin_post_nopriv_tsrb_change_password', 'tsrb_handle_change_password' );

/**
 * Handle password change.
 */
function tsrb_handle_change_password() {
	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	if ( ! isset( $_POST['tsrb_profile_nonce'] ) || ! wp_verify_nonce( $_POST['tsrb_profile_nonce'], 'tsrb_profile_update' ) ) {
		wp_safe_redirect(
			add_query_arg(
				'error',
				rawurlencode( __( 'Security check failed.', 'tribuna-studio-rent-booking' ) ),
				wp_get_referer()
			)
		);
		exit;
	}

	$user_id           = get_current_user_id();
	$current_password  = isset( $_POST['current_password'] ) ? (string) wp_unslash( $_POST['current_password'] ) : '';
	$new_password      = isset( $_POST['new_password'] ) ? (string) wp_unslash( $_POST['new_password'] ) : '';
	$new_password_conf = isset( $_POST['new_password_confirm'] ) ? (string) wp_unslash( $_POST['new_password_confirm'] ) : '';

	if ( '' === $current_password && '' === $new_password && '' === $new_password_conf ) {
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	if ( empty( $current_password ) || empty( $new_password ) || empty( $new_password_conf ) ) {
		wp_safe_redirect(
			add_query_arg(
				'error',
				rawurlencode( __( 'Please fill all password fields.', 'tribuna-studio-rent-booking' ) ),
				wp_get_referer()
			)
		);
		exit;
	}

	if ( strlen( $new_password ) < 8 ) {
		wp_safe_redirect(
			add_query_arg(
				'error',
				rawurlencode( __( 'New password must be at least 8 characters.', 'tribuna-studio-rent-booking' ) ),
				wp_get_referer()
			)
		);
		exit;
	}

	if ( $new_password !== $new_password_conf ) {
		wp_safe_redirect(
			add_query_arg(
				'error',
				rawurlencode( __( 'Password confirmation does not match.', 'tribuna-studio-rent-booking' ) ),
				wp_get_referer()
			)
		);
		exit;
	}

	$user = get_userdata( $user_id );
	if ( ! $user || ! wp_check_password( $current_password, $user->user_pass, $user_id ) ) {
		wp_safe_redirect(
			add_query_arg(
				'error',
				rawurlencode( __( 'Current password is incorrect.', 'tribuna-studio-rent-booking' ) ),
				wp_get_referer()
			)
		);
		exit;
	}

	wp_set_password( $new_password, $user_id );

	wp_set_auth_cookie( $user_id );
	wp_set_current_user( $user_id );

	wp_safe_redirect( add_query_arg( 'pw_changed', '1', wp_get_referer() ) );
	exit;
}

/**
 * Batasi tribuna_member dari akses wp-admin (kecuali admin-ajax & admin-post).
 */
add_action( 'admin_init', 'tsrb_restrict_member_admin_access' );
function tsrb_restrict_member_admin_access() {
	if ( ! is_user_logged_in() ) {
		return;
	}

	$user = wp_get_current_user();
	if ( empty( $user->ID ) ) {
		return;
	}

	if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_tsrb_all' ) ) {
		return;
	}

	if ( in_array( 'tribuna_member', (array) $user->roles, true ) ) {
		$script = isset( $_SERVER['SCRIPT_NAME'] ) ? wp_basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) : '';

		if ( in_array( $script, array( 'admin-ajax.php', 'admin-post.php' ), true ) ) {
			return;
		}

		if ( is_admin() ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}
	}
}

/**
 * Sembunyikan menu WordPress umum untuk tsrb_manager dan tsrb_booking_admin.
 */
add_action(
	'admin_menu',
	function () {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user  = wp_get_current_user();
		$roles = (array) $user->roles;

		if ( in_array( 'tsrb_manager', $roles, true ) || in_array( 'tsrb_booking_admin', $roles, true ) ) {
			remove_menu_page( 'index.php' );
			remove_menu_page( 'upload.php' );
			remove_menu_page( 'edit.php' );
			remove_menu_page( 'edit.php?post_type=page' );
			remove_menu_page( 'edit-comments.php' );
			remove_menu_page( 'themes.php' );
			remove_menu_page( 'plugins.php' );
			remove_menu_page( 'users.php' );
			remove_menu_page( 'tools.php' );
			remove_menu_page( 'options-general.php' );
		}
	},
	999
);

/**
 * Tambah custom cron interval 15 menit untuk auto-cancel unpaid.
 */
add_filter( 'cron_schedules', 'tsrb_add_cron_intervals' );
function tsrb_add_cron_intervals( $schedules ) {
	if ( ! isset( $schedules['tsrb_fifteen_minutes'] ) ) {
		$schedules['tsrb_fifteen_minutes'] = array(
			'interval' => 15 * 60,
			'display'  => __( 'Every 15 Minutes (TSRB)', 'tribuna-studio-rent-booking' ),
		);
	}

	return $schedules;
}

/**
 * Hook untuk event cron auto-cancel unpaid bookings.
 */
add_action( 'tsrb_auto_cancel_unpaid_event', 'tsrb_handle_auto_cancel_unpaid' );
function tsrb_handle_auto_cancel_unpaid() {
	if ( ! class_exists( 'Tribuna_Booking_Service' ) ) {
		require_once TSRB_PLUGIN_DIR . 'includes/class-tribuna-booking-service.php';
	}

	$service = new Tribuna_Booking_Service();
	$service->auto_cancel_unpaid_bookings();
}

/**
 * Bootstrap plugin.
 *
 * @return Tribuna_Studio_Rent_Booking
 */
function tsrb_run_plugin() {
	return Tribuna_Studio_Rent_Booking::instance();
}

register_activation_hook( __FILE__, array( 'Tribuna_Studio_Rent_Booking', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Tribuna_Studio_Rent_Booking', 'deactivate' ) );

tsrb_run_plugin();
