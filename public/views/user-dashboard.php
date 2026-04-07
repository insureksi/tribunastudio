<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend User Booking Dashboard
 *
 * Variabel yang tersedia:
 * - $upcoming                  : array booking aktif/mendatang.
 * - $history                   : array booking riwayat.
 * - $studio_model              : instance Tribuna_Studio_Model (dari AJAX).
 * - $member_reschedule_enabled : bool, apakah member boleh reschedule sendiri.
 * - $payment_window_seconds    : int, durasi batas waktu pembayaran (detik, 0 = tidak ada batas).
 * - $server_now                : int, timestamp server sekarang (Unix).
 *
 * Tambahan (opsional, dari controller):
 * - $member_cancel_enabled     : bool, apakah member boleh request cancel.
 * - $cancel_policy_map         : array[id_booking => array hasil evaluasi policy].
 */

/** @var array $upcoming */
/** @var array $history */
/** @var Tribuna_Studio_Model $studio_model */

if ( ! isset( $studio_model ) || ! ( $studio_model instanceof Tribuna_Studio_Model ) ) {
	$studio_model = new Tribuna_Studio_Model();
}

// Fallback helper hanya jika variabel belum dikirim dari AJAX (tidak override).
if ( class_exists( 'Tribuna_Helpers' ) && method_exists( 'Tribuna_Helpers', 'get_settings' ) ) {
	$tsrb_settings = Tribuna_Helpers::get_settings();
} else {
	$tsrb_settings = array();
}

$workflow = isset( $tsrb_settings['workflow'] ) && is_array( $tsrb_settings['workflow'] ) ? $tsrb_settings['workflow'] : array();

// ----------------------
// Reschedule flags.
// ----------------------
if ( ! isset( $member_reschedule_enabled ) ) {
	$allow_member_reschedule   = ! empty( $workflow['allow_member_reschedule'] );
	$reschedule_admin_only_opt = ! empty( $workflow['reschedule_admin_only'] );
	$member_reschedule_enabled = ( $allow_member_reschedule && ! $reschedule_admin_only_opt );
} else {
	$allow_member_reschedule   = (bool) $member_reschedule_enabled;
	$reschedule_admin_only_opt = false;
}

$reschedule_cutoff_hours  = isset( $workflow['reschedule_cutoff_hours'] ) ? (int) $workflow['reschedule_cutoff_hours'] : 0;
$reschedule_allow_pending = ! empty( $workflow['reschedule_allow_pending'] );

// ----------------------
// Cancellation / refund workflow.
// ----------------------
if ( ! isset( $member_cancel_enabled ) ) {
	$allow_member_cancel   = ! empty( $workflow['allow_member_cancel'] );
	$member_cancel_enabled = (bool) $allow_member_cancel;
} else {
	$allow_member_cancel = (bool) $member_cancel_enabled;
}

$refund_full_hours_before      = isset( $workflow['refund_full_hours_before'] ) ? (int) $workflow['refund_full_hours_before'] : 0;
$refund_partial_hours_before   = isset( $workflow['refund_partial_hours_before'] ) ? (int) $workflow['refund_partial_hours_before'] : 0;
$refund_partial_percent        = isset( $workflow['refund_partial_percent'] ) ? (int) $workflow['refund_partial_percent'] : 0;
$refund_no_refund_inside_hours = isset( $workflow['refund_no_refund_inside_hours'] ) ? (int) $workflow['refund_no_refund_inside_hours'] : 0;

// ----------------------
// Aturan booking & reschedule tambahan.
// Diselaraskan dengan workflow_defaults di settings.
// ----------------------
$booking_min_hours_before_start = isset( $workflow['min_lead_time_hours'] ) ? (int) $workflow['min_lead_time_hours'] : 0;
$booking_max_days_in_advance    = isset( $workflow['booking_max_days_in_advance'] ) ? (int) $workflow['booking_max_days_in_advance'] : 0;
$booking_require_full_payment   = ! empty( $workflow['booking_require_full_payment'] );
$max_active_bookings_per_user   = isset( $workflow['max_active_bookings_per_user'] ) ? (int) $workflow['max_active_bookings_per_user'] : 0;
$reschedule_max_changes         = isset( $workflow['reschedule_max_changes'] ) ? (int) $workflow['reschedule_max_changes'] : 0;

// Payment window & server time fallback jika belum dikirim dari AJAX.
if ( ! isset( $payment_window_seconds ) ) {
	$payment_window_seconds = 0;
	if ( isset( $workflow['payment_deadline_hours'] ) ) {
		$payment_window_seconds = (int) $workflow['payment_deadline_hours'] * HOUR_IN_SECONDS;
	}
}

if ( ! isset( $server_now ) ) {
	$server_now = current_time( 'timestamp' );
}

// Optional: peta policy per booking dari controller.
$cancel_policy_map = isset( $cancel_policy_map ) && is_array( $cancel_policy_map ) ? $cancel_policy_map : array();

/**
 * Helper: hitung data payment timer untuk satu booking (mirror kolom Payment Timer di backend).
 *
 * @param object $booking
 * @param int    $payment_window_seconds
 * @param int    $server_now
 *
 * @return array
 */
function tsrb_get_payment_timer_data_for_booking( $booking, $payment_window_seconds, $server_now ) {
	$result = array(
		'has_window'    => false,
		'active'        => false,
		'expired'       => false,
		'expires'       => 0,
		'state_label'   => '',
		'initial_label' => '',
	);

	if ( $payment_window_seconds <= 0 || ! $booking ) {
		$result['state_label'] = __( 'N/A', 'tribuna-studio-rent-booking' );
		return $result;
	}

	if ( empty( $booking->created_at ) ) {
		$result['state_label'] = __( 'N/A', 'tribuna-studio-rent-booking' );
		return $result;
	}

	$created_ts = strtotime( $booking->created_at );
	if ( ! $created_ts ) {
		$result['state_label'] = __( 'N/A', 'tribuna-studio-rent-booking' );
		return $result;
	}

	$expires_ts           = $created_ts + $payment_window_seconds;
	$result['has_window'] = true;
	$result['expires']    = $expires_ts;

	// Status non-pending: ikuti label statis seperti backend.
	if ( 'paid' === $booking->status ) {
		$result['state_label'] = __( 'Completed', 'tribuna-studio-rent-booking' );
		return $result;
	}

	if ( 'cancelled' === $booking->status ) {
		$result['state_label'] = __( 'Cancelled', 'tribuna-studio-rent-booking' );
		return $result;
	}

	// Hanya pending_payment yang butuh timer.
	if ( 'pending_payment' !== $booking->status ) {
		$result['state_label'] = __( 'N/A', 'tribuna-studio-rent-booking' );
		return $result;
	}

	// Pending + window aktif.
	if ( $server_now >= $expires_ts ) {
		$result['expired']     = true;
		$result['state_label'] = __( 'Expired', 'tribuna-studio-rent-booking' );
	} else {
		$result['active'] = true;

		// Isi label awal agar kolom tidak kosong sebelum JS update.
		$remaining = $expires_ts - $server_now;
		if ( $remaining < 0 ) {
			$remaining = 0;
		}
		$hours   = floor( $remaining / 3600 );
		$minutes = floor( ( $remaining % 3600 ) / 60 );

		if ( $hours > 0 ) {
			/* translators: %1$d: hours, %2$d: minutes */
			$result['initial_label'] = sprintf(
				__( '%1$d jam %2$d menit', 'tribuna-studio-rent-booking' ),
				(int) $hours,
				(int) $minutes
			);
		} elseif ( $minutes > 0 ) {
			/* translators: %d: minutes */
			$result['initial_label'] = sprintf(
				__( '%d menit', 'tribuna-studio-rent-booking' ),
				(int) $minutes
			);
		} else {
			$result['initial_label'] = __( 'Kurang dari 1 menit', 'tribuna-studio-rent-booking' );
		}
	}

	return $result;
}

/**
 * Helper: ambil policy cancellation untuk booking tertentu (dari map atau property pada objek).
 *
 * @param object $booking
 * @param array  $cancel_policy_map
 *
 * @return array
 */
function tsrb_get_cancel_policy_for_booking( $booking, $cancel_policy_map ) {
	if ( ! $booking || empty( $booking->id ) ) {
		return array();
	}

	$id = (int) $booking->id;

	if ( isset( $cancel_policy_map[ $id ] ) && is_array( $cancel_policy_map[ $id ] ) ) {
		return $cancel_policy_map[ $id ];
	}

	if ( isset( $booking->cancel_policy ) && is_array( $booking->cancel_policy ) ) {
		return $booking->cancel_policy;
	}

	return array();
}

/**
 * Helper: label status frontend termasuk cancel requested.
 *
 * @param string $status
 *
 * @return string
 */
function tsrb_get_frontend_status_label( $status ) {
	if ( 'pending_payment' === $status ) {
		return __( 'Menunggu Pembayaran', 'tribuna-studio-rent-booking' );
	} elseif ( 'paid' === $status ) {
		return __( 'Sudah Dibayar', 'tribuna-studio-rent-booking' );
	} elseif ( 'cancel_requested' === $status ) {
		return __( 'Pengajuan Pembatalan', 'tribuna-studio-rent-booking' );
	} elseif ( 'cancelled' === $status ) {
		return __( 'Dibatalkan', 'tribuna-studio-rent-booking' );
	}

	return ucfirst( str_replace( '_', ' ', $status ) );
}
?>

<div class="tsrb-user-dashboard">

	<h2 class="tsrb-user-dashboard-title">
		<?php esc_html_e( 'Booking Saya', 'tribuna-studio-rent-booking' ); ?>
	</h2>

	<div class="tsrb-user-dashboard-section tsrb-user-dashboard-upcoming">
		<h3><?php esc_html_e( 'Booking Aktif / Mendatang', 'tribuna-studio-rent-booking' ); ?></h3>

		<?php if ( empty( $upcoming ) ) : ?>
			<p class="tsrb-user-dashboard-empty">
				<?php esc_html_e( 'Belum ada booking mendatang.', 'tribuna-studio-rent-booking' ); ?>
			</p>
		<?php else : ?>
			<table
				class="tsrb-user-bookings-table tsrb-user-bookings-table-upcoming"
				data-tsrb-server-now="<?php echo esc_attr( (int) $server_now ); ?>"
				data-tsrb-payment-window="<?php echo esc_attr( (int) $payment_window_seconds ); ?>"
			>
				<thead>
					<tr>
						<th><?php esc_html_e( 'Tanggal', 'tribuna-studio-rent-booking' ); ?></th>
						<th><?php esc_html_e( 'Jam', 'tribuna-studio-rent-booking' ); ?></th>
						<th><?php esc_html_e( 'Studio', 'tribuna-studio-rent-booking' ); ?></th>
						<th><?php esc_html_e( 'Total', 'tribuna-studio-rent-booking' ); ?></th>
						<th><?php esc_html_e( 'Status', 'tribuna-studio-rent-booking' ); ?></th>
						<th><?php esc_html_e( 'Batas Waktu Pembayaran', 'tribuna-studio-rent-booking' ); ?></th>
						<?php if ( $member_reschedule_enabled || $member_cancel_enabled ) : ?>
							<th><?php esc_html_e( 'Aksi', 'tribuna-studio-rent-booking' ); ?></th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $upcoming as $booking ) : ?>
						<?php
						$studio_name = '—';
						if ( ! empty( $booking->studio_id ) ) {
							$studio = $studio_model->get( (int) $booking->studio_id );
							if ( $studio && ! empty( $studio->name ) ) {
								$studio_name = $studio->name;
							}
						}

						$label_status = tsrb_get_frontend_status_label( $booking->status );
						$timer        = tsrb_get_payment_timer_data_for_booking( $booking, $payment_window_seconds, $server_now );

						// Reschedule permission.
						$show_reschedule_button = false;
						if ( $member_reschedule_enabled && in_array( $booking->status, array( 'pending_payment', 'paid' ), true ) ) {
							$show_reschedule_button = true;
							if ( 'pending_payment' === $booking->status && ! $reschedule_allow_pending ) {
								$show_reschedule_button = false;
							}
						}

						// Cancellation permission.
						$cancel_policy       = tsrb_get_cancel_policy_for_booking( $booking, $cancel_policy_map );
						$can_request_cancel  = false;
						$cancel_button_label = __( 'Ajukan Pembatalan', 'tribuna-studio-rent-booking' );

						if ( $member_cancel_enabled && in_array( $booking->status, array( 'pending_payment', 'paid' ), true ) ) {
							$can_request_cancel = true;
						}
						if ( 'cancel_requested' === $booking->status ) {
							$can_request_cancel = false;
						}

						$window_label      = isset( $cancel_policy['window_label'] ) ? $cancel_policy['window_label'] : '';
						$refund_percent    = isset( $cancel_policy['refund_percent'] ) ? (int) $cancel_policy['refund_percent'] : null;
						$refundable_amount = isset( $cancel_policy['refundable_amount'] ) ? $cancel_policy['refundable_amount'] : null;
						$notes_user        = isset( $cancel_policy['notes_user'] ) ? $cancel_policy['notes_user'] : '';
						?>
						<tr
							class="tsrb-user-booking-row tsrb-user-booking-row-status-<?php echo esc_attr( $booking->status ); ?>"
							data-tsrb-booking-id="<?php echo esc_attr( $booking->id ); ?>"
							data-tsrb-booking-date="<?php echo esc_attr( $booking->date ); ?>"
							data-tsrb-booking-start="<?php echo esc_attr( $booking->start_time ); ?>"
							data-tsrb-booking-end="<?php echo esc_attr( $booking->end_time ); ?>"
							data-tsrb-booking-studio="<?php echo esc_attr( $studio_name ); ?>"
						>
							<td class="tsrb-user-booking-date">
								<?php echo esc_html( $booking->date ); ?>
							</td>
							<td class="tsrb-user-booking-time">
								<?php echo esc_html( $booking->start_time . ' - ' . $booking->end_time ); ?>
							</td>
							<td class="tsrb-user-booking-studio">
								<?php echo esc_html( $studio_name ); ?>
							</td>
							<td class="tsrb-user-booking-total">
								<?php echo esc_html( Tribuna_Helpers::format_price( $booking->final_price ) ); ?>
							</td>
							<td class="tsrb-user-booking-status">
								<?php echo esc_html( $label_status ); ?>
								<?php if ( ! empty( $window_label ) ) : ?>
									<br><small class="tsrb-user-booking-cancel-window">
										<?php echo esc_html( $window_label ); ?>
									</small>
								<?php endif; ?>
								<?php if ( null !== $refund_percent ) : ?>
									<br><small class="tsrb-user-booking-refund-percent">
										<?php
										printf(
											esc_html__( 'Perkiraan refund: %d%% dari pembayaran.', 'tribuna-studio-rent-booking' ),
											(int) $refund_percent
										);
										?>
									</small>
								<?php endif; ?>
								<?php if ( null !== $refundable_amount ) : ?>
									<br><small class="tsrb-user-booking-refund-amount">
										<?php
										printf(
											esc_html__( 'Perkiraan nominal refund: %s', 'tribuna-studio-rent-booking' ),
											esc_html( Tribuna_Helpers::format_price( $refundable_amount ) )
										);
										?>
									</small>
								<?php endif; ?>
								<?php if ( ! empty( $notes_user ) ) : ?>
									<br><small class="tsrb-user-booking-cancel-note">
										<?php echo esc_html( $notes_user ); ?>
									</small>
								<?php endif; ?>
							</td>
							<td class="tsrb-user-booking-payment-deadline">
								<?php
								if ( ! $timer['has_window'] ) :
									?>
									<span class="tsrb-payment-timer-badge tsrb-payment-timer-badge--ok">
										<span class="tsrb-timer-dot"></span>
										<span class="tsrb-timer-label">
											<?php esc_html_e( 'N/A', 'tribuna-studio-rent-booking' ); ?>
										</span>
									</span>
								<?php
								else :
									if ( 'paid' === $booking->status ) :
										?>
										<span class="tsrb-payment-timer-badge tsrb-payment-timer-badge--ok">
											<span class="tsrb-timer-dot"></span>
											<span class="tsrb-timer-label">
												<?php esc_html_e( 'Completed', 'tribuna-studio-rent-booking' ); ?>
											</span>
										</span>
										<?php
									elseif ( 'cancelled' === $booking->status ) :
										?>
										<span class="tsrb-payment-timer-badge tsrb-payment-timer-badge--expired">
											<span class="tsrb-timer-dot"></span>
											<span class="tsrb-timer-label">
												<?php esc_html_e( 'Cancelled', 'tribuna-studio-rent-booking' ); ?>
											</span>
										</span>
										<?php
									elseif ( $timer['active'] ) :
										?>
										<span
											class="tsrb-payment-timer-badge tsrb-payment-timer-badge--ok js-tsrb-payment-timer-frontend"
											data-expires="<?php echo esc_attr( (int) $timer['expires'] ); ?>"
											data-server-now="<?php echo esc_attr( (int) $server_now ); ?>"
										>
											<span class="tsrb-timer-dot"></span>
											<span class="tsrb-timer-label">
												<?php echo esc_html( $timer['initial_label'] ); ?>
											</span>
										</span>
										<?php
									else :
										?>
										<span class="tsrb-payment-timer-badge tsrb-payment-timer-badge--expired">
											<span class="tsrb-timer-dot"></span>
											<span class="tsrb-timer-label">
												<?php esc_html_e( 'Expired', 'tribuna-studio-rent-booking' ); ?>
											</span>
										</span>
										<?php
									endif;
								endif;
								?>
							</td>
							<?php if ( $member_reschedule_enabled || $member_cancel_enabled ) : ?>
								<td class="tsrb-user-booking-actions">
									<?php if ( $member_reschedule_enabled && $show_reschedule_button ) : ?>
										<button
											type="button"
											class="tsrb-btn tsrb-btn-reschedule"
											data-tsrb-reschedule-booking-id="<?php echo esc_attr( $booking->id ); ?>"
										>
											<?php esc_html_e( 'Ubah Jadwal', 'tribuna-studio-rent-booking' ); ?>
										</button>
									<?php endif; ?>

									<?php if ( $member_cancel_enabled ) : ?>
										<?php if ( $can_request_cancel ) : ?>
											<button
												type="button"
												class="tsrb-btn tsrb-btn-cancel-request"
												data-tsrb-cancel-booking-id="<?php echo esc_attr( $booking->id ); ?>"
											>
												<?php echo esc_html( $cancel_button_label ); ?>
											</button>
										<?php elseif ( 'cancel_requested' === $booking->status ) : ?>
											<span class="tsrb-user-booking-cancel-requested-label">
												<?php esc_html_e( 'Sedang diproses admin', 'tribuna-studio-rent-booking' ); ?>
											</span>
										<?php endif; ?>
									<?php endif; ?>

									<?php if ( ! $show_reschedule_button && ! $can_request_cancel && 'cancel_requested' !== $booking->status ) : ?>
										<span class="tsrb-user-booking-no-action">—</span>
									<?php endif; ?>
								</td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<div class="tsrb-user-dashboard-note tsrb-user-dashboard-note-booking">
				<strong><?php esc_html_e( 'Aturan Booking', 'tribuna-studio-rent-booking' ); ?></strong>
				<ul>
					<li><?php esc_html_e( 'Booking hanya dapat dibuat untuk jadwal yang masih tersedia di kalender studio.', 'tribuna-studio-rent-booking' ); ?></li>
					<?php if ( $booking_min_hours_before_start > 0 ) : ?>
						<li>
							<?php
							printf(
								esc_html__( 'Booking harus dibuat minimal %d jam sebelum jam mulai.', 'tribuna-studio-rent-booking' ),
								(int) $booking_min_hours_before_start
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
					<?php if ( $payment_window_seconds > 0 ) : ?>
						<li>
							<?php
							$payment_deadline_hours = (int) floor( $payment_window_seconds / HOUR_IN_SECONDS );
							if ( $payment_deadline_hours > 0 ) {
								printf(
									esc_html__( 'Jika pembayaran tidak diterima dalam waktu %d jam sejak booking dibuat, booking akan dibatalkan otomatis.', 'tribuna-studio-rent-booking' ),
									(int) $payment_deadline_hours
								);
							} else {
								esc_html_e( 'Jika pembayaran tidak diterima dalam waktu yang ditentukan, booking dapat dibatalkan oleh sistem.', 'tribuna-studio-rent-booking' );
							}
							?>
						</li>
					<?php endif; ?>
					<?php if ( $booking_require_full_payment ) : ?>
						<li><?php esc_html_e( 'Booking dianggap sah setelah admin mengonfirmasi bahwa pembayaran telah diterima sesuai instruksi pada saat pemesanan.', 'tribuna-studio-rent-booking' ); ?></li>
					<?php endif; ?>
				</ul>
			</div>

			<div class="tsrb-user-dashboard-note tsrb-user-dashboard-note-reschedule-policy">
				<strong><?php esc_html_e( 'Aturan Reschedule', 'tribuna-studio-rent-booking' ); ?></strong>
				<ul>
					<li><?php esc_html_e( 'Perubahan jadwal hanya dapat diajukan dengan menghubungi admin (WhatsApp atau e-mail) dan akan diproses jika slot jadwal baru masih tersedia.', 'tribuna-studio-rent-booking' ); ?></li>
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
			</div>

			<?php if ( $refund_full_hours_before || $refund_partial_hours_before || $refund_no_refund_inside_hours ) : ?>
				<div class="tsrb-user-dashboard-note tsrb-user-dashboard-note-cancellation">
					<strong><?php esc_html_e( 'Aturan Pembatalan & Refund', 'tribuna-studio-rent-booking' ); ?></strong>
					<ul>
						<?php if ( $refund_full_hours_before ) : ?>
							<li>
								<?php
								printf(
									esc_html__( 'Refund penuh diberikan jika pembatalan dilakukan minimal %d jam sebelum jam mulai.', 'tribuna-studio-rent-booking' ),
									(int) $refund_full_hours_before
								);
								?>
							</li>
						<?php endif; ?>
						<?php if ( $refund_partial_hours_before && $refund_partial_percent ) : ?>
							<li>
								<?php
								printf(
									esc_html__( 'Refund parsial sebesar %1$d%% diberikan jika pembatalan dilakukan sebelum %2$d jam dari jam mulai.', 'tribuna-studio-rent-booking' ),
									(int) $refund_partial_percent,
									(int) $refund_partial_hours_before
								);
								?>
							</li>
						<?php endif; ?>
						<?php if ( $refund_no_refund_inside_hours ) : ?>
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
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<div class="tsrb-user-dashboard-section tsrb-user-dashboard-history">
		<h3><?php esc_html_e( 'Riwayat Booking', 'tribuna-studio-rent-booking' ); ?></h3>

		<?php if ( empty( $history ) ) : ?>
			<p class="tsrb-user-dashboard-empty">
				<?php esc_html_e( 'Belum ada riwayat booking.', 'tribuna-studio-rent-booking' ); ?>
			</p>
		<?php else : ?>
			<table
				class="tsrb-user-bookings-table tsrb-user-bookings-table-history"
				data-tsrb-server-now="<?php echo esc_attr( (int) $server_now ); ?>"
				data-tsrb-payment-window="<?php echo esc_attr( (int) $payment_window_seconds ); ?>"
			>
				<thead>
					<tr>
						<th><?php esc_html_e( 'Tanggal', 'tribuna-studio-rent-booking' ); ?></th>
						<th><?php esc_html_e( 'Jam', 'tribuna-studio-rent-booking' ); ?></th>
						<th><?php esc_html_e( 'Studio', 'tribuna-studio-rent-booking' ); ?></th>
						<th><?php esc_html_e( 'Total', 'tribuna-studio-rent-booking' ); ?></th>
						<th><?php esc_html_e( 'Status', 'tribuna-studio-rent-booking' ); ?></th>
						<th><?php esc_html_e( 'Batas Waktu Pembayaran', 'tribuna-studio-rent-booking' ); ?></th>
						<?php if ( $member_reschedule_enabled || $member_cancel_enabled ) : ?>
							<th><?php esc_html_e( 'Aksi', 'tribuna-studio-rent-booking' ); ?></th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $history as $booking ) : ?>
						<?php
						$studio_name = '—';
						if ( ! empty( $booking->studio_id ) ) {
							$studio = $studio_model->get( (int) $booking->studio_id );
							if ( $studio && ! empty( $studio->name ) ) {
								$studio_name = $studio->name;
							}
						}

						$label_status = tsrb_get_frontend_status_label( $booking->status );
						$timer        = tsrb_get_payment_timer_data_for_booking( $booking, $payment_window_seconds, $server_now );

						// Allow reschedule/cancel dari history hanya bila status masih aktif.
						$show_reschedule_button_history = false;
						if ( $member_reschedule_enabled && in_array( $booking->status, array( 'pending_payment', 'paid' ), true ) ) {
							$show_reschedule_button_history = true;
							if ( 'pending_payment' === $booking->status && ! $reschedule_allow_pending ) {
								$show_reschedule_button_history = false;
							}
						}

						$cancel_policy      = tsrb_get_cancel_policy_for_booking( $booking, $cancel_policy_map );
						$can_request_cancel = false;
						if ( $member_cancel_enabled && in_array( $booking->status, array( 'pending_payment', 'paid' ), true ) ) {
							$can_request_cancel = true;
						}
						if ( 'cancel_requested' === $booking->status ) {
							$can_request_cancel = false;
						}

						$window_label      = isset( $cancel_policy['window_label'] ) ? $cancel_policy['window_label'] : '';
						$refund_percent    = isset( $cancel_policy['refund_percent'] ) ? (int) $cancel_policy['refund_percent'] : null;
						$refundable_amount = isset( $cancel_policy['refundable_amount'] ) ? $cancel_policy['refundable_amount'] : null;
						$notes_user        = isset( $cancel_policy['notes_user'] ) ? $cancel_policy['notes_user'] : '';
						?>
						<tr
							class="tsrb-user-booking-row tsrb-user-booking-row-status-<?php echo esc_attr( $booking->status ); ?>"
							data-tsrb-booking-id="<?php echo esc_attr( $booking->id ); ?>"
						>
							<td class="tsrb-user-booking-date">
								<?php echo esc_html( $booking->date ); ?>
							</td>
							<td class="tsrb-user-booking-time">
								<?php echo esc_html( $booking->start_time . ' - ' . $booking->end_time ); ?>
							</td>
							<td class="tsrb-user-booking-studio">
								<?php echo esc_html( $studio_name ); ?>
							</td>
							<td class="tsrb-user-booking-total">
								<?php echo esc_html( Tribuna_Helpers::format_price( $booking->final_price ) ); ?>
							</td>
							<td class="tsrb-user-booking-status">
								<?php echo esc_html( $label_status ); ?>
								<?php if ( ! empty( $window_label ) ) : ?>
									<br><small class="tsrb-user-booking-cancel-window">
										<?php echo esc_html( $window_label ); ?>
									</small>
								<?php endif; ?>
								<?php if ( null !== $refund_percent ) : ?>
									<br><small class="tsrb-user-booking-refund-percent">
										<?php
										printf(
											esc_html__( 'Perkiraan refund: %d%% dari pembayaran.', 'tribuna-studio-rent-booking' ),
											(int) $refund_percent
										);
										?>
									</small>
								<?php endif; ?>
								<?php if ( null !== $refundable_amount ) : ?>
									<br><small class="tsrb-user-booking-refund-amount">
										<?php
										printf(
											esc_html__( 'Perkiraan nominal refund: %s', 'tribuna-studio-rent-booking' ),
											esc_html( Tribuna_Helpers::format_price( $refundable_amount ) )
										);
										?>
									</small>
								<?php endif; ?>
								<?php if ( ! empty( $notes_user ) ) : ?>
									<br><small class="tsrb-user-booking-cancel-note">
										<?php echo esc_html( $notes_user ); ?>
									</small>
								<?php endif; ?>
							</td>
							<td class="tsrb-user-booking-payment-deadline">
								<?php
								if ( ! $timer['has_window'] ) :
									?>
									<span class="tsrb-payment-timer-badge tsrb-payment-timer-badge--ok">
										<span class="tsrb-timer-dot"></span>
										<span class="tsrb-timer-label">
											<?php esc_html_e( 'N/A', 'tribuna-studio-rent-booking' ); ?>
										</span>
									</span>
								<?php
								else :
									if ( 'paid' === $booking->status ) :
										?>
										<span class="tsrb-payment-timer-badge tsrb-payment-timer-badge--ok">
											<span class="tsrb-timer-dot"></span>
											<span class="tsrb-timer-label">
												<?php esc_html_e( 'Completed', 'tribuna-studio-rent-booking' ); ?>
											</span>
										</span>
										<?php
									elseif ( 'cancelled' === $booking->status ) :
										?>
										<span class="tsrb-payment-timer-badge tsrb-payment-timer-badge--expired">
											<span class="tsrb-timer-dot"></span>
											<span class="tsrb-timer-label">
												<?php esc_html_e( 'Cancelled', 'tribuna-studio-rent-booking' ); ?>
											</span>
										</span>
										<?php
									elseif ( $timer['active'] ) :
										?>
										<span
											class="tsrb-payment-timer-badge tsrb-payment-timer-badge--ok js-tsrb-payment-timer-frontend"
											data-expires="<?php echo esc_attr( (int) $timer['expires'] ); ?>"
											data-server-now="<?php echo esc_attr( (int) $server_now ); ?>"
										>
											<span class="tsrb-timer-dot"></span>
											<span class="tsrb-timer-label">
												<?php echo esc_html( $timer['initial_label'] ); ?>
											</span>
										</span>
										<?php
									else :
										?>
										<span class="tsrb-payment-timer-badge tsrb-payment-timer-badge--expired">
											<span class="tsrb-timer-dot"></span>
											<span class="tsrb-timer-label">
												<?php esc_html_e( 'Expired', 'tribuna-studio-rent-booking' ); ?>
											</span>
										</span>
										<?php
									endif;
								endif;
								?>
							</td>
							<?php if ( $member_reschedule_enabled || $member_cancel_enabled ) : ?>
								<td class="tsrb-user-booking-actions">
									<?php if ( $member_reschedule_enabled && $show_reschedule_button_history ) : ?>
										<button
											type="button"
											class="tsrb-btn tsrb-btn-reschedule"
											data-tsrb-reschedule-booking-id="<?php echo esc_attr( $booking->id ); ?>"
											data-tsrb-reschedule-from-history="1"
										>
											<?php esc_html_e( 'Ubah Jadwal', 'tribuna-studio-rent-booking' ); ?>
										</button>
									<?php endif; ?>

									<?php if ( $member_cancel_enabled ) : ?>
										<?php if ( $can_request_cancel ) : ?>
											<button
												type="button"
												class="tsrb-btn tsrb-btn-cancel-request"
												data-tsrb-cancel-booking-id="<?php echo esc_attr( $booking->id ); ?>"
											>
												<?php esc_html_e( 'Ajukan Pembatalan', 'tribuna-studio-rent-booking' ); ?>
											</button>
										<?php elseif ( 'cancel_requested' === $booking->status ) : ?>
											<span class="tsrb-user-booking-cancel-requested-label">
												<?php esc_html_e( 'Sedang diproses admin', 'tribuna-studio-rent-booking' ); ?>
											</span>
										<?php endif; ?>
									<?php endif; ?>

									<?php if ( ! $show_reschedule_button_history && ! $can_request_cancel && 'cancel_requested' !== $booking->status ) : ?>
										<span class="tsrb-user-booking-no-action">—</span>
									<?php endif; ?>
								</td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>

<?php if ( $member_reschedule_enabled ) : ?>
	<div class="tsrb-modal tsrb-modal-reschedule" style="display:none;">
		<div class="tsrb-modal-backdrop"></div>
		<div class="tsrb-modal-dialog">
			<div class="tsrb-modal-header">
				<h4 class="tsrb-modal-title">
					<?php esc_html_e( 'Ubah Jadwal Booking', 'tribuna-studio-rent-booking' ); ?>
				</h4>
				<button type="button" class="tsrb-modal-close" aria-label="<?php esc_attr_e( 'Tutup', 'tribuna-studio-rent-booking' ); ?>">×</button>
			</div>
			<div class="tsrb-modal-body">
				<p class="tsrb-modal-booking-info">
					<strong><?php esc_html_e( 'Booking:', 'tribuna-studio-rent-booking' ); ?></strong>
					<span class="tsrb-modal-booking-summary"></span>
				</p>

				<div class="tsrb-modal-field">
					<label for="tsrb-reschedule-date">
						<?php esc_html_e( 'Tanggal baru', 'tribuna-studio-rent-booking' ); ?>
					</label>
					<input type="date" id="tsrb-reschedule-date" class="tsrb-input tsrb-input-date" />
				</div>

				<div class="tsrb-modal-field tsrb-modal-field-time">
					<label for="tsrb-reschedule-start">
						<?php esc_html_e( 'Jam mulai baru', 'tribuna-studio-rent-booking' ); ?>
					</label>
					<input type="time" id="tsrb-reschedule-start" class="tsrb-input tsrb-input-time" />
				</div>

				<div class="tsrb-modal-field tsrb-modal-field-time">
					<label for="tsrb-reschedule-end">
						<?php esc_html_e( 'Jam selesai baru', 'tribuna-studio-rent-booking' ); ?>
					</label>
					<input type="time" id="tsrb-reschedule-end" class="tsrb-input tsrb-input-time" />
				</div>

				<p class="tsrb-modal-error" style="display:none;"></p>
				<p class="tsrb-modal-success" style="display:none;"></p>
			</div>
			<div class="tsrb-modal-footer">
				<button type="button" class="tsrb-btn tsrb-btn-secondary tsrb-modal-cancel">
					<?php esc_html_e( 'Batal', 'tribuna-studio-rent-booking' ); ?>
				</button>
				<button type="button" class="tsrb-btn tsrb-btn-primary tsrb-modal-submit-reschedule">
					<?php esc_html_e( 'Simpan Jadwal Baru', 'tribuna-studio-rent-booking' ); ?>
				</button>
			</div>
		</div>
	</div>
<?php endif; ?>

<?php if ( $member_cancel_enabled ) : ?>
	<div class="tsrb-modal tsrb-modal-cancel-request" style="display:none">
		<div class="tsrb-modal-backdrop"></div>
		<div class="tsrb-modal-dialog">
			<div class="tsrb-modal-header">
				<h4 class="tsrb-modal-title">
					<?php esc_html_e( 'Ajukan Pembatalan Booking', 'tribuna-studio-rent-booking' ); ?>
				</h4>
				<button type="button" class="tsrb-modal-close" aria-label="<?php esc_attr_e( 'Tutup', 'tribuna-studio-rent-booking' ); ?>">&times;</button>
			</div>
			<div class="tsrb-modal-body">
				<p class="tsrb-modal-booking-info">
					<strong><?php esc_html_e( 'Booking', 'tribuna-studio-rent-booking' ); ?>:</strong>
					<span class="tsrb-modal-cancel-booking-summary"></span>
				</p>

				<p class="tsrb-modal-cancel-policy-text">
					<?php esc_html_e( 'Mohon cek kembali estimasi refund di tabel sebelum mengirim pengajuan. Admin akan memproses sesuai kebijakan yang berlaku.', 'tribuna-studio-rent-booking' ); ?>
				</p>

				<div class="tsrb-modal-field">
					<label for="tsrb-cancel-request-note">
						<?php esc_html_e( 'Alasan pembatalan (opsional)', 'tribuna-studio-rent-booking' ); ?>
					</label>
					<textarea id="tsrb-cancel-request-note" class="tsrb-input tsrb-input-textarea" rows="3"></textarea>
				</div>
				<p class="tsrb-modal-error tsrb-modal-cancel-error" style="display:none"></p>
				<p class="tsrb-modal-success tsrb-modal-cancel-success" style="display:none"></p>
			</div>
			<div class="tsrb-modal-footer">
				<button type="button" class="tsrb-btn tsrb-btn-secondary tsrb-modal-cancel">
					<?php esc_html_e( 'Tutup', 'tribuna-studio-rent-booking' ); ?>
				</button>
				<button type="button" class="tsrb-btn tsrb-btn-primary tsrb-modal-submit-cancel-request">
					<?php esc_html_e( 'Kirim Pengajuan Pembatalan', 'tribuna-studio-rent-booking' ); ?>
				</button>
			</div>
		</div>
	</div>
<?php endif; ?>
