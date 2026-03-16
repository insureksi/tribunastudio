<?php
/**
 * Members list view (Tribuna Member role).
 *
 * File: admin/views/members-list.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Sekarang pakai capability bookings -> Manager + Admin Booking boleh akses.
if ( ! current_user_can( Tribuna_Helpers::booking_capability() ) ) {
    wp_die( esc_html__( 'You do not have permission to access this page.', 'tribuna-studio-rent-booking' ) );
}

// Pagination & search.
$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
$per_page = 50;
$offset   = ( $paged - 1 ) * $per_page;

$search_query = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

// Activity filters.
$activity     = isset( $_GET['activity'] ) ? sanitize_text_field( wp_unslash( $_GET['activity'] ) ) : '';
$recent_days  = isset( $_GET['recent_days'] ) ? (int) $_GET['recent_days'] : 30;
$min_bookings = isset( $_GET['min_bookings'] ) ? (int) $_GET['min_bookings'] : 0;

$recent_days  = $recent_days > 0 ? $recent_days : 30;
$min_bookings = $min_bookings > 0 ? $min_bookings : 0;

// Base query for members.
$args = array(
    'role'    => 'tribuna_member',
    'number'  => $per_page,
    'offset'  => $offset,
    'orderby' => 'registered',
    'order'   => 'DESC',
    'fields'  => array( 'ID', 'user_login', 'user_email', 'display_name', 'user_registered' ),
);

if ( $search_query ) {
    $args['search']         = '*' . $search_query . '*';
    $args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
}

$members      = get_users( $args );
$total_users  = count_users();
$total_member = isset( $total_users['avail_roles']['tribuna_member'] ) ? (int) $total_users['avail_roles']['tribuna_member'] : 0;
$total_pages  = $per_page > 0 ? ceil( $total_member / $per_page ) : 1;

// Build user_ids list for stats.
$user_ids = ! empty( $members ) ? wp_list_pluck( $members, 'ID' ) : array();

// Preload booking stats per user (aggregated).
$booking_stats = array();
if ( ! empty( $user_ids ) && class_exists( 'Tribuna_Booking_Model' ) ) {
    $booking_model = new Tribuna_Booking_Model();
    if ( method_exists( $booking_model, 'get_member_stats' ) ) {
        $booking_stats = $booking_model->get_member_stats(
            array(
                'user_ids'  => $user_ids,
                'status'    => 'paid',
                'date_from' => '',
                'date_to'   => '',
            )
        );
    }
}

// Apply activity/min booking filters in PHP.
$today            = current_time( 'Y-m-d' );
$filtered_members = array();

foreach ( $members as $user_obj ) {
    $uid  = $user_obj->ID;
    $stat = isset( $booking_stats[ $uid ] ) ? $booking_stats[ $uid ] : null;

    $count     = $stat ? (int) $stat->booking_count : 0;
    $last_date = $stat ? $stat->last_booking_date : '';

    // Min bookings.
    if ( $min_bookings > 0 && $count < $min_bookings ) {
        continue;
    }

    // Activity filter: recent / dormant.
    if ( $activity ) {
        $is_recent = false;
        if ( $last_date ) {
            $diff_days = ( strtotime( $today ) - strtotime( $last_date ) ) / DAY_IN_SECONDS;
            $is_recent = ( $diff_days <= $recent_days );
        }

        if ( 'recent' === $activity && ! $is_recent ) {
            continue;
        }

        if ( 'dormant' === $activity ) {
            // Dormant = tidak punya booking atau last booking lebih lama dari recent_days.
            if ( $is_recent ) {
                continue;
            }
        }
    }

    $filtered_members[] = $user_obj;
}

$members = $filtered_members;

// URL export members (mengikuti filter).
$export_members_url = esc_url(
    add_query_arg(
        array(
            'action'       => 'tsrb_export_members',
            'activity'     => $activity,
            'recent_days'  => $recent_days,
            'min_bookings' => $min_bookings,
            's'            => $search_query,
        ),
        admin_url( 'admin-post.php' )
    )
);
?>
<div class="wrap tsrb-members-page">
    <h1 class="wp-heading-inline">
        <?php esc_html_e( 'Tribuna Members', 'tribuna-studio-rent-booking' ); ?>
    </h1>

    <hr class="wp-header-end" />

    <p class="description">
        <?php esc_html_e( 'Users who registered via Tribuna booking flow with the Tribuna Member role.', 'tribuna-studio-rent-booking' ); ?>
    </p>

    <?php // Toolbar atas: Filter + Search + Export. ?>
    <div class="tsrb-members-filters">
        <form method="get" class="tsrb-members-filter-form">
            <input type="hidden" name="page" value="tsrb-members" />

            <div class="tsrb-members-toolbar">
                <div class="tsrb-members-filter-row">
                    <label for="tsrb-activity-filter">
                        <?php esc_html_e( 'Activity', 'tribuna-studio-rent-booking' ); ?>
                    </label>
                    <select name="activity" id="tsrb-activity-filter">
                        <option value=""><?php esc_html_e( 'All', 'tribuna-studio-rent-booking' ); ?></option>
                        <option value="recent" <?php selected( $activity, 'recent' ); ?>>
                            <?php esc_html_e( 'Active recently', 'tribuna-studio-rent-booking' ); ?>
                        </option>
                        <option value="dormant" <?php selected( $activity, 'dormant' ); ?>>
                            <?php esc_html_e( 'Dormant', 'tribuna-studio-rent-booking' ); ?>
                        </option>
                    </select>

                    <label for="tsrb-recent-days">
                        <?php esc_html_e( 'Recent days', 'tribuna-studio-rent-booking' ); ?>
                    </label>
                    <input
                        type="number"
                        min="1"
                        id="tsrb-recent-days"
                        name="recent_days"
                        value="<?php echo esc_attr( $recent_days ); ?>"
                    />

                    <label for="tsrb-min-bookings">
                        <?php esc_html_e( 'Min bookings', 'tribuna-studio-rent-booking' ); ?>
                    </label>
                    <input
                        type="number"
                        min="0"
                        id="tsrb-min-bookings"
                        name="min_bookings"
                        value="<?php echo esc_attr( $min_bookings ); ?>"
                    />

                    <input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'tribuna-studio-rent-booking' ); ?>" />
                </div>

                <div class="tsrb-members-search-row">
                    <p class="search-box tsrb-members-search-box">
                        <label class="screen-reader-text" for="member-search-input">
                            <?php esc_html_e( 'Search members', 'tribuna-studio-rent-booking' ); ?>
                        </label>
                        <input
                            type="search"
                            id="member-search-input"
                            name="s"
                            value="<?php echo esc_attr( $search_query ); ?>"
                            placeholder="<?php esc_attr_e( 'Search name, username, email…', 'tribuna-studio-rent-booking' ); ?>"
                        />
                        <input type="submit" class="button" value="<?php esc_attr_e( 'Search Members', 'tribuna-studio-rent-booking' ); ?>" />
                    </p>

                    <div class="tsrb-members-export-group">
                        <a href="<?php echo $export_members_url; ?>" class="button button-secondary">
                            <span class="dashicons dashicons-download" style="vertical-align:middle;margin-right:3px;"></span>
                            <?php esc_html_e( 'Export Members to Excel', 'tribuna-studio-rent-booking' ); ?>
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="tsrb-members-table-wrap">
        <table class="wp-list-table widefat fixed striped tsrb-members-table">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-username">
                        <?php esc_html_e( 'Username', 'tribuna-studio-rent-booking' ); ?>
                    </th>
                    <th scope="col" class="manage-column column-name">
                        <?php esc_html_e( 'Full Name', 'tribuna-studio-rent-booking' ); ?>
                    </th>
                    <th scope="col" class="manage-column column-email">
                        <?php esc_html_e( 'Email', 'tribuna-studio-rent-booking' ); ?>
                    </th>
                    <th scope="col" class="manage-column column-phone">
                        <?php esc_html_e( 'WhatsApp', 'tribuna-studio-rent-booking' ); ?>
                    </th>
                    <th scope="col" class="manage-column column-registered">
                        <?php esc_html_e( 'Registered', 'tribuna-studio-rent-booking' ); ?>
                    </th>
                    <th scope="col" class="manage-column column-bookings num">
                        <?php esc_html_e( 'Bookings', 'tribuna-studio-rent-booking' ); ?>
                    </th>
                    <th scope="col" class="manage-column column-last-booking">
                        <?php esc_html_e( 'Last Booking', 'tribuna-studio-rent-booking' ); ?>
                    </th>
                    <th scope="col" class="manage-column column-revenue num">
                        <?php esc_html_e( 'Total Revenue', 'tribuna-studio-rent-booking' ); ?>
                    </th>
                    <th scope="col" class="manage-column column-tags">
                        <?php esc_html_e( 'Tags / Notes', 'tribuna-studio-rent-booking' ); ?>
                    </th>
                    <th scope="col" class="manage-column column-actions">
                        <?php esc_html_e( 'Actions', 'tribuna-studio-rent-booking' ); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
            <?php if ( empty( $members ) ) : ?>
                <tr>
                    <td colspan="10">
                        <?php esc_html_e( 'No Tribuna Members found.', 'tribuna-studio-rent-booking' ); ?>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ( $members as $user_obj ) : ?>
                    <?php
                    $uid          = $user_obj->ID;
                    $stat         = isset( $booking_stats[ $uid ] ) ? $booking_stats[ $uid ] : null;
                    $count        = $stat ? (int) $stat->booking_count : 0;
                    $last_date    = $stat ? $stat->last_booking_date : '';
                    $revenue      = $stat ? $stat->total_revenue : 0;
                    $whatsapp     = get_user_meta( $uid, 'tsrb_whatsapp', true );
                    $member_tags  = get_user_meta( $uid, 'tsrb_member_tags', true );
                    $member_note  = get_user_meta( $uid, 'tsrb_member_note', true );
                    $bookings_url = add_query_arg(
                        array(
                            'page'      => 'tsrb-bookings',
                            'member_id' => $uid,
                        ),
                        admin_url( 'admin.php' )
                    );
                    $edit_user_url = get_edit_user_link( $uid );

                    $wa_link = '';
                    if ( $whatsapp ) {
                        $clean_phone = preg_replace( '/\D+/', '', $whatsapp );
                        if ( $clean_phone ) {
                            $wa_link = 'https://wa.me/' . $clean_phone;
                        }
                    }
                    ?>
                    <tr>
                        <td class="column-username" data-colname="<?php esc_attr_e( 'Username', 'tribuna-studio-rent-booking' ); ?>">
                            <strong>
                                <a href="<?php echo esc_url( $edit_user_url ); ?>">
                                    <?php echo esc_html( $user_obj->user_login ); ?>
                                </a>
                            </strong>
                        </td>
                        <td class="column-name" data-colname="<?php esc_attr_e( 'Full Name', 'tribuna-studio-rent-booking' ); ?>">
                            <?php echo esc_html( $user_obj->display_name ); ?>
                        </td>
                        <td class="column-email" data-colname="<?php esc_attr_e( 'Email', 'tribuna-studio-rent-booking' ); ?>">
                            <a href="mailto:<?php echo esc_attr( $user_obj->user_email ); ?>">
                                <?php echo esc_html( $user_obj->user_email ); ?>
                            </a>
                        </td>
                        <td class="column-phone" data-colname="<?php esc_attr_e( 'WhatsApp', 'tribuna-studio-rent-booking' ); ?>">
                            <?php echo $whatsapp ? esc_html( $whatsapp ) : '&mdash;'; ?>
                        </td>
                        <td class="column-registered" data-colname="<?php esc_attr_e( 'Registered', 'tribuna-studio-rent-booking' ); ?>">
                            <?php
                            echo esc_html(
                                mysql2date(
                                    get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
                                    $user_obj->user_registered
                                )
                            );
                            ?>
                        </td>
                        <td class="column-bookings num" data-colname="<?php esc_attr_e( 'Bookings', 'tribuna-studio-rent-booking' ); ?>">
                            <?php echo esc_html( number_format_i18n( $count ) ); ?>
                        </td>
                        <td class="column-last-booking" data-colname="<?php esc_attr_e( 'Last Booking', 'tribuna-studio-rent-booking' ); ?>">
                            <?php echo $last_date ? esc_html( $last_date ) : '&mdash;'; ?>
                        </td>
                        <td class="column-revenue num" data-colname="<?php esc_attr_e( 'Total Revenue', 'tribuna-studio-rent-booking' ); ?>">
                            <?php echo esc_html( Tribuna_Helpers::format_price( $revenue ) ); ?>
                        </td>
                        <td class="column-tags" data-colname="<?php esc_attr_e( 'Tags / Notes', 'tribuna-studio-rent-booking' ); ?>">
                            <?php if ( $member_tags ) : ?>
                                <span class="tsrb-member-tags">
                                    <?php echo esc_html( $member_tags ); ?>
                                </span><br>
                            <?php endif; ?>
                            <?php if ( $member_note ) : ?>
                                <span class="description">
                                    <?php echo esc_html( $member_note ); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="column-actions" data-colname="<?php esc_attr_e( 'Actions', 'tribuna-studio-rent-booking' ); ?>">
                            <div class="tsrb-member-actions">
                                <a href="<?php echo esc_url( $bookings_url ); ?>" class="button button-small">
                                    <?php esc_html_e( 'View Bookings', 'tribuna-studio-rent-booking' ); ?>
                                </a>

                                <?php if ( $wa_link ) : ?>
                                    <a href="<?php echo esc_url( $wa_link ); ?>" target="_blank" rel="noopener noreferrer" class="button button-small">
                                        <?php esc_html_e( 'WhatsApp', 'tribuna-studio-rent-booking' ); ?>
                                    </a>
                                <?php endif; ?>

                                <a href="<?php echo esc_url( $edit_user_url ); ?>" class="button button-small">
                                    <?php esc_html_e( 'Edit Profile', 'tribuna-studio-rent-booking' ); ?>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ( $total_pages > 1 ) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo paginate_links(
                    array(
                        'base'      => add_query_arg( 'paged', '%#%' ),
                        'format'    => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'current'   => $paged,
                        'total'     => $total_pages,
                    )
                );
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>
