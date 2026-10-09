<?php
/**
 * Copies a parent theme's site settings to its child, so activating the
 * child does not reset the site's look: Customizer settings (including menu
 * locations), Additional CSS, widget assignments, and Site Editor templates,
 * template parts and global styles.
 *
 * @package ChildThemeMaker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parent-to-child settings copier.
 */
class CTMaker_Settings_Copier {

	/**
	 * Site Editor post types stored per theme.
	 *
	 * @var string[]
	 */
	const EDITOR_POST_TYPES = array( 'wp_template', 'wp_template_part', 'wp_global_styles' );

	/**
	 * Copies everything. Customizer settings of the child are replaced; Site
	 * Editor items the child already has are kept.
	 *
	 * @param string $parent_slug Parent stylesheet (folder name).
	 * @param string $child_slug  Child stylesheet (folder name).
	 * @return array{mods:bool,css:bool,widgets:bool,editor:int,skipped:int}
	 */
	public static function copy( $parent_slug, $child_slug ) {
		$result = array(
			'mods'    => false,
			'css'     => false,
			'widgets' => false,
			'editor'  => 0,
			'skipped' => 0,
		);

		$mods = get_option( 'theme_mods_' . $parent_slug );
		if ( ! is_array( $mods ) ) {
			$mods = array();
		}
		unset( $mods['custom_css_post_id'] );

		// Widgets: WordPress keeps the live assignment in sidebars_widgets while a
		// theme is active and in its theme mods once it is switched away.
		if ( get_stylesheet() === $parent_slug ) {
			$sidebars = get_option( 'sidebars_widgets', array() );
			if ( is_array( $sidebars ) ) {
				unset( $sidebars['array_version'] );
				$mods['sidebars_widgets'] = array(
					'time' => time(),
					'data' => $sidebars,
				);
			}
		}
		if ( ! empty( $mods['sidebars_widgets']['data'] ) && is_array( $mods['sidebars_widgets']['data'] ) ) {
			$active            = array_diff_key( $mods['sidebars_widgets']['data'], array( 'wp_inactive_widgets' => true ) );
			$result['widgets'] = (bool) array_filter( $active );
		}

		$css = wp_get_custom_css( $parent_slug );
		if ( '' !== trim( $css ) ) {
			$post = wp_update_custom_css_post( $css, array( 'stylesheet' => $child_slug ) );
			if ( $post instanceof WP_Post ) {
				$mods['custom_css_post_id'] = $post->ID;
				$result['css']              = true;
			}
		}

		if ( ! empty( $mods ) ) {
			update_option( 'theme_mods_' . $child_slug, $mods );
			$result['mods'] = true;
		}

		$editor            = self::copy_editor_items( $parent_slug, $child_slug );
		$result['editor']  = $editor['copied'];
		$result['skipped'] = $editor['skipped'];

		return $result;
	}

	/**
	 * Duplicates Site Editor templates, template parts and global styles.
	 *
	 * @param string $parent_slug Parent stylesheet.
	 * @param string $child_slug  Child stylesheet.
	 * @return array{copied:int,skipped:int}
	 */
	public static function copy_editor_items( $parent_slug, $child_slug ) {
		$copied  = 0;
		$skipped = 0;

		$posts = array();
		$page  = 1;
		do {
			$batch = get_posts(
				array(
					'post_type'      => self::EDITOR_POST_TYPES,
					'post_status'    => array( 'publish', 'draft', 'auto-draft' ),
					'posts_per_page' => 100,
					'paged'          => $page,
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- One admin action.
						array(
							'taxonomy' => 'wp_theme',
							'field'    => 'name',
							'terms'    => $parent_slug,
						),
					),
				)
			);
			$posts = array_merge( $posts, $batch );
			$full  = 100 === count( $batch );
			++$page;
		} while ( $full && $page <= 20 );

		foreach ( $posts as $post ) {
			$name = $post->post_name;
			if ( 'wp_global_styles' === $post->post_type ) {
				$name = 'wp-global-styles-' . rawurlencode( $child_slug );
			}

			if ( self::child_has( $post->post_type, $name, $child_slug ) ) {
				++$skipped;
				continue;
			}

			$new_id = wp_insert_post(
				wp_slash(
					array(
						'post_type'    => $post->post_type,
						'post_status'  => $post->post_status,
						'post_name'    => $name,
						'post_title'   => $post->post_title,
						'post_content' => $post->post_content,
						'post_excerpt' => $post->post_excerpt,
						'post_author'  => $post->post_author,
					)
				),
				true
			);
			if ( is_wp_error( $new_id ) ) {
				continue;
			}

			wp_set_post_terms( $new_id, array( $child_slug ), 'wp_theme' );

			// Core de-duplicates template slugs against the active theme while the
			// new post has no theme yet, so "index" can come out as "index-2".
			if ( get_post_field( 'post_name', $new_id ) !== $name ) {
				wp_update_post(
					array(
						'ID'        => $new_id,
						'post_name' => $name,
					)
				);
			}

			if ( 'wp_template_part' === $post->post_type ) {
				$areas = wp_get_post_terms( $post->ID, 'wp_template_part_area', array( 'fields' => 'names' ) );
				if ( is_array( $areas ) && $areas ) {
					wp_set_post_terms( $new_id, $areas, 'wp_template_part_area' );
				}
			}
			$origin = get_post_meta( $post->ID, 'origin', true );
			if ( $origin ) {
				update_post_meta( $new_id, 'origin', $origin );
			}
			++$copied;
		}

		return array(
			'copied'  => $copied,
			'skipped' => $skipped,
		);
	}

	/**
	 * Whether the child already has a Site Editor item with this slug.
	 *
	 * @param string $post_type Post type.
	 * @param string $name      Post slug.
	 * @param string $child_slug     Child stylesheet.
	 * @return bool
	 */
	private static function child_has( $post_type, $name, $child_slug ) {
		// No theme term yet means the child has no Site Editor items at all.
		if ( ! term_exists( $child_slug, 'wp_theme' ) ) {
			return false;
		}
		$found = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'draft', 'auto-draft', 'trash' ),
				// Not 'name': WP_Query treats that as a single-post lookup and drops the tax query.
				'post_name__in'  => array( $name ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- One lookup per copied item.
					array(
						'taxonomy' => 'wp_theme',
						'field'    => 'name',
						'terms'    => $child_slug,
					),
				),
			)
		);
		return ! empty( $found );
	}
}
