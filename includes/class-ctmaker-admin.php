<?php
/**
 * Appearance > Child Theme screen.
 *
 * @package ChildThemeMaker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin screen and request handlers.
 */
class CTMaker_Admin {

	const PAGE       = 'child-theme-maker';
	const CAPABILITY = 'install_themes';

	/**
	 * Hook suffix of the screen.
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * Notices to print: list of [type, html].
	 *
	 * @var array<int,array{0:string,1:string}>
	 */
	private $notices = array();

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_ctmaker_download_new', array( $this, 'download_new' ) );
		add_action( 'admin_post_ctmaker_download_child', array( $this, 'download_child' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CTMAKER_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Adds the screen under Appearance.
	 *
	 * @return void
	 */
	public function menu() {
		$this->hook = (string) add_theme_page(
			__( 'Child Theme Maker', 'child-theme-maker' ),
			__( 'Child Theme', 'child-theme-maker' ),
			self::CAPABILITY,
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Adds a link to the screen on the Plugins list.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		if ( current_user_can( self::CAPABILITY ) ) {
			array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Create child theme', 'child-theme-maker' ) . '</a>' );
		}
		return $links;
	}

	/**
	 * Loads CSS and JS on the screen only.
	 *
	 * @param string $hook Current hook suffix.
	 * @return void
	 */
	public function assets( $hook ) {
		if ( $hook !== $this->hook ) {
			return;
		}
		wp_enqueue_style( 'ctmaker-admin', CTMAKER_URL . 'assets/admin.css', array(), CTMAKER_VERSION );
		wp_enqueue_script( 'ctmaker-admin', CTMAKER_URL . 'assets/admin.js', array(), CTMAKER_VERSION, true );
	}

	/**
	 * Screen URL.
	 *
	 * @param array $args Query args.
	 * @return string
	 */
	public static function url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'themes.php' ) );
	}

	/**
	 * Installed themes that can be a parent (not child themes, no errors).
	 *
	 * @return WP_Theme[] Keyed by stylesheet.
	 */
	public static function parents() {
		$out = array();
		foreach ( wp_get_themes() as $slug => $theme ) {
			if ( ! $theme->parent() && ! $theme->errors() ) {
				$out[ $slug ] = $theme;
			}
		}
		uasort(
			$out,
			static function ( $a, $b ) {
				return strnatcasecmp( $a->get( 'Name' ), $b->get( 'Name' ) );
			}
		);
		return $out;
	}

	/**
	 * Installed child themes whose parent is installed.
	 *
	 * @return WP_Theme[] Keyed by stylesheet.
	 */
	public static function children() {
		$out = array();
		foreach ( wp_get_themes() as $slug => $theme ) {
			if ( $theme->parent() && ! $theme->errors() ) {
				$out[ $slug ] = $theme;
			}
		}
		return $out;
	}

	/**
	 * Folder names already used in the themes folder.
	 *
	 * @return string[]
	 */
	private static function used_slugs() {
		$used    = array_keys( wp_get_themes( array( 'errors' => null ) ) );
		$entries = scandir( get_theme_root() );
		if ( is_array( $entries ) ) {
			foreach ( $entries as $entry ) {
				if ( '.' !== $entry[0] ) {
					$used[] = strtolower( $entry );
				}
			}
		}
		return array_values( array_unique( $used ) );
	}

	/**
	 * Child fields from the request.
	 *
	 * @return array
	 */
	private static function posted_child() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Callers verify the nonce first.
		$child = array(
			'parent'      => isset( $_POST['ctmaker_parent'] ) ? sanitize_text_field( wp_unslash( $_POST['ctmaker_parent'] ) ) : '',
			'name'        => isset( $_POST['ctmaker_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ctmaker_name'] ) ) : '',
			'slug'        => isset( $_POST['ctmaker_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['ctmaker_slug'] ) ) : '',
			'description' => isset( $_POST['ctmaker_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ctmaker_description'] ) ) : '',
			'author'      => isset( $_POST['ctmaker_author'] ) ? sanitize_text_field( wp_unslash( $_POST['ctmaker_author'] ) ) : '',
			'author_uri'  => isset( $_POST['ctmaker_author_uri'] ) ? esc_url_raw( wp_unslash( $_POST['ctmaker_author_uri'] ) ) : '',
			'version'     => isset( $_POST['ctmaker_version'] ) ? sanitize_text_field( wp_unslash( $_POST['ctmaker_version'] ) ) : '1.0.0',
			'carry'       => ! empty( $_POST['ctmaker_carry'] ),
			'activate'    => ! empty( $_POST['ctmaker_activate'] ),
		);
		// phpcs:enable
		$child['slug'] = strtolower( trim( $child['slug'] ) );
		return $child;
	}

	/**
	 * Resolves a parent theme from its folder name.
	 *
	 * @param string $stylesheet Folder name.
	 * @return WP_Theme|null
	 */
	private static function parent_theme( $stylesheet ) {
		$parents = self::parents();
		return isset( $parents[ $stylesheet ] ) ? $parents[ $stylesheet ] : null;
	}

	/**
	 * Resolves a child theme from its folder name.
	 *
	 * @param string $stylesheet Folder name.
	 * @return WP_Theme|null
	 */
	private static function child_theme( $stylesheet ) {
		$children = self::children();
		return isset( $children[ $stylesheet ] ) ? $children[ $stylesheet ] : null;
	}

	/**
	 * Adds a notice.
	 *
	 * @param string $type    success, error, warning or info.
	 * @param string $message Escaped HTML.
	 * @return void
	 */
	private function notice( $type, $message ) {
		$this->notices[] = array( $type, $message );
	}

	/**
	 * Handles "Download .zip" on the create form: builds the child theme
	 * without installing it.
	 *
	 * @return void
	 */
	public function download_new() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to create themes.', 'child-theme-maker' ), 403 );
		}
		check_admin_referer( 'ctmaker_create' );

		$child  = self::posted_child();
		$parent = self::parent_theme( $child['parent'] );
		if ( ! $parent ) {
			wp_die( esc_html__( 'Pick an installed parent theme.', 'child-theme-maker' ) );
		}
		$errors = CTMaker_Generator::validate( $child, array() );
		if ( $errors ) {
			wp_die( esc_html( implode( ' ', $errors ) ) );
		}

		$facts = CTMaker_Generator::parent_facts( $parent );
		$copy  = array();
		if ( '' !== $facts['screenshot'] ) {
			$copy[ $facts['screenshot'] ] = $parent->get_stylesheet_directory() . '/' . $facts['screenshot'];
		}
		$zip = CTMaker_Zip::build( $child['slug'], CTMaker_Generator::files( $child, $facts ), '', $copy );
		if ( is_wp_error( $zip ) ) {
			wp_die( esc_html( $zip->get_error_message() ) );
		}
		CTMaker_Zip::send( $zip, $child['slug'] . '.zip' );
	}

	/**
	 * Handles "Download .zip" for an installed child theme.
	 *
	 * @return void
	 */
	public function download_child() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to export themes.', 'child-theme-maker' ), 403 );
		}
		check_admin_referer( 'ctmaker_download_child' );

		$slug  = isset( $_GET['child'] ) ? sanitize_text_field( wp_unslash( $_GET['child'] ) ) : '';
		$child = self::child_theme( $slug );
		if ( ! $child ) {
			wp_die( esc_html__( 'That child theme is not installed.', 'child-theme-maker' ) );
		}

		$zip = CTMaker_Zip::build( $child->get_stylesheet(), array(), $child->get_stylesheet_directory() );
		if ( is_wp_error( $zip ) ) {
			wp_die( esc_html( $zip->get_error_message() ) );
		}
		CTMaker_Zip::send( $zip, $child->get_stylesheet() . '.zip' );
	}

	/**
	 * Creates a child theme from the posted form.
	 *
	 * @return bool|null True on success, false on error, null when the
	 *                   file system credentials form was printed instead.
	 */
	private function handle_create() {
		$child  = self::posted_child();
		$parent = self::parent_theme( $child['parent'] );
		if ( ! $parent ) {
			$this->notice( 'error', esc_html__( 'Pick an installed parent theme.', 'child-theme-maker' ) );
			return false;
		}

		$errors = CTMaker_Generator::validate( $child, self::used_slugs() );
		if ( $errors ) {
			foreach ( $errors as $error ) {
				$this->notice( 'error', esc_html( $error ) );
			}
			return false;
		}

		$fs = CTMaker_Filesystem::connect(
			self::url(),
			array( 'ctmaker_action', '_wpnonce', 'ctmaker_parent', 'ctmaker_name', 'ctmaker_slug', 'ctmaker_description', 'ctmaker_author', 'ctmaker_author_uri', 'ctmaker_version', 'ctmaker_carry', 'ctmaker_activate' )
		);
		if ( ! $fs ) {
			return null;
		}

		if ( $fs->theme_exists( $child['slug'] ) ) {
			/* translators: %s: folder name. */
			$this->notice( 'error', esc_html( sprintf( __( 'A theme folder named "%s" already exists. Pick another folder name.', 'child-theme-maker' ), $child['slug'] ) ) );
			return false;
		}

		$facts = CTMaker_Generator::parent_facts( $parent );
		$dir   = get_theme_root() . '/' . $child['slug'];
		$ok    = true;
		foreach ( CTMaker_Generator::files( $child, $facts ) as $rel => $contents ) {
			$ok = $ok && $fs->put( $dir . '/' . $rel, $contents );
		}
		if ( $ok && '' !== $facts['screenshot'] ) {
			// A missing screenshot is cosmetic; ignore a failed copy.
			$fs->copy( $parent->get_stylesheet_directory() . '/' . $facts['screenshot'], $dir . '/' . $facts['screenshot'] );
		}
		if ( ! $ok ) {
			$fs->remove_theme( $child['slug'] );
			$this->notice( 'error', esc_html__( 'Could not write the child theme files. Check that wp-content/themes is writable.', 'child-theme-maker' ) );
			return false;
		}

		wp_clean_themes_cache();
		$theme = wp_get_theme( $child['slug'] );
		if ( ! $theme->exists() || $theme->errors() ) {
			$this->notice( 'error', esc_html__( 'The files were written, but WordPress does not recognize the new theme. Check the themes folder.', 'child-theme-maker' ) );
			return false;
		}

		self::allow_on_site( $theme->get_stylesheet() );

		$parts = array(
			/* translators: 1: child theme name, 2: parent theme name. */
			sprintf( esc_html__( 'Created %1$s, a child theme of %2$s.', 'child-theme-maker' ), '<strong>' . esc_html( $theme->get( 'Name' ) ) . '</strong>', esc_html( $parent->get( 'Name' ) ) ),
		);

		if ( $child['carry'] && current_user_can( 'edit_theme_options' ) ) {
			$parts[] = esc_html( self::describe_copy( CTMaker_Settings_Copier::copy( $parent->get_stylesheet(), $theme->get_stylesheet() ) ) );
		}

		if ( $child['activate'] && current_user_can( 'switch_themes' ) ) {
			switch_theme( $theme->get_stylesheet() );
			$parts[] = esc_html__( 'It is now your active theme.', 'child-theme-maker' );
		}

		$this->notice( 'success', implode( ' ', $parts ) );
		return true;
	}

	/**
	 * On multisite, allows a new theme on the current site only, so it can be
	 * previewed and activated here without enabling it for the whole network.
	 *
	 * @param string $slug Theme folder.
	 * @return void
	 */
	private static function allow_on_site( $slug ) {
		if ( ! is_multisite() ) {
			return;
		}
		$allowed = get_option( 'allowedthemes' );
		if ( ! is_array( $allowed ) ) {
			$allowed = array();
		}
		$allowed[ $slug ] = true;
		update_option( 'allowedthemes', $allowed );
	}

	/**
	 * Copies parent settings to an existing child.
	 *
	 * @param WP_Theme $child Child theme.
	 * @return void
	 */
	private function handle_sync( WP_Theme $child ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			$this->notice( 'error', esc_html__( 'Sorry, you are not allowed to change theme settings.', 'child-theme-maker' ) );
			return;
		}
		$result = CTMaker_Settings_Copier::copy( $child->get_template(), $child->get_stylesheet() );
		$this->notice( 'success', esc_html( self::describe_copy( $result ) ) );
	}

	/**
	 * Copies the selected parent templates into a child.
	 *
	 * @param WP_Theme $child Child theme.
	 * @return bool|null Null when the credentials form was printed.
	 */
	private function handle_override( WP_Theme $child ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified in render().
		$files = isset( $_POST['ctmaker_files'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['ctmaker_files'] ) ) : array();
		if ( ! $files && isset( $_POST['ctmaker_files_list'] ) ) {
			// Carried through the file system credentials form, which only keeps scalar fields.
			$files = array_filter( array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['ctmaker_files_list'] ) ) ) ) );
		}
		// phpcs:enable
		if ( ! $files ) {
			$this->notice( 'warning', esc_html__( 'Select at least one file to copy.', 'child-theme-maker' ) );
			return false;
		}

		$_POST['ctmaker_files_list'] = implode( ',', $files );
		$fs                          = CTMaker_Filesystem::connect( self::url( array( 'tab' => 'manage' ) ), array( 'ctmaker_action', '_wpnonce', 'ctmaker_child', 'ctmaker_files_list' ) );
		if ( ! $fs ) {
			return null;
		}

		$result = CTMaker_Templates::copy( $fs, $child->parent(), $child, $files );
		if ( $result['copied'] ) {
			$this->notice(
				'success',
				esc_html(
					sprintf(
						/* translators: 1: number of files, 2: list of files. */
						_n( 'Copied %1$d file to the child theme: %2$s. Edit the copy; the parent original stays untouched.', 'Copied %1$d files to the child theme: %2$s. Edit the copies; the parent originals stay untouched.', count( $result['copied'] ), 'child-theme-maker' ),
						count( $result['copied'] ),
						implode( ', ', $result['copied'] )
					)
				)
			);
		}
		if ( $result['existing'] ) {
			/* translators: %s: list of files. */
			$this->notice( 'info', esc_html( sprintf( __( 'Already in the child theme, left as they are: %s.', 'child-theme-maker' ), implode( ', ', $result['existing'] ) ) ) );
		}
		if ( $result['failed'] ) {
			/* translators: %s: list of files. */
			$this->notice( 'error', esc_html( sprintf( __( 'Could not copy: %s.', 'child-theme-maker' ), implode( ', ', $result['failed'] ) ) ) );
		}
		return true;
	}

	/**
	 * Sentence describing what the settings copy did.
	 *
	 * @param array $r Result of CTMaker_Settings_Copier::copy().
	 * @return string
	 */
	private static function describe_copy( array $r ) {
		$done = array();
		if ( $r['mods'] ) {
			$done[] = __( 'Customizer settings and menu locations', 'child-theme-maker' );
		}
		if ( $r['css'] ) {
			$done[] = __( 'Additional CSS', 'child-theme-maker' );
		}
		if ( $r['widgets'] ) {
			$done[] = __( 'widgets', 'child-theme-maker' );
		}
		if ( $r['editor'] ) {
			/* translators: %d: number of templates, template parts and style sets. */
			$done[] = sprintf( _n( '%d Site Editor customization', '%d Site Editor customizations', $r['editor'], 'child-theme-maker' ), $r['editor'] );
		}
		if ( ! $done ) {
			$text = __( 'The parent had no saved settings to copy.', 'child-theme-maker' );
		} else {
			/* translators: %s: list of copied settings. */
			$text = sprintf( __( 'Copied from the parent: %s.', 'child-theme-maker' ), implode( ', ', $done ) );
		}
		if ( $r['skipped'] ) {
			/* translators: %d: number of items. */
			$text .= ' ' . sprintf( _n( '%d Site Editor item already existed in the child and was kept.', '%d Site Editor items already existed in the child and were kept.', $r['skipped'], 'child-theme-maker' ), $r['skipped'] );
		}
		return $text;
	}

	/**
	 * Renders the screen and processes its forms.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'child-theme-maker' ) );
		}

		$tab     = isset( $_GET['tab'] ) && 'manage' === $_GET['tab'] ? 'manage' : 'create'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab switch only.
		$created = null;
		$action  = isset( $_POST['ctmaker_action'] ) ? sanitize_key( wp_unslash( $_POST['ctmaker_action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified below per action.

		echo '<div class="wrap ctmaker-wrap">';
		echo '<h1>' . esc_html__( 'Child Theme Maker', 'child-theme-maker' ) . '</h1>';

		if ( 'create' === $action ) {
			check_admin_referer( 'ctmaker_create' );
			$created = $this->handle_create();
			if ( null === $created ) {
				echo '</div>';
				return;
			}
		} elseif ( 'sync' === $action || 'override' === $action ) {
			check_admin_referer( 'ctmaker_' . $action );
			$tab   = 'manage';
			$slug  = isset( $_POST['ctmaker_child'] ) ? sanitize_text_field( wp_unslash( $_POST['ctmaker_child'] ) ) : '';
			$child = self::child_theme( $slug );
			if ( ! $child ) {
				$this->notice( 'error', esc_html__( 'That child theme is not installed.', 'child-theme-maker' ) );
			} elseif ( 'sync' === $action ) {
				$this->handle_sync( $child );
			} elseif ( null === $this->handle_override( $child ) ) {
				echo '</div>';
				return;
			}
		}

		foreach ( $this->notices as $notice ) {
			printf( '<div class="notice notice-%1$s ctmaker-notice"><p>%2$s</p></div>', esc_attr( $notice[0] ), wp_kses_post( $notice[1] ) );
		}

		$children = self::children();
		echo '<nav class="nav-tab-wrapper">';
		printf( '<a href="%s" class="nav-tab%s">%s</a>', esc_url( self::url() ), 'create' === $tab ? ' nav-tab-active' : '', esc_html__( 'Create', 'child-theme-maker' ) );
		printf(
			'<a href="%s" class="nav-tab%s">%s <span class="count">(%d)</span></a>',
			esc_url( self::url( array( 'tab' => 'manage' ) ) ),
			'manage' === $tab ? ' nav-tab-active' : '',
			esc_html__( 'Your child themes', 'child-theme-maker' ),
			count( $children )
		);
		echo '</nav>';

		if ( 'manage' === $tab ) {
			$this->render_manage( $children );
		} elseif ( true === $created ) {
			$this->render_created( wp_get_theme( self::posted_child()['slug'] ) );
		} else {
			$this->render_create( false === $created );
		}

		echo '</div>';
	}

	/**
	 * Next steps after a child was created.
	 *
	 * @param WP_Theme $theme New child theme.
	 * @return void
	 */
	private function render_created( WP_Theme $theme ) {
		$slug = $theme->get_stylesheet();
		echo '<div class="ctmaker-card ctmaker-next">';
		echo '<h2>' . esc_html__( 'Next steps', 'child-theme-maker' ) . '</h2><p>';

		if ( get_stylesheet() !== $slug && current_user_can( 'switch_themes' ) ) {
			$activate = wp_nonce_url( admin_url( 'themes.php?action=activate&stylesheet=' . rawurlencode( $slug ) ), 'switch-theme_' . $slug );
			printf( '<a class="button button-primary" href="%s">%s</a> ', esc_url( $activate ), esc_html__( 'Activate', 'child-theme-maker' ) );
			if ( $theme->is_block_theme() ) {
				// Site Editor theme previews exist since WordPress 6.3.
				$preview = add_query_arg( 'wp_theme_preview', $slug, admin_url( 'site-editor.php' ) );
			} else {
				$preview = add_query_arg( 'theme', $slug, admin_url( 'customize.php' ) );
			}
			printf( '<a class="button" href="%s">%s</a> ', esc_url( $preview ), esc_html__( 'Live preview', 'child-theme-maker' ) );
		}
		printf(
			'<a class="button" href="%s">%s</a> ',
			esc_url(
				self::url(
					array(
						'tab'   => 'manage',
						'child' => $slug,
					)
				)
			),
			esc_html__( 'Override parent templates', 'child-theme-maker' )
		);
		if ( CTMaker_Zip::available() ) {
			printf( '<a class="button" href="%s">%s</a>', esc_url( self::download_url( $slug ) ), esc_html__( 'Download .zip', 'child-theme-maker' ) );
		}
		echo '</p><p class="description">';
		echo esc_html__( 'Put your changes in the child theme: CSS in its style.css, PHP in its functions.php, and template edits in copies made from the "Your child themes" tab. Parent theme updates will no longer overwrite them.', 'child-theme-maker' );
		echo '</p></div>';
	}

	/**
	 * Download URL for an installed child.
	 *
	 * @param string $slug Child folder.
	 * @return string
	 */
	private static function download_url( $slug ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=ctmaker_download_child&child=' . rawurlencode( $slug ) ), 'ctmaker_download_child' );
	}

	/**
	 * The create form.
	 *
	 * @param bool $repopulate Whether to refill the form from the request.
	 * @return void
	 */
	private function render_create( $repopulate ) {
		$parents = self::parents();
		if ( ! $parents ) {
			echo '<p>' . esc_html__( 'No installed theme can be used as a parent.', 'child-theme-maker' ) . '</p>';
			return;
		}

		$posted   = $repopulate ? self::posted_child() : null;
		$selected = $posted ? $posted['parent'] : get_template();
		if ( ! isset( $parents[ $selected ] ) ) {
			$selected = (string) array_key_first( $parents );
		}
		$defaults = CTMaker_Generator::default_child( $parents[ $selected ]->get( 'Name' ), $selected );
		$user     = wp_get_current_user();
		$values   = $posted ? $posted : array(
			'name'        => $defaults['name'],
			'slug'        => $defaults['slug'],
			'description' => '',
			'author'      => $user->display_name,
			'author_uri'  => '',
			'version'     => '1.0.0',
			'carry'       => true,
			'activate'    => false,
		);

		$active = wp_get_theme();
		if ( ! $active->parent() ) {
			echo '<p class="ctmaker-intro">';
			printf(
				/* translators: %s: active theme name. */
				esc_html__( 'You are using %s directly. Edits to its files are lost on the next theme update. A child theme keeps your changes safe and inherits everything else from the parent.', 'child-theme-maker' ),
				'<strong>' . esc_html( $active->get( 'Name' ) ) . '</strong>'
			);
			echo '</p>';
		} else {
			echo '<p class="ctmaker-intro">';
			printf(
				/* translators: 1: active child theme name, 2: its parent name. */
				esc_html__( 'Your active theme %1$s is already a child theme of %2$s.', 'child-theme-maker' ),
				'<strong>' . esc_html( $active->get( 'Name' ) ) . '</strong>',
				esc_html( $active->parent()->get( 'Name' ) )
			);
			echo '</p>';
		}
		?>
		<form method="post" action="<?php echo esc_url( self::url() ); ?>" class="ctmaker-card" id="ctmaker-create">
			<?php wp_nonce_field( 'ctmaker_create' ); ?>
			<input type="hidden" name="ctmaker_action" value="create">

			<h2><span class="ctmaker-step">1</span> <?php esc_html_e( 'Pick the parent theme', 'child-theme-maker' ); ?></h2>
			<p>
				<label for="ctmaker_parent" class="screen-reader-text"><?php esc_html_e( 'Parent theme', 'child-theme-maker' ); ?></label>
				<select name="ctmaker_parent" id="ctmaker_parent">
					<?php
					foreach ( $parents as $slug => $theme ) {
						$d = CTMaker_Generator::default_child( $theme->get( 'Name' ), $slug );
						printf(
							'<option value="%1$s" data-name="%2$s" data-slug="%3$s"%4$s>%5$s</option>',
							esc_attr( $slug ),
							esc_attr( $d['name'] ),
							esc_attr( $d['slug'] ),
							selected( $slug, $selected, false ),
							esc_html(
								sprintf(
									/* translators: 1: theme name, 2: version, 3: "block theme" or "classic theme". */
									__( '%1$s %2$s (%3$s)', 'child-theme-maker' ),
									$theme->get( 'Name' ),
									$theme->get( 'Version' ),
									$theme->is_block_theme() ? __( 'block theme', 'child-theme-maker' ) : __( 'classic theme', 'child-theme-maker' )
								)
							)
						);
					}
					?>
				</select>
			</p>

			<h2><span class="ctmaker-step">2</span> <?php esc_html_e( 'Name it', 'child-theme-maker' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ctmaker_name"><?php esc_html_e( 'Theme name', 'child-theme-maker' ); ?></label></th>
					<td><input name="ctmaker_name" id="ctmaker_name" type="text" class="regular-text" required value="<?php echo esc_attr( $values['name'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="ctmaker_slug"><?php esc_html_e( 'Folder name', 'child-theme-maker' ); ?></label></th>
					<td>
						<input name="ctmaker_slug" id="ctmaker_slug" type="text" class="regular-text code" required pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?php echo esc_attr( $values['slug'] ); ?>">
						<p class="description"><?php esc_html_e( 'Lowercase letters, numbers and hyphens. It cannot be changed later.', 'child-theme-maker' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ctmaker_description"><?php esc_html_e( 'Description', 'child-theme-maker' ); ?></label></th>
					<td><textarea name="ctmaker_description" id="ctmaker_description" rows="2" class="large-text"><?php echo esc_textarea( $values['description'] ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="ctmaker_author"><?php esc_html_e( 'Author', 'child-theme-maker' ); ?></label></th>
					<td><input name="ctmaker_author" id="ctmaker_author" type="text" class="regular-text" value="<?php echo esc_attr( $values['author'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="ctmaker_author_uri"><?php esc_html_e( 'Author URL', 'child-theme-maker' ); ?></label></th>
					<td><input name="ctmaker_author_uri" id="ctmaker_author_uri" type="url" class="regular-text code" value="<?php echo esc_attr( $values['author_uri'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="ctmaker_version"><?php esc_html_e( 'Version', 'child-theme-maker' ); ?></label></th>
					<td><input name="ctmaker_version" id="ctmaker_version" type="text" class="small-text" value="<?php echo esc_attr( $values['version'] ); ?>"></td>
				</tr>
			</table>

			<h2><span class="ctmaker-step">3</span> <?php esc_html_e( 'Create it', 'child-theme-maker' ); ?></h2>
			<fieldset>
				<?php if ( current_user_can( 'edit_theme_options' ) ) : ?>
				<p><label><input type="checkbox" name="ctmaker_carry" value="1" <?php checked( $values['carry'] ); ?>> <?php esc_html_e( 'Copy my settings from the parent: Customizer settings, menu locations, widgets, Additional CSS and Site Editor changes. Without this, switching to the child shows the parent\'s defaults.', 'child-theme-maker' ); ?></label></p>
				<?php endif; ?>
				<?php if ( current_user_can( 'switch_themes' ) ) : ?>
				<p><label><input type="checkbox" name="ctmaker_activate" value="1" <?php checked( $values['activate'] ); ?>> <?php esc_html_e( 'Activate the child theme now', 'child-theme-maker' ); ?></label></p>
				<?php endif; ?>
			</fieldset>
			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Create child theme', 'child-theme-maker' ); ?></button>
				<?php if ( CTMaker_Zip::available() ) : ?>
				<button type="submit" class="button" formaction="<?php echo esc_url( admin_url( 'admin-post.php?action=ctmaker_download_new' ) ); ?>"><?php esc_html_e( 'Download as .zip instead', 'child-theme-maker' ); ?></button>
				<?php endif; ?>
			</p>
		</form>
		<?php
	}

	/**
	 * The "Your child themes" tab.
	 *
	 * @param WP_Theme[] $children Installed child themes.
	 * @return void
	 */
	private function render_manage( array $children ) {
		if ( ! $children ) {
			echo '<p>' . esc_html__( 'There are no child themes yet.', 'child-theme-maker' ) . ' <a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Create one', 'child-theme-maker' ) . '</a></p>';
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification -- Selecting which theme to show.
		$current = '';
		if ( isset( $_POST['ctmaker_child'] ) ) {
			$current = sanitize_text_field( wp_unslash( $_POST['ctmaker_child'] ) );
		} elseif ( isset( $_GET['child'] ) ) {
			$current = sanitize_text_field( wp_unslash( $_GET['child'] ) );
		}
		// phpcs:enable
		if ( ! isset( $children[ $current ] ) ) {
			$current = isset( $children[ get_stylesheet() ] ) ? get_stylesheet() : (string) array_key_first( $children );
		}
		$child  = $children[ $current ];
		$parent = $child->parent();

		if ( count( $children ) > 1 ) {
			echo '<form method="get" class="ctmaker-switch"><input type="hidden" name="page" value="' . esc_attr( self::PAGE ) . '"><input type="hidden" name="tab" value="manage">';
			echo '<label for="ctmaker-child-switch">' . esc_html__( 'Child theme:', 'child-theme-maker' ) . '</label> <select name="child" id="ctmaker-child-switch">';
			foreach ( $children as $slug => $theme ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $slug ), selected( $slug, $current, false ), esc_html( $theme->get( 'Name' ) ) );
			}
			echo '</select> <button class="button">' . esc_html__( 'Show', 'child-theme-maker' ) . '</button></form>';
		}

		echo '<div class="ctmaker-card"><h2>' . esc_html( $child->get( 'Name' ) ) . '</h2><p>';
		printf(
			/* translators: 1: parent theme name, 2: folder name. */
			esc_html__( 'Child of %1$s, in the folder %2$s.', 'child-theme-maker' ),
			'<strong>' . esc_html( $parent->get( 'Name' ) ) . '</strong>',
			'<code>' . esc_html( $current ) . '</code>'
		);
		if ( get_stylesheet() === $current ) {
			echo ' <span class="ctmaker-badge">' . esc_html__( 'Active', 'child-theme-maker' ) . '</span>';
		}
		echo '</p><p>';
		if ( CTMaker_Zip::available() ) {
			printf( '<a class="button" href="%s">%s</a> ', esc_url( self::download_url( $current ) ), esc_html__( 'Download .zip', 'child-theme-maker' ) );
		}
		echo '</p>';

		if ( current_user_can( 'edit_theme_options' ) ) {
			echo '<form method="post" action="' . esc_url( self::url( array( 'tab' => 'manage' ) ) ) . '">';
			wp_nonce_field( 'ctmaker_sync' );
			echo '<input type="hidden" name="ctmaker_action" value="sync"><input type="hidden" name="ctmaker_child" value="' . esc_attr( $current ) . '">';
			echo '<p><button class="button ctmaker-confirm" data-confirm="' . esc_attr__( 'Replace this child theme\'s Customizer settings with the parent\'s?', 'child-theme-maker' ) . '">' . esc_html__( 'Copy settings from the parent again', 'child-theme-maker' ) . '</button></p>';
			echo '<p class="description">' . esc_html__( 'Replaces the child\'s Customizer settings, menu locations, widgets and Additional CSS with the parent\'s. Site Editor templates and styles the child already has are kept.', 'child-theme-maker' ) . '</p>';
			echo '</form>';
		}
		echo '</div>';

		$files   = CTMaker_Templates::overridable( $parent );
		$present = array_flip( CTMaker_Templates::present_in_child( $child, $files ) );

		echo '<div class="ctmaker-card"><h2>' . esc_html__( 'Override parent templates', 'child-theme-maker' ) . '</h2>';
		echo '<p>' . esc_html__( 'Copy a parent file into the child theme, then edit the copy. WordPress uses the child\'s copy and parent updates leave it alone.', 'child-theme-maker' ) . '</p>';
		if ( ! $files ) {
			echo '<p>' . esc_html__( 'The parent theme has no template files that can be overridden this way.', 'child-theme-maker' ) . '</p></div>';
			return;
		}
		?>
		<form method="post" action="<?php echo esc_url( self::url( array( 'tab' => 'manage' ) ) ); ?>" id="ctmaker-override">
			<?php wp_nonce_field( 'ctmaker_override' ); ?>
			<input type="hidden" name="ctmaker_action" value="override">
			<input type="hidden" name="ctmaker_child" value="<?php echo esc_attr( $current ); ?>">
			<p>
				<label for="ctmaker-filter" class="screen-reader-text"><?php esc_html_e( 'Filter files', 'child-theme-maker' ); ?></label>
				<input type="search" id="ctmaker-filter" placeholder="<?php esc_attr_e( 'Filter files…', 'child-theme-maker' ); ?>" class="regular-text">
			</p>
			<ul class="ctmaker-files">
				<?php foreach ( $files as $file ) : ?>
					<li>
						<?php if ( isset( $present[ $file ] ) ) : ?>
							<label class="ctmaker-present"><input type="checkbox" disabled checked> <code><?php echo esc_html( $file ); ?></code> <span><?php esc_html_e( 'in child', 'child-theme-maker' ); ?></span></label>
						<?php else : ?>
							<label><input type="checkbox" name="ctmaker_files[]" value="<?php echo esc_attr( $file ); ?>"> <code><?php echo esc_html( $file ); ?></code></label>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Copy selected files to the child theme', 'child-theme-maker' ); ?></button></p>
		</form>
		</div>
		<?php
	}
}
