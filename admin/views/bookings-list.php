<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Variable yang diharapkan dari controller:
 * - $bookings
 * - $studios
 * - $stats
 * - $widget_stats
 * - $upcoming_bookings_admin
 * - $payment_window_seconds
 */

// Filter current.
$current_status        = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
$current_date_from     = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
$current_date_to       = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
$current_search        = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$current_orderby       = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'date';
$current_order         = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC';
$current_coupon_filter = isset( $_GET['coupon'] ) ? sanitize_text_field( wp_unslash( $_GET['coupon'] ) ) : '';
$current_coupon_code   = isset( $_GET['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_GET['coupon_code'] ) ) : '';
$current_payment_proof = isset( $_GET['payment_proof'] ) ? sanitize_text_field( wp_unslash( $_GET['payment_proof'] ) ) : '';
$current_studio_id     = isset( $_GET['studio_id'] ) ? (int) $_GET['studio_id'] : 0;

if ( ! function_exists( 'tsrb_admin_sort_link' ) ) {
	function tsrb_admin_sort_link( $key, $label, $current_orderby, $current_order ) {
		$order = 'ASC';
		$arrow = '';

		if ( $current_orderby === $key ) {
			if ( strtoupper( $current_order ) === 'ASC' ) {
				$order = 'DESC';
				$arrow = '&darr;';
			} else {
				$order = 'ASC';
				$arrow = '&uarr;';
			}
		}

		$args = array(
			'page'    => 'tsrb-bookings',
			'orderby' => $key,
			'order'   => $order,
		);

		if ( isset( $_GET['status'] ) && '' !== $_GET['status'] ) {
			$args['status'] = sanitize_text_field( wp_unslash( $_GET['status'] ) );
		}
		if ( isset( $_GET['date_from'] ) && '' !== $_GET['date_from'] ) {
			$args['date_from'] = sanitize_text_field( wp_unslash( $_GET['date_from'] ) );
		}
		if ( isset( $_GET['date_to'] ) && '' !== $_GET['date_to'] ) {
			$args['date_to'] = sanitize_text_field( wp_unslash( $_GET['date_to'] ) );
		}
		if ( isset( $_GET['s'] ) && '' !== $_GET['s'] ) {
			$args['s'] = sanitize_text_field( wp_unslash( $_GET['s'] ) );
		}
		if ( isset( $_GET['coupon'] ) && '' !== $_GET['coupon'] ) {
			$args['coupon'] = sanitize_text_field( wp_unslash( $_GET['coupon'] ) );
		}
		if ( isset( $_GET['coupon_code'] ) && '' !== $_GET['coupon_code'] ) {
			$args['coupon_code'] = sanitize_text_field( wp_unslash( $_GET['coupon_code'] ) );
		}
		if ( isset( $_GET['payment_proof'] ) && '' !== $_GET['payment_proof'] ) {
			$args['payment_proof'] = sanitize_text_field( wp_unslash( $_GET['payment_proof'] ) );
		}
		if ( isset( $_GET['studio_id'] ) && (int) $_GET['studio_id'] > 0 ) {
			$args['studio_id'] = (int) $_GET['studio_id'];
		}

		$url = esc_url( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		echo '<a href="' . $url . '">' . esc_html( $label ) . ' ' . wp_kses_post( $arrow ) . '</a>';
	}
}

// Build export URL.
$export_args = array(
	'action' => 'tsrb_export_bookings',
);

if ( '' !== $current_status ) {
	$export_args['status'] = $current_status;
}
if ( '' !== $current_date_from ) {
	$export_args['date_from'] = $current_date_from;
}
if ( '' !== $current_date_to ) {
	$export_args['date_to'] = $current_date_to;
}
if ( '' !== $current_search ) {
	$export_args['s'] = $current_search;
}
if ( '' !== $current_coupon_filter ) {
	$export_args['coupon'] = $current_coupon_filter;
}
if ( '' !== $current_coupon_code ) {
	$export_args['coupon_code'] = $current_coupon_code;
}
if ( '' !== $current_payment_proof ) {
	$export_args['payment_proof'] = $current_payment_proof;
}
if ( $current_studio_id > 0 ) {
	$export_args['studio_id'] = $current_studio_id;
}
if ( '' !== $current_orderby ) {
	$export_args['orderby'] = $current_orderby;
}
if ( '' !== $current_order ) {
	$export_args['order'] = $current_order;
}

$export_url = esc_url( admin_url( 'admin-post.php?' . http_build_query( $export_args ) ) );

// Nonce bulk.
$bulk_action_nonce = wp_create_nonce( 'tsrb_bulk_update_bookings' );

// Stats default.
$stats_defaults = array(
	'total'         => 0,
	'pending'       => 0,
	'paid'          => 0,
	'cancelled'     => 0,
	'total_revenue' => 0,
);
$stats = isset( $stats ) && is_array( $stats ) ? wp_parse_args( $stats, $stats_defaults ) : $stats_defaults;

// Widget stats.
$widget_stats_defaults = array(
	'upcoming_7_days' => 0,
	'today'           => 0,
	'pending_payment' => 0,
	'cancelled_7_days'=> 0,
);
$widget_stats = isset( $widget_stats ) && is_array( $widget_stats ) ? wp_parse_args( $widget_stats, $widget_stats_defaults ) : $widget_stats_defaults;

// Upcoming list.
$upcoming_bookings_admin = isset( $upcoming_bookings_admin ) && is_array( $upcoming_bookings_admin ) ? $upcoming_bookings_admin : array();

// Payment window.
$payment_window_seconds = isset( $payment_window_seconds ) ? (int) $payment_window_seconds : 0;

// Helper label upcoming.
function tsrb_format_upcoming_item_label( $booking ) {
	$date_label = ! empty( $booking->date ) ? mysql2date( get_option( 'date_format' ), $booking->date ) : '';
	$time_label = trim(
		( ! empty( $booking->start_time ) ? $booking->start_time : '' ) .
		( ! empty( $booking->end_time ) ? ' - ' . $booking->end_time : '' )
	);

	$parts = array();
	if ( $date_label ) {
		$parts[] = $date_label;
	}
	if ( $time_label ) {
		$parts[] = $time_label;
	}

	return implode( ' | ', $parts );
}
?>
<div class="wrap tsrb-bookings">
	<h1><?php esc_html_e( 'Bookings', 'tribuna-studio-rent-booking' ); ?></h1>

	<?php
	$upcoming_7  = (int) $widget_stats['upcoming_7_days'];
	$today_count = (int) $widget_stats['today'];
	$pending_pay = (int) $widget_stats['pending_payment'];
	$cancel_7    = (int) $widget_stats['cancelled_7_days'];
	?>
	<div class="tsrb-bookings-header-layout">
		<div class="tsrb-bookings-stats tsrb-bookings-stats-global">
			<div class="tsrb-bookings-stat-item">
				<span class="tsrb-stat-label"><?php esc_html_e( 'Today', 'tribuna-studio-rent-booking' ); ?></span>
				<span class="tsrb-stat-value"><?php echo esc_html( $today_count ); ?></span>
			</div>
			<div class="tsrb-bookings-stat-item">
				<span class="tsrb-stat-label"><?php esc_html_e( 'Next 7 days', 'tribuna-studio-rent-booking' ); ?></span>
				<span class="tsrb-stat-value"><?php echo esc_html( $upcoming_7 ); ?></span>
			</div>
			<div class="tsrb-bookings-stat-item">
				<span class="tsrb-stat-label"><?php esc_html_e( 'Pending payments', 'tribuna-studio-rent-booking' ); ?></span>
				<span class="tsrb-stat-value"><?php echo esc_html( $pending_pay ); ?></span>
			</div>
			<div class="tsrb-bookings-stat-item">
				<span class="tsrb-stat-label"><?php esc_html_e( 'Cancelled / Expired (last 7 days)', 'tribuna-studio-rent-booking' ); ?></span>
				<span class="tsrb-stat-value"><?php echo esc_html( $cancel_7 ); ?></span>
			</div>
		</div>

		<div class="tsrb-widget tsrb-widget-upcoming-admin">
			<h2 class="tsrb-widget-title">
				<?php
				printf(
					esc_html__( 'Upcoming bookings (next %d days)', 'tribuna-studio-rent-booking' ),
					7
				);
				?>
			</h2>
			<?php if ( ! empty( $upcoming_bookings_admin ) ) : ?>
				<ul class="tsrb-upcoming-admin-list">
					<?php
					$studio_names = array();
					if ( class_exists( 'Tribuna_Studio_Model' ) ) {
						$studio_model_for_widget = new Tribuna_Studio_Model();
					}

					foreach ( $upcoming_bookings_admin as $ub ) :
						$label       = tsrb_format_upcoming_item_label( $ub );
						$studio_name = '—';

						if ( ! empty( $ub->studio_id ) && isset( $studio_model_for_widget ) ) {
							if ( ! isset( $studio_names[ $ub->studio_id ] ) ) {
								$studio_obj = $studio_model_for_widget->get( (int) $ub->studio_id );
								$studio_names[ $ub->studio_id ] = $studio_obj ? $studio_obj->name : '—';
							}
							$studio_name = $studio_names[ $ub->studio_id ];
						}

						$status_label = ucfirst( str_replace( '_', ' ', $ub->status ) );
						$status_class = 'tsrb-status-' . sanitize_html_class( $ub->status );
						?>
						<li class="tsrb-upcoming-admin-item">
							<div class="tsrb-upcoming-admin-main">
								<span class="tsrb-upcoming-admin-time">
									<?php echo esc_html( $label ); ?>
								</span>
								<span class="tsrb-upcoming-admin-studio">
									<?php echo esc_html( $studio_name ); ?>
								</span>
							</div>
							<div class="tsrb-upcoming-admin-meta">
								<span class="tsrb-upcoming-admin-customer">
									<?php echo esc_html( $ub->user_name ); ?>
								</span>
								<span class="tsrb-upcoming-admin-status tsrb-badge <?php echo esc_attr( $status_class ); ?>">
									<?php echo esc_html( $status_label ); ?>
								</span>
								<a class="button button-small tsrb-upcoming-admin-view"
								   href="<?php echo esc_url( admin_url( 'admin.php?page=tsrb-bookings&view=edit&id=' . (int) $ub->id ) ); ?>">
									<?php esc_html_e( 'View', 'tribuna-studio-rent-booking' ); ?>
								</a>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="tsrb-upcoming-admin-empty">
					<?php esc_html_e( 'No upcoming bookings in the next days.', 'tribuna-studio-rent-booking' ); ?>
				</p>
			<?php endif; ?>
		</div>
	</div>

	<div class="tsrb-bookings-stats tsrb-bookings-stats-filtered">
		<div class="tsrb-bookings-stat-item tsrb-stat-total">
			<span class="tsrb-stat-label"><?php esc_html_e( 'Total (filtered)', 'tribuna-studio-rent-booking' ); ?></span>
			<span class="tsrb-stat-value"><?php echo esc_html( (int) $stats['total'] ); ?></span>
		</div>
		<div class="tsrb-bookings-stat-item tsrb-stat-pending">
			<span class="tsrb-stat-label"><?php esc_html_e( 'Pending Payment', 'tribuna-studio-rent-booking' ); ?></span>
			<span class="tsrb-stat-value"><?php echo esc_html( (int) $stats['pending'] ); ?></span>
		</div>
		<div class="tsrb-bookings-stat-item tsrb-stat-paid">
			<span class="tsrb-stat-label"><?php esc_html_e( 'Paid / Confirmed', 'tribuna-studio-rent-booking' ); ?></span>
			<span class="tsrb-stat-value"><?php echo esc_html( (int) $stats['paid'] ); ?></span>
		</div>
		<div class="tsrb-bookings-stat-item tsrb-stat-cancelled">
			<span class="tsrb-stat-label"><?php esc_html_e( 'Cancelled', 'tribuna-studio-rent-booking' ); ?></span>
			<span class="tsrb-stat-value"><?php echo esc_html( (int) $stats['cancelled'] ); ?></span>
		</div>
	</div>

	<form method="get" action="" class="tsrb-bookings-filters-form">
		<input type="hidden" name="page" value="tsrb-bookings" />

		<div class="tsrb-bookings-toolbar tsrb-bookings-toolbar-top">
			<div class="tsrb-bookings-filters-wrap">

				<div class="tsrb-filter-row">
					<label for="tsrb-filter-status" class="screen-reader-text">
						<?php esc_html_e( 'Filter by status', 'tribuna-studio-rent-booking' ); ?>
					</label>
					<select name="status" id="tsrb-filter-status">
						<option value=""><?php esc_html_e( 'All Statuses', 'tribuna-studio-rent-booking' ); ?></option>
						<option value="pending_payment" <?php selected( $current_status, 'pending_payment' ); ?>>
							<?php esc_html_e( 'Pending Payment', 'tribuna-studio-rent-booking' ); ?>
						</option>
						<option value="paid" <?php selected( $current_status, 'paid' ); ?>>
							<?php esc_html_e( 'Paid / Confirmed', 'tribuna-studio-rent-booking' ); ?>
						</option>
						<option value="cancel_requested" <?php selected( $current_status, 'cancel_requested' ); ?>>
							<?php esc_html_e( 'Cancellation Requested', 'tribuna-studio-rent-booking' ); ?>
						</option>
						<option value="cancelled" <?php selected( $current_status, 'cancelled' ); ?>>
							<?php esc_html_e( 'Cancelled', 'tribuna-studio-rent-booking' ); ?>
						</option>
					</select>

					<label for="tsrb-filter-studio" class="screen-reader-text">
						<?php esc_html_e( 'Filter by studio', 'tribuna-studio-rent-booking' ); ?>
					</label>
					<select name="studio_id" id="tsrb-filter-studio">
						<option value="0"><?php esc_html_e( 'All Studios', 'tribuna-studio-rent-booking' ); ?></option>
						<?php if ( ! empty( $studios ) && is_array( $studios ) ) : ?>
							<?php foreach ( $studios as $studio ) : ?>
								<option value="<?php echo esc_attr( (int) $studio->id ); ?>" <?php selected( $current_studio_id, (int) $studio->id ); ?>>
									<?php echo esc_html( $studio->name ); ?>
								</option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>

					<label for="tsrb-filter-date-from" class="screen-reader-text">
						<?php esc_html_e( 'Filter from date', 'tribuna-studio-rent-booking' ); ?>
					</label>
					<input type="date" id="tsrb-filter-date-from" name="date_from" value="<?php echo esc_attr( $current_date_from ); ?>" />

					<label for="tsrb-filter-date-to" class="screen-reader-text">
						<?php esc_html_e( 'Filter to date', 'tribuna-studio-rent-booking' ); ?>
					</label>
					<input type="date" id="tsrb-filter-date-to" name="date_to" value="<?php echo esc_attr( $current_date_to ); ?>" />

					<label for="tsrb-filter-coupon" class="screen-reader-text">
						<?php esc_html_e( 'Filter by coupon usage', 'tribuna-studio-rent-booking' ); ?>
					</label>
					<select name="coupon" id="tsrb-filter-coupon">
						<option value=""><?php esc_html_e( 'All Bookings', 'tribuna-studio-rent-booking' ); ?></option>
						<option value="with" <?php selected( $current_coupon_filter, 'with' ); ?>>
							<?php esc_html_e( 'With Coupon', 'tribuna-studio-rent-booking' ); ?>
						</option>
						<option value="without" <?php selected( $current_coupon_filter, 'without' ); ?>>
							<?php esc_html_e( 'Without Coupon', 'tribuna-studio-rent-booking' ); ?>
						</option>
					</select>

					<label for="tsrb-filter-coupon-code" class="screen-reader-text">
						<?php esc_html_e( 'Filter by coupon code', 'tribuna-studio-rent-booking' ); ?>
					</label>
					<input type="text" id="tsrb-filter-coupon-code" name="coupon_code" placeholder="<?php esc_attr_e( 'Coupon code', 'tribuna-studio-rent-booking' ); ?>" value="<?php echo esc_attr( $current_coupon_code ); ?>" />

					<label for="tsrb-filter-payment" class="screen-reader-text">
						<?php esc_html_e( 'Filter by payment proof', 'tribuna-studio-rent-booking' ); ?>
					</label>
					<select name="payment_proof" id="tsrb-filter-payment">
						<option value=""><?php esc_html_e( 'All Payment Proof', 'tribuna-studio-rent-booking' ); ?></option>
						<option value="with" <?php selected( $current_payment_proof, 'with' ); ?>>
							<?php esc_html_e( 'With Payment Proof', 'tribuna-studio-rent-booking' ); ?>
						</option>
						<option value="without" <?php selected( $current_payment_proof, 'without' ); ?>>
							<?php esc_html_e( 'Without Payment Proof', 'tribuna-studio-rent-booking' ); ?>
						</option>
					</select>

					<?php submit_button( __( 'Filter', 'tribuna-studio-rent-booking' ), 'button', 'filter_action', false ); ?>
				</div>

				<div class="tsrb-search-row">
					<p class="search-box tsrb-bookings-search-box">
						<label class="screen-reader-text" for="tsrb-booking-search-input">
							<?php esc_html_e( 'Search bookings', 'tribuna-studio-rent-booking' ); ?>
						</label>
						<input type="search" id="tsrb-booking-search-input" name="s" value="<?php echo esc_attr( $current_search ); ?>" />
						<?php submit_button( __( 'Search', 'tribuna-studio-rent-booking' ), 'button', false, false ); ?>
					</p>

					<div class="tsrb-bookings-export-group">
						<a href="<?php echo $export_url; ?>" class="button button-secondary">
							<span class="dashicons dashicons-download" style="vertical-align:middle;margin-right:3px;"></span>
							<?php esc_html_e( 'Export to Excel', 'tribuna-studio-rent-booking' ); ?>
						</a>
					</div>
				</div>

			</div>
		</div>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tsrb-bookings-bulk-form">
		<?php wp_nonce_field( 'tsrb_bulk_update_bookings', 'tsrb_bulk_nonce' ); ?>
		<input type="hidden" name="action" value="tsrb_bulk_update_bookings" />

		<div class="tsrb-bookings-toolbar tsrb-bookings-toolbar-bulk tsrb-bookings-bulk-nav">
			<div class="tsrb-bookings-bulk-inner">
				<label class="screen-reader-text" for="tsrb-bulk-action">
					<?php esc_html_e( 'Select bulk action', 'tribuna-studio-rent-booking' ); ?>
				</label>
				<select name="tsrb_bulk_action" id="tsrb-bulk-action">
					<option value="-1"><?php esc_html_e( 'Bulk Actions', 'tribuna-studio-rent-booking' ); ?></option>
					<option value="set_pending"><?php esc_html_e( 'Set Pending Payment', 'tribuna-studio-rent-booking' ); ?></option>
					<option value="set_paid"><?php esc_html_e( 'Set Paid / Confirmed', 'tribuna-studio-rent-booking' ); ?></option>
					<option value="set_cancelled"><?php esc_html_e( 'Set Cancelled', 'tribuna-studio-rent-booking' ); ?></option>
				</select>
				<?php submit_button( __( 'Apply', 'tribuna-studio-rent-bookings' ), 'button action', 'tsrb_bulk_apply', false ); ?>
			</div>
		</div>

		<?php
		$server_now = Tribuna_Helpers::get_server_now_timestamp();
		?>

		<div class="tsrb-admin-table-wrap tsrb-admin-table-wrap-responsive tsrb-bookings-table-wrapper">
			<table class="widefat fixed striped tsrb-bookings-table tsrb-user-bookings-table"
				   data-server-now="<?php echo esc_attr( $server_now ); ?>"
				   data-payment-window="<?php echo esc_attr( $payment_window_seconds ); ?>">
				<thead>
					<tr>
						<td id="cb" class="manage-column column-cb check-column tsrb-col-checkbox tsrb-center">
							<input type="checkbox" class="tsrb-check-all" />
						</td>
						<th class="tsrb-col-id"><?php tsrb_admin_sort_link( 'id', __( 'ID', 'tribuna-studio-rent-booking' ), $current_orderby, $current_order ); ?></th>
						<th class="tsrb-col-customer tsrb-center-header"><?php esc_html_e( 'Customer', 'tribuna-studio-rent-booking' ); ?></th>
						<th class="tsrb-col-studio"><?php esc_html_e( 'Studio', 'tribuna-studio-rent-booking' ); ?></th>
						<th class="tsrb-col-date tsrb-nowrap"><?php tsrb_admin_sort_link( 'date', __( 'Date', 'tribuna-studio-rent-booking' ), $current_orderby, $current_order ); ?></th>
						<th class="tsrb-col-time tsrb-nowrap"><?php esc_html_e( 'Time', 'tribuna-studio-rent-booking' ); ?></th>
						<th class="tsrb-col-addons tsrb-center-header"><?php esc_html_e( 'Add-ons', 'tribuna-studio-rent-booking' ); ?></th>
						<th class="tsrb-col-coupon"><?php esc_html_e( 'Coupon', 'tribuna-studio-rent-booking' ); ?></th>
						<th class="tsrb-col-payment"><?php esc_html_e( 'Payment', 'tribuna-studio-rent-booking' ); ?></th>
						<th class="tsrb-col-total"><?php tsrb_admin_sort_link( 'final_price', __( 'Total', 'tribuna-studio-rent-booking' ), $current_orderby, $current_order ); ?></th>
						<th class="tsrb-col-status tsrb-nowrap tsrb-status-header"><?php tsrb_admin_sort_link( 'status', __( 'Status', 'tribuna-studio-rent-booking' ), $current_orderby, $current_order ); ?></th>
						<th class="tsrb-col-created tsrb-nowrap"><?php tsrb_admin_sort_link( 'created_at', __( 'Created', 'tribuna-studio-rent-booking' ), $current_orderby, $current_order ); ?></th>
						<th class="tsrb-col-timer tsrb-nowrap tsrb-timer-header"><?php esc_html_e( 'Payment timer', 'tribuna-studio-rent-booking' ); ?></th>
						<th class="tsrb-col-actions tsrb-center-header"><?php esc_html_e( 'Actions', 'tribuna-studio-rent-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! empty( $bookings ) ) : ?>
					<?php foreach ( $bookings as $booking ) : ?>
						<?php
						$row_class = '';
						if ( 'pending_payment' === $booking->status ) {
							$row_class = 'tsrb-status-pending';
						} elseif ( 'paid' === $booking->status ) {
							$row_class = 'tsrb-status-paid';
						} elseif ( 'cancel_requested' === $booking->status ) {
							$row_class = 'tsrb-status-cancel-requested';
						} elseif ( 'cancelled' === $booking->status ) {
							$row_class = 'tsrb-status-cancelled';
						}

						$has_coupon        = ! empty( $booking->coupon_code );
						$has_payment_proof = ! empty( $booking->payment_proof );

						$created_timestamp   = ! empty( $booking->created_at ) ? strtotime( $booking->created_at ) : 0;
						$payment_deadline_ts = 0;

						if ( $created_timestamp && $payment_window_seconds > 0 ) {
							$payment_deadline_ts = $created_timestamp + $payment_window_seconds;
						}

						$timer_active = ( 'pending_payment' === $booking->status ) && $payment_deadline_ts > 0;
						$remaining    = ( $timer_active && $payment_deadline_ts > $server_now ) ? ( $payment_deadline_ts - $server_now ) : 0;

						$date_display = '&mdash;';
						if ( ! empty( $booking->date ) ) {
							$date_ts = strtotime( $booking->date );
							$date_display = $date_ts ? date_i18n( 'd-m-Y', $date_ts ) : $booking->date;
						}

						$created_date_display = '';
						$created_time_display = '';
						if ( $created_timestamp ) {
							$created_date_display = date_i18n( 'd-m-Y', $created_timestamp );
							$created_time_display = date_i18n( 'H:i', $created_timestamp );
						}

						$addons_label = '';
						if ( ! empty( $booking->addons ) ) {
							$addons_label = $booking->addons;
						}
						?>
						<tr class="<?php echo esc_attr( $row_class ); ?>">
							<th scope="row"
								class="check-column tsrb-col-checkbox tsrb-center"
								data-label="<?php esc_attr_e( 'Select', 'tribuna-studio-rent-booking' ); ?>">
								<input type="checkbox" name="booking_ids[]" value="<?php echo esc_attr( $booking->id ); ?>" class="tsrb-booking-checkbox" />
							</th>
							<td class="tsrb-col-id"
								data-label="<?php esc_attr_e( 'ID', 'tribuna-studio-rent-booking' ); ?>">
								<?php echo esc_html( $booking->id ); ?>
							</td>
							<td class="tsrb-col-customer"
								data-label="<?php esc_attr_e( 'Customer', 'tribuna-studio-rent-booking' ); ?>">
								<?php echo esc_html( $booking->user_name ); ?><br>
								<span class="description tsrb-col-email"><?php echo esc_html( $booking->email ); ?></span>
							</td>
							<td class="tsrb-col-studio"
								data-label="<?php esc_attr_e( 'Studio', 'tribuna-studio-rent-booking' ); ?>">
								<?php
								if ( ! empty( $booking->studio_id ) ) {
									$studio_model = new Tribuna_Studio_Model();
									$studio       = $studio_model->get( (int) $booking->studio_id );
									echo $studio ? esc_html( $studio->name ) : '&mdash;';
								} else {
									echo '&mdash;';
								}
								?>
							</td>
							<td class="tsrb-col-date tsrb-nowrap"
								data-label="<?php esc_attr_e( 'Date', 'tribuna-studio-rent-booking' ); ?>">
								<?php echo esc_html( $date_display ); ?>
							</td>
							<td class="tsrb-col-time tsrb-nowrap"
								data-label="<?php esc_attr_e( 'Time', 'tribuna-studio-rent-booking' ); ?>">
								<?php echo esc_html( $booking->start_time . ' - ' . $booking->end_time ); ?>
							</td>
							<td class="tsrb-col-addons tsrb-center"
								data-label="<?php esc_attr_e( 'Add-ons', 'tribuna-studio-rent-booking' ); ?>">
								<?php if ( ! empty( $addons_label ) ) : ?>
									<span class="tsrb-addons-label">
										<?php echo esc_html( $addons_label ); ?>
									</span>
								<?php else : ?>
									<span class="tsrb-addons-label tsrb-addons-empty">
										<?php esc_html_e( 'No add-ons', 'tribuna-studio-rent-booking' ); ?>
									</span>
								<?php endif; ?>
							</td>
							<td class="tsrb-col-coupon"
								data-label="<?php esc_attr_e( 'Coupon', 'tribuna-studio-rent-booking' ); ?>">
								<?php if ( $has_coupon ) : ?>
									<span class="tsrb-badge tsrb-badge-coupon">
										<?php echo esc_html( $booking->coupon_code ); ?>
									</span>
								<?php else : ?>
									<span class="tsrb-badge tsrb-badge-no-coupon">
										<?php esc_html_e( 'No coupon', 'tribuna-studio-rent-booking' ); ?>
									</span>
								<?php endif; ?>
							</td>
							<td class="tsrb-col-payment tsrb-payment-indicator"
								data-label="<?php esc_attr_e( 'Payment', 'tribuna-studio-rent-booking' ); ?>">
								<?php if ( $has_payment_proof ) : ?>
									<span class="tsrb-icon tsrb-icon-has-proof" title="<?php esc_attr_e( 'Payment proof uploaded', 'tribuna-studio-rent-booking' ); ?>"></span>
								<?php else : ?>
									<span class="tsrb-icon tsrb-icon-no-proof" title="<?php esc_attr_e( 'No payment proof', 'tribuna-studio-rent-booking' ); ?>"></span>
								<?php endif; ?>
							</td>
							<td class="tsrb-col-total"
								data-label="<?php esc_attr_e( 'Total', 'tribuna-studio-rent-booking' ); ?>">
								<?php echo esc_html( Tribuna_Helpers::format_price( $booking->final_price ) ); ?>
							</td>
							<td class="tsrb-col-status tsrb-status-cell"
								data-label="<?php esc_attr_e( 'Status', 'tribuna-studio-rent-booking' ); ?>">
								<div class="tsrb-booking-status-inline-wrapper">
									<select name="status"
											class="tsrb-booking-status-select"
											data-booking-id="<?php echo esc_attr( $booking->id ); ?>">
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
								</div>
								<div class="tsrb-booking-status-actions">
									<button type="button" class="button tsrb-booking-status-save" data-booking-id="<?php echo esc_attr( $booking->id ); ?>">
										<?php esc_html_e( 'Save', 'tribuna-studio-rent-booking' ); ?>
									</button>
									<span class="tsrb-inline-status-msg" data-booking-id="<?php echo esc_attr( $booking->id ); ?>"></span>
								</div>
							</td>
							<td class="tsrb-col-created"
								data-label="<?php esc_attr_e( 'Created', 'tribuna-studio-rent-booking' ); ?>">
								<?php
								if ( $created_date_display && $created_time_display ) {
									echo '<div class="tsrb-created-date">' . esc_html( $created_date_display ) . '</div>';
									echo '<div class="tsrb-created-time">' . esc_html( $created_time_display ) . '</div>';
								} else {
									echo '&mdash;';
								}
								?>
							</td>
							<td class="tsrb-col-timer tsrb-payment-timer"
								data-label="<?php esc_attr_e( 'Payment timer', 'tribuna-studio-rent-booking' ); ?>">
								<div class="tsrb-timer-wrapper">
								<?php
								if ( ! $payment_window_seconds || ! $created_timestamp ) :
									?>
									<span class="tsrb-payment-timer-badge tsrb-payment-timer-badge--ok">
										<span class="tsrb-timer-dot"></span>
										<span class="tsrb-timer-label"><?php esc_html_e( 'N/A', 'tribuna-studio-rent-booking' ); ?></span>
									</span>
								<?php
								else :
									if ( 'paid' === $booking->status ) :
										?>
										<span class="tsrb-payment-timer-badge tsrb-payment-timer-badge--ok">
											<span class="tsrb-timer-dot"></span>
											<span class="tsrb-timer-label"><?php esc_html_e( 'Completed', 'tribuna-studio-rent-booking' ); ?></span>
										</span>
										<?php
									elseif ( 'cancelled' === $booking->status ) :
										?>
										<span class="tsrb-payment-timer-badge tsrb-payment-timer-badge--expired">
											<span class="tsrb-timer-dot"></span>
											<span class="tsrb-timer-label"><?php esc_html_e( 'Cancelled', 'tribuna-studio-rent-booking' ); ?></span>
										</span>
										<?php
									elseif ( $timer_active && $remaining > 0 ) :
										?>
										<span
											class="tsrb-payment-timer-badge tsrb-payment-timer-badge--ok js-tsrb-payment-timer"
											data-booking-id="<?php echo esc_attr( $booking->id ); ?>"
											data-expires="<?php echo esc_attr( $payment_deadline_ts ); ?>"
											data-server-now="<?php echo esc_attr( $server_now ); ?>"
										>
											<span class="tsrb-timer-dot"></span>
											<span class="tsrb-timer-label">
												<?php echo esc_html( Tribuna_Helpers::format_countdown_label( $remaining ) ); ?>
											</span>
										</span>
										<?php
									else :
										?>
										<span class="tsrb-payment-timer-badge tsrb-payment-timer-badge--expired">
											<span class="tsrb-timer-dot"></span>
											<span class="tsrb-timer-label"><?php esc_html_e( 'Expired', 'tribuna-studio-rent-booking' ); ?></span>
										</span>
										<?php
									endif;
								endif;
								?>
								</div>
							</td>
							<td class="tsrb-col-actions tsrb-actions-column tsrb-center"
								data-label="<?php esc_attr_e( 'Actions', 'tribuna-studio-rent-booking' ); ?>">
								<div class="tsrb-actions-inner">
									<div class="tsrb-actions-wrapper">
										<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=tsrb-bookings&view=edit&id=' . (int) $booking->id ) ); ?>">
											<?php esc_html_e( 'View Details', 'tribuna-studio-rent-booking' ); ?>
										</a>

										<?php if ( 'cancel_requested' === $booking->status ) : ?>
											<button type="button"
													class="button button-small tsrb-list-approve-cancel"
													data-booking-id="<?php echo esc_attr( $booking->id ); ?>">
												<?php esc_html_e( 'Approve Cancel', 'tribuna-studio-rent-booking' ); ?>
											</button>
											<button type="button"
													class="button button-small tsrb-list-reject-cancel"
													data-booking-id="<?php echo esc_attr( $booking->id ); ?>">
												<?php esc_html_e( 'Reject', 'tribuna-studio-rent-booking' ); ?>
											</button>
										<?php elseif ( in_array( $booking->status, array( 'pending_payment', 'paid' ), true ) ) : ?>
											<button type="button"
													class="button button-small tsrb-list-direct-cancel"
													data-booking-id="<?php echo esc_attr( $booking->id ); ?>">
												<?php esc_html_e( 'Cancel', 'tribuna-studio-rent-booking' ); ?>
											</button>
										<?php endif; ?>
									</div>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="14">
							<?php esc_html_e( 'No bookings found.', 'tribuna-studio-rent-booking' ); ?>
						</td>
					</tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
	</form>
</div>
