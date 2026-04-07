<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend handler.
 */
class Tribuna_Frontend {

	private $plugin_name;
	private $version;
	private $studio_model;
	private $addon_model;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name  = $plugin_name;
		$this->version      = $version;
		$this->studio_model = new Tribuna_Studio_Model();
		$this->addon_model  = new Tribuna_Addon_Model();
	}

	/**
	 * Enqueue public scripts & styles.
	 */
	public function enqueue_styles_scripts() {
		if ( ! is_singular() && ! is_page() ) {
			return;
		}

		wp_enqueue_style(
			'tsrb-public',
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
			'tsrb-public',
			TSRB_PLUGIN_URL . 'public/js/tribuna-public.js',
			array( 'jquery', 'tsrb-fullcalendar' ),
			$this->version,
			true
		);

		// Ambil settings terpusat via helper (satu sumber kebenaran).
		$settings = Tribuna_Helpers::get_settings();

		$admin_whatsapp = isset( $settings['admin_whatsapp_number'] ) ? $settings['admin_whatsapp_number'] : '';

		// Build studios data with hourly prices.
		$studios      = $this->studio_model->get_active();
		$studios_data = array();

		if ( ! empty( $studios ) ) {
			foreach ( $studios as $studio ) {
				$studios_data[ $studio->id ] = array(
					'id'           => (int) $studio->id,
					'name'         => $studio->name,
					'hourly_price' => ( null !== $studio->hourly_price_override )
						? (float) $studio->hourly_price_override
						: 0,
				);
			}
		}

		// Data user login saat ini (untuk dipakai di UI booking tanpa reload).
		$current_user_data = null;
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( $user instanceof WP_User && $user->exists() ) {
				$current_user_data = array(
					'id'           => $user->ID,
					'display_name' => $user->display_name,
					'email'        => $user->user_email,
					'phone'        => get_user_meta( $user->ID, 'tsrb_whatsapp', true ),
				);
			}
		}

		// Workflow settings (untuk dipakai JS di form booking).
		$workflow = isset( $settings['workflow'] ) && is_array( $settings['workflow'] )
			? $settings['workflow']
			: array();

		// Defaults sinkron dengan activation + sanitize_settings + service.
		$workflow_defaults = array(
			// Auto-cancel & lead time.
			'auto_cancel_unpaid_hours'         => 0,
			'auto_cancel_unpaid_sameday_hours' => 0,
			'min_lead_time_hours'              => 0,
			'require_manual_approval'          => 0,
			// Policy text.
			'cancellation_policy_text'         => '',
			'booking_reschedule_policy_text'   => '',
			'cancel_refund_policy_text'        => '',
			// Guard booking.
			'prevent_new_if_pending_payment'   => 0,
			'max_active_bookings_per_user'     => 0,
			// Reschedule rules.
			'allow_member_reschedule'          => 0,
			'reschedule_cutoff_hours'          => 0,
			'reschedule_allow_pending'         => 0,
			'reschedule_admin_only'            => 0,
			// Payment timer.
			'payment_deadline_hours'           => 0,
			// Refund & credit rules.
			'refund_full_hours_before'         => 0,
			'refund_partial_hours_before'      => 0,
			'refund_partial_percent'           => 0,
			'refund_no_refund_inside_hours'    => 0,
			// Member cancellation.
			'allow_member_cancel'              => 0,
		);

		$workflow = wp_parse_args( $workflow, $workflow_defaults );

		wp_localize_script(
			'tsrb-public',
			'TSRB_Public',
			array(
				'ajax_url'                => admin_url( 'admin-ajax.php' ),
				'nonce'                   => wp_create_nonce( 'tsrb_public_nonce' ),
				'currency'                => isset( $settings['currency'] ) ? $settings['currency'] : 'IDR',
				'studios'                 => $studios_data,
				'payment_qr_url'          => $this->get_payment_qr_url(),
				'whatsapp_number'         => $admin_whatsapp,
				'current_user'            => $current_user_data,

				// Lead time & auto-cancel (dipakai di UI).
				'min_lead_time_hours'     => (int) $workflow['min_lead_time_hours'],
				'auto_cancel_unpaid_hour' => (int) $workflow['auto_cancel_unpaid_hours'],
				'auto_cancel_sameday_hour'=> (int) $workflow['auto_cancel_unpaid_sameday_hours'],

				// URL endpoint untuk HTML invoice (Download Invoice).
				'invoice_url'             => admin_url( 'admin-ajax.php?action=tsrb_download_invoice_html' ),

				// Info kebijakan pembatalan & reschedule (untuk ditampilkan di UI).
				'cancellation_policy'     => array(
					'allow_member_cancel'           => ! empty( $workflow['allow_member_cancel'] ),
					'refund_full_hours_before'      => (int) $workflow['refund_full_hours_before'],
					'refund_partial_hours_before'   => (int) $workflow['refund_partial_hours_before'],
					'refund_partial_percent'        => (int) $workflow['refund_partial_percent'],
					'refund_no_refund_inside_hours' => (int) $workflow['refund_no_refund_inside_hours'],
					// Text policy yang bisa dirender di frontend.
					'policy_text'                   => wp_kses_post( $workflow['cancellation_policy_text'] ),
					'cancel_refund_policy_text'     => wp_kses_post( $workflow['cancel_refund_policy_text'] ),
					'booking_reschedule_policy_text'=> wp_kses_post( $workflow['booking_reschedule_policy_text'] ),
				),
			)
		);
	}

	/**
	 * Register shortcodes.
	 */
	public function register_shortcodes() {
		add_shortcode( 'studio_booking', array( $this, 'shortcode_studio_booking' ) );
		add_shortcode( 'studio_booking_calendar', array( $this, 'shortcode_public_calendar' ) );
		add_shortcode( 'studio_booking_dashboard', array( $this, 'shortcode_user_dashboard' ) );
		add_shortcode( 'studio_profile', array( $this, 'shortcode_studio_profile' ) );
	}

	/**
	 * Multi-step booking form (frontend).
	 *
	 * Guest:
	 * - Step 1 tetap bisa pilih studio + kalender + slot.
	 * - Step 2 berisi CTA login/daftar (modal).
	 *
	 * Logged-in:
	 * - Flow multi-step penuh + prefill data user.
	 */
	public function shortcode_studio_booking( $atts = array() ) {
		$studios        = $this->studio_model->get_active();
		$addons         = $this->addon_model->get_active();
		$payment_qr_url = $this->get_payment_qr_url();

		$is_logged_in = is_user_logged_in();
		$current_user = $is_logged_in ? wp_get_current_user() : null;

		ob_start();
		include TSRB_PLUGIN_DIR . 'public/views/booking-form.php';
		return ob_get_clean();
	}

	/**
	 * Public availability calendar.
	 */
	public function shortcode_public_calendar( $atts = array() ) {
		$studios = $this->studio_model->get_active();

		ob_start();
		include TSRB_PLUGIN_DIR . 'public/views/public-calendar.php';
		return ob_get_clean();
	}

	/**
	 * User booking dashboard (frontend, non-WP-admin UI).
	 */
	public function shortcode_user_dashboard( $atts = array() ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your bookings.', 'tribuna-studio-rent-booking' ) . '</p>';
		}

		$current_user_id = get_current_user_id();
		$booking_model   = new Tribuna_Booking_Model();

		// Ambil semua booking milik user saat ini.
		$all_bookings = $booking_model->get_bookings(
			array(
				'user_id' => $current_user_id,
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		);

		$today    = current_time( 'Y-m-d' );
		$upcoming = array();
		$history  = array();

		if ( ! empty( $all_bookings ) ) {
			foreach ( $all_bookings as $booking ) {
				if ( $booking->date >= $today ) {
					$upcoming[] = $booking;
				} else {
					$history[] = $booking;
				}
			}
		}

		ob_start();
		include TSRB_PLUGIN_DIR . 'public/views/user-dashboard.php';
		return ob_get_clean();
	}

	/**
	 * Frontend member area / user profile.
	 */
	public function shortcode_studio_profile( $atts = array() ) {
		if ( ! is_user_logged_in() ) {
			ob_start();
			?>
			<div class="tsrb-profile-wrapper">
				<p><?php esc_html_e( 'You must be logged in to access your profile.', 'tribuna-studio-rent-booking' ); ?></p>
				<button type="button" class="button tsrb-open-login-modal">
					<?php esc_html_e( 'Login', 'tribuna-studio-rent-booking' ); ?>
				</button>
			</div>
			<?php
			return ob_get_clean();
		}

		$current_user = wp_get_current_user();
		$whatsapp     = get_user_meta( $current_user->ID, 'tsrb_whatsapp', true );

		$atts = shortcode_atts(
			array(
				'logout_redirect' => home_url( '/' ),
			),
			$atts,
			'studio_profile'
		);

		$logout_url = wp_logout_url( esc_url_raw( $atts['logout_redirect'] ) );

		ob_start();
		include TSRB_PLUGIN_DIR . 'public/views/user-profile.php';
		return ob_get_clean();
	}

	/**
	 * Helper: get payment QR image URL from settings.
	 */
	protected function get_payment_qr_url() {
		$settings = Tribuna_Helpers::get_settings();
		$id       = isset( $settings['payment_qr_image_id'] ) ? (int) $settings['payment_qr_image_id'] : 0;

		if ( ! $id ) {
			return '';
		}

		$url = wp_get_attachment_image_url( $id, 'medium' );

		return $url ? $url : '';
	}
}
