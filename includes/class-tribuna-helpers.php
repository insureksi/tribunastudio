<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tribuna_Helpers {

	/**
	 * Cached settings agar tidak berulang-ulang get_option di satu request.
	 *
	 * @var array|null
	 */
	protected static $settings_cache = null;

	/**
	 * Ambil settings plugin dari satu sumber utama.
	 *
	 * - Utama: option baru 'tsrb_settings' (snake case, dipakai Settings API).
	 * - Fallback sekali: option legacy 'tsrbSettings' (camel case) hanya jika yang baru kosong/bukan array.
	 * - Merge dengan defaults ringan supaya key penting selalu ada.
	 *
	 * Catatan:
	 * - Timezone tidak lagi disimpan khusus di option; gunakan setting WordPress (timezone_string).
	 *
	 * @return array
	 */
	public static function get_settings() {
		if ( null !== self::$settings_cache ) {
			return self::$settings_cache;
		}

		$new_settings = get_option( 'tsrb_settings', null );

		if ( ! is_array( $new_settings ) ) {
			$legacy = get_option( 'tsrbSettings', null );
			if ( is_array( $legacy ) ) {
				$new_settings = $legacy;
			} else {
				$new_settings = array();
			}
		}

		$defaults = array(
			'hourly_price'        => 75000,
			'currency'            => 'IDR',
			'admin_email'         => get_option( 'admin_email' ),
			'payment_qr_image_id' => 0,
			'operating_hours'     => array(),
			'blocked_dates'       => array(),
			'emails'              => array(),
			'workflow'            => array(),
			'integrations'        => array(),
		);

		$settings = wp_parse_args( is_array( $new_settings ) ? $new_settings : array(), $defaults );

		if ( ! is_array( $settings['workflow'] ) ) {
			$settings['workflow'] = array();
		}
		if ( ! is_array( $settings['emails'] ) ) {
			$settings['emails'] = array();
		}
		if ( ! is_array( $settings['integrations'] ) ) {
			$settings['integrations'] = array();
		}
		if ( ! is_array( $settings['operating_hours'] ) ) {
			$settings['operating_hours'] = array();
		}
		if ( ! is_array( $settings['blocked_dates'] ) ) {
			$settings['blocked_dates'] = array();
		}

		self::$settings_cache = $settings;

		return self::$settings_cache;
	}

	/**
	 * Format harga sesuai setting plugin.
	 *
	 * @param float|int $amount Amount.
	 * @return string
	 */
	public static function format_price( $amount ) {
		$settings = self::get_settings();
		$currency = isset( $settings['currency'] ) ? $settings['currency'] : 'IDR';

		return sprintf( '%s %s', $currency, number_format_i18n( $amount, 0 ) );
	}

	/**
	 * Sanitize datetime string (simple).
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_datetime( $value ) {
		$value = sanitize_text_field( $value );
		return $value;
	}

	/**
	 * Capability untuk akses penuh fitur Tribuna (Manager / Owner).
	 *
	 * @return string
	 */
	public static function admin_capability() {
		return apply_filters( 'tsrb_admin_capability', 'manage_tsrb_all' );
	}

	/**
	 * Capability untuk pengelolaan booking (Booking Admin / CS).
	 *
	 * @return string
	 */
	public static function booking_capability() {
		return apply_filters( 'tsrb_booking_capability', 'manage_tsrb_bookings' );
	}

	/**
	 * Build Google Calendar URL.
	 *
	 * @param array $args Args:
	 *                    - title
	 *                    - description
	 *                    - start (Y-m-d H:i:s)
	 *                    - end   (Y-m-d H:i:s)
	 *                    - timezone
	 *                    - location
	 * @return string
	 */
	public static function build_google_calendar_url( $args ) {
		$defaults = array(
			'title'       => '',
			'description' => '',
			'start'       => '',
			'timezone'    => get_option( 'timezone_string', 'Asia/Jakarta' ),
			'end'         => '',
			'location'    => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$base = 'https://www.google.com/calendar/render?action=TEMPLATE';

		$start_ts = strtotime( $args['start'] );
		$end_ts   = strtotime( $args['end'] );

		$params = array(
			'text'     => $args['title'],
			'dates'    => ( $start_ts ? gmdate( 'Ymd\\THis\\Z', $start_ts ) : '' ) . '/' . ( $end_ts ? gmdate( 'Ymd\\THis\\Z', $end_ts ) : '' ),
			'ctz'      => $args['timezone'],
			'details'  => $args['description'],
			'location' => $args['location'],
		);

		return esc_url_raw( $base . '&' . http_build_query( $params ) );
	}

	/* ======================================================
	 *  HELPER UNTUK PAYMENT TIMER & WAKTU SERVER
	 * ===================================================== */

	/**
	 * Ambil timestamp sekarang berdasarkan timezone WordPress.
	 *
	 * @return int
	 */
	public static function get_server_now_timestamp() {
		return current_time( 'timestamp' );
	}

	/**
	 * Format durasi countdown (dalam detik) menjadi label singkat.
	 *
	 * @param int  $seconds               Sisa detik.
	 * @param bool $allow_zero_as_expired Jika true, 0 atau negatif akan selalu "Expired".
	 * @return string
	 */
	public static function format_countdown_label( $seconds, $allow_zero_as_expired = true ) {
		$seconds = (int) $seconds;

		if ( $allow_zero_as_expired && $seconds <= 0 ) {
			return __( 'Expired', 'tribuna-studio-rent-booking' );
		}

		$seconds = max( 0, $seconds );

		$hours   = floor( $seconds / HOUR_IN_SECONDS );
		$seconds = $seconds % HOUR_IN_SECONDS;
		$minutes = floor( $seconds / MINUTE_IN_SECONDS );
		$seconds = $seconds % MINUTE_IN_SECONDS;

		$parts = array();

		if ( $hours > 0 ) {
			$parts[] = sprintf( _n( '%dh', '%dh', $hours, 'tribuna-studio-rent-booking' ), $hours );
		}

		if ( $minutes > 0 || $hours > 0 ) {
			$parts[] = sprintf( _n( '%dm', '%dm', $minutes, 'tribuna-studio-rent-booking' ), $minutes );
		}

		if ( 0 === $hours && 0 === $minutes ) {
			$parts[] = sprintf( _n( '%ds', '%ds', $seconds, 'tribuna-studio-rent-booking' ), $seconds );
		}

		return implode( ' ', $parts );
	}

	/* ======================================================
	 *  BOOKING POLICY HTML (dipakai frontpage & dashboard)
	 * ===================================================== */

	/**
	 * Bangun HTML kebijakan booking dari workflow.
	 *
	 * Dipakai untuk:
	 * - Modal "Kebijakan Booking" di step 3 frontpage.
	 * - Bisa juga dipakai di dashboard (Riwayat Booking) agar konsisten.
	 *
	 * @return string HTML sudah siap render.
	 */
	public static function get_booking_policy_html() {
		$settings = self::get_settings();
		$workflow = isset( $settings['workflow'] ) && is_array( $settings['workflow'] )
			? $settings['workflow']
			: array();

		// Booking rules.
		$min_lead_time_hours          = isset( $workflow['min_lead_time_hours'] ) ? (int) $workflow['min_lead_time_hours'] : 0;
		$max_active_bookings_per_user = isset( $workflow['max_active_bookings_per_user'] ) ? (int) $workflow['max_active_bookings_per_user'] : 0;
		$payment_deadline_hours       = isset( $workflow['payment_deadline_hours'] ) ? (int) $workflow['payment_deadline_hours'] : 0;

		// Reschedule rules.
		$reschedule_cutoff_hours  = isset( $workflow['reschedule_cutoff_hours'] ) ? (int) $workflow['reschedule_cutoff_hours'] : 0;
		$reschedule_allow_pending = ! empty( $workflow['reschedule_allow_pending'] );
		$reschedule_max_changes   = isset( $workflow['reschedule_max_changes'] ) ? (int) $workflow['reschedule_max_changes'] : 0;

		// Cancellation / refund rules.
		$refund_full_hours_before      = isset( $workflow['refund_full_hours_before'] ) ? (int) $workflow['refund_full_hours_before'] : 0;
		$refund_partial_hours_before   = isset( $workflow['refund_partial_hours_before'] ) ? (int) $workflow['refund_partial_hours_before'] : 0;
		$refund_partial_percent        = isset( $workflow['refund_partial_percent'] ) ? (int) $workflow['refund_partial_percent'] : 0;
		$refund_no_refund_inside_hours = isset( $workflow['refund_no_refund_inside_hours'] ) ? (int) $workflow['refund_no_refund_inside_hours'] : 0;

		ob_start();
		?>

		<h5><?php esc_html_e( 'Aturan Booking', 'tribuna-studio-rent-booking' ); ?></h5>
		<ul>
			<li><?php esc_html_e( 'Booking hanya dapat dibuat untuk jadwal yang masih tersedia di kalender studio.', 'tribuna-studio-rent-booking' ); ?></li>
			<?php if ( $min_lead_time_hours > 0 ) : ?>
				<li>
					<?php
					printf(
						esc_html__( 'Booking harus dibuat minimal %d jam sebelum jam mulai.', 'tribuna-studio-rent-booking' ),
						(int) $min_lead_time_hours
					);
					?>
				</li>
			<?php endif; ?>
			<?php if ( $max_active_bookings_per_user > 0 ) : ?>
				<li>
					<?php
					printf(
						esc_html__( 'Setiap member dapat memiliki maksimal %d booking aktif sekaligus.', 'tribuna-studio-rent-booking' ),
						(int) $max_active_bookings_per_user
					);
					?>
				</li>
			<?php endif; ?>
			<?php if ( $payment_deadline_hours > 0 ) : ?>
				<li>
					<?php
					printf(
						esc_html__( 'Jika pembayaran tidak diterima dalam waktu %d jam sejak booking dibuat, booking akan dibatalkan otomatis.', 'tribuna-studio-rent-booking' ),
						(int) $payment_deadline_hours
					);
					?>
				</li>
			<?php endif; ?>
		</ul>

		<h5><?php esc_html_e( 'Aturan Reschedule', 'tribuna-studio-rent-booking' ); ?></h5>
		<ul>
			<li>
				<?php esc_html_e( 'Perubahan jadwal hanya dapat diajukan dengan menghubungi admin (WhatsApp atau e-mail) dan akan diproses jika slot jadwal baru masih tersedia.', 'tribuna-studio-rent-booking' ); ?>
			</li>
			<?php if ( $reschedule_cutoff_hours > 0 ) : ?>
				<li>
					<?php
					printf(
						esc_html__( 'Permohonan reschedule dapat diajukan maksimal %d jam sebelum jam mulai booking awal.', 'tribuna-studio-rent-booking' ),
						(int) $reschedule_cutoff_hours
					);
					?>
				</li>
			<?php endif; ?>
			<li>
				<?php
				if ( $reschedule_allow_pending ) {
					esc_html_e( 'Reschedule hanya berlaku untuk booking dengan status Menunggu Pembayaran dan Sudah Dibayar.', 'tribuna-studio-rent-booking' );
				} else {
					esc_html_e( 'Reschedule hanya berlaku untuk booking dengan status Sudah Dibayar.', 'tribuna-studio-rent-booking' );
				}
				?>
			</li>
			<?php if ( $reschedule_max_changes > 0 ) : ?>
				<li>
					<?php
					printf(
						esc_html__( 'Setiap booking hanya dapat di-reschedule maksimal %d kali.', 'tribuna-studio-rent-booking' ),
						(int) $reschedule_max_changes
					);
					?>
				</li>
			<?php endif; ?>
		</ul>

		<?php if ( $refund_full_hours_before || $refund_partial_hours_before || $refund_no_refund_inside_hours ) : ?>
			<h5><?php esc_html_e( 'Aturan Pembatalan & Refund', 'tribuna-studio-rent-booking' ); ?></h5>
			<ul>
				<?php if ( $refund_full_hours_before > 0 ) : ?>
					<li>
						<?php
						printf(
							esc_html__( 'Refund penuh diberikan jika pembatalan dilakukan minimal %d jam sebelum jam mulai.', 'tribuna-studio-rent-booking' ),
							(int) $refund_full_hours_before
						);
						?>
					</li>
				<?php endif; ?>
				<?php if ( $refund_partial_hours_before > 0 && $refund_partial_percent > 0 ) : ?>
					<li>
						<?php
						printf(
							esc_html__( 'Refund parsial sebesar %1$d%% diberikan jika pembatalan dilakukan sebelum %2$d jam dari jam mulai di hari H.', 'tribuna-studio-rent-booking' ),
							(int) $refund_partial_percent,
							(int) $refund_partial_hours_before
						);
						?>
					</li>
				<?php endif; ?>
				<?php if ( $refund_no_refund_inside_hours > 0 ) : ?>
					<li>
						<?php
						printf(
							esc_html__( 'Tidak ada refund jika pembatalan dilakukan kurang dari %d jam sebelum jam mulai.', 'tribuna-studio-rent-booking' ),
							(int) $refund_no_refund_inside_hours
						);
						?>
					</li>
				<?php endif; ?>
			</ul>
		<?php endif; ?>

		<?php
		$html = ob_get_clean();

		return apply_filters( 'tsrb_booking_policy_html', $html, $settings, $workflow );
	}
}
