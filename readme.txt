=== JetEngine Color Swatches ===
Author: Marrisonlab
Tags: woocommerce, elementor, jetengine, swatch, variations, color
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
WC requires at least: 7.0
License: GPLv2 or later

Elementor widget to display WooCommerce variation color swatches.
Compatible with JetEngine Listing Grid and single product page.

== Installation ==

1. Upload the `jetengine-color-swatches` folder to `/wp-content/plugins/`
2. Activate the plugin from WP Admin → Plugins
3. Go to WooCommerce → Attributes → select an attribute → edit the terms
4. For each term configure: Type (Color / Image), Color 1, Color 2 (optional)
5. In the Elementor builder drag the "Color Swatches Variazioni" widget

== Features ==

* Single or dual color swatch (135° diagonal split)
* Image swatch (WP media library)
* Shapes: circle, square, rounded rectangle
* Tooltip with variation name
* Out-of-stock status (transparent swatch with diagonal strike)
* "+N" counter for excess swatches
* Click on swatch → syncs with native WooCommerce select (single product)
* Compatible with JetEngine Listing Grid (event delegation, no re-init needed)
* Complete style controls in Elementor panel

== File Structure ==

jetengine-color-swatches.php       – Plugin entry point
includes/
  class-admin-settings.php         – Admin panel, term meta, media uploader
  class-swatches-renderer.php      – Swatch HTML generation
  class-elementor-widget.php       – Elementor widget with all controls
assets/
  css/swatches-frontend.css        – Frontend styles
  css/swatches-admin.css           – Admin styles
  js/swatches-frontend.js          – Frontend interaction (click, sync WC)
  js/swatches-admin.js             – Color picker, media uploader admin

== Changelog ==

= 1.0.0 =
* Initial release
