<?php
/**
 * Plugin Name:       Child Theme Maker
 * Plugin URI:        https://github.com/perezamadorluisenrique-gif/child-theme-maker
 * Description:       Create a safe child theme for any classic or block theme in three steps, carry over your Customizer and Site Editor settings, override parent templates and download the child as a .zip.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Enrique
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       child-theme-maker
 *
 * @package ChildThemeMaker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CTMAKER_VERSION', '1.0.0' );
define( 'CTMAKER_FILE', __FILE__ );
define( 'CTMAKER_DIR', plugin_dir_path( __FILE__ ) );
define( 'CTMAKER_URL', plugin_dir_url( __FILE__ ) );

require_once CTMAKER_DIR . 'includes/class-ctmaker-generator.php';
require_once CTMAKER_DIR . 'includes/class-ctmaker-filesystem.php';
require_once CTMAKER_DIR . 'includes/class-ctmaker-settings-copier.php';
require_once CTMAKER_DIR . 'includes/class-ctmaker-templates.php';
require_once CTMAKER_DIR . 'includes/class-ctmaker-zip.php';
require_once CTMAKER_DIR . 'includes/class-ctmaker-admin.php';

add_action(
	'plugins_loaded',
	static function () {
		if ( is_admin() ) {
			( new CTMaker_Admin() )->register();
		}
	}
);
