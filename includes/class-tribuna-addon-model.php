<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add-on model – custom table {prefix}studio_addons.
 */
class Tribuna_Addon_Model {

    protected $table;

    public function __construct() {
        global $wpdb;
        // Pastikan ini SAMA dengan nama tabel di database Anda
        // Misal: wp_studio_addons
        $this->table = $wpdb->prefix . 'studio_addons';
    }

    /**
     * Insert add-on baru.
     */
    public function create( $data ) {
        global $wpdb;

        $defaults = array(
            'name'        => '',
            'description' => '',
            'price'       => 0,
            'status'      => 'active', // active / inactive
            'created_at'  => current_time( 'mysql' ),
            'updated_at'  => current_time( 'mysql' ),
        );

        $data = wp_parse_args( $data, $defaults );

        $inserted = $wpdb->insert(
            $this->table,
            $data
        );

        if ( false === $inserted ) {
            // debug jika perlu: error_log( 'Add-on insert error: ' . $wpdb->last_error );
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Update add-on.
     */
    public function update( $id, $data ) {
        global $wpdb;

        $data['updated_at'] = current_time( 'mysql' );

        $updated = $wpdb->update(
            $this->table,
            $data,
            array( 'id' => (int) $id )
        );

        return false !== $updated;
    }

    /**
     * Delete add-on.
     */
    public function delete( $id ) {
        global $wpdb;

        $deleted = $wpdb->delete(
            $this->table,
            array( 'id' => (int) $id ),
            array( '%d' )
        );

        return false !== $deleted;
    }

    /**
     * Get satu add-on.
     */
    public function get( $id ) {
        global $wpdb;

        $sql = "SELECT * FROM {$this->table} WHERE id = %d";
        $sql = $wpdb->prepare( $sql, (int) $id );

        return $wpdb->get_row( $sql );
    }

    /**
     * Semua add-on (untuk list admin).
     */
    public function get_all() {
        global $wpdb;

        $sql = "SELECT * FROM {$this->table} ORDER BY id ASC";
        return $wpdb->get_results( $sql );
    }

    /**
     * Add-ons aktif (untuk frontend).
     * HANYA status = 'active', jadi yang sudah dihapus / inactive tidak terbaca.
     */
    public function get_active() {
        global $wpdb;

        $sql = "SELECT * FROM {$this->table} WHERE status = %s ORDER BY id ASC";
        $sql = $wpdb->prepare( $sql, 'active' );

        return $wpdb->get_results( $sql );
    }
}
