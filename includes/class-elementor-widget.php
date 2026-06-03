<?php
defined( 'ABSPATH' ) || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

/**
 * Widget Elementor: Color Swatches Variazioni
 * Funziona sia nel JetEngine Listing Grid che nella pagina singolo prodotto.
 */
class JECS_Elementor_Widget extends Widget_Base {

	public function get_name(): string {
		return 'jecs_color_swatches';
	}

	public function get_title(): string {
		return esc_html__( 'Color Swatches Variazioni', 'je-color-swatches' );
	}

	public function get_icon(): string {
		return 'eicon-product-images';
	}

	public function get_categories(): array {
		return [ 'woocommerce-elements', 'jet-listing-elements', 'general' ];
	}

	public function get_keywords(): array {
		return [ 'swatch', 'variazione', 'colore', 'woocommerce', 'prodotto', 'jetengine' ];
	}

	/* ---------------------------------------------------------------
	 * Controlli
	 * --------------------------------------------------------------- */
	protected function register_controls(): void {

		/* ---- Sezione: Contenuto ---- */
		$this->start_controls_section( 'section_content', [
			'label' => esc_html__( 'Impostazioni', 'je-color-swatches' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'product_id_source', [
			'label'   => esc_html__( 'Sorgente ID Prodotto', 'je-color-swatches' ),
			'type'    => Controls_Manager::SELECT,
			'options' => [
				'auto'   => esc_html__( 'Automatico (post corrente / JetEngine loop)', 'je-color-swatches' ),
				'manual' => esc_html__( 'ID manuale', 'je-color-swatches' ),
			],
			'default' => 'auto',
		] );

		$this->add_control( 'product_id', [
			'label'     => esc_html__( 'ID Prodotto', 'je-color-swatches' ),
			'type'      => Controls_Manager::NUMBER,
			'condition' => [ 'product_id_source' => 'manual' ],
		] );

		$this->add_control( 'filter_taxonomy', [
			'label'       => esc_html__( 'Filtra per Attributo (tassonomia pa_*)', 'je-color-swatches' ),
			'type'        => Controls_Manager::TEXT,
			'placeholder' => 'pa_colore',
			'description' => esc_html__( 'Lascia vuoto per mostrare tutti gli attributi con swatch configurati.', 'je-color-swatches' ),
		] );

		$this->add_control( 'max_swatches', [
			'label'   => esc_html__( 'Max swatch visibili (0 = tutti)', 'je-color-swatches' ),
			'type'    => Controls_Manager::NUMBER,
			'default' => 0,
			'min'     => 0,
		] );

		$image_sizes = get_intermediate_image_sizes();
		$image_size_options = [];
		foreach ( $image_sizes as $size ) {
			$image_size_options[ $size ] = ucfirst( $size );
		}
		$image_size_options['full'] = esc_html__( 'Full size (originale)', 'je-color-swatches' );

		$this->add_control( 'variation_image_size', [
			'label'   => esc_html__( 'Dimensione immagine variazione', 'je-color-swatches' ),
			'type'    => Controls_Manager::SELECT,
			'options' => $image_size_options,
			'default' => 'large',
			'description' => esc_html__( 'Usa la stessa dimensione dell\'immagine principale del listing per caricamenti più leggeri.', 'je-color-swatches' ),
		] );

		$this->add_control( 'link_to', [
			'label'   => esc_html__( 'Link swatch', 'je-color-swatches' ),
			'type'    => Controls_Manager::SELECT,
			'options' => [
				'none'    => esc_html__( 'Nessuno', 'je-color-swatches' ),
				'product' => esc_html__( 'Pagina prodotto', 'je-color-swatches' ),
			],
			'default' => 'none',
		] );

		$this->add_control( 'show_tooltip', [
			'label'        => esc_html__( 'Mostra tooltip nome', 'je-color-swatches' ),
			'type'         => Controls_Manager::SWITCHER,
			'label_on'     => esc_html__( 'Sì', 'je-color-swatches' ),
			'label_off'    => esc_html__( 'No', 'je-color-swatches' ),
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->end_controls_section();

		/* ---- Sezione: Stile ---- */
		$this->start_controls_section( 'section_style', [
			'label' => esc_html__( 'Stile', 'je-color-swatches' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'swatch_shape', [
			'label'   => esc_html__( 'Forma', 'je-color-swatches' ),
			'type'    => Controls_Manager::SELECT,
			'options' => [
				'circle'  => esc_html__( 'Cerchio', 'je-color-swatches' ),
				'square'  => esc_html__( 'Quadrato', 'je-color-swatches' ),
				'rounded' => esc_html__( 'Rettangolo arrotondato', 'je-color-swatches' ),
			],
			'default' => 'circle',
		] );

		$this->add_control( 'swatch_size', [
			'label'   => esc_html__( 'Dimensione (px)', 'je-color-swatches' ),
			'type'    => Controls_Manager::SLIDER,
			'range'   => [ 'px' => [ 'min' => 12, 'max' => 80 ] ],
			'default' => [ 'size' => 16, 'unit' => 'px' ],
			'selectors' => [
				'{{WRAPPER}} .jecs-swatches' => '--jecs-size: {{SIZE}}{{UNIT}};',
			],
		] );

		$this->add_control( 'swatch_gap', [
			'label'   => esc_html__( 'Spaziatura (px)', 'je-color-swatches' ),
			'type'    => Controls_Manager::SLIDER,
			'range'   => [ 'px' => [ 'min' => 0, 'max' => 24 ] ],
			'default' => [ 'size' => 6, 'unit' => 'px' ],
			'selectors' => [
				'{{WRAPPER}} .jecs-swatches' => '--jecs-gap: {{SIZE}}{{UNIT}};',
			],
		] );

		$this->add_control( 'swatch_align', [
			'label'   => esc_html__( 'Allineamento', 'je-color-swatches' ),
			'type'    => Controls_Manager::CHOOSE,
			'options' => [
				'left'   => [
					'title' => esc_html__( 'Sinistra', 'je-color-swatches' ),
					'icon'  => 'eicon-h-align-left',
				],
				'center' => [
					'title' => esc_html__( 'Centro', 'je-color-swatches' ),
					'icon'  => 'eicon-h-align-center',
				],
				'right'  => [
					'title' => esc_html__( 'Destra', 'je-color-swatches' ),
					'icon'  => 'eicon-h-align-right',
				],
			],
			'default' => 'left',
			'selectors' => [
				'{{WRAPPER}} .jecs-swatches' => 'justify-content: {{VALUE}} !important;',
			],
		] );

		$this->add_control( 'border_width', [
			'label'   => esc_html__( 'Bordo (px)', 'je-color-swatches' ),
			'type'    => Controls_Manager::SLIDER,
			'range'   => [ 'px' => [ 'min' => 0, 'max' => 6 ] ],
			'default' => [ 'size' => 2, 'unit' => 'px' ],
			'selectors' => [
				'{{WRAPPER}} .jecs-swatch__inner' => 'border-width: {{SIZE}}{{UNIT}};',
			],
		] );

		$this->add_control( 'border_color', [
			'label'     => esc_html__( 'Colore bordo', 'je-color-swatches' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '#e0e0e0',
			'selectors' => [
				'{{WRAPPER}} .jecs-swatch__inner' => 'border-color: {{VALUE}};',
			],
		] );

		$this->add_control( 'border_color_hover', [
			'label'     => esc_html__( 'Colore bordo hover/attivo', 'je-color-swatches' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '#000000',
			'selectors' => [
				'{{WRAPPER}} .jecs-swatch:hover .jecs-swatch__inner'  => 'border-color: {{VALUE}};',
				'{{WRAPPER}} .jecs-swatch.jecs-swatch--active .jecs-swatch__inner' => 'border-color: {{VALUE}};',
			],
		] );

		$this->add_control( 'oos_opacity', [
			'label'   => esc_html__( 'Opacità out-of-stock', 'je-color-swatches' ),
			'type'    => Controls_Manager::SLIDER,
			'range'   => [ 'px' => [ 'min' => 0, 'max' => 100 ] ],
			'default' => [ 'size' => 40 ],
			'selectors' => [
				'{{WRAPPER}} .jecs-swatch--oos' => 'opacity: calc({{SIZE}} / 100);',
			],
		] );

		$this->add_control( 'tooltip_bg', [
			'label'     => esc_html__( 'Sfondo tooltip', 'je-color-swatches' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '#222222',
			'selectors' => [
				'{{WRAPPER}} .jecs-swatch__tooltip' => 'background: {{VALUE}};',
			],
		] );

		$this->add_control( 'tooltip_color', [
			'label'     => esc_html__( 'Colore testo tooltip', 'je-color-swatches' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '#ffffff',
			'selectors' => [
				'{{WRAPPER}} .jecs-swatch__tooltip' => 'color: {{VALUE}};',
			],
		] );

		$this->end_controls_section();
	}

	/* ---------------------------------------------------------------
	 * Rendering frontend
	 * --------------------------------------------------------------- */
	protected function render(): void {
		$settings = $this->get_settings_for_display();

		$product_id = null;
		if ( 'manual' === $settings['product_id_source'] && ! empty( $settings['product_id'] ) ) {
			$product_id = (int) $settings['product_id'];
		} else {
			/*
			 * In un JetEngine Listing Grid il post corrente viene impostato
			 * tramite setup_postdata() prima di rendere il template.
			 * get_the_ID() restituisce quindi il prodotto corretto.
			 */
			$product_id = (int) get_the_ID();
		}

		if ( ! $product_id ) {
			if ( isset( \Elementor\Plugin::$instance->editor ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p style="color:#999;font-size:12px;">' . esc_html__( 'Nessun prodotto trovato.', 'je-color-swatches' ) . '</p>';
			}
			return;
		}

		// Determina il contesto: se siamo in un listing grid, usa 'listing'
		// TODO: Aggiungere controllo migliore per determinare il contesto
		$is_listing = isset( $settings['product_id_source'] ) && 'current' === $settings['product_id_source'];

		$options = [
			'size'         => (int) ( $settings['swatch_size']['size'] ?? 28 ),
			'shape'        => $settings['swatch_shape'] ?? 'circle',
			'show_tooltip' => 'yes' === ( $settings['show_tooltip'] ?? 'yes' ),
			'link_to'      => $settings['link_to'] ?? 'none',
			'max_swatches' => (int) ( $settings['max_swatches'] ?? 0 ),
			'gap'          => (int) ( $settings['swatch_gap']['size'] ?? 6 ),
			'image_size'   => $settings['variation_image_size'] ?? 'large',
			'context'      => 'listing', // Forzato a listing per testare il filtro
		];

		$taxonomy = ! empty( $settings['filter_taxonomy'] ) ? sanitize_key( $settings['filter_taxonomy'] ) : null;

		$html = JECS_Swatches_Renderer::instance()->render( $product_id, $taxonomy, $options );

		if ( empty( $html ) ) {
			if ( isset( \Elementor\Plugin::$instance->editor ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p style="color:#999;font-size:12px;">' . esc_html__( 'Nessuna variazione con swatch trovata per questo prodotto.', 'je-color-swatches' ) . '</p>';
			}
			return;
		}

		echo $html; // PHPCS: XSS ok – HTML generato internamente e già escaped.
	}

	/* Anteprima editor testuale */
	protected function content_template(): void {
		?>
		<div class="jecs-swatches" style="--jecs-size:16px;--jecs-gap:6px;">
			<span class="jecs-swatch jecs-swatch--circle jecs-swatch--color">
				<span class="jecs-swatch__inner" style="background:linear-gradient(135deg,#e74c3c 50%,#2c3e50 50%);border:2px solid #e0e0e0;"></span>
			</span>
			<span class="jecs-swatch jecs-swatch--circle jecs-swatch--color">
				<span class="jecs-swatch__inner" style="background-color:#3498db;border:2px solid #e0e0e0;"></span>
			</span>
			<span class="jecs-swatch jecs-swatch--circle jecs-swatch--color">
				<span class="jecs-swatch__inner" style="background-color:#2ecc71;border:2px solid #e0e0e0;"></span>
			</span>
		</div>
		<?php
	}
}
