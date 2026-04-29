<?php

defined( 'ABSPATH' ) || exit;

class Multisite_Sitemaps_Router {

	public function init(): void {
		add_action( 'init', [ $this, 'add_rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
		add_action( 'template_redirect', [ $this, 'handle_request' ] );
		add_action( 'network_admin_notices', [ $this, 'maybe_notice_no_permalinks' ] );
		add_action( 'admin_notices', [ $this, 'maybe_notice_no_permalinks' ] );
		add_filter( 'robots_txt', [ $this, 'add_sitemap_to_robots' ], 10, 2 );
	}

	public function add_rewrite_rules(): void {
		// /sitemap.xml → network index (main site) or site index (sub-sites)
		add_rewrite_rule(
			'^sitemap\.xml$',
			'index.php?ms_sitemap=index',
			'top'
		);

		// /sitemap-{post_type}-{page}.xml → paginated post-type sitemap
		add_rewrite_rule(
			'^sitemap-([a-z0-9_-]+)-([0-9]+)\.xml$',
			'index.php?ms_sitemap=posttype&ms_post_type=$matches[1]&ms_page=$matches[2]',
			'top'
		);

		// /sitemap-{post_type}.xml → post-type sitemap (page 1)
		add_rewrite_rule(
			'^sitemap-([a-z0-9_-]+)\.xml$',
			'index.php?ms_sitemap=posttype&ms_post_type=$matches[1]&ms_page=1',
			'top'
		);

		// Auto-flush rewrite rules on first activation or after plugin updates.
		$rules = get_option( 'rewrite_rules', [] );
		if ( ! array_key_exists( 'sitemap\.xml$', $rules ) ) {
			flush_rewrite_rules();
		}
	}

	public function add_query_vars( array $vars ): array {
		$vars[] = 'ms_sitemap';
		$vars[] = 'ms_post_type';
		$vars[] = 'ms_page';
		return $vars;
	}

	public function handle_request(): void {
		$sitemap_type = get_query_var( 'ms_sitemap' );

		if ( ! $sitemap_type ) {
			return;
		}

		// Tell caching plugins not to cache sitemap responses.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow' );
		header( 'Cache-Control: public, max-age=3600' );

		switch ( $sitemap_type ) {
			case 'index':
				if ( is_main_site() ) {
					( new Multisite_Network_Sitemap() )->render();
				} else {
					( new Multisite_Site_Sitemap( get_current_blog_id() ) )->render_index();
				}
				break;

			case 'posttype':
				$post_type = sanitize_key( get_query_var( 'ms_post_type' ) );
				$page      = max( 1, (int) get_query_var( 'ms_page' ) );

				if ( ! $post_type ) {
					status_header( 404 );
					break;
				}

				( new Multisite_Site_Sitemap( get_current_blog_id() ) )->render_posttype( $post_type, $page );
				break;

			default:
				status_header( 404 );
		}

		exit;
	}

	public function add_sitemap_to_robots( string $output, bool $public ): string {
		if ( ! $public ) {
			return $output;
		}
		return $output . "\nSitemap: " . get_home_url( null, '/sitemap.xml' ) . "\n";
	}

	public function maybe_notice_no_permalinks(): void {
		if ( get_option( 'permalink_structure' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'Multisite Sitemaps requires pretty permalinks. Please save your Permalink Settings.', 'multisite-sitemaps' )
		);
	}
}
