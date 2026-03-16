<?php
/**
 * Frontend user profile view.
 *
 * @var WP_User $current_user
 * @var string  $whatsapp
 * @var string  $logout_url
 * @var bool    $updated
 * @var bool    $pw_changed
 * @var string  $error_msg
 * @var string  $username
 * @var int     $user_id
 * @var string  $registered
 * @var string  $last_login
 * @var int     $total_bookings
 * @var int     $active_bookings
 * @var string  $whatsapp_link
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Pastikan variabel dasar ada.
if ( ! isset( $current_user ) || ! ( $current_user instanceof WP_User ) ) {
	$current_user = wp_get_current_user();
}

if ( ! isset( $whatsapp ) ) {
	$whatsapp = get_user_meta( $current_user->ID, 'tsrb_whatsapp', true );
}

// Full name diambil dari display_name, yang sekarang disinkronkan dengan first_name/last_name di controller.
$full_name = $current_user->display_name;
$email     = $current_user->user_email;

// Default untuk info tambahan kalau belum diset dari controller.
if ( ! isset( $username ) ) {
	$username = $current_user->user_login;
}
if ( ! isset( $user_id ) ) {
	$user_id = $current_user->ID;
}
if ( ! isset( $registered ) ) {
	$registered = $current_user->user_registered;
}
if ( ! isset( $last_login ) ) {
	$last_login = get_user_meta( $current_user->ID, 'last_login', true );
}
if ( ! isset( $total_bookings ) ) {
	$total_bookings = 0;
}
if ( ! isset( $active_bookings ) ) {
	$active_bookings = 0;
}
if ( ! isset( $whatsapp_link ) ) {
	$whatsapp_link = '';
}

/**
 * Logout URL:
 * - Jika controller tidak mengisi $logout_url, gunakan URL kustom tanpa wp_logout_url()
 *   agar tidak muncul halaman konfirmasi.
 * - Disamakan dengan booking form: ?tsrb_logout=1 di halaman booking.
 */
if ( ! isset( $logout_url ) || ! $logout_url ) {
	$booking_page_url = get_permalink();
	$logout_url       = add_query_arg( 'tsrb_logout', '1', $booking_page_url );
}

/*
 * Status pesan:
 * - Jika dipanggil via AJAX (get_user_profile), variabel $updated, $pw_changed, $error_msg
 *   sudah diset di controller, jadi jangan di-override dari $_GET.
 * - Jika dipanggil via halaman biasa (shortcode/profile page), isi dari query string.
 */
if ( ! isset( $updated ) ) {
	$updated = isset( $_GET['updated'] ) && '1' === $_GET['updated'];
}

if ( ! isset( $pw_changed ) ) {
	$pw_changed = isset( $_GET['pw_changed'] ) && '1' === $_GET['pw_changed'];
}

if ( ! isset( $error_msg ) ) {
	$error_msg = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';
}

// Format tanggal registrasi & last login (opsional).
$registered_display = '';
if ( ! empty( $registered ) ) {
	$registered_display = date_i18n( get_option( 'date_format', 'Y-m-d' ), strtotime( $registered ) );
}

$last_login_display = '';
if ( ! empty( $last_login ) ) {
	if ( is_numeric( $last_login ) ) {
		$last_login_display = date_i18n( get_option( 'date_format', 'Y-m-d' ) . ' H:i', (int) $last_login );
	} else {
		$last_login_display = date_i18n( get_option( 'date_format', 'Y-m-d' ) . ' H:i', strtotime( $last_login ) );
	}
}
?>
<div class="tsrb-profile-wrapper tsrb-profile-wrapper--modal">

	<div class="tsrb-profile-header">
		<div class="tsrb-profile-header-main">
			<h2 class="tsrb-profile-title">
				<?php esc_html_e( 'My Profile', 'tribuna-studio-rent-booking' ); ?>
			</h2>
			<p class="tsrb-profile-subtitle">
				<?php esc_html_e( 'Update your account details and password.', 'tribuna-studio-rent-booking' ); ?>
			</p>
		</div>

		<?php if ( ! empty( $logout_url ) ) : ?>
			<div class="tsrb-profile-header-actions">
				<button type="button"
						class="button button-secondary tsrb-btn-logout tsrb-account-logout-trigger">
					<?php esc_html_e( 'Logout', 'tribuna-studio-rent-booking' ); ?>
				</button>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( $error_msg ) : ?>
		<div class="tsrb-profile-notice tsrb-profile-notice--error">
			<?php echo esc_html( $error_msg ); ?>
		</div>
	<?php endif; ?>

	<?php if ( $updated ) : ?>
		<div class="tsrb-profile-notice tsrb-profile-notice--success">
			<?php esc_html_e( 'Profile updated successfully.', 'tribuna-studio-rent-booking' ); ?>
		</div>
	<?php endif; ?>

	<?php if ( $pw_changed ) : ?>
		<div class="tsrb-profile-notice tsrb-profile-notice--success">
			<?php esc_html_e( 'Password changed successfully.', 'tribuna-studio-rent-booking' ); ?>
		</div>
	<?php endif; ?>

	<div class="tsrb-profile-account-summary tsrb-profile-card">
		<div class="tsrb-profile-account-summary-left">
			<p class="tsrb-account-id-row">
				<strong><?php esc_html_e( 'ID', 'tribuna-studio-rent-booking' ); ?>:</strong>
				<span class="tsrb-account-id-value">
					<?php echo esc_html( (string) $user_id ); ?>
				</span>
			</p>

			<p class="tsrb-account-identifier">
				<strong><?php esc_html_e( 'Username', 'tribuna-studio-rent-booking' ); ?>:</strong>
				<span class="tsrb-account-identifier-value">
					<?php echo esc_html( $username ); ?>
				</span>
			</p>

			<p class="tsrb-account-username-note">
				<?php esc_html_e( 'This username is used for login and cannot be changed.', 'tribuna-studio-rent-booking' ); ?>
			</p>

			<?php if ( $registered_display || $last_login_display ) : ?>
				<p class="tsrb-account-dates">
					<?php if ( $registered_display ) : ?>
						<span class="tsrb-account-date-item">
							<strong><?php esc_html_e( 'Register date', 'tribuna-studio-rent-booking' ); ?>:</strong>
							<?php echo esc_html( $registered_display ); ?>
						</span>
					<?php endif; ?>

					<?php if ( $last_login_display ) : ?>
						<span class="tsrb-account-date-item">
							<strong><?php esc_html_e( 'Last login', 'tribuna-studio-rent-booking' ); ?>:</strong>
							<?php echo esc_html( $last_login_display ); ?>
						</span>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</div>

		<div class="tsrb-profile-account-summary-right">
			<p class="tsrb-account-booking-stats">
				<span class="tsrb-account-booking-stat-item">
					<strong><?php esc_html_e( 'Active bookings', 'tribuna-studio-rent-booking' ); ?>:</strong>
					<?php echo esc_html( (string) $active_bookings ); ?>
				</span>
				<span class="tsrb-account-booking-stat-item">
					<strong><?php esc_html_e( 'Total bookings', 'tribuna-studio-rent-booking' ); ?>:</strong>
					<?php echo esc_html( (string) $total_bookings ); ?>
				</span>
			</p>

			<?php if ( ! empty( $whatsapp_link ) ) : ?>
				<p class="tsrb-account-whatsapp">
					<a href="<?php echo esc_url( $whatsapp_link ); ?>"
					   target="_blank"
					   rel="noopener noreferrer"
					   class="tsrb-btn tsrb-btn-secondary tsrb-btn-whatsapp">
						<?php esc_html_e( 'Chat admin via WhatsApp', 'tribuna-studio-rent-booking' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
	</div>

	<div class="tsrb-profile-columns">
		<div class="tsrb-profile-column tsrb-profile-column--left tsrb-profile-card">
			<h3 class="tsrb-profile-section-title">
				<?php esc_html_e( 'Account details', 'tribuna-studio-rent-booking' ); ?>
			</h3>

			<form
				id="tsrb-profile-form"
				class="tsrb-profile-form tsrb-profile-form-account"
				method="post"
				action=""
			>
				<input type="hidden" name="action" value="tsrb_update_profile" />
				<input type="hidden" name="tsrb_ajax" value="1" />

				<?php wp_nonce_field( 'tsrb_profile_update', 'tsrb_profile_nonce' ); ?>

				<p class="tsrb-field">
					<label for="tsrb_full_name" class="tsrb-field-label">
						<?php esc_html_e( 'Full name', 'tribuna-studio-rent-booking' ); ?>
					</label>
					<input type="text"
						   id="tsrb_full_name"
						   name="full_name"
						   class="tsrb-field-input"
						   value="<?php echo esc_attr( $full_name ); ?>"
						   required />
				</p>

				<p class="tsrb-field">
					<label for="tsrb_email" class="tsrb-field-label">
						<?php esc_html_e( 'Email', 'tribuna-studio-rent-booking' ); ?>
					</label>
					<input type="email"
						   id="tsrb_email"
						   name="email"
						   class="tsrb-field-input"
						   value="<?php echo esc_attr( $email ); ?>"
						   required />
				</p>

				<p class="tsrb-field">
					<label for="tsrb_whatsapp" class="tsrb-field-label">
						<?php esc_html_e( 'WhatsApp number', 'tribuna-studio-rent-booking' ); ?>
					</label>
					<input type="text"
						   id="tsrb_whatsapp"
						   name="whatsapp"
						   class="tsrb-field-input"
						   placeholder="+62812xxxxxxx"
						   value="<?php echo esc_attr( $whatsapp ); ?>" />
					<small class="tsrb-field-help">
						<?php esc_html_e( 'Used for booking confirmation and reminders.', 'tribuna-studio-rent-booking' ); ?>
					</small>
				</p>

				<p class="tsrb-actions">
					<button type="submit" class="tsrb-btn tsrb-btn-primary button tsrb-profile-save-btn">
						<?php esc_html_e( 'Save profile', 'tribuna-studio-rent-booking' ); ?>
					</button>
				</p>
			</form>
		</div>

		<div class="tsrb-profile-column tsrb-profile-column--right tsrb-profile-card">
			<h3 class="tsrb-profile-section-title">
				<?php esc_html_e( 'Change password', 'tribuna-studio-rent-booking' ); ?>
			</h3>

			<form
				id="tsrb-password-form"
				class="tsrb-profile-form tsrb-profile-form-password"
				method="post"
				action=""
			>
				<input type="hidden" name="action" value="tsrb_change_password" />
				<input type="hidden" name="tsrb_ajax" value="1" />

				<?php wp_nonce_field( 'tsrb_profile_update', 'tsrb_profile_nonce' ); ?>

				<p class="tsrb-field">
					<label for="tsrb_current_password" class="tsrb-field-label">
						<?php esc_html_e( 'Current password', 'tribuna-studio-rent-booking' ); ?>
					</label>
					<input type="password"
						   id="tsrb_current_password"
						   name="current_password"
						   class="tsrb-field-input"
						   autocomplete="current-password" />
				</p>

				<p class="tsrb-field">
					<label for="tsrb_new_password" class="tsrb-field-label">
						<?php esc_html_e( 'New password', 'tribuna-studio-rent-booking' ); ?>
					</label>
					<input type="password"
						   id="tsrb_new_password"
						   name="new_password"
						   class="tsrb-field-input"
						   autocomplete="new-password" />
					<small class="tsrb-field-help">
						<?php esc_html_e( 'Minimum 8 characters.', 'tribuna-studio-rent-booking' ); ?>
					</small>
				</p>

				<p class="tsrb-field">
					<label for="tsrb_new_password_confirm" class="tsrb-field-label">
						<?php esc_html_e( 'Confirm new password', 'tribuna-studio-rent-booking' ); ?>
					</label>
					<input type="password"
						   id="tsrb_new_password_confirm"
						   name="new_password_confirm"
						   class="tsrb-field-input"
						   autocomplete="new-password" />
				</p>

				<p class="tsrb-actions tsrb-actions--between">
					<button type="submit" class="tsrb-btn tsrb-btn-primary button tsrb-password-update-btn">
						<?php esc_html_e( 'Update password', 'tribuna-studio-rent-booking' ); ?>
					</button>
				</p>
			</form>
		</div>
	</div>
</div>
