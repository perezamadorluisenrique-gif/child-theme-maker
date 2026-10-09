// End-to-end smoke test on a real WordPress site.
//
// Needs: a WordPress install with this plugin active and an "admin"/"admin"
// administrator, served at BASE_URL; WP-CLI; Playwright with Chromium.
//   WP_PATH=/path/to/wp WP_CLI="php wp-cli.phar --allow-root" BASE_URL=http://localhost:8899 node tests/e2e/smoke.mjs
//
// Covers: classic parent (enqueues its own sheet), classic parent that loads
// get_stylesheet_uri(), block parent with Site Editor changes, settings
// carry-over, template overrides, and both .zip downloads.

import { execSync } from 'node:child_process';
import { existsSync, readFileSync, writeFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { createRequire } from 'node:module';

// require() honors NODE_PATH, so a globally installed Playwright works too.
const { chromium } = createRequire( import.meta.url )( 'playwright' );

const WP_PATH = process.env.WP_PATH;
const WP_CLI = process.env.WP_CLI || 'wp';
const BASE = process.env.BASE_URL || 'http://localhost:8899';
// Block parent with Site Editor changes; WordPress 6.3 only ships Twenty Twenty-Three.
const BLOCK = process.env.BLOCK_PARENT || 'twentytwentyfive';
const themes = join( WP_PATH, 'wp-content/themes' );

let failures = 0;
const check = ( ok, label ) => {
	console.log( `${ ok ? 'PASS' : 'FAIL' }  ${ label }` );
	if ( ! ok ) failures++;
};
const wp = ( args ) => execSync( `${ WP_CLI } --path=${ WP_PATH } ${ args } 2>/dev/null`, { encoding: 'utf8' } ).trim();
const wpEval = ( code ) => {
	const file = join( mkdtempSync( join( tmpdir(), 'ctm-' ) ), 'eval.php' );
	writeFileSync( file, `<?php ${ code }` );
	return wp( `eval-file ${ file }` );
};
const front = async () => ( await fetch( `${ BASE }/?nocache=${ Date.now() }` ) ).text();

const browser = await chromium.launch( { executablePath: process.env.CHROMIUM || undefined } );
const page = await browser.newPage( { acceptDownloads: true } );
const errors = [];
page.on( 'pageerror', ( e ) => errors.push( e.message ) );
// Ignore requests to outside hosts (fonts, Gravatar), which the test network may block.
page.on( 'console', ( m ) => m.type() === 'error' && ! /ERR_TUNNEL_CONNECTION_FAILED|ERR_NAME_NOT_RESOLVED|ERR_CONNECTION/.test( m.text() ) && errors.push( m.text() ) );

await page.goto( `${ BASE }/wp-login.php` );
await page.fill( '#user_login', 'admin' );
await page.fill( '#user_pass', 'admin' );
await page.click( '#wp-submit' );
await page.waitForURL( /wp-admin/ );

async function createChild( parent, { carry = true, activate = true } = {} ) {
	await page.goto( `${ BASE }/wp-admin/themes.php?page=child-theme-maker` );
	await page.selectOption( '#ctmaker_parent', parent );
	const slug = await page.inputValue( '#ctmaker_slug' );
	if ( ! carry ) await page.uncheck( 'input[name=ctmaker_carry]' );
	if ( activate ) await page.check( 'input[name=ctmaker_activate]' );
	await page.click( 'button.button-primary' );
	await page.waitForSelector( '.ctmaker-notice' );
	return { slug, notice: await page.textContent( '.ctmaker-notice' ) };
}

// ---- 1. Classic parent that enqueues its own style.css (Twenty Twenty-One).
wp( 'theme activate twentytwentyone' );
wp( 'theme mod set background_color 123456' );
wp( 'menu create Main' );
wp( 'menu item add-custom main MENU-MARKER https://example.com' );
wp( 'menu location assign main primary' );
wp( 'widget add block sidebar-1 --content="<!-- wp:paragraph --><p>WIDGET-MARKER</p><!-- /wp:paragraph -->"' );
wpEval( "wp_update_custom_css_post( 'body{--css-marker:1}' );" );

await page.goto( `${ BASE }/wp-admin/themes.php?page=child-theme-maker` );
const defaultParent = await page.inputValue( '#ctmaker_parent' );
check( defaultParent === 'twentytwentyone', `active theme is the default parent (${ defaultParent })` );
const defaultName = await page.inputValue( '#ctmaker_name' );
check( defaultName === 'Twenty Twenty-One Child', `name is prefilled (${ defaultName })` );
await page.selectOption( '#ctmaker_parent', 'twentytwentytwo' );
check( ( await page.inputValue( '#ctmaker_slug' ) ) === 'twentytwentytwo-child', 'slug follows the parent choice' );
await page.fill( '#ctmaker_name', 'Mi Tema Café' );
check( ( await page.inputValue( '#ctmaker_slug' ) ) === 'mi-tema-cafe', 'slug follows the typed name' );

let r = await createChild( 'twentytwentyone' );
check( r.slug === 'twentytwentyone-child', 'classic child slug' );
check( /Created/.test( r.notice ) && /Customizer settings/.test( r.notice ) && /Additional CSS/.test( r.notice ) && /widgets/.test( r.notice ), `classic create notice lists the carried settings (${ r.notice.trim() })` );
check( wp( 'option get stylesheet' ) === 'twentytwentyone-child', 'classic child is active' );
check( existsSync( join( themes, 'twentytwentyone-child/screenshot.png' ) ), 'screenshot copied' );
check( ! existsSync( join( themes, 'twentytwentyone-child/theme.json' ) ), 'no theme.json for a classic child' );
let html = await front();
const parentAt = html.indexOf( 'themes/twentytwentyone/style.css' );
const childAt = html.indexOf( 'themes/twentytwentyone-child/style.css' );
check( parentAt > 0 && childAt > parentAt, 'parent sheet loads once, before the child sheet' );
check( html.split( 'themes/twentytwentyone/style.css' ).length === 2, 'parent sheet not loaded twice' );
check( html.includes( '123456' ), 'Customizer setting carried over' );
check( html.includes( 'MENU-MARKER' ), 'menu location carried over' );
check( html.includes( 'WIDGET-MARKER' ), 'widgets carried over' );
check( html.includes( '--css-marker:1' ), 'Additional CSS carried over' );
check( wpEval( "echo wp_get_custom_css_post( 'twentytwentyone-child' )->post_name;" ) === 'twentytwentyone-child', 'child gets its own Additional CSS post' );

// Existing folder is refused.
await page.goto( `${ BASE }/wp-admin/themes.php?page=child-theme-maker` );
await page.selectOption( '#ctmaker_parent', 'twentytwentyone' );
await page.fill( '#ctmaker_slug', 'twentytwentyone-child' );
await page.click( 'button.button-primary' );
check( /already exists/.test( await page.textContent( '.ctmaker-notice.notice-error' ) ), 'existing folder is refused' );

// ---- 2. Override tab.
await page.goto( `${ BASE }/wp-admin/themes.php?page=child-theme-maker&tab=manage&child=twentytwentyone-child` );
const boxes = await page.$$eval( '.ctmaker-files input[name="ctmaker_files[]"]', ( els ) => els.map( ( e ) => e.value ) );
check( boxes.includes( 'header.php' ) && boxes.includes( 'template-parts/header/site-header.php' ), 'classic template files listed' );
check( ! boxes.includes( 'functions.php' ) && ! boxes.some( ( b ) => b.startsWith( 'inc/' ) || b.startsWith( 'classes/' ) ), 'functions.php and code folders not listed' );
await page.fill( '#ctmaker-filter', 'footer' );
check( await page.isHidden( 'label:has(code:text-is("header.php"))' ), 'filter hides non-matching files' );
await page.check( 'input[value="footer.php"]' );
await page.click( '#ctmaker-override button.button-primary' );
await page.waitForSelector( '.ctmaker-notice' );
check( existsSync( join( themes, 'twentytwentyone-child/footer.php' ) ), 'footer.php copied into the child' );
check( await page.isVisible( '.ctmaker-present:has(code:text-is("footer.php"))' ), 'copied file shown as in child' );
writeFileSync( join( themes, 'twentytwentyone-child/footer.php' ), readFileSync( join( themes, 'twentytwentyone-child/footer.php' ), 'utf8' ).replace( '</footer>', 'FOOTER-OVERRIDE</footer>' ) );
check( ( await front() ).includes( 'FOOTER-OVERRIDE' ), 'child template override is used' );

// Tampered path is refused.
r = await page.evaluate( async () => {
	const f = document.getElementById( 'ctmaker-override' );
	const d = new FormData( f );
	d.append( 'ctmaker_files[]', '../../../wp-config.php' );
	return ( await fetch( f.action, { method: 'POST', body: d } ) ).text();
} );
check( /Could not copy: \.\.\/\.\.\/\.\.\/wp-config\.php/.test( r ) && ! existsSync( join( WP_PATH, 'wp-content/wp-config.php' ) ), 'path traversal refused' );

// ---- 3. Downloads.
let [ dl ] = await Promise.all( [ page.waitForEvent( 'download' ), page.click( 'a:text-is("Download .zip")' ) ] );
let zipPath = await dl.path();
let listing = execSync( `unzip -l ${ zipPath }`, { encoding: 'utf8' } );
check( dl.suggestedFilename() === 'twentytwentyone-child.zip' && listing.includes( 'twentytwentyone-child/style.css' ) && listing.includes( 'twentytwentyone-child/footer.php' ), 'installed child downloads as zip' );

await page.goto( `${ BASE }/wp-admin/themes.php?page=child-theme-maker` );
await page.selectOption( '#ctmaker_parent', 'twentytwentytwo' );
[ dl ] = await Promise.all( [ page.waitForEvent( 'download' ), page.click( 'button:text-is("Download as .zip instead")' ) ] );
zipPath = await dl.path();
listing = execSync( `unzip -l ${ zipPath }`, { encoding: 'utf8' } );
check( listing.includes( 'twentytwentytwo-child/theme.json' ) && listing.includes( 'twentytwentytwo-child/functions.php' ), 'new block child downloads as zip' );
check( ! existsSync( join( themes, 'twentytwentytwo-child' ) ), 'zip-only download does not install' );

// ---- 4. Parent that loads get_stylesheet_uri() (Twenty Seventeen).
wp( 'theme activate twentyseventeen' );
r = await createChild( 'twentyseventeen', { carry: false, activate: false } );
// The next-step buttons must work, including on multisite where a new theme is not yet allowed on the site.
const previewHref = await page.getAttribute( 'a:text-is("Live preview")', 'href' );
const previewStatus = ( await page.request.get( previewHref ) ).status();
check( previewStatus === 200, `Live preview opens (${ previewStatus })` );
await page.click( 'a:text-is("Activate")' );
await page.waitForLoadState();
const activated = wp( 'option get stylesheet' ) === 'twentyseventeen-child';
check( activated, `Activate button switches to the child${ activated ? '' : ': ' + ( await page.textContent( 'body' ) ).replace( /\s+/g, ' ' ).trim().slice( 0, 120 ) }` );
html = await front();
const p17 = html.indexOf( 'themes/twentyseventeen/style.css' );
const c17 = html.indexOf( 'themes/twentyseventeen-child/style.css' );
check( p17 > 0 && c17 > p17, 'get_stylesheet_uri() parent: parent sheet added before the child sheet' );
check( html.split( 'themes/twentyseventeen-child/style.css' ).length === 2, 'get_stylesheet_uri() parent: child sheet loaded once' );

// ---- 5. Block parent with Site Editor changes.
wp( `theme activate ${ BLOCK }` );
wpEval( `
$id = WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
wp_set_post_terms( $id, array( '${ BLOCK }' ), 'wp_theme' ); // Set by core when an admin saves styles; WP-CLI has no user here.
wp_update_post( array( 'ID' => $id, 'post_content' => wp_json_encode( array( 'version' => 3, 'isGlobalStylesUserThemeJSON' => true, 'styles' => array( 'color' => array( 'background' => '#abcdef' ) ) ) ) ) );
$t = wp_insert_post( array( 'post_type' => 'wp_template', 'post_status' => 'publish', 'post_name' => 'home', 'post_title' => 'Blog Home', 'post_content' => '<!-- wp:paragraph --><p>TEMPLATE-MARKER</p><!-- /wp:paragraph -->' ) );
wp_set_post_terms( $t, array( '${ BLOCK }' ), 'wp_theme' );
` );
r = await createChild( BLOCK );
check( /2 Site Editor customizations/.test( r.notice ), `block create notice counts Site Editor items (${ r.notice.trim() })` );
check( existsSync( join( themes, `${ BLOCK }-child/theme.json` ) ), 'theme.json written for a block child' );
check( wpEval( `echo wp_get_theme( '${ BLOCK }-child' )->is_block_theme() ? 'yes' : 'no';` ) === 'yes', 'block child is recognized as a block theme' );
html = await front();
check( html.includes( 'TEMPLATE-MARKER' ), 'customized template carried over' );
check( html.includes( '#abcdef' ), 'global styles carried over' );
const childTemplates = wpEval( `echo implode( ',', wp_list_pluck( get_posts( array( 'post_type' => 'wp_template', 'tax_query' => array( array( 'taxonomy' => 'wp_theme', 'field' => 'name', 'terms' => '${ BLOCK }-child' ) ) ) ), 'post_name' ) );` );
check( childTemplates === 'home', `child template slug kept, no home-2 (${ childTemplates })` );

await page.goto( `${ BASE }/wp-admin/themes.php?page=child-theme-maker&tab=manage&child=${ BLOCK }-child` );
const blockFiles = await page.$$eval( '.ctmaker-files code', ( els ) => els.map( ( e ) => e.textContent ) );
check( blockFiles.includes( 'templates/index.html' ) && blockFiles.includes( 'parts/header.html' ) && blockFiles.some( ( f ) => f.startsWith( 'patterns/' ) ), 'block templates, parts and patterns listed' );

// Copy settings again keeps existing Site Editor items.
page.once( 'dialog', ( d ) => d.accept() );
await page.click( 'button.ctmaker-confirm' );
await page.waitForSelector( '.ctmaker-notice' );
check( /already existed in the child and were kept/.test( await page.textContent( '.ctmaker-notice' ) ), 'resync keeps existing Site Editor items' );

// A parent without Site Editor changes must not pick up another theme's.
r = await createChild( 'twentytwentytwo', { activate: false } );
check( ! /Site Editor/.test( r.notice ), `no other theme's Site Editor items copied (${ r.notice.trim() })` );

check( errors.length === 0, `no JavaScript errors${ errors.length ? ': ' + errors.join( ' | ' ) : '' }` );
const debugLog = join( WP_PATH, 'debug.log' );
// Count fatals anywhere, and anything else raised in the plugin or a generated
// child theme. Core's own notices (old core on new PHP, blocked update checks) don't count.
const log = existsSync( debugLog )
	? readFileSync( debugLog, 'utf8' ).split( '\n' ).filter( ( l ) => /PHP (Fatal|Parse)/.test( l ) || ( /PHP (Notice|Warning|Deprecated)/.test( l ) && /wp-content\/(plugins\/child-theme-maker|themes\/[a-z0-9-]+-child)\//.test( l ) ) )
	: [];
check( log.length === 0, `no PHP errors, and no warnings or notices from the plugin or child themes${ log.length ? ': ' + log.join( ' | ' ) : '' }` );

await browser.close();
console.log( failures ? `\n${ failures } check(s) failed` : '\nAll checks passed' );
process.exit( failures ? 1 : 0 );
