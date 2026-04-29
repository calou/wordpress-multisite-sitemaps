<?php

defined( 'ABSPATH' ) || exit;

/**
 * Renders the network-level sitemap index served at the main site's /sitemap.xml.
 *
 * Main site content: listed as individual post-type sitemaps.
 * Sub-sites: each linked to their own /sitemap.xml (site index).
 */
class Multisite_Network_Sitemap {

	private const PER_PAGE = 50000;

	public function render(): void {
		$sites = get_sites( [
			'public'   => 1,
			'archived' => 0,
			'spam'     => 0,
			'deleted'  => 0,
			'number'   => 1000,
		] );

		$main_site_id = get_main_site_id();

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $sites as $site ) {
			if ( (int) $site->blog_id === $main_site_id ) {
				$this->render_main_site_entries( (int) $site->blog_id );
			} else {
				$this->render_subsite_entry( (int) $site->blog_id );
			}
		}

		echo '</sitemapindex>';
	}

	/**
	 * Lists each post-type sitemap for the main site directly in the network index.
	 */
	private function render_main_site_entries( int $site_id ): void {
		switch_to_blog( $site_id );

		foreach ( $this->get_public_post_types() as $post_type ) {
			$count = (int) wp_count_posts( $post_type )->publish;
			if ( $count === 0 ) {
				continue;
			}

			$total_pages = (int) ceil( $count / self::PER_PAGE );
			$lastmod     = $this->get_posttype_lastmod( $post_type );

			for ( $page = 1; $page <= $total_pages; $page++ ) {
				$suffix = $total_pages > 1 ? "-{$page}" : '';
				$url    = get_home_url( null, "/sitemap-{$post_type}{$suffix}.xml" );
				$this->output_sitemap_entry( $url, $lastmod );
			}
		}

		restore_current_blog();
	}

	/**
	 * Links a sub-site to its own /sitemap.xml (handled by Multisite_Site_Sitemap).
	 */
	private function render_subsite_entry( int $site_id ): void {
		$url     = get_home_url( $site_id, '/sitemap.xml' );
		$lastmod = $this->get_site_lastmod( $site_id );
		$this->output_sitemap_entry( $url, $lastmod );
	}

	private function get_site_lastmod( int $site_id ): string {
		switch_to_blog( $site_id );
		$posts = get_posts( [
			'numberposts'   => 1,
			'post_status'   => 'publish',
			'orderby'       => 'modified',
			'order'         => 'DESC',
			'no_found_rows' => true,
		] );
		$lastmod = ! empty( $posts ) ? get_the_modified_date( 'c', $posts[0] ) : '';
		restore_current_blog();
		return $lastmod;
	}

	private function get_posttype_lastmod( string $post_type ): string {
		$posts = get_posts( [
			'post_type'     => $post_type,
			'numberposts'   => 1,
			'post_status'   => 'publish',
			'orderby'       => 'modified',
			'order'         => 'DESC',
			'no_found_rows' => true,
		] );
		return ! empty( $posts ) ? get_the_modified_date( 'c', $posts[0] ) : '';
	}

	private function output_sitemap_entry( string $url, string $lastmod ): void {
		echo "\t<sitemap>\n";
		echo "\t\t<loc>" . esc_url( $url ) . "</loc>\n";
		if ( $lastmod ) {
			echo "\t\t<lastmod>" . esc_html( $lastmod ) . "</lastmod>\n";
		}
		echo "\t</sitemap>\n";
	}

	private function get_public_post_types(): array {
		$types = get_post_types( [ 'public' => true ] );
		unset( $types['attachment'] );
		return array_values( $types );
	}
}
