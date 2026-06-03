<?php
defined( 'ABSPATH' ) || exit;

/**
 * Genera l'HTML degli swatch per un dato prodotto / termine.
 */
final class JECS_Swatches_Renderer {

	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/* ---------------------------------------------------------------
	 * Helper per immagini webp
	 * --------------------------------------------------------------- */

	/**
	 * Ottiene l'URL dell'immagine, preferendo la versione webp se disponibile
	 *
	 * @param int    $attachment_id ID dell'allegato
	 * @param string $size           Dimensione dell'immagine
	 * @return string|false URL dell'immagine o false
	 */
	private function get_image_url( int $attachment_id, string $size = 'thumbnail' ) {
		$url = wp_get_attachment_image_url( $attachment_id, $size );
		if ( ! $url ) {
			return false;
		}

		// Prova a ottenere la versione webp
		$webp_url = $this->get_webp_url( $url );
		if ( $webp_url && $this->webp_file_exists( $webp_url ) ) {
			return $webp_url;
		}

		return $url;
	}

	/**
	 * Converte un URL immagine in URL webp
	 */
	private function get_webp_url( string $url ): string {
		// Rimuovi query string se presente
		$url = strtok( $url, '?' );

		// Sostituisci estensioni comuni con .webp
		$extensions = [ '.jpg', '.jpeg', '.png', '.gif' ];
		foreach ( $extensions as $ext ) {
			if ( str_ends_with( strtolower( $url ), $ext ) ) {
				return substr( $url, 0, -strlen( $ext ) ) . '.webp';
			}
		}

		return $url . '.webp';
	}

	/**
	 * Verifica se il file webp esiste
	 */
	private function webp_file_exists( string $url ): bool {
		// Converti URL in percorso locale
		$upload_dir = wp_upload_dir();
		$base_url   = $upload_dir['baseurl'];

		if ( strpos( $url, $base_url ) === 0 ) {
			$file_path = $upload_dir['basedir'] . substr( $url, strlen( $base_url ) );
			return file_exists( $file_path );
		}

		return false;
	}

	/* ---------------------------------------------------------------
	 * API pubblica
	 * --------------------------------------------------------------- */

	/**
	 * Renderizza tutti gli swatch di un prodotto per una o tutte le tassonomie.
	 *
	 * @param int|null    $product_id  ID prodotto (null = post corrente nel loop).
	 * @param string|null $taxonomy    Tassonomia specifica (null = tutte).
	 * @param array       $options     Opzioni widget (size, show_tooltip, link, etc.).
	 * @return string HTML
	 */
	public function render( ?int $product_id = null, ?string $taxonomy = null, array $options = [] ): string {
		$product_id = $product_id ?: get_the_ID();
		if ( ! $product_id ) {
			return '';
		}
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			return '';
		}

		// Determina il contesto: se siamo in una pagina prodotto singola o in un listing
		$is_single_product = is_product() && is_singular( 'product' );

		$defaults = [
			'size'         => 16,
			'shape'        => 'circle',   // circle | square | rounded
			'show_tooltip' => true,
			'link_to'      => 'none',     // none | product
			'max_swatches' => 0,          // 0 = illimitato
			'gap'          => 6,
			'context'      => $is_single_product ? 'single' : 'listing',   // single | listing
		];
		$options = wp_parse_args( $options, $defaults );

		$attributes = $product->get_variation_attributes();
		if ( empty( $attributes ) ) {
			return '';
		}

		$output = '';
		foreach ( $attributes as $attr_taxonomy => $values ) {
			if ( $taxonomy && $taxonomy !== $attr_taxonomy ) {
				continue;
			}
			if ( ! JECS_Admin_Settings::is_enabled( $attr_taxonomy ) ) {
				continue;
			}
			// Se siamo in listing, verifica il flag show_in_listing
			if ( 'listing' === $options['context'] && ! $this->is_attribute_show_in_listing( $attr_taxonomy ) ) {
				error_log( 'JECS Listing Filter - Skipping ' . $attr_taxonomy . ' (show_in_listing flag not set)' );
				continue;
			}
			error_log( 'JECS Listing Filter - Rendering ' . $attr_taxonomy . ' (context: ' . $options['context'] . ', is_single_product: ' . ( $is_single_product ? 'true' : 'false' ) . ')' );
			$output .= $this->render_attribute_swatches( $product_id, $attr_taxonomy, $values, $options );
		}

		return $output;
	}

	/**
	 * Renderizza lo swatch di un singolo termine (usato nell'admin e come shortcode).
	 *
	 * @param int  $term_id
	 * @param bool $admin    Se true usa dimensioni piccole per la colonna lista.
	 * @return string HTML
	 */
	public function render_swatch_for_term( int $term_id, bool $admin = false ): string {
		$options = [
			'size'         => $admin ? 24 : 32,
			'shape'        => 'circle',
			'show_tooltip' => false,
			'link_to'      => 'none',
		];
		return $this->build_swatch_item( $term_id, null, $options, [] );
	}

	/**
	 * Wrapper pubblico di build_swatch_item, usato da JECS_Single_Product.
	 */
	public function build_swatch_item_public( int $term_id, $term, array $options, array $available_slugs ): string {
		return $this->build_swatch_item( $term_id, $term, $options, $available_slugs );
	}

	/* ---------------------------------------------------------------
	 * Internals
	 * --------------------------------------------------------------- */

	private function render_attribute_swatches( int $product_id, string $taxonomy, array $values, array $options ): string {
		if ( empty( $values ) ) {
			return '';
		}

		$terms = [];
		foreach ( $values as $value ) {
			$term = get_term_by( 'slug', $value, $taxonomy );
			if ( $term ) {
				$terms[] = $term;
			}
		}

		if ( empty( $terms ) ) {
			return '';
		}

		$max     = (int) $options['max_swatches'];
		$shown   = $max > 0 ? array_slice( $terms, 0, $max ) : $terms;
		$hidden  = $max > 0 ? array_slice( $terms, $max )    : [];

		$size    = (int) $options['size'];
		$gap     = (int) $options['gap'];

		/* Raccoglie le variazioni disponibili per gestire lo stato out-of-stock */
		$available_variations = $this->get_available_term_slugs( $product_id, $taxonomy );
		$image_size = $options['image_size'] ?? 'large';
		$options['variation_images'] = $this->get_variation_image_map( $product_id, $taxonomy, $image_size );

		$items = '';
		foreach ( $shown as $term ) {
			$items .= $this->build_swatch_item( $term->term_id, $term, $options, $available_variations );
		}

		$counter = '';
		if ( ! empty( $hidden ) ) {
			$counter = sprintf(
				'<span class="jecs-swatch-counter" title="%s">+%d</span>',
				esc_attr( implode( ', ', array_column( $hidden, 'name' ) ) ),
				count( $hidden )
			);
		}

		$attr_label = $this->get_attribute_label( $taxonomy );

		return sprintf(
			'<div class="jecs-swatches" data-taxonomy="%s" data-product-id="%d" style="--jecs-size:%dpx; --jecs-gap:%dpx;" aria-label="%s">%s%s</div>',
			esc_attr( $taxonomy ),
			$product_id,
			$size,
			$gap,
			esc_attr( $attr_label ),
			$items,
			$counter
		);
	}

	private function build_swatch_item( int $term_id, $term, array $options, array $available_slugs ): string {
		// Ottieni il tipo di swatch dall'attributo (livello superiore) o fallback al termine
		$taxonomy = $term ? $term->taxonomy : '';
		$attribute_type = $this->get_attribute_swatch_type( $taxonomy );
		$term_type = get_term_meta( $term_id, '_jecs_type', true );

		// Forza l'uso del tipo dell'attributo se definito
		$type = $attribute_type ?: ( $term_type ?: 'color' );
		$color_1 = get_term_meta( $term_id, '_jecs_color_1', true ) ?: '#cccccc';
		$color_2 = get_term_meta( $term_id, '_jecs_color_2', true ) ?: '';
		$color_3 = get_term_meta( $term_id, '_jecs_color_3', true ) ?: '';
		$img_id  = (int) get_term_meta( $term_id, '_jecs_image_id', true );

		$term_name = $term ? $term->name : '';
		$term_slug = $term ? $term->slug : '';

		$shape   = $options['shape']        ?? 'circle';
		$tooltip = $options['show_tooltip'] ?? true;
		$link_to = $options['link_to']      ?? 'none';

		$is_oos = ! empty( $available_slugs ) && ! in_array( $term_slug, $available_slugs, true );

		$classes = [ 'jecs-swatch', "jecs-swatch--{$shape}", "jecs-swatch--{$type}" ];
		if ( $is_oos ) {
			$classes[] = 'jecs-swatch--oos';
		}

		$style_inner = $this->build_swatch_style( $type, $color_1, $color_2, $color_3, $img_id );

		if ( $type === 'text' && $term_name ) {
			/* Swatch testuale: mostra il nome del termine */
			$inner = sprintf(
				'<span class="jecs-swatch__inner" style="%s">%s</span>',
				esc_attr( $style_inner ),
				esc_html( $term_name )
			);
		} else {
			$inner = sprintf(
				'<span class="jecs-swatch__inner" style="%s" aria-hidden="true"></span>',
				esc_attr( $style_inner )
			);

			if ( $tooltip && $term_name ) {
				$inner .= sprintf(
					'<span class="jecs-swatch__tooltip">%s</span>',
					esc_html( $term_name )
				);
			}
		}

		$variation_image = $options['variation_images'][ $term_slug ] ?? '';
		$data_image_attr = $variation_image ? sprintf( ' data-image="%s"', esc_url( $variation_image ) ) : '';

		$data_attrs = sprintf(
			'data-term="%s" data-color1="%s" data-color2="%s" data-color3="%s"%s',
			esc_attr( $term_slug ),
			esc_attr( $color_1 ),
			esc_attr( $color_2 ),
			esc_attr( $color_3 ),
			$data_image_attr
		);

		$aria_label = $term_name ? sprintf( ' aria-label="%s"', esc_attr( $term_name ) ) : '';

		if ( $link_to === 'product' && $term ) {
			/* Determina l'URL del prodotto con parametro variazione preselezionata */
			$product = wc_get_product( get_the_ID() );
			$url     = $product ? get_permalink( $product->get_id() ) : '#';
			$wrapper = sprintf(
				'<a href="%s" class="%s" %s%s>%s</a>',
				esc_url( add_query_arg( 'attribute_' . $this->get_attribute_slug_from_term( $term ), $term_slug, $url ) ),
				esc_attr( implode( ' ', $classes ) ),
				$data_attrs,
				$aria_label,
				$inner
			);
		} else {
			$wrapper = sprintf(
				'<span class="%s" %s%s>%s</span>',
				esc_attr( implode( ' ', $classes ) ),
				$data_attrs,
				$aria_label,
				$inner
			);
		}

		return $wrapper;
	}

	private function build_swatch_style( string $type, string $color_1, string $color_2, string $color_3, int $img_id ): string {
		if ( $type === 'text' ) {
			/* Swatch testuale: stile gestito via CSS, non background */
			return '';
		}

		if ( $type === 'image' && $img_id ) {
			$url = $this->get_image_url( $img_id, 'thumbnail' );
			if ( $url ) {
				return "background-image:url('" . esc_url( $url ) . "');background-size:cover;background-position:center;";
			}
		}

		if ( $color_2 && $color_3 ) {
			/* Swatch diviso in tre fasce uguali */
			return "background: linear-gradient(90deg, {$color_1} 33.33%, {$color_2} 33.33% 66.66%, {$color_3} 66.66%);";
		}

		if ( $color_2 ) {
			/* Swatch diviso diagonalmente a metà */
			return "background: linear-gradient(135deg, {$color_1} 50%, {$color_2} 50%);";
		}

		return "background-color:{$color_1};";
	}

	private function get_available_term_slugs( int $product_id, string $taxonomy ): array {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return [];
		}
		$variations = $product->get_available_variations();
		$slugs      = [];
		foreach ( $variations as $variation ) {
			$key = 'attribute_' . $taxonomy;
			if ( isset( $variation['attributes'][ $key ] ) && $variation['attributes'][ $key ] !== '' ) {
				$slugs[] = $variation['attributes'][ $key ];
			}
		}
		return array_unique( $slugs );
	}

	/**
	 * Mappa term slug → URL immagine della prima variazione che lo contiene.
	 */
	private function get_variation_image_map( int $product_id, string $taxonomy, string $image_size = 'large' ): array {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return [];
		}
		$map     = [];
		$attr_key = 'attribute_' . $taxonomy; // es. 'attribute_pa_colore'
		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation ) {
				continue;
			}
			/* get_variation_attributes() restituisce gli slug dei termini, non i nomi */
			$variation_attrs = $variation->get_variation_attributes();
			$term_slug       = $variation_attrs[ $attr_key ] ?? '';
			if ( empty( $term_slug ) || isset( $map[ $term_slug ] ) ) {
				continue;
			}
			$image_id = $variation->get_image_id();
			if ( ! $image_id ) {
				continue;
			}
			$url = $this->get_image_url( $image_id, $image_size );
			if ( $url ) {
				$map[ $term_slug ] = $url;
			}
		}
		return $map;
	}

	private function get_attribute_label( string $taxonomy ): string {
		$label = wc_attribute_label( $taxonomy );
		return $label ?: str_replace( 'pa_', '', $taxonomy );
	}

	/**
	 * Ottiene il tipo di swatch definito a livello di attributo
	 */
	private function get_attribute_swatch_type( string $taxonomy ): string {
		if ( strpos( $taxonomy, 'pa_' ) !== 0 ) {
			return '';
		}

		$attribute_name = str_replace( 'pa_', '', $taxonomy );
		$attribute_taxonomies = wc_get_attribute_taxonomies();

		foreach ( $attribute_taxonomies as $tax ) {
			if ( $tax->attribute_name === $attribute_name ) {
				$attribute_id = $tax->attribute_id;
				$swatch_type = get_option( 'jecs_swatch_type_' . $attribute_id, '' );
				return $swatch_type;
			}
		}

		return '';
	}

	/**
	 * Verifica se l'attributo deve essere mostrato nel listing
	 */
	private function is_attribute_show_in_listing( string $taxonomy ): bool {
		if ( strpos( $taxonomy, 'pa_' ) !== 0 ) {
			return false;
		}

		$attribute_name = str_replace( 'pa_', '', $taxonomy );
		$attribute_taxonomies = wc_get_attribute_taxonomies();

		foreach ( $attribute_taxonomies as $tax ) {
			if ( $tax->attribute_name === $attribute_name ) {
				$attribute_id = $tax->attribute_id;
				$show_in_listing = get_option( 'jecs_show_in_listing_' . $attribute_id, false );
				return (bool) $show_in_listing;
			}
		}

		return false;
	}

	private function get_attribute_slug_from_term( $term ): string {
		if ( ! $term ) {
			return '';
		}
		return sanitize_title( str_replace( 'pa_', '', $term->taxonomy ) );
	}
}
