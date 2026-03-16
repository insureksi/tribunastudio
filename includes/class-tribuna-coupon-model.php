<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Tribuna_Coupon_Model {

    protected $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'studio_coupons';
    }

    public function get_by_code( $code ) {
        global $wpdb;

        $now = current_time( 'mysql' );

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table}
             WHERE code = %s
             AND status = 'active'
             AND (expires_at IS NULL OR expires_at >= %s)",
            $code,
            $now
        );

        return $wpdb->get_row( $sql );
    }

    public function increment_usage( $id ) {
        global $wpdb;

        $sql = $wpdb->prepare(
            "UPDATE {$this->table}
             SET used_count = used_count + 1
             WHERE id = %d
             AND (max_usage = 0 OR used_count < max_usage)",
            (int) $id
        );

        $updated = $wpdb->query( $sql );

        return false !== $updated;
    }

    public function get_all() {
        global $wpdb;

        $sql = "SELECT * FROM {$this->table} ORDER BY created_at DESC";

        return $wpdb->get_results( $sql );
    }

    public function create( $data ) {
        global $wpdb;

        $now = current_time( 'mysql' );

        $defaults = array(
            'code'       => '',
            'type'       => 'fixed',
            'value'      => 0,
            'max_usage'  => 0,
            'used_count' => 0,
            'expires_at' => null,
            'status'     => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        );

        $data = wp_parse_args( $data, $defaults );

        $inserted = $wpdb->insert(
            $this->table,
            $data
        );

        if ( false === $inserted ) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Create or update coupon from admin.
     *
     * @param int   $id   Coupon ID (0 for new).
     * @param array $data Data.
     * @return int|bool
     */
    public function save_coupon( $id, $data ) {
        global $wpdb;

        $now = current_time( 'mysql' );

        $defaults = array(
            'code'       => '',
            'type'       => 'fixed',
            'value'      => 0,
            'max_usage'  => 0,
            'used_count' => 0,
            'expires_at' => null,
            'status'     => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        );

        $data               = wp_parse_args( $data, $defaults );
        $data['updated_at'] = $now;

        if ( $id ) {
            unset( $data['created_at'] );

            $updated = $wpdb->update(
                $this->table,
                $data,
                array( 'id' => (int) $id ),
                null,
                array( '%d' )
            );

            return false !== $updated;
        }

        $inserted = $wpdb->insert( $this->table, $data );
        if ( false === $inserted ) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Delete coupon by ID.
     *
     * @param int $id Coupon ID.
     * @return bool True on success, false on failure.
     */
    public function delete( $id ) {
        global $wpdb;

        $id = (int) $id;
        if ( $id <= 0 ) {
            return false;
        }

        $deleted = $wpdb->delete(
            $this->table,
            array( 'id' => $id ),
            array( '%d' )
        );

        return false !== $deleted;
    }
}
