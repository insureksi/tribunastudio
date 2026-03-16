<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! $booking ) :
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Booking not found', 'tribuna-studio-rent-booking' ); ?></h1>
	</div>
	<?php
	return;
endif;

// Pastikan $logs selalu array.
if ( ! isset( $logs ) || ! is_array( $logs ) ) {
	$logs = array();
}

// Ambil settings workflow dari option baru, fallback legacy.
$settings_new = get_option( 'tsrbsettings', null );
if ( is_array( $settings_new ) ) {
	$settings = $settings_new;
} else {
	$settings = get_option( 'tsrb_settings', array() );
}

$workflow = isset( $settings['workflow'] ) && is_array( $settings['workflow'] ) ? $settings['workflow'] : array();

$allow_member_reschedule    = ! empty( $workflow['allow_member_reschedule'] );
$reschedule_admin_only      = ! empty( $workflow['reschedule_admin_only'] );
$reschedule_cutoff_hours    = isset( $workflow['reschedule_cutoff_hours'] ) ? (int) $workflow['reschedule_cutoff_hours'] : 0;
$reschedule_allow_pending   = ! empty( $workflow['reschedule_allow_pending'] );
$reschedule_allow_paid      = ! empty( $workflow['reschedule_allow_paid'] );
$reschedule_allow_cancelled = ! empty( $workflow['reschedule_allow_cancelled'] );

// Aturan refund/cancellation global (untuk informasi).
$refund_full_hours_before      = isset( $workflow['refund_full_hours_before'] ) ? (int) $workflow['refund_full_hours_before'] : 0;
$refund_partial_hours_before   = isset( $workflow['refund_partial_hours_before'] ) ? (int) $workflow['refund_partial_hours_before'] : 0;
$refund_partial_percent        = isset( $workflow['refund_partial_percent'] ) ? (int) $workflow['refund_partial_percent'] : 0;
$refund_no_refund_inside_hours = isset( $workflow['refund_no_refund_inside_hours'] ) ? (int) $workflow['refund_no_refund_inside_hours'] : 0;

// Di-prepare di Tribuna_Admin::render_bookings() sebelum include file ini.
$cancel_policy = isset( $cancel_policy ) && is_array( $cancel_policy ) ? $cancel_policy : array();

// Flag status booking terkait cancellation.
$is_cancelled             = ( 'cancelled' === $booking->status );
$is_pending_payment       = ( 'pending_payment' === $booking->status );
$is_paid                  = ( 'paid' === $booking->status );
$is_cancel_requested      = ( isset( $booking->status ) && 'cancel_requested' === $booking->status );
$can_admin_process_cancel = ( $is_paid || $is_pending_payment || $is_cancel_requested );

// Helper label status untuk log.
function tsrb_format_status_label( $status ) {
	$status = (string) $status;

	if ( '' === $status ) {
		return '';
	}

	switch ( $status ) {
		case 'pending_payment':
			return __( 'Pending Payment', 'tribuna-studio-rent-booking' );
		case 'paid':
			return __( 'Paid / Confirmed', 'tribuna-studio-rent-booking' );
		case 'cancel_requested':
			return __( 'Cancellation Requested', 'tribuna-studio-rent-booking' );
		case 'cancelled':
			return __( 'Cancelled', 'tribuna-studio-rent-booking' );
		default:
			// Fallback: humanize string.
			return ucfirst( str_replace( '_', ' ', $status ) );
	}
}

?>
<div class="wrap tsrb-booking-edit"
	 data-booking-id="<?php echo esc_attr( $booking->id ); ?>"
	 data-studio-id="<?php echo esc_attr( (int) $booking->studio_id ); ?>">
	<h1>
		<?php
		printf(
			/* translators: %d booking ID. */
			esc_html__( 'Booking #%d', 'tribuna-studio-rent-booking' ),
			(int) $booking->id
		);
		?>
	</h1>

	<div class="tsrb-booking-columns">
		<div class="tsrb-booking-main">
			<h2><?php esc_html_e( 'Customer Details', 'tribuna-studio-rent-booking' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Name', 'tribuna-studio-rent-booking' ); ?></th>
					<td><?php echo esc_html( $booking->user_name ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Email', 'tribuna-studio-rent-booking' ); ?></th>
					<td><?php echo esc_html( $booking->email ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Phone / WhatsApp', 'tribuna-studio-rent-booking' ); ?></th>
					<td><?php echo esc_html( $booking->phone ); ?></td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Booking Details', 'tribuna-studio-rent-booking' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Studio', 'tribuna-studio-rent-booking' ); ?></th>
					<td>
						<?php
						if ( ! empty( $booking->studio_id ) ) {
							$studio = ( new Tribuna_Studio_Model() )->get( (int) $booking->studio_id );
							echo $studio ? esc_html( $studio->name ) : '&mdash;';
						} else {
							echo '&mdash;';
						}
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Date', 'tribuna-studio-rent-booking' ); ?></th>
					<td><?php echo esc_html( $booking->date ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Time', 'tribuna-studio-rent-booking' ); ?></th>
					<td><?php echo esc_html( $booking->start_time . ' - ' . $booking->end_time ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Duration (hours)', 'tribuna-studio-rent-booking' ); ?></th>
					<td><?php echo esc_html( $booking->duration ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Add-ons', 'tribuna-studio-rent-booking' ); ?></th>
					<td>
						<?php
						if ( ! empty( $booking->addons ) ) {
							echo esc_html( $booking->addons );
						} else {
							echo '&mdash;';
						}
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Coupon', 'tribuna-studio-rent-booking' ); ?></th>
					<td>
						<?php
						if ( ! empty( $booking->coupon_code ) ) {
							echo '<strong>' . esc_html( $booking->coupon_code ) . '</strong>';
						} else {
							echo '&mdash;';
						}
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Totals', 'tribuna-studio-rent-booking' ); ?></th>
					<td>
						<?php
						echo esc_html( Tribuna_Helpers::format_price( $booking->total_price ) );
						if ( $booking->discount_amount > 0 ) {
							echo '<br>' . esc_html__( 'Discount:', 'tribuna-studio-rent-booking' ) . ' ' . esc_html( Tribuna_Helpers::format_price( $booking->discount_amount ) );
						}
						echo '<br><strong>' . esc_html__( 'Final:', 'tribuna-studio-rent-booking' ) . ' ' . esc_html( Tribuna_Helpers::format_price( $booking->final_price ) ) . '</strong>';
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Payment Proof', 'tribuna-studio-rent-booking' ); ?></th>
					<td>
						<?php
						if ( ! empty( $booking->payment_proof ) ) {
							$url = esc_url( $booking->payment_proof );
							echo '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View file', 'tribuna-studio-rent-booking' ) . '</a>';
						} else {
							echo '&mdash;';
						}
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Google Calendar', 'tribuna-studio-rent-booking' ); ?></th>
					<td>
						<?php
						if ( ! empty( $booking->google_calendar_url ) ) {
							echo '<a href="' . esc_url( $booking->google_calendar_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open in Google Calendar', 'tribuna-studio-rent-booking' ) . '</a>';
						} else {
							echo '&mdash;';
						}
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Channel', 'tribuna-studio-rent-booking' ); ?></th>
					<td>
						<?php
						$channel = ! empty( $booking->channel ) ? $booking->channel : 'website';
						echo esc_html( ucfirst( str_replace( '_', ' ', $channel ) ) );
						?>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Activity Log', 'tribuna-studio-rent-booking' ); ?></h2>
			<div class="tsrb-booking-log-box">
				<table class="widefat fixed striped">
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

								$from_label = tsrb_format_status_label( $log->old_status );
								$to_label   = tsrb_format_status_label( $log->new_status );

								if ( '' !== $from_label ) {
									$from_to = sprintf(
										'%s → %s',
										$from_label,
										$to_label
									);
								} else {
									$from_to = $to_label;
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
			</div>
		</div>

		<div class="tsrb-booking-sidebar">
			<h2><?php esc_html_e( 'Status & Notes', 'tribuna-studio-rent-booking' ); ?></h2>

			<p>
				<label for="tsrb-booking-status"><strong><?php esc_html_e( 'Status', 'tribuna-studio-rent-booking' ); ?></strong></label><br />
				<select id="tsrb-booking-status" name="status">
					<option value="pending_payment" <?php selected( $booking->status, 'pending_payment' ); ?>>
						<?php esc_html_e( 'Pending Payment', 'tribuna-studio-rent-booking' ); ?>
					</option>
					<option value="paid" <?php selected( $booking->status, 'paid' ); ?>>
						<?php esc_html_e( 'Paid / Confirmed', 'tribuna-studio-rent-booking' ); ?>
					</option>
					<option value="cancel_requested" <?php selected( $booking->status, 'cancel_requested' ); ?>>
						<?php esc_html_e( 'Cancellation Requested', 'tribuna-studio-rent-booking' ); ?>
					</option>
					<option value="cancelled" <?php selected( $booking->status, 'cancelled' ); ?>>
						<?php esc_html_e( 'Cancelled', 'tribuna-studio-rent-booking' ); ?>
					</option>
				</select>
			</p>

			<p>
				<label for="tsrb-admin-note"><strong><?php esc_html_e( 'Admin Note', 'tribuna-studio-rent-booking' ); ?></strong></label><br />
				<textarea id="tsrb-admin-note" rows="5" style="width:100%;"><?php echo esc_textarea( $booking->admin_note ); ?></textarea>
			</p>

			<p>
				<button class="button button-primary" id="tsrb-save-booking"
					data-booking-id="<?php echo esc_attr( $booking->id ); ?>">
					<?php esc_html_e( 'Save', 'tribuna-studio-rent-booking' ); ?>
				</button>
				<span class="tsrb-save-status"></span>
			</p>

			<hr />

			<h2><?php esc_html_e( 'Reschedule Booking', 'tribuna-studio-rent-booking' ); ?></h2>

			<p class="description">
				<?php
				if ( $reschedule_cutoff_hours > 0 ) {
					printf(
						esc_html__( 'Reschedule should respect minimum lead time of %d hours and cannot conflict with other bookings.', 'tribuna-studio-rent-booking' ),
						(int) $reschedule_cutoff_hours
					);
				} else {
					esc_html_e( 'You can adjust the schedule as long as the new slot is available.', 'tribuna-studio-rent-booking' );
				}
				?>
			</p>

			<div class="tsrb-admin-reschedule-wrapper"
				 data-booking-id="<?php echo esc_attr( $booking->id ); ?>"
				 data-studio-id="<?php echo esc_attr( (int) $booking->studio_id ); ?>">

				<p class="tsrb-reschedule-selected-date-info">
					<strong><?php esc_html_e( 'Selected date:', 'tribuna-studio-rent-booking' ); ?></strong>
					<span id="tsrb-reschedule-selected-date-info">
						<?php echo esc_html( $booking->date ); ?>
					</span>
				</p>

				<div id="tsrb-admin-booking-calendar"></div>

				<div class="tsrb-admin-reschedule-slots">
					<h3><?php esc_html_e( 'Available Time Slots', 'tribuna-studio-rent-booking' ); ?></h3>
					<div id="tsrb-admin-time-slots">
						<p class="tsrb-info">
							<?php esc_html_e( 'Please select a date on the calendar to see available time slots.', 'tribuna-studio-rent-booking' ); ?>
						</p>
					</div>
				</div>

				<input type="hidden"
					   id="tsrb-reschedule-date"
					   value="<?php echo esc_attr( $booking->date ); ?>" />
				<input type="hidden"
					   id="tsrb-reschedule-start"
					   value="<?php echo esc_attr( substr( $booking->start_time, 0, 5 ) ); ?>" />
				<input type="hidden"
					   id="tsrb-reschedule-end"
					   value="<?php echo esc_attr( substr( $booking->end_time, 0, 5 ) ); ?>" />

				<input type="hidden" id="tsrb-admin-slot-start" />
				<input type="hidden" id="tsrb-admin-slot-end" />
			</div>

			<p>
				<button
					type="button"
					class="button button-secondary"
					id="tsrb-admin-reschedule-booking"
					data-booking-id="<?php echo esc_attr( $booking->id ); ?>"
				>
					<?php esc_html_e( 'Save New Schedule', 'tribuna-studio-rent-booking' ); ?>
				</button>
				<span class="tsrb-reschedule-status"></span>
			</p>

			<?php if ( $allow_member_reschedule ) : ?>
				<p class="description">
					<?php esc_html_e( 'Note: Members can also request reschedule from their dashboard (according to workflow rules).', 'tribuna-studio-rent-booking' ); ?>
				</p>
			<?php endif; ?>

			<hr />

			<h2><?php esc_html_e( 'Cancellation & Refund', 'tribuna-studio-rent-booking' ); ?></h2>

			<?php if ( $is_cancelled ) : ?>
				<p class="description">
					<strong><?php esc_html_e( 'This booking is already cancelled.', 'tribuna-studio-rent-booking' ); ?></strong>
				</p>
			<?php else : ?>
				<?php if ( ! empty( $cancel_policy ) ) : ?>
					<div class="tsrb-cancel-policy-box">
						<p>
							<strong><?php esc_html_e( 'Evaluated cancellation policy for this booking', 'tribuna-studio-rent-booking' ); ?></strong>
						</p>
						<ul style="list-style: disc; margin-left: 18px;">
							<?php if ( ! empty( $cancel_policy['window_label'] ) ) : ?>
								<li>
									<?php
									printf(
										esc_html__( 'Current window: %s', 'tribuna-studio-rent-booking' ),
										esc_html( $cancel_policy['window_label'] )
									);
									?>
								</li>
							<?php endif; ?>

							<?php if ( isset( $cancel_policy['eligible_refund_type'] ) ) : ?>
								<li>
									<?php esc_html_e( 'Refund type:', 'tribuna-studio-rent-booking' ); ?>
									<strong><?php echo esc_html( $cancel_policy['eligible_refund_type'] ); ?></strong>
								</li>
							<?php endif; ?>

							<?php if ( isset( $cancel_policy['refund_percent'] ) ) : ?>
								<li>
									<?php
									printf(
										esc_html__( 'Refund percent: %d%% of paid amount.', 'tribuna-studio-rent-booking' ),
										(int) $cancel_policy['refund_percent']
									);
									?>
								</li>
							<?php endif; ?>

							<?php if ( isset( $cancel_policy['refundable_amount'] ) ) : ?>
								<li>
									<?php esc_html_e( 'Recommended refund amount:', 'tribuna-studio-rent-booking' ); ?>
									<strong><?php echo esc_html( Tribuna_Helpers::format_price( $cancel_policy['refundable_amount'] ) ); ?></strong>
								</li>
							<?php endif; ?>

							<?php if ( isset( $cancel_policy['non_refundable_amount'] ) && $cancel_policy['non_refundable_amount'] > 0 ) : ?>
								<li>
									<?php esc_html_e( 'Non-refundable amount (can be treated as fee or credit):', 'tribuna-studio-rent-booking' ); ?>
									<strong><?php echo esc_html( Tribuna_Helpers::format_price( $cancel_policy['non_refundable_amount'] ) ); ?></strong>
								</li>
							<?php endif; ?>

							<?php if ( ! empty( $cancel_policy['notes_admin'] ) ) : ?>
								<li>
									<?php esc_html_e( 'Notes for admin:', 'tribuna-studio-rent-booking' ); ?>
									<?php echo esc_html( $cancel_policy['notes_admin'] ); ?>
								</li>
							<?php endif; ?>
						</ul>
					</div>
				<?php else : ?>
					<p class="description">
						<?php esc_html_e( 'Cancellation policy evaluation is not available for this booking. You can still manually set status to Cancelled and handle refund off-system.', 'tribuna-studio-rent-booking' ); ?>
					</p>
				<?php endif; ?>

				<div class="tsrb-cancel-actions"
					 data-booking-id="<?php echo esc_attr( $booking->id ); ?>">

					<?php if ( $is_cancel_requested ) : ?>
						<p>
							<strong><?php esc_html_e( 'Customer has requested cancellation.', 'tribuna-studio-rent-booking' ); ?></strong>
						</p>

						<p>
							<label for="tsrb-cancel-admin-note">
								<?php esc_html_e( 'Cancellation / refund note (visible only in admin log)', 'tribuna-studio-rent-booking' ); ?>
							</label><br />
							<textarea id="tsrb-cancel-admin-note"
									  class="large-text"
									  rows="3"></textarea>
						</p>

						<p>
							<button type="button"
									class="button button-primary tsrb-approve-cancellation"
									data-booking-id="<?php echo esc_attr( $booking->id ); ?>">
								<?php esc_html_e( 'Approve Cancellation & Apply Refund', 'tribuna-studio-rent-booking' ); ?>
							</button>

							<button type="button"
									class="button tsrb-reject-cancellation"
									data-booking-id="<?php echo esc_attr( $booking->id ); ?>">
								<?php esc_html_e( 'Reject Cancellation Request', 'tribuna-studio-rent-booking' ); ?>
							</button>

							<span class="tsrb-cancel-status"></span>
						</p>
					<?php else : ?>
						<p class="description">
							<?php esc_html_e( 'Use the buttons below to cancel this booking on behalf of the customer and record refund manually.', 'tribuna-studio-rent-booking' ); ?>
						</p>

						<p>
							<label for="tsrb-cancel-admin-note-direct">
								<?php esc_html_e( 'Cancellation / refund note (visible only in admin log)', 'tribuna-studio-rent-booking' ); ?>
							</label><br />
							<textarea id="tsrb-cancel-admin-note-direct"
									  class="large-text"
									  rows="3"></textarea>
						</p>

						<p>
							<button type="button"
									class="button button-secondary tsrb-direct-cancel-booking"
									data-booking-id="<?php echo esc_attr( $booking->id ); ?>">
								<?php esc_html_e( 'Cancel Booking (manual refund handling)', 'tribuna-studio-rent-booking' ); ?>
							</button>
							<span class="tsrb-cancel-status"></span>
						</p>
					<?php endif; ?>

					<?php if ( $refund_full_hours_before || $refund_partial_hours_before || $refund_no_refund_inside_hours ) : ?>
						<p class="description">
							<?php
							esc_html_e( 'Reminder of global cancellation/refund rules:', 'tribuna-studio-rent-booking' );
							echo '<br />';
							if ( $refund_full_hours_before ) {
								printf(
									esc_html__( '- Full refund if cancelled at least %d hours before start time.', 'tribuna-studio-rent-booking' ),
									(int) $refund_full_hours_before
								);
								echo '<br />';
							}
							if ( $refund_partial_hours_before && $refund_partial_percent ) {
								printf(
									esc_html__( '- Partial refund (%1$d%%) if cancelled on the same day at least %2$d hours before start.', 'tribuna-studio-rent-booking' ),
									(int) $refund_partial_percent,
									(int) $refund_partial_hours_before
								);
								echo '<br />';
							}
							if ( $refund_no_refund_inside_hours ) {
								printf(
									esc_html__( '- No refund if cancelled less than %d hours before start.', 'tribuna-studio-rent-booking' ),
									(int) $refund_no_refund_inside_hours
								);
							}
							?>
						</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
