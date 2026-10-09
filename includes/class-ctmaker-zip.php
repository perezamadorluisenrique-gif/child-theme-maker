<?php
/**
 * Packs a child theme as a .zip that WordPress can install through
 * Appearance > Themes > Add New > Upload Theme.
 *
 * @package ChildThemeMaker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Zip builder.
 */
class CTMaker_Zip {

	/**
	 * Whether the server can build zips.
	 *
	 * @return bool
	 */
	public static function available() {
		return class_exists( 'ZipArchive' );
	}

	/**
	 * Builds a zip in the temp folder.
	 *
	 * @param string               $slug  Folder name used inside the zip.
	 * @param array<string,string> $files Relative path => contents to add.
	 * @param string               $dir   Existing folder to add recursively, or ''.
	 * @param array<string,string> $copy  Relative path => absolute source file to add.
	 * @return string|WP_Error Path of the zip file.
	 */
	public static function build( $slug, array $files, $dir = '', array $copy = array() ) {
		if ( ! self::available() ) {
			return new WP_Error( 'ctmaker_no_zip', __( 'This server cannot create .zip files (the PHP Zip extension is missing).', 'child-theme-maker' ) );
		}

		$path = wp_tempnam( $slug . '.zip' );
		$zip  = new ZipArchive();
		if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			wp_delete_file( $path );
			return new WP_Error( 'ctmaker_zip_open', __( 'Could not create the .zip file in the temporary folder.', 'child-theme-maker' ) );
		}

		$zip->addEmptyDir( $slug );

		if ( '' !== $dir && is_dir( $dir ) ) {
			$dir      = wp_normalize_path( untrailingslashit( $dir ) );
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::SELF_FIRST
			);
			foreach ( $iterator as $item ) {
				$rel = ltrim( substr( wp_normalize_path( $item->getPathname() ), strlen( $dir ) ), '/' );
				if ( '' === $rel || $item->isLink() || 0 === strpos( $rel, '.git' ) ) {
					continue;
				}
				if ( $item->isDir() ) {
					$zip->addEmptyDir( $slug . '/' . $rel );
				} else {
					$zip->addFile( $item->getPathname(), $slug . '/' . $rel );
				}
			}
		}

		foreach ( $copy as $rel => $source ) {
			if ( is_readable( $source ) ) {
				$zip->addFile( $source, $slug . '/' . $rel );
			}
		}

		foreach ( $files as $rel => $contents ) {
			$zip->addFromString( $slug . '/' . $rel, $contents );
		}

		$zip->close();
		return $path;
	}

	/**
	 * Sends a zip file to the browser and deletes it.
	 *
	 * @param string $path     Zip file.
	 * @param string $filename Download name.
	 * @return void
	 */
	public static function send( $path, $filename ) {
		// The temp folder is always local, whatever transport WP_Filesystem uses.
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		$contents = ( new WP_Filesystem_Direct( null ) )->get_contents( $path );
		wp_delete_file( $path );

		if ( false === $contents ) {
			wp_die( esc_html__( 'Could not read the .zip file.', 'child-theme-maker' ) );
		}

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Length: ' . strlen( $contents ) );
		echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary zip download.
		exit;
	}
}
