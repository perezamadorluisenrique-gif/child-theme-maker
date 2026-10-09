=== Child Theme Maker ===
Contributors: wporg-username
Tags: child theme, child themes, create child theme, theme, block theme
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create a safe child theme for any classic or block theme in three steps, keep your Customizer and Site Editor settings, and override templates.

== Description ==

Editing your theme's files directly means losing every change on the next theme update. A child theme fixes that, but making one by hand means writing a style.css header, getting the stylesheet loading right in functions.php, and then finding that switching to it reset your menus, widgets and Customizer settings.

Child Theme Maker does all of that from **Appearance > Child Theme**:

1. **Pick the parent.** Any installed classic theme or block theme, including the default Twenty themes.
2. **Name it.** The name, folder, author and version are filled in for you.
3. **Create it.** The child theme is written to your themes folder, ready to activate or preview.

= What makes the child theme correct =

* **Stylesheets load in the right order, whatever the parent does.** The child's functions.php looks at the styles the parent actually registers and only adds what is missing. Parents that load their own style.css, parents that load "the active theme's" style.css, and parents that load none all work, without the double-loading or missing parent styles that hand-made child themes often have.
* **Block themes get a theme.json** that inherits every setting from the parent, using the parent's own schema version.
* **The parent's screenshot is copied**, so the child is easy to spot on the Themes screen.
* **Protected from wrong updates.** The child declares `Update URI: false`, so WordPress never offers to "update" it with an unrelated theme from WordPress.org that happens to use the same folder name.
* **It does not need this plugin.** The generated theme is plain WordPress code. You can deactivate or delete Child Theme Maker and the child keeps working.

= Keep your site looking the same =

With one checkbox, your settings move with you to the child theme:

* Customizer settings (colors, logo, header, layout options)
* Menu locations
* Widgets and sidebar assignments
* Additional CSS
* Site Editor changes in block themes: customized templates, template parts and global styles

= Override parent templates =

The **Your child themes** tab lists the parent's template files (PHP templates for classic themes; templates, parts, patterns and style variations for block themes). Tick the ones you want to change and they are copied into the child, where you can edit them safely. Files the child already has are never overwritten.

= Download as .zip =

Download any child theme as a .zip to install on another site, or build one as a .zip without installing it here.

= Private and light =

* No account, no external service, no tracking, no ads.
* Nothing loads on your site's front end. The plugin only adds one admin screen.
* Works through the WordPress file system API, so it also works on hosts that require FTP credentials to write files.

== Installation ==

1. Install and activate Child Theme Maker from **Plugins > Add New**.
2. Go to **Appearance > Child Theme**.
3. Pick the parent theme, check the name, and click **Create child theme**.
4. Click **Live preview** to check it, then **Activate**.

== Frequently Asked Questions ==

= Will I lose my settings when I activate the child theme? =

Not if you keep "Copy my settings from the parent" ticked when you create it. You can also copy them again later from the **Your child themes** tab.

= Do I need to keep this plugin active? =

No. The child theme is self-contained. Keep the plugin if you want to override more templates or download the child as a .zip later.

= Where do I put my custom CSS and code? =

CSS goes at the end of the child's style.css, or in **Appearance > Customize > Additional CSS** (classic themes) or **Appearance > Editor > Styles** (block themes). PHP goes in the child's functions.php.

= Does it work with block themes? =

Yes. The child gets a style.css, a functions.php and a theme.json, and Site Editor customizations of the parent can be copied over.

= Can I make a child of a child theme? =

No. WordPress only supports one level of child themes, so only themes that are not child themes are offered as parents.

= Who can use it? =

Users who can install themes (administrators on single sites, super admins on multisite). Copying settings also needs the right to edit theme options, and activating needs the right to switch themes.

== Screenshots ==

1. Create a child theme in three steps.
2. Override parent templates from the "Your child themes" tab.

== Changelog ==

= 1.0.0 =
* First release: three-step child theme creation for classic and block themes, settings carry-over, template overrides and .zip download.

== Upgrade Notice ==

= 1.0.0 =
First release.
