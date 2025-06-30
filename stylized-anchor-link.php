<?php
/**
 * Plugin Name: Stylized Anchor Link
 * Description: A Gutenberg block that allows editors to search for and choose a published post to insert as a stylized anchor link.
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: stylized-anchor-link
 * Domain Path: /languages
 */


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


define( 'STYLIZED_ANCHOR_LINK_VERSION', '1.0.0' );
define( 'STYLIZED_ANCHOR_LINK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'STYLIZED_ANCHOR_LINK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );


require_once STYLIZED_ANCHOR_LINK_PLUGIN_DIR . 'includes/class-stylized-anchor-link.php';

// Initialize
function stylized_anchor_link_init() {
    return Stylized_Anchor_Link::get_instance();
}
add_action( 'plugins_loaded', 'stylized_anchor_link_init' );

/**
 * Assets
 */
function stylized_anchor_link_register_block() {
    $script_path = plugin_dir_path( __FILE__ ) . 'build/index.js';
    $editor_style_path = plugin_dir_path( __FILE__ ) . 'build/index.css';
    $style_path = plugin_dir_path( __FILE__ ) . 'build/style-index.css';
    
    // Check if build files exist
    $script_exists = file_exists( $script_path );
    $editor_style_exists = file_exists( $editor_style_path );
    $style_exists = file_exists( $style_path );

    if ( ! $script_exists ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>Stylized Anchor Link: Build files are missing. Please run npm build.</p></div>';
        } );
    }

    register_block_type( 'stylized-anchor-link/block', array(
        'editor_script' => 'stylized-anchor-link-editor',
        'editor_style'  => 'stylized-anchor-link-editor',
        'style'         => 'stylized-anchor-link',
        'render_callback' => 'stylized_anchor_link_render_callback',
        'attributes'    => array(
            'postId'    => array(
                'type'    => 'number',
                'default' => null,
            ),
            'postTitle' => array(
                'type'    => 'string',
                'default' => '',
            ),
            'postUrl'   => array(
                'type'    => 'string',
                'default' => '',
            ),
        ),
    ) );
    
    // JS
    if ( $script_exists ) {
        wp_register_script(
            'stylized-anchor-link-editor',
            plugins_url( 'build/index.js', __FILE__ ),
            array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-api-fetch' ),
            filemtime( $script_path ),
            true
        );
    }

    // CSS
    if ( $editor_style_exists ) {
        wp_register_style(
            'stylized-anchor-link-editor',
            plugins_url( 'build/index.css', __FILE__ ),
            array( 'wp-edit-blocks' ),
            filemtime( $editor_style_path )
        );
    }
    
    if ( $style_exists ) {
        wp_register_style(
            'stylized-anchor-link',
            plugins_url( 'build/style-index.css', __FILE__ ),
            array(),
            filemtime( $style_path )
        );
    }
}
add_action( 'init', 'stylized_anchor_link_register_block' );

function stylized_anchor_link_render_callback( $attributes ) {
    $post_id = isset( $attributes['postId'] ) ? $attributes['postId'] : 0;
    
    if ( ! $post_id ) {
        return '<p class="dmg-read-more">' . esc_html__( 'Please select a post.', 'stylized-anchor-link' ) . '</p>';
    }
    
    // Get post data from attributes or fetch fresh data if needed
    $post_title = isset( $attributes['postTitle'] ) ? $attributes['postTitle'] : get_the_title( $post_id );
    $post_url = isset( $attributes['postUrl'] ) ? $attributes['postUrl'] : get_permalink( $post_id );
    
    if ( empty( $post_title ) || empty( $post_url ) ) {
        return '<p class="dmg-read-more">' . esc_html__( 'Post not found.', 'stylized-anchor-link' ) . '</p>';
    }
    
    return sprintf(
        '<p class="dmg-read-more">%s <a href="%s">%s</a></p>',
        esc_html__( 'Read More:', 'stylized-anchor-link' ),
        esc_url( $post_url ),
        esc_html( $post_title )
    );
}

// Load WP-CLI command if in CLI environment
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once STYLIZED_ANCHOR_LINK_PLUGIN_DIR . 'includes/class-stylized-anchor-link-cli.php';
    WP_CLI::add_command( 'dmg-read-more', 'Stylized_Anchor_Link_CLI' );
}
