<?php
/**
 * Main plugin class
 *
 * @package StylizedAnchorLink
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main plugin class
 */
class Stylized_Anchor_Link {

    /**
     * Plugin instance.
     *
     * @var Stylized_Anchor_Link
     */
    private static $instance;

    /**
     * Get plugin instance.
     *
     * @return Stylized_Anchor_Link
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Initialize plugin.
     */
    private function init() {
        // Register block
        add_action( 'init', array( $this, 'register_block' ) );
        
        // Register CLI commands if WP-CLI is available
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            $this->register_cli_commands();
        }
    }

    /**
     * Register block.
     */
    public function register_block() {
        register_block_type( plugin_dir_path( dirname( __FILE__ ) ) . 'build' );
    }

    /**
     * Register CLI commands.
     */
    private function register_cli_commands() {
        require_once plugin_dir_path( __FILE__ ) . 'class-stylized-anchor-link-cli.php';
        WP_CLI::add_command( 'dmg-read-more', 'Stylized_Anchor_Link_CLI' );
    }
}