<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle custom tables creation (install / upgrade).
 */
class Tribuna_Database {

	/**
	 * Create or update tables on activation.
	 */
	public function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$bookings_table = $wpdb->prefix . 'studio_bookings';
		$coupons_table  = $wpdb->prefix . 'studio_coupons';
		$addons_table   = $wpdb->prefix . 'studio_addons';
		$studios_table  = $wpdb->prefix . 'studio_studios';
		$logs_table     = $wpdb->prefix . 'studio_booking_logs';

		$sql = array();

		// Bookings table.
		// Kolom addons_price ditambahkan setelah addons untuk menyimpan
		// total harga add-ons saat booking dibuat, sehingga invoice dapat
		// menampilkan sub-total add-ons yang akurat tanpa perlu re-query
		// tabel studio_addons (yang harganya bisa berubah di kemudian hari).
		$sql[] = "CREATE TABLE {$bookings_table} (
id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
user_id BIGINT(20) UNSIGNED NULL,
user_name VARCHAR(191) NOT NULL,
email VARCHAR(191) NOT NULL,
phone VARCHAR(50) NOT NULL,
studio_id BIGINT(20) UNSIGNED NULL,
date DATE NOT NULL,
start_time TIME NOT NULL,
end_time TIME NOT NULL,
duration INT(11) NOT NULL DEFAULT 1,
addons TEXT NULL,
addons_price DECIMAL(12,2) NOT NULL DEFAULT 0,
total_price DECIMAL(12,2) NOT NULL DEFAULT 0,
coupon_code VARCHAR(50) NULL,
discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
final_price DECIMAL(12,2) NOT NULL DEFAULT 0,
status VARCHAR(20) NOT NULL DEFAULT 'pending_payment',
payment_proof VARCHAR(255) NULL,
admin_note TEXT NULL,
google_calendar_url TEXT NULL,
channel VARCHAR(50) NOT NULL DEFAULT 'website',
refund_type VARCHAR(50) NULL,
refund_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
credit_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
cancel_reason TEXT NULL,
created_at DATETIME NOT NULL,
updated_at DATETIME NOT NULL,
PRIMARY KEY  (id),
KEY user_id (user_id),
KEY studio_id (studio_id),
KEY date_time (date, start_time, end_time),
KEY status (status),
KEY channel (channel)
) {$charset_collate};";

		// Booking logs table (activity log per booking).
		$sql[] = "CREATE TABLE {$logs_table} (
id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
booking_id BIGINT(20) UNSIGNED NOT NULL,
old_status VARCHAR(20) NULL,
new_status VARCHAR(20) NOT NULL,
changed_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
note TEXT NULL,
created_at DATETIME NOT NULL,
PRIMARY KEY  (id),
KEY booking_id (booking_id),
KEY changed_by (changed_by),
KEY status_change (old_status, new_status)
) {$charset_collate};";

		// Coupons table.
		$sql[] = "CREATE TABLE {$coupons_table} (
id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
code VARCHAR(50) NOT NULL,
type VARCHAR(20) NOT NULL DEFAULT 'fixed',
value DECIMAL(12,2) NOT NULL DEFAULT 0,
max_usage INT(11) NOT NULL DEFAULT 0,
used_count INT(11) NOT NULL DEFAULT 0,
expires_at DATETIME NULL,
status VARCHAR(20) NOT NULL DEFAULT 'active',
created_at DATETIME NOT NULL,
updated_at DATETIME NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY code (code),
KEY status (status)
) {$charset_collate};";

		// Addons table.
		$sql[] = "CREATE TABLE {$addons_table} (
id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
name VARCHAR(191) NOT NULL,
description TEXT NULL,
price DECIMAL(12,2) NOT NULL DEFAULT 0,
status VARCHAR(20) NOT NULL DEFAULT 'active',
created_at DATETIME NOT NULL,
updated_at DATETIME NOT NULL,
PRIMARY KEY  (id),
KEY status (status)
) {$charset_collate};";

		// Studios table (multi-studio).
		$sql[] = "CREATE TABLE {$studios_table} (
id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
name VARCHAR(191) NOT NULL,
slug VARCHAR(191) NOT NULL,
description TEXT NULL,
hourly_price_override DECIMAL(12,2) NULL,
gallery_image_ids TEXT NULL,
status VARCHAR(20) NOT NULL DEFAULT 'active',
created_at DATETIME NOT NULL,
updated_at DATETIME NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY slug (slug),
KEY status (status)
) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// dbDelta akan membuat tabel baru atau menambah kolom yang belum ada,
		// sehingga aman untuk upgrade di site yang sudah berjalan.
		// Kolom addons_price akan otomatis ditambahkan ke tabel existing
		// tanpa perlu DROP/RECREATE tabel.
		foreach ( $sql as $query ) {
			dbDelta( $query );
		}
	}
}