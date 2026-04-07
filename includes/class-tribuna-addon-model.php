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

    /**
     * Ambil beberapa add-on sekaligus berdasarkan array ID.
     *
     * Dipakai oleh invoice handler (download_invoice_html) untuk menampilkan
     * nama dan harga add-on per item pada baris Add-ons di invoice.
     *
     * Catatan: method ini sengaja mengambil add-on tanpa filter status,
     * karena invoice harus tetap bisa menampilkan add-on yang mungkin
     * sudah di-inactive-kan setelah booking dibuat.
     *
     * @param int[] $ids Array of add-on IDs. Nilai non-integer diabaikan.
     * @return array Array of row objects, urut sesuai urutan $ids. Kosong jika $ids kosong.
     */
    public function get_by_ids( $ids ) {
        global $wpdb;

        if ( empty( $ids ) || ! is_array( $ids ) ) {
            return array();
        }

        // Sanitasi: pastikan semua nilai adalah integer positif.
        $ids = array_values(
            array_filter(
                array_map( 'intval', $ids ),
                function ( $id ) {
                    return $id > 0;
                }
            )
        );

        if ( empty( $ids ) ) {
            return array();
        }

        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id IN ({$placeholders}) ORDER BY id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $ids
        );

        $rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

        return is_array( $rows ) ? $rows : array();
    }
}