<?php
/**
 * Plugin Name: JetEngine Color Swatches
 * Plugin URI:  https://github.com/Angelo/jetengine-variations
 * Description: Elementor widget per mostrare gli swatch colori delle variazioni WooCommerce, compatibile con JetEngine listing e singolo prodotto. Supporta colore singolo e doppio (split diagonale).
 * Version:     1.0.1
 * Author:      Angelo
 * Text Domain: je-color-swatches
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 */

defined( 'ABSPATH' ) || exit;

define( 'JECS_VERSION',     '1.0.1' );
define( 'JECS_PLUGIN_FILE', __FILE__ );
define( 'JECS_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'JECS_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

/**
 * Main bootstrap class.
 */
final class JE_Color_Swatches {

	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', [ $this, 'init' ] );
	}

	public function init(): void {
		if ( ! $this->check_dependencies() ) {
			return;
		}

		$this->load_includes();
		$this->hook_up();
	}

	private function check_dependencies(): bool {
		$ok = true;

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-error"><p><strong>JetEngine Color Swatches</strong>: WooCommerce è richiesto.</p></div>';
			} );
			$ok = false;
		}

		if ( ! did_action( 'elementor/loaded' ) && ! class_exists( '\Elementor\Plugin' ) ) {
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-error"><p><strong>JetEngine Color Swatches</strong>: Elementor è richiesto.</p></div>';
			} );
			$ok = false;
		}

		return $ok;
	}

	private function load_includes(): void {
		require_once JECS_PLUGIN_DIR . 'includes/class-admin-settings.php';
		require_once JECS_PLUGIN_DIR . 'includes/class-swatches-renderer.php';
		require_once JECS_PLUGIN_DIR . 'includes/class-single-product.php';
		require_once JECS_PLUGIN_DIR . 'includes/class-listing-hover.php';
		// Il widget viene caricato in register_widget() per garantire che
		// Elementor (Widget_Base) sia già inizializzato.
	}

	private function hook_up(): void {
		JECS_Admin_Settings::instance();
		JECS_Swatches_Renderer::instance();
		JECS_Single_Product::instance();
		JECS_Listing_Hover::instance();

		add_action( 'elementor/widgets/register', [ $this, 'register_widget' ] );
		add_action( 'elementor/frontend/after_enqueue_styles', [ $this, 'enqueue_frontend_assets' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
	}

	public function register_widget( $widgets_manager ): void {
		require_once JECS_PLUGIN_DIR . 'includes/class-elementor-widget.php';
		$widgets_manager->register( new JECS_Elementor_Widget() );
	}

	public function enqueue_frontend_assets(): void {
		wp_enqueue_style(
			'jecs-frontend',
			JECS_PLUGIN_URL . 'assets/css/swatches-frontend.css',
			[],
			JECS_VERSION
		);
		wp_enqueue_script(
			'jecs-frontend',
			JECS_PLUGIN_URL . 'assets/js/swatches-frontend.js',
			[ 'jquery' ],
			JECS_VERSION,
			true
		);
		wp_localize_script( 'jecs-frontend', 'jecsData', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'jecs_nonce' ),
		] );
	}
}

JE_Color_Swatches::instance();
