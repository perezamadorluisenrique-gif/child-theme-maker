<?php
/**
 * Lists the parent template files a child theme can override, and copies
 * them into the child.
 *
 * @package ChildThemeMaker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template override helper.
 */
class CTMaker_Templates {

	/**
	 * Most files listed for one theme.
	 *
	 * @var int
	 */
	const MAX_FILES = 1000;

	/**
	 * Folders that hold code or assets rather than templates.
	 *
	 * @var string[]
	 */
	const SKIP_DIRS = array( '.git', 'node_modules', 'vendor', 'assets', 'css', 'js', 'fonts', 'images', 'img', 'languages', 'src', 'build', 'dist', 'tests' );

	/**
	 * Relative paths of the parent's overridable files, sorted.
	 *
	 * Block themes: HTML templates and parts, patterns and style variations.
	 * Classic themes: PHP files except functions.php and the code folders a
	 * child cannot override by copying (inc/, includes/, classes/).
	 *
	 * @param WP_Theme $parent_theme Parent theme.
	 * @return string[]
	 */
	public static function overridable( WP_Theme $parent_theme ) {
		$root = wp_normalize_path( $parent_theme->get_stylesheet_directory() );
		$out  = array();

		if ( $parent_theme->is_block_theme() ) {
			$folders = array(
				'templates' => 'html',
				'parts'     => 'html',
				'patterns'  => 'php',
				'styles'    => 'json',
			);
			foreach ( $folders as $folder => $ext ) {
				foreach ( self::scan( $root, $folder, array( $ext ), 3 ) as $file ) {
					$out[] = $file;
				}
			}
		} else {
			$skip = array_merge( self::SKIP_DIRS, array( 'inc', 'includes', 'classes', 'lib' ) );
			foreach ( self::scan( $root, '', array( 'php' ), 4, $skip ) as $file ) {
				if ( 'functions.php' !== $file ) {
					$out[] = $file;
				}
			}
		}

		sort( $out );
		return array_slice( $out, 0, self::MAX_FILES );
	}

	/**
	 * Recursively lists files with the given extensions.
	 *
	 * @param string   $root  Theme root, normalized.
	 * @param string   $sub   Sub folder relative to root, '' for root.
	 * @param string[] $exts  Extensions to keep.
	 * @param int      $depth Folder levels left.
	 * @param string[] $skip  Folder names to skip.
	 * @return string[] Relative paths.
	 */
	private static function scan( $root, $sub, array $exts, $depth, array $skip = array() ) {
		$dir = '' === $sub ? $root : $root . '/' . $sub;
		if ( $depth < 0 || ! is_dir( $dir ) ) {
			return array();
		}

		$entries = scandir( $dir );
		if ( false === $entries ) {
			return array();
		}

		$out = array();
		foreach ( $entries as $entry ) {
			if ( '.' === $entry[0] ) {
				continue;
			}
			$rel  = '' === $sub ? $entry : $sub . '/' . $entry;
			$path = $root . '/' . $rel;
			if ( is_dir( $path ) ) {
				if ( ! in_array( strtolower( $entry ), $skip, true ) && ! is_link( $path ) ) {
					$out = array_merge( $out, self::scan( $root, $rel, $exts, $depth - 1, $skip ) );
				}
			} elseif ( in_array( strtolower( pathinfo( $entry, PATHINFO_EXTENSION ) ), $exts, true ) ) {
				$out[] = $rel;
			}
			if ( count( $out ) >= self::MAX_FILES ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Copies parent files into the child. Only paths from overridable() are
	 * accepted, and files the child already has are never overwritten.
	 *
	 * @param CTMaker_Filesystem $fs     File system.
	 * @param WP_Theme           $parent_theme Parent theme.
	 * @param WP_Theme           $child  Child theme.
	 * @param string[]           $files  Relative paths requested.
	 * @return array{copied:string[],existing:string[],failed:string[]}
	 */
	public static function copy( CTMaker_Filesystem $fs, WP_Theme $parent_theme, WP_Theme $child, array $files ) {
		$allowed = self::overridable( $parent_theme );
		$result  = array(
			'copied'   => array(),
			'existing' => array(),
			'failed'   => array(),
		);

		foreach ( array_unique( $files ) as $file ) {
			if ( ! in_array( $file, $allowed, true ) ) {
				$result['failed'][] = $file;
				continue;
			}
			$to = $child->get_stylesheet_directory() . '/' . $file;
			if ( $fs->exists( $to ) ) {
				$result['existing'][] = $file;
				continue;
			}
			if ( $fs->copy( $parent_theme->get_stylesheet_directory() . '/' . $file, $to ) ) {
				$result['copied'][] = $file;
			} else {
				$result['failed'][] = $file;
			}
		}

		return $result;
	}

	/**
	 * Which of the given paths the child already has.
	 *
	 * @param WP_Theme $child Child theme.
	 * @param string[] $files Relative paths.
	 * @return string[]
	 */
	public static function present_in_child( WP_Theme $child, array $files ) {
		$dir = $child->get_stylesheet_directory();
		return array_values(
			array_filter(
				$files,
				static function ( $file ) use ( $dir ) {
					return file_exists( $dir . '/' . $file );
				}
			)
		);
	}
}
