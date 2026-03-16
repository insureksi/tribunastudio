<?php
/**
 * Core booking business logic service.
 *
 * Dipakai oleh AJAX Admin & Public agar logic terpusat.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tribuna_Booking_Service {

	/**
	 * @var Tribuna_Booking_Model
	 */
	protected $booking_model;

	/**
	 * @var Tribuna_Coupon_Model|null
	 */
	protected $coupon_model;

	/**
	 * @var Tribuna_Addon_Model|null
	 */
	protected $addon_model;

	/**
	 * @var Tribuna_Studio_Model|null
	 */
	protected $studio_model;

	/**
	 * @var Tribuna_Booking_Log_Model|null
	 */
	protected $log_model;

	/**
	 * Cached settings.
	 *
	 * @var array|null
	 */
	protected $settings = null;

	public function __construct() {
		$this->booking_model = new Tribuna_Booking_Model();
		$this->coupon_model  = class_exists( 'Tribuna_Coupon_Model' ) ? new Tribuna_Coupon_Model() : null;
		$this->addon_model   = class_exists( 'Tribuna_Addon_Model' ) ? new Tribuna_Addon_Model() : null;
		$this->studio_model  = class_exists( 'Tribuna_Studio_Model' ) ? new Tribuna_Studio_Model() : null;
		$this->log_model     = class_exists( 'Tribuna_Booking_Log_Model' ) ? new Tribuna_Booking_Log_Model() : null;
	}

	/* ======================================================
	 *  KALENDER ADMIN
	 * ===================================================== */

	/**
	 * Build events for admin FullCalendar (with busy dates).
	 *
	 * @param string $start Y-m-d.
	 * @param string $end   Y-m-d.
	 * @return array
	 */
	public function get_admin_calendar_events( $start, $end ) {
		$events = $this->booking_model->get_events_for_calendar( $start, $end );

		if ( method_exists( $this->booking_model, 'get_busy_dates' ) ) {
			$busy_dates = $this->booking_model->get_busy_dates( $start, $end, 3 );
			if ( ! empty( $busy_dates ) ) {
				foreach ( $busy_dates as $date => $count ) {
					$events[] = array(
						'start'     => $date,
						'end'       => $date,
						'display'   => 'background',
						'className' => array( 'tsrb-busy-date' ),
						'isBusy'    => true,
						'busyCount' => (int) $count,
						'allDay'    => true,
					);
				}
			}
		}

		return $events;
	}

	/* ======================================================
	 *  STATUS BOOKING + EMAIL + LOG
	 * ===================================================== */

	/**
	 * Update booking status + optional admin note + send emails.
	 *
	 * Dipakai oleh AJAX admin.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $new_status New status (pending_payment|paid|cancel_requested|cancelled).
	 * @param string $admin_note Optional admin note.
	 * @param int    $changed_by User ID admin.
	 * @return bool
	 */
	public function update_status_with_email( $booking_id, $new_status, $admin_note = '', $changed_by = 0 ) {
		$booking_id = (int) $booking_id;

		// Tambah cancel_requested agar bisa disimpan dari admin.
		$allowed_statuses = array( 'pending_payment', 'paid', 'cancel_requested', 'cancelled' );
		if ( ! $booking_id || ! in_array( $new_status, $allowed_statuses, true ) ) {
			return false;
		}

		$booking = $this->booking_model->get( $booking_id );
		if ( ! $booking ) {
			return false;
		}

		$old_status = $booking->status;

		if ( $old_status === $new_status && '' === trim( (string) $admin_note ) ) {
			return true;
		}

		$update_data = array(
			'status' => $new_status,
		);

		if ( '' !== $admin_note ) {
			$update_data['admin_note'] = $admin_note;
		}

		$updated = $this->booking_model->update( $booking_id, $update_data );
		if ( ! $updated ) {
			return false;
		}

		do_action(
			'tsrb_booking_status_changed',
			$booking_id,
			$old_status,
			$new_status,
			(int) $changed_by
		);

		$booking = $this->booking_model->get( $booking_id );
		if ( $booking ) {
			$this->send_status_change_email( $booking );
		}

		if ( $this->log_model && method_exists( $this->log_model, 'create' ) ) {
			$note_for_log = '';
			if ( '' !== $admin_note ) {
				$note_for_log = $admin_note;
			}

			$this->log_model->create(
				array(
					'booking_id'         => $booking_id,
					'old_status'         => $old_status,
					'new_status'         => $new_status,
					'changed_by'         => (int) $changed_by,
					'changed_by_display' => '',
					'note'               => $note_for_log,
				)
			);
		}

		return true;
	}

	/* ======================================================
	 *  AVAILABILITY
	 * ===================================================== */

	/**
	 * Check availability slot & build slot map for given date/studio.
	 *
	 * @param string   $date      Y-m-d.
	 * @param int|null $studio_id Studio ID or null.
	 * @return array
	 */
	public function get_availability_for_date( $date, $studio_id = null ) {
		$date      = (string) $date;
		$studio_id = $studio_id ? (int) $studio_id : 0;

		$settings        = $this->get_settings();
		$operating_hours = isset( $settings['operating_hours'] ) ? $settings['operating_hours'] : array();
		$blocked_dates   = isset( $settings['blocked_dates'] ) && is_array( $settings['blocked_dates'] )
			? $settings['blocked_dates']
			: array();

		if ( in_array( $date, $blocked_dates, true ) ) {
			return array(
				'status'       => 'closed',
				'slots'        => array(),
				'total_slots'  => 0,
				'booked_slots' => 0,
			);
		}

		$timestamp = strtotime( $date );
		if ( ! $timestamp ) {
			return array(
				'status'       => 'closed',
				'slots'        => array(),
				'total_slots'  => 0,
				'booked_slots' => 0,
			);
		}

		$weekday_idx = (int) gmdate( 'w', $timestamp );
		$weekday_map = array( 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday' );
		$weekday_key = $weekday_map[ $weekday_idx ];

		if ( empty( $operating_hours[ $weekday_key ]['open'] ) || empty( $operating_hours[ $weekday_key ]['close'] ) ) {
			return array(
				'status'       => 'closed',
				'slots'        => array(),
				'total_slots'  => 0,
				'booked_slots' => 0,
			);
		}

		$open_time  = $operating_hours[ $weekday_key ]['open'];
		$close_time = $operating_hours[ $weekday_key ]['close'];

		$slots   = array();
		$current = strtotime( $date . ' ' . $open_time );
		$end     = strtotime( $date . ' ' . $close_time );

		if ( ! $current || ! $end || $current >= $end ) {
			return array(
				'status'       => 'closed',
				'slots'        => array(),
				'total_slots'  => 0,
				'booked_slots' => 0,
			);
		}

		while ( $current < $end ) {
			$slot_start = date( 'H:i:s', $current );
			$slot_end   = date( 'H:i:s', $current + HOUR_IN_SECONDS );

			$slots[] = array(
				'start_time' => $slot_start,
				'end_time'   => $slot_end,
				'status'     => 'available',
				'customer'   => '',
			);

			$current += HOUR_IN_SECONDS;
		}

		global $wpdb;
		$bookings_table = $wpdb->prefix . 'studio_bookings';

		if ( $studio_id > 0 ) {
			$booking_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$bookings_table}
					 WHERE date = %s
					   AND studio_id = %d
					   AND status IN ('pending_payment','paid')",
					$date,
					$studio_id
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			$booking_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$bookings_table}
					 WHERE date = %s
					   AND status IN ('pending_payment','paid')",
					$date
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		$booked_slots = 0;

		if ( ! empty( $booking_rows ) ) {
			foreach ( $booking_rows as $row ) {
				foreach ( $slots as &$slot ) {
					if ( ! ( $slot['end_time'] <= $row->start_time || $slot['start_time'] >= $row->end_time ) ) {
						$slot['status']   = 'booked';
						$slot['customer'] = $row->user_name;
						$booked_slots++;
					}
				}
				unset( $slot );
			}
		}

		$total_slots = count( $slots );
		$status      = 'available';

		if ( $total_slots > 0 ) {
			if ( 0 === $booked_slots ) {
				$status = 'available';
			} elseif ( $booked_slots >= $total_slots ) {
				$status = 'full';
			} else {
				$status = 'partial';
			}
		}

		return array(
			'status'       => $status,
			'slots'        => $slots,
			'total_slots'  => $total_slots,
			'booked_slots' => $booked_slots,
		);
	}

	/* ======================================================
	 *  BOOKING CREATION (FRONTEND)
	 * ===================================================== */

	/**
	 * Full booking creation flow dari form frontend.
	 *
	 * @param array $data Sanitized data from request.
	 * @return int|WP_Error Booking ID or error.
	 */
	public function create_booking_from_request( $data ) {
		$current_user_id = get_current_user_id();
		if ( ! $current_user_id ) {
			return new WP_Error(
				'not_logged_in',
				__( 'Anda harus login untuk melakukan booking.', 'tribuna-studio-rent-booking' )
			);
		}

		$full_name = isset( $data['full_name'] ) ? $data['full_name'] : '';
		$email     = isset( $data['email'] ) ? $data['email'] : '';
		$phone     = isset( $data['phone'] ) ? $data['phone'] : '';
		$notes     = isset( $data['notes'] ) ? $data['notes'] : '';

		$date       = isset( $data['date'] ) ? $data['date'] : '';
		$slot_start = isset( $data['slot_start'] ) ? $data['slot_start'] : '';
		$slot_end   = isset( $data['slot_end'] ) ? $data['slot_end'] : '';
		$studio_id  = isset( $data['studio_id'] ) ? (int) $data['studio_id'] : 0;

		$selected_addons = ! empty( $data['addons'] ) && is_array( $data['addons'] )
			? array_map( 'absint', $data['addons'] )
			: array();

		$coupon_code = isset( $data['coupon_code'] ) ? $data['coupon_code'] : '';

		if ( empty( $full_name ) || empty( $email ) || empty( $phone ) || empty( $date ) || empty( $slot_start ) || empty( $slot_end ) ) {
			return new WP_Error(
				'missing_fields',
				__( 'Silakan lengkapi semua field yang wajib diisi.', 'tribuna-studio-rent-booking' )
			);
		}

		if ( ! is_email( $email ) ) {
			return new WP_Error(
				'invalid_email',
				__( 'Alamat email tidak valid.', 'tribuna-studio-rent-booking' )
			);
		}

		$start_timestamp = strtotime( $date . ' ' . $slot_start );
		$end_timestamp   = strtotime( $date . ' ' . $slot_end );

		if ( ! $start_timestamp || ! $end_timestamp || $end_timestamp <= $start_timestamp ) {
			return new WP_Error(
				'invalid_time',
				__( 'Rentang waktu booking tidak valid.', 'tribuna-studio-rent-booking' )
			);
		}

		$duration_hours = ( $end_timestamp - $start_timestamp ) / HOUR_IN_SECONDS;
		if ( $duration_hours <= 0 ) {
			return new WP_Error(
				'invalid_duration',
				__( 'Durasi booking tidak valid.', 'tribuna-studio-rent-booking' )
			);
		}

		$settings = $this->get_settings();
		$workflow = isset( $settings['workflow'] ) && is_array( $settings['workflow'] )
			? $settings['workflow']
			: array();

		$min_lead_time_hours            = isset( $workflow['min_lead_time_hours'] ) ? (int) $workflow['min_lead_time_hours'] : 0;
		$max_active_bookings_per_user   = isset( $workflow['max_active_bookings_per_user'] ) ? (int) $workflow['max_active_bookings_per_user'] : 0;
		$prevent_new_if_pending_payment = ! empty( $workflow['prevent_new_if_pending_payment'] ) ? 1 : 0;

		if ( $min_lead_time_hours > 0 && method_exists( $this->booking_model, 'is_slot_respect_lead_time' ) ) {
			$start_time_str = date( 'H:i:s', $start_timestamp );
			if ( ! $this->booking_model->is_slot_respect_lead_time( $date, $start_time_str, $min_lead_time_hours ) ) {
				return new WP_Error(
					'lead_time_too_short',
					sprintf(
						__(
							'Jam mulai terlalu dekat dengan waktu sekarang. Jarak minimal booking adalah %d jam sebelum jam mulai.',
							'tribuna-studio-rent-booking'
						),
						$min_lead_time_hours
					)
				);
			}
		}

		$active_count  = 0;
		$pending_count = 0;

		if ( method_exists( $this->booking_model, 'count_active_by_user' ) ) {
			$active_count = (int) $this->booking_model->count_active_by_user( $current_user_id );
		}

		if ( method_exists( $this->booking_model, 'count_pending_active_by_user' ) ) {
			$pending_count = (int) $this->booking_model->count_pending_active_by_user( $current_user_id );
		}

		if ( $max_active_bookings_per_user > 0 && $active_count >= $max_active_bookings_per_user ) {
			return new WP_Error(
				'active_booking_limit_reached',
				sprintf(
					__(
						'Anda sudah mencapai batas maksimal %d booking aktif (status Lunas atau Menunggu Pembayaran). Silakan selesaikan atau batalkan booking yang ada sebelum membuat booking baru.',
						'tribuna-studio-rent-booking'
					),
					$max_active_bookings_per_user
				)
			);
		}

		if ( $prevent_new_if_pending_payment && $pending_count > 0 ) {
			return new WP_Error(
				'pending_payment_exists',
				__(
					'Anda masih memiliki booking dengan status Menunggu Pembayaran (Pending Payment). Silakan selesaikan pembayaran atau batalkan booking tersebut sebelum membuat booking baru.',
					'tribuna-studio-rent-booking'
				)
			);
		}

		if ( $this->booking_model->has_overlap(
			$date,
			date( 'H:i:s', $start_timestamp ),
			date( 'H:i:s', $end_timestamp )
		) ) {
			return new WP_Error(
				'slot_unavailable',
				__( 'Jam yang dipilih sudah tidak tersedia. Silakan pilih jam lain.', 'tribuna-studio-rent-booking' )
			);
		}

		$base_hourly_price = 0;
		$studio_name       = '';

		if ( $studio_id ) {
			$studio = $this->studio_model ? $this->studio_model->get( $studio_id ) : null;
			if ( $studio ) {
				$studio_name = $studio->name;
				if ( null !== $studio->hourly_price_override && $studio->hourly_price_override > 0 ) {
					$base_hourly_price = (float) $studio->hourly_price_override;
				}
			} else {
				return new WP_Error(
					'invalid_studio',
					__( 'Studio yang dipilih tidak valid.', 'tribuna-studio-rent-booking' )
				);
			}
		} else {
			return new WP_Error(
				'missing_studio',
				__( 'Silakan pilih studio terlebih dahulu.', 'tribuna-studio-rent-booking' )
			);
		}

		if ( $base_hourly_price <= 0 ) {
			return new WP_Error(
				'no_price',
				__( 'Harga sewa studio belum dikonfigurasi. Silakan hubungi admin.', 'tribuna-studio-rent-booking' )
			);
		}

		$addons_price = 0;
		$addon_names  = array();

		if ( ! empty( $selected_addons ) && $this->addon_model ) {
			$all_addons   = $this->addon_model->get_active();
			$addons_by_id = array();

			if ( ! empty( $all_addons ) ) {
				foreach ( $all_addons as $addon ) {
					$addons_by_id[ $addon->id ] = $addon;
				}
			}

			foreach ( $selected_addons as $addon_id ) {
				if ( isset( $addons_by_id[ $addon_id ] ) ) {
					$addons_price += (float) $addons_by_id[ $addon_id ]->price;
					$addon_names[] = $addons_by_id[ $addon_id ]->name;
				}
			}
		}

		$total_price     = ( $base_hourly_price * $duration_hours ) + $addons_price;
		$discount_amount = 0;
		$coupon_row      = null;

		if ( $coupon_code && $this->coupon_model ) {
			$coupon_row = $this->coupon_model->get_by_code( $coupon_code );
			if ( $coupon_row ) {
				if ( (int) $coupon_row->max_usage === 0 || (int) $coupon_row->used_count < (int) $coupon_row->max_usage ) {
					if ( 'percent' === $coupon_row->type ) {
						$discount_amount = ( $total_price * (float) $coupon_row->value ) / 100;
					} else {
						$discount_amount = (float) $coupon_row->value;
					}

					if ( $discount_amount > $total_price ) {
						$discount_amount = $total_price;
					}
				}
			}
		}

		$final_price = $total_price - $discount_amount;
		if ( $final_price < 0 ) {
			$final_price = 0;
		}

		/**
		 * APPLY USER CREDIT (DEPOSIT) SEBELUM SIMPAN BOOKING
		 */
		$credit_used = 0;
		if ( $current_user_id && $final_price > 0 ) {
			$current_credit = $this->get_user_credit( $current_user_id );
			if ( $current_credit > 0 ) {
				$credit_used  = min( $final_price, $current_credit );
				$final_price -= $credit_used;
				$this->deduct_user_credit( $current_user_id, $credit_used );
			}
		}

		$timezone = isset( $settings['timezone'] ) ? $settings['timezone'] : get_option( 'timezone_string', 'Asia/Jakarta' );

		$gc_url = Tribuna_Helpers::build_google_calendar_url(
			array(
				'title'       => sprintf( __( 'Booking Studio - %s', 'tribuna-studio-rent-booking' ), $full_name ),
				'description' => sprintf(
					__(
						"Booking atas nama %s\nStudio: %s\nTanggal: %s\nJam: %s - %s\nDurasi: %d jam\nTotal: %s",
						'tribuna-studio-rent-booking'
					),
					$full_name,
					$studio_name,
					$date,
					$slot_start,
					$slot_end,
					$duration_hours,
					Tribuna_Helpers::format_price( $final_price )
				),
				'start'       => date( 'Y-m-d H:i:s', $start_timestamp ),
				'end'         => date( 'Y-m-d H:i:s', $end_timestamp ),
				'timezone'    => $timezone,
				'location'    => '',
			)
		);

		$addons_str = '';
		if ( ! empty( $addon_names ) ) {
			$addons_str = implode( ', ', $addon_names );
		}

		$status = 'pending_payment';
		if ( isset( $workflow['require_manual_approval'] ) && $workflow['require_manual_approval'] ) {
			$status = 'pending_payment';
		}

		$booking_id = $this->booking_model->create(
			array(
				'user_id'             => $current_user_id,
				'user_name'           => $full_name,
				'email'               => $email,
				'phone'               => $phone,
				'date'                => $date,
				'start_time'          => date( 'H:i:s', $start_timestamp ),
				'end_time'            => date( 'H:i:s', $end_timestamp ),
				'duration'            => (int) $duration_hours,
				'studio_id'           => $studio_id,
				'addons'              => $addons_str,
				'total_price'         => $total_price,
				'coupon_code'         => $coupon_row ? $coupon_row->code : null,
				'discount_amount'     => $discount_amount,
				'final_price'         => $final_price,
				'status'              => $status,
				'admin_note'          => $notes,
				'google_calendar_url' => $gc_url,
				'channel'             => 'website',
				// info refund & credit default 0/null (diisi saat cancel).
			)
		);

		if ( ! $booking_id ) {
			// Kalau booking gagal dibuat dan ada credit yang sudah dikurangi, idealnya dikembalikan.
			if ( $credit_used > 0 && $current_user_id ) {
				$this->add_user_credit( $current_user_id, $credit_used );
			}

			return new WP_Error(
				'create_failed',
				__( 'Terjadi kesalahan saat menyimpan booking. Silakan coba lagi.', 'tribuna-studio-rent-booking' )
			);
		}

		if ( $coupon_row && $this->coupon_model ) {
			$this->coupon_model->increment_usage( $coupon_row->id );
		}

		$this->send_new_booking_emails( $booking_id );

		return $booking_id;
	}

	/* ======================================================
	 *  EMAIL NEW BOOKING & STATUS CHANGE
	 * ===================================================== */

	/**
	 * Send email for new booking (user + admin) using templates.
	 *
	 * @param int $booking_id Booking ID.
	 */
	public function send_new_booking_emails( $booking_id ) {
		$booking = $this->booking_model->get( $booking_id );
		if ( ! $booking ) {
			return;
		}

		$settings    = $this->get_settings();
		$emails_conf = isset( $settings['emails'] ) && is_array( $settings['emails'] )
			? $settings['emails']
			: array();

		$site_name   = get_bloginfo( 'name' );
		$admin_email = isset( $settings['admin_email'] ) ? sanitize_email( $settings['admin_email'] ) : get_option( 'admin_email' );
		$user_email  = sanitize_email( $booking->email );

		$studio_name = '';
		if ( $this->studio_model && ! empty( $booking->studio_id ) ) {
			$studio      = $this->studio_model->get( (int) $booking->studio_id );
			$studio_name = ( $studio && ! empty( $studio->name ) ) ? $studio->name : '';
		}

		$replacements = array(
			'{customer_name}' => $booking->user_name,
			'{booking_id}'    => $booking->id,
			'{studio_name}'   => $studio_name,
			'{booking_date}'  => $booking->date,
			'{start_time}'    => $booking->start_time,
			'{end_time}'      => $booking->end_time,
			'{total}'         => Tribuna_Helpers::format_price( $booking->final_price ),
			'{status}'        => $booking->status,
			'{site_name}'     => $site_name,
		);

		$default_cust_subject = __( 'Permintaan booking Anda telah diterima', 'tribuna-studio-rent-booking' );
		$default_cust_body    = __(
			"Halo {customer_name},\n\nTerima kasih atas permintaan booking Anda untuk {studio_name} pada {booking_date} pukul {start_time}.\n\nTotal: {total}\nStatus: {status}\n\nSalam,\n{site_name}",
			'tribuna-studio-rent-booking'
		);

		$cust_subject_tpl = isset( $emails_conf['customer_new_subject'] ) && '' !== trim( $emails_conf['customer_new_subject'] )
			? $emails_conf['customer_new_subject']
			: $default_cust_subject;

		$cust_body_tpl = isset( $emails_conf['customer_new_body'] ) && '' !== trim( $emails_conf['customer_new_body'] )
			? $emails_conf['customer_new_body']
			: $default_cust_body;

		$cust_subject = strtr( $cust_subject_tpl, $replacements );
		$cust_body    = strtr( $cust_body_tpl, $replacements );

		if ( $user_email ) {
			$cust_headers = array( 'Content-Type: text/html; charset=UTF-8' );
			wp_mail( $user_email, $cust_subject, nl2br( $cust_body ), $cust_headers );
		}

		$default_admin_subject = __( 'Booking baru diterima', 'tribuna-studio-rent-booking' );
		$default_admin_body    = __(
			"Booking baru diterima:\n\nCustomer: {customer_name}\nStudio: {studio_name}\nTanggal: {booking_date}\nJam: {start_time} - {end_time}\nTotal: {total}\nStatus: {status}\n\nBooking ID: {booking_id}",
			'tribuna-studio-rent-booking'
		);

		$admin_subject_tpl = isset( $emails_conf['admin_new_subject'] ) && '' !== trim( $emails_conf['admin_new_subject'] )
			? $emails_conf['admin_new_subject']
			: $default_admin_subject;

		$admin_body_tpl = isset( $emails_conf['admin_new_body'] ) && '' !== trim( $emails_conf['admin_new_body'] )
			? $emails_conf['admin_new_body']
			: $default_admin_body;

		$admin_subject = strtr( $admin_subject_tpl, $replacements );
		$admin_body    = strtr( $admin_body_tpl, $replacements );

		if ( $admin_email ) {
			$admin_headers = array( 'Content-Type: text/html; charset=UTF-8' );
			wp_mail( $admin_email, $admin_subject, nl2br( $admin_body ), $admin_headers );
		}
	}

	/**
	 * Send email when booking status changed using templates.
	 *
	 * @param object $booking Booking row.
	 */
	public function send_status_change_email( $booking ) {
		if ( ! $booking ) {
			return;
		}

		$settings    = $this->get_settings();
		$emails_conf = isset( $settings['emails'] ) && is_array( $settings['emails'] )
			? $settings['emails']
			: array();

		$site_name   = get_bloginfo( 'name' );
		$admin_email = isset( $settings['admin_email'] ) ? sanitize_email( $settings['admin_email'] ) : get_option( 'admin_email' );
		$user_email  = sanitize_email( $booking->email );

		$studio_name = '';
		if ( $this->studio_model && ! empty( $booking->studio_id ) ) {
			$studio      = $this->studio_model->get( (int) $booking->studio_id );
			$studio_name = ( $studio && ! empty( $studio->name ) ) ? $studio->name : '';
		}

		$status_label = ucfirst( str_replace( '_', ' ', $booking->status ) );

		$replacements = array(
			'{customer_name}' => $booking->user_name,
			'{booking_id}'    => $booking->id,
			'{studio_name}'   => $studio_name,
			'{booking_date}'  => $booking->date,
			'{start_time}'    => $booking->start_time,
			'{end_time}'      => $booking->end_time,
			'{total}'         => Tribuna_Helpers::format_price( $booking->final_price ),
			'{status}'        => $status_label,
			'{site_name}'     => $site_name,
		);

		$customer_subject_tpl = '';
		$customer_body_tpl    = '';

		if ( 'paid' === $booking->status ) {
			$customer_subject_tpl = isset( $emails_conf['customer_paid_subject'] ) && '' !== trim( $emails_conf['customer_paid_subject'] )
				? $emails_conf['customer_paid_subject']
				: __( 'Booking Anda telah dikonfirmasi', 'tribuna-studio-rent-booking' );

			$customer_body_tpl = isset( $emails_conf['customer_paid_body'] ) && '' !== trim( $emails_conf['customer_paid_body'] )
				? $emails_conf['customer_paid_body']
				: __(
					"Halo {customer_name},\n\nBooking Anda sudah dikonfirmasi.\n\nStudio: {studio_name}\nTanggal: {booking_date}\nJam: {start_time} - {end_time}\nTotal: {total}\n\nSampai jumpa di studio.\n{site_name}",
					'tribuna-studio-rent-booking'
				);
		} elseif ( 'cancelled' === $booking->status ) {
			$customer_subject_tpl = isset( $emails_conf['customer_cancel_subject'] ) && '' !== trim( $emails_conf['customer_cancel_subject'] )
				? $emails_conf['customer_cancel_subject']
				: __( 'Booking Anda telah dibatalkan', 'tribuna-studio-rent-booking' );

			$customer_body_tpl = isset( $emails_conf['customer_cancel_body'] ) && '' !== trim( $emails_conf['customer_cancel_body'] )
				? $emails_conf['customer_cancel_body']
				: __(
					"Halo {customer_name},\n\nBooking Anda untuk {studio_name} pada {booking_date} telah dibatalkan.\n\nJika pembatalan ini tidak Anda lakukan, silakan hubungi kami.\n{site_name}",
					'tribuna-studio-rent-booking'
				);
		} else {
			$customer_subject_tpl = sprintf(
				__( 'Status booking Anda berubah menjadi %s', 'tribuna-studio-rent-booking' ),
				$status_label
			);
			$customer_body_tpl = __(
				"Halo {customer_name},\n\nStatus booking Anda telah diperbarui menjadi: {status}\n\nTanggal: {booking_date}\nJam: {start_time} - {end_time}\nTotal: {total}\n\nTerima kasih.\n{site_name}",
				'tribuna-studio-rent-booking'
			);
		}

		$customer_subject = strtr( $customer_subject_tpl, $replacements );
		$customer_body    = strtr( $customer_body_tpl, $replacements );

		if ( $user_email ) {
			$cust_headers = array( 'Content-Type: text/html; charset=UTF-8' );
			wp_mail( $user_email, $customer_subject, nl2br( $customer_body ), $cust_headers );
		}

		$admin_subject = sprintf(
			__( 'Status Booking #%d berubah', 'tribuna-studio-rent-booking' ),
			$booking->id
		);

		$admin_body = sprintf(
			__(
				"Booking #%1\$d berubah status menjadi %2\$s.\n\nCustomer: %3\$s\nStudio: %4\$s\nTanggal: %5\$s\nJam: %6\$s - %7\$s\nTotal: %8\$s\n\nSitus: %9\$s",
				'tribuna-studio-rent-booking'
			),
			$booking->id,
			$status_label,
			$booking->user_name,
			$studio_name,
			$booking->date,
			$booking->start_time,
			$booking->end_time,
			Tribuna_Helpers::format_price( $booking->final_price ),
			$site_name
		);

		if ( $admin_email ) {
			$admin_headers = array( 'Content-Type: text/html; charset=UTF-8' );
			wp_mail( $admin_email, $admin_subject, nl2br( $admin_body ), $admin_headers );
		}
	}

	/* ======================================================
	 *  SETTINGS
	 * ===================================================== */

	/**
	 * Get settings with defaults.
	 *
	 * Menggabungkan option baru `tsrbsettings` (hasil form Settings)
	 * dengan option legacy `tsrb_settings` (hasil dari versi lama),
	 * lalu di-merge dengan default sehingga perubahan di tab Workflow
	 * termasuk flag `prevent_new_if_pending_payment` terbaca dengan benar.
	 *
	 * @return array
	 */
	protected function get_settings() {
		if ( null !== $this->settings ) {
			return $this->settings;
		}

		$defaults = array(
			'hourly_price'        => 75000,
			'currency'            => 'IDR',
			'admin_email'         => get_option( 'admin_email' ),
			'payment_qr_image_id' => 0,
			'timezone'            => 'Asia/Jakarta',
			'operating_hours'     => array(),
			'blocked_dates'       => array(),
			'emails'              => array(),
			'workflow'            => array(),
			'integrations'        => array(),
		);

		// Option baru (utama) dan option lama (legacy).
		$new_settings = get_option( 'tsrbsettings', array() );
		if ( ! is_array( $new_settings ) ) {
			$new_settings = array();
		}

		$legacy_settings = get_option( 'tsrb_settings', array() );
		if ( ! is_array( $legacy_settings ) ) {
			$legacy_settings = array();
		}

		// tsrbsettings override tsrb_settings, lalu merge dengan defaults.
		$merged          = wp_parse_args( $new_settings, $legacy_settings );
		$this->settings = wp_parse_args( $merged, $defaults );

		return $this->settings;
	}

	/* ======================================================
	 *  CREDIT / DEPOSIT PER USER
	 * ===================================================== */

	/**
	 * Ambil saldo credit user.
	 *
	 * @param int $user_id User ID.
	 * @return float
	 */
	public function get_user_credit( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return 0;
		}

		$raw = get_user_meta( $user_id, 'tsrb_credit_balance', true );
		if ( '' === $raw || null === $raw ) {
			return 0;
		}

		return max( 0, (float) $raw );
	}

	/**
	 * Tambah saldo credit user.
	 *
	 * @param int   $user_id User ID.
	 * @param float $amount  Amount.
	 * @return void
	 */
	public function add_user_credit( $user_id, $amount ) {
		$user_id = (int) $user_id;
		$amount  = (float) $amount;

		if ( $user_id <= 0 || $amount <= 0 ) {
			return;
		}

		$current = $this->get_user_credit( $user_id );
		$new     = $current + $amount;

		update_user_meta( $user_id, 'tsrb_credit_balance', $new );
	}

	/**
	 * Kurangi saldo credit user.
	 *
	 * @param int   $user_id User ID.
	 * @param float $amount  Amount.
	 * @return void
	 */
	public function deduct_user_credit( $user_id, $amount ) {
		$user_id = (int) $user_id;
		$amount  = (float) $amount;

		if ( $user_id <= 0 || $amount <= 0 ) {
			return;
		}

		$current = $this->get_user_credit( $user_id );
		$new     = $current - $amount;

		if ( $new < 0 ) {
			$new = 0;
		}

		update_user_meta( $user_id, 'tsrb_credit_balance', $new );
	}

	/* ======================================================
	 *  CANCELLATION & REFUND POLICY
	 * ===================================================== */

	/**
	 * Evaluasi kebijakan cancellation & refund untuk satu booking.
	 *
	 * @param object     $booking      Booking row.
	 * @param int|string $request_time Timestamp atau string waktu request (optional).
	 * @return array
	 */
	public function evaluate_cancellation_policy( $booking, $request_time = null ) {
		if ( ! $booking ) {
			return array(
				'allowed'        => false,
				'refund_type'    => 'none',
				'refund_percent' => 0,
				'credit_percent' => 0,
				'reason'         => 'booking_not_found',
				'hours_before'   => 0,
			);
		}

		// Tentukan waktu request.
		if ( null === $request_time ) {
			$request_ts = current_time( 'timestamp' );
		} elseif ( is_numeric( $request_time ) ) {
			$request_ts = (int) $request_time;
		} else {
			$request_ts = strtotime( (string) $request_time );
			if ( ! $request_ts ) {
				$request_ts = current_time( 'timestamp' );
			}
		}

		$settings = $this->get_settings();
		$workflow = isset( $settings['workflow'] ) && is_array( $settings['workflow'] )
			? $settings['workflow']
			: array();

		$full_hours_before    = isset( $workflow['refund_full_hours_before'] ) ? (int) $workflow['refund_full_hours_before'] : 24;
		$partial_hours_before = isset( $workflow['refund_partial_hours_before'] ) ? (int) $workflow['refund_partial_hours_before'] : 3;
		$partial_percent      = isset( $workflow['refund_partial_percent'] ) ? (int) $workflow['refund_partial_percent'] : 70;
		$no_refund_inside     = isset( $workflow['refund_no_refund_inside_hours'] ) ? (int) $workflow['refund_no_refund_inside_hours'] : 0;

		// Normalisasi nilai negatif.
		$full_hours_before    = max( 0, $full_hours_before );
		$partial_hours_before = max( 0, $partial_hours_before );
		$no_refund_inside     = max( 0, $no_refund_inside );
		$partial_percent      = max( 0, min( 100, $partial_percent ) );

		$start_ts = strtotime( $booking->date . ' ' . $booking->start_time );
		if ( ! $start_ts ) {
			return array(
				'allowed'        => false,
				'refund_type'    => 'none',
				'refund_percent' => 0,
				'credit_percent' => 0,
				'reason'         => 'invalid_booking_time',
				'hours_before'   => 0,
			);
		}

		// Jika request setelah jam mulai: dianggap no-show → tidak ada refund, tapi allowed untuk ditandai cancelled.
		if ( $request_ts >= $start_ts ) {
			return array(
				'allowed'        => true,
				'refund_type'    => 'none',
				'refund_percent' => 0,
				'credit_percent' => 0,
				'reason'         => 'no_show_or_late',
				'hours_before'   => 0,
			);
		}

		$diff_hours = ( $start_ts - $request_ts ) / HOUR_IN_SECONDS;
		$diff_hours = floor( $diff_hours * 100 ) / 100; // 2 desimal.

		// Zona no-refund paling ketat.
		if ( $no_refund_inside > 0 && $diff_hours < $no_refund_inside ) {
			return array(
				'allowed'        => true,
				'refund_type'    => 'none',
				'refund_percent' => 0,
				'credit_percent' => 0,
				'reason'         => 'inside_no_refund_window',
				'hours_before'   => $diff_hours,
			);
		}

		// Full refund jika cukup jauh sebelum jam mulai.
		if ( $full_hours_before > 0 && $diff_hours >= $full_hours_before ) {
			return array(
				'allowed'        => true,
				'refund_type'    => 'full',
				'refund_percent' => 100,
				'credit_percent' => 0,
				'reason'         => 'before_full_refund_window',
				'hours_before'   => $diff_hours,
			);
		}

		// Partial refund jika masih di jendela partial.
		if ( $partial_hours_before > 0 && $partial_percent > 0 && $diff_hours >= $partial_hours_before ) {
			return array(
				'allowed'        => true,
				'refund_type'    => 'partial',
				'refund_percent' => $partial_percent,
				'credit_percent' => 0,
				'reason'         => 'inside_partial_refund_window',
				'hours_before'   => $diff_hours,
			);
		}

		// Jika konfigurasi kurang bagus (misal semua 0) → tetap izinkan cancel tapi tanpa refund.
		return array(
			'allowed'        => true,
			'refund_type'    => 'none',
			'refund_percent' => 0,
			'credit_percent' => 0,
			'reason'         => 'default_no_refund',
			'hours_before'   => $diff_hours,
		);
	}

	/**
	 * Apply hasil evaluasi cancellation ke booking:
	 * - hitung refund_amount dan credit_amount
	 * - update booking (status, refund_type, dsb.)
	 * - update saldo credit user
	 * - log & email status change
	 *
	 * @param int   $booking_id    Booking ID.
	 * @param array $policy_result Hasil evaluate_cancellation_policy().
	 * @param int   $changed_by    User ID admin/system.
	 * @return bool
	 */
	public function apply_cancellation_result( $booking_id, $policy_result, $changed_by = 0 ) {
		$booking_id = (int) $booking_id;
		if ( $booking_id <= 0 || empty( $policy_result ) || ! is_array( $policy_result ) ) {
			return false;
		}

		$booking = $this->booking_model->get( $booking_id );
		if ( ! $booking ) {
			return false;
		}

		$final_price = (float) $booking->final_price;
		if ( $final_price < 0 ) {
			$final_price = 0;
		}

		$refund_percent = isset( $policy_result['refund_percent'] ) ? (int) $policy_result['refund_percent'] : 0;
		$credit_percent = isset( $policy_result['credit_percent'] ) ? (int) $policy_result['credit_percent'] : 0;
		$refund_type    = isset( $policy_result['refund_type'] ) ? $policy_result['refund_type'] : 'none';
		$reason         = isset( $policy_result['reason'] ) ? $policy_result['reason'] : '';

		$refund_percent = max( 0, min( 100, $refund_percent ) );
		$credit_percent = max( 0, min( 100, $credit_percent ) );

		$refund_amount = round( $final_price * $refund_percent / 100 );
		$credit_amount = round( $final_price * $credit_percent / 100 );

		if ( $credit_amount > 0 && $booking->user_id ) {
			$this->add_user_credit( (int) $booking->user_id, $credit_amount );
		}

		$update_data = array(
			'status'        => 'cancelled',
			'refund_type'   => $refund_type,
			'refund_amount' => $refund_amount,
			'credit_amount' => $credit_amount,
			'cancel_reason' => $reason,
		);

		$updated = $this->booking_model->update( $booking_id, $update_data );
		if ( ! $updated ) {
			return false;
		}

		if ( $this->log_model && method_exists( $this->log_model, 'create' ) ) {
			$note_parts = array(
				'Cancellation processed',
				'Refund: ' . Tribuna_Helpers::format_price( $refund_amount ),
				'Credit: ' . Tribuna_Helpers::format_price( $credit_amount ),
				'Type: ' . $refund_type,
				'Reason: ' . $reason,
			);

			$this->log_model->create(
				array(
					'booking_id'         => $booking_id,
					'old_status'         => $booking->status,
					'new_status'         => 'cancelled',
					'changed_by'         => (int) $changed_by,
					'changed_by_display' => $changed_by ? '' : 'System',
					'note'               => implode( '; ', $note_parts ),
				)
			);
		}

		$updated_booking = $this->booking_model->get( $booking_id );
		if ( $updated_booking ) {
			$this->send_status_change_email( $updated_booking );
		}

		return true;
	}

	/**
	 * Dipakai oleh member (AJAX) untuk submit request cancel.
	 *
	 * Hanya mengubah status ke cancel_requested dan log,
	 * keputusan final refund tetap di admin (handle_admin_cancel_action).
	 *
	 * @param int $booking_id Booking ID.
	 * @param int $user_id    Current user ID.
	 * @return true|WP_Error
	 */
	public function handle_member_cancel_request( $booking_id, $user_id ) {
		$booking_id = (int) $booking_id;
		$user_id    = (int) $user_id;

		if ( $booking_id <= 0 || $user_id <= 0 ) {
			return new WP_Error( 'invalid_data', __( 'Invalid booking or user.', 'tribuna-studio-rent-booking' ) );
		}

		$booking = $this->booking_model->get( $booking_id );
		if ( ! $booking ) {
			return new WP_Error( 'not_found', __( 'Booking not found.', 'tribuna-studio-rent-booking' ) );
		}

		if ( (int) $booking->user_id !== $user_id ) {
			return new WP_Error( 'forbidden', __( 'You are not allowed to cancel this booking.', 'tribuna-studio-rent-booking' ) );
		}

		if ( ! in_array( $booking->status, array( 'pending_payment', 'paid' ), true ) ) {
			return new WP_Error( 'invalid_status', __( 'This booking cannot be cancelled in its current status.', 'tribuna-studio-rent-booking' ) );
		}

		$policy = $this->evaluate_cancellation_policy( $booking );
		if ( empty( $policy['allowed'] ) ) {
			return new WP_Error( 'not_allowed', __( 'Cancellation is not allowed for this booking.', 'tribuna-studio-rent-booking' ) );
		}

		$updated = $this->booking_model->update(
			$booking_id,
			array(
				'status'        => 'cancel_requested',
				'cancel_reason' => $policy['reason'],
			)
		);

		if ( ! $updated ) {
			return new WP_Error( 'update_failed', __( 'Failed to submit cancellation request.', 'tribuna-studio-rent-booking' ) );
		}

		if ( $this->log_model && method_exists( $this->log_model, 'create' ) ) {
			$this->log_model->create(
				array(
					'booking_id'         => $booking_id,
					'old_status'         => $booking->status,
					'new_status'         => 'cancel_requested',
					'changed_by'         => $user_id,
					'changed_by_display' => '',
					'note'               => __( 'Member requested cancellation.', 'tribuna-studio-rent-booking' ),
				)
			);
		}

		return true;
	}

	/**
	 * Dipakai admin untuk approve / reject cancellation request.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $action     approve|reject.
	 * @param int    $admin_id   Admin user ID.
	 * @return true|WP_Error
	 */
	public function handle_admin_cancel_action( $booking_id, $action, $admin_id ) {
		$booking_id = (int) $booking_id;
		$admin_id   = (int) $admin_id;
		$action     = (string) $action;

		if ( $booking_id <= 0 || $admin_id <= 0 ) {
			return new WP_Error( 'invalid_data', __( 'Invalid booking or user.', 'tribuna-studio-rent-booking' ) );
		}

		$booking = $this->booking_model->get( $booking_id );
		if ( ! $booking ) {
			return new WP_Error( 'not_found', __( 'Booking not found.', 'tribuna-studio-rent-booking' ) );
		}

		if ( ! in_array( $booking->status, array( 'cancel_requested', 'pending_payment', 'paid' ), true ) ) {
			return new WP_Error( 'invalid_status', __( 'This booking cannot be processed for cancellation.', 'tribuna-studio-rent-booking' ) );
		}

		if ( 'reject' === $action ) {
			// Untuk kesederhanaan, kembalikan ke 'paid' jika sebelumnya bukan pending_payment.
			$new_status = ( 'pending_payment' === $booking->status ) ? 'pending_payment' : 'paid';

			$updated = $this->booking_model->update(
				$booking_id,
				array(
					'status'        => $new_status,
					'cancel_reason' => null,
				)
			);

			if ( ! $updated ) {
				return new WP_Error( 'update_failed', __( 'Failed to reject cancellation.', 'tribuna-studio-rent-booking' ) );
			}

			if ( $this->log_model && method_exists( $this->log_model, 'create' ) ) {
				$this->log_model->create(
					array(
						'booking_id'         => $booking_id,
						'old_status'         => $booking->status,
						'new_status'         => $new_status,
						'changed_by'         => $admin_id,
						'changed_by_display' => '',
						'note'               => __( 'Cancellation request rejected by admin.', 'tribuna-studio-rent-booking' ),
					)
				);
			}

			$updated_booking = $this->booking_model->get( $booking_id );
			if ( $updated_booking ) {
				$this->send_status_change_email( $updated_booking );
			}

			return true;
		}

		// approve → hitung policy dan terapkan.
		$policy = $this->evaluate_cancellation_policy( $booking );

		$ok = $this->apply_cancellation_result( $booking_id, $policy, $admin_id );
		if ( ! $ok ) {
			return new WP_Error( 'process_failed', __( 'Failed to process cancellation.', 'tribuna-studio-rent-booking' ) );
		}

		return true;
	}

	/* ======================================================
	 *  AUTO-CANCEL PENDING PAYMENT (CRON SUPPORT)
	 * ===================================================== */

	/**
	 * Auto-cancel booking pending_payment berdasarkan workflow:
	 * - auto_cancel_unpaid_hours (global)
	 * - auto_cancel_unpaid_same_day_hours (khusus booking hari ini)
	 *
	 * Auto-cancel selalu tanpa refund (booking belum dibayar).
	 *
	 * @return int Jumlah booking yang dibatalkan.
	 */
	public function auto_cancel_unpaid_bookings() {
		$settings = $this->get_settings();
		$workflow = isset( $settings['workflow'] ) && is_array( $settings['workflow'] )
			? $settings['workflow']
			: array();

		$hours_global   = isset( $workflow['auto_cancel_unpaid_hours'] ) ? (int) $workflow['auto_cancel_unpaid_hours'] : 0;
		$hours_same_day = isset( $workflow['auto_cancel_unpaid_same_day_hours'] ) ? (int) $workflow['auto_cancel_unpaid_same_day_hours'] : 0;

		if ( $hours_global <= 0 && $hours_same_day <= 0 ) {
			return 0;
		}

		$now_ts = current_time( 'timestamp' );
		$today  = gmdate(
			'Y-m-d',
			$now_ts + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS )
		);

		// Ambil kandidat booking pending_payment yang berumur minimal max(hours_global, hours_same_day).
		$threshold_hours = max( $hours_global, $hours_same_day );
		$candidates      = $this->booking_model->get_unpaid_bookings_for_auto_cancel( $threshold_hours );
		if ( empty( $candidates ) ) {
			return 0;
		}

		$cancelled_count = 0;

		foreach ( $candidates as $booking ) {
			if ( 'pending_payment' !== $booking->status ) {
				continue;
			}

			$created_ts = strtotime( $booking->created_at );
			if ( ! $created_ts ) {
				continue;
			}

			$age_hours = ( $now_ts - $created_ts ) / HOUR_IN_SECONDS;

			// Booking hari ini → gunakan rule same_day jika diset.
			if ( $hours_same_day > 0 && $booking->date === $today ) {
				if ( $age_hours < $hours_same_day ) {
					continue;
				}

				$policy = array(
					'allowed'        => true,
					'refund_type'    => 'none',
					'refund_percent' => 0,
					'credit_percent' => 0,
					'reason'         => 'auto_cancel_pending_same_day',
				);
			} else {
				// Global.
				if ( $hours_global <= 0 || $age_hours < $hours_global ) {
					continue;
				}

				$policy = array(
					'allowed'        => true,
					'refund_type'    => 'none',
					'refund_percent' => 0,
					'credit_percent' => 0,
					'reason'         => 'auto_cancel_pending_global',
				);
			}

			$ok = $this->apply_cancellation_result( (int) $booking->id, $policy, 0 );
			if ( $ok ) {
				$cancelled_count++;
			}
		}

		return $cancelled_count;
	}

	/* ======================================================
	 *  ADMIN SUPPORT: WIDGET STATS, UPCOMING, PAYMENT TIMER
	 * ===================================================== */

	/**
	 * Ambil data statistik untuk widget di halaman Bookings admin.
	 *
	 * @return array
	 */
	public function get_bookings_widget_stats() {
		if ( ! method_exists( $this->booking_model, 'get_widget_overview_stats' ) ) {
			return array(
				'upcoming_7_days'  => 0,
				'today'            => 0,
				'pending_payment'  => 0,
				'cancelled_7_days' => 0,
			);
		}

		return $this->booking_model->get_widget_overview_stats();
	}

	/**
	 * Ambil daftar upcoming bookings untuk widget (dashboard / bookings admin).
	 *
	 * @param array $args {
	 *     Optional. Args untuk upcoming.
	 *
	 *     @type int      $days_ahead Range hari ke depan. Default 7.
	 *     @type int      $limit      Jumlah booking maksimum. Default 10.
	 *     @type string[] $statuses   Status yang di-include. Default ['pending_payment','paid'].
	 * }
	 * @return array List row booking (WPDB results).
	 */
	public function get_upcoming_bookings( $args = array() ) {
		if ( ! method_exists( $this->booking_model, 'get_upcoming_bookings' ) ) {
			return array();
		}

		$mapped_args = array(
			'days_ahead' => isset( $args['days_ahead'] ) ? (int) $args['days_ahead'] : 7,
			'limit'      => isset( $args['limit'] ) ? (int) $args['limit'] : 10,
		);

		if ( isset( $args['statuses'] ) && is_array( $args['statuses'] ) ) {
			$mapped_args['statuses'] = $args['statuses'];
		}

		return $this->booking_model->get_upcoming_bookings( $mapped_args );
	}

	/**
	 * Ambil durasi batas waktu pembayaran (dalam detik)
	 * dari pengaturan Workflow & Policies.
	 *
	 * Mengambil nilai dari:
	 *   $settings['workflow']['payment_deadline_hours']
	 *
	 * @return int Durasi dalam detik (0 jika tidak dikonfigurasi).
	 */
	public function get_payment_window_seconds() {
		$settings = $this->get_settings();
		$workflow = isset( $settings['workflow'] ) && is_array( $settings['workflow'] )
			? $settings['workflow']
			: array();

		$hours = isset( $workflow['payment_deadline_hours'] )
			? (int) $workflow['payment_deadline_hours']
			: 0;

		if ( $hours <= 0 ) {
			return 0;
		}

		return $hours * HOUR_IN_SECONDS;
	}
}
