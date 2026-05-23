<?php
defined( 'ABSPATH' ) || exit;

/**
 * Inietta gli swatch nella pagina singolo prodotto WooCommerce,
 * sopra ogni select nativo delle variazioni.
 *
 * Usa il filtro woocommerce_dropdown_variation_attribute_options_html
 * che WooCommerce chiama per ogni attributo variabile.
 */
final class JECS_Single_Product {

	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		/*
		 * Priorità 10: filtriamo l'HTML del select per ogni attributo
		 * e anteponiamo la riga degli swatch.
		 */
		add_filter(
			'woocommerce_dropdown_variation_attribute_options_html',
			[ $this, 'prepend_swatches' ],
			10,
			2
		);
	}

	/**
	 * Costruisce l'HTML degli swatch e lo antepone al select nativo.
	 *
	 * @param string $html   HTML originale del <select>.
	 * @param array  $args   Argomenti passati da wc_dropdown_variation_attribute_options().
	 * @return string
	 */
	public function prepend_swatches( string $html, array $args ): string {
		/** @var WC_Product_Variable $product */
		$product = $args['product'] ?? null;

		if ( ! $product || ! $product instanceof WC_Product_Variable ) {
			return $html;
		}

		$taxonomy = $args['attribute'] ?? '';
		if ( ! $taxonomy ) {
			return $html;
		}

		/* Normalizza: WC a volte passa il nome senza prefisso pa_ */
		if ( strpos( $taxonomy, 'pa_' ) !== 0 ) {
			$taxonomy = wc_attribute_taxonomy_name( $taxonomy );
		}

		/* Verifica che la tassonomia esista davvero come attributo WC */
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return $html;
		}

		/* Swatch abilitati per questo attributo? Se no, restituisce l'HTML originale intatto */
		if ( ! JECS_Admin_Settings::is_enabled( $taxonomy ) ) {
			return $html;
		}

		/* Raccoglie i termini disponibili per questo prodotto */
		$term_slugs = $args['options'] ?? [];
		if ( empty( $term_slugs ) ) {
			return $html;
		}

		$terms = [];
		foreach ( $term_slugs as $slug ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( $term ) {
				$terms[] = $term;
			}
		}

		if ( empty( $terms ) ) {
			return $html;
		}

		/* Swatch disponibili (variazioni non esaurite) */
		$available_slugs = $this->get_available_slugs( $product, $taxonomy );

		$size = 20;
		$gap  = 6;

		$items = '';
		foreach ( $terms as $term ) {
			$items .= JECS_Swatches_Renderer::instance()->build_swatch_item_public(
				$term->term_id,
				$term,
				[
					'size'         => $size,
					'shape'        => 'circle',
					'show_tooltip' => true,
					'link_to'      => 'none',
				],
				$available_slugs
			);
		}

		$attr_label = wc_attribute_label( $taxonomy );

		$swatch_row = sprintf(
			'<div class="jecs-swatches jecs-swatches--single" data-taxonomy="%s" data-product-id="%d" style="--jecs-size:%dpx;--jecs-gap:%dpx;" aria-label="%s">%s</div>',
			esc_attr( $taxonomy ),
			$product->get_id(),
			$size,
			$gap,
			esc_attr( $attr_label ),
			$items
		);

		/*
		 * Aggiunge la classe jecs-hidden-select al <select> per permettere
		 * all'opzione CSS "Nascondi select" di funzionare.
		 * Il select rimane nel DOM per la sincronizzazione WC.
		 */
		$html = preg_replace( '/<select/', '<select class="jecs-native-select"', $html, 1 );

		return $swatch_row . $html;
	}

	/**
	 * Restituisce gli slug disponibili (variazioni in stock) per una tassonomia.
	 */
	private function get_available_slugs( WC_Product_Variable $product, string $taxonomy ): array {
		$variations = $product->get_available_variations();
		$slugs      = [];
		foreach ( $variations as $v ) {
			$key = 'attribute_' . $taxonomy;
			if ( isset( $v['attributes'][ $key ] ) && $v['attributes'][ $key ] !== '' ) {
				$slugs[] = $v['attributes'][ $key ];
			}
		}
		return array_unique( $slugs );
	}
}
