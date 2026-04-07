<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin area.
 */
class Tribuna_Admin {

	/**
	 * Plugin name.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Models.
	 */
	private $booking_model;
	private $coupon_model;
	private $addon_model;
	private $studio_model;

	/**
	 * Services.
	 *
	 * @var Tribuna_Booking_Service|null
	 */
	private $booking_service;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_name Plugin name.
	 * @param string $version     Plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name     = $plugin_name;
		$this->version         = $version;
		$this->booking_model   = new Tribuna_Booking_Model();
		$this->coupon_model    = new Tribuna_Coupon_Model();
		$this->addon_model     = new Tribuna_Addon_Model();
		$this->studio_model    = new Tribuna_Studio_Model();
		$this->booking_service = class_exists( 'Tribuna_Booking_Service' ) ? new Tribuna_Booking_Service() : null;

		// Settings dan capability override untuk options.php.
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'option_page_capability_tsrb_settings_group', array( $this, 'filter_settings_capability' ) );

		// AJAX lama (compat): proses cancellation approve/reject.
		add_action( 'wp_ajax_tsrb_admin_process_cancel', array( $this, 'ajax_process_cancel' ) );
		// AJAX baru (dipakai oleh admin/js/tribuna-admin.js).
		add_action( 'wp_ajax_tsrb_admin_process_cancellation', array( $this, 'ajax_process_cancellation' ) );
	}

	/**
	 * Register admin menu.
	 */
	public function add_admin_menu() {
		$cap_admin    = Tribuna_Helpers::admin_capability();    // manage_tsrb_all.
		$cap_bookings = Tribuna_Helpers::booking_capability();  // manage_tsrb_bookings.

		// Top-level menu: hanya Manager/Owner (full access) + Administrator.
		$hook = add_menu_page(
			__( 'Tribuna Studio Booking', 'tribuna-studio-rent-booking' ),
			__( 'Tribuna Booking', 'tribuna-studio-rent-booking' ),
			$cap_admin,
			'tsrb-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-calendar-alt',
			26
		);

		// Dashboard: hanya Manager/Owner.
		add_submenu_page(
			'tsrb-dashboard',
			__( 'Dashboard', 'tribuna-studio-rent-booking' ),
			__( 'Dashboard', 'tribuna-studio-rent-booking' ),
			$cap_admin,
			'tsrb-dashboard',
			array( $this, 'render_dashboard' )
		);

		// Bookings: boleh diakses Manager & Booking Admin/CS.
		add_submenu_page(
			'tsrb-dashboard',
			__( 'Bookings', 'tribuna-studio-rent-booking' ),
			__( 'Bookings', 'tribuna-studio-rent-booking' ),
			$cap_bookings,
			'tsrb-bookings',
			array( $this, 'render_bookings' )
		);

		// Studios: hanya Manager/Owner.
		add_submenu_page(
			'tsrb-dashboard',
			__( 'Studios', 'tribuna-studio-rent-booking' ),
			__( 'Studios', 'tribuna-studio-rent-booking' ),
			$cap_admin,
			'tsrb-studios',
			array( $this, 'render_studios' )
		);

		// Coupons: hanya Manager/Owner.
		add_submenu_page(
			'tsrb-dashboard',
			__( 'Coupons', 'tribuna-studio-rent-booking' ),
			__( 'Coupons', 'tribuna-studio-rent-booking' ),
			$cap_admin,
			'tsrb-coupons',
			array( $this, 'render_coupons' )
		);

		// Add-ons: hanya Manager/Owner.
		add_submenu_page(
			'tsrb-dashboard',
			__( 'Add-ons', 'tribuna-studio-rent-booking' ),
			__( 'Add-ons', 'tribuna-studio-rent-booking' ),
			$cap_admin,
			'tsrb-addons',
			array( $this, 'render_addons' )
		);

		// Members: Manager + Admin Booking (pakai capability bookings).
		add_submenu_page(
			'tsrb-dashboard',
			__( 'Members', 'tribuna-studio-rent-booking' ),
			__( 'Members', 'tribuna-studio-rent-booking' ),
			$cap_bookings,
			'tsrb-members',
			array( $this, 'render_members' )
		);

		// Settings: hanya Manager/Owner.
		add_submenu_page(
			'tsrb-dashboard',
			__( 'Settings', 'tribuna-studio-rent-booking' ),
			__( 'Settings', 'tribuna-studio-rent-booking' ),
			$cap_admin,
			'tsrb-settings',
			array( $this, 'render_settings' )
		);

		add_action(
			"load-{$hook}",
			array( $this, 'screen_options' )
		);
	}

	/**
	 * Screen options (reserved).
	 */
	public function screen_options() {
		// Reserved for list table options.
	}

	/**
	 * Enqueue admin styles & scripts.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_styles_scripts( $hook ) {
		if ( false === strpos( $hook, 'tsrb-' ) ) {
			return;
		}

		wp_enqueue_media();

		// Admin core styles.
		wp_enqueue_style(
			'tsrb-admin',
			TSRB_PLUGIN_URL . 'admin/css/tribuna-admin.css',
			array(),
			$this->version
		);

		// Optional: reuse frontend styles untuk konsistensi visual.
		wp_enqueue_style(
			'tsrb-public-styles-for-admin',
			TSRB_PLUGIN_URL . 'public/css/tribuna-public.css',
			array(),
			$this->version
		);

		wp_enqueue_style(
			'tsrb-fullcalendar',
			'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css',
			array(),
			'5.11.3'
		);

		wp_enqueue_script(
			'tsrb-fullcalendar',
			'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js',
			array( 'jquery' ),
			'5.11.3',
			true
		);

		wp_enqueue_script(
			'tsrb-admin',
			TSRB_PLUGIN_URL . 'admin/js/tribuna-admin.js',
			array( 'jquery', 'tsrb-fullcalendar', 'media-editor', 'media-upload' ),
			$this->version,
			true
		);

		wp_localize_script(
			'tsrb-admin',
			'TSRBAdmin',
			array(
				'ajaxurl'              => admin_url( 'admin-ajax.php' ),
				'nonce'                => wp_create_nonce( 'tsrb_admin_nonce' ),
				'bulkconfirm'          => __( 'Apply bulk action to selected bookings?', 'tribuna-studio-rent-booking' ),
				'loadingtext'          => __( 'Loading booking details...', 'tribuna-studio-rent-booking' ),

				// Tambahan untuk fitur reschedule admin (dipakai di tribuna-admin.js).
				'rescheduleincomplete' => __( 'Please select a new date and time slot.', 'tribuna-studio-rent-booking' ),
				'rescheduleprocessing' => __( 'Saving new schedule...', 'tribuna-studio-rent-booking' ),
				'reschedulesuccess'    => __( 'Schedule updated.', 'tribuna-studio-rent-booking' ),
				'rescheduleerror'      => __( 'Failed to update schedule.', 'tribuna-studio-rent-booking' ),

				// Tambahan untuk fitur cancellation/refund admin.
				'cancelConfirm'        => __( 'Are you sure you want to cancel this booking?', 'tribuna-studio-rent-booking' ),
				'cancelApproveConfirm' => __( 'Approve this cancellation and apply refund/credit?', 'tribuna-studio-rent-booking' ),
				'cancelRejectConfirm'  => __( 'Reject this cancellation request?', 'tribuna-studio-rent-booking' ),
				'cancelProcessing'     => __( 'Processing cancellation...', 'tribuna-studio-rent-booking' ),
				'cancelSuccess'        => __( 'Cancellation processed.', 'tribuna-studio-rent-booking' ),
				'cancelError'          => __( 'Failed to process cancellation.', 'tribuna-studio-rent-booking' ),
			)
		);
	}

	/**
	 * Register settings (tsrb_settings_group / tsrb_settings).
	 *
	 * Kita simpan ke option utama 'tsrb_settings'.
	 */
	public function register_settings() {
		register_setting(
			'tsrb_settings_group',
			'tsrb_settings',
			array( $this, 'sanitize_settings' )
		);
	}

	/**
	 * Override capability untuk group tsrb_settings_group (pakai manage_tsrb_all).
	 *
	 * @param string $capability Default capability.
	 * @return string
	 */
	public function filter_settings_capability( $capability ) {
		return Tribuna_Helpers::admin_capability();
	}

	/**
	 * Sanitize settings array (all tabs: General, Emails, Workflow, Integrations).
	 *
	 * @param array $settings Raw settings coming from form (subset per tab).
	 * @return array
	 */
	public function sanitize_settings( $settings ) {
		// Basis ambil dari helper (satu sumber utama tsrb_settings + defaults).
		$old = Tribuna_Helpers::get_settings();
		if ( ! is_array( $old ) ) {
			$old = array();
		}

		$clean = $old;

		// ---------- GENERAL ----------
		$clean['currency'] = isset( $settings['currency'] )
			? sanitize_text_field( $settings['currency'] )
			: ( isset( $old['currency'] ) ? $old['currency'] : 'IDR' );

		$clean['admin_email'] = isset( $settings['admin_email'] )
			? sanitize_email( $settings['admin_email'] )
			: ( isset( $old['admin_email'] ) ? $old['admin_email'] : get_option( 'admin_email' ) );

		$clean['timezone'] = isset( $settings['timezone'] )
			? sanitize_text_field( $settings['timezone'] )
			: ( isset( $old['timezone'] ) ? $old['timezone'] : 'Asia/Jakarta' );

		// QR Code image ID.
		$clean['payment_qr_image_id'] = isset( $settings['payment_qr_image_id'] )
			? (int) $settings['payment_qr_image_id']
			: ( isset( $old['payment_qr_image_id'] ) ? (int) $old['payment_qr_image_id'] : 0 );

		// Nomor WhatsApp admin.
		$clean['admin_whatsapp_number'] = isset( $settings['admin_whatsapp_number'] )
			? sanitize_text_field( wp_unslash( $settings['admin_whatsapp_number'] ) )
			: ( isset( $old['admin_whatsapp_number'] ) ? $old['admin_whatsapp_number'] : '' );

		// Operating hours.
		$clean['operating_hours'] = isset( $old['operating_hours'] ) && is_array( $old['operating_hours'] ) ? $old['operating_hours'] : array();
		$days                     = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );

		if ( isset( $settings['operating_hours'] ) && is_array( $settings['operating_hours'] ) ) {
			foreach ( $days as $day ) {
				$open  = isset( $settings['operating_hours'][ $day ]['open'] ) ? sanitize_text_field( $settings['operating_hours'][ $day ]['open'] ) : '';
				$close = isset( $settings['operating_hours'][ $day ]['close'] ) ? sanitize_text_field( $settings['operating_hours'][ $day ]['close'] ) : '';

				$clean['operating_hours'][ $day ] = array(
					'open'  => $open,
					'close' => $close,
				);
			}
		} else {
			foreach ( $days as $day ) {
				if ( ! isset( $clean['operating_hours'][ $day ] ) ) {
					$clean['operating_hours'][ $day ] = array(
						'open'  => '',
						'close' => '',
					);
				}
			}
		}

		// Blocked dates.
		if ( isset( $settings['blocked_dates'] ) && is_array( $settings['blocked_dates'] ) ) {
			$clean['blocked_dates'] = array();
			foreach ( $settings['blocked_dates'] as $date ) {
				if ( '' !== trim( (string) $date ) ) {
					$clean['blocked_dates'][] = sanitize_text_field( $date );
				}
			}
		} elseif ( ! isset( $clean['blocked_dates'] ) || ! is_array( $clean['blocked_dates'] ) ) {
			$clean['blocked_dates'] = array();
		}

		// ---------- EMAIL TEMPLATES ----------
		$email_defaults = array(
			'customer_new_subject'        => __( 'Your booking request has been received', 'tribuna-studio-rent-booking' ),
			'customer_new_body'           => __(
				"Hi {customer_name},\n\nThank you for your booking request for {studio_name} on {booking_date} at {start_time}.\n\nTotal: {total}\nStatus: {status}\n\nBest regards,\n{site_name}",
				'tribuna-studio-rent-booking'
			),
			'customer_paid_subject'       => __( 'Your booking is confirmed', 'tribuna-studio-rent-booking' ),
			'customer_paid_body'          => __(
				"Hi {customer_name},\n\nYour booking is now confirmed.\n\nStudio: {studio_name}\nDate: {booking_date}\nTime: {start_time} - {end_time}\nTotal: {total}\n\nWe look forward to seeing you.\n{site_name}",
				'tribuna-studio-rent-booking'
			),
			'customer_cancel_subject'     => __( 'Your booking has been cancelled', 'tribuna-studio-rent-booking' ),
			'customer_cancel_body'        => __(
				"Hi {customer_name},\n\nYour booking for {studio_name} on {booking_date} has been cancelled.\n\nIf this was not intended, please contact us.\n{site_name}",
				'tribuna-studio-rent-booking'
			),
			'admin_new_subject'           => __( 'New booking received', 'tribuna-studio-rent-booking' ),
			'admin_new_body'              => __(
				"New booking received:\n\nCustomer: {customer_name}\nStudio: {studio_name}\nDate: {booking_date}\nTime: {start_time} - {end_time}\nTotal: {total}\nStatus: {status}\n\nBooking ID: {booking_id}",
				'tribuna-studio-rent-booking'
			),
			'customer_reschedule_subject' => __( 'Your booking has been rescheduled', 'tribuna-studio-rent-booking' ),
			'customer_reschedule_body'    => __(
				"Hi {customer_name},\n\nYour booking has been rescheduled.\n\nStudio: {studio_name}\nOld schedule: {old_booking_date}, {old_start_time} - {old_end_time}\nNew schedule: {booking_date}, {start_time} - {end_time}\nTotal (unchanged): {total}\nStatus: {status}\n\nIf you did not request this change, please contact us.\n{site_name}",
				'tribuna-studio-rent-booking'
			),
		);

		$existing_emails = isset( $old['emails'] ) && is_array( $old['emails'] ) ? $old['emails'] : array();
		$new_emails      = isset( $settings['emails'] ) && is_array( $settings['emails'] ) ? $settings['emails'] : array();

		$merged_emails = wp_parse_args( $new_emails, $existing_emails );
		$merged_emails = wp_parse_args( $merged_emails, $email_defaults );

		$clean['emails'] = array();

		foreach ( $email_defaults as $key => $default_val ) {
			$val = isset( $merged_emails[ $key ] ) ? $merged_emails[ $key ] : $default_val;

			if ( false !== strpos( $key, '_body' ) ) {
				$clean['emails'][ $key ] = wp_kses_post( $val );
			} else {
				$clean['emails'][ $key ] = sanitize_text_field( $val );
			}
		}

		// ---------- WORKFLOW & POLICIES ----------
		$workflow_defaults = array(
			// Auto-cancel & lead time.
			'auto_cancel_unpaid_hours'         => 0,
			'auto_cancel_unpaid_sameday_hours' => 0,
			'min_lead_time_hours'              => 0,
			'require_manual_approval'          => 0,
			// Field baru untuk informasi ke pelanggan.
			'booking_reschedule_policy_text'   => '',
			'cancel_refund_policy_text'        => '',
			// Field lama tetap disimpan sebagai legacy/fallback.
			'cancellation_policy_text'         => '',
			// Guard booking.
			'prevent_new_if_pending_payment'   => 0,
			'max_active_bookings_per_user'     => 0,
			// Aturan reschedule.
			'allow_member_reschedule'          => 0,
			'reschedule_cutoff_hours'          => 0,
			'reschedule_allow_pending'         => 0,
			'reschedule_admin_only'            => 0,
			// Batas waktu pembayaran dalam jam untuk timer & auto-expire.
			'payment_deadline_hours'           => 0,
			// Aturan refund & credit.
			'refund_full_hours_before'         => 24,
			'refund_partial_hours_before'      => 3,
			'refund_partial_percent'           => 70,
			'refund_no_refund_inside_hours'    => 0,
			// Izinkan member request cancellation.
			'allow_member_cancel'              => 0,
		);

		$existing_workflow = isset( $old['workflow'] ) && is_array( $old['workflow'] ) ? $old['workflow'] : array();
		$wf_in             = isset( $settings['workflow'] ) && is_array( $settings['workflow'] ) ? $settings['workflow'] : array();

		// Backward-compat: map key lama ke baru jika masih ada.
		if ( isset( $existing_workflow['autocancel_unpaid_hours'] ) && ! isset( $existing_workflow['auto_cancel_unpaid_hours'] ) ) {
			$existing_workflow['auto_cancel_unpaid_hours'] = (int) $existing_workflow['autocancel_unpaid_hours'];
		}
		if ( isset( $existing_workflow['autocancel_unpaid_sameday_hours'] ) && ! isset( $existing_workflow['auto_cancel_unpaid_sameday_hours'] ) ) {
			$existing_workflow['auto_cancel_unpaid_sameday_hours'] = (int) $existing_workflow['autocancel_unpaid_sameday_hours'];
		}
		// Jaga juga varian typo lama.
		if ( isset( $existing_workflow['auto_cancel_unpaid_same_day_hours'] ) && ! isset( $existing_workflow['auto_cancel_unpaid_sameday_hours'] ) ) {
			$existing_workflow['auto_cancel_unpaid_sameday_hours'] = (int) $existing_workflow['auto_cancel_unpaid_same_day_hours'];
		}

		$merged_workflow = wp_parse_args( $existing_workflow, $workflow_defaults );

		// FIX: Jika form tidak mengirim 'workflow' (misalnya saat save tab Email),
		// jangan reset semua checkbox ke 0. Pertahankan workflow lama apa adanya.
		if ( empty( $wf_in ) ) {
			$clean['workflow'] = $merged_workflow;
		} else {
			$clean['workflow'] = array(
				'auto_cancel_unpaid_hours'         => isset( $wf_in['auto_cancel_unpaid_hours'] )
					? max( 0, (int) $wf_in['auto_cancel_unpaid_hours'] )
					: max( 0, (int) $merged_workflow['auto_cancel_unpaid_hours'] ),

				'auto_cancel_unpaid_sameday_hours' => isset( $wf_in['auto_cancel_unpaid_sameday_hours'] )
					? max( 0, (int) $wf_in['auto_cancel_unpaid_sameday_hours'] )
					: max( 0, (int) $merged_workflow['auto_cancel_unpaid_sameday_hours'] ),

				'min_lead_time_hours'              => isset( $wf_in['min_lead_time_hours'] )
					? max( 0, (int) $wf_in['min_lead_time_hours'] )
					: max( 0, (int) $merged_workflow['min_lead_time_hours'] ),

				'require_manual_approval'          => isset( $wf_in['require_manual_approval'] )
					? ( ! empty( $wf_in['require_manual_approval'] ) ? 1 : 0 )
					: ( ! empty( $merged_workflow['require_manual_approval'] ) ? 1 : 0 ),

				'cancellation_policy_text'         => isset( $wf_in['cancellation_policy_text'] )
					? wp_kses_post( $wf_in['cancellation_policy_text'] )
					: ( isset( $merged_workflow['cancellation_policy_text'] ) ? wp_kses_post( $merged_workflow['cancellation_policy_text'] ) : '' ),

				// Field baru: kebijakan booking & reschedule.
				'booking_reschedule_policy_text'   => isset( $wf_in['booking_reschedule_policy_text'] )
					? wp_kses_post( $wf_in['booking_reschedule_policy_text'] )
					: ( isset( $merged_workflow['booking_reschedule_policy_text'] ) ? wp_kses_post( $merged_workflow['booking_reschedule_policy_text'] ) : '' ),

				// Field baru: kebijakan cancellation & refund.
				'cancel_refund_policy_text'        => isset( $wf_in['cancel_refund_policy_text'] )
					? wp_kses_post( $wf_in['cancel_refund_policy_text'] )
					: ( isset( $merged_workflow['cancel_refund_policy_text'] ) ? wp_kses_post( $merged_workflow['cancel_refund_policy_text'] ) : '' ),

				'prevent_new_if_pending_payment'   => isset( $wf_in['prevent_new_if_pending_payment'] )
					? ( ! empty( $wf_in['prevent_new_if_pending_payment'] ) ? 1 : 0 )
					: ( ! empty( $merged_workflow['prevent_new_if_pending_payment'] ) ? 1 : 0 ),

				'max_active_bookings_per_user'     => isset( $wf_in['max_active_bookings_per_user'] )
					? max( 0, (int) $wf_in['max_active_bookings_per_user'] )
					: max( 0, (int) $merged_workflow['max_active_bookings_per_user'] ),

				'allow_member_reschedule'          => isset( $wf_in['allow_member_reschedule'] )
					? ( ! empty( $wf_in['allow_member_reschedule'] ) ? 1 : 0 )
					: ( ! empty( $merged_workflow['allow_member_reschedule'] ) ? 1 : 0 ),

				'reschedule_cutoff_hours'          => isset( $wf_in['reschedule_cutoff_hours'] )
					? max( 0, (int) $wf_in['reschedule_cutoff_hours'] )
					: max( 0, (int) $merged_workflow['reschedule_cutoff_hours'] ),

				'reschedule_allow_pending'         => isset( $wf_in['reschedule_allow_pending'] )
					? ( ! empty( $wf_in['reschedule_allow_pending'] ) ? 1 : 0 )
					: ( ! empty( $merged_workflow['reschedule_allow_pending'] ) ? 1 : 0 ),

				'reschedule_admin_only'            => isset( $wf_in['reschedule_admin_only'] )
					? ( ! empty( $wf_in['reschedule_admin_only'] ) ? 1 : 0 )
					: ( ! empty( $merged_workflow['reschedule_admin_only'] ) ? 1 : 0 ),

				'payment_deadline_hours'           => isset( $wf_in['payment_deadline_hours'] )
					? max( 0, (int) $wf_in['payment_deadline_hours'] )
					: max( 0, (int) $merged_workflow['payment_deadline_hours'] ),

				'refund_full_hours_before'         => isset( $wf_in['refund_full_hours_before'] )
					? max( 0, (int) $wf_in['refund_full_hours_before'] )
					: max( 0, (int) $merged_workflow['refund_full_hours_before'] ),

				'refund_partial_hours_before'      => isset( $wf_in['refund_partial_hours_before'] )
					? max( 0, (int) $wf_in['refund_partial_hours_before'] )
					: max( 0, (int) $merged_workflow['refund_partial_hours_before'] ),

				'refund_partial_percent'           => isset( $wf_in['refund_partial_percent'] )
					? max( 0, min( 100, (int) $wf_in['refund_partial_percent'] ) )
					: max( 0, min( 100, (int) $merged_workflow['refund_partial_percent'] ) ),

				'refund_no_refund_inside_hours'    => isset( $wf_in['refund_no_refund_inside_hours'] )
					? max( 0, (int) $wf_in['refund_no_refund_inside_hours'] )
					: max( 0, (int) $merged_workflow['refund_no_refund_inside_hours'] ),

				// Checkbox untuk request cancellation oleh member.
				'allow_member_cancel'              => isset( $wf_in['allow_member_cancel'] )
					? ( ! empty( $wf_in['allow_member_cancel'] ) ? 1 : 0 )
					: ( ! empty( $merged_workflow['allow_member_cancel'] ) ? 1 : 0 ),
			);
		}

		// ---------- INTEGRATIONS ----------
		$integrations_defaults = array(
			'google_calendar_enabled'  => 0,
			'google_calendar_id'       => '',
			'google_client_id'         => '',
			'google_client_secret'     => '',
			'whatsapp_default_message' => __( 'Hi, I would like to ask about my booking ({booking_id}) on {booking_date}.', 'tribuna-studio-rent-booking' ),
		);

		$existing_integrations = isset( $old['integrations'] ) && is_array( $old['integrations'] ) ? $old['integrations'] : array();
		$new_integrations      = isset( $settings['integrations'] ) && is_array( $settings['integrations'] ) ? $settings['integrations'] : array();

		$merged_integrations = wp_parse_args( $new_integrations, $existing_integrations );
		$merged_integrations = wp_parse_args( $merged_integrations, $integrations_defaults );

		$clean['integrations'] = array(
			'google_calendar_enabled'  => ! empty( $merged_integrations['google_calendar_enabled'] ) ? 1 : 0,
			'google_calendar_id'       => isset( $merged_integrations['google_calendar_id'] ) ? sanitize_text_field( $merged_integrations['google_calendar_id'] ) : '',
			'google_client_id'         => isset( $merged_integrations['google_client_id'] ) ? sanitize_text_field( $merged_integrations['google_client_id'] ) : '',
			'google_client_secret'     => isset( $merged_integrations['google_client_secret'] ) ? sanitize_text_field( $merged_integrations['google_client_secret'] ) : '',
			'whatsapp_default_message' => isset( $merged_integrations['whatsapp_default_message'] ) ? wp_kses_post( $merged_integrations['whatsapp_default_message'] ) : $integrations_defaults['whatsapp_default_message'],
		);

		return $clean;
	}

	/**
	 * Dashboard page.
	 */
	public function render_dashboard() {
		if ( ! current_user_can( Tribuna_Helpers::admin_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'tribuna-studio-rent-booking' ) );
		}

		$month     = current_time( 'Y-m' );
		$total     = $this->booking_model->count_by_status();
		$pending   = $this->booking_model->count_by_status( 'pending_payment' );
		$confirmed = $this->booking_model->count_by_status( 'paid' );
		$cancelled = $this->booking_model->count_by_status( 'cancelled' );
		$revenue   = $this->booking_model->get_monthly_revenue( $month );

		include TSRB_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Bookings page.
	 */
	public function render_bookings() {
		if ( ! current_user_can( Tribuna_Helpers::booking_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'tribuna-studio-rent-booking' ) );
		}

		$view = isset( $_GET['view'] ) ? sanitize_text_field( wp_unslash( $_GET['view'] ) ) : '';

		if ( 'edit' === $view && ! empty( $_GET['id'] ) ) {
			$id      = (int) $_GET['id'];
			$booking = $this->booking_model->get( $id );

			// Ambil log aktivitas booking jika model tersedia.
			$logs = array();
			if ( class_exists( 'Tribuna_Booking_Log_Model' ) ) {
				$log_model = new Tribuna_Booking_Log_Model();
				$logs      = $log_model->get_by_booking_id( $id );
			}

			// Policy cancellation dan hasil evaluasi (untuk info di detail).
			$cancel_policy = array();
			if ( $this->booking_service && $booking && method_exists( $this->booking_service, 'evaluate_cancellation_policy' ) ) {
				$cancel_policy = $this->booking_service->evaluate_cancellation_policy( $booking );
			}

			include TSRB_PLUGIN_DIR . 'admin/views/bookings-edit.php';
		} else {
			$status        = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
			$date_from     = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
			$date_to       = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
			$search        = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
			$orderby       = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'date';
			$order         = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC';
			$coupon_filter = isset( $_GET['coupon'] ) ? sanitize_text_field( wp_unslash( $_GET['coupon'] ) ) : '';
			$coupon_code   = isset( $_GET['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_GET['coupon_code'] ) ) : '';
			$payment_proof = isset( $_GET['payment_proof'] ) ? sanitize_text_field( wp_unslash( $_GET['payment_proof'] ) ) : '';
			$studio_id     = isset( $_GET['studio_id'] ) ? (int) $_GET['studio_id'] : 0;
			$member_id     = isset( $_GET['member_id'] ) ? (int) $_GET['member_id'] : 0; // filter dari Members quick action.

			$args = array(
				'status'        => $status,
				'date_from'     => $date_from,
				'date_to'       => $date_to,
				'search'        => $search,
				'orderby'       => $orderby,
				'order'         => $order,
				'coupon'        => $coupon_filter,
				'coupon_code'   => $coupon_code,
				'payment_proof' => $payment_proof,
				'studio_id'     => $studio_id,
				'member_id'     => $member_id,
			);

			$bookings = $this->booking_model->get_bookings( $args );

			// Statistik inline untuk filter aktif.
			if ( method_exists( $this->booking_model, 'get_stats_for_filters' ) ) {
				$stats = $this->booking_model->get_stats_for_filters( $args );
			} else {
				$stats = array();
			}

			// Data widget overview (upcoming 7 hari, today, dll).
			$widget_stats = array(
				'upcoming_7_days'  => 0,
				'today'            => 0,
				'pending_payment'  => 0,
				'cancelled_7_days' => 0,
			);

			if ( $this->booking_service && method_exists( $this->booking_service, 'get_bookings_widget_stats' ) ) {
				$widget_stats = $this->booking_service->get_bookings_widget_stats();
			}

			// Upcoming bookings list untuk widget di halaman booking (next 7 days).
			$upcoming_bookings_admin = array();
			if ( $this->booking_service && method_exists( $this->booking_service, 'get_upcoming_bookings' ) ) {
				$upcoming_bookings_admin = $this->booking_service->get_upcoming_bookings(
					array(
						'days_ahead' => 7,
						'limit'      => 10,
					)
				);
			}

			// Durasi batas pembayaran (dalam detik) untuk kolom Payment timer.
			$payment_window_seconds = 0;
			if ( $this->booking_service && method_exists( $this->booking_service, 'get_payment_window_seconds' ) ) {
				$payment_window_seconds = (int) $this->booking_service->get_payment_window_seconds();
			}

			// Daftar studio untuk dropdown filter.
			$studios = array();
			if ( method_exists( $this->studio_model, 'get_all' ) ) {
				$studios = $this->studio_model->get_all();
			}

			include TSRB_PLUGIN_DIR . 'admin/views/bookings-list.php';
		}
	}

	/**
	 * Studios page.
	 */
	public function render_studios() {
		if ( ! current_user_can( Tribuna_Helpers::admin_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'tribuna-studio-rent-booking' ) );
		}

		include TSRB_PLUGIN_DIR . 'admin/views/studios-list.php';
	}

	/**
	 * Coupons page.
	 */
	public function render_coupons() {
		if ( ! current_user_can( Tribuna_Helpers::admin_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'tribuna-studio-rent-booking' ) );
		}

		$coupons = $this->coupon_model->get_all();
		include TSRB_PLUGIN_DIR . 'admin/views/coupons-list.php';
	}

	/**
	 * Add-ons page.
	 */
	public function render_addons() {
		if ( ! current_user_can( Tribuna_Helpers::admin_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'tribuna-studio-rent-booking' ) );
		}

		$addons = $this->addon_model->get_all();
		include TSRB_PLUGIN_DIR . 'admin/views/addons-list.php';
	}

	/**
	 * Settings page.
	 */
	public function render_settings() {
		if ( ! current_user_can( Tribuna_Helpers::admin_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'tribuna-studio-rent-booking' ) );
		}

		// Ambil dari helper (satu sumber utama tsrb_settings).
		$settings = Tribuna_Helpers::get_settings();

		include TSRB_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Members page (list Tribuna Member users).
	 */
	public function render_members() {
		// Sekarang pakai capability bookings -> Manager + Admin Booking boleh.
		if ( ! current_user_can( Tribuna_Helpers::booking_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'tribuna-studio-rent-booking' ) );
		}

		include TSRB_PLUGIN_DIR . 'admin/views/members-list.php';
	}

	/**
	 * Handle studios CRUD via admin-post.
	 */
	public function handle_studios_form() {
		if ( ! current_user_can( Tribuna_Helpers::admin_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'tribuna-studio-rent-booking' ) );
		}

		if ( ! isset( $_POST['tsrb_studios_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsrb_studios_nonce'] ) ), 'tsrb_studios_action' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'tribuna-studio-rent-booking' ) );
		}

		$action = isset( $_POST['tsrb_studios_form_action'] ) ? sanitize_text_field( wp_unslash( $_POST['tsrb_studios_form_action'] ) ) : '';

		if ( 'add' === $action || 'edit' === $action ) {
			$studio_id   = isset( $_POST['studio_id'] ) ? (int) $_POST['studio_id'] : 0;
			$name        = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
			$slug        = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
			$description = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';
			$status      = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'active';

			$hourly_price_override = isset( $_POST['hourly_price_override'] ) && '' !== $_POST['hourly_price_override']
				? (float) $_POST['hourly_price_override']
				: null;

			$gallery_image_ids_raw = isset( $_POST['gallery_image_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['gallery_image_ids'] ) ) : '';
			$gallery_image_ids     = null;

			if ( '' !== $gallery_image_ids_raw ) {
				$ids_array = array_filter( array_map( 'absint', explode( ',', $gallery_image_ids_raw ) ) );
				if ( ! empty( $ids_array ) ) {
					$gallery_image_ids = implode( ',', $ids_array );
				}
			}

			if ( empty( $name ) ) {
				wp_redirect( add_query_arg( 'tsrb_msg', 'studio_name_required', wp_get_referer() ) );
				exit;
			}

			if ( empty( $slug ) ) {
				$slug = sanitize_title( $name );
			}

			$data = array(
				'name'        => $name,
				'slug'        => $slug,
				'description' => $description,
				'status'      => in_array( $status, array( 'active', 'inactive' ), true ) ? $status : 'active',
			);

			if ( null !== $hourly_price_override ) {
				$data['hourly_price_override'] = $hourly_price_override;
			} else {
				$data['hourly_price_override'] = null;
			}

			if ( null !== $gallery_image_ids ) {
				$data['gallery_image_ids'] = $gallery_image_ids;
			} else {
				$data['gallery_image_ids'] = null;
			}

			if ( 'add' === $action ) {
				$this->studio_model->create( $data );
			} elseif ( 'edit' === $action && $studio_id ) {
				$this->studio_model->update( $studio_id, $data );
			}

			wp_redirect( admin_url( 'admin.php?page=tsrb-studios&tsrb_msg=studio_saved' ) );
			exit;
		} elseif ( 'delete' === $action ) {
			$studio_id = isset( $_POST['studio_id'] ) ? (int) $_POST['studio_id'] : 0;
			if ( $studio_id ) {
				$this->studio_model->delete( $studio_id );
			}

			wp_redirect( admin_url( 'admin.php?page=tsrb-studios&tsrb_msg=studio_deleted' ) );
			exit;
		}

		wp_redirect( admin_url( 'admin.php?page=tsrb-studios' ) );
		exit;
	}

	/**
	 * Handle coupons create/update/delete via admin-post.
	 */
	public function handle_coupons_form() {
		if ( ! current_user_can( Tribuna_Helpers::admin_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'tribuna-studio-rent-booking' ) );
		}

		if ( ! isset( $_POST['tsrb_coupons_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsrb_coupons_nonce'] ) ), 'tsrb_coupons_action' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'tribuna-studio-rent-booking' ) );
		}

		$action    = isset( $_POST['tsrb_coupons_form_action'] ) ? sanitize_text_field( wp_unslash( $_POST['tsrb_coupons_form_action'] ) ) : '';
		$coupon_id = isset( $_POST['coupon_id'] ) ? (int) $_POST['coupon_id'] : 0;

		if ( 'delete' === $action ) {
			if ( $coupon_id ) {
				$this->coupon_model->delete( $coupon_id );
			}

			wp_redirect( admin_url( 'admin.php?page=tsrb-coupons&tsrb_msg=coupon_deleted' ) );
			exit;
		}

		// Default: add / edit.
		$code       = isset( $_POST['code'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['code'] ) ) ) : '';
		$type       = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'fixed';
		$value      = isset( $_POST['value'] ) ? (float) $_POST['value'] : 0;
		$max_usage  = isset( $_POST['max_usage'] ) ? (int) $_POST['max_usage'] : 0;
		$expires_at = isset( $_POST['expires_at'] ) ? sanitize_text_field( wp_unslash( $_POST['expires_at'] ) ) : '';
		$status     = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'active';

		if ( empty( $code ) || $value <= 0 ) {
			wp_redirect( add_query_arg( 'tsrb_msg', 'coupon_invalid', wp_get_referer() ) );
			exit;
		}

		if ( ! in_array( $type, array( 'fixed', 'percent' ), true ) ) {
			$type = 'fixed';
		}

		if ( ! in_array( $status, array( 'active', 'inactive' ), true ) ) {
			$status = 'active';
		}

		$expires = null;
		if ( ! empty( $expires_at ) ) {
			$timestamp = strtotime( $expires_at . ' 23:59:59' );
			if ( $timestamp ) {
				$expires = gmdate( 'Y-m-d H:i:s', $timestamp );
			}
		}

		$data = array(
			'code'       => $code,
			'type'       => $type,
			'value'      => $value,
			'max_usage'  => $max_usage,
			'status'     => $status,
			'expires_at' => $expires,
		);

		$result = $this->coupon_model->save_coupon( $coupon_id, $data );

		if ( false === $result ) {
			wp_redirect( add_query_arg( 'tsrb_msg', 'coupon_save_failed', wp_get_referer() ) );
			exit;
		}

		wp_redirect( admin_url( 'admin.php?page=tsrb-coupons&tsrb_msg=coupon_saved' ) );
		exit;
	}

	/**
	 * Handle addons CRUD via admin-post.
	 */
	public function handle_addons_form() {
		if ( ! current_user_can( Tribuna_Helpers::admin_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'tribuna-studio-rent-booking' ) );
		}

		if ( ! isset( $_POST['tsrb_addons_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsrb_addons_nonce'] ) ), 'tsrb_addons_action' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'tribuna-studio-rent-booking' ) );
		}

		$action = isset( $_POST['tsrb_addons_form_action'] ) ? sanitize_text_field( wp_unslash( $_POST['tsrb_addons_form_action'] ) ) : '';

		if ( 'add' === $action || 'edit' === $action ) {
			$addon_id    = isset( $_POST['addon_id'] ) ? (int) $_POST['addon_id'] : 0;
			$name        = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
			$description = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';
			$price       = isset( $_POST['price'] ) ? (float) $_POST['price'] : 0;
			$status      = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'active';

			if ( empty( $name ) ) {
				wp_redirect( add_query_arg( 'tsrb_msg', 'addon_name_required', wp_get_referer() ) );
				exit;
			}

			$data = array(
				'name'        => $name,
				'description' => $description,
				'price'       => $price,
				'status'      => in_array( $status, array( 'active', 'inactive' ), true ) ? $status : 'active',
			);

			if ( 'add' === $action ) {
				$this->addon_model->create( $data );
			} elseif ( 'edit' === $action && $addon_id ) {
				$this->addon_model->update( $addon_id, $data );
			}

			wp_redirect( admin_url( 'admin.php?page=tsrb-addons&tsrb_msg=addon_saved' ) );
			exit;
		} elseif ( 'delete' === $action ) {
			$addon_id = isset( $_POST['addon_id'] ) ? (int) $_POST['addon_id'] : 0;
			if ( $addon_id ) {
				$this->addon_model->delete( $addon_id );
			}

			wp_redirect( admin_url( 'admin.php?page=tsrb-addons&tsrb_msg=addon_deleted' ) );
			exit;
		}

		wp_redirect( admin_url( 'admin.php?page=tsrb-addons' ) );
		exit;
	}

	/**
	 * Handle export bookings to CSV (Excel).
	 */
	public function handle_export_bookings() {
		// Manager + Admin Booking boleh export bookings.
		if ( ! current_user_can( Tribuna_Helpers::booking_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'tribuna-studio-rent-booking' ) );
		}

		$filters = array();

		if ( ! empty( $_GET['status'] ) ) {
			$filters['status'] = sanitize_text_field( wp_unslash( $_GET['status'] ) );
		}

		if ( ! empty( $_GET['date_from'] ) ) {
			$filters['date_from'] = sanitize_text_field( wp_unslash( $_GET['date_from'] ) );
		}

		if ( ! empty( $_GET['date_to'] ) ) {
			$filters['date_to'] = sanitize_text_field( wp_unslash( $_GET['date_to'] ) );
		}

		if ( ! empty( $_GET['s'] ) ) {
			$filters['search'] = sanitize_text_field( wp_unslash( $_GET['s'] ) );
		}

		if ( ! empty( $_GET['coupon'] ) ) {
			$filters['coupon'] = sanitize_text_field( wp_unslash( $_GET['coupon'] ) );
		}

		if ( ! empty( $_GET['coupon_code'] ) ) {
			$filters['coupon_code'] = sanitize_text_field( wp_unslash( $_GET['coupon_code'] ) );
		}

		if ( ! empty( $_GET['payment_proof'] ) ) {
			$filters['payment_proof'] = sanitize_text_field( wp_unslash( $_GET['payment_proof'] ) );
		}

		if ( ! empty( $_GET['studio_id'] ) ) {
			$filters['studio_id'] = (int) $_GET['studio_id'];
		}

		if ( ! empty( $_GET['member_id'] ) ) {
			$filters['member_id'] = (int) $_GET['member_id'];
		}

		Tribuna_Exporter::export_bookings_csv( $filters );
	}

	/**
	 * Handle export members to CSV (Excel).
	 */
	public function handle_export_members() {
		// Manager + Admin Booking boleh export members.
		if ( ! current_user_can( Tribuna_Helpers::booking_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'tribuna-studio-rent-booking' ) );
		}

		$filters = array(
			'activity'     => isset( $_GET['activity'] ) ? sanitize_text_field( wp_unslash( $_GET['activity'] ) ) : '',
			'recent_days'  => isset( $_GET['recent_days'] ) ? (int) $_GET['recent_days'] : 0,
			'min_bookings' => isset( $_GET['min_bookings'] ) ? (int) $_GET['min_bookings'] : 0,
			'search'       => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
		);

		Tribuna_Exporter::export_members_csv( $filters );
	}

	/**
	 * Handle Bookings bulk status update.
	 *
	 * Admin-post action: tsrb_bulk_update_bookings.
	 */
	public function handle_bulk_update_bookings() {
		if ( ! current_user_can( Tribuna_Helpers::booking_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'tribuna-studio-rent-booking' ) );
		}

		if ( ! isset( $_POST['tsrb_bulk_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsrb_bulk_nonce'] ) ), 'tsrb_bulk_update_bookings' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'tribuna-studio-rent-booking' ) );
		}

		$action      = isset( $_POST['tsrb_bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['tsrb_bulk_action'] ) ) : '';
		$booking_ids = isset( $_POST['booking_ids'] ) && is_array( $_POST['booking_ids'] ) ? array_map( 'intval', $_POST['booking_ids'] ) : array();

		if ( empty( $action ) || empty( $booking_ids ) ) {
			wp_safe_redirect( add_query_arg( 'tsrb_msg', 'bulk_no_selection', admin_url( 'admin.php?page=tsrb-bookings' ) ) );
			exit;
		}

		$new_status = '';
		switch ( $action ) {
			case 'set_pending':
				$new_status = 'pending_payment';
				break;
			case 'set_paid':
				$new_status = 'paid';
				break;
			case 'set_cancelled':
				$new_status = 'cancelled';
				break;
			default:
				$new_status = '';
		}

		if ( '' === $new_status ) {
			wp_safe_redirect( add_query_arg( 'tsrb_msg', 'bulk_invalid_action', admin_url( 'admin.php?page=tsrb-bookings' ) ) );
			exit;
		}

		$updated_count = 0;

		// Jika model punya bulk_update_status(), gunakan itu (sudah ada logging).
		if ( method_exists( $this->booking_model, 'bulk_update_status' ) ) {
			$updated_count = $this->booking_model->bulk_update_status(
				$booking_ids,
				$new_status,
				get_current_user_id()
			);
		} else {
			// Fallback: loop manual ke update_status() (juga sudah logging).
			foreach ( $booking_ids as $booking_id ) {
				if ( method_exists( $this->booking_model, 'update_status' ) ) {
					$result = $this->booking_model->update_status( $booking_id, $new_status, get_current_user_id() );
					if ( $result ) {
						$updated_count++;
					}
				}
			}
		}

		$redirect_url = add_query_arg(
			array(
				'page'          => 'tsrb-bookings',
				'tsrb_msg'      => 'bulk_updated',
				'updated_count' => $updated_count,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * AJAX lama: Proses approve / reject cancellation oleh admin.
	 *
	 * Action: wp_ajax_tsrb_admin_process_cancel
	 *
	 * $_POST:
	 * - booking_id
	 * - cancel_action: approve|reject
	 * - admin_note   : optional
	 */
	public function ajax_process_cancel() {
		check_ajax_referer( 'tsrb_admin_nonce', 'nonce' );

		// Manager + Admin Booking boleh proses cancellation.
		if ( ! current_user_can( Tribuna_Helpers::booking_capability() ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You are not allowed to perform this action.', 'tribuna-studio-rent-booking' ),
				),
				403
			);
		}

		if ( ! $this->booking_service || ! method_exists( $this->booking_service, 'handle_admin_cancel_action' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Cancellation service is not available.', 'tribuna-studio-rent-booking' ),
				),
				500
			);
		}

		$admin_id   = get_current_user_id();
		$booking_id = isset( $_POST['booking_id'] ) ? (int) $_POST['booking_id'] : 0;
		$action     = isset( $_POST['cancel_action'] ) ? sanitize_text_field( wp_unslash( $_POST['cancel_action'] ) ) : '';
		$admin_note = isset( $_POST['admin_note'] ) ? wp_kses_post( wp_unslash( $_POST['admin_note'] ) ) : '';

		if ( $booking_id <= 0 || ! in_array( $action, array( 'approve', 'reject' ), true ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid cancellation request.', 'tribuna-studio-rent-booking' ),
				),
				400
			);
		}

		$booking = $this->booking_model->get( $booking_id );
		if ( ! $booking ) {
			wp_send_json_error(
				array(
					'message' => __( 'Booking not found.', 'tribuna-studio-rent-booking' ),
				),
				404
			);
		}

		// Simpan catatan admin jika ada.
		if ( '' !== $admin_note ) {
			$this->booking_model->update(
				$booking_id,
				array(
					'admin_note' => $admin_note,
				)
			);
		}

		// Proses via service (policy-based, sudah logging).
		$result = $this->booking_service->handle_admin_cancel_action( $booking_id, $action, $admin_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				),
				400
			);
		}

		$message = ( 'approve' === $action )
			? __( 'Cancellation has been approved and processed.', 'tribuna-studio-rent-booking' )
			: __( 'Cancellation request has been rejected.', 'tribuna-studio-rent-booking' );

		wp_send_json_success(
			array(
				'message' => $message,
			)
		);
	}

	/**
	 * AJAX baru: Proses approve / reject / direct cancellation oleh admin.
	 *
	 * Action: wp_ajax_tsrb_admin_process_cancellation
	 *
	 * $_POST:
	 * - booking_id
	 * - process_action: approve|reject|direct
	 * - admin_note    : optional
	 */
	public function ajax_process_cancellation() {
		check_ajax_referer( 'tsrb_admin_nonce', 'nonce' );

		if ( ! current_user_can( Tribuna_Helpers::booking_capability() ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You are not allowed to perform this action.', 'tribuna-studio-rent-booking' ),
				),
				403
			);
		}

		$admin_id   = get_current_user_id();
		$booking_id = isset( $_POST['booking_id'] ) ? (int) $_POST['booking_id'] : 0;
		$action     = isset( $_POST['process_action'] ) ? sanitize_text_field( wp_unslash( $_POST['process_action'] ) ) : '';
		$admin_note = isset( $_POST['admin_note'] ) ? wp_kses_post( wp_unslash( $_POST['admin_note'] ) ) : '';

		if ( $booking_id <= 0 || ! in_array( $action, array( 'approve', 'reject', 'direct' ), true ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid cancellation request.', 'tribuna-studio-rent-booking' ),
				),
				400
			);
		}

		$booking = $this->booking_model->get( $booking_id );
		if ( ! $booking ) {
			wp_send_json_error(
				array(
					'message' => __( 'Booking not found.', 'tribuna-studio-rent-booking' ),
				),
				404
			);
		}

		// Simpan admin note jika ada.
		if ( '' !== $admin_note ) {
			$this->booking_model->update(
				$booking_id,
				array(
					'admin_note' => $admin_note,
				)
			);
		}

		$new_status = '';

		if ( 'direct' === $action ) {
			// Direct cancel oleh admin tanpa request member.
			// Tidak menggunakan policy refund otomatis (refund_type = 'none').
			$updated = $this->booking_model->update(
				$booking_id,
				array(
					'status'        => 'cancelled',
					'refund_type'   => 'none',
					'refund_amount' => 0,
					'credit_amount' => 0,
					'cancel_reason' => 'admin_direct_cancel',
				)
			);

			if ( ! $updated ) {
				wp_send_json_error(
					array(
						'message' => __( 'Failed to cancel booking.', 'tribuna-studio-rent-booking' ),
					),
					500
				);
			}

			// Log manual kalau model log tersedia (fallback; model service juga akan kirim email bila dipakai).
			if ( class_exists( 'Tribuna_Booking_Log_Model' ) ) {
				Tribuna_Booking_Log_Model::log_status_change(
					$booking_id,
					$booking->status,
					'cancelled',
					$admin_id,
					__( 'Booking cancelled directly by admin.', 'tribuna-studio-rent-booking' )
				);
			}

			// Kirim email status changed.
			if ( $this->booking_service && method_exists( $this->booking_service, 'send_status_change_email' ) ) {
				$updated_booking = $this->booking_model->get( $booking_id );
				if ( $updated_booking ) {
					$this->booking_service->send_status_change_email( $updated_booking );
				}
			}

			$new_status = 'cancelled';

			wp_send_json_success(
				array(
					'message'    => __( 'Booking has been cancelled by admin.', 'tribuna-studio-rent-booking' ),
					'new_status' => $new_status,
					'reload'     => false,
				)
			);
		}

		// approve / reject pakai service (sudah logging).
		if ( ! $this->booking_service || ! method_exists( $this->booking_service, 'handle_admin_cancel_action' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Cancellation service is not available.', 'tribuna-studio-rent-booking' ),
				),
				500
			);
		}

		$result = $this->booking_service->handle_admin_cancel_action( $booking_id, $action, $admin_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				),
				400
			);
		}

		// Ambil status terbaru setelah proses.
		$updated_booking = $this->booking_model->get( $booking_id );
		if ( $updated_booking ) {
			$new_status = $updated_booking->status;
		}

		$message = '';
		if ( 'approve' === $action ) {
			$message = __( 'Cancellation has been approved and processed.', 'tribuna-studio-rent-booking' );
		} elseif ( 'reject' === $action ) {
			$message = __( 'Cancellation request has been rejected.', 'tribuna-studio-rent-booking' );
		}

		wp_send_json_success(
			array(
				'message'    => $message,
				'new_status' => $new_status,
				'reload'     => false,
			)
		);
	}
}
