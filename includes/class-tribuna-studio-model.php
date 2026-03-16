<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Studio model – multi-studio configuration with gallery.
 */
class Tribuna_Studio_Model {

    /**
     * Table name.
     *
     * @var string
     */
    protected $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'studio_studios';
    }

    /**
     * Get all studios (for admin list).
     *
     * @return array
     */
    public function get_all() {
        global $wpdb;

        $sql = "SELECT * FROM {$this->table} ORDER BY created_at DESC";

        return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    }

    /**
     * Get active studios (for front-end).
     *
     * @return array
     */
    public function get_active() {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE status = %s ORDER BY name ASC",
            'active'
        );

        return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    }

    /**
     * Get single studio.
     *
     * @param int $id Studio ID.
     * @return object|null
     */
    public function get( $id ) {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            (int) $id
        );

        return $wpdb->get_row( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    }

    /**
     * Create studio.
     *
     * @param array $data Data.
     * @return int|false
     */
    public function create( $data ) {
        global $wpdb;

        $now = current_time( 'mysql' );

        $defaults = array(
            'name'                  => '',
            'slug'                  => '',
            'description'           => '',
            'hourly_price_override' => null,
            'gallery_image_ids'     => null,
            'status'                => 'active',
            'created_at'            => $now,
            'updated_at'            => $now,
        );

        $data = wp_parse_args( $data, $defaults );

        $inserted = $wpdb->insert( $this->table, $data );

        if ( false === $inserted ) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Update studio.
     *
     * @param int   $id   Studio ID.
     * @param array $data Data.
     * @return bool
     */
    public function update( $id, $data ) {
        global $wpdb;

        $data['updated_at'] = current_time( 'mysql' );

        $updated = $wpdb->update(
            $this->table,
            $data,
            array( 'id' => (int) $id ),
            null,
            array( '%d' )
        );

        return false !== $updated;
    }

    /**
     * Delete studio.
     *
     * @param int $id Studio ID.
     * @return bool
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
}
