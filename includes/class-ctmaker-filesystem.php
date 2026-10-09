<?php
/**
 * Thin wrapper around WP_Filesystem for writing inside the themes folder.
 *
 * @package ChildThemeMaker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * File operations used by the plugin.
 */
class CTMaker_Filesystem {

	/**
	 * Connected WP_Filesystem instance.
	 *
	 * @var WP_Filesystem_Base
	 */
	private $fs;

	/**
	 * Absolute local path of the themes folder.
	 *
	 * @var string
	 */
	private $local_root;

	/**
	 * Path of the themes folder as the file system transport sees it
	 * (differs from the local path for FTP and SSH transports).
	 *
	 * @var string
	 */
	private $fs_root;

	/**
	 * Wraps a connected WP_Filesystem.
	 *
	 * @param WP_Filesystem_Base $fs Connected file system.
	 */
	public function __construct( WP_Filesystem_Base $fs ) {
		$this->fs         = $fs;
		$this->local_root = untrailingslashit( get_theme_root() );
		$remote           = $fs->wp_themes_dir();
		$this->fs_root    = untrailingslashit( $remote ? $remote : $this->local_root );
	}

	/**
	 * Connects WP_Filesystem, asking for credentials when the server needs
	 * them. Prints the credentials form and returns null when it has to ask.
	 *
	 * @param string $form_url     URL the credentials form posts to.
	 * @param array  $extra_fields Request fields to carry through the form.
	 * @return CTMaker_Filesystem|null
	 */
	public static function connect( $form_url, array $extra_fields ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$context = get_theme_root();
		$creds   = request_filesystem_credentials( $form_url, '', false, $context, $extra_fields );
		if ( false === $creds ) {
			return null;
		}
		if ( ! WP_Filesystem( $creds, $context ) ) {
			request_filesystem_credentials( $form_url, '', true, $context, $extra_fields );
			return null;
		}

		global $wp_filesystem;
		return new self( $wp_filesystem );
	}

	/**
	 * Maps a local path under the themes folder to the transport path.
	 *
	 * @param string $local_path Absolute local path.
	 * @return string
	 */
	private function to_fs( $local_path ) {
		$local_path = wp_normalize_path( $local_path );
		$root       = wp_normalize_path( $this->local_root );
		if ( 0 === strpos( $local_path, $root . '/' ) ) {
			return $this->fs_root . substr( $local_path, strlen( $root ) );
		}
		return $local_path;
	}

	/**
	 * Whether a theme folder exists.
	 *
	 * @param string $slug Folder name.
	 * @return bool
	 */
	public function theme_exists( $slug ) {
		return $this->fs->exists( $this->fs_root . '/' . $slug );
	}

	/**
	 * Creates a folder and its missing parents.
	 *
	 * @param string $local_dir Absolute local path under the themes folder.
	 * @return bool
	 */
	public function mkdir_p( $local_dir ) {
		$target = $this->to_fs( untrailingslashit( $local_dir ) );
		if ( $this->fs->is_dir( $target ) ) {
			return true;
		}
		$parent = dirname( $local_dir );
		if ( $parent !== $local_dir && ! $this->fs->is_dir( $this->to_fs( $parent ) ) && ! $this->mkdir_p( $parent ) ) {
			return false;
		}
		return $this->fs->mkdir( $target, FS_CHMOD_DIR );
	}

	/**
	 * Writes a file, creating its folder.
	 *
	 * @param string $local_file Absolute local path under the themes folder.
	 * @param string $contents   File contents.
	 * @return bool
	 */
	public function put( $local_file, $contents ) {
		if ( ! $this->mkdir_p( dirname( $local_file ) ) ) {
			return false;
		}
		return $this->fs->put_contents( $this->to_fs( $local_file ), $contents, FS_CHMOD_FILE );
	}

	/**
	 * Copies a file, creating the destination folder. Never overwrites.
	 *
	 * @param string $local_from Absolute local source path.
	 * @param string $local_to   Absolute local destination path.
	 * @return bool
	 */
	public function copy( $local_from, $local_to ) {
		if ( ! $this->mkdir_p( dirname( $local_to ) ) ) {
			return false;
		}
		return $this->fs->copy( $this->to_fs( $local_from ), $this->to_fs( $local_to ), false, FS_CHMOD_FILE );
	}

	/**
	 * Whether a file exists.
	 *
	 * @param string $local_file Absolute local path.
	 * @return bool
	 */
	public function exists( $local_file ) {
		return $this->fs->exists( $this->to_fs( $local_file ) );
	}

	/**
	 * Removes a theme folder created in this request after a failed write.
	 *
	 * @param string $slug Folder name.
	 * @return void
	 */
	public function remove_theme( $slug ) {
		if ( '' !== $slug ) {
			$this->fs->delete( $this->fs_root . '/' . $slug, true );
		}
	}
}
