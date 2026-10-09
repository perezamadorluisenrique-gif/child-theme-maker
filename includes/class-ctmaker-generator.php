<?php
/**
 * Builds the files of a child theme as strings. No file system access here,
 * so the same output can be written to wp-content/themes or packed in a zip.
 *
 * @package ChildThemeMaker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Child theme file generator.
 */
class CTMaker_Generator {

	/**
	 * Screenshot file names WordPress looks for, in its own order.
	 *
	 * @var string[]
	 */
	const SCREENSHOT_EXTENSIONS = array( 'png', 'gif', 'jpg', 'jpeg', 'webp', 'avif' );

	/**
	 * Highest theme.json schema version we write.
	 *
	 * @var int
	 */
	const MAX_THEME_JSON_VERSION = 3;

	/**
	 * Cleans one value for the style.css header: a single line that cannot
	 * close the comment block.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function clean_header_value( $value ) {
		$value = (string) $value;
		// Repeat until stable: removing one marker or tag can join the
		// characters around it into a new one ("**//" holds "*/" twice).
		do {
			$before = $value;
			$value  = wp_strip_all_tags( $value );
			$value  = preg_replace( '/[\r\n\t]+/', ' ', $value );
			$value  = str_replace( array( '*/', '/*' ), '', $value );
		} while ( $value !== $before );
		return trim( preg_replace( '/\s{2,}/', ' ', $value ) );
	}

	/**
	 * Turns a theme name into a folder name.
	 *
	 * @param string $name Theme name or slug typed by the user.
	 * @return string Lowercase letters, digits and hyphens only.
	 */
	public static function make_slug( $name ) {
		$slug = sanitize_title( remove_accents( (string) $name ) );
		$slug = preg_replace( '/[^a-z0-9-]/', '', $slug );
		return trim( preg_replace( '/-{2,}/', '-', $slug ), '-' );
	}

	/**
	 * Default child name and slug for a parent.
	 *
	 * @param string $parent_name       Parent theme name.
	 * @param string $parent_stylesheet Parent folder name.
	 * @return array{name:string,slug:string}
	 */
	public static function default_child( $parent_name, $parent_stylesheet ) {
		return array(
			/* translators: %s: parent theme name. */
			'name' => sprintf( __( '%s Child', 'child-theme-maker' ), $parent_name ),
			'slug' => self::make_slug( $parent_stylesheet . '-child' ),
		);
	}

	/**
	 * Builds style.css.
	 *
	 * @param array $child  Child fields: name, slug, description, author, author_uri, version.
	 * @param array $parent_facts Parent facts: stylesheet, requires_wp, requires_php.
	 * @return string
	 */
	public static function style_css( array $child, array $parent_facts ) {
		$headers = array(
			'Theme Name'        => $child['name'],
			'Description'       => $child['description'] ?? '',
			'Author'            => $child['author'] ?? '',
			'Author URI'        => $child['author_uri'] ?? '',
			'Template'          => $parent_facts['stylesheet'],
			'Version'           => ( $child['version'] ?? '' ) !== '' ? $child['version'] : '1.0.0',
			'Requires at least' => $parent_facts['requires_wp'] ?? '',
			'Requires PHP'      => $parent_facts['requires_php'] ?? '',
			'License'           => 'GNU General Public License v2 or later',
			'License URI'       => 'https://www.gnu.org/licenses/gpl-2.0.html',
			'Text Domain'       => $child['slug'],
			// Keeps wordpress.org from offering an "update" that would replace
			// this child with an unrelated theme that happens to share its slug.
			'Update URI'        => 'false',
		);

		$out = "/*\n";
		foreach ( $headers as $key => $value ) {
			$value = self::clean_header_value( $value );
			if ( '' === $value ) {
				continue;
			}
			$out .= $key . ': ' . $value . "\n";
		}
		$out .= "*/\n\n";
		$out .= '/* ' . self::clean_header_value( __( 'Add your custom CSS below this line.', 'child-theme-maker' ) ) . " */\n";

		return $out;
	}

	/**
	 * Builds functions.php.
	 *
	 * The stylesheet loader decides at run time, from the styles the parent
	 * actually registered, whether the parent's style.css and the child's
	 * style.css still need to be enqueued. That covers parents that enqueue
	 * their own sheet with get_template_directory_uri(), parents that use
	 * get_stylesheet_uri() (which points at the child once it is active) and
	 * parents that enqueue nothing. The child keeps working without this plugin.
	 *
	 * @param array $child Child fields: name, slug.
	 * @return string
	 */
	public static function functions_php( array $child ) {
		// Only the docblock uses the name; without * and / nothing in it can
		// end the comment, whatever the header cleaning lets through.
		$name = trim( str_replace( array( '*', '/' ), '', self::clean_header_value( $child['name'] ) ) );

		$template = file_get_contents( __DIR__ . '/templates/child-functions.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local template bundled with the plugin.
		return str_replace( array( '{{THEME_NAME}}', '{{THEME_SLUG}}' ), array( $name, self::make_slug( $child['slug'] ?? '' ) ), (string) $template );
	}

	/**
	 * Builds theme.json for a block child: an empty override that inherits
	 * everything from the parent.
	 *
	 * @param int $parent_version Parent theme.json schema version (0 if unknown).
	 * @return string
	 */
	public static function theme_json( $parent_version ) {
		$version = (int) $parent_version;
		if ( $version < 2 || $version > self::MAX_THEME_JSON_VERSION ) {
			$version = self::MAX_THEME_JSON_VERSION;
		}

		$data = array(
			'$schema'  => 'https://schemas.wp.org/wp/' . ( 2 === $version ? '6.5' : '6.6' ) . '/theme.json',
			'version'  => $version,
			'settings' => new stdClass(),
			'styles'   => new stdClass(),
		);

		return wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
	}

	/**
	 * All generated text files of a child theme.
	 *
	 * @param array $child  Child fields.
	 * @param array $parent_facts Parent facts: stylesheet, is_block, theme_json_version, requires_wp, requires_php.
	 * @return array<string,string> Relative path => contents.
	 */
	public static function files( array $child, array $parent_facts ) {
		$files = array(
			'style.css'     => self::style_css( $child, $parent_facts ),
			'functions.php' => self::functions_php( $child ),
		);

		if ( ! empty( $parent_facts['is_block'] ) ) {
			$files['theme.json'] = self::theme_json( $parent_facts['theme_json_version'] ?? 0 );
		}

		return $files;
	}

	/**
	 * Validates the child fields.
	 *
	 * @param array    $child          Child fields.
	 * @param string[] $existing_slugs Folder names already in use.
	 * @return string[] Error messages, empty when valid.
	 */
	public static function validate( array $child, array $existing_slugs ) {
		$errors = array();

		if ( '' === self::clean_header_value( $child['name'] ?? '' ) ) {
			$errors[] = __( 'Enter a name for the child theme.', 'child-theme-maker' );
		}

		$slug = $child['slug'] ?? '';
		if ( '' === $slug || self::make_slug( $slug ) !== $slug ) {
			$errors[] = __( 'The folder name can only use lowercase letters, numbers and hyphens.', 'child-theme-maker' );
		} elseif ( in_array( $slug, $existing_slugs, true ) ) {
			/* translators: %s: folder name. */
			$errors[] = sprintf( __( 'A theme folder named "%s" already exists. Pick another folder name.', 'child-theme-maker' ), $slug );
		}

		$author_uri = $child['author_uri'] ?? '';
		if ( '' !== $author_uri && ! wp_http_validate_url( $author_uri ) ) {
			$errors[] = __( 'The author URL is not a valid web address.', 'child-theme-maker' );
		}

		$version = $child['version'] ?? '';
		if ( '' !== $version && ! preg_match( '/^[0-9A-Za-z.\-+]{1,20}$/', $version ) ) {
			$errors[] = __( 'Use a version like 1.0.0.', 'child-theme-maker' );
		}

		return $errors;
	}

	/**
	 * Facts about a parent theme that the generator needs.
	 *
	 * @param WP_Theme $theme Parent theme.
	 * @return array
	 */
	public static function parent_facts( WP_Theme $theme ) {
		$json_version = 0;
		$json_file    = $theme->get_stylesheet_directory() . '/theme.json';
		if ( is_readable( $json_file ) ) {
			$decoded = wp_json_file_decode( $json_file, array( 'associative' => true ) );
			if ( is_array( $decoded ) && isset( $decoded['version'] ) ) {
				$json_version = (int) $decoded['version'];
			}
		}

		return array(
			'stylesheet'         => $theme->get_stylesheet(),
			'name'               => $theme->get( 'Name' ),
			'is_block'           => $theme->is_block_theme(),
			'theme_json_version' => $json_version,
			'requires_wp'        => (string) $theme->get( 'RequiresWP' ),
			'requires_php'       => (string) $theme->get( 'RequiresPHP' ),
			'screenshot'         => self::screenshot_file( $theme->get_stylesheet_directory() ),
		);
	}

	/**
	 * Finds the screenshot file of a theme folder.
	 *
	 * @param string $dir Theme folder.
	 * @return string File name, or '' when there is none.
	 */
	public static function screenshot_file( $dir ) {
		foreach ( self::SCREENSHOT_EXTENSIONS as $ext ) {
			if ( is_readable( $dir . '/screenshot.' . $ext ) ) {
				return 'screenshot.' . $ext;
			}
		}
		return '';
	}
}
