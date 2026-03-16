<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Model untuk log perubahan booking (activity log).
 *
 * Tabel: {$wpdb->prefix}studio_booking_logs
 *
 * Kolom:
 * - id BIGINT(20) UNSIGNED PK AI
 * - booking_id BIGINT(20) UNSIGNED
 * - old_status VARCHAR(20) NULL
 * - new_status VARCHAR(20) NOT NULL
 * - changed_by BIGINT(20) UNSIGNED (user ID admin/member/system)
 * - note TEXT NULL
 * - created_at DATETIME NOT NULL
 */
class Tribuna_Booking_Log_Model {

	/**
	 * Nama tabel (dengan prefix).
	 *
	 * @var string
	 */
	protected $table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		// Harus selaras dengan Tribuna_Database::create_tables().
		$this->table = $wpdb->prefix . 'studio_booking_logs';
	}

	/**
	 * Buat satu baris log.
	 *
	 * @param array $data {
	 *   @type int    $booking_id          (wajib) ID booking.
	 *   @type string $old_status          (opsional) Status lama.
	 *   @type string $new_status          (wajib) Status baru (boleh sama dengan lama jika hanya catatan).
	 *   @type string $note                (opsional) Catatan tambahan.
	 *   @type int    $changed_by          (opsional) User ID yang mengubah (admin/member/0=system).
	 *   @type string $changed_by_display  (opsional) Diabaikan, hanya untuk kompatibilitas.
	 * }
	 *
	 * @return int|false Insert ID atau false jika gagal / input tidak valid.
	 */
	public function create( $data ) {
		global $wpdb;

		$booking_id = isset( $data['booking_id'] ) ? (int) $data['booking_id'] : 0;
		$old_status = array_key_exists( 'old_status', $data ) ? sanitize_text_field( (string) $data['old_status'] ) : null;
		$new_status = isset( $data['new_status'] ) ? sanitize_text_field( (string) $data['new_status'] ) : '';
		$note       = isset( $data['note'] ) ? wp_kses_post( (string) $data['note'] ) : '';
		$changed_by = isset( $data['changed_by'] ) ? (int) $data['changed_by'] : 0;

		// Minimal harus ada booking_id dan new_status.
		if ( $booking_id <= 0 || '' === $new_status ) {
			return false;
		}

		// Safety: pastikan tabel benar-benar ada sebelum insert,
		// supaya kalau site belum sempat menjalankan dbDelta tidak memicu fatal error.
		if ( empty( $this->table ) || ! $this->table_exists() ) {
			return false;
		}

		$inserted = $wpdb->insert(
			$this->table,
			array(
				'booking_id' => $booking_id,
				'old_status' => $old_status,
				'new_status' => $new_status,
				'changed_by' => $changed_by,
				'note'       => $note,
				'created_at' => current_time( 'mysql' ),
			),
			array(
				'%d',
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Ambil semua log untuk 1 booking, urut terbaru dulu.
	 *
	 * @param int $booking_id Booking ID.
	 *
	 * @return array Array of row objects.
	 */
	public function get_by_booking_id( $booking_id ) {
		global $wpdb;

		$booking_id = (int) $booking_id;
		if ( $booking_id <= 0 ) {
			return array();
		}

		if ( empty( $this->table ) || ! $this->table_exists() ) {
			return array();
		}

		$sql = $wpdb->prepare(
			"SELECT *
			 FROM {$this->table}
			 WHERE booking_id = %d
			 ORDER BY created_at DESC, id DESC",
			$booking_id
		);

		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// Tambahkan properti virtual changed_by_display saat dibaca,
		// supaya view bisa menampilkan nama user tanpa perlu join.
		if ( ! empty( $rows ) && is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$display = '';
				if ( ! empty( $row->changed_by ) ) {
					$user = get_user_by( 'id', (int) $row->changed_by );
					if ( $user ) {
						$display = $user->display_name;
					}
				}
				$row->changed_by_display = $display;
			}
		}

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Helper statis untuk mencatat perubahan status secara aman.
	 *
	 * Dipakai sebagai fallback dari hook atau dari kode lain
	 * yang tidak punya instance service.
	 *
	 * @param int         $booking_id Booking ID.
	 * @param string      $old_status Status lama.
	 * @param string      $new_status Status baru.
	 * @param int         $changed_by User ID yang mengubah.
	 * @param string|null $note       Catatan tambahan (opsional).
	 */
	public static function log_status_change( $booking_id, $old_status, $new_status, $changed_by = 0, $note = null ) {
		$booking_id = (int) $booking_id;
		if ( $booking_id <= 0 ) {
			return;
		}

		$model = new self();

		$model->create(
			array(
				'booking_id' => $booking_id,
				'old_status' => $old_status,
				'new_status' => $new_status,
				'changed_by' => (int) $changed_by,
				'note'       => null !== $note ? $note : '',
			)
		);
	}

	/**
	 * Cek apakah tabel log sudah ada di database.
	 *
	 * @return bool
	 */
	protected function table_exists() {
		global $wpdb;

		if ( empty( $this->table ) ) {
			return false;
		}

		// Gunakan cache sederhana supaya tidak query berkali-kali per request.
		static $cache = array();

		if ( array_key_exists( $this->table, $cache ) ) {
			return $cache[ $this->table ];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$table_name = $wpdb->esc_like( $this->table );
		$exists     = ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $this->table );

		$cache[ $this->table ] = (bool) $exists;

		return $cache[ $this->table ];
	}
}
