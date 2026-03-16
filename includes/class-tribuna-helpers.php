<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tribuna_Helpers {

	/**
	 * Format harga sesuai setting plugin.
	 *
	 * @param float|int $amount Amount.
	 * @return string
	 */
	public static function format_price( $amount ) {
		$settings = get_option( 'tsrb_settings', array() );
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
		/**
		 * Filter: tsrb_admin_capability
		 *
		 * Izinkan developer override capability utama plugin jika diperlukan.
		 * Pastikan capability yang dipakai ditambahkan ke role "administrator"
		 * pada saat aktivasi / upgrade plugin.
		 */
		return apply_filters( 'tsrb_admin_capability', 'manage_tsrb_all' );
	}

	/**
	 * Capability untuk pengelolaan booking (Booking Admin / CS).
	 *
	 * @return string
	 */
	public static function booking_capability() {
		/**
		 * Filter: tsrb_booking_capability
		 *
		 * Izinkan override capability booking jika diperlukan.
		 */
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
			'end'         => '',
			'timezone'    => get_option( 'timezone_string', 'Asia/Jakarta' ),
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
	 * Dipakai untuk sinkronisasi timer antara PHP dan JavaScript.
	 *
	 * @return int
	 */
	public static function get_server_now_timestamp() {
		return current_time( 'timestamp' );
	}

	/**
	 * Format durasi countdown (dalam detik) menjadi label singkat.
	 *
	 * Contoh:
	 * - 3661  -> "1h 1m"
	 * - 59    -> "59s"
	 * - 0/-10 -> "Expired"
	 *
	 * @param int $seconds Sisa detik.
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

		if ( $hours === 0 && $minutes === 0 ) {
			$parts[] = sprintf( _n( '%ds', '%ds', $seconds, 'tribuna-studio-rent-booking' ), $seconds );
		}

		return implode( ' ', $parts );
	}
}
