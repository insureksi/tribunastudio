<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dashboard variables yang sudah disiapkan di class-tribuna-admin.php:
 * $total, $pending, $confirmed, $cancelled, $month, $revenue
 * + (baru) $quick_stats, $upcoming_bookings (opsional, jika sudah diset di controller).
 */

/**
 * ====== Revenue detail (filter rentang waktu) ======
 */

// Model booking untuk hitung revenue.
$booking_model = new Tribuna_Booking_Model();

// Tanggal hari ini (mengikuti timezone WordPress).
$today         = current_time( 'Y-m-d' );
$default_start = date( 'Y-m-d', strtotime( '-29 days', strtotime( $today ) ) );

// Ambil filter dari query string.
$range = isset( $_GET['rev_range'] ) ? sanitize_text_field( wp_unslash( $_GET['rev_range'] ) ) : 'last30';
$start = isset( $_GET['rev_start'] ) ? sanitize_text_field( wp_unslash( $_GET['rev_start'] ) ) : $default_start;
$end   = isset( $_GET['rev_end'] ) ? sanitize_text_field( wp_unslash( $_GET['rev_end'] ) ) : $today;

// Hitung start/end berdasarkan preset range jika bukan custom.
switch ( $range ) {
    case 'today':
        $start = $today;
        $end   = $today;
        break;
    case 'last7':
        $start = date( 'Y-m-d', strtotime( '-6 days', strtotime( $today ) ) );
        $end   = $today;
        break;
    case 'last30':
        $start = $default_start;
        $end   = $today;
        break;
    case 'thismonth':
        $start = date( 'Y-m-01', strtotime( $today ) );
        $end   = date( 'Y-m-t', strtotime( $today ) );
        break;
    case 'custom':
    default:
        // Gunakan input apa adanya (akan distandardisasi di bawah).
        break;
}

// Normalisasi format tanggal.
$start_dt = date( 'Y-m-d', strtotime( $start ) );
$end_dt   = date( 'Y-m-d', strtotime( $end ) );

// Query data booking paid di rentang ini.
global $wpdb;
$table = $wpdb->prefix . 'studio_bookings';

$rows = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT date, final_price
         FROM {$table}
         WHERE status = 'paid'
           AND date >= %s
           AND date <= %s",
        $start_dt,
        $end_dt
    )
); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared

$total_revenue_detail  = 0;
$total_bookings_detail = 0;
$per_day_map           = array(); // date => total revenue hari itu.

if ( ! empty( $rows ) ) {
    foreach ( $rows as $row ) {
        $amount = (float) $row->final_price;

        $total_revenue_detail  += $amount;
        $total_bookings_detail += 1;

        if ( ! isset( $per_day_map[ $row->date ] ) ) {
            $per_day_map[ $row->date ] = 0;
        }
        $per_day_map[ $row->date ] += $amount;
    }
}

// Durasi hari (minimal 1 untuk menghindari bagi nol).
$diff_days = ( strtotime( $end_dt ) - strtotime( $start_dt ) ) / DAY_IN_SECONDS + 1;
if ( $diff_days < 1 ) {
    $diff_days = 1;
}

$avg_per_booking_detail = $total_bookings_detail > 0 ? ( $total_revenue_detail / $total_bookings_detail ) : 0;
$avg_per_day_detail     = $total_revenue_detail > 0 ? ( $total_revenue_detail / $diff_days ) : 0;

// Siapkan data "Top 10 hari" berdasarkan revenue tertinggi.
$top_days = array();
if ( ! empty( $per_day_map ) ) {
    $top_days = $per_day_map;
    arsort( $top_days );                                     // Urutkan descending by nilai.
    $top_days = array_slice( $top_days, 0, 10, true );       // Ambil 10 teratas.
}

// Quick stats range (today / week / month) jika belum diset oleh controller.
if ( ! isset( $quick_stats ) || ! is_array( $quick_stats ) ) {
    $quick_stats = array(
        'today'      => $booking_model->get_stats_for_range( 'today' ),
        'this_week'  => $booking_model->get_stats_for_range( 'this_week' ),
        'this_month' => $booking_model->get_stats_for_range( 'this_month' ),
    );
}

// Upcoming bookings: fallback jika controller belum mengirimkan.
if ( ! isset( $upcoming_bookings ) ) {
    $upcoming_bookings = $booking_model->get_upcoming_bookings(
        array(
            'days_ahead' => 7,
            'limit'      => 10,
        )
    );
}
?>
<div class="wrap tsrb-dashboard">
    <h1><?php esc_html_e( 'Tribuna Studio Booking – Dashboard', 'tribuna-studio-rent-booking' ); ?></h1>

    <!-- Stat cards asli (lifetime / overall) -->
    <div class="tsrb-stats">
        <div class="tsrb-stat-card">
            <h3><?php esc_html_e( 'Total Bookings', 'tribuna-studio-rent-booking' ); ?></h3>
            <p><?php echo esc_html( $total ); ?></p>
        </div>
        <div class="tsrb-stat-card">
            <h3><?php esc_html_e( 'Pending Payment', 'tribuna-studio-rent-booking' ); ?></h3>
            <p><?php echo esc_html( $pending ); ?></p>
        </div>
        <div class="tsrb-stat-card">
            <h3><?php esc_html_e( 'Confirmed (Paid)', 'tribuna-studio-rent-booking' ); ?></h3>
            <p><?php echo esc_html( $confirmed ); ?></p>
        </div>
        <div class="tsrb-stat-card">
            <h3><?php esc_html_e( 'Cancelled', 'tribuna-studio-rent-booking' ); ?></h3>
            <p><?php echo esc_html( $cancelled ); ?></p>
        </div>
        <div class="tsrb-stat-card tsrb-stat-wide">
            <h3>
                <?php
                printf(
                    esc_html__( 'Revenue for %s', 'tribuna-studio-rent-booking' ),
                    esc_html( $month )
                );
                ?>
            </h3>
            <p><?php echo esc_html( Tribuna_Helpers::format_price( $revenue ) ); ?></p>
        </div>
    </div>

    <!-- Quick metrics per time range -->
    <div class="tsrb-dashboard-quick-metrics">
        <h2><?php esc_html_e( 'Quick metrics', 'tribuna-studio-rent-booking' ); ?></h2>

        <div class="tsrb-range-switch">
            <button type="button"
                class="button button-primary"
                data-range="today">
                <?php esc_html_e( 'Today', 'tribuna-studio-rent-booking' ); ?>
            </button>
            <button type="button"
                class="button button-secondary"
                data-range="this_week">
                <?php esc_html_e( 'This week', 'tribuna-studio-rent-booking' ); ?>
            </button>
            <button type="button"
                class="button button-secondary"
                data-range="this_month">
                <?php esc_html_e( 'This month', 'tribuna-studio-rent-booking' ); ?>
            </button>
        </div>

        <?php
        $ranges_labels = array(
            'today'      => __( 'Today', 'tribuna-studio-rent-booking' ),
            'this_week'  => __( 'This week', 'tribuna-studio-rent-booking' ),
            'this_month' => __( 'This month', 'tribuna-studio-rent-booking' ),
        );

        foreach ( $ranges_labels as $range_key => $label ) :
            $stats = isset( $quick_stats[ $range_key ] ) && is_array( $quick_stats[ $range_key ] )
                ? $quick_stats[ $range_key ]
                : array( 'total' => 0, 'pending' => 0, 'paid' => 0, 'cancelled' => 0, 'total_revenue' => 0 );
            ?>
            <div class="tsrb-range-metrics" data-range="<?php echo esc_attr( $range_key ); ?>" <?php echo ( 'today' !== $range_key ) ? 'style="display:none;"' : ''; ?>>
                <div class="tsrb-stats tsrb-stats-inline">
                    <div class="tsrb-stat-card">
                        <h3><?php esc_html_e( 'Total bookings', 'tribuna-studio-rent-booking' ); ?></h3>
                        <p><?php echo esc_html( (int) $stats['total'] ); ?></p>
                    </div>
                    <div class="tsrb-stat-card">
                        <h3><?php esc_html_e( 'Paid', 'tribuna-studio-rent-booking' ); ?></h3>
                        <p><?php echo esc_html( (int) $stats['paid'] ); ?></p>
                    </div>
                    <div class="tsrb-stat-card">
                        <h3><?php esc_html_e( 'Pending', 'tribuna-studio-rent-booking' ); ?></h3>
                        <p><?php echo esc_html( (int) $stats['pending'] ); ?></p>
                    </div>
                    <div class="tsrb-stat-card">
                        <h3><?php esc_html_e( 'Cancelled', 'tribuna-studio-rent-booking' ); ?></h3>
                        <p><?php echo esc_html( (int) $stats['cancelled'] ); ?></p>
                    </div>
                    <div class="tsrb-stat-card tsrb-stat-wide">
                        <h3>
                            <?php
                            printf(
                                /* translators: %s: range label */
                                esc_html__( 'Revenue (%s)', 'tribuna-studio-rent-booking' ),
                                esc_html( $label )
                            );
                            ?>
                        </h3>
                        <p><?php echo esc_html( Tribuna_Helpers::format_price( $stats['total_revenue'] ) ); ?></p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="tsrb-dashboard-main-layout">
        <!-- Widget Revenue detail dengan filter -->
        <div class="tsrb-widget tsrb-widget-revenue">
            <h2><?php esc_html_e( 'Revenue (detailed)', 'tribuna-studio-rent-booking' ); ?></h2>

            <form method="get" class="tsrb-revenue-filter">
                <input type="hidden" name="page" value="tsrb-dashboard" />

                <label>
                    <?php esc_html_e( 'Range', 'tribuna-studio-rent-booking' ); ?>
                    <select name="rev_range" id="tsrb-rev-range">
                        <option value="today" <?php selected( $range, 'today' ); ?>>
                            <?php esc_html_e( 'Today', 'tribuna-studio-rent-booking' ); ?>
                        </option>
                        <option value="last7" <?php selected( $range, 'last7' ); ?>>
                            <?php esc_html_e( 'Last 7 days', 'tribuna-studio-rent-booking' ); ?>
                        </option>
                        <option value="last30" <?php selected( $range, 'last30' ); ?>>
                            <?php esc_html_e( 'Last 30 days', 'tribuna-studio-rent-booking' ); ?>
                        </option>
                        <option value="thismonth" <?php selected( $range, 'thismonth' ); ?>>
                            <?php esc_html_e( 'This month', 'tribuna-studio-rent-booking' ); ?>
                        </option>
                        <option value="custom" <?php selected( $range, 'custom' ); ?>>
                            <?php esc_html_e( 'Custom', 'tribuna-studio-rent-booking' ); ?>
                        </option>
                    </select>
                </label>

                <label>
                    <?php esc_html_e( 'From', 'tribuna-studio-rent-booking' ); ?>
                    <input type="date" name="rev_start" value="<?php echo esc_attr( $start_dt ); ?>" />
                </label>

                <label>
                    <?php esc_html_e( 'To', 'tribuna-studio-rent-booking' ); ?>
                    <input type="date" name="rev_end" value="<?php echo esc_attr( $end_dt ); ?>" />
                </label>

                <button type="submit" class="button button-secondary">
                    <?php esc_html_e( 'Filter', 'tribuna-studio-rent-booking' ); ?>
                </button>
            </form>

            <div class="tsrb-revenue-summary">
                <p>
                    <strong><?php esc_html_e( 'Selected range:', 'tribuna-studio-rent-booking' ); ?></strong>
                    <?php echo esc_html( $start_dt . ' – ' . $end_dt ); ?>
                </p>

                <ul>
                    <li>
                        <strong><?php esc_html_e( 'Total revenue', 'tribuna-studio-rent-booking' ); ?>:</strong>
                        <?php echo esc_html( Tribuna_Helpers::format_price( $total_revenue_detail ) ); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e( 'Paid bookings', 'tribuna-studio-rent-booking' ); ?>:</strong>
                        <?php echo esc_html( $total_bookings_detail ); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e( 'Average / booking', 'tribuna-studio-rent-booking' ); ?>:</strong>
                        <?php echo esc_html( Tribuna_Helpers::format_price( $avg_per_booking_detail ) ); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e( 'Average / day', 'tribuna-studio-rent-booking' ); ?>:</strong>
                        <?php echo esc_html( Tribuna_Helpers::format_price( $avg_per_day_detail ) ); ?>
                    </li>
                </ul>
            </div>

            <?php if ( ! empty( $top_days ) ) : ?>
                <h3><?php esc_html_e( 'Revenue per day (Top 10)', 'tribuna-studio-rent-booking' ); ?></h3>
                <table class="widefat striped tsrb-revenue-per-day-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Date', 'tribuna-studio-rent-booking' ); ?></th>
                            <th><?php esc_html_e( 'Revenue', 'tribuna-studio-rent-booking' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $top_days as $day => $amount ) : ?>
                            <tr>
                                <td><?php echo esc_html( $day ); ?></td>
                                <td><?php echo esc_html( Tribuna_Helpers::format_price( $amount ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php esc_html_e( 'No paid bookings in this period.', 'tribuna-studio-rent-booking' ); ?></p>
            <?php endif; ?>
        </div>

        <!-- Upcoming bookings widget -->
        <div class="tsrb-widget tsrb-widget-upcoming">
            <h2>
                <?php
                printf(
                    /* translators: %d: number of days */
                    esc_html__( 'Upcoming bookings (next %d days)', 'tribuna-studio-rent-booking' ),
                    7
                );
                ?>
            </h2>

            <?php if ( ! empty( $upcoming_bookings ) ) : ?>
                <ul class="tsrb-upcoming-list">
                    <?php
                    // Preload studios for performance (optional).
                    $studio_names = array();
                    if ( class_exists( 'Tribuna_Studio_Model' ) ) {
                        $studio_model = new Tribuna_Studio_Model();
                    }

                    foreach ( $upcoming_bookings as $b ) :
                        $date_label = mysql2date( get_option( 'date_format' ), $b->date );
                        $time_label = trim( $b->start_time . ' - ' . $b->end_time );
                        $studio_name = '—';

                        if ( ! empty( $b->studio_id ) && isset( $studio_model ) ) {
                            if ( ! isset( $studio_names[ $b->studio_id ] ) ) {
                                $studio_obj = $studio_model->get( (int) $b->studio_id );
                                $studio_names[ $b->studio_id ] = $studio_obj ? $studio_obj->name : '—';
                            }
                            $studio_name = $studio_names[ $b->studio_id ];
                        }

                        $status_label = ucfirst( str_replace( '_', ' ', $b->status ) );
                        ?>
                        <li class="tsrb-upcoming-item">
                            <div class="tsrb-upcoming-main">
                                <span class="tsrb-upcoming-date">
                                    <?php echo esc_html( $date_label ); ?>
                                </span>
                                <span class="tsrb-upcoming-time">
                                    <?php echo esc_html( $time_label ); ?>
                                </span>
                                <span class="tsrb-upcoming-studio">
                                    <?php echo esc_html( $studio_name ); ?>
                                </span>
                            </div>
                            <div class="tsrb-upcoming-meta">
                                <span class="tsrb-upcoming-customer">
                                    <?php echo esc_html( $b->user_name ); ?>
                                </span>
                                <span class="tsrb-upcoming-status tsrb-badge">
                                    <?php echo esc_html( $status_label ); ?>
                                </span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p><?php esc_html_e( 'No upcoming bookings in the next days.', 'tribuna-studio-rent-booking' ); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <h2><?php esc_html_e( 'Monthly Calendar', 'tribuna-studio-rent-booking' ); ?></h2>
    <div id="tsrb-admin-calendar"></div>

    <?php include TSRB_PLUGIN_DIR . 'admin/views/calendar-popup.php'; ?>
</div>
