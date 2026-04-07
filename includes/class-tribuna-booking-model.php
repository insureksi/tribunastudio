<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Booking model.
 */
class Tribuna_Booking_Model {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'studio_bookings';
	}

	/**
	 * Create booking.
	 *
	 * @param array $data Booking data.
	 * @return int|false
	 */
	public function create( $data ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$defaults = array(
			'user_id'             => null,
			'user_name'           => '',
			'email'               => '',
			'phone'               => '',
			'studio_id'           => null,
			'date'                => '',
			'start_time'          => '',
			'end_time'            => '',
			'duration'            => 1,
			'addons'              => '',
			'total_price'         => 0,
			'coupon_code'         => null,
			'discount_amount'     => 0,
			'final_price'         => 0,
			'status'              => 'pending_payment',
			'payment_proof'       => null,
			'admin_note'          => null,
			'google_calendar_url' => null,
			'channel'             => 'website',

			// Kolom Cancellation & Refund.
			'refund_type'   => null,
			'refund_amount' => 0,
			'credit_amount' => 0,
			'cancel_reason' => null,

			'created_at' => $now,
			'updated_at' => $now,
		);

		$data = wp_parse_args( $data, $defaults );

		$inserted = $wpdb->insert(
			$this->table,
			$data,
			array(
				'%d', // user_id.
				'%s', // user_name.
				'%s', // email.
				'%s', // phone.
				'%d', // studio_id.
				'%s', // date.
				'%s', // start_time.
				'%s', // end_time.
				'%d', // duration.
				'%s', // addons.
				'%f', // total_price.
				'%s', // coupon_code.
				'%f', // discount_amount.
				'%f', // final_price.
				'%s', // status.
				'%s', // payment_proof.
				'%s', // admin_note.
				'%s', // google_calendar_url.
				'%s', // channel.
				'%s', // refund_type.
				'%f', // refund_amount.
				'%f', // credit_amount.
				'%s', // cancel_reason.
				'%s', // created_at.
				'%s', // updated_at.
			)
		);

		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update booking (generic).
	 *
	 * @param int   $id   Booking ID.
	 * @param array $data Data.
	 * @return bool
	 */
	public function update( $id, $data ) {
		global $wpdb;

		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}

		if ( ! is_array( $data ) || empty( $data ) ) {
			return false;
		}

		$data['updated_at'] = current_time( 'mysql' );

		$updated = $wpdb->update(
			$this->table,
			$data,
			array( 'id' => $id ),
			null,
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Update status only (single) + trigger event + log.
	 *
	 * @param int    $id         Booking ID.
	 * @param string $new_status New status.
	 * @param int    $changed_by User ID (admin/member/system).
	 * @return bool
	 */
	public function update_status( $id, $new_status, $changed_by = 0 ) {
		$id         = (int) $id;
		$new_status = (string) $new_status;

		$booking = $this->get( $id );
		if ( ! $booking ) {
			return false;
		}

		$old_status = $booking->status;

		$updated = $this->update(
			$id,
			array(
				'status' => $new_status,
			)
		);

		if ( $updated ) {
			// Event untuk hook lain.
			do_action(
				'tsrb_booking_status_changed',
				(int) $id,
				$old_status,
				$new_status,
				(int) $changed_by
			);

			// Activity log fallback: jika service tidak dipakai, tetap tercatat.
			if ( class_exists( 'Tribuna_Booking_Log_Model' ) ) {
				Tribuna_Booking_Log_Model::log_status_change(
					$id,
					$old_status,
					$new_status,
					$changed_by,
					null
				);
			}
		}

		return $updated;
	}

	/**
	 * Bulk update status untuk beberapa booking + event + log per booking.
	 *
	 * @param array  $ids        Booking IDs.
	 * @param string $new_status Status baru.
	 * @param int    $changed_by User ID admin.
	 * @return int Jumlah booking yang berhasil diupdate.
	 */
	public function bulk_update_status( $ids, $new_status, $changed_by = 0 ) {
		$ids = array_filter( array_map( 'intval', (array) $ids ) );
		if ( empty( $ids ) ) {
			return 0;
		}

		global $wpdb;

		// Ambil status lama untuk trigger event & log per booking.
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql          = $wpdb->prepare(
			"SELECT id, status FROM {$this->table} WHERE id IN ($placeholders)",
			$ids
		);
		$rows         = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( empty( $rows ) ) {
			return 0;
		}

		$updated_count = 0;

		foreach ( $rows as $row ) {
			$updated = $this->update(
				(int) $row->id,
				array(
					'status' => $new_status,
				)
			);

			if ( $updated ) {
				$updated_count++;

				do_action(
					'tsrb_booking_status_changed',
					(int) $row->id,
					$row->status,
					$new_status,
					(int) $changed_by
				);

				if ( class_exists( 'Tribuna_Booking_Log_Model' ) ) {
					Tribuna_Booking_Log_Model::log_status_change(
						(int) $row->id,
						$row->status,
						$new_status,
						$changed_by,
						null
					);
				}
			}
		}

		return $updated_count;
	}

	/**
	 * Get booking by ID.
	 *
	 * @param int $id Booking ID.
	 * @return object|null
	 */
	public function get( $id ) {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT * FROM {$this->table} WHERE id = %d",
			(int) $id
		);

		return $wpdb->get_row( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Get bookings with optional filters for dashboard/list.
	 *
	 * @param array $args Args.
	 * @return array
	 */
	public function get_bookings( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'        => '',
			'date'          => '',  // legacy, single date.
			'date_from'     => '',
			'date_to'       => '',
			'search'        => '',
			'coupon'        => '',  // all | with | without.
			'coupon_code'   => '',
			'payment_proof' => '',  // all | with | without.
			'studio_id'     => 0,
			'orderby'       => 'date',
			'order'         => 'DESC',
			'per_page'      => 20,
			'paged'         => 1,
			'user_id'       => 0,
			'member_id'     => 0,   // alias user_id untuk filter dari Members.
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = ' WHERE 1=1 ';
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where   .= ' AND status = %s ';
			$params[] = $args['status'];
		}

		// Legacy single date.
		if ( ! empty( $args['date'] ) ) {
			$where   .= ' AND date = %s ';
			$params[] = $args['date'];
		}

		// Date range.
		if ( ! empty( $args['date_from'] ) ) {
			$where   .= ' AND date >= %s ';
			$params[] = $args['date_from'];
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where   .= ' AND date <= %s ';
			$params[] = $args['date_to'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where   .= ' AND ( user_name LIKE %s OR email LIKE %s ) ';
			$params[] = $like;
			$params[] = $like;
		}

		// user_id / member_id filter.
		$filter_user_id = 0;
		if ( ! empty( $args['member_id'] ) ) {
			$filter_user_id = (int) $args['member_id'];
		} elseif ( ! empty( $args['user_id'] ) ) {
			$filter_user_id = (int) $args['user_id'];
		}

		if ( $filter_user_id ) {
			$where   .= ' AND user_id = %d ';
			$params[] = $filter_user_id;
		}

		if ( ! empty( $args['studio_id'] ) ) {
			$where   .= ' AND studio_id = %d ';
			$params[] = (int) $args['studio_id'];
		}

		// Coupon usage filter.
		if ( 'with' === $args['coupon'] ) {
			$where .= " AND coupon_code IS NOT NULL AND coupon_code != '' ";
		} elseif ( 'without' === $args['coupon'] ) {
			$where .= " AND ( coupon_code IS NULL OR coupon_code = '' ) ";
		}

		// Specific coupon code.
		if ( ! empty( $args['coupon_code'] ) ) {
			$where   .= ' AND coupon_code = %s ';
			$params[] = $args['coupon_code'];
		}

		// Payment proof filter.
		if ( 'with' === $args['payment_proof'] ) {
			$where .= " AND payment_proof IS NOT NULL AND payment_proof != '' ";
		} elseif ( 'without' === $args['payment_proof'] ) {
			$where .= " AND ( payment_proof IS NULL OR payment_proof = '' ) ";
		}

		$allowed_orderby = array(
			'date'        => 'date',
			'created_at'  => 'created_at',
			'final_price' => 'final_price',
			'status'      => 'status',
			'id'          => 'id',
		);

		$orderby = isset( $allowed_orderby[ $args['orderby'] ] ) ? $allowed_orderby[ $args['orderby'] ] : 'date';
		$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$order_by_sql = " ORDER BY {$orderby} {$order}, id DESC ";

		$offset  = ( max( 1, (int) $args['paged'] ) - 1 ) * (int) $args['per_page'];
		$limit   = (int) $args['per_page'];
		$limit_q = " LIMIT {$offset}, {$limit} ";

		$sql = "SELECT * FROM {$this->table} {$where} {$order_by_sql} {$limit_q}";

		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Helper: get all bookings by user_id (untuk riwayat booking user).
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_by_user_id( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return array();
		}

		return $this->get_bookings(
			array(
				'user_id'  => $user_id,
				'per_page' => 9999,
				'paged'    => 1,
				'orderby'  => 'date',
				'order'    => 'DESC',
			)
		);
	}

	/**
	 * Count bookings by status.
	 *
	 * @param string $status Status filter.
	 * @return int
	 */
	public function count_by_status( $status = '' ) {
		global $wpdb;

		if ( $status ) {
			$sql = $wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE status = %s",
				$status
			);
		} else {
			$sql = "SELECT COUNT(*) FROM {$this->table}";
		}

		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Get monthly revenue for dashboard.
	 *
	 * @param string $month Y-m format.
	 * @return float
	 */
	public function get_monthly_revenue( $month ) {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT SUM(final_price) FROM {$this->table}
			 WHERE status = 'paid'
			 AND DATE_FORMAT(date, '%%Y-%%m') = %s",
			$month
		);

		$sum = $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return (float) $sum;
	}

	/**
	 * Get events for admin FullCalendar (month).
	 *
	 * @param string $start Start date Y-m-d.
	 * @param string $end   End date Y-m-d.
	 * @return array
	 */
	public function get_events_for_calendar( $start, $end ) {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT * FROM {$this->table}
			 WHERE date >= %s AND date <= %s",
			$start,
			$end
		);

		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$events = array();

		foreach ( $rows as $row ) {
			$events[] = array(
				'id'       => (int) $row->id,
				'title'    => $row->user_name . ' (' . esc_html( $row->status ) . ')',
				'start'    => $row->date . 'T' . $row->start_time,
				'end'      => $row->date . 'T' . $row->end_time,
				'status'   => $row->status,
				'customer' => $row->user_name,
			);
		}

		return $events;
	}

	/**
	 * Check overlapping booking PER STUDIO.
	 *
	 * Hanya menghitung booking aktif (pending_payment / paid),
	 * sehingga booking yang sudah cancelled tidak mengunci slot lagi.
	 *
	 * @param string   $date       Date Y-m-d.
	 * @param string   $start_time Time H:i:s.
	 * @param string   $end_time   Time H:i:s.
	 * @param int|null $studio_id  Studio ID (wajib untuk multi studio).
	 * @param int|null $exclude_id Booking ID to exclude (saat reschedule).
	 * @return bool True jika ada overlapping booking.
	 */
	public function has_overlap( $date, $start_time, $end_time, $studio_id = null, $exclude_id = null ) {
		global $wpdb;

		$sql = "SELECT COUNT(*) FROM {$this->table}
				WHERE date = %s
				  AND status IN ('pending_payment','paid')
				  AND NOT ( end_time <= %s OR start_time >= %s)";

		$params = array( $date, $start_time, $end_time );

		if ( ! empty( $studio_id ) ) {
			$sql     .= ' AND studio_id = %d';
			$params[] = (int) $studio_id;
		}

		if ( ! empty( $exclude_id ) ) {
			$sql     .= ' AND id != %d';
			$params[] = (int) $exclude_id;
		}

		$sql   = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = (int) $wpdb->get_var( $sql );    // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return $count > 0;
	}

	/**
	 * Count bookings by user_id.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function count_by_user( $user_id ) {
		global $wpdb;

		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return 0;
		}

		$sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table} WHERE user_id = %d",
			$user_id
		);

		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Count active bookings by user_id (status pending_payment/paid dan tanggal >= hari ini).
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function count_active_by_user( $user_id ) {
		global $wpdb;

		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return 0;
		}

		$today = gmdate(
			'Y-m-d',
			current_time( 'timestamp' ) + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS )
		);

		$sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table}
			 WHERE user_id = %d
			   AND date >= %s
			   AND status IN ('pending_payment','paid')",
			$user_id,
			$today
		);

		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Count active bookings with status pending_payment (tanggal >= hari ini).
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function count_pending_active_by_user( $user_id ) {
		global $wpdb;

		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return 0;
		}

		$today = gmdate(
			'Y-m-d',
			current_time( 'timestamp' ) + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS )
		);

		$sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table}
			 WHERE user_id = %d
			   AND date >= %s
			   AND status = 'pending_payment'",
			$user_id,
			$today
		);

		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Stats untuk filter aktif (dipakai di bookings list admin).
	 *
	 * @param array $args Filter args (sama seperti get_bookings()).
	 * @return array {
	 *   @type int   $total
	 *   @type int   $pending
	 *   @type int   $paid
	 *   @type int   $cancelled
	 *   @type float $total_revenue
	 * }
	 */
	public function get_stats_for_filters( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'        => '',
			'date'          => '',
			'date_from'     => '',
			'date_to'       => '',
			'search'        => '',
			'coupon'        => '',
			'coupon_code'   => '',
			'payment_proof' => '',
			'studio_id'     => 0,
			'user_id'       => 0,
			'member_id'     => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = ' WHERE 1=1 ';
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where   .= ' AND status = %s ';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['date'] ) ) {
			$where   .= ' AND date = %s ';
			$params[] = $args['date'];
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where   .= ' AND date >= %s ';
			$params[] = $args['date_from'];
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where   .= ' AND date <= %s ';
			$params[] = $args['date_to'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where   .= ' AND ( user_name LIKE %s OR email LIKE %s ) ';
			$params[] = $like;
			$params[] = $like;
		}

		$filter_user_id = 0;
		if ( ! empty( $args['member_id'] ) ) {
			$filter_user_id = (int) $args['member_id'];
		} elseif ( ! empty( $args['user_id'] ) ) {
			$filter_user_id = (int) $args['user_id'];
		}

		if ( $filter_user_id ) {
			$where   .= ' AND user_id = %d ';
			$params[] = $filter_user_id;
		}

		if ( ! empty( $args['studio_id'] ) ) {
			$where   .= ' AND studio_id = %d ';
			$params[] = (int) $args['studio_id'];
		}

		if ( 'with' === $args['coupon'] ) {
			$where .= " AND coupon_code IS NOT NULL AND coupon_code != '' ";
		} elseif ( 'without' === $args['coupon'] ) {
			$where .= " AND ( coupon_code IS NULL OR coupon_code = '' ) ";
		}

		if ( ! empty( $args['coupon_code'] ) ) {
			$where   .= ' AND coupon_code = %s ';
			$params[] = $args['coupon_code'];
		}

		if ( 'with' === $args['payment_proof'] ) {
			$where .= " AND payment_proof IS NOT NULL AND payment_proof != '' ";
		} elseif ( 'without' === $args['payment_proof'] ) {
			$where .= " AND ( payment_proof IS NULL OR payment_proof = '' ) ";
		}

		$sql_base = "FROM {$this->table} {$where}";

		$sql_total     = "SELECT COUNT(*) {$sql_base}";
		$sql_pending   = "SELECT COUNT(*) {$sql_base} AND status = 'pending_payment'";
		$sql_paid      = "SELECT COUNT(*) {$sql_base} AND status = 'paid'";
		$sql_cancelled = "SELECT COUNT(*) {$sql_base} AND status = 'cancelled'";
		$sql_revenue   = "SELECT SUM(final_price) {$sql_base} AND status = 'paid'";

		if ( ! empty( $params ) ) {
			$sql_total     = $wpdb->prepare( $sql_total, $params );
			$sql_pending   = $wpdb->prepare( $sql_pending, $params );
			$sql_paid      = $wpdb->prepare( $sql_paid, $params );
			$sql_cancelled = $wpdb->prepare( $sql_cancelled, $params );
			$sql_revenue   = $wpdb->prepare( $sql_revenue, $params );
		}

		$total     = (int) $wpdb->get_var( $sql_total );     // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$pending   = (int) $wpdb->get_var( $sql_pending );   // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$paid      = (int) $wpdb->get_var( $sql_paid );      // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$cancelled = (int) $wpdb->get_var( $sql_cancelled ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$revenue   = (float) $wpdb->get_var( $sql_revenue ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return array(
			'total'         => $total,
			'pending'       => $pending,
			'paid'          => $paid,
			'cancelled'     => $cancelled,
			'total_revenue' => $revenue,
		);
	}

	/**
	 * Aggregated stats per member.
	 *
	 * @param array $args .
	 * @return array
	 */
	public function get_member_stats( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'user_ids'  => array(),
			'date_from' => '',
			'date_to'   => '',
			'status'    => 'paid',
		);
		$args = wp_parse_args( $args, $defaults );

		$where   = array( 'user_id IS NOT NULL' );
		$prepare = array();

		if ( ! empty( $args['user_ids'] ) && is_array( $args['user_ids'] ) ) {
			$user_ids = array_map( 'intval', $args['user_ids'] );
			$user_ids = array_filter( $user_ids );
			if ( $user_ids ) {
				$placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );
				$where[]      = "user_id IN ( {$placeholders} )";
				foreach ( $user_ids as $uid ) {
					$prepare[] = $uid;
				}
			}
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where[]   = 'date >= %s';
			$prepare[] = $args['date_from'];
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[]   = 'date <= %s';
			$prepare[] = $args['date_to'];
		}

		if ( ! empty( $args['status'] ) && 'all' !== $args['status'] ) {
			$where[]   = 'status = %s';
			$prepare[] = $args['status'];
		}

		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$sql = "
			SELECT
				user_id,
				COUNT(*)         AS booking_count,
				MAX(date)        AS last_booking_date,
				SUM(final_price) AS total_revenue
			FROM {$this->table}
			{$where_sql}
			GROUP BY user_id
		";

		if ( $prepare ) {
			$sql = $wpdb->prepare( $sql, $prepare );
		}

		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$results = array();
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$results[ (int) $row->user_id ] = (object) array(
					'booking_count'     => (int) $row->booking_count,
					'last_booking_date' => $row->last_booking_date,
					'total_revenue'     => (float) $row->total_revenue,
				);
			}
		}

		return $results;
	}

	/**
	 * Quick stats untuk range tertentu (today / this_week / this_month).
	 *
	 * @param string $range      Range key.
	 * @param array  $extra_args Extra filter args (optional).
	 * @return array
	 */
	public function get_stats_for_range( $range, $extra_args = array() ) {
		$range = (string) $range;

		$now   = current_time( 'timestamp' );
		$today = gmdate( 'Y-m-d', $now + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
		$from  = $today;
		$to    = $today;

		if ( 'this_week' === $range ) {
			$week_start_ts = strtotime( 'monday this week', $now );
			$week_end_ts   = strtotime( 'sunday this week', $now );
			$from          = gmdate( 'Y-m-d', $week_start_ts + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
			$to            = gmdate( 'Y-m-d', $week_end_ts + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
		} elseif ( 'this_month' === $range ) {
			$y    = (int) gmdate( 'Y', $now + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
			$m    = (int) gmdate( 'm', $now + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
			$from = sprintf( '%04d-%02d-01', $y, $m );
			$to   = gmdate(
				'Y-m-t',
				mktime( 0, 0, 0, $m, 1, $y ) + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS )
			);
		}

		$args = array(
			'date_from' => $from,
			'date_to'   => $to,
		);

		if ( ! empty( $extra_args ) && is_array( $extra_args ) ) {
			$args = array_merge( $args, $extra_args );
		}

		return $this->get_stats_for_filters( $args );
	}

	/**
	 * Ambil upcoming bookings untuk dashboard widget.
	 *
	 * @param array $args {
	 *   @type int      $days_ahead Range hari ke depan.
	 *   @type int      $limit      Jumlah booking max.
	 *   @type string[] $statuses   Status yang di-include.
	 * }
	 * @return array
	 */
	public function get_upcoming_bookings( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'days_ahead' => 7,
			'limit'      => 10,
			'statuses'   => array( 'pending_payment', 'paid' ),
		);
		$args = wp_parse_args( $args, $defaults );

		$days_ahead = max( 1, (int) $args['days_ahead'] );
		$limit      = max( 1, (int) $args['limit'] );
		$statuses   = (array) $args['statuses'];

		$now   = current_time( 'timestamp' );
		$today = gmdate( 'Y-m-d', $now + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
		$end   = gmdate(
			'Y-m-d',
			$now + $days_ahead * DAY_IN_SECONDS + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS )
		);

		$where  = 'WHERE date >= %s AND date <= %s';
		$params = array( $today, $end );

		if ( ! empty( $statuses ) ) {
			$statuses     = array_map( 'sanitize_text_field', $statuses );
			$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
			$where       .= " AND status IN ( {$placeholders} )";
			$params       = array_merge( $params, $statuses );
		}

		$sql = "
			SELECT *
			FROM {$this->table}
			{$where}
			ORDER BY date ASC, start_time ASC
			LIMIT %d
		";

		$params[] = $limit;

		$sql  = $wpdb->prepare( $sql, $params );
		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Ambil tanggal-tanggal sibuk (busy) untuk kalender admin.
	 *
	 * @param string $start     Start date Y-m-d.
	 * @param string $end       End date Y-m-d.
	 * @param int    $threshold Minimal jumlah booking untuk dianggap sibuk.
	 * @return array [ 'Y-m-d' => count ]
	 */
	public function get_busy_dates( $start, $end, $threshold = 3 ) {
		global $wpdb;

		$threshold = max( 1, (int) $threshold );

		$sql = $wpdb->prepare(
			"SELECT date, COUNT(*) AS cnt
			 FROM {$this->table}
			 WHERE date >= %s
			   AND date <= %s
			   AND status IN ('pending_payment','paid')
			 GROUP BY date
			 HAVING cnt >= %d",
			$start,
			$end,
			$threshold
		);

		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$results = array();
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$results[ $row->date ] = (int) $row->cnt;
			}
		}

		return $results;
	}

	/**
	 * Ambil booking pending_payment yang sudah melewati batas jam untuk auto-cancel.
	 *
	 * @param int $hours Batas jam (auto_cancel_unpaid_hours).
	 * @return array
	 */
	public function get_unpaid_bookings_for_auto_cancel( $hours ) {
		global $wpdb;

		$hours = (int) $hours;
		if ( $hours <= 0 ) {
			return array();
		}

		$now_ts     = current_time( 'timestamp' );
		$cutoff_ts  = $now_ts - ( $hours * HOUR_IN_SECONDS );
		$cutoff_str = gmdate( 'Y-m-d H:i:s', $cutoff_ts + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );

		$sql = $wpdb->prepare(
			"SELECT *
			 FROM {$this->table}
			 WHERE status = 'pending_payment'
			   AND created_at <= %s",
			$cutoff_str
		);

		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Cek apakah slot booking memenuhi minimal lead time.
	 *
	 * @param string $date        Tanggal booking (Y-m-d).
	 * @param string $start_time  Jam mulai (H:i:s).
	 * @param int    $lead_hours  Minimal lead time dalam jam.
	 * @return bool
	 */
	public function is_slot_respect_lead_time( $date, $start_time, $lead_hours ) {
		$lead_hours = (int) $lead_hours;
		if ( $lead_hours <= 0 ) {
			return true;
		}

		$now_ts = current_time( 'timestamp' );

		$datetime_str = trim( $date . ' ' . $start_time );
		$booking_ts   = strtotime( $datetime_str );

		if ( ! $booking_ts ) {
			return false;
		}

		$diff_hours = ( $booking_ts - $now_ts ) / HOUR_IN_SECONDS;

		return $diff_hours >= $lead_hours;
	}

	/* ======================================================
	 *  RESCHEDULE HELPERS
	 * ===================================================== */

	public function get_booking_start_timestamp( $booking ) {
		if ( ! $booking || empty( $booking->date ) || empty( $booking->start_time ) ) {
			return false;
		}

		$datetime_str = trim( $booking->date . ' ' . $booking->start_time );
		$ts           = strtotime( $datetime_str );

		return $ts ? $ts : false;
	}

	public function can_user_reschedule_booking( $booking, $user_id = 0, $settings = array() ) {
		$user_id = (int) $user_id;

		if ( ! $booking ) {
			return new WP_Error( 'tsrb_reschedule_not_found', __( 'Booking not found.', 'tribuna-studio-rent-booking' ) );
		}

		if ( ! in_array( $booking->status, array( 'pending_payment', 'paid' ), true ) ) {
			return new WP_Error( 'tsrb_reschedule_invalid_status', __( 'This booking cannot be rescheduled in its current status.', 'tribuna-studio-rent-booking' ) );
		}

		// Jika $settings kosong, ambil dari helper (satu sumber utama).
		if ( empty( $settings ) || ! is_array( $settings ) ) {
			if ( class_exists( 'Tribuna_Helpers' ) && method_exists( 'Tribuna_Helpers', 'get_settings' ) ) {
				$settings = Tribuna_Helpers::get_settings();
			} else {
				$settings = array();
			}
		}

		$workflow = isset( $settings['workflow'] ) && is_array( $settings['workflow'] ) ? $settings['workflow'] : array();

		$allow_member_reschedule  = ! empty( $workflow['allow_member_reschedule'] ) ? 1 : 0;
		$reschedule_cutoff_hours  = isset( $workflow['reschedule_cutoff_hours'] ) ? (int) $workflow['reschedule_cutoff_hours'] : 0;
		$reschedule_allow_pending = ! empty( $workflow['reschedule_allow_pending'] ) ? 1 : 0;
		$reschedule_admin_only    = ! empty( $workflow['reschedule_admin_only'] ) ? 1 : 0;

		if ( $reschedule_admin_only && $user_id > 0 ) {
			return new WP_Error( 'tsrb_reschedule_admin_only', __( 'Reschedule can only be done by admin.', 'tribuna-studio-rent-booking' ) );
		}

		if ( $user_id > 0 ) {
			if ( ! $allow_member_reschedule ) {
				return new WP_Error( 'tsrb_reschedule_disabled', __( 'Reschedule is not allowed for members.', 'tribuna-studio-rent-booking' ) );
			}

			if ( (int) $booking->user_id !== $user_id ) {
				return new WP_Error( 'tsrb_reschedule_not_owner', __( 'You are not allowed to reschedule this booking.', 'tribuna-studio-rent-booking' ) );
			}
		}

		if ( 'pending_payment' === $booking->status && ! $reschedule_allow_pending && $user_id > 0 ) {
			return new WP_Error( 'tsrb_reschedule_pending_not_allowed', __( 'You cannot reschedule a booking that is still pending payment.', 'tribuna-studio-rent-booking' ) );
		}

		if ( $reschedule_cutoff_hours > 0 ) {
			$now_ts     = current_time( 'timestamp' );
			$booking_ts = $this->get_booking_start_timestamp( $booking );
			if ( ! $booking_ts ) {
				return new WP_Error( 'tsrb_reschedule_invalid_time', __( 'Cannot determine booking time for reschedule.', 'tribuna-studio-rent-booking' ) );
			}

			$diff_hours = ( $booking_ts - $now_ts ) / HOUR_IN_SECONDS;

			if ( $diff_hours < $reschedule_cutoff_hours ) {
				return new WP_Error( 'tsrb_reschedule_cutoff', __( 'This booking is too close to the start time to be rescheduled.', 'tribuna-studio-rent-booking' ) );
			}
		}

		return true;
	}

	public function reschedule_booking( $id, $new_date, $new_start_time, $new_end_time, $changed_by = 0, $settings = array() ) {
		$id         = (int) $id;
		$changed_by = (int) $changed_by;

		$booking = $this->get( $id );
		if ( ! $booking ) {
			return new WP_Error( 'tsrb_reschedule_not_found', __( 'Booking not found.', 'tribuna-studio-rent-booking' ) );
		}

		// Jika $settings kosong, ambil dari helper (satu sumber utama).
		if ( empty( $settings ) || ! is_array( $settings ) ) {
			if ( class_exists( 'Tribuna_Helpers' ) && method_exists( 'Tribuna_Helpers', 'get_settings' ) ) {
				$settings = Tribuna_Helpers::get_settings();
			} else {
				$settings = array();
			}
		}

		$workflow = isset( $settings['workflow'] ) && is_array( $settings['workflow'] ) ? $settings['workflow'] : array();

		$min_lead_time_hours = isset( $workflow['min_lead_time_hours'] ) ? (int) $workflow['min_lead_time_hours'] : 0;

		if ( ! $this->is_slot_respect_lead_time( $new_date, $new_start_time, $min_lead_time_hours ) ) {
			return new WP_Error( 'tsrb_reschedule_lead_time', __( 'The new schedule does not respect the minimal lead time.', 'tribuna-studio-rent-booking' ) );
		}

		// Cek bentrok hanya terhadap booking di studio yang sama.
		$studio_id = ! empty( $booking->studio_id ) ? (int) $booking->studio_id : null;

		if ( $this->has_overlap( $new_date, $new_start_time, $new_end_time, $studio_id, $id ) ) {
			return new WP_Error( 'tsrb_reschedule_overlap', __( 'The new schedule overlaps with another booking.', 'tribuna-studio-rent-booking' ) );
		}

		$duration = 1;
		$start_ts = strtotime( $new_date . ' ' . $new_start_time );
		$end_ts   = strtotime( $new_date . ' ' . $new_end_time );
		if ( $start_ts && $end_ts && $end_ts > $start_ts ) {
			$duration = (int) round( ( $end_ts - $start_ts ) / HOUR_IN_SECONDS );
			if ( $duration <= 0 ) {
				$duration = 1;
			}
		}

		$old_date       = $booking->date;
		$old_start_time = $booking->start_time;
		$old_end_time   = $booking->end_time;

		$updated = $this->update(
			$id,
			array(
				'date'       => $new_date,
				'start_time' => $new_start_time,
				'end_time'   => $new_end_time,
				'duration'   => $duration,
			)
		);

		if ( ! $updated ) {
			return new WP_Error( 'tsrb_reschedule_update_failed', __( 'Failed to update booking schedule.', 'tribuna-studio-rent-booking' ) );
		}

		$updated_booking = $this->get( $id );

		// Log activity untuk reschedule.
		if ( class_exists( 'Tribuna_Booking_Log_Model' ) ) {
			$note = sprintf(
				/* translators: 1: old datetime, 2: new datetime */
				__( 'Booking rescheduled from %1$s %2$s-%3$s to %4$s %5$s-%6$s.', 'tribuna-studio-rent-booking' ),
				$old_date,
				$old_start_time,
				$old_end_time,
				$new_date,
				$new_start_time,
				$new_end_time
			);

			Tribuna_Booking_Log_Model::log_status_change(
				$id,
				$booking->status,
				$booking->status,
				$changed_by,
				$note
			);
		}

		do_action(
			'tsrb_booking_rescheduled',
			(int) $id,
			$booking,
			$updated_booking,
			(int) $changed_by
		);

		return true;
	}

	/* ======================================================
	 *  WIDGET STATS
	 * ===================================================== */

	public function get_widget_overview_stats() {
		global $wpdb;

		$now_ts      = current_time( 'timestamp' );
		$offset_secs = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;

		$today = gmdate( 'Y-m-d', $now_ts + $offset_secs );
		$next7 = gmdate( 'Y-m-d', $now_ts + ( 7 * DAY_IN_SECONDS ) + $offset_secs );
		$prev7 = gmdate( 'Y-m-d', $now_ts - ( 7 * DAY_IN_SECONDS ) + $offset_secs );

		$sql_upcoming = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table}
			 WHERE date >= %s
			   AND date <= %s
			   AND status IN ('pending_payment','paid')",
			$today,
			$next7
		);

		$sql_today = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table}
			 WHERE date = %s
			   AND status IN ('pending_payment','paid')",
			$today
		);

		$sql_pending = "SELECT COUNT(*) FROM {$this->table} WHERE status = 'pending_payment'";

		$sql_cancelled = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table}
			 WHERE date >= %s
			   AND date <= %s
			   AND status IN ('cancelled','expired')",
			$prev7,
			$today
		);

		$upcoming_7 = (int) $wpdb->get_var( $sql_upcoming );   // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$today_cnt  = (int) $wpdb->get_var( $sql_today );      // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$pending    = (int) $wpdb->get_var( $sql_pending );    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$cancelled7 = (int) $wpdb->get_var( $sql_cancelled );  // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return array(
			'upcoming_7_days'  => $upcoming_7,
			'today'            => $today_cnt,
			'pending_payment'  => $pending,
			'cancelled_7_days' => $cancelled7,
		);
	}
}
