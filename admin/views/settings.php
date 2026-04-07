<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page.
 *
 * Sekarang menggunakan satu option utama `tsrb_settings`.
 * Semua field name mengarah ke `tsrb_settings[...]` agar tersimpan via Settings API.
 */

// Ambil settings konsisten lewat helper (satu sumber utama).
$settings = Tribuna_Helpers::get_settings();

// Normalisasi struktur settings.
$currency        = isset( $settings['currency'] ) ? $settings['currency'] : 'IDR';
$admin_email     = isset( $settings['admin_email'] ) ? $settings['admin_email'] : get_option( 'admin_email' );
$qr_image_id     = isset( $settings['payment_qr_image_id'] ) ? (int) $settings['payment_qr_image_id'] : 0;
$admin_whatsapp  = isset( $settings['admin_whatsapp_number'] ) ? $settings['admin_whatsapp_number'] : '';
$operating_hours = isset( $settings['operating_hours'] ) && is_array( $settings['operating_hours'] ) ? $settings['operating_hours'] : array();
$blocked_dates   = isset( $settings['blocked_dates'] ) && is_array( $settings['blocked_dates'] ) ? $settings['blocked_dates'] : array();

// Email templates.
$emails = isset( $settings['emails'] ) && is_array( $settings['emails'] ) ? $settings['emails'] : array();

$email_defaults = array(
	'customer_new_subject'        => __( 'Your booking request has been received', 'tribuna-studio-rent-booking' ),
	'customer_new_body'           => __( 'Hi {customer_name},<br><br>Thank you for your booking request for {studio_name} on {booking_date} at {start_time}.<br>Total: {total}<br>Status: {status}<br><br>Regards,<br>{site_name}', 'tribuna-studio-rent-booking' ),
	'customer_paid_subject'       => __( 'Your booking is confirmed', 'tribuna-studio-rent-booking' ),
	'customer_paid_body'          => __( 'Hi {customer_name},<br><br>Your booking is now confirmed.<br>Studio: {studio_name}<br>Date: {booking_date}<br>Time: {start_time} - {end_time}<br>Total: {total}<br><br>We look forward to seeing you.<br>{site_name}', 'tribuna-studio-rent-booking' ),
	'customer_cancel_subject'     => __( 'Your booking has been cancelled', 'tribuna-studio-rent-booking' ),
	'customer_cancel_body'        => __( 'Hi {customer_name},<br><br>Your booking for {studio_name} on {booking_date} has been cancelled.<br><br>If this was not intended, please contact us.<br>{site_name}', 'tribuna-studio-rent-booking' ),
	'admin_new_subject'           => __( 'New booking received', 'tribuna-studio-rent-booking' ),
	'admin_new_body'              => __( 'New booking received:<br>Customer: {customer_name}<br>Studio: {studio_name}<br>Date: {booking_date}<br>Time: {start_time} - {end_time}<br>Total: {total}<br>Status: {status}<br>ID: {booking_id}', 'tribuna-studio-rent-booking' ),
	'admin_paid_subject'          => __( 'Booking marked as paid', 'tribuna-studio-rent-booking' ),
	'admin_paid_body'             => __( 'Booking #{booking_id} has been marked as paid.<br>Customer: {customer_name}<br>Studio: {studio_name}<br>Date: {booking_date}<br>Time: {start_time} - {end_time}<br>Total: {total}<br>Status: {status}', 'tribuna-studio-rent-booking' ),
	'admin_cancel_subject'        => __( 'Booking cancelled', 'tribuna-studio-rent-booking' ),
	'admin_cancel_body'           => __( 'Booking #{booking_id} has been cancelled.<br>Customer: {customer_name}<br>Studio: {studio_name}<br>Date: {booking_date}<br>Time: {start_time} - {end_time}<br>Total: {total}<br>Status: {status}', 'tribuna-studio-rent-booking' ),
	// TEMPLATE RESCHEDULE CUSTOMER.
	'customer_reschedule_subject' => __( 'Your booking has been rescheduled', 'tribuna-studio-rent-booking' ),
	'customer_reschedule_body'    => __( 'Hi {customer_name},<br><br>Your booking has been rescheduled.<br><br>Studio: {studio_name}<br>Old schedule: {old_booking_date}, {old_start_time} - {old_end_time}<br>New schedule: {booking_date}, {start_time} - {end_time}<br>Total remains: {total}<br>Status: {status}<br><br>If you did not request this change, please contact us.<br>{site_name}', 'tribuna-studio-rent-booking' ),
);

$emails = wp_parse_args( $emails, $email_defaults );

// Workflow Policies.
$workflow = isset( $settings['workflow'] ) && is_array( $settings['workflow'] ) ? $settings['workflow'] : array();

$workflow_defaults = array(
	// Auto-cancel & lead time.
	'auto_cancel_unpaid_hours'         => 0,
	'auto_cancel_unpaid_sameday_hours' => 0,
	'min_lead_time_hours'              => 0,
	'require_manual_approval'          => 0,
	// Field baru untuk informasi ke pelanggan.
	'booking_reschedule_policy_text'   => '',
	'cancel_refund_policy_text'        => '',
	// Field lama tetap disimpan sebagai legacy/fallback.
	'cancellation_policy_text'         => '',
	// Guard booking.
	'prevent_new_if_pending_payment'   => 0,
	'max_active_bookings_per_user'     => 0,
	// Aturan reschedule.
	'allow_member_reschedule'          => 0,
	'reschedule_cutoff_hours'          => 0,
	'reschedule_allow_pending'         => 0,
	'reschedule_admin_only'            => 0,
	// Batas waktu pembayaran dipakai untuk timer & auto-kebijakan.
	'payment_deadline_hours'           => 0,
	// Aturan refund / credit.
	'refund_full_hours_before'         => 24,
	'refund_partial_hours_before'      => 3,
	'refund_partial_percent'           => 70,
	'refund_no_refund_inside_hours'    => 0,
	// Izinkan member cancel.
	'allow_member_cancel'              => 0,
);

$workflow = wp_parse_args( $workflow, $workflow_defaults );

// Integrations.
$integrations = isset( $settings['integrations'] ) && is_array( $settings['integrations'] ) ? $settings['integrations'] : array();

$integrations_defaults = array(
	'google_calendar_enabled'  => 0,
	'google_calendar_id'       => '',
	'google_client_id'         => '',
	'google_client_secret'     => '',
	'whatsapp_default_message' => __( 'Hi, I would like to ask about my booking #{booking_id} on {booking_date}.', 'tribuna-studio-rent-booking' ),
);

$integrations = wp_parse_args( $integrations, $integrations_defaults );

// Helper data.
$days = array(
	'monday'    => __( 'Monday', 'tribuna-studio-rent-booking' ),
	'tuesday'   => __( 'Tuesday', 'tribuna-studio-rent-booking' ),
	'wednesday' => __( 'Wednesday', 'tribuna-studio-rent-booking' ),
	'thursday'  => __( 'Thursday', 'tribuna-studio-rent-booking' ),
	'friday'    => __( 'Friday', 'tribuna-studio-rent-booking' ),
	'saturday'  => __( 'Saturday', 'tribuna-studio-rent-booking' ),
	'sunday'    => __( 'Sunday', 'tribuna-studio-rent-booking' ),
);

// Tabs setup.
$tabs = array(
	'general'      => __( 'General', 'tribuna-studio-rent-booking' ),
	'emails'       => __( 'Email Templates', 'tribuna-studio-rent-booking' ),
	'workflow'     => __( 'Workflow Policies', 'tribuna-studio-rent-booking' ),
	'integrations' => __( 'Integrations', 'tribuna-studio-rent-booking' ),
	'logs'         => __( 'Logs / Status', 'tribuna-studio-rent-booking' ),
);

$current_tab = isset( $_GET['tab'], $tabs[ $_GET['tab'] ] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// Logs status data (read-only).
global $wpdb;

$tables_status   = array();
$expected_tables = array(
	$wpdb->prefix . 'studio_bookings',
	$wpdb->prefix . 'studio_booking_logs',
	$wpdb->prefix . 'studio_coupons',
	$wpdb->prefix . 'studio_addons',
	$wpdb->prefix . 'studio_studios',
);

foreach ( $expected_tables as $t ) {
	$exists              = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
	$tables_status[ $t ] = (bool) $exists;
}

$plugin_version = defined( 'TSRB_VERSION' ) ? TSRB_VERSION : '';
$booking_model  = new Tribuna_Booking_Model();

$total_bookings_all    = $booking_model->count_by_status();
$total_bookings_paid   = $booking_model->count_by_status( 'paid' );
$total_bookings_pend   = $booking_model->count_by_status( 'pending_payment' );
$total_bookings_cancel = $booking_model->count_by_status( 'cancelled' );

$count_members = 0;
if ( function_exists( 'count_users' ) ) {
	$users_count = count_users();
	if ( ! empty( $users_count['avail_roles']['tribuna_member'] ) ) {
		$count_members = (int) $users_count['avail_roles']['tribuna_member'];
	}
}

$cron_event = function_exists( 'wp_get_scheduled_event' ) ? wp_get_scheduled_event( 'tsrb_autocancel_unpaid', null ) : null;
?>

<div class="wrap tsrb-settings-page">
	<h1><?php esc_html_e( 'Settings', 'tribuna-studio-rent-booking' ); ?></h1>

	<h2 class="nav-tab-wrapper">
		<?php foreach ( $tabs as $slug => $label ) : ?>
			<?php
			$class = ( $slug === $current_tab ) ? 'nav-tab nav-tab-active' : 'nav-tab';
			$url   = add_query_arg(
				array(
					'page' => 'tsrb-settings',
					'tab'  => $slug,
				),
				admin_url( 'admin.php' )
			);
			?>
			<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</h2>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'tsrb_settings_group' );
		// do_settings_sections tidak wajib di sini karena kita memproses manual via sanitize_settings().
		?>

		<?php if ( 'general' === $current_tab ) : ?>

			<h2 class="title"><?php esc_html_e( 'General Settings', 'tribuna-studio-rent-booking' ); ?></h2>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="tsrb_currency"><?php esc_html_e( 'Currency', 'tribuna-studio-rent-booking' ); ?></label>
						</th>
						<td>
							<input type="text" id="tsrb_currency" name="tsrb_settings[currency]" value="<?php echo esc_attr( $currency ); ?>" class="regular-text">
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="tsrb_admin_email"><?php esc_html_e( 'Admin Email', 'tribuna-studio-rent-booking' ); ?></label>
						</th>
						<td>
							<input type="email" id="tsrb_admin_email" name="tsrb_settings[admin_email]" value="<?php echo esc_attr( $admin_email ); ?>" class="regular-text">
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="tsrb_admin_whatsapp_number">
								<?php esc_html_e( 'Admin WhatsApp Number', 'tribuna-studio-rent-booking' ); ?>
							</label>
						</th>
						<td>
							<input type="text"
								   id="tsrb_admin_whatsapp_number"
								   name="tsrb_settings[admin_whatsapp_number]"
								   value="<?php echo esc_attr( $admin_whatsapp ); ?>"
								   class="regular-text"
								   placeholder="Contoh: 628123456789">
							<p class="description">
								<?php esc_html_e( 'Number used for WhatsApp link on frontend (international format without +, e.g. 628123456789).', 'tribuna-studio-rent-booking' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php esc_html_e( 'Payment QR Code', 'tribuna-studio-rent-booking' ); ?>
						</th>
						<td>
							<div id="tsrb-payment-qr-wrapper">
								<input type="hidden" id="tsrb_payment_qr_image_id" name="tsrb_settings[payment_qr_image_id]" value="<?php echo esc_attr( $qr_image_id ); ?>">
								<button type="button" class="button" id="tsrb-payment-qr-upload">
									<?php esc_html_e( 'Choose/Upload QR Image', 'tribuna-studio-rent-booking' ); ?>
								</button>
								<button type="button" class="button" id="tsrb-payment-qr-remove">
									<?php esc_html_e( 'Remove', 'tribuna-studio-rent-booking' ); ?>
								</button>

								<div id="tsrb-payment-qr-preview" style="margin-top:10px;">
									<?php
									if ( $qr_image_id ) {
										echo wp_get_attachment_image( $qr_image_id, 'medium' );
									}
									?>
								</div>

								<p class="description">
									<?php esc_html_e( 'This QR will be shown on the payment step of the booking form.', 'tribuna-studio-rent-booking' ); ?>
								</p>
							</div>
						</td>
					</tr>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Operating Hours', 'tribuna-studio-rent-booking' ); ?></h2>
			<table class="widefat fixed striped tsrb-operating-hours">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Day', 'tribuna-studio-rent-booking' ); ?></th>
						<th><?php esc_html_e( 'Open', 'tribuna-studio-rent-booking' ); ?></th>
						<th><?php esc_html_e( 'Close', 'tribuna-studio-rent-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $days as $key => $label ) : ?>
						<tr>
							<td><?php echo esc_html( $label ); ?></td>
							<td>
								<input type="time"
									   name="tsrb_settings[operating_hours][<?php echo esc_attr( $key ); ?>][open]"
									   value="<?php echo isset( $operating_hours[ $key ]['open'] ) ? esc_attr( $operating_hours[ $key ]['open'] ) : ''; ?>">
							</td>
							<td>
								<input type="time"
									   name="tsrb_settings[operating_hours][<?php echo esc_attr( $key ); ?>][close]"
									   value="<?php echo isset( $operating_hours[ $key ]['close'] ) ? esc_attr( $operating_hours[ $key ]['close'] ) : ''; ?>">
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Blocked Dates / Holidays', 'tribuna-studio-rent-booking' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Add dates when studio is closed. These dates will be unavailable for booking.', 'tribuna-studio-rent-booking' ); ?>
			</p>

			<table class="widefat fixed striped" id="tsrb-blocked-dates-table" style="max-width: 400px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'tribuna-studio-rent-booking' ); ?></th>
						<th style="width: 80px;"><?php esc_html_e( 'Action', 'tribuna-studio-rent-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $blocked_dates ) && is_array( $blocked_dates ) ) : ?>
						<?php foreach ( $blocked_dates as $date ) : ?>
							<tr>
								<td>
									<input type="date" name="tsrb_settings[blocked_dates][]" value="<?php echo esc_attr( $date ); ?>" style="width: 100%;">
								</td>
								<td>
									<button type="button" class="button tsrb-remove-blocked-date">&times;</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<p style="margin-top: 10px;">
				<button type="button" class="button" id="tsrb-add-blocked-date">
					<?php esc_html_e( 'Add Date', 'tribuna-studio-rent-booking' ); ?>
				</button>
			</p>

			<p>
				<?php submit_button( __( 'Save Changes', 'tribuna-studio-rent-booking' ), 'primary', 'submit', false ); ?>
			</p>

		<?php elseif ( 'emails' === $current_tab ) : ?>

			<h2 class="title"><?php esc_html_e( 'Email Templates', 'tribuna-studio-rent-booking' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Anda dapat menyesuaikan judul dan isi email. Tag yang tersedia antara lain:', 'tribuna-studio-rent-booking' ); ?>
				<code>{customer_name}</code>, <code>{booking_id}</code>, <code>{studio_name}</code>, <code>{booking_date}</code>,
				<code>{start_time}</code>, <code>{end_time}</code>, <code>{total}</code>, <code>{status}</code>, <code>{site_name}</code>,
				<code>{old_booking_date}</code>, <code>{old_start_time}</code>, <code>{old_end_time}</code>.
			</p>
			<p class="description">
				<?php esc_html_e( 'Isi email di bawah ini mendukung format teks kaya (bold, italic, list, link) dan penyisipan gambar melalui editor.', 'tribuna-studio-rent-booking' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Customer booking baru - judul email', 'tribuna-studio-rent-booking' ); ?></th>
						<td>
							<input type="text" class="regular-text"
								   name="tsrb_settings[emails][customer_new_subject]"
								   value="<?php echo esc_attr( $emails['customer_new_subject'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Customer booking baru - isi email', 'tribuna-studio-rent-booking' ); ?></th>
						<td>
							<?php
							wp_editor(
								$emails['customer_new_body'],
								'tsrb_emails_customer_new_body',
								array(
									'textarea_name' => 'tsrb_settings[emails][customer_new_body]',
									'media_buttons' => true,
									'teeny'         => false,
								)
							);
							?>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Customer booking dikonfirmasi - judul email', 'tribuna-studio-rent-booking' ); ?></th>
						<td>
							<input type="text" class="regular-text"
								   name="tsrb_settings[emails][customer_paid_subject]"
								   value="<?php echo esc_attr( $emails['customer_paid_subject'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Customer booking dikonfirmasi - isi email', 'tribuna-studio-rent-booking' ); ?></th>
						<td>
							<?php
							wp_editor(
								$emails['customer_paid_body'],
								'tsrb_emails_customer_paid_body',
								array(
									'textarea_name' => 'tsrb_settings[emails][customer_paid_body]',
									'media_buttons' => true,
									'teeny'         => false,
								)
							);
							?>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Customer booking dibatalkan - judul email', 'tribuna-studio-rent-booking' ); ?></th>
						<td>
							<input type="text" class="regular-text"
								   name="tsrb_settings[emails][customer_cancel_subject]"
								   value="<?php echo esc_attr( $emails['customer_cancel_subject'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Customer booking dibatalkan - isi email', 'tribuna-studio-rent-booking' ); ?></th>
						<td>
							<?php
							wp_editor(
								$emails['customer_cancel_body'],
								'tsrb_emails_customer_cancel_body',
								array(
									'textarea_name' => 'tsrb_settings[emails][customer_cancel_body]',
									'media_buttons' => true,
									'teeny'         => false,
								)
							);
							?>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Admin notifikasi booking baru - judul email', 'tribuna-studio-rent-booking' ); ?></th>
						<td>
							<input type="text" class="regular-text"
								   name="tsrb_settings[emails][admin_new_subject]"
								   value="<?php echo esc_attr( $emails['admin_new_subject'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Admin notifikasi booking baru - isi email', 'tribuna-studio-rent-booking' ); ?></th>
						<td>
							<?php
							wp_editor(
								$emails['admin_new_body'],
								'tsrb_emails_admin_new_body',
								array(
									'textarea_name' => 'tsrb_settings[emails][admin_new_body]',
									'media_buttons' => true,
									'teeny'         => false,
								)
							);
							?>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Admin notifikasi booking paid - judul email', 'tribuna-studio-rent-booking' ); ?></th>
						<td>
							<input type="text" class="regular-text"
								   name="tsrb_settings[emails][admin_paid_subject]"
								   value="<?php echo esc_attr( $emails['admin_paid_subject'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Admin notifikasi booking paid - isi email', 'tribuna-studio-rent-booking' ); ?></th>
						<td>
							<?php
							wp_editor(
								$emails['admin_paid_body'],
								'tsrb_emails_admin_paid_body',
								array(
									'textarea_name' => 'tsrb_settings[emails][admin_paid_body]',
									'media_buttons' => true,
									'teeny'         => false,
								)
							);
							?>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Admin notifikasi booking cancel - judul email', 'tribuna-studio-rent-booking' ); ?></th>
						<td>
							<input type="text" class="regular-text"
								   name="tsrb_settings[emails][admin_cancel_subject]"
								   value="<?php echo esc_attr( $emails['admin_cancel_subject'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Admin notifikasi booking cancel - isi email', 'tribuna-studio-rent-booking' ); ?></th>
						<td>
							<?php
							wp_editor(
								$emails['admin_cancel_body'],
								'tsrb_emails_admin_cancel_body',
								array(
									'textarea_name' => 'tsrb_settings[emails][admin_cancel_body]',
									'media_buttons' => true,
									'teeny'         => false,
								)
							);
							?>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Customer booking di-reschedule - judul email', 'tribuna-studio-rent-booking' ); ?></th>
						<td>
							<input type="text" class="regular-text"
								   name="tsrb_settings[emails][customer_reschedule_subject]"
								   value="<?php echo esc_attr( $emails['customer_reschedule_subject'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Customer booking di-reschedule - isi email', 'tribuna-studio-rent-booking' ); ?></th>
						<td>
							<?php
							wp_editor(
								$emails['customer_reschedule_body'],
								'tsrb_emails_customer_reschedule_body',
								array(
									'textarea_name' => 'tsrb_settings[emails][customer_reschedule_body]',
									'media_buttons' => true,
									'teeny'         => false,
								)
							);
							?>
						</td>
					</tr>
				</tbody>
			</table>

			<p>
				<?php submit_button( __( 'Save Changes', 'tribuna-studio-rent-booking' ), 'primary', 'submit', false ); ?>
			</p>

		<?php elseif ( 'workflow' === $current_tab ) : ?>

			<h2 class="title"><?php esc_html_e( 'Workflow Policies', 'tribuna-studio-rent-booking' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Atur aturan booking, reschedule, pembatalan, refund, dan informasi yang akan ditampilkan ke pelanggan.', 'tribuna-studio-rent-booking' ); ?>
			</p>

			<hr class="tsrb-section-separator" />

			<!-- BAGIAN 1: ATURAN BOOKING -->
			<h2><?php esc_html_e( '1. Aturan Booking', 'tribuna-studio-rent-booking' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Pengaturan ini mengatur bagaimana pelanggan boleh melakukan booking (lead time, auto-cancel, batas jumlah booking, dan batas waktu pembayaran).', 'tribuna-studio-rent-booking' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="tsrb_auto_cancel_unpaid_hours">
								<?php esc_html_e( 'Auto-cancel booking belum dibayar setelah (jam)', 'tribuna-studio-rent-booking' ); ?>
							</label>
						</th>
						<td>
							<input type="number" min="0" step="1"
								   id="tsrb_auto_cancel_unpaid_hours"
								   name="tsrb_settings[workflow][auto_cancel_unpaid_hours]"
								   value="<?php echo esc_attr( (int) $workflow['auto_cancel_unpaid_hours'] ); ?>">
							<p class="description">
								<?php esc_html_e( 'Isi lebih dari 0 untuk mengaktifkan pembatalan otomatis booking dengan status Pending Payment yang terlalu lama (semua tanggal).', 'tribuna-studio-rent-booking' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="tsrb_auto_cancel_unpaid_sameday_hours">
								<?php esc_html_e( 'Auto-cancel booking belum dibayar di hari H setelah (jam)', 'tribuna-studio-rent-booking' ); ?>
							</label>
						</th>
						<td>
							<input type="number" min="0" step="1"
								   id="tsrb_auto_cancel_unpaid_sameday_hours"
								   name="tsrb_settings[workflow][auto_cancel_unpaid_sameday_hours]"
								   value="<?php echo esc_attr( (int) $workflow['auto_cancel_unpaid_sameday_hours'] ); ?>">
							<p class="description">
								<?php esc_html_e( 'Opsional. Jika diisi, booking yang tanggalnya sama dengan hari ini akan dibatalkan otomatis lebih cepat berdasarkan nilai ini.', 'tribuna-studio-rent-booking' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="tsrb_payment_deadline_hours">
								<?php esc_html_e( 'Batas waktu pembayaran (jam sejak booking dibuat)', 'tribuna-studio-rent-booking' ); ?>
							</label>
						</th>
						<td>
							<input type="number" min="0" step="1"
								   id="tsrb_payment_deadline_hours"
								   name="tsrb_settings[workflow][payment_deadline_hours]"
								   value="<?php echo esc_attr( (int) $workflow['payment_deadline_hours'] ); ?>">
							<p class="description">
								<?php esc_html_e( 'Digunakan untuk menampilkan timer pembayaran di halaman Bookings admin dan logika kedaluwarsa pembayaran. 0 berarti tidak ada batas khusus.', 'tribuna-studio-rent-booking' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="tsrb_min_lead_time_hours">
								<?php esc_html_e( 'Jarak minimal sebelum jam mulai (jam)', 'tribuna-studio-rent-booking' ); ?>
							</label>
						</th>
						<td>
							<input type="number" min="0" step="1"
								   id="tsrb_min_lead_time_hours"
								   name="tsrb_settings[workflow][min_lead_time_hours]"
								   value="<?php echo esc_attr( (int) $workflow['min_lead_time_hours'] ); ?>">
							<p class="description">
								<?php esc_html_e( 'Pelanggan tidak dapat melakukan booking untuk slot yang dimulai lebih dekat dari jarak minimal ini terhadap waktu sekarang.', 'tribuna-studio-rent-booking' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php esc_html_e( 'Booking perlu persetujuan manual?', 'tribuna-studio-rent-booking' ); ?>
						</th>
						<td>
							<label>
								<input type="hidden"
									   name="tsrb_settings[workflow][require_manual_approval]"
									   value="0">
								<input type="checkbox"
									   name="tsrb_settings[workflow][require_manual_approval]"
									   value="1" <?php checked( (int) $workflow['require_manual_approval'], 1 ); ?>>
								<?php esc_html_e( 'Jika dicentang, booking harus disetujui admin secara manual sebelum dianggap benar-benar terkonfirmasi.', 'tribuna-studio-rent-booking' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Gunakan jika Anda ingin review manual jadwal, pembayaran, dll sebelum konfirmasi ke pelanggan.', 'tribuna-studio-rent-booking' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php esc_html_e( 'Blokir booking baru jika masih ada Pending Payment aktif?', 'tribuna-studio-rent-booking' ); ?>
						</th>
						<td>
							<label>
								<input type="hidden"
									   name="tsrb_settings[workflow][prevent_new_if_pending_payment]"
									   value="0">
								<input type="checkbox"
									   name="tsrb_settings[workflow][prevent_new_if_pending_payment]"
									   value="1" <?php checked( (int) $workflow['prevent_new_if_pending_payment'], 1 ); ?>>
								<?php esc_html_e( 'Jika dicentang, member tidak dapat membuat booking baru sebelum menyelesaikan pembayaran booking sebelumnya yang masih Pending Payment.', 'tribuna-studio-rent-booking' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Mencegah spam booking yang mengunci banyak slot tanpa pembayaran.', 'tribuna-studio-rent-booking' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="tsrb_max_active_bookings_per_user">
								<?php esc_html_e( 'Maksimal booking aktif per user', 'tribuna-studio-rent-booking' ); ?>
							</label>
						</th>
						<td>
							<input type="number" min="0" step="1"
								   id="tsrb_max_active_bookings_per_user"
								   name="tsrb_settings[workflow][max_active_bookings_per_user]"
								   value="<?php echo esc_attr( (int) $workflow['max_active_bookings_per_user'] ); ?>">
							<p class="description">
								<?php esc_html_e( '0 berarti tidak dibatasi. Jika diisi, user hanya bisa memiliki maksimal N booking aktif (Paid atau Pending Payment) di tanggal yang akan datang.', 'tribuna-studio-rent-booking' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<p>
				<?php submit_button( __( 'Save Changes', 'tribuna-studio-rent-booking' ), 'primary', 'submit', false ); ?>
			</p>

			<hr class="tsrb-section-separator" />

			<!-- BAGIAN 2: ATURAN RESCHEDULE -->
			<h2><?php esc_html_e( '2. Aturan Reschedule', 'tribuna-studio-rent-booking' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Atur siapa yang boleh reschedule, batas waktu permintaan reschedule, dan status booking yang boleh dipindah jam/harinya.', 'tribuna-studio-rent-booking' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Siapa yang boleh melakukan reschedule?', 'tribuna-studio-rent-booking' ); ?>
						</th>
						<td>
							<p>
								<label>
									<input type="hidden"
										   name="tsrb_settings[workflow][reschedule_admin_only]"
										   value="0">
									<input type="checkbox"
										   name="tsrb_settings[workflow][reschedule_admin_only]"
										   value="1" <?php checked( (int) $workflow['reschedule_admin_only'], 1 ); ?>>
									<?php esc_html_e( 'Hanya admin yang boleh merubah jadwal booking (member tidak bisa reschedule sendiri).', 'tribuna-studio-rent-booking' ); ?>
								</label>
							</p>
							<p>
								<label>
									<input type="hidden"
										   name="tsrb_settings[workflow][allow_member_reschedule]"
										   value="0">
									<input type="checkbox"
										   name="tsrb_settings[workflow][allow_member_reschedule]"
										   value="1" <?php checked( (int) $workflow['allow_member_reschedule'], 1 ); ?>>
									<?php esc_html_e( 'Izinkan member mengajukan reschedule sendiri untuk booking tertentu dengan batas waktu di bawah.', 'tribuna-studio-rent-booking' ); ?>
								</label>
							</p>
							<p class="description">
								<?php esc_html_e( 'Jika kedua opsi dicentang, pengaturan "Hanya admin" akan diutamakan sehingga member tetap tidak dapat reschedule.', 'tribuna-studio-rent-booking' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="tsrb_reschedule_cutoff_hours">
								<?php esc_html_e( 'Batas waktu reschedule (jam sebelum jam mulai)', 'tribuna-studio-rent-booking' ); ?>
							</label>
						</th>
						<td>
							<input type="number" min="0" step="1"
								   id="tsrb_reschedule_cutoff_hours"
								   name="tsrb_settings[workflow][reschedule_cutoff_hours]"
								   value="<?php echo esc_attr( (int) $workflow['reschedule_cutoff_hours'] ); ?>">
							<p class="description">
								<?php esc_html_e( 'Contoh: 12 berarti member hanya bisa mengajukan reschedule maksimal 12 jam sebelum jam mulai. Setelah lewat batas ini, hanya admin yang boleh mengubah jadwal.', 'tribuna-studio-rent-booking' ); ?>
							</p>
							<p class="description">
								<?php esc_html_e( 'Nilai 0 berarti tidak ada batas jam khusus, reschedule tetap mengikuti aturan lead time minimal.', 'tribuna-studio-rent-booking' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php esc_html_e( 'Status booking yang boleh di-reschedule oleh member', 'tribuna-studio-rent-booking' ); ?>
						</th>
						<td>
							<p>
								<label>
									<input type="hidden"
										   name="tsrb_settings[workflow][reschedule_allow_pending]"
										   value="0">
									<input type="checkbox"
										   name="tsrb_settings[workflow][reschedule_allow_pending]"
										   value="1" <?php checked( (int) $workflow['reschedule_allow_pending'], 1 ); ?>>
									<?php esc_html_e( 'Izinkan booking dengan status Pending Payment juga bisa di-reschedule oleh member.', 'tribuna-studio-rent-booking' ); ?>
								</label>
							</p>
							<p class="description">
								<?php esc_html_e( 'Jika tidak dicentang, maka hanya booking dengan status Lunas (Paid) yang dapat di-reschedule oleh member. Admin tetap dapat merubah jadwal untuk semua status yang aktif.', 'tribuna-studio-rent-booking' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php esc_html_e( 'Catatan tambahan terkait harga dan slot', 'tribuna-studio-rent-booking' ); ?>
						</th>
						<td>
							<ul style="list-style: disc; margin-left: 18px;">
								<li><?php esc_html_e( 'Reschedule hanya memindahkan jadwal/slot untuk booking yang sama. Booking ID, total harga, dan pembayaran tetap dianggap satu transaksi.', 'tribuna-studio-rent-booking' ); ?></li>
								<li><?php esc_html_e( 'Harga booking tidak akan otomatis dihitung ulang berdasarkan jam/hari baru. Total yang dibayarkan pelanggan tetap sama.', 'tribuna-studio-rent-booking' ); ?></li>
								<li><?php esc_html_e( 'Semua permintaan reschedule tetap akan dicek terhadap aturan lead time minimal dan ketersediaan slot (tidak boleh tabrakan dengan booking lain).', 'tribuna-studio-rent-booking' ); ?></li>
							</ul>
						</td>
					</tr>
				</tbody>
			</table>

			<p>
				<?php submit_button( __( 'Save Changes', 'tribuna-studio-rent-booking' ), 'primary', 'submit', false ); ?>
			</p>

			<hr class="tsrb-section-separator" />

			<!-- BAGIAN 3: ATURAN CANCELLATION -->
			<h2><?php esc_html_e( '3. Aturan Cancellation', 'tribuna-studio-rent-booking' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Atur kapan pelanggan boleh membatalkan booking dan bagaimana proses permintaannya.', 'tribuna-studio-rent-booking' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Siapa yang boleh mengajukan pembatalan?', 'tribuna-studio-rent-booking' ); ?>
						</th>
						<td>
							<p>
								<label>
									<input type="hidden"
										   name="tsrb_settings[workflow][allow_member_cancel]"
										   value="0">
									<input type="checkbox"
										   name="tsrb_settings[workflow][allow_member_cancel]"
										   value="1" <?php checked( ! empty( $workflow['allow_member_cancel'] ) ); ?>>
									<?php esc_html_e( 'Izinkan member mengajukan permintaan pembatalan dari dashboard mereka.', 'tribuna-studio-rent-booking' ); ?>
								</label>
							</p>
							<p class="description">
								<?php esc_html_e( 'Jika dicentang, member akan melihat tombol "Ajukan Pembatalan" pada booking yang memenuhi syarat. Admin tetap harus meninjau dan menekan tombol approve/reject di halaman detail booking.', 'tribuna-studio-rent-booking' ); ?>
							</p>
							<p class="description">
								<?php esc_html_e( 'Jika tidak dicentang, hanya admin yang dapat membatalkan booking langsung dari panel admin.', 'tribuna-studio-rent-booking' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php esc_html_e( 'Skema umum cancellation', 'tribuna-studio-rent-booking' ); ?>
						</th>
						<td>
							<ul style="list-style: disc; margin-left: 18px;">
								<li><?php esc_html_e( 'Pelanggan/member mengajukan pembatalan dari dashboard (request cancel) jika diizinkan.', 'tribuna-studio-rent-booking' ); ?></li>
								<li><?php esc_html_e( 'Status booking berubah menjadi "Cancel requested".', 'tribuna-studio-rent-booking' ); ?></li>
								<li><?php esc_html_e( 'Admin meninjau dan memutuskan approve / reject di halaman detail booking.', 'tribuna-studio-rent-booking' ); ?></li>
								<li><?php esc_html_e( 'Jika approve, sistem menghitung refund / credit berdasarkan aturan waktu di bawah ini.', 'tribuna-studio-rent-booking' ); ?></li>
							</ul>
							<p class="description">
								<?php esc_html_e( 'Aturan waktu di bawah akan dipakai saat admin menekan tombol approve cancellation.', 'tribuna-studio-rent-booking' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="tsrb_refund_full_hours_before">
								<?php esc_html_e( 'Full refund jika dibatalkan minimal (jam) sebelum jam mulai', 'tribuna-studio-rent-booking' ); ?>
							</label>
						</th>
						<td>
							<input type="number" min="0" step="1"
								   id="tsrb_refund_full_hours_before"
								   name="tsrb_settings[workflow][refund_full_hours_before]"
								   value="<?php echo esc_attr( (int) $workflow['refund_full_hours_before'] ); ?>">
							<p class="description">
								<?php esc_html_e( 'Contoh: 24 berarti jika pembatalan dilakukan 24 jam atau lebih sebelum jam mulai, admin dapat memberikan refund 100% tanpa potongan.', 'tribuna-studio-rent-booking' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="tsrb_refund_partial_hours_before">
								<?php esc_html_e( 'Partial refund jika dibatalkan di hari H sebelum jam (jam)', 'tribuna-studio-rent-booking' ); ?>
							</label>
						</th>
						<td>
							<input type="number" min="0" step="1"
								   id="tsrb_refund_partial_hours_before"
								   name="tsrb_settings[workflow][refund_partial_hours_before]"
								   value="<?php echo esc_attr( (int) $workflow['refund_partial_hours_before'] ); ?>">
							<p class="description">
								<?php esc_html_e( 'Contoh: 3 berarti jika pembatalan di hari yang sama dan masih lebih awal dari 3 jam sebelum jam mulai, sistem akan menggunakan skema partial refund di bawah.', 'tribuna-studio-rent-booking' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="tsrb_refund_partial_percent">
								<?php esc_html_e( 'Persentase refund pada partial refund (%)', 'tribuna-studio-rent-booking' ); ?>
							</label>
						</th>
						<td>
							<input type="number" min="0" max="100" step="1"
								   id="tsrb_refund_partial_percent"
								   name="tsrb_settings[workflow][refund_partial_percent]"
								   value="<?php echo esc_attr( (int) $workflow['refund_partial_percent'] ); ?>">
							<p class="description">
								<?php esc_html_e( 'Contoh: 70 berarti pelanggan akan menerima refund 70% dari nilai booking, dan sisa 30% bisa dianggap hangus atau dikonversi menjadi credit tergantung kebijakan Anda.', 'tribuna-studio-rent-booking' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="tsrb_refund_no_refund_inside_hours">
								<?php esc_html_e( 'Tidak ada refund jika dibatalkan kurang dari (jam) sebelum jam mulai', 'tribuna-studio-rent-booking' ); ?>
							</label>
						</th>
						<td>
							<input type="number" min="0" step="1"
								   id="tsrb_refund_no_refund_inside_hours"
								   name="tsrb_settings[workflow][refund_no_refund_inside_hours]"
								   value="<?php echo esc_attr( (int) $workflow['refund_no_refund_inside_hours'] ); ?>">
							<p class="description">
								<?php esc_html_e( 'Contoh: 1 berarti jika pembatalan dilakukan kurang dari 1 jam sebelum jam mulai, sistem akan menandai bahwa tidak ada refund yang diberikan.', 'tribuna-studio-rent-booking' ); ?>
							</p>
							<p class="description">
								<?php esc_html_e( 'Nilai 0 berarti tidak ada zona wajib no-refund khusus; keputusan bisa tetap diambil oleh admin secara manual.', 'tribuna-studio-rent-booking' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<p>
				<?php submit_button( __( 'Save Changes', 'tribuna-studio-rent-booking' ), 'primary', 'submit', false ); ?>
			</p>

			<hr class="tsrb-section-separator" />

			<!-- BAGIAN 4: INFORMASI UNTUK PELANGGAN -->
			<h2><?php esc_html_e( '4. Informasi ke Pelanggan', 'tribuna-studio-rent-booking' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Teks di bawah ini akan digunakan sebagai informasi umum kebijakan booking/reschedule/cancellation/refund yang dapat ditampilkan pada form booking dan email.', 'tribuna-studio-rent-booking' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="tsrb_booking_reschedule_policy_text">
								<?php esc_html_e( 'Kebijakan Booking & Reschedule (ditampilkan ke pelanggan)', 'tribuna-studio-rent-booking' ); ?>
							</label>
						</th>
						<td>
							<?php
							wp_editor(
								isset( $workflow['booking_reschedule_policy_text'] ) ? $workflow['booking_reschedule_policy_text'] : '',
								'tsrb_booking_reschedule_policy_text',
								array(
									'textarea_name' => 'tsrb_settings[workflow][booking_reschedule_policy_text]',
									'media_buttons' => false,
									'teeny'         => false,
									'textarea_rows' => 6,
								)
							);
							?>
							<p class="description">
								<?php esc_html_e( 'Tulis kebijakan umum booking dan reschedule yang ingin diketahui pelanggan sebelum booking. Anda dapat menggunakan teks kaya (bold, list, link).', 'tribuna-studio-rent-booking' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="tsrb_cancel_refund_policy_text">
								<?php esc_html_e( 'Kebijakan Cancellation & Refund (ditampilkan ke pelanggan)', 'tribuna-studio-rent-booking' ); ?>
							</label>
						</th>
						<td>
							<?php
							wp_editor(
								isset( $workflow['cancel_refund_policy_text'] ) ? $workflow['cancel_refund_policy_text'] : '',
								'tsrb_cancel_refund_policy_text',
								array(
									'textarea_name' => 'tsrb_settings[workflow][cancel_refund_policy_text]',
									'media_buttons' => false,
									'teeny'         => false,
									'textarea_rows' => 6,
								)
							);
							?>
							<p class="description">
								<?php esc_html_e( 'Tulis kebijakan pembatalan dan refund yang akan ditampilkan ke pelanggan (misalnya di langkah konfirmasi booking). Anda dapat menggunakan teks kaya (bold, list, link).', 'tribuna-studio-rent-booking' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<p>
				<?php submit_button( __( 'Save Changes', 'tribuna-studio-rent-booking' ), 'primary', 'submit', false ); ?>
			</p>

		<?php elseif ( 'integrations' === $current_tab ) : ?>

			<h2 class="title"><?php esc_html_e( 'Integrations', 'tribuna-studio-rent-booking' ); ?></h2>

			<h3><?php esc_html_e( 'Google Calendar', 'tribuna-studio-rent-booking' ); ?></h3>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Aktifkan sinkronisasi ke Google Calendar', 'tribuna-studio-rent-booking' ); ?>
						</th>
						<td>
							<label>
								<input type="hidden"
									   name="tsrb_settings[integrations][google_calendar_enabled]"
									   value="0">
								<input type="checkbox"
									   name="tsrb_settings[integrations][google_calendar_enabled]"
									   value="1" <?php checked( (int) $integrations['google_calendar_enabled'], 1 ); ?>>
								<?php esc_html_e( 'Sinkronkan booking ke Google Calendar jika memungkinkan.', 'tribuna-studio-rent-booking' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="tsrb_google_calendar_id">
								<?php esc_html_e( 'Google Calendar ID', 'tribuna-studio-rent-booking' ); ?>
							</label>
						</th>
						<td>
							<input type="text" class="regular-text"
								   id="tsrb_google_calendar_id"
								   name="tsrb_settings[integrations][google_calendar_id]"
								   value="<?php echo esc_attr( $integrations['google_calendar_id'] ); ?>">
							<p class="description">
								<?php esc_html_e( 'Contoh: your-calendar-id@group.calendar.google.com', 'tribuna-studio-rent-booking' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="tsrb_google_client_id">
								<?php esc_html_e( 'Google Client ID', 'tribuna-studio-rent-booking' ); ?>
							</label>
						</th>
						<td>
							<input type="text" class="regular-text"
								   id="tsrb_google_client_id"
								   name="tsrb_settings[integrations][google_client_id]"
								   value="<?php echo esc_attr( $integrations['google_client_id'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="tsrb_google_client_secret">
								<?php esc_html_e( 'Google Client Secret', 'tribuna-studio-rent-booking' ); ?>
							</label>
						</th>
						<td>
							<input type="text" class="regular-text"
								   id="tsrb_google_client_secret"
								   name="tsrb_settings[integrations][google_client_secret]"
								   value="<?php echo esc_attr( $integrations['google_client_secret'] ); ?>">
						</td>
					</tr>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'WhatsApp', 'tribuna-studio-rent-booking' ); ?></h3>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="tsrb_whatsapp_default_message">
								<?php esc_html_e( 'Template pesan WhatsApp default', 'tribuna-studio-rent-booking' ); ?>
							</label>
						</th>
						<td>
							<textarea id="tsrb_whatsapp_default_message"
									  name="tsrb_settings[integrations][whatsapp_default_message]"
									  rows="4"
									  class="large-text"><?php echo esc_textarea( $integrations['whatsapp_default_message'] ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Tag yang tersedia: {customer_name}, {booking_id}, {booking_date}, {start_time}, {site_name}.', 'tribuna-studio-rent-booking' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<p>
				<?php submit_button( __( 'Save Changes', 'tribuna-studio-rent-booking' ), 'primary', 'submit', false ); ?>
			</p>

		<?php elseif ( 'logs' === $current_tab ) : ?>

			<h2 class="title"><?php esc_html_e( 'Logs / Status', 'tribuna-studio-rent-booking' ); ?></h2>

			<h3><?php esc_html_e( 'Plugin info', 'tribuna-studio-rent-booking' ); ?></h3>
			<table class="widefat striped" style="max-width: 600px;">
				<tbody>
					<tr>
						<th><?php esc_html_e( 'Plugin version', 'tribuna-studio-rent-booking' ); ?></th>
						<td><?php echo $plugin_version ? esc_html( $plugin_version ) : '&mdash;'; ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'WordPress version', 'tribuna-studio-rent-booking' ); ?></th>
						<td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'PHP version', 'tribuna-studio-rent-booking' ); ?></th>
						<td><?php echo esc_html( PHP_VERSION ); ?></td>
					</tr>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'Database tables', 'tribuna-studio-rent-booking' ); ?></h3>
			<table class="widefat striped" style="max-width: 600px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Table', 'tribuna-studio-rent-booking' ); ?></th>
						<th><?php esc_html_e( 'Status', 'tribuna-studio-rent-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $tables_status as $table_name => $exists ) : ?>
						<tr>
							<td><code><?php echo esc_html( $table_name ); ?></code></td>
							<td>
								<?php if ( $exists ) : ?>
									<span style="color: #008000;"><?php esc_html_e( 'OK', 'tribuna-studio-rent-booking' ); ?></span>
								<?php else : ?>
									<span style="color: #cc0000;"><?php esc_html_e( 'Missing', 'tribuna-studio-rent-booking' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'Counters', 'tribuna-studio-rent-booking' ); ?></h3>
			<table class="widefat striped" style="max-width: 600px%;">
				<tbody>
					<tr>
						<th><?php esc_html_e( 'Total bookings', 'tribuna-studio-rent-booking' ); ?></th>
						<td><?php echo esc_html( $total_bookings_all ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Paid bookings', 'tribuna-studio-rent-booking' ); ?></th>
						<td><?php echo esc_html( $total_bookings_paid ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Pending bookings', 'tribuna-studio-rent-booking' ); ?></th>
						<td><?php echo esc_html( $total_bookings_pend ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Cancelled bookings', 'tribuna-studio-rent-booking' ); ?></th>
						<td><?php echo esc_html( $total_bookings_cancel ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Members (role tribuna_member)', 'tribuna-studio-rent-booking' ); ?></th>
						<td><?php echo esc_html( $count_members ); ?></td>
					</tr>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'Cron / Automation', 'tribuna-studio-rent-booking' ); ?></h3>
			<table class="widefat striped" style="max-width: 600px%;">
				<tbody>
					<tr>
						<th><?php esc_html_e( 'Auto-cancel unpaid hook', 'tribuna-studio-rent-booking' ); ?></th>
						<td>
							<?php
							if ( $cron_event ) {
								printf(
									esc_html__( 'Scheduled: %s', 'tribuna-studio-rent-booking' ),
									esc_html( date_i18n( get_option( 'date_format' ) . ' H:i', $cron_event->timestamp ) )
								);
							} else {
								esc_html_e( 'Not scheduled', 'tribuna-studio-rent-booking' );
							}
							?>
						</td>
					</tr>
				</tbody>
			</table>

		<?php endif; ?>

		<?php submit_button(); ?>
	</form>
</div>
