<?php
/**
 * Tests for CTMaker_Generator.
 *
 * @package ChildThemeMaker
 */

use PHPUnit\Framework\TestCase;

class GeneratorTest extends TestCase {

	private function child( array $overrides = array() ) {
		return array_merge(
			array(
				'name'        => 'Twenty Twenty-One Child',
				'slug'        => 'twentytwentyone-child',
				'description' => 'My changes',
				'author'      => 'Ana',
				'author_uri'  => 'https://example.com',
				'version'     => '1.0.0',
			),
			$overrides
		);
	}

	private function parent_facts( array $overrides = array() ) {
		return array_merge(
			array(
				'stylesheet'         => 'twentytwentyone',
				'is_block'           => false,
				'theme_json_version' => 0,
				'requires_wp'        => '5.3',
				'requires_php'       => '5.6',
			),
			$overrides
		);
	}

	public function test_header_value_cannot_break_out_of_the_comment() {
		$this->assertSame( 'Evil x', CTMaker_Generator::clean_header_value( "Evil */\n x" ) );
		$this->assertSame( 'a b', CTMaker_Generator::clean_header_value( "a\r\n\tb" ) );
		$this->assertSame( 'Bold', CTMaker_Generator::clean_header_value( '<b>Bold</b>' ) );
	}

	public function test_make_slug() {
		$this->assertSame( 'mi-tema-hijo', CTMaker_Generator::make_slug( 'Mi Tema  Hijo' ) );
		$this->assertSame( 'cafe-theme', CTMaker_Generator::make_slug( 'Café Theme!' ) );
		$this->assertSame( 'a-b', CTMaker_Generator::make_slug( '--a--b--' ) );
	}

	public function test_default_child() {
		$this->assertSame(
			array(
				'name' => 'Astra Child',
				'slug' => 'astra-child',
			),
			CTMaker_Generator::default_child( 'Astra', 'astra' )
		);
	}

	public function test_style_css_headers() {
		$css = CTMaker_Generator::style_css( $this->child(), $this->parent_facts() );

		$this->assertStringStartsWith( "/*\nTheme Name: Twenty Twenty-One Child\n", $css );
		$this->assertStringContainsString( "\nTemplate: twentytwentyone\n", $css );
		$this->assertStringContainsString( "\nText Domain: twentytwentyone-child\n", $css );
		$this->assertStringContainsString( "\nUpdate URI: false\n", $css );
		$this->assertStringContainsString( "\nRequires PHP: 5.6\n", $css );
		$this->assertSame( 1, substr_count( $css, "*/\n\n" ) );
	}

	public function test_style_css_skips_empty_headers_and_defaults_version() {
		$css = CTMaker_Generator::style_css(
			$this->child(
				array(
					'description' => '',
					'author_uri'  => '',
					'version'     => '',
				)
			),
			$this->parent_facts( array( 'requires_wp' => '' ) )
		);

		$this->assertStringNotContainsString( 'Description:', $css );
		$this->assertStringNotContainsString( 'Author URI:', $css );
		$this->assertStringNotContainsString( 'Requires at least:', $css );
		$this->assertStringContainsString( "\nVersion: 1.0.0\n", $css );
	}

	public function test_functions_php_is_valid_php() {
		$php = CTMaker_Generator::functions_php( $this->child( array( 'name' => 'Odd */ <?php ?> name' ) ) );

		$this->assertStringNotContainsString( '{{', $php );
		$this->assertStringContainsString( '@package twentytwentyone-child', $php );
		$this->assertStringContainsString( "add_action(\n\t'wp_enqueue_scripts'", $php );

		$file = tempnam( sys_get_temp_dir(), 'ctm' );
		file_put_contents( $file, $php );
		exec( escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $file ) . ' 2>&1', $output, $code );
		unlink( $file );
		$this->assertSame( 0, $code, implode( "\n", $output ) );
	}

	public function test_theme_json_version() {
		$this->assertSame( 3, json_decode( CTMaker_Generator::theme_json( 3 ), true )['version'] );
		$this->assertSame( 2, json_decode( CTMaker_Generator::theme_json( 2 ), true )['version'] );
		$this->assertSame( 3, json_decode( CTMaker_Generator::theme_json( 0 ), true )['version'] );
		$this->assertSame( 3, json_decode( CTMaker_Generator::theme_json( 99 ), true )['version'] );
		$this->assertStringContainsString( '"settings": {}', CTMaker_Generator::theme_json( 3 ) );
	}

	public function test_files_for_classic_and_block_parents() {
		$this->assertSame( array( 'style.css', 'functions.php' ), array_keys( CTMaker_Generator::files( $this->child(), $this->parent_facts() ) ) );
		$this->assertSame(
			array( 'style.css', 'functions.php', 'theme.json' ),
			array_keys(
				CTMaker_Generator::files(
					$this->child(),
					$this->parent_facts(
						array(
							'is_block'           => true,
							'theme_json_version' => 3,
						)
					)
				)
			)
		);
	}

	public function test_validate_accepts_good_input() {
		$this->assertSame( array(), CTMaker_Generator::validate( $this->child(), array( 'twentytwentyone' ) ) );
	}

	/**
	 * @dataProvider bad_input
	 */
	public function test_validate_rejects_bad_input( array $overrides, array $existing, $expected ) {
		$errors = CTMaker_Generator::validate( $this->child( $overrides ), $existing );
		$this->assertCount( 1, $errors );
		$this->assertStringContainsString( $expected, $errors[0] );
	}

	public function bad_input() {
		return array(
			'empty name'      => array( array( 'name' => ' */ ' ), array(), 'Enter a name' ),
			'uppercase slug'  => array( array( 'slug' => 'My-Child' ), array(), 'lowercase' ),
			'traversal slug'  => array( array( 'slug' => '../evil' ), array(), 'lowercase' ),
			'empty slug'      => array( array( 'slug' => '' ), array(), 'lowercase' ),
			'existing folder' => array( array(), array( 'twentytwentyone-child' ), 'already exists' ),
			'bad url'         => array( array( 'author_uri' => 'javascript:alert(1)' ), array(), 'not a valid' ),
			'bad version'     => array( array( 'version' => '1.0 beta' ), array(), 'version' ),
		);
	}
}
