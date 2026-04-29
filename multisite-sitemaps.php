<?php
/**
 * Plugin Name:       Multisite Sitemaps
 * Description:       Generates sitemap.xml for WordPress multisite networks.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      8.0
 * Network:           true
 * License:           GPL-2.0+
 */

defined( 'ABSPATH' ) || exit;

define( 'MULTISITE_SITEMAPS_FILE', __FILE__ );
define( 'MULTISITE_SITEMAPS_DIR', plugin_dir_path( __FILE__ ) );

if ( ! is_multisite() ) {
	add_action( 'admin_notices', static function () {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Multisite Sitemaps requires WordPress Multisite.', 'multisite-sitemaps' )
		);
	} );
	return;
}

require_once MULTISITE_SITEMAPS_DIR . 'includes/class-sitemap-router.php';
require_once MULTISITE_SITEMAPS_DIR . 'includes/class-network-sitemap.php';
require_once MULTISITE_SITEMAPS_DIR . 'includes/class-site-sitemap.php';

// Disable the core WP sitemap so ours is the single source of truth.
add_filter( 'wp_sitemaps_enabled', '__return_false' );

add_action( 'plugins_loaded', static function () {
	( new Multisite_Sitemaps_Router() )->init();
} );
