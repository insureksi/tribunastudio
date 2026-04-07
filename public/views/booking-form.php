<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Settings global plugin dari helper satu sumber utama.
if ( class_exists( 'Tribuna_Helpers' ) && method_exists( 'Tribuna_Helpers', 'get_settings' ) ) {
	$settings = Tribuna_Helpers::get_settings();
} else {
	$settings = array();
}

// Workflow & policy.
$workflow = isset( $settings['workflow'] ) && is_array( $settings['workflow'] ) ? $settings['workflow'] : array();

// Status login & current user.
$is_logged_in  = isset( $is_logged_in ) ? (bool) $is_logged_in : is_user_logged_in();
$current_user  = isset( $current_user ) ? $current_user : ( $is_logged_in ? wp_get_current_user() : null );
$prefill_name  = ( $is_logged_in && $current_user instanceof WP_User && $current_user->exists() ) ? $current_user->display_name : '';
$prefill_email = ( $is_logged_in && $current_user instanceof WP_User && $current_user->exists() ) ? $current_user->user_email : '';
$prefill_phone = '';

if ( $is_logged_in && $current_user instanceof WP_User && $current_user->exists() ) {
	$prefill_phone = get_user_meta( $current_user->ID, 'tsrb_whatsapp', true );
}

// Helper untuk inisial avatar kecil.
$account_initials = '';
if ( $prefill_name ) {
	$parts = preg_split( '/\s+/', trim( $prefill_name ) );
	if ( ! empty( $parts ) ) {
		$first            = mb_substr( $parts[0], 0, 1 );
		$last             = count( $parts ) > 1 ? mb_substr( $parts[ count( $parts ) - 1 ], 0, 1 ) : '';
		$account_initials = strtoupper( $first . $last );
	}
}

// Logout URL: kembali ke halaman booking ini setelah logout.
$booking_page_url = get_permalink();
$logout_url       = add_query_arg( 'tsrb_logout', '1', $booking_page_url );

// QR pembayaran (jika ada).
$payment_qr_url = '';
if ( ! empty( $settings['payment_qr_image_id'] ) ) {
	$payment_qr_url = wp_get_attachment_image_url( (int) $settings['payment_qr_image_id'], 'medium' );
}
?>
<div class="tsrb-booking-wrapper" data-tsrb-logged-in="<?php echo $is_logged_in ? '1' : '0'; ?>">

	<!-- STATUS AKUN DI ATAS FORM -->
	<div class="tsrb-account-status" data-logout-url="<?php echo esc_url( $logout_url ); ?>">
		<?php if ( $is_logged_in && $current_user instanceof WP_User && $current_user->exists() ) : ?>
			<div class="tsrb-account-status-left">
				<div class="tsrb-account-avatar">
					<span><?php echo esc_html( $account_initials ? $account_initials : mb_substr( $prefill_name, 0, 1 ) ); ?></span>
				</div>
				<div class="tsrb-account-status-text">
					<div class="tsrb-account-status-line">
						<?php esc_html_e( 'Login sebagai', 'tribuna-studio-rent-booking' ); ?>
						<strong><?php echo esc_html( $prefill_name ); ?></strong>
					</div>
					<div class="tsrb-account-status-sub">
						<?php echo esc_html( $prefill_email ); ?>
					</div>
				</div>
			</div>
			<div class="tsrb-account-status-right">
				<button type="button"
						class="tsrb-account-status-link tsrb-account-history-trigger">
					<?php esc_html_e( 'Riwayat Booking', 'tribuna-studio-rent-booking' ); ?>
				</button>
				<button type="button"
						class="tsrb-account-status-link tsrb-account-profile-trigger">
					<?php esc_html_e( 'Profil Saya', 'tribuna-studio-rent-booking' ); ?>
				</button>
				<button type="button"
						class="tsrb-account-status-link tsrb-account-logout-trigger">
					<?php esc_html_e( 'Logout', 'tribuna-studio-rent-booking' ); ?>
				</button>
			</div>
		<?php else : ?>
			<div class="tsrb-account-status-left">
				<div class="tsrb-account-avatar tsrb-account-avatar-guest">
					<span>?</span>
				</div>
				<div class="tsrb-account-status-text">
					<div class="tsrb-account-status-line">
						<?php esc_html_e( 'Anda belum login.', 'tribuna-studio-rent-booking' ); ?>
					</div>
					<div class="tsrb-account-status-sub">
						<?php esc_html_e( 'Login atau daftar akun untuk menyelesaikan booking.', 'tribuna-studio-rent-booking' ); ?>
					</div>
				</div>
			</div>
			<div class="tsrb-account-status-right tsrb-account-status-right-guest">
				<button type="button"
						class="tsrb-account-status-link tsrb-open-login-modal">
					<?php esc_html_e( 'Login', 'tribuna-studio-rent-booking' ); ?>
				</button>
				<button type="button"
						class="tsrb-account-status-link tsrb-account-status-link-primary tsrb-open-register-modal">
					<?php esc_html_e( 'Daftar', 'tribuna-studio-rent-booking' ); ?>
				</button>
			</div>
		<?php endif; ?>
	</div>

	<!-- ARROW STEP INDICATOR -->
	<div class="tsrb-booking-steps tsrb-steps-arrow">
		<div class="tsrb-step tsrb-step-active" data-step="1">
			<span class="tsrb-step-number">1.</span>
			<span class="tsrb-step-label"><?php esc_html_e( 'Pilih Tanggal & Jam', 'tribuna-studio-rent-booking' ); ?></span>
		</div>
		<div class="tsrb-step" data-step="2">
			<span class="tsrb-step-number">2.</span>
			<span class="tsrb-step-label"><?php esc_html_e( 'Data Anda', 'tribuna-studio-rent-booking' ); ?></span>
		</div>
		<div class="tsrb-step" data-step="3">
			<span class="tsrb-step-number">3.</span>
			<span class="tsrb-step-label"><?php esc_html_e( 'Konfirmasi & Pembayaran', 'tribuna-studio-rent-booking' ); ?></span>
		</div>
	</div>

	<form id="tsrb-booking-form" enctype="multipart/form-data">
		<input type="hidden" name="action" value="tsrb_submit_booking">
		<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'tsrb_public_nonce' ) ); ?>">

		<!-- LANGKAH 1 -->
		<div class="tsrb-step-panel tsrb-step-panel-1 tsrb-step-panel-active" data-step="1">
			<h3><?php esc_html_e( 'Pilih Studio', 'tribuna-studio-rent-booking' ); ?></h3>
			<?php if ( ! empty( $studios ) ) : ?>
				<div class="tsrb-studio-select-list">
					<?php foreach ( $studios as $index => $studio ) : ?>
						<?php
						$primary_url = '';
						if ( ! empty( $studio->gallery_image_ids ) ) {
							$ids = array_filter( array_map( 'absint', explode( ',', $studio->gallery_image_ids ) ) );
							if ( ! empty( $ids ) ) {
								$primary_url = wp_get_attachment_image_url( $ids[0], 'medium' );
							}
						}
						$is_default = ( 0 === $index );
						?>
						<label class="tsrb-studio-card <?php echo $is_default ? 'tsrb-studio-card-selected' : ''; ?>">
							<input
								type="radio"
								name="studio_id"
								value="<?php echo esc_attr( $studio->id ); ?>"
								<?php checked( $is_default ); ?>
								class="tsrb-studio-radio"
							>
							<div class="tsrb-studio-image">
								<?php if ( $primary_url ) : ?>
									<img src="<?php echo esc_url( $primary_url ); ?>" alt="<?php echo esc_attr( $studio->name ); ?>">
								<?php else : ?>
									<div class="tsrb-studio-image-placeholder">
										<?php esc_html_e( 'Tidak ada gambar', 'tribuna-studio-rent-booking' ); ?>
									</div>
								<?php endif; ?>
							</div>
							<div class="tsrb-studio-info">
								<div class="tsrb-studio-name"><?php echo esc_html( $studio->name ); ?></div>
								<?php if ( ! empty( $studio->description ) ) : ?>
									<div class="tsrb-studio-desc">
										<?php echo esc_html( wp_trim_words( wp_strip_all_tags( $studio->description ), 18 ) ); ?>
									</div>
								<?php endif; ?>
							</div>
						</label>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p><?php esc_html_e( 'Belum ada studio yang dikonfigurasi.', 'tribuna-studio-rent-booking' ); ?></p>
			<?php endif; ?>

			<h3><?php esc_html_e( 'Pilih Tanggal', 'tribuna-studio-rent-booking' ); ?></h3>
			<div class="tsrb-booking-calendar-wrap">
				<div id="tsrb-booking-calendar"></div>
			</div>

			<div class="tsrb-selected-date-box">
				<div class="tsrb-selected-date">
					<span class="tsrb-selected-date-label">
						<?php esc_html_e( 'Tanggal Terpilih:', 'tribuna-studio-rent-booking' ); ?>
					</span>
					<span id="tsrb-selected-date-text" class="tsrb-selected-date-text" data-value="">-</span>
				</div>
				<div class="tsrb-selected-date-help tsrb-selected-date-help-empty">
					<?php esc_html_e( 'Belum ada tanggal dipilih. Klik salah satu tanggal pada kalender di atas.', 'tribuna-studio-rent-booking' ); ?>
				</div>
			</div>

			<div class="tsrb-time-slots-wrapper">
				<h3><?php esc_html_e( 'Jam Tersedia', 'tribuna-studio-rent-booking' ); ?></h3>
				<div id="tsrb-time-slots">
					<p class="tsrb-info">
						<?php esc_html_e( 'Silakan pilih tanggal untuk melihat jam yang tersedia.', 'tribuna-studio-rent-booking' ); ?>
					</p>
				</div>
				<input type="hidden" id="tsrb-slot-start" name="slot_start" value="">
				<input type="hidden" id="tsrb-slot-end" name="slot_end" value="">
			</div>

			<div class="tsrb-addons-wrapper">
				<h3><?php esc_html_e( 'Layanan Tambahan (Add-ons)', 'tribuna-studio-rent-booking' ); ?></h3>
				<?php if ( ! empty( $addons ) ) : ?>
					<?php foreach ( $addons as $addon ) : ?>
						<label class="tsrb-addon-item">
							<div class="tsrb-addon-left">
								<input
									type="checkbox"
									name="addons[]"
									value="<?php echo esc_attr( $addon->id ); ?>"
									data-price="<?php echo esc_attr( $addon->price ); ?>"
								>
							</div>
							<div class="tsrb-addon-right">
								<div class="tsrb-addon-name">
									<?php echo esc_html( $addon->name ); ?>
									<span class="tsrb-addon-price">
										<?php echo esc_html( Tribuna_Helpers::format_price( $addon->price ) ); ?>
									</span>
								</div>
								<?php if ( ! empty( $addon->description ) ) : ?>
									<div class="tsrb-addon-desc">
										<?php echo esc_html( $addon->description ); ?>
									</div>
								<?php endif; ?>
							</div>
						</label>
					<?php endforeach; ?>
				<?php else : ?>
					<p><?php esc_html_e( 'Belum ada layanan tambahan yang dikonfigurasi.', 'tribuna-studio-rent-booking' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="tsrb-pricing-summary">
				<h3><?php esc_html_e( 'Ringkasan Harga', 'tribuna-studio-rent-booking' ); ?></h3>
				<table class="tsrb-pricing-table">
					<tbody>
					<tr>
						<th><?php esc_html_e( 'Durasi', 'tribuna-studio-rent-booking' ); ?></th>
						<td><span id="tsrb-duration-hours">0</span> <?php esc_html_e( 'jam', 'tribuna-studio-rent-booking' ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Harga Dasar', 'tribuna-studio-rent-booking' ); ?></th>
						<td><span id="tsrb-base-price">0</span></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Add-ons', 'tribuna-studio-rent-booking' ); ?></th>
						<td><span id="tsrb-addons-price">0</span></td>
					</tr>
					<tr class="tsrb-pricing-total-row">
						<th><?php esc_html_e( 'Total', 'tribuna-studio-rent-booking' ); ?></th>
						<td><span id="tsrb-total-price">0</span></td>
					</tr>
					</tbody>
				</table>
			</div>

			<div class="tsrb-step-actions">
				<button type="button"
						class="tsrb-next-step tsrb-btn tsrb-btn-primary button button-primary"
						data-next-step="2"
						id="tsrb-next-step-1">
					<?php esc_html_e( 'Lanjut: Data Anda', 'tribuna-studio-rent-booking' ); ?>
				</button>
			</div>
		</div>

		<!-- LANGKAH 2 -->
		<div class="tsrb-step-panel tsrb-step-panel-2" data-step="2">
			<h3><?php esc_html_e( 'Data Anda', 'tribuna-studio-rent-booking' ); ?></h3>

			<?php if ( $is_logged_in ) : ?>
				<p class="tsrb-info tsrb-data-info">
					<?php esc_html_e( 'Data di bawah ini akan digunakan untuk booking Anda. Anda bisa mengubahnya terlebih dahulu jika diperlukan.', 'tribuna-studio-rent-booking' ); ?>
				</p>

				<div class="tsrb-data-form tsrb-form-stacked">
					<div class="tsrb-form-field">
						<label for="tsrb-full-name"><?php esc_html_e( 'Nama Lengkap', 'tribuna-studio-rent-booking' ); ?> *</label>
						<input
							type="text"
							id="tsrb-full-name"
							name="full_name"
							value="<?php echo esc_attr( $prefill_name ); ?>"
							placeholder="<?php esc_attr_e( 'e.g. Budi Santoso', 'tribuna-studio-rent-booking' ); ?>"
							required
						>
					</div>

					<div class="tsrb-form-field">
						<label for="tsrb-email"><?php esc_html_e( 'Email', 'tribuna-studio-rent-booking' ); ?> *</label>
						<input
							type="email"
							id="tsrb-email"
							name="email"
							value="<?php echo esc_attr( $prefill_email ); ?>"
							placeholder="<?php esc_attr_e( 'e.g. nama.anda@gmail.com', 'tribuna-studio-rent-booking' ); ?>"
							required
						>
					</div>

					<div class="tsrb-form-field">
						<label for="tsrb-phone"><?php esc_html_e( 'No. HP / WhatsApp', 'tribuna-studio-rent-booking' ); ?> *</label>
						<input
							type="text"
							id="tsrb-phone"
							name="phone"
							value="<?php echo esc_attr( $prefill_phone ); ?>"
							placeholder="<?php esc_attr_e( 'e.g. 6281234567890', 'tribuna-studio-rent-booking' ); ?>"
							required
						>
					</div>

					<div class="tsrb-form-field">
						<label for="tsrb-notes"><?php esc_html_e( 'Catatan Tambahan (opsional)', 'tribuna-studio-rent-booking' ); ?></label>
						<textarea
							id="tsrb-notes"
							name="notes"
							rows="4"
							placeholder="<?php esc_attr_e( 'Informasi tambahan untuk admin, misalnya kebutuhan khusus, catatan teknis, dsb.', 'tribuna-studio-rent-booking' ); ?>"></textarea>
					</div>
				</div>

				<div class="tsrb-step-actions">
					<button type="button"
							class="tsrb-prev-step tsrb-btn tsrb-btn-secondary button"
							data-prev-step="1">
						<?php esc_html_e( 'Kembali', 'tribuna-studio-rent-booking' ); ?>
					</button>
					<button type="button"
							class="tsrb-next-step tsrb-btn tsrb-btn-primary button button-primary"
							data-next-step="3">
						<?php esc_html_e( 'Lanjut: Konfirmasi', 'tribuna-studio-rent-booking' ); ?>
					</button>
				</div>
			<?php else : ?>
				<p class="tsrb-login-notice tsrb-data-login-notice">
					<?php esc_html_e( 'Untuk menyelesaikan booking, silakan login atau daftar akun terlebih dahulu. Setelah login, halaman ini akan tetap menyimpan pilihan studio, tanggal, dan jam Anda.', 'tribuna-studio-rent-booking' ); ?>
				</p>
				<div class="tsrb-auth-buttons tsrb-data-auth-buttons">
					<button type="button"
							class="tsrb-btn tsrb-btn-secondary button tsrb-open-login-modal">
						<?php esc_html_e( 'Login', 'tribuna-studio-rent-booking' ); ?>
					</button>
					<button type="button"
							class="tsrb-btn tsrb-btn-primary button button-primary tsrb-open-register-modal">
						<?php esc_html_e( 'Daftar Akun Baru', 'tribuna-studio-rent-booking' ); ?>
					</button>
				</div>

				<div class="tsrb-step-actions">
					<button type="button"
							class="tsrb-prev-step tsrb-btn tsrb-btn-ghost button"
							data-prev-step="1">
						<?php esc_html_e( 'Kembali', 'tribuna-studio-rent-booking' ); ?>
					</button>
				</div>
			<?php endif; ?>
		</div>

		<!-- LANGKAH 3 -->
		<div class="tsrb-step-panel tsrb-step-panel-3" data-step="3">
			<h3><?php esc_html_e( 'Ringkasan Booking', 'tribuna-studio-rent-booking' ); ?></h3>

			<div id="tsrb-summary-invoice" class="tsrb-summary-invoice"></div>

			<h3><?php esc_html_e( 'Kupon Diskon', 'tribuna-studio-rent-booking' ); ?></h3>
			<div class="tsrb-coupon-area tsrb-form-stacked">
				<div class="tsrb-form-field">
					<label for="tsrb-coupon-code">
						<?php esc_html_e( 'Kode Kupon (opsional)', 'tribuna-studio-rent-booking' ); ?>
					</label>
					<input
						type="text"
						id="tsrb-coupon-code"
						name="coupon_code"
						placeholder="<?php esc_attr_e( 'e.g. PROMOHEMAT', 'tribuna-studio-rent-booking' ); ?>"
					>
				</div>

				<button type="button"
						class="tsrb-btn tsrb-btn-secondary button"
						id="tsrb-apply-coupon">
					<?php esc_html_e( 'Gunakan Kupon', 'tribuna-studio-rent-booking' ); ?>
				</button>

				<span id="tsrb-coupon-message"></span>
			</div>

			<hr>

			<h3><?php esc_html_e( 'Pembayaran', 'tribuna-studio-rent-booking' ); ?></h3>
			<p class="tsrb-payment-intro">
				<?php esc_html_e( 'Silakan scan kode QR di bawah untuk melakukan pembayaran.', 'tribuna-studio-rent-booking' ); ?>
			</p>

			<div class="tsrb-payment-qr">
				<?php if ( ! empty( $payment_qr_url ) ) : ?>
					<img src="<?php echo esc_url( $payment_qr_url ); ?>" alt="<?php esc_attr_e( 'QR Code Pembayaran', 'tribuna-studio-rent-booking' ); ?>">
				<?php else : ?>
					<p class="tsrb-info">
						<?php esc_html_e( 'QR pembayaran akan muncul di sini setelah diatur oleh admin di menu Pengaturan.', 'tribuna-studio-rent-booking' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<p class="tsrb-payment-info tsrb-payment-note">
				<?php esc_html_e( 'Setelah melakukan pembayaran, silakan hubungi dan kirim bukti bayar ke admin melalui WhatsApp. Booking Anda akan dikonfirmasi setelah pembayaran diverifikasi.', 'tribuna-studio-rent-booking' ); ?>
			</p>

			<div class="tsrb-final-total-wrapper">
				<div class="tsrb-final-total">
					<span class="tsrb-final-total-label">
						<?php esc_html_e( 'Total Bayar:', 'tribuna-studio-rent-booking' ); ?>
					</span>
					<span id="tsrb-final-price-text" class="tsrb-final-total-amount">0</span>
				</div>
			</div>

			<div class="tsrb-whatsapp-wrapper">
				<button type="button"
						id="tsrb-booking-policy-button"
						class="tsrb-btn tsrb-btn-ghost button tsrb-btn-booking-policy">
					<?php esc_html_e( 'Kebijakan Booking', 'tribuna-studio-rent-booking' ); ?>
				</button>

				<button type="button"
						id="tsrb-whatsapp-button"
						class="tsrb-btn tsrb-btn-secondary button">
					<?php esc_html_e( 'Kirim bukti via WhatsApp', 'tribuna-studio-rent-booking' ); ?>
				</button>
			</div>

			<div class="tsrb-step-actions">
				<button type="button"
						class="tsrb-prev-step tsrb-step3-back tsrb-btn tsrb-btn-secondary button"
						data-prev-step="2">
					<?php esc_html_e( 'Kembali', 'tribuna-studio-rent-booking' ); ?>
				</button>
				<button type="submit"
						id="tsrb-submit-booking"
						class="tsrb-btn tsrb-btn-primary button button-primary">
					<?php esc_html_e( 'Konfirmasi Booking', 'tribuna-studio-rent-booking' ); ?>
				</button>
			</div>

			<div id="tsrb-booking-result"></div>
		</div>
	</form>
</div>

<!-- Modal Login/Daftar -->
<div id="tsrb-auth-modal" class="tsrb-auth-modal" style="display:none;">
	<div class="tsrb-auth-modal-overlay"></div>

	<div class="tsrb-auth-modal-content tsrb-auth-card">
		<button type="button" class="tsrb-auth-modal-close">&times;</button>

		<div class="tsrb-auth-card-header">
			<div class="tsrb-auth-card-icon">
				<span>TS</span>
			</div>
			<div class="tsrb-auth-tabs">
				<button type="button" class="tsrb-auth-tab tsrb-auth-tab-login tsrb-auth-tab-active">
					<?php esc_html_e( 'Login', 'tribuna-studio-rent-booking' ); ?>
				</button>
				<button type="button" class="tsrb-auth-tab tsrb-auth-tab-register">
					<?php esc_html_e( 'Daftar', 'tribuna-studio-rent-booking' ); ?>
				</button>
			</div>
		</div>

		<!-- LOGIN PANEL -->
		<div class="tsrb-auth-tab-panel tsrb-auth-tab-panel-login tsrb-auth-tab-panel-active">
			<h3 class="tsrb-auth-title"><?php esc_html_e( 'Login', 'tribuna-studio-rent-booking' ); ?></h3>

			<form id="tsrb-login-form" class="tsrb-auth-form" action="" method="post">
				<div class="tsrb-auth-field">
					<label for="tsrb-login-username"><?php esc_html_e( 'Username atau Email', 'tribuna-studio-rent-booking' ); ?></label>
					<div class="tsrb-auth-input-wrap">
						<span class="tsrb-auth-input-icon dashicons dashicons-email-alt"></span>
						<input type="text"
							   id="tsrb-login-username"
							   class="tsrb-auth-input"
							   placeholder="<?php esc_attr_e( 'Masukkan username atau email', 'tribuna-studio-rent-booking' ); ?>">
					</div>
				</div>

				<div class="tsrb-auth-field">
					<label for="tsrb-login-password"><?php esc_html_e( 'Password', 'tribuna-studio-rent-booking' ); ?></label>
					<div class="tsrb-auth-input-wrap">
						<span class="tsrb-auth-input-icon dashicons dashicons-lock"></span>
						<input type="password"
							   id="tsrb-login-password"
							   class="tsrb-auth-input"
							   placeholder="<?php esc_attr_e( 'Password', 'tribuna-studio-rent-booking' ); ?>">
					</div>
				</div>

				<div class="tsrb-auth-row tsrb-auth-row-inline">
					<label class="tsrb-auth-remember">
						<input type="checkbox" id="tsrb-login-remember" value="1">
						<span><?php esc_html_e( 'Remember me', 'tribuna-studio-rent-booking' ); ?></span>
					</label>
					<a class="tsrb-auth-link"
					   href="<?php echo esc_url( wp_lostpassword_url( get_permalink() ) ); ?>"
					   target="_blank">
						<?php esc_html_e( 'Forgot password?', 'tribuna-studio-rent-booking' ); ?>
					</a>
				</div>

				<button type="submit"
						class="tsrb-btn-auth tsrb-btn-auth-primary"
						id="tsrb-login-submit">
					<?php esc_html_e( 'Login', 'tribuna-studio-rent-booking' ); ?>
				</button>
			</form>

			<p class="tsrb-auth-switch">
				<?php esc_html_e( 'Belum punya akun?', 'tribuna-studio-rent-booking' ); ?>
				<a href="#" class="tsrb-switch-to-register tsrb-auth-link">
					<?php esc_html_e( 'Daftar sekarang', 'tribuna-studio-rent-booking' ); ?>
				</a>
			</p>

			<div class="tsrb-auth-message" id="tsrb-login-message"></div>
		</div>

		<!-- REGISTER PANEL -->
		<div class="tsrb-auth-tab-panel tsrb-auth-tab-panel-register">
			<h3 class="tsrb-auth-title"><?php esc_html_e( 'Daftar', 'tribuna-studio-rent-booking' ); ?></h3>

			<div class="tsrb-auth-field">
				<label for="tsrb-reg-username"><?php esc_html_e( 'Username', 'tribuna-studio-rent-booking' ); ?></label>
				<div class="tsrb-auth-input-wrap">
					<span class="tsrb-auth-input-icon dashicons dashicons-admin-users"></span>
					<input type="text"
						   id="tsrb-reg-username"
						   class="tsrb-auth-input"
						   placeholder="<?php esc_attr_e( 'Buat username', 'tribuna-studio-rent-booking' ); ?>">
				</div>
			</div>

			<div class="tsrb-auth-field">
				<label for="tsrb-reg-fullname"><?php esc_html_e( 'Nama Lengkap', 'tribuna-studio-rent-booking' ); ?></label>
				<div class="tsrb-auth-input-wrap">
					<span class="tsrb-auth-input-icon dashicons dashicons-id"></span>
					<input type="text"
						   id="tsrb-reg-fullname"
						   class="tsrb-auth-input"
						   placeholder="<?php esc_attr_e( 'Nama lengkap Anda', 'tribuna-studio-rent-booking' ); ?>">
				</div>
			</div>

			<div class="tsrb-auth-field">
				<label for="tsrb-reg-phone"><?php esc_html_e( 'Nomor WhatsApp', 'tribuna-studio-rent-booking' ); ?></label>
				<div class="tsrb-auth-input-wrap">
					<span class="tsrb-auth-input-icon dashicons dashicons-smartphone"></span>
					<input type="text"
						   id="tsrb-reg-phone"
						   class="tsrb-auth-input"
						   placeholder="<?php esc_attr_e( 'Nomor WhatsApp aktif', 'tribuna-studio-rent-booking' ); ?>">
				</div>
			</div>

			<div class="tsrb-auth-field">
				<label for="tsrb-reg-email"><?php esc_html_e( 'Email', 'tribuna-studio-rent-booking' ); ?></label>
				<div class="tsrb-auth-input-wrap">
					<span class="tsrb-auth-input-icon dashicons dashicons-email-alt"></span>
					<input type="email"
						   id="tsrb-reg-email"
						   class="tsrb-auth-input"
						   placeholder="<?php esc_attr_e( 'Alamat email Anda', 'tribuna-studio-rent-booking' ); ?>">
				</div>
			</div>

			<div class="tsrb-auth-field">
				<label for="tsrb-reg-password"><?php esc_html_e( 'Password', 'tribuna-studio-rent-booking' ); ?></label>
				<div class="tsrb-auth-input-wrap">
					<span class="tsrb-auth-input-icon dashicons dashicons-lock"></span>
					<input type="password"
						   id="tsrb-reg-password"
						   class="tsrb-auth-input"
						   placeholder="<?php esc_attr_e( 'Buat password (min. 8 karakter)', 'tribuna-studio-rent-booking' ); ?>">
				</div>
			</div>

			<div class="tsrb-auth-field">
				<label for="tsrb-reg-password-confirm"><?php esc_html_e( 'Konfirmasi Password', 'tribuna-studio-rent-booking' ); ?></label>
				<div class="tsrb-auth-input-wrap">
					<span class="tsrb-auth-input-icon dashicons dashicons-lock"></span>
					<input type="password"
						   id="tsrb-reg-password-confirm"
						   class="tsrb-auth-input"
						   placeholder="<?php esc_attr_e( 'Ulangi password', 'tribuna-studio-rent-booking' ); ?>">
				</div>
			</div>

			<button type="button"
					class="tsrb-btn-auth tsrb-btn-auth-primary"
					id="tsrb-register-submit">
				<?php esc_html_e( 'Daftar', 'tribuna-studio-rent-booking' ); ?>
			</button>

			<p class="tsrb-auth-switch">
				<?php esc_html_e( 'Sudah punya akun?', 'tribuna-studio-rent-booking' ); ?>
				<a href="#" class="tsrb-switch-to-login tsrb-auth-link">
					<?php esc_html_e( 'Login sekarang', 'tribuna-studio-rent-booking' ); ?>
				</a>
			</p>

			<div class="tsrb-auth-message" id="tsrb-register-message"></div>
		</div>
	</div>
</div>

<!-- Modal Riwayat Booking -->
<div id="tsrb-history-modal" class="tsrb-account-modal" style="display:none;">
	<div class="tsrb-account-modal-overlay"></div>
	<div class="tsrb-account-modal-content">
		<button type="button" class="tsrb-account-modal-close">&times;</button>
		<div id="tsrb-history-modal-body">
			<p class="tsrb-info">
				<?php esc_html_e( 'Memuat riwayat booking...', 'tribuna-studio-rent-booking' ); ?>
			</p>
		</div>
	</div>
</div>

<!-- Modal Profil User -->
<div id="tsrb-profile-modal" class="tsrb-account-modal" style="display:none;">
	<div class="tsrb-account-modal-overlay"></div>

	<div class="tsrb-account-modal-content tsrb-profile-modal-content">
		<button type="button" class="tsrb-account-modal-close">&times;</button>
		<div id="tsrb-profile-modal-body">
			<p class="tsrb-info">
				<?php esc_html_e( 'Memuat profil...', 'tribuna-studio-rent-booking' ); ?>
			</p>
		</div>
	</div>
</div>

<!-- Modal Konfirmasi Booking -->
<div id="tsrb-booking-success-modal" class="tsrb-account-modal" style="display:none;">
	<div class="tsrb-account-modal-overlay"></div>
 	<div class="tsrb-account-modal-content tsrb-booking-success-modal-content">
 		<button type="button" class="tsrb-account-modal-close">&times;</button>
 		<div id="tsrb-booking-success-body">
 			<p class="tsrb-info">
 				<?php esc_html_e( 'Memproses booking...', 'tribuna-studio-rent-booking' ); ?>
 			</p>
 		</div>
 	</div>
</div>

<!-- Modal Kebijakan Booking -->
<div id="tsrb-booking-policy-modal" class="tsrb-modal tsrb-modal-booking-policy" style="display:none;">
	<div class="tsrb-modal-backdrop"></div>
	<div class="tsrb-modal-dialog">
		<div class="tsrb-modal-header">
			<h4 class="tsrb-modal-title">
				<?php esc_html_e( 'Kebijakan Booking', 'tribuna-studio-rent-booking' ); ?>
			</h4>
			<button type="button"
					class="tsrb-modal-close"
					aria-label="<?php esc_attr_e( 'Tutup', 'tribuna-studio-rent-booking' ); ?>">
				&times;
			</button>
		</div>
		<div class="tsrb-modal-body">
			<div class="tsrb-booking-policy-body tsrb-modal-booking-policy-body">
				<?php
				if ( class_exists( 'Tribuna_Helpers' ) && method_exists( 'Tribuna_Helpers', 'get_booking_policy_html' ) ) {
					echo Tribuna_Helpers::get_booking_policy_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} else {
					esc_html_e( 'Belum ada kebijakan booking yang ditampilkan.', 'tribuna-studio-rent-booking' );
				}
				?>
			</div>
			<div class="tsrb-booking-policy-error" style="display:none;"></div>
		</div>
		<div class="tsrb-modal-footer">
			<button type="button" class="tsrb-btn tsrb-btn-secondary tsrb-modal-cancel">
				<?php esc_html_e( 'Tutup', 'tribuna-studio-rent-booking' ); ?>
			</button>
		</div>
	</div>
</div>
