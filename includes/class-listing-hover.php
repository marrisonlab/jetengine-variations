<?php
defined( 'ABSPATH' ) || exit;

/**
 * Hover image nel listing JetEngine.
 *
 * Inietta data-hover-src="URL_prima_immagine_galleria" sul tag <img>
 * della thumbnail di ogni prodotto che ha almeno un'immagine nella galleria.
 * Il tag viene processato lato JS per lo swap al mouseenter.
 */
final class JECS_Listing_Hover {

	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'post_thumbnail_html', [ $this, 'inject_hover_src' ], 10, 5 );
	}

	/**
	 * Aggiunge data-hover-src all'<img> della thumbnail se il prodotto
	 * ha immagini nella galleria.
	 *
	 * @param string $html            HTML originale della thumbnail.
	 * @param int    $post_id         ID del post/prodotto.
	 * @param int    $post_thumbnail_id ID dell'attachment thumbnail.
	 * @param string $size            Dimensione richiesta.
	 * @param array  $attr            Attributi extra.
	 * @return string HTML modificato.
	 */
	public function inject_hover_src( string $html, int $post_id, int $post_thumbnail_id, $size, $attr ): string {
		if ( empty( $html ) ) {
			return $html;
		}

		// Funziona solo per prodotti WooCommerce
		if ( ! function_exists( 'wc_get_product' ) ) {
			return $html;
		}

		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return $html;
		}

		$gallery_ids = $product->get_gallery_image_ids();
		if ( empty( $gallery_ids ) ) {
			return $html;
		}

		// Prima immagine della galleria nella stessa dimensione della thumbnail
		$hover_url = wp_get_attachment_image_url( $gallery_ids[0], $size ?: 'woocommerce_thumbnail' );
		if ( ! $hover_url ) {
			return $html;
		}

		// Inietta data-hover-src sul primo <img> trovato nell'HTML
		$html = preg_replace(
			'/<img\s/',
			'<img data-hover-src="' . esc_url( $hover_url ) . '" ',
			$html,
			1
		);

		return $html;
	}
}
