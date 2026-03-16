<?php
/**
 * AJAX handlers untuk sisi Public (frontend).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tribuna_Ajax_Public {

	/**
	 * @var Tribuna_Booking_Service
	 */
	protected $booking_service;

	/**
	 * @var Tribuna_Booking_Model
	 */
	protected $booking_model;

	/**
	 * @var Tribuna_Coupon_Model
	 */
	protected $coupon_model;

	/**
	 * @var Tribuna_Addon_Model
	 */
	protected $addon_model;

	/**
	 * @var Tribuna_Studio_Model
	 */
	protected $studio_model;

	public function __construct() {
		$this->booking_service = new Tribuna_Booking_Service();
		$this->booking_model   = new Tribuna_Booking_Model();
		$this->coupon_model    = new Tribuna_Coupon_Model();
		$this->addon_model     = new Tribuna_Addon_Model();
		$this->studio_model    = new Tribuna_Studio_Model();
	}

	/* ============= UTIL / COMMON ============= */

	/**
	 * Endpoint ringan untuk ambil nonce publik baru setelah login/daftar.
	 *
	 * Action: wp_ajax_tsrb_refresh_public_nonce, wp_ajax_nopriv_tsrb_refresh_public_nonce
	 */
	public function refresh_public_nonce() {
		$new_nonce = wp_create_nonce( 'tsrb_public_nonce' );

		wp_send_json_success(
			array(
				'nonce' => $new_nonce,
			)
		);
	}

	/* ============= AVAILABILITY & BOOKING ============= */

	/**
	 * Get availability & slots untuk suatu tanggal (per studio).
	 *
	 * Action: wp_ajax_tsrb_get_availability, wp_ajax_nopriv_tsrb_get_availability
	 */
	public function get_availability() {
		check_ajax_referer( 'tsrb_public_nonce', 'nonce' );

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

		$result = $this->booking_service->get_availability_for_date( $date, $studio_id );

		wp_send_json_success(
			array(
				'date'         => $date,
				'status'       => $result['status'],
				'slots'        => $result['slots'],
				'total_slots'  => $result['total_slots'],
				'booked_slots' => $result['booked_slots'],
			)
		);
	}

	/**
	 * Validasi kupon dari frontend.
	 *
	 * Action: wp_ajax_tsrb_validate_coupon, wp_ajax_nopriv_tsrb_validate_coupon
	 */
	public function validate_coupon() {
		check_ajax_referer( 'tsrb_public_nonce', 'nonce' );

		$code = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';

		if ( empty( $code ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Coupon code is required.', 'tribuna-studio-rent-booking' ),
				),
				400
			);
		}

		$coupon = $this->coupon_model->get_by_code( $code );
		if ( ! $coupon ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid or expired coupon.', 'tribuna-studio-rent-booking' ),
				),
				404
			);
		}

		if ( (int) $coupon->max_usage > 0 && (int) $coupon->used_count >= (int) $coupon->max_usage ) {
			wp_send_json_error(
				array(
					'message' => __( 'Coupon usage limit reached.', 'tribuna-studio-rent-booking' ),
				),
				400
			);
		}

		wp_send_json_success(
			array(
				'id'    => (int) $coupon->id,
				'code'  => $coupon->code,
				'type'  => $coupon->type,
				'value' => (float) $coupon->value,
			)
		);
	}

	/**
	 * Submit booking baru dari form frontend.
	 *
	 * Action: wp_ajax_tsrb_submit_booking, wp_ajax_nopriv_tsrb_submit_booking
	 */
	public function submit_booking() {
		check_ajax_referer( 'tsrb_public_nonce', 'nonce' );

		$data = array();

		$data['full_name'] = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
		$data['email']     = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$data['phone']     = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$data['notes']     = isset( $_POST['notes'] ) ? wp_kses_post( wp_unslash( $_POST['notes'] ) ) : '';

		$data['date']       = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$data['slot_start'] = isset( $_POST['slot_start'] ) ? sanitize_text_field( wp_unslash( $_POST['slot_start'] ) ) : '';
		$data['slot_end']   = isset( $_POST['slot_end'] ) ? sanitize_text_field( wp_unslash( $_POST['slot_end'] ) ) : '';
		$data['studio_id']  = isset( $_POST['studio_id'] ) ? (int) $_POST['studio_id'] : 0;

		$data['addons'] = isset( $_POST['addons'] ) && is_array( $_POST['addons'] )
			? array_map( 'absint', $_POST['addons'] )
			: array(); // phpcs:ignore WordPress.Security.NonceVerification

		$data['coupon_code'] = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '';

		$result = $this->booking_service->create_booking_from_request( $data );

		if ( is_wp_error( $result ) ) {
			$code    = $result->get_error_code();
			$message = $result->get_error_message();

			$status = 400;

			switch ( $code ) {
				case 'not_logged_in':
					$status = 401;
					break;
				case 'slot_unavailable':
					$status = 409;
					break;
				case 'create_failed':
					$status = 500;
					break;
				case 'lead_time_too_short':
				case 'pending_payment_exists':
				case 'active_booking_limit_reached':
					$status = 400;
					break;
			}

			wp_send_json_error(
				array(
					'message' => $message,
					'code'    => $code,
				),
				$status
			);
		}

		$booking_id = (int) $result;
		$booking    = $this->booking_model->get( $booking_id );

		if ( ! $booking ) {
			wp_send_json_success(
				array(
					'message'    => __( 'Booking submitted. Please wait for admin confirmation.', 'tribuna-studio-rent-booking' ),
					'booking_id' => $booking_id,
				)
			);
		}

		wp_send_json_success(
			array(
				'message'     => __( 'Booking submitted. Please wait for admin confirmation.', 'tribuna-studio-rent-booking' ),
				'booking_id'  => $booking_id,
				'google_cal'  => $booking->google_calendar_url,
				'final_price' => (float) $booking->final_price,
				'total_price' => (float) $booking->total_price,
				'discount'    => (float) $booking->discount_amount,
				'duration'    => (int) $booking->duration,
			)
		);
	}

	/**
	 * Endpoint AJAX untuk generate/return HTML invoice (print friendly) berdasarkan booking_id.
	 *
	 * Action: wp_ajax_tsrb_download_invoice_html
	 *
	 * Dipakai oleh tombol "Download Invoice" di frontend (bentuk HTML yang bisa di-print).
	 */
	public function download_invoice_html() {
		check_ajax_referer( 'tsrb_public_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message' => __( 'You must be logged in to download invoice.', 'tribuna-studio-rent-booking' ),
				),
				401
			);
		}

		$current_user_id = get_current_user_id();
		$booking_id      = isset( $_GET['booking_id'] ) ? (int) $_GET['booking_id'] : 0;

		if ( $booking_id <= 0 ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid booking ID.', 'tribuna-studio-rent-booking' ),
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

		// Hanya pemilik booking atau admin yang boleh ambil invoice.
		if ( (int) $booking->user_id !== $current_user_id && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You are not allowed to access this invoice.', 'tribuna-studio-rent-booking' ),
				),
				403
			);
		}

		// Ambil studio & addons untuk melengkapi invoice.
		$studio = null;
		if ( ! empty( $booking->studio_id ) && $this->studio_model instanceof Tribuna_Studio_Model ) {
			$studio = $this->studio_model->get( (int) $booking->studio_id );
		}

		$addons = array();
		if ( ! empty( $booking->addons ) && $this->addon_model instanceof Tribuna_Addon_Model ) {
			// Asumsi: booking->addons berisi array ID atau string CSV ID.
			$addon_ids = is_array( $booking->addons )
				? array_map( 'absint', $booking->addons )
				: array_map( 'absint', explode( ',', (string) $booking->addons ) );
			$addon_ids = array_filter( $addon_ids );
			if ( ! empty( $addon_ids ) && method_exists( $this->addon_model, 'get_by_ids' ) ) {
				$addons = $this->addon_model->get_by_ids( $addon_ids );
			}
		}

		$site_name = get_bloginfo( 'name' );
		$currency  = function_exists( 'Tribuna_Helpers::get_currency' )
			? Tribuna_Helpers::get_currency()
			: 'IDR';

		// Format helper sederhana.
		$format_price = function( $amount ) use ( $currency ) {
			$amount = (float) $amount;
			return sprintf( '%s %s', $currency, number_format( $amount, 0, ',', '.' ) );
		};

		$date_display = '';
		if ( ! empty( $booking->date ) ) {
			$timestamp = strtotime( $booking->date );
			if ( $timestamp ) {
				$date_display = date_i18n( 'l, d-m-Y', $timestamp );
			} else {
				$date_display = $booking->date;
			}
		}

		// Bangun HTML invoice sederhana (print-friendly).
		ob_start();
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<title><?php echo esc_html( sprintf( __( 'Invoice Booking #%d', 'tribuna-studio-rent-booking' ), $booking_id ) ); ?></title>
			<style>
				body {
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
					font-size: 14px;
					color: #222;
					margin: 0;
					padding: 20px;
					background: #f5f5f5;
				}
				.tsrb-invoice-wrapper {
					max-width: 800px;
					margin: 0 auto;
					background: #fff;
					border-radius: 6px;
					padding: 24px 28px;
					box-shadow: 0 2px 6px rgba(0,0,0,0.06);
				}
				.tsrb-invoice-header {
					display: flex;
					justify-content: space-between;
					align-items: flex-start;
					margin-bottom: 16px;
					border-bottom: 1px solid #e3e3e3;
					padding-bottom: 12px;
				}
				.tsrb-invoice-title {
					font-size: 18px;
					font-weight: 600;
					margin-bottom: 4px;
				}
				.tsrb-invoice-meta div {
					margin-bottom: 2px;
				}
				.tsrb-invoice-meta-label {
					font-weight: 600;
				}
				table.tsrb-invoice-table {
					width: 100%;
					border-collapse: collapse;
					margin-top: 16px;
					margin-bottom: 16px;
				}
				.tsrb-invoice-table th,
				.tsrb-invoice-table td {
					border: 1px solid #e0e0e0;
					padding: 8px 10px;
					text-align: left;
					vertical-align: top;
				}
				.tsrb-invoice-table thead {
					background: #fafafa;
				}
				.tsrb-invoice-row-total td {
					font-weight: 600;
				}
				.tsrb-invoice-footer {
					margin-top: 24px;
					font-size: 12px;
					color: #555;
				}
				.tsrb-print-actions {
					text-align: right;
					margin-bottom: 12px;
				}
				.tsrb-print-btn {
					display: inline-block;
					padding: 6px 12px;
					border-radius: 4px;
					border: 1px solid #0073aa;
					background: #0073aa;
					color: #fff;
					text-decoration: none;
					font-size: 13px;
					cursor: pointer;
				}
				@media print {
					.tsrb-print-actions {
						display: none;
					}
					body {
						background: #fff;
					}
					.tsrb-invoice-wrapper {
						box-shadow: none;
						border-radius: 0;
					}
				}
			</style>
		</head>
		<body>
			<div class="tsrb-print-actions">
				<button class="tsrb-print-btn" onclick="window.print();">
					<?php esc_html_e( 'Print / Save as PDF', 'tribuna-studio-rent-booking' ); ?>
				</button>
			</div>

			<div class="tsrb-invoice-wrapper">
				<div class="tsrb-invoice-header">
					<div>
						<div class="tsrb-invoice-title">
							<?php echo esc_html( $site_name ); ?>
						</div>
						<div><?php esc_html_e( 'Booking Invoice', 'tribuna-studio-rent-booking' ); ?></div>
					</div>
					<div class="tsrb-invoice-meta">
						<div>
							<span class="tsrb-invoice-meta-label"><?php esc_html_e( 'Booking ID:', 'tribuna-studio-rent-booking' ); ?></span>
							<span>#<?php echo esc_html( $booking_id ); ?></span>
						</div>
						<div>
							<span class="tsrb-invoice-meta-label"><?php esc_html_e( 'Name:', 'tribuna-studio-rent-booking' ); ?></span>
							<span><?php echo esc_html( $booking->user_name ); ?></span>
						</div>
						<div>
							<span class="tsrb-invoice-meta-label"><?php esc_html_e( 'Email:', 'tribuna-studio-rent-booking' ); ?></span>
							<span><?php echo esc_html( $booking->email ); ?></span>
						</div>
						<div>
							<span class="tsrb-invoice-meta-label"><?php esc_html_e( 'Phone:', 'tribuna-studio-rent-booking' ); ?></span>
							<span><?php echo esc_html( $booking->phone ); ?></span>
						</div>
					</div>
				</div>

				<table class="tsrb-invoice-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Item', 'tribuna-studio-rent-booking' ); ?></th>
							<th><?php esc_html_e( 'Details', 'tribuna-studio-rent-booking' ); ?></th>
							<th><?php esc_html_e( 'Sub-total', 'tribuna-studio-rent-booking' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>
								<?php
								printf(
									/* translators: %s: studio name */
									esc_html__( 'Studio Rent: %s', 'tribuna-studio-rent-booking' ),
									esc_html( $studio ? $studio->name : __( 'Studio', 'tribuna-studio-rent-booking' ) )
								);
								?>
							</td>
							<td>
								<?php if ( $date_display ) : ?>
									<?php esc_html_e( 'Date:', 'tribuna-studio-rent-booking' ); ?>
									<?php echo ' ' . esc_html( $date_display ); ?><br>
								<?php endif; ?>
								<?php esc_html_e( 'Time:', 'tribuna-studio-rent-booking' ); ?>
								<?php echo ' ' . esc_html( $booking->start_time . ' - ' . $booking->end_time ); ?><br>
								<?php esc_html_e( 'Duration:', 'tribuna-studio-rent-booking' ); ?>
								<?php echo ' ' . esc_html( (int) $booking->duration ) . ' ' . esc_html__( 'hour(s)', 'tribuna-studio-rent-booking' ); ?>
							</td>
							<td>
								<?php echo esc_html( $format_price( $booking->total_price ) ); ?>
							</td>
						</tr>

						<tr>
							<td><?php esc_html_e( 'Add-ons', 'tribuna-studio-rent-booking' ); ?></td>
							<td>
								<?php
								if ( ! empty( $addons ) ) {
									foreach ( $addons as $index => $addon ) {
										echo esc_html( $addon->name );
										if ( $index < count( $addons ) - 1 ) {
											echo '<br>';
										}
									}
								} else {
									esc_html_e( 'No add-ons', 'tribuna-studio-rent-booking' );
								}
								?>
							</td>
							<td>
								<?php echo esc_html( $format_price( $booking->addons_price ?? 0 ) ); ?>
							</td>
						</tr>

						<tr>
							<td><?php esc_html_e( 'Coupon / Discount', 'tribuna-studio-rent-booking' ); ?></td>
							<td>
								<?php
								if ( ! empty( $booking->coupon_code ) ) {
									printf(
										/* translators: 1: coupon code */
										esc_html__( 'Coupon: %1$s', 'tribuna-studio-rent-booking' ),
										esc_html( $booking->coupon_code )
									);
								} else {
									esc_html_e( 'No coupon applied', 'tribuna-studio-rent-booking' );
								}
								?>
							</td>
							<td>
								<?php
								$discount = (float) $booking->discount_amount;
								echo $discount > 0 ? '- ' . esc_html( $format_price( $discount ) ) : esc_html( $format_price( 0 ) );
								?>
							</td>
						</tr>

						<tr class="tsrb-invoice-row-total">
							<td><strong><?php esc_html_e( 'Total', 'tribuna-studio-rent-booking' ); ?></strong></td>
							<td></td>
							<td><strong><?php echo esc_html( $format_price( $booking->final_price ) ); ?></strong></td>
						</tr>
					</tbody>
				</table>

				<div class="tsrb-invoice-footer">
					<?php esc_html_e( 'Thank you for your booking.', 'tribuna-studio-rent-booking' ); ?>
				</div>
			</div>
		</body>
		</html>
		<?php
		$html = ob_get_clean();

		// Kembalikan HTML langsung sebagai response penuh (bukan JSON) untuk dibuka di tab baru.
		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Reschedule booking oleh member dari dashboard user.
	 *
	 * Action: wp_ajax_tsrb_reschedule_booking
	 */
	public function reschedule_booking() {
		check_ajax_referer( 'tsrb_public_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message' => __( 'You must be logged in to reschedule a booking.', 'tribuna-studio-rent-booking' ),
				),
				401
			);
		}

		$current_user_id = get_current_user_id();

		$booking_id = isset( $_POST['booking_id'] ) ? (int) $_POST['booking_id'] : 0;
		$new_date   = isset( $_POST['new_date'] ) ? sanitize_text_field( wp_unslash( $_POST['new_date'] ) ) : '';
		$new_start  = isset( $_POST['new_start'] ) ? sanitize_text_field( wp_unslash( $_POST['new_start'] ) ) : '';
		$new_end    = isset( $_POST['new_end'] ) ? sanitize_text_field( wp_unslash( $_POST['new_end'] ) ) : '';

		if ( $booking_id <= 0 || empty( $new_date ) || empty( $new_start ) || empty( $new_end ) ) {
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

		if ( (int) $booking->user_id !== $current_user_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'You are not allowed to modify this booking.', 'tribuna-studio-rent-booking' ),
				),
				403
			);
		}

		// Ambil settings dari tsrbsettings (baru), fallback tsrb_settings (legacy).
		$new_settings = get_option( 'tsrbsettings', null );
		if ( is_array( $new_settings ) ) {
			$settings = $new_settings;
		} else {
			$settings = get_option( 'tsrb_settings', array() );
		}

		$workflow = isset( $settings['workflow'] ) && is_array( $settings['workflow'] ) ? $settings['workflow'] : array();

		$allow_member_reschedule = ! empty( $workflow['allow_member_reschedule'] );
		$reschedule_admin_only   = ! empty( $workflow['reschedule_admin_only'] );

		if ( ! $allow_member_reschedule || $reschedule_admin_only ) {
			wp_send_json_error(
				array(
					'message' => __( 'Rescheduling is not allowed for members.', 'tribuna-studio-rent-booking' ),
				),
				403
			);
		}

		if ( ! method_exists( $this->booking_service, 'validate_reschedule_request' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Reschedule feature is not available.', 'tribuna-studio-rent-booking' ),
				),
				500
			);
		}

		$validation = $this->booking_service->validate_reschedule_request(
			$booking,
			array(
				'new_date'  => $new_date,
				'new_start' => $new_start,
				'new_end'   => $new_end,
			),
			$settings,
			$current_user_id
		);

		if ( is_wp_error( $validation ) ) {
			$code    = $validation->get_error_code();
			$message = $validation->get_error_message();

			$status = 400;
			if ( 'not_allowed' === $code || 'forbidden' === $code ) {
				$status = 403;
			} elseif ( 'slot_unavailable' === $code ) {
				$status = 409;
			}

			wp_send_json_error(
				array(
					'message' => $message,
					'code'    => $code,
				),
				$status
			);
		}

		if ( ! method_exists( $this->booking_model, 'reschedule_booking' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Reschedule method is not implemented.', 'tribuna-studio-rent-booking' ),
				),
				500
			);
		}

		$success = $this->booking_model->reschedule_booking(
			$booking_id,
			$new_date,
			$new_start,
			$new_end,
			$current_user_id,
			$settings
		);

		if ( ! $success ) {
			wp_send_json_error(
				array(
					'message' => __( 'Failed to reschedule booking.', 'tribuna-studio-rent-booking' ),
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
				'start_time' => $updated_booking ? $updated_booking->start_time : $new_start,
				'end_time'   => $updated_booking ? $updated_booking->end_time : $new_end,
				'status'     => $updated_booking ? $updated_booking->status : $booking->status,
			)
		);
	}

	/**
	 * Pengajuan pembatalan booking oleh member dari dashboard.
	 *
	 * Action: wp_ajax_tsrb_member_request_cancellation
	 */
	public function member_request_cancellation() {
		check_ajax_referer( 'tsrb_public_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message' => __( 'You must be logged in to request a cancellation.', 'tribuna-studio-rent-booking' ),
				),
				401
			);
		}

		$current_user_id = get_current_user_id();
		$booking_id      = isset( $_POST['booking_id'] ) ? (int) $_POST['booking_id'] : 0;
		$note            = isset( $_POST['note'] ) ? wp_kses_post( wp_unslash( $_POST['note'] ) ) : '';

		if ( $booking_id <= 0 ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid booking ID.', 'tribuna-studio-rent-booking' ),
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

		if ( (int) $booking->user_id !== $current_user_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'You are not allowed to cancel this booking.', 'tribuna-studio-rent-booking' ),
				),
				403
			);
		}

		// Ambil settings dari tsrbsettings (baru), fallback tsrb_settings (legacy).
		$new_settings = get_option( 'tsrbsettings', null );
		if ( is_array( $new_settings ) ) {
			$settings = $new_settings;
		} else {
			$settings = get_option( 'tsrb_settings', array() );
		}

		$workflow = isset( $settings['workflow'] ) && is_array( $settings['workflow'] )
			? $settings['workflow']
			: array();

		$allow_member_cancel = ! empty( $workflow['allow_member_cancel'] );

		if ( ! $allow_member_cancel ) {
			wp_send_json_error(
				array(
					'message' => __( 'Cancellation requests by members are disabled. Please contact admin.', 'tribuna-studio-rent-booking' ),
				),
				403
			);
		}

		// Gunakan service helper khusus agar konsisten dengan policy baru.
		if ( ! method_exists( $this->booking_service, 'handle_member_cancel_request' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Cancellation feature is not available.', 'tribuna-studio-rent-booking' ),
				),
				500
			);
		}

		// Tambah catatan manual jika ada.
		if ( '' !== $note ) {
			$this->booking_model->update(
				$booking_id,
				array(
					'cancel_reason' => $note,
				)
			);
		}

		$result = $this->booking_service->handle_member_cancel_request( $booking_id, $current_user_id );

		if ( is_wp_error( $result ) ) {
			$code    = $result->get_error_code();
			$message = $result->get_error_message();

			$status = 400;
			if ( 'forbidden' === $code ) {
				$status = 403;
			} elseif ( 'not_found' === $code ) {
				$status = 404;
			}

			wp_send_json_error(
				array(
					'message' => $message,
					'code'    => $code,
				),
				$status
			);
		}

		// Eval policy hanya untuk kasih info ke user (refund_label), bukan untuk langsung apply.
		$evaluation = $this->booking_service->evaluate_cancellation_policy( $booking );

		$refund_label = __( 'no refund', 'tribuna-studio-rent-booking' );
		if ( isset( $evaluation['refund_type'] ) && 'full' === $evaluation['refund_type'] ) {
			$refund_label = __( 'full refund', 'tribuna-studio-rent-booking' );
		} elseif ( isset( $evaluation['refund_type'] ) && 'partial' === $evaluation['refund_type'] ) {
			$percent      = isset( $evaluation['refund_percent'] ) ? (int) $evaluation['refund_percent'] : 0;
			$refund_label = sprintf(
				/* translators: %d: percent */
				__( 'partial refund (%d%%)', 'tribuna-studio-rent-booking' ),
				$percent
			);
		}

		$message = __( 'Your cancellation request has been submitted and will be reviewed by admin.', 'tribuna-studio-rent-booking' );
		if ( ! empty( $evaluation['allowed'] ) && isset( $evaluation['refund_type'] ) ) {
			$message .= ' ' . sprintf(
				/* translators: %s: refund label */
				__( 'According to the current policy, this booking is eligible for %s (final decision by admin).', 'tribuna-studio-rent-booking' ),
				$refund_label
			);
		}

		wp_send_json_success(
			array(
				'message'        => $message,
				'booking_id'     => $booking_id,
				'refund_type'    => isset( $evaluation['refund_type'] ) ? $evaluation['refund_type'] : 'none',
				'refund_percent' => isset( $evaluation['refund_percent'] ) ? (int) $evaluation['refund_percent'] : 0,
			)
		);
	}

	/* ============= USER DASHBOARD & PROFILE ============= */

	/**
	 * Get user bookings (dashboard) untuk popup Riwayat Booking.
	 *
	 * Action: wp_ajax_tsrb_get_user_bookings
	 */
	public function get_user_bookings() {
		check_ajax_referer( 'tsrb_public_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message' => __( 'You must be logged in to view your bookings.', 'tribuna-studio-rent-booking' ),
				),
				401
			);
		}

		$current_user_id = get_current_user_id();

		$all_bookings = $this->booking_model->get_by_user_id( $current_user_id );

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

		// Ambil settings dari tsrbsettings (baru), fallback tsrb_settings (legacy).
		$new_settings = get_option( 'tsrbsettings', null );
		if ( is_array( $new_settings ) ) {
			$settings = $new_settings;
		} else {
			$settings = get_option( 'tsrb_settings', array() );
		}

		$workflow = isset( $settings['workflow'] ) && is_array( $settings['workflow'] )
			? $settings['workflow']
			: array();

		$allow_member_reschedule = ! empty( $workflow['allow_member_reschedule'] );
		$reschedule_admin_only   = ! empty( $workflow['reschedule_admin_only'] );
		$allow_member_cancel     = ! empty( $workflow['allow_member_cancel'] );

		$member_reschedule_enabled = ( $allow_member_reschedule && ! $reschedule_admin_only );
		$member_cancel_enabled     = $allow_member_cancel;

		$payment_window_seconds = 0;
		if ( method_exists( $this->booking_service, 'get_payment_window_seconds' ) ) {
			$payment_window_seconds = (int) $this->booking_service->get_payment_window_seconds();
		}

		$server_now = current_time( 'timestamp' );

		$studio_model = $this->studio_model instanceof Tribuna_Studio_Model
			? $this->studio_model
			: new Tribuna_Studio_Model();

		ob_start();
		include TSRB_PLUGIN_DIR . 'public/views/user-dashboard.php';
		$html = ob_get_clean();

		wp_send_json_success(
			array(
				'html' => $html,
			)
		);
	}

	/**
	 * Get user profile untuk popup Profil Saya.
	 *
	 * Action: wp_ajax_tsrb_get_user_profile
	 */
	public function get_user_profile() {
		check_ajax_referer( 'tsrb_public_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message' => __( 'You must be logged in to view your profile.', 'tribuna-studio-rent-booking' ),
				),
				401
			);
		}

		$current_user = wp_get_current_user();
		$whatsapp     = get_user_meta( $current_user->ID, 'tsrb_whatsapp', true );

		$booking_page_url = get_permalink();
		$logout_url       = add_query_arg( 'tsrb_logout', '1', $booking_page_url );

		$username   = $current_user->user_login;
		$user_id    = $current_user->ID;
		$registered = $current_user->user_registered;
		$last_login = get_user_meta( $current_user->ID, 'last_login', true );

		$total_bookings  = 0;
		$active_bookings = 0;

		if ( $this->booking_model instanceof Tribuna_Booking_Model ) {
			if ( method_exists( $this->booking_model, 'count_by_user' ) ) {
				$total_bookings = (int) $this->booking_model->count_by_user( $current_user->ID );
			}
			if ( method_exists( $this->booking_model, 'count_active_by_user' ) ) {
				$active_bookings = (int) $this->booking_model->count_active_by_user( $current_user->ID );
			}
		}

		// Ambil settings dari tsrbsettings (baru), fallback tsrb_settings (legacy).
		$new_settings = get_option( 'tsrbsettings', null );
		if ( is_array( $new_settings ) ) {
			$settings = $new_settings;
		} else {
			$settings = get_option( 'tsrb_settings', array() );
		}

		$admin_whatsapp = isset( $settings['admin_whatsapp_number'] ) ? trim( $settings['admin_whatsapp_number'] ) : '';
		$integrations   = isset( $settings['integrations'] ) && is_array( $settings['integrations'] )
			? $settings['integrations']
			: array();
		$default_msg    = isset( $integrations['whatsapp_default_message'] )
			? $integrations['whatsapp_default_message']
			: '';

		if ( empty( $default_msg ) ) {
			$default_msg = __( 'Hi, I would like to ask about my booking.', 'tribuna-studio-rent-booking' );
		}

		$customer_name = $current_user->display_name;
		$site_name     = get_bloginfo( 'name' );

		$wa_text = str_replace(
			array( '{customername}', '{sitename}' ),
			array( $customer_name, $site_name ),
			$default_msg
		);

		$whatsapp_link = '';
		if ( ! empty( $admin_whatsapp ) ) {
			$whatsapp_link = sprintf(
				'https://wa.me/%1$s?text=%2$s',
				rawurlencode( $admin_whatsapp ),
				rawurlencode( $wa_text )
			);
		}

		$updated    = false;
		$pw_changed = false;
		$error_msg  = '';

		ob_start();
		include TSRB_PLUGIN_DIR . 'public/views/user-profile.php';
		$html = ob_get_clean();

		wp_send_json_success(
			array(
				'html' => $html,
			)
		);
	}

	/**
	 * Update user profile via AJAX (modal Profil Saya).
	 *
	 * Action: wp_ajax_tsrb_update_user_profile
	 */
	public function update_user_profile() {
		check_ajax_referer( 'tsrb_public_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message' => __( 'You must be logged in to update your profile.', 'tribuna-studio-rent-booking' ),
				),
				401
			);
		}

		$user_id   = get_current_user_id();
		$full_name = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
		$email     = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$whatsapp  = isset( $_POST['whatsapp'] ) ? sanitize_text_field( wp_unslash( $_POST['whatsapp'] ) ) : '';

		if ( empty( $full_name ) || empty( $email ) || ! is_email( $email ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please provide a valid name and email.', 'tribuna-studio-rent-booking' ),
				),
				400
			);
		}

		// Cek email unik (jika diubah).
		$existing = get_user_by( 'email', $email );
		if ( $existing && (int) $existing->ID !== (int) $user_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'This email is already used by another account.', 'tribuna-studio-rent-booking' ),
				),
				400
			);
		}

		$update_args = array(
			'ID'           => $user_id,
			'display_name' => $full_name,
			'user_email'   => $email,
		);

		$result = wp_update_user( $update_args );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Failed to update profile.', 'tribuna-studio-rent-booking' ),
				),
				500
			);
		}

		// Sinkronisasi first_name dan last_name dengan Full Name.
		$first_name = '';
		$last_name  = '';

		if ( $full_name ) {
			$parts = preg_split( '/\s+/', trim( $full_name ) );
			if ( ! empty( $parts ) ) {
				$first_name = array_shift( $parts );
				$last_name  = implode( ' ', $parts );
			}
		}

		if ( $first_name ) {
			update_user_meta( $user_id, 'first_name', $first_name );
		}
		if ( $last_name ) {
			update_user_meta( $user_id, 'last_name', $last_name );
		}

		update_user_meta( $user_id, 'tsrb_whatsapp', $whatsapp );

		wp_send_json_success(
			array(
				'message' => __( 'Profile updated successfully.', 'tribuna-studio-rent-booking' ),
			)
		);
	}

	/**
	 * Change user password via AJAX (modal Profil Saya).
	 *
	 * Action: wp_ajax_tsrb_change_password
	 */
	public function change_user_password() {
		check_ajax_referer( 'tsrb_public_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message' => __( 'You must be logged in to change your password.', 'tribuna-studio-rent-booking' ),
				),
				401
			);
		}

		$user_id          = get_current_user_id();
		$current_password = isset( $_POST['current_password'] ) ? (string) wp_unslash( $_POST['current_password'] ) : '';
		$new_password     = isset( $_POST['new_password'] ) ? (string) wp_unslash( $_POST['new_password'] ) : '';
		$new_confirm      = isset( $_POST['new_password_confirm'] ) ? (string) wp_unslash( $_POST['new_password_confirm'] ) : '';

		if ( empty( $current_password ) || empty( $new_password ) || empty( $new_confirm ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please fill in all password fields.', 'tribuna-studio-rent-booking' ),
				),
				400
			);
		}

		if ( strlen( $new_password ) < 8 ) {
			wp_send_json_error(
				array(
					'message' => __( 'New password must be at least 8 characters.', 'tribuna-studio-rent-booking' ),
				),
				400
			);
		}

		if ( $new_password !== $new_confirm ) {
			wp_send_json_error(
				array(
					'message' => __( 'Password confirmation does not match.', 'tribuna-studio-rent-booking' ),
				),
				400
			);
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			wp_send_json_error(
				array(
					'message' => __( 'User not found.', 'tribuna-studio-rent-booking' ),
				),
				404
			);
		}

		if ( ! wp_check_password( $current_password, $user->user_pass, $user_id ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Current password is incorrect.', 'tribuna-studio-rent-booking' ),
				),
				400
			);
		}

		wp_set_password( $new_password, $user_id );

		wp_send_json_success(
			array(
				'message' => __( 'Password changed successfully.', 'tribuna-studio-rent-booking' ),
			)
		);
	}

	/* ============= AUTH (REGISTER & LOGIN) ============= */

	/**
	 * Registrasi user via AJAX (modal).
	 */
	public function register_user() {
		check_ajax_referer( 'tsrb_public_nonce', 'nonce' );

		if ( is_user_logged_in() ) {
			$current_user = wp_get_current_user();
			$phone_meta   = $current_user->ID ? get_user_meta( $current_user->ID, 'tsrb_whatsapp', true ) : '';
			wp_send_json_error(
				array(
					'message' => __( 'You are already logged in.', 'tribuna-studio-rent-booking' ),
					'user'    => array(
						'id'           => $current_user->ID,
						'display_name' => $current_user->display_name,
						'email'        => $current_user->user_email,
						'phone'        => $phone_meta,
					),
					'code'    => 'already_logged_in',
				),
				400
			);
		}

		$username         = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ) ) : '';
		$full_name        = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
		$email            = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone            = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$password         = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$password_confirm = isset( $_POST['password_confirm'] ) ? (string) wp_unslash( $_POST['password_confirm'] ) : '';

		if ( empty( $username ) || empty( $full_name ) || empty( $email ) || empty( $password ) || empty( $password_confirm ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Username, full name, email, and password are required.', 'tribuna-studio-rent-booking' ),
					'code'    => 'missing_fields',
				),
				400
			);
		}

		if ( ! is_email( $email ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid email address.', 'tribuna-studio-rent-booking' ),
					'code'    => 'invalid_email',
				),
				400
			);
		}

		if ( username_exists( $username ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'This username is already taken.', 'tribuna-studio-rent-booking' ),
					'code'    => 'username_exists',
				),
				400
			);
		}

		if ( email_exists( $email ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'This email is already registered.', 'tribuna-studio-rent-booking' ),
					'code'    => 'email_exists',
				),
				400
			);
		}

		if ( strlen( $password ) < 8 ) {
			wp_send_json_error(
				array(
					'message' => __( 'Password must be at least 8 characters.', 'tribuna-studio-rent-booking' ),
					'code'    => 'password_too_short',
				),
				400
			);
		}

		if ( $password !== $password_confirm ) {
			wp_send_json_error(
				array(
					'message' => __( 'Password confirmation does not match.', 'tribuna-studio-rent-booking' ),
					'code'    => 'password_mismatch',
				),
				400
			);
		}

		$user_role = 'tribuna_member';

		$user_id = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_email'   => $email,
				'user_pass'    => $password,
				'display_name' => $full_name,
				'role'         => $user_role,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			$error_code = $user_id->get_error_code();

			$message = __( 'Failed to create user account.', 'tribuna-studio-rent-booking' );
			$code    = 'registration_failed';

			if ( 'existing_user_login' === $error_code ) {
				$message = __( 'This username is already taken.', 'tribuna-studio-rent-booking' );
				$code    = 'username_exists';
			} elseif ( 'existing_user_email' === $error_code ) {
				$message = __( 'This email is already registered.', 'tribuna-studio-rent-booking' );
				$code    = 'email_exists';
			}

			wp_send_json_error(
				array(
					'message' => $message,
					'code'    => $code,
				),
				400
			);
		}

		// Sinkron first_name & last_name dari full_name.
		$first_name = '';
		$last_name  = '';

		if ( $full_name ) {
			$parts = preg_split( '/\s+/', trim( $full_name ) );
			if ( ! empty( $parts ) ) {
				$first_name = array_shift( $parts );
				$last_name  = implode( ' ', $parts );
			}
		}

		if ( $first_name ) {
			update_user_meta( $user_id, 'first_name', $first_name );
		}
		if ( $last_name ) {
			update_user_meta( $user_id, 'last_name', $last_name );
		}

		if ( $phone ) {
			update_user_meta( $user_id, 'tsrb_whatsapp', $phone );
		}

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id );

		$user_obj  = get_user_by( 'id', $user_id );
		$user_data = array(
			'id'           => $user_obj ? $user_obj->ID : $user_id,
			'display_name' => $user_obj ? $user_obj->display_name : $full_name,
			'email'        => $user_obj ? $user_obj->user_email : $email,
			'phone'        => $phone ? $phone : get_user_meta( $user_id, 'tsrb_whatsapp', true ),
		);

		wp_send_json_success(
			array(
				'message' => __( 'Registration successful. You are now logged in.', 'tribuna-studio-rent-booking' ),
				'user'    => $user_data,
			)
		);
	}

	/**
	 * Login user via AJAX (modal).
	 */
	public function login_user() {
		check_ajax_referer( 'tsrb_public_nonce', 'nonce' );

		if ( is_user_logged_in() ) {
			$current_user = wp_get_current_user();
			$phone_meta   = $current_user->ID ? get_user_meta( $current_user->ID, 'tsrb_whatsapp', true ) : '';
			wp_send_json_success(
				array(
					'message' => __( 'You are already logged in.', 'tribuna-studio-rent-booking' ),
					'user'    => array(
						'id'           => $current_user->ID,
						'display_name' => $current_user->display_name,
						'email'        => $current_user->user_email,
						'phone'        => $phone_meta,
					),
				)
			);
		}

		$user_login    = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '';
		$user_password = isset( $_POST['pwd'] ) ? (string) $_POST['pwd'] : '';
		$remember_me   = isset( $_POST['remember_me'] ) ? (int) $_POST['remember_me'] : 0;

		if ( empty( $user_login ) || empty( $user_password ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Username/email and password are required.', 'tribuna-studio-rent-booking' ),
					'code'    => 'missing_fields',
				),
				400
			);
		}

		/*
		 * Dukungan login menggunakan username ATAU email:
		 * - Jika input mengandung '@' → treat sebagai email, cari user terlebih dahulu.
		 * - Jika email tidak ditemukan → error user_not_found dengan pesan khusus.
		 * - Jika ditemukan → pakai user_login sebenarnya untuk wp_signon.
		 */
		$user_obj       = null;
		$login_for_auth = $user_login;

		if ( false !== strpos( $user_login, '@' ) ) {
			$user_obj = get_user_by( 'email', $user_login );
			if ( ! $user_obj ) {
				wp_send_json_error(
					array(
						'message' => __( 'Username atau email tidak terdaftar.', 'tribuna-studio-rent-booking' ),
						'code'    => 'user_not_found',
					),
					404
				);
			}

			$login_for_auth = $user_obj->user_login;
		} else {
			$user_obj = get_user_by( 'login', $user_login );
			if ( ! $user_obj ) {
				wp_send_json_error(
					array(
						'message' => __( 'Username atau email tidak terdaftar.', 'tribuna-studio-rent-booking' ),
						'code'    => 'user_not_found',
					),
					404
				);
			}
		}

		// Cek password manual supaya bisa beri pesan khusus.
		if ( ! wp_check_password( $user_password, $user_obj->user_pass, $user_obj->ID ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Password yang Anda masukkan salah.', 'tribuna-studio-rent-booking' ),
					'code'    => 'incorrect_password',
				),
				401
			);
		}

		$creds = array(
			'user_login'    => $login_for_auth,
			'user_password' => $user_password,
			'remember'      => ( 1 === $remember_me ),
		);

		$user = wp_signon( $creds, is_ssl() );
		if ( is_wp_error( $user ) ) {
			// Fallback error umum jika ada masalah lain (mis. konfigurasi).
			wp_send_json_error(
				array(
					'message' => __( 'Gagal login. Silakan coba lagi.', 'tribuna-studio-rent-booking' ),
					'code'    => 'login_failed',
				),
				500
			);
		}

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, 1 === $remember_me );

		$phone_meta = get_user_meta( $user->ID, 'tsrb_whatsapp', true );

		wp_send_json_success(
			array(
				'message' => __( 'Login successful.', 'tribuna-studio-rent-booking' ),
				'user'    => array(
					'id'           => $user->ID,
					'display_name' => $user->display_name,
					'email'        => $user->user_email,
					'phone'        => $phone_meta,
				),
			)
		);
	}
}
