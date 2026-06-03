<?php
defined( 'ABSPATH' ) || exit;

/**
 * Gestisce le impostazioni admin per gli swatch colori.
 *
 * ABILITAZIONE PER ATTRIBUTO
 * Usa l'opzione WordPress 'jecs_enabled_attributes' (array indicizzato per attribute_id).
 * La checkbox "Abilita Swatch Colori" appare nella pagina modifica/aggiunta attributo WC.
 * Il salvataggio avviene tramite i hook nativi woocommerce_attribute_updated/added.
 * Il tipo attributo rimane 'select' — non viene mai modificato dal plugin.
 *
 * MIGRAZIONE AUTOMATICA
 * Se esistono attributi con type 'jecs_color' (approccio precedente), vengono
 * automaticamente migrati: il tipo viene ripristinato a 'select' e l'abilitazione
 * viene salvata nell'opzione. La migrazione gira una sola volta.
 *
 * CONFIGURAZIONE PER TERMINE
 * I dati vengono salvati come term_meta:
 *   _jecs_type     => 'color' | 'image'
 *   _jecs_color_1  => '#rrggbb'
 *   _jecs_color_2  => '#rrggbb' (vuoto = colore singolo)
 *   _jecs_image_id => attachment_id
 */
final class JECS_Admin_Settings {

	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'admin_menu',            [ $this, 'add_settings_page' ] );

		/* Migrazione da jecs_color type a option-based (gira una sola volta) */
		add_action( 'admin_init', [ $this, 'maybe_migrate' ], 20 );

		/* Checkbox nella pagina Attributi WooCommerce */
		add_action( 'woocommerce_after_add_attribute_fields',  [ $this, 'render_add_enable_checkbox' ] );
		add_action( 'woocommerce_after_edit_attribute_fields', [ $this, 'render_edit_enable_checkbox' ] );

		/* Salva lo stato abilitazione quando WooCommerce salva l'attributo */
		add_action( 'woocommerce_attribute_added',   [ $this, 'save_enable_state_on_add' ],    10, 2 );
		add_action( 'woocommerce_attribute_updated', [ $this, 'save_enable_state_on_update' ], 10, 3 );

		/* Hooks sui termini degli attributi prodotto */
		add_action( 'created_term', [ $this, 'save_term_meta' ], 10, 3 );
		add_action( 'edited_term',  [ $this, 'save_term_meta' ], 10, 3 );

		/* Campi colore/immagine per ogni tassonomia pa_* abilitata */
		add_action( 'init', [ $this, 'register_attribute_hooks' ], 20 );
	}

	/* ---------------------------------------------------------------
	 * Migrazione automatica da tipo 'jecs_color' a option-based
	 * --------------------------------------------------------------- */

	public function maybe_migrate(): void {
		if ( get_option( 'jecs_v2_migrated' ) ) {
			return;
		}

		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_type = 'jecs_color'"
		);

		if ( ! empty( $rows ) ) {
			$options = get_option( 'jecs_enabled_attributes', [] );
			foreach ( $rows as $row ) {
				$options[ (int) $row->attribute_id ] = true;
				$wpdb->update(
					$wpdb->prefix . 'woocommerce_attribute_taxonomies',
					[ 'attribute_type' => 'select' ],
					[ 'attribute_id'   => (int) $row->attribute_id ],
					[ '%s' ],
					[ '%d' ]
				);
			}
			update_option( 'jecs_enabled_attributes', $options, false );
			delete_transient( 'wc_attribute_taxonomies' );
		}

		update_option( 'jecs_v2_migrated', true );
	}

	/* ---------------------------------------------------------------
	 * API pubblica
	 * --------------------------------------------------------------- */

	/**
	 * Ritorna true se gli swatch sono abilitati per la tassonomia data.
	 * Legge dall'opzione 'jecs_enabled_attributes' (array di attribute_id => true).
	 *
	 * @param string $taxonomy es. 'pa_colore'
	 */
	public static function is_enabled( string $taxonomy ): bool {
		static $cache = [];
		if ( array_key_exists( $taxonomy, $cache ) ) {
			return $cache[ $taxonomy ];
		}
		$enabled_ids = get_option( 'jecs_enabled_attributes', [] );
		$attributes  = wc_get_attribute_taxonomies();
		foreach ( $attributes as $attr ) {
			if ( wc_attribute_taxonomy_name( $attr->attribute_name ) === $taxonomy ) {
				$cache[ $taxonomy ] = ! empty( $enabled_ids[ (int) $attr->attribute_id ] );
				return $cache[ $taxonomy ];
			}
		}
		$cache[ $taxonomy ] = false;
		return false;
	}

	/* ---------------------------------------------------------------
	 * Checkbox "Abilita Swatch" nella form AGGIUNGI attributo
	 * --------------------------------------------------------------- */

	public function render_add_enable_checkbox(): void {
		?>
		<div class="form-field">
			<label for="jecs_enable_swatches"><?php esc_html_e( 'Abilita Swatch', 'je-color-swatches' ); ?></label>
			<input type="checkbox" name="jecs_enable_swatches" id="jecs_enable_swatches" value="1" />
			<p class="description"><?php esc_html_e( 'Abilita la gestione degli swatch per questo attributo.', 'je-color-swatches' ); ?></p>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------
	 * Checkbox "Abilita Swatch" nella form MODIFICA attributo
	 * --------------------------------------------------------------- */

	public function render_edit_enable_checkbox(): void {
		$attribute_id = absint( $_GET['edit'] ?? 0 );
		if ( ! $attribute_id ) {
			return;
		}
		$enabled_ids = get_option( 'jecs_enabled_attributes', [] );
		$is_checked  = ! empty( $enabled_ids[ $attribute_id ] );
		$swatch_type = get_option( 'jecs_swatch_type_' . $attribute_id, 'color' );
		$show_in_listing = get_option( 'jecs_show_in_listing_' . $attribute_id, false );
		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="jecs_enable_swatches"><?php esc_html_e( 'Abilita Swatch', 'je-color-swatches' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" name="jecs_enable_swatches" id="jecs_enable_swatches" value="1" <?php checked( $is_checked ); ?> />
						<?php esc_html_e( 'Abilita la gestione degli swatch per questo attributo.', 'je-color-swatches' ); ?>
					</label>
				</td>
			</tr>
			<tr class="jecs-swatch-type-row" style="<?php echo $is_checked ? '' : 'display:none;'; ?>">
				<th scope="row">
					<label for="jecs_swatch_type"><?php esc_html_e( 'Tipo Swatch', 'je-color-swatches' ); ?></label>
				</th>
				<td>
					<select name="jecs_swatch_type" id="jecs_swatch_type" class="regular-text">
						<option value="color" <?php selected( $swatch_type, 'color' ); ?>><?php esc_html_e( 'Colore', 'je-color-swatches' ); ?></option>
						<option value="text" <?php selected( $swatch_type, 'text' ); ?>><?php esc_html_e( 'Testo (es. taglie)', 'je-color-swatches' ); ?></option>
						<option value="image" <?php selected( $swatch_type, 'image' ); ?>><?php esc_html_e( 'Immagine', 'je-color-swatches' ); ?></option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Seleziona il tipo di swatch da utilizzare per questo attributo.', 'je-color-swatches' ); ?>
					</p>
					<p class="description" style="color: #666;">
						<?php printf( esc_html__( 'Tipo corrente salvato: %s', 'je-color-swatches' ), '<strong>' . esc_html( $swatch_type ?: 'non impostato' ) . '</strong>' ); ?>
					</p>
				</td>
			</tr>
			<tr class="jecs-show-in-listing-row" style="<?php echo $is_checked ? '' : 'display:none;'; ?>">
				<th scope="row">
					<label for="jecs_show_in_listing"><?php esc_html_e( 'Mostra nel listing', 'je-color-swatches' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" name="jecs_show_in_listing" id="jecs_show_in_listing" value="1" <?php checked( $show_in_listing ); ?> />
						<?php esc_html_e( 'Mostra questo attributo nei listing (griglia prodotti).', 'je-color-swatches' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<script>
		jQuery(document).ready(function($) {
			$('#jecs_enable_swatches').on('change', function() {
				$('.jecs-swatch-type-row, .jecs-show-in-listing-row').toggle($(this).is(':checked'));
			});
		});
		</script>
		<?php
	}

	/* ---------------------------------------------------------------
	 * Salvataggio stato abilitazione (hook nativi WooCommerce)
	 * --------------------------------------------------------------- */

	public function save_enable_state_on_add( int $id, array $data ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( ! empty( $_POST['jecs_enable_swatches'] ) ) {
			$options        = get_option( 'jecs_enabled_attributes', [] );
			$options[ $id ] = true;
			update_option( 'jecs_enabled_attributes', $options, false );

			// Salva il tipo di swatch
			$swatch_type = isset( $_POST['jecs_swatch_type'] ) ? sanitize_key( $_POST['jecs_swatch_type'] ) : 'color';
			update_option( 'jecs_swatch_type_' . $id, $swatch_type, false );

			// Salva il flag show in listing
			$show_in_listing = ! empty( $_POST['jecs_show_in_listing'] );
			update_option( 'jecs_show_in_listing_' . $id, $show_in_listing, false );
		}
	}

	public function save_enable_state_on_update( int $id, array $data, string $old_name ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$options = get_option( 'jecs_enabled_attributes', [] );
		if ( ! empty( $_POST['jecs_enable_swatches'] ) ) {
			$options[ $id ] = true;

			// Salva il tipo di swatch
			$swatch_type = isset( $_POST['jecs_swatch_type'] ) ? sanitize_key( $_POST['jecs_swatch_type'] ) : 'color';
			update_option( 'jecs_swatch_type_' . $id, $swatch_type, false );

			// Salva il flag show in listing
			$show_in_listing = ! empty( $_POST['jecs_show_in_listing'] );
			update_option( 'jecs_show_in_listing_' . $id, $show_in_listing, false );
		} else {
			unset( $options[ $id ] );

			// Rimuovi il tipo di swatch
			delete_option( 'jecs_swatch_type_' . $id );

			// Rimuovi il flag show in listing
			delete_option( 'jecs_show_in_listing_' . $id );
		}
		update_option( 'jecs_enabled_attributes', $options, false );
	}

	/* ---------------------------------------------------------------
	 * Registra hooks specifici per ogni tassonomia pa_* abilitata
	 * --------------------------------------------------------------- */

	public function register_attribute_hooks(): void {
		$attribute_taxonomies = wc_get_attribute_taxonomies();
		if ( empty( $attribute_taxonomies ) ) {
			return;
		}
		foreach ( $attribute_taxonomies as $tax ) {
			$taxonomy = wc_attribute_taxonomy_name( $tax->attribute_name );
			/* Aggiungi sempre i hooks — is_enabled() viene controllato dentro i metodi */
			add_action( "{$taxonomy}_add_form_fields",  [ $this, 'add_term_fields' ] );
			add_action( "{$taxonomy}_edit_form_fields", [ $this, 'edit_term_fields' ], 10, 2 );
			/* Colonna swatch solo per attributi abilitati */
			if ( self::is_enabled( $taxonomy ) ) {
				add_filter( "manage_{$taxonomy}_custom_column", [ $this, 'render_term_column' ], 10, 3 );
				add_filter( "manage_edit-{$taxonomy}_columns",  [ $this, 'add_term_column' ] );
			}
		}
	}

	/* ---------------------------------------------------------------
	 * Menu
	 * --------------------------------------------------------------- */

	public function add_settings_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Swatch', 'je-color-swatches' ),
			__( 'Swatch', 'je-color-swatches' ),
			'manage_woocommerce',
			'jecs-settings',
			[ $this, 'render_settings_page' ]
		);
		add_submenu_page(
			'woocommerce',
			__( 'Sincronizza JetSmartFilter', 'je-color-swatches' ),
			__( 'Sincronizza JetSmartFilter', 'je-color-swatches' ),
			'manage_woocommerce',
			'jecs-smartfilter-sync',
			[ $this, 'render_smartfilter_sync_page' ]
		);
	}

	public function render_settings_page(): void {
		$attribute_taxonomies = wc_get_attribute_taxonomies();
		$attr_edit_url        = admin_url( 'admin.php?page=product_attributes' );
		?>
		<div class="wrap jecs-settings-wrap">
			<h1><?php esc_html_e( 'JetEngine Color Swatches', 'je-color-swatches' ); ?></h1>
			<p>
				<?php esc_html_e( 'Per abilitare gli swatch su un attributo vai su', 'je-color-swatches' ); ?>
				<a href="<?php echo esc_url( $attr_edit_url ); ?>"><?php esc_html_e( 'WooCommerce → Attributi', 'je-color-swatches' ); ?></a>,
				<?php esc_html_e( 'modifica l\'attributo e spunta la checkbox "Abilita Swatch".', 'je-color-swatches' ); ?>
			</p>
			<?php if ( empty( $attribute_taxonomies ) ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Nessun attributo trovato. Creane uno da WooCommerce → Attributi.', 'je-color-swatches' ); ?></p></div>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Attributo', 'je-color-swatches' ); ?></th>
							<th><?php esc_html_e( 'Tipo', 'je-color-swatches' ); ?></th>
							<th><?php esc_html_e( 'Swatch abilitati', 'je-color-swatches' ); ?></th>
							<th><?php esc_html_e( 'Azioni', 'je-color-swatches' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $attribute_taxonomies as $tax ) :
						$taxonomy  = wc_attribute_taxonomy_name( $tax->attribute_name );
						$enabled   = self::is_enabled( $taxonomy );
						$terms_url = admin_url( 'edit-tags.php?taxonomy=' . $taxonomy . '&post_type=product' );
						$edit_url  = admin_url( 'admin.php?page=product_attributes&edit=' . absint( $tax->attribute_id ) );
					?>
						<tr>
							<td><?php echo esc_html( $tax->attribute_label ); ?></td>
							<td><?php echo esc_html( $tax->attribute_type ); ?></td>
							<td>
								<?php if ( $enabled ) : ?>
									<span style="color:#00a32a;">&#10003; <?php esc_html_e( 'Sì', 'je-color-swatches' ); ?></span>
								<?php else : ?>
									<span style="color:#999;">&#8212;</span>
								<?php endif; ?>
							</td>
							<td>
								<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Modifica attributo', 'je-color-swatches' ); ?></a>
								<?php if ( $enabled ) : ?>
									&nbsp;|&nbsp; <a href="<?php echo esc_url( $terms_url ); ?>"><?php esc_html_e( 'Gestisci termini →', 'je-color-swatches' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------
	 * Pagina sincronizzazione JetSmartFilter
	 * --------------------------------------------------------------- */

	public function render_smartfilter_sync_page(): void {
		// Gestisci il form submission
		if ( isset( $_POST['jecs_sync_nonce'] ) && wp_verify_nonce( $_POST['jecs_sync_nonce'], 'jecs_sync_action' ) ) {
			$filter_id = isset( $_POST['filter_id'] ) ? absint( $_POST['filter_id'] ) : 0;
			$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_text_field( $_POST['taxonomy'] ) : '';
			if ( $filter_id && $taxonomy ) {
				$result = $this->sync_filter_colors( $filter_id, $taxonomy );
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $result ) . '</p></div>';
			}
		}

		// Ottieni tutti i filtri JetSmartFilter
		$filters = $this->get_smartfilters();

		// Ottieni tutti gli attributi con swatch abilitati
		$enabled_attributes = $this->get_enabled_attributes();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Sincronizza JetSmartFilter', 'je-color-swatches' ); ?></h1>
			<p><?php esc_html_e( 'Sincronizza i colori dagli attributi WooCommerce con i filtri visuali JetSmartFilter.', 'je-color-swatches' ); ?></p>

			<?php if ( empty( $filters ) ) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e( 'Nessun filtro JetSmartFilter trovato. Assicurati che JetSmartFilter sia attivo e che esistano filtri di tipo "Visual".', 'je-color-swatches' ); ?></p>
				</div>
			<?php elseif ( empty( $enabled_attributes ) ) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e( 'Nessun attributo con swatch abilitato trovato. Vai su WooCommerce → Attributi per abilitare gli swatch.', 'je-color-swatches' ); ?></p>
				</div>
			<?php else : ?>
				<form method="post">
					<?php wp_nonce_field( 'jecs_sync_action', 'jecs_sync_nonce' ); ?>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="filter_id"><?php esc_html_e( 'Seleziona Filtro', 'je-color-swatches' ); ?></label>
							</th>
							<td>
								<select name="filter_id" id="filter_id" class="regular-text">
									<option value=""><?php esc_html_e( '-- Seleziona un filtro --', 'je-color-swatches' ); ?></option>
									<?php foreach ( $filters as $filter ) : ?>
										<option value="<?php echo esc_attr( $filter->ID ); ?>">
											<?php echo esc_html( $filter->post_title ); ?> (ID: <?php echo esc_html( $filter->ID ); ?>)
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'Seleziona il filtro visuale JetSmartFilter da sincronizzare.', 'je-color-swatches' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="taxonomy"><?php esc_html_e( 'Seleziona Attributo', 'je-color-swatches' ); ?></label>
							</th>
							<td>
								<select name="taxonomy" id="taxonomy" class="regular-text">
									<option value=""><?php esc_html_e( '-- Seleziona un attributo --', 'je-color-swatches' ); ?></option>
									<?php foreach ( $enabled_attributes as $attr ) : ?>
										<option value="<?php echo esc_attr( $attr['taxonomy'] ); ?>">
											<?php echo esc_html( $attr['label'] ); ?> (<?php echo esc_html( $attr['taxonomy'] ); ?>)
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'Seleziona l\'attributo WooCommerce da cui sincronizzare i colori.', 'je-color-swatches' ); ?>
								</p>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Sincronizza Colori', 'je-color-swatches' ), 'primary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Ottiene tutti i filtri JetSmartFilter
	 */
	private function get_smartfilters(): array {
		$args = [
			'post_type'      => 'jet-smart-filters',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];
		return get_posts( $args );
	}

	/**
	 * Ottiene tutti gli attributi con swatch abilitati
	 */
	private function get_enabled_attributes(): array {
		$attribute_taxonomies = wc_get_attribute_taxonomies();
		$enabled = [];
		foreach ( $attribute_taxonomies as $tax ) {
			$taxonomy = wc_attribute_taxonomy_name( $tax->attribute_name );
			if ( self::is_enabled( $taxonomy ) ) {
				$enabled[] = [
					'taxonomy' => $taxonomy,
					'label'    => $tax->attribute_label,
				];
			}
		}
		return $enabled;
	}

	/**
	 * Sincronizza i colori degli attributi con il filtro JetSmartFilter
	 */
	private function sync_filter_colors( int $filter_id, string $taxonomy ): string {
		$filter = get_post( $filter_id );
		if ( ! $filter || $filter->post_type !== 'jet-smart-filters' ) {
			return __( 'Filtro non valido.', 'je-color-swatches' );
		}

		if ( empty( $taxonomy ) || strpos( $taxonomy, 'pa_' ) !== 0 ) {
			return __( 'L\'attributo selezionato non sembra essere un attributo prodotto WooCommerce.', 'je-color-swatches' );
		}

		// Verifica che gli swatch siano abilitati per questa tassonomia
		if ( ! self::is_enabled( $taxonomy ) ) {
			return sprintf( __( 'Gli swatch non sono abilitati per l\'attributo %s.', 'je-color-swatches' ), $taxonomy );
		}

		// Ottieni tutti i termini della tassonomia
		$terms = get_terms( [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		] );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return __( 'Nessun termine trovato per questa tassonomia.', 'je-color-swatches' );
		}

		// Costruisci l'array delle scelte per il filtro visuale
		$choices = [];
		foreach ( $terms as $term ) {
			$color_1 = get_term_meta( $term->term_id, '_jecs_color_1', true );
			if ( $color_1 ) {
				$choices[] = [
					'label'          => $term->name,
					'value'          => '',
					'selected_value' => (string) $term->term_id,
					'source_color'   => $color_1,
					'source_image'   => '',
					'id'             => uniqid(),
				];
			}
		}

		if ( empty( $choices ) ) {
			return __( 'Nessun colore trovato per i termini di questa tassonomia.', 'je-color-swatches' );
		}

		// Aggiorna il meta key _source_color_image_input con le scelte sincronizzate
		update_post_meta( $filter_id, '_source_color_image_input', $choices );

		// Assicura che il tipo sia impostato su color
		update_post_meta( $filter_id, '_color_image_type', 'color' );

		// Aggiorna la tassonomia del filtro per corrispondere a quella selezionata
		update_post_meta( $filter_id, '_query_var', $taxonomy );
		update_post_meta( $filter_id, '_source_taxonomy', $taxonomy );

		return sprintf(
			__( 'Sincronizzazione completata con successo! %d colori sincronizzati per l\'attributo %s.', 'je-color-swatches' ),
			count( $choices ),
			$taxonomy
		);
	}

	/* ---------------------------------------------------------------
	 * Helper per ottenere il tipo di swatch dell'attributo
	 * --------------------------------------------------------------- */

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
				return get_option( 'jecs_swatch_type_' . $attribute_id, '' );
			}
		}

		return '';
	}

	/* ---------------------------------------------------------------
	 * Campi nella pagina "Aggiungi termine"
	 * --------------------------------------------------------------- */

	public function add_term_fields( string $taxonomy ): void {
		if ( ! self::is_enabled( $taxonomy ) ) {
			return;
		}

		$attribute_type = $this->get_attribute_swatch_type( $taxonomy );
		$type = $attribute_type ?: 'color';
		?>
		<div class="form-field jecs-field-wrap jecs-color-fields jecs-color-fields-add" style="<?php echo $type === 'color' ? '' : 'display:none;'; ?>">
			<label><?php esc_html_e( 'Colore 1', 'je-color-swatches' ); ?></label>
			<input type="text" name="jecs_color_1" value="#ffffff" class="jecs-color-picker" />
		</div>
		<div class="form-field jecs-field-wrap jecs-color-fields jecs-color-fields-add" style="<?php echo $type === 'color' ? '' : 'display:none;'; ?>">
			<label><?php esc_html_e( 'Colore 2 (opzionale – swatch diviso)', 'je-color-swatches' ); ?></label>
			<input type="text" name="jecs_color_2" value="" class="jecs-color-picker" placeholder="<?php esc_attr_e( 'Lascia vuoto per colore singolo', 'je-color-swatches' ); ?>" />
		</div>
		<div class="form-field jecs-field-wrap jecs-color-fields jecs-color-fields-add" style="<?php echo $type === 'color' ? '' : 'display:none;'; ?>">
			<label><?php esc_html_e( 'Colore 3 (opzionale – swatch triplo)', 'je-color-swatches' ); ?></label>
			<input type="text" name="jecs_color_3" value="" class="jecs-color-picker" placeholder="<?php esc_attr_e( 'Lascia vuoto per due o un colore', 'je-color-swatches' ); ?>" />
		</div>
		<div class="form-field jecs-field-wrap jecs-image-fields jecs-image-fields-add" style="<?php echo $type === 'image' ? '' : 'display:none;'; ?>">
			<label><?php esc_html_e( 'Immagine Swatch', 'je-color-swatches' ); ?></label>
			<?php $this->render_image_uploader( 0 ); ?>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------
	 * Campi nella pagina "Modifica termine"
	 * --------------------------------------------------------------- */

	public function edit_term_fields( $term, string $taxonomy ): void {
		if ( ! self::is_enabled( $taxonomy ) ) {
			return;
		}

		$attribute_type = $this->get_attribute_swatch_type( $taxonomy );
		$type = $attribute_type ?: ( get_term_meta( $term->term_id, '_jecs_type', true ) ?: 'color' );
		$color_1  = get_term_meta( $term->term_id, '_jecs_color_1',  true ) ?: '#ffffff';
		$color_2  = get_term_meta( $term->term_id, '_jecs_color_2',  true ) ?: '';
		$color_3  = get_term_meta( $term->term_id, '_jecs_color_3',  true ) ?: '';
		$image_id = (int) get_term_meta( $term->term_id, '_jecs_image_id', true );
		?>
		<tr class="form-field jecs-field-wrap jecs-color-fields" style="<?php echo $type === 'color' ? '' : 'display:none;'; ?>">
			<th><label><?php esc_html_e( 'Colore 1', 'je-color-swatches' ); ?></label></th>
			<td>
				<input type="text" name="jecs_color_1" value="<?php echo esc_attr( $color_1 ); ?>" class="jecs-color-picker" />
				<p class="description"><?php esc_html_e( 'Colore principale dello swatch.', 'je-color-swatches' ); ?></p>
			</td>
		</tr>
		<tr class="form-field jecs-field-wrap jecs-color-fields" style="<?php echo $type === 'color' ? '' : 'display:none;'; ?>">
			<th><label><?php esc_html_e( 'Colore 2', 'je-color-swatches' ); ?></label></th>
			<td>
				<input type="text" name="jecs_color_2" value="<?php echo esc_attr( $color_2 ); ?>" class="jecs-color-picker" placeholder="<?php esc_attr_e( 'Lascia vuoto per colore singolo', 'je-color-swatches' ); ?>" />
				<p class="description"><?php esc_html_e( 'Se impostato lo swatch sarà diviso a metà.', 'je-color-swatches' ); ?></p>
			</td>
		</tr>
		<tr class="form-field jecs-field-wrap jecs-color-fields" style="<?php echo $type === 'color' ? '' : 'display:none;'; ?>">
			<th><label><?php esc_html_e( 'Colore 3', 'je-color-swatches' ); ?></label></th>
			<td>
				<input type="text" name="jecs_color_3" value="<?php echo esc_attr( $color_3 ); ?>" class="jecs-color-picker" placeholder="<?php esc_attr_e( 'Lascia vuoto per due o un colore', 'je-color-swatches' ); ?>" />
				<p class="description"><?php esc_html_e( 'Se impostato lo swatch sarà diviso in tre parti uguali.', 'je-color-swatches' ); ?></p>
			</td>
		</tr>
		<tr class="form-field jecs-field-wrap jecs-image-fields" style="<?php echo $type === 'image' ? '' : 'display:none;'; ?>">
			<th><label><?php esc_html_e( 'Immagine Swatch', 'je-color-swatches' ); ?></label></th>
			<td><?php $this->render_image_uploader( $image_id ); ?></td>
		</tr>
		<?php
	}

	/* ---------------------------------------------------------------
	 * Helpers HTML
	 * --------------------------------------------------------------- */

	private function render_type_select( string $current ): void {
		?>
		<select name="jecs_type" class="jecs-type-select">
			<option value="color" <?php selected( $current, 'color' ); ?>><?php esc_html_e( 'Colore', 'je-color-swatches' ); ?></option>
			<option value="text" <?php selected( $current, 'text' ); ?>><?php esc_html_e( 'Testo (es. taglie)', 'je-color-swatches' ); ?></option>
			<option value="image" <?php selected( $current, 'image' ); ?>><?php esc_html_e( 'Immagine', 'je-color-swatches' ); ?></option>
		</select>
		<?php
	}

	private function render_image_uploader( int $image_id ): void {
		$src = $image_id ? $this->get_image_url( $image_id, 'thumbnail' ) : '';
		?>
		<div class="jecs-image-uploader">
			<input type="hidden" name="jecs_image_id" value="<?php echo esc_attr( $image_id ?: '' ); ?>" class="jecs-image-id" />
			<div class="jecs-image-preview" style="<?php echo $src ? '' : 'display:none;'; ?>">
				<img src="<?php echo esc_url( $src ); ?>" style="max-width:80px; max-height:80px;" />
			</div>
			<button type="button" class="button jecs-upload-btn"><?php esc_html_e( 'Seleziona immagine', 'je-color-swatches' ); ?></button>
			<button type="button" class="button jecs-remove-img-btn" style="<?php echo $src ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Rimuovi', 'je-color-swatches' ); ?></button>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------
	 * Helper per immagini webp
	 * --------------------------------------------------------------- */

	/**
	 * Ottiene l'URL dell'immagine, preferendo la versione webp se disponibile
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
	 * Salvataggio term_meta
	 * --------------------------------------------------------------- */

	public function save_term_meta( int $term_id, int $tt_id, string $taxonomy ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! self::is_enabled( $taxonomy ) ) {
			return;
		}
		if ( isset( $_POST['jecs_type'] ) ) {
			$type = sanitize_key( $_POST['jecs_type'] );
			update_term_meta( $term_id, '_jecs_type', in_array( $type, [ 'color', 'text', 'image' ], true ) ? $type : 'color' );
		}
		if ( isset( $_POST['jecs_color_1'] ) ) {
			update_term_meta( $term_id, '_jecs_color_1', sanitize_hex_color( $_POST['jecs_color_1'] ) ?: '#ffffff' );
		}
		$color_2 = isset( $_POST['jecs_color_2'] ) ? sanitize_hex_color( $_POST['jecs_color_2'] ) : '';
		update_term_meta( $term_id, '_jecs_color_2', $color_2 );

		$color_3 = isset( $_POST['jecs_color_3'] ) ? sanitize_hex_color( $_POST['jecs_color_3'] ) : '';
		update_term_meta( $term_id, '_jecs_color_3', $color_3 );

		if ( isset( $_POST['jecs_image_id'] ) ) {
			update_term_meta( $term_id, '_jecs_image_id', absint( $_POST['jecs_image_id'] ) );
		}
	}

	/* ---------------------------------------------------------------
	 * Colonna "Swatch" nella lista termini
	 * --------------------------------------------------------------- */

	public function add_term_column( array $columns ): array {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'name' === $key ) {
				$new['jecs_swatch'] = __( 'Swatch', 'je-color-swatches' );
			}
		}
		return $new;
	}

	public function render_term_column( string $content, string $column, int $term_id ): string {
		if ( 'jecs_swatch' !== $column ) {
			return $content;
		}
		return JECS_Swatches_Renderer::instance()->render_swatch_for_term( $term_id, true );
	}

	/* ---------------------------------------------------------------
	 * Assets admin
	 * --------------------------------------------------------------- */

	public function enqueue_admin_assets( string $hook ): void {
		if ( ! in_array( $hook, [ 'edit-tags.php', 'term.php' ], true ) ) {
			return;
		}
		$taxonomy = $_GET['taxonomy'] ?? '';
		if ( strpos( $taxonomy, 'pa_' ) !== 0 || ! self::is_enabled( $taxonomy ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'jecs-admin', JECS_PLUGIN_URL . 'assets/css/swatches-admin.css', [], JECS_VERSION );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_script( 'jecs-admin', JECS_PLUGIN_URL . 'assets/js/swatches-admin.js', [ 'jquery', 'wp-color-picker' ], JECS_VERSION, true );
	}
}
