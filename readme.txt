=== JetEngine Color Swatches ===
Contributors: Angelo
Tags: woocommerce, elementor, jetengine, swatch, variazioni, colore
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
WC requires at least: 7.0
License: GPLv2 or later

Widget Elementor per mostrare swatch colori delle variazioni WooCommerce.
Compatibile con JetEngine Listing Grid e pagina singolo prodotto.

== Installazione ==

1. Carica la cartella `jetengine-color-swatches` in `/wp-content/plugins/`
2. Attiva il plugin da WP Admin → Plugin
3. Vai su WooCommerce → Attributi → scegli un attributo → modifica i termini
4. Per ogni termine configura: Tipo (Colore / Immagine), Colore 1, Colore 2 (opzionale)
5. Nel builder Elementor trascina il widget "Color Swatches Variazioni"

== Funzionalità ==

* Swatch colore singolo o doppio (split diagonale 135°)
* Swatch immagine (media library WP)
* Forme: cerchio, quadrato, rettangolo arrotondato
* Tooltip con nome variazione
* Stato out-of-stock (swatch trasparente con barra diagonale)
* Contatore "+N" per swatch in eccesso
* Click sullo swatch → sincronizza il select nativo WooCommerce (singolo prodotto)
* Compatibile con JetEngine Listing Grid (event delegation, no re-init necessario)
* Controlli stile completi nel pannello Elementor

== Struttura file ==

jetengine-color-swatches.php       – Plugin entry point
includes/
  class-admin-settings.php         – Pannello admin, term meta, media uploader
  class-swatches-renderer.php      – Generazione HTML swatch
  class-elementor-widget.php       – Widget Elementor con tutti i controlli
assets/
  css/swatches-frontend.css        – Stili frontend
  css/swatches-admin.css           – Stili admin
  js/swatches-frontend.js          – Interazione frontend (click, sync WC)
  js/swatches-admin.js             – Color picker, media uploader admin

== Changelog ==

= 1.0.0 =
* Prima release
