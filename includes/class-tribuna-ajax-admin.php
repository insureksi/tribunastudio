<?php
/**
 * AJAX handlers khusus Admin area.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tribuna_Ajax_Admin {

	/**
	 * @var Tribuna_Booking_Service
	 */
	protected $booking_service;

	/**
	 * @var Tribuna_Booking_Model
	 */
	protected $booking_model;

	/**
	 * @var Tribuna_Studio_Model
	 */
	protected $studio_model;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->booking_service = new Tribuna_Booking_Service();
		$this->booking_model   = new Tribuna_Booking_Model();
		$this->studio_model    = new Tribuna_Studio_Model();
	}

	/**
	 * Events untuk FullCalendar di Dashboard (termasuk busy dates highlight).
	 *
	 * Action: wp_ajax_tsrb_get_admin_calendar_events
	 */
	public function get_admin_calendar_events() {
		check_ajax_referer( 'tsrb_admin_nonce', 'nonce' );

		if ( ! current_user_can( Tribuna_Helpers::admin_capability() ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Unauthorized', 'tribuna-studio-rent-booking' ) ),
				403
			);
		}

		$start = isset( $_GET['start'] ) ? sanitize_text_field( wp_unslash( $_GET['start'] ) ) : '';
		$end   = isset( $_GET['end'] ) ? sanitize_text_field( wp_unslash( $_GET['end'] ) ) : '';

		if ( empty( $start ) || empty( $end ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid date range.', 'tribuna-studio-rent-booking' ) ),
				400
			);
		}

		if ( ! method_exists( $this->booking_service, 'get_admin_calendar_events' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Calendar service not available.', 'tribuna-studio-rent-booking' ) ),
				500
			);
		}

		$events = $this->booking_service->get_admin_calendar_events( $start, $end );

		wp_send_json_success( $events );
	}

	/**
	 * Endpoint admin untuk ambil availability slots per tanggal/studio.
	 *
	 * Action: wp_ajax_tsrb_get_admin_availability
	 */
	public function get_admin_availability() {
		check_ajax_referer( 'tsrb_admin_nonce', 'nonce' );

		if ( ! current_user_can( Tribuna_Helpers::booking_capability() ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Unauthorized', 'tribuna-studio-rent-booking' ),
				),
				403
			);
		}

		$date      = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : '';
		$studio_id = isset( $_GET['studio_id'] ) ? (int) $_GET['studio_id'] : 0;

		if ( empty( $date ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid date.', 'tribuna-studio-rent-booking' ),
				),
				400
			);
		}

		if ( ! method_exists( $this->booking_service, 'get_availability_for_date' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Availability service not available.', 'tribuna-studio-rent-booking' ),
				),
				500
			);
		}

		$result = $this->booking_service->get_availability_for_date( $date, $studio_id );

		if ( ! is_array( $result ) ) {
			$result = array(
				'status'       => 'closed',
				'slots'        => array(),
				'total_slots'  => 0,
				'booked_slots' => 0,
			);
		}

		wp_send_json_success(
			array(
				'date'        => $date,
				'status'      => isset( $result['status'] ) ? $result['status'] : 'closed',
				'slots'       => isset( $result['slots'] ) ? $result['slots'] : array(),
				'totalslots'  => isset( $result['total_slots'] ) ? (int) $result['total_slots'] : 0,
				'bookedslots' => isset( $result['booked_slots'] ) ? (int) $result['booked_slots'] : 0,
			)
		);
	}

	/**
	 * Update status booking dari halaman admin (inline).
	 *
	 * Action: wp_ajax_tsrb_update_booking_status
	 */
	public function update_booking_status() {
		check_ajax_referer( 'tsrb_admin_nonce', 'nonce' );

		if ( ! current_user_can( Tribuna_Helpers::booking_capability() ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Unauthorized', 'tribuna-studio-rent-booking' ) ),
				403
			);
		}

		$booking_id = isset( $_POST['booking_id'] ) ? (int) $_POST['booking_id'] : 0;
		$status     = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
		$admin_note = isset( $_POST['admin_note'] ) ? wp_kses_post( wp_unslash( $_POST['admin_note'] ) ) : '';

		// Inline hanya untuk status dasar; workflow cancel pakai endpoint khusus di bawah.
		if ( ! $booking_id || ! in_array( $status, array( 'pending_payment', 'paid', 'cancelled' ), true ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid data.', 'tribuna-studio-rent-booking' ) ),
				400
			);
		}

		if ( ! method_exists( $this->booking_service, 'update_status_with_email' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Update service not available.', 'tribuna-studio-rent-booking' ) ),
				500
			);
		}

		$updated = $this->booking_service->update_status_with_email(
			$booking_id,
			$status,
			$admin_note,
			get_current_user_id()
		);

		if ( ! $updated ) {
			wp_send_json_error(
				array( 'message' => __( 'Failed to update booking.', 'tribuna-studio-rent-booking' ) ),
				500
			);
		}

		wp_send_json_success(
			array( 'message' => __( 'Booking updated.', 'tribuna-studio-rent-booking' ) )
		);
	}

	/**
	 * Reschedule booking dari halaman admin.
	 *
	 * Action: wp_ajax_tsrb_admin_reschedule_booking
	 */
	public function admin_reschedule_booking() {
		check_ajax_referer( 'tsrb_admin_nonce', 'nonce' );

		if ( ! current_user_can( Tribuna_Helpers::booking_capability() ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Unauthorized', 'tribuna-studio-rent-booking' ),
				),
				403
			);
		}

		$booking_id = isset( $_POST['booking_id'] ) ? (int) $_POST['booking_id'] : 0;
		$new_date   = isset( $_POST['new_date'] ) ? sanitize_text_field( wp_unslash( $_POST['new_date'] ) ) : '';
		$new_start  = isset( $_POST['new_start'] ) ? sanitize_text_field( wp_unslash( $_POST['new_start'] ) ) : '';
		$new_end    = isset( $_POST['new_end'] ) ? sanitize_text_field( wp_unslash( $_POST['new_end'] ) ) : '';

		if ( ! $booking_id || empty( $new_date ) || empty( $new_start ) || empty( $new_end ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Incomplete reschedule data.', 'tribuna-studio-rent-booking' ),
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

		$settings = get_option( 'tsrb_settings', array() );

		if ( method_exists( $this->booking_model, 'reschedule_booking' ) ) {
			$result = $this->booking_model->reschedule_booking(
				$booking_id,
				$new_date,
				$new_start . ':00',
				$new_end . ':00',
				get_current_user_id(),
				$settings
			);

			if ( is_wp_error( $result ) ) {
				wp_send_json_error(
					array(
						'message' => $result->get_error_message(),
						'code'    => $result->get_error_code(),
					),
					400
				);
			}
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Reschedule method is not implemented.', 'tribuna-studio-rent-booking' ),
				),
				500
			);
		}

		$updated_booking = $this->booking_model->get( $booking_id );

		wp_send_json_success(
			array(
				'message'    => __( 'Booking schedule updated successfully.', 'tribuna-studio-rent-booking' ),
				'booking_id' => $booking_id,
				'date'       => $updated_booking ? $updated_booking->date : $new_date,
				'start_time' => $updated_booking ? $updated_booking->start_time : ( $new_start . ':00' ),
				'end_time'   => $updated_booking ? $updated_booking->end_time : ( $new_end . ':00' ),
				'status'     => $updated_booking ? $updated_booking->status : $booking->status,
			)
		);
	}

	/**
	 * Admin: mark booking as Cancellation Requested.
	 *
	 * Action: wp_ajax_tsrb_admin_mark_cancel_requested
	 */
	public function admin_mark_cancel_requested() {
		check_ajax_referer( 'tsrb_admin_nonce', 'nonce' );

		if ( ! current_user_can( Tribuna_Helpers::booking_capability() ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Unauthorized', 'tribuna-studio-rent-booking' ) ),
				403
			);
		}

		$booking_id = isset( $_POST['booking_id'] ) ? (int) $_POST['booking_id'] : 0;
		$admin_note = isset( $_POST['admin_note'] ) ? wp_kses_post( wp_unslash( $_POST['admin_note'] ) ) : '';

		if ( ! $booking_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid booking ID.', 'tribuna-studio-rent-booking' ) ),
				400
			);
		}

		$booking = $this->booking_model->get( $booking_id );
		if ( ! $booking ) {
			wp_send_json_error(
				array( 'message' => __( 'Booking not found.', 'tribuna-studio-rent-booking' ) ),
				404
			);
		}

		// Gunakan service helper agar policy & log konsisten dengan alur member.
		if ( method_exists( $this->booking_service, 'handle_member_cancel_request' ) ) {
			$result = $this->booking_service->handle_member_cancel_request( $booking_id, (int) $booking->user_id );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error(
					array(
						'message' => $result->get_error_message(),
						'code'    => $result->get_error_code(),
					),
					400
				);
			}
		} else {
			// Fallback: update status langsung jika helper tidak ada.
			$ok = $this->booking_model->update(
				$booking_id,
				array(
					'status'        => 'cancel_requested',
					'cancel_reason' => __( 'Marked as cancellation requested by admin.', 'tribuna-studio-rent-booking' ),
				)
			);

			if ( ! $ok ) {
				wp_send_json_error(
					array( 'message' => __( 'Failed to process cancellation.', 'tribuna-studio-rent-booking' ) ),
					500
				);
			}
		}

		// Tambahkan catatan admin jika ada.
		if ( '' !== $admin_note ) {
			$this->booking_model->update(
				$booking_id,
				array(
					'admin_note' => $admin_note,
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Booking marked as cancellation requested.', 'tribuna-studio-rent-booking' ),
				'status'  => 'cancel_requested',
			)
		);
	}

	/**
	 * Admin: approve cancellation & apply refund (menggunakan service).
	 *
	 * Action: wp_ajax_tsrb_admin_approve_cancellation
	 */
	public function admin_approve_cancellation() {
		check_ajax_referer( 'tsrb_admin_nonce', 'nonce' );

		if ( ! current_user_can( Tribuna_Helpers::booking_capability() ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Unauthorized', 'tribuna-studio-rent-booking' ) ),
				403
			);
		}

		if ( ! method_exists( $this->booking_service, 'evaluate_cancellation_policy' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Cancellation policy evaluator is not available.', 'tribuna-studio-rent-booking' ) ),
				500
			);
		}

		$booking_id = isset( $_POST['booking_id'] ) ? (int) $_POST['booking_id'] : 0;
		$admin_note = isset( $_POST['admin_note'] ) ? wp_kses_post( wp_unslash( $_POST['admin_note'] ) ) : '';

		if ( ! $booking_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid booking ID.', 'tribuna-studio-rent-booking' ) ),
				400
			);
		}

		$booking = $this->booking_model->get( $booking_id );
		if ( ! $booking ) {
			wp_send_json_error(
				array( 'message' => __( 'Booking not found.', 'tribuna-studio-rent-booking' ) ),
				404
			);
		}

		// Hanya boleh approve jika status masih aktif / cancel_requested.
		if ( ! in_array( $booking->status, array( 'pending_payment', 'paid', 'cancel_requested' ), true ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'This booking cannot be processed for cancellation.', 'tribuna-studio-rent-booking' ),
					'code'    => 'invalid_status',
				),
				400
			);
		}

		// Evaluasi policy dengan waktu request = sekarang.
		$evaluation = $this->booking_service->evaluate_cancellation_policy( $booking );

		if ( empty( $evaluation['allowed'] ) ) {
			$message = ! empty( $evaluation['message'] )
				? $evaluation['message']
				: __( 'Cancellation is not allowed by policy.', 'tribuna-studio-rent-booking' );

			wp_send_json_error(
				array(
					'message' => $message,
					'code'    => 'cancellation_not_allowed',
				),
				400
			);
		}

		$current_user_id = get_current_user_id();

		// Gabungkan admin_note dengan ringkasan refund ke admin_note booking.
		if ( '' !== $admin_note ) {
			$note_full = $admin_note;
		} else {
			$note_full = '';
		}

		if ( isset( $evaluation['refund_type'] ) ) {
			$note_full .= "\n\n[Refund]\n";
			$note_full .= 'Type: ' . $evaluation['refund_type'] . "\n";
			if ( isset( $evaluation['refund_percent'] ) ) {
				$note_full .= 'Percent: ' . (int) $evaluation['refund_percent'] . "%\n";
			}
		}

		if ( '' !== $note_full ) {
			$this->booking_model->update(
				$booking_id,
				array(
					'admin_note' => $note_full,
				)
			);
		}

		// Terapkan hasil policy (status → cancelled, refund_amount & credit_amount, update credit user, log, email).
		$ok = $this->booking_service->apply_cancellation_result( $booking_id, $evaluation, $current_user_id );

		if ( ! $ok ) {
			wp_send_json_error(
				array( 'message' => __( 'Failed to process cancellation.', 'tribuna-studio-rent-booking' ) ),
				500
			);
		}

		$final_booking = $this->booking_model->get( $booking_id );
		$refund_amount = isset( $evaluation['refund_percent'] )
			? round( (float) $final_booking->final_price * (int) $evaluation['refund_percent'] / 100 )
			: 0;

		wp_send_json_success(
			array(
				'message'        => __( 'Cancellation approved and booking marked as cancelled.', 'tribuna-studio-rent-booking' ),
				'status'         => 'cancelled',
				'refund_type'    => isset( $evaluation['refund_type'] ) ? $evaluation['refund_type'] : 'none',
				'refund_percent' => isset( $evaluation['refund_percent'] ) ? (int) $evaluation['refund_percent'] : 0,
				'refund_amount'  => (float) $refund_amount,
			)
		);
	}

	/**
	 * Admin: direct cancel booking (manual refund handling).
	 *
	 * Action: wp_ajax_tsrb_admin_direct_cancel_booking
	 */
	public function admin_direct_cancel_booking() {
		check_ajax_referer( 'tsrb_admin_nonce', 'nonce' );

		if ( ! current_user_can( Tribuna_Helpers::booking_capability() ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Unauthorized', 'tribuna-studio-rent-booking' ) ),
				403
			);
		}

		$booking_id = isset( $_POST['booking_id'] ) ? (int) $_POST['booking_id'] : 0;
		$admin_note = isset( $_POST['admin_note'] ) ? wp_kses_post( wp_unslash( $_POST['admin_note'] ) ) : '';

		if ( ! $booking_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid booking ID.', 'tribuna-studio-rent-booking' ) ),
				400
			);
		}

		$booking = $this->booking_model->get( $booking_id );
		if ( ! $booking ) {
			wp_send_json_error(
				array( 'message' => __( 'Booking not found.', 'tribuna-studio-rent-booking' ) ),
				404
			);
		}

		// Direct cancel: tanpa perhitungan refund otomatis, hanya status & catatan.
		$ok = $this->booking_model->update(
			$booking_id,
			array(
				'status'     => 'cancelled',
				'admin_note' => $admin_note,
			)
		);

		if ( ! $ok ) {
			wp_send_json_error(
				array( 'message' => __( 'Failed to process cancellation.', 'tribuna-studio-rent-booking' ) ),
				500
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Booking cancelled. Please handle refund manually.', 'tribuna-studio-rent-booking' ),
				'status'  => 'cancelled',
			)
		);
	}

	/**
	 * Quick View booking untuk modal di list admin.
	 *
	 * Action: wp_ajax_tsrb_get_booking_quick_view
	 */
	public function get_booking_quick_view() {
		check_ajax_referer( 'tsrb_admin_nonce', 'nonce' );

		if ( ! current_user_can( Tribuna_Helpers::booking_capability() ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Unauthorized', 'tribuna-studio-rent-booking' ) ),
				403
			);
		}

		$booking_id = isset( $_GET['booking_id'] ) ? (int) $_GET['booking_id'] : 0;
		if ( ! $booking_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid booking ID.', 'tribuna-studio-rent-booking' ) ),
				400
			);
		}

		$booking = $this->booking_model->get( $booking_id );
		if ( ! $booking ) {
			wp_send_json_error(
				array( 'message' => __( 'Booking not found.', 'tribuna-studio-rent-booking' ) ),
				404
			);
		}

		$studio_name = '';
		if ( ! empty( $booking->studio_id ) ) {
			$studio = $this->studio_model->get( (int) $booking->studio_id );
			if ( $studio ) {
				$studio_name = $studio->name;
			}
		}

		$logs_html = '';
		if ( class_exists( 'Tribuna_Booking_Log_Model' ) ) {
			$log_model = new Tribuna_Booking_Log_Model();
			$logs      = $log_model->get_by_booking_id( $booking_id );

			ob_start();
			?>
			<table class="widefat fixed striped tsrb-booking-log-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date / Time', 'tribuna-studio-rent-booking' ); ?></th>
						<th><?php esc_html_e( 'Changed By', 'tribuna-studio-rent-booking' ); ?></th>
						<th><?php esc_html_e( 'From → To', 'tribuna-studio-rent-booking' ); ?></th>
						<th><?php esc_html_e( 'Note', 'tribuna-studio-rent-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! empty( $logs ) ) : ?>
					<?php foreach ( $logs as $log ) : ?>
						<?php
						$changed_by_name = '';
						if ( ! empty( $log->changed_by_display ) ) {
							$changed_by_name = $log->changed_by_display;
						} elseif ( ! empty( $log->changed_by ) ) {
							$changed_by_name = sprintf( '#%d', (int) $log->changed_by );
						} else {
							$changed_by_name = '-';
						}

						$from_to = '';
						if ( $log->old_status ) {
							$from_to = sprintf(
								'%s → %s',
								$log->old_status,
								$log->new_status
							);
						} else {
							$from_to = $log->new_status;
						}
						?>
						<tr>
							<td><?php echo esc_html( $log->created_at ); ?></td>
							<td><?php echo esc_html( $changed_by_name ); ?></td>
							<td><?php echo esc_html( $from_to ); ?></td>
							<td><?php echo esc_html( $log->note ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="4">
							<em><?php esc_html_e( 'No log entries yet.', 'tribuna-studio-rent-booking' ); ?></em>
						</td>
					</tr>
				<?php endif; ?>
				</tbody>
			</table>
			<?php
			$logs_html = ob_get_clean();
		} else {
			$logs_html = '<p>' . esc_html__( 'Activity log will appear here after log table is implemented.', 'tribuna-studio-rent-booking' ) . '</p>';
		}

		ob_start();
		?>
		<div class="tsrb-booking-quick-summary">
			<h2><?php echo esc_html( sprintf( __( 'Booking #%d', 'tribuna-studio-rent-booking' ), $booking->id ) ); ?></h2>

			<table class="widefat fixed striped">
				<tbody>
					<tr>
						<th><?php esc_html_e( 'Customer', 'tribuna-studio-rent-booking' ); ?></th>
						<td>
							<?php echo esc_html( $booking->user_name ); ?><br>
							<span class="description"><?php echo esc_html( $booking->email ); ?></span><br>
							<span class="description"><?php echo esc_html( $booking->phone ); ?></span>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Studio', 'tribuna-studio-rent-booking' ); ?></th>
						<td><?php echo $studio_name ? esc_html( $studio_name ) : '&mdash;'; ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Date & Time', 'tribuna-studio-rent-booking' ); ?></th>
						<td><?php echo esc_html( $booking->date . ' | ' . $booking->start_time . ' - ' . $booking->end_time ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Duration', 'tribuna-studio-rent-booking' ); ?></th>
						<td><?php echo esc_html( $booking->duration ); ?> <?php esc_html_e( 'hours', 'tribuna-studio-rent-booking' ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Add-ons', 'tribuna-studio-rent-booking' ); ?></th>
						<td><?php echo $booking->addons ? esc_html( $booking->addons ) : '&mdash;'; ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Coupon', 'tribuna-studio-rent-booking' ); ?></th>
						<td><?php echo $booking->coupon_code ? esc_html( $booking->coupon_code ) : '&mdash;'; ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Total / Final', 'tribuna-studio-rent-booking' ); ?></th>
						<td>
							<?php
							echo esc_html(
								sprintf(
									'%s → %s',
									Tribuna_Helpers::format_price( $booking->total_price ),
									Tribuna_Helpers::format_price( $booking->final_price )
								)
							);
							?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Status', 'tribuna-studio-rent-booking' ); ?></th>
						<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $booking->status ) ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Payment Proof', 'tribuna-studio-rent-booking' ); ?></th>
						<td>
							<?php if ( ! empty( $booking->payment_proof ) ) : ?>
								<a href="<?php echo esc_url( $booking->payment_proof ); ?>" target="_blank" rel="noopener noreferrer">
									<?php esc_html_e( 'View payment proof', 'tribuna-studio-rent-booking' ); ?>
								</a>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Created / Updated', 'tribuna-studio-rent-booking' ); ?></th>
						<td>
							<?php
							echo esc_html( $booking->created_at );
							if ( ! empty( $booking->updated_at ) ) {
								echo ' &mdash; ' . esc_html( $booking->updated_at );
							}
							?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Google Calendar', 'tribuna-studio-rent-booking' ); ?></th>
						<td>
							<?php if ( ! empty( $booking->google_calendar_url ) ) : ?>
								<a href="<?php echo esc_url( $booking->google_calendar_url ); ?>" target="_blank" rel="noopener noreferrer">
									<?php esc_html_e( 'Open in Google Calendar', 'tribuna-studio-rent-booking' ); ?>
								</a>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</td>
					</tr>
					<?php if ( ! empty( $booking->admin_note ) ) : ?>
						<tr>
							<th><?php esc_html_e( 'Admin Note', 'tribuna-studio-rent-booking' ); ?></th>
							<td><?php echo wp_kses_post( nl2br( $booking->admin_note ) ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'Activity Log', 'tribuna-studio-rent-booking' ); ?></h3>
			<div class="tsrb-booking-log">
				<?php echo $logs_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
		<?php
		$html = ob_get_clean();

		wp_send_json_success(
			array(
				'html' => $html,
			)
		);
	}
}
