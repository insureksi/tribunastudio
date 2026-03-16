<?php
/**
 * Excel exporter untuk Bookings dan Members.
 *
 * @package TribunaStudioRentBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Tribuna_Exporter
 */
class Tribuna_Exporter {

    /**
     * Export bookings ke Excel (CSV format).
     *
     * @param array $filters Filter untuk bookings.
     */
    public static function export_bookings_csv( $filters = array() ) {
        $booking_model = new Tribuna_Booking_Model();
        $studio_model  = new Tribuna_Studio_Model();

        // Ambil semua bookings sesuai filter (tanpa pagination).
        $filters['per_page'] = 9999;
        $filters['paged']    = 1;
        $bookings            = $booking_model->get_bookings( $filters );

        // Set headers untuk download CSV.
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="bookings-export-' . gmdate( 'Y-m-d-His' ) . '.csv"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        // Output stream.
        $output = fopen( 'php://output', 'w' );

        // BOM untuk Excel UTF-8.
        fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

        // Header kolom.
        $headers = array(
            'ID',
            'Customer Name',
            'Email',
            'Phone',
            'Studio',
            'Date',
            'Time',
            'Duration (hours)',
            'Total Price',
            'Coupon Code',
            'Discount',
            'Final Price',
            'Status',
            'Payment Proof',
            'Created At',
            'Google Calendar',
        );
        fputcsv( $output, $headers );

        // Data rows.
        foreach ( $bookings as $booking ) {
            $studio = $booking->studio_id ? $studio_model->get( (int) $booking->studio_id ) : null;

            $row = array(
                $booking->id,
                $booking->user_name,
                $booking->email,
                $booking->phone,
                $studio ? $studio->name : '—',
                $booking->date,
                $booking->start_time . ' - ' . $booking->end_time,
                $booking->duration,
                number_format( (float) $booking->total_price, 0, ',', '.' ),
                $booking->coupon_code ? $booking->coupon_code : '—',
                number_format( (float) $booking->discount_amount, 0, ',', '.' ),
                number_format( (float) $booking->final_price, 0, ',', '.' ),
                ucfirst( str_replace( '_', ' ', $booking->status ) ),
                $booking->payment_proof ? 'Yes' : 'No',
                $booking->created_at,
                $booking->google_calendar_url ? $booking->google_calendar_url : '—',
            );

            fputcsv( $output, $row );
        }

        fclose( $output );
        exit;
    }

    /**
     * Export members/users ke CSV dengan agregat bookings + filter.
     *
     * @param array $filters {
     *   @type string $activity     '' | 'recent' | 'dormant'.
     *   @type int    $recent_days  N hari untuk definisi "recent".
     *   @type int    $min_bookings Minimal booking count.
     *   @type string $search       Pencarian nama/email/username.
     * }
     */
    public static function export_members_csv( $filters = array() ) {
        $defaults = array(
            'activity'     => '',
            'recent_days'  => 0,
            'min_bookings' => 0,
            'search'       => '',
        );
        $filters = wp_parse_args( $filters, $defaults );

        $activity     = $filters['activity'];
        $recent_days  = (int) $filters['recent_days'];
        $min_bookings = (int) $filters['min_bookings'];
        $search_query = $filters['search'];

        $recent_days  = $recent_days > 0 ? $recent_days : 30;
        $min_bookings = $min_bookings > 0 ? $min_bookings : 0;

        // Ambil semua members (tanpa pagination) sesuai search.
        $user_args = array(
            'role'    => 'tribuna_member',
            'number'  => -1,
            'orderby' => 'registered',
            'order'   => 'DESC',
            'fields'  => array( 'ID', 'user_login', 'user_email', 'display_name', 'user_registered' ),
        );

        if ( $search_query ) {
            $user_args['search']         = '*' . $search_query . '*';
            $user_args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
        }

        $members  = get_users( $user_args );
        $user_ids = ! empty( $members ) ? wp_list_pluck( $members, 'ID' ) : array();

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

        // Apply filters: activity + min bookings.
        $today    = current_time( 'Y-m-d' );
        $filtered = array();

        foreach ( $members as $user_obj ) {
            $uid  = $user_obj->ID;
            $stat = isset( $booking_stats[ $uid ] ) ? $booking_stats[ $uid ] : null;

            $count     = $stat ? (int) $stat->booking_count : 0;
            $last_date = $stat ? $stat->last_booking_date : '';

            // Min bookings.
            if ( $min_bookings > 0 && $count < $min_bookings ) {
                continue;
            }

            // Activity filter.
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
                    if ( $is_recent ) {
                        continue;
                    }
                }
            }

            $filtered[] = $user_obj;
        }

        // Set headers untuk download CSV.
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="members-export-' . gmdate( 'Y-m-d-His' ) . '.csv"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        // Output stream.
        $output = fopen( 'php://output', 'w' );

        // BOM untuk Excel UTF-8.
        fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

        // Header kolom.
        $headers = array(
            'Member ID',
            'Username',
            'Name',
            'Email',
            'WhatsApp',
            'Registered',
            'Bookings',
            'Last Booking',
            'Total Revenue',
            'Tags',
            'Note',
        );
        fputcsv( $output, $headers );

        // Data rows.
        foreach ( $filtered as $user_obj ) {
            $uid  = $user_obj->ID;
            $stat = isset( $booking_stats[ $uid ] ) ? $booking_stats[ $uid ] : null;

            $count     = $stat ? (int) $stat->booking_count : 0;
            $last_date = $stat ? $stat->last_booking_date : '';
            $revenue   = $stat ? (float) $stat->total_revenue : 0.0;

            $whatsapp   = get_user_meta( $uid, 'tsrb_whatsapp', true );
            $member_tags = get_user_meta( $uid, 'tsrb_member_tags', true );
            $member_note = get_user_meta( $uid, 'tsrb_member_note', true );

            $row = array(
                $uid,
                $user_obj->user_login,
                $user_obj->display_name,
                $user_obj->user_email,
                $whatsapp,
                $user_obj->user_registered,
                $count,
                $last_date,
                $revenue,
                $member_tags,
                $member_note,
            );

            fputcsv( $output, $row );
        }

        fclose( $output );
        exit;
    }
}
