<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tribuna_Loader
 *
 * Loader sederhana untuk mendaftarkan semua action & filter plugin.
 */
class Tribuna_Loader {

	/**
	 * @var array
	 */
	protected $actions = array();

	/**
	 * @var array
	 */
	protected $filters = array();

	/**
	 * Tambahkan action ke queue.
	 *
	 * @param string $hook          Nama hook WordPress.
	 * @param object $component     Instance objek pemilik callback.
	 * @param string $callback      Nama method yang akan dipanggil.
	 * @param int    $priority      Prioritas.
	 * @param int    $accepted_args Jumlah argumen.
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}

	/**
	 * Tambahkan filter ke queue.
	 *
	 * @param string $hook          Nama hook WordPress.
	 * @param object $component     Instance objek pemilik callback.
	 * @param string $callback      Nama method yang akan dipanggil.
	 * @param int    $priority      Prioritas.
	 * @param int    $accepted_args Jumlah argumen.
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}

	/**
	 * Registrasikan semua action & filter ke WordPress.
	 */
	public function run() {
		foreach ( $this->actions as $hook ) {
			add_action(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		foreach ( $this->filters as $hook ) {
			add_filter(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}
	}
}
