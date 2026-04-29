<?php

defined( 'ABSPATH' ) || exit;

/**
 * Renders sitemaps scoped to a single site in the network.
 *
 * /sitemap.xml on a sub-site → render_index() (list of post-type sitemaps)
 * /sitemap-{type}.xml        → render_posttype() (flat list of URLs)
 */
class Multisite_Site_Sitemap {

	private const PER_PAGE = 50000;

	public function __construct( private readonly int $site_id ) {}

	public function render_index(): void {
		switch_to_blog( $this->site_id );

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

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

		echo '</sitemapindex>';
	}

	public function render_posttype( string $post_type, int $page = 1 ): void {
		switch_to_blog( $this->site_id );

		if ( ! post_type_exists( $post_type ) ) {
			restore_current_blog();
			status_header( 404 );
			return;
		}

		$posts = get_posts( [
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => self::PER_PAGE,
			'offset'         => ( $page - 1 ) * self::PER_PAGE,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		] );

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $posts as $post ) {
			$url        = get_permalink( $post );
			$lastmod    = get_the_modified_date( 'c', $post );
			$priority   = $this->get_priority( $post_type );
			$changefreq = $this->get_changefreq( $post_type );

			echo "\t<url>\n";
			echo "\t\t<loc>" . esc_url( $url ) . "</loc>\n";
			if ( $lastmod ) {
				echo "\t\t<lastmod>" . esc_html( $lastmod ) . "</lastmod>\n";
			}
			echo "\t\t<changefreq>" . esc_html( $changefreq ) . "</changefreq>\n";
			echo "\t\t<priority>" . esc_html( $priority ) . "</priority>\n";
			echo "\t</url>\n";
		}

		restore_current_blog();

		echo '</urlset>';
	}

	private function get_priority( string $post_type ): string {
		return match ( $post_type ) {
			'page'  => '0.8',
			'post'  => '0.6',
			default => '0.5',
		};
	}

	private function get_changefreq( string $post_type ): string {
		return match ( $post_type ) {
			'page'  => 'monthly',
			'post'  => 'weekly',
			default => 'monthly',
		};
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
