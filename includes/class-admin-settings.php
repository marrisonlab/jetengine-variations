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
	 * Checkbox "Abilita Swatch Colori" nella form AGGIUNGI attributo
	 * --------------------------------------------------------------- */

	public function render_add_enable_checkbox(): void {
		?>
		<div class="form-field">
			<label for="jecs_enable_swatches"><?php esc_html_e( 'Abilita Swatch Colori', 'je-color-swatches' ); ?></label>
			<input type="checkbox" name="jecs_enable_swatches" id="jecs_enable_swatches" value="1" />
			<p class="description"><?php esc_html_e( 'Abilita la gestione degli swatch colore per questo attributo.', 'je-color-swatches' ); ?></p>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------
	 * Checkbox "Abilita Swatch Colori" nella form MODIFICA attributo
	 * --------------------------------------------------------------- */

	public function render_edit_enable_checkbox(): void {
		$attribute_id = absint( $_GET['edit'] ?? 0 );
		if ( ! $attribute_id ) {
			return;
		}
		$enabled_ids = get_option( 'jecs_enabled_attributes', [] );
		$is_checked  = ! empty( $enabled_ids[ $attribute_id ] );
		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="jecs_enable_swatches"><?php esc_html_e( 'Abilita Swatch Colori', 'je-color-swatches' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" name="jecs_enable_swatches" id="jecs_enable_swatches" value="1" <?php checked( $is_checked ); ?> />
						<?php esc_html_e( 'Abilita la gestione degli swatch colore per questo attributo.', 'je-color-swatches' ); ?>
					</label>
				</td>
			</tr>
		</table>
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
		}
	}

	public function save_enable_state_on_update( int $id, array $data, string $old_name ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$options = get_option( 'jecs_enabled_attributes', [] );
		if ( ! empty( $_POST['jecs_enable_swatches'] ) ) {
			$options[ $id ] = true;
		} else {
			unset( $options[ $id ] );
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
			__( 'Swatch Colori', 'je-color-swatches' ),
			__( 'Swatch Colori', 'je-color-swatches' ),
			'manage_woocommerce',
			'jecs-settings',
			[ $this, 'render_settings_page' ]
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
				<?php esc_html_e( 'modifica l\'attributo e spunta la checkbox "Abilita Swatch Colori".', 'je-color-swatches' ); ?>
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
	 * Campi nella pagina "Aggiungi termine"
	 * --------------------------------------------------------------- */

	public function add_term_fields( string $taxonomy ): void {
		if ( ! self::is_enabled( $taxonomy ) ) {
			return;
		}
		?>
		<div class="form-field jecs-field-wrap">
			<label><?php esc_html_e( 'Tipo Swatch', 'je-color-swatches' ); ?></label>
			<?php $this->render_type_select( 'color' ); ?>
		</div>
		<div class="form-field jecs-field-wrap jecs-color-fields jecs-color-fields-add">
			<label><?php esc_html_e( 'Colore 1', 'je-color-swatches' ); ?></label>
			<input type="text" name="jecs_color_1" value="#ffffff" class="jecs-color-picker" />
		</div>
		<div class="form-field jecs-field-wrap jecs-color-fields jecs-color-fields-add">
			<label><?php esc_html_e( 'Colore 2 (opzionale – swatch diviso)', 'je-color-swatches' ); ?></label>
			<input type="text" name="jecs_color_2" value="" class="jecs-color-picker" placeholder="<?php esc_attr_e( 'Lascia vuoto per colore singolo', 'je-color-swatches' ); ?>" />
		</div>
		<div class="form-field jecs-field-wrap jecs-color-fields jecs-color-fields-add">
			<label><?php esc_html_e( 'Colore 3 (opzionale – swatch triplo)', 'je-color-swatches' ); ?></label>
			<input type="text" name="jecs_color_3" value="" class="jecs-color-picker" placeholder="<?php esc_attr_e( 'Lascia vuoto per due o un colore', 'je-color-swatches' ); ?>" />
		</div>
		<div class="form-field jecs-field-wrap jecs-image-fields jecs-image-fields-add" style="display:none;">
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
		$type     = get_term_meta( $term->term_id, '_jecs_type',     true ) ?: 'color';
		$color_1  = get_term_meta( $term->term_id, '_jecs_color_1',  true ) ?: '#ffffff';
		$color_2  = get_term_meta( $term->term_id, '_jecs_color_2',  true ) ?: '';
		$color_3  = get_term_meta( $term->term_id, '_jecs_color_3',  true ) ?: '';
		$image_id = (int) get_term_meta( $term->term_id, '_jecs_image_id', true );
		?>
		<tr class="form-field jecs-field-wrap">
			<th><label><?php esc_html_e( 'Tipo Swatch', 'je-color-swatches' ); ?></label></th>
			<td><?php $this->render_type_select( $type ); ?></td>
		</tr>
		<tr class="form-field jecs-field-wrap jecs-color-fields" <?php echo in_array( $type, [ 'image', 'text' ], true ) ? 'style="display:none;"' : ''; ?>>
			<th><label><?php esc_html_e( 'Colore 1', 'je-color-swatches' ); ?></label></th>
			<td>
				<input type="text" name="jecs_color_1" value="<?php echo esc_attr( $color_1 ); ?>" class="jecs-color-picker" />
				<p class="description"><?php esc_html_e( 'Colore principale dello swatch.', 'je-color-swatches' ); ?></p>
			</td>
		</tr>
		<tr class="form-field jecs-field-wrap jecs-color-fields" <?php echo in_array( $type, [ 'image', 'text' ], true ) ? 'style="display:none;"' : ''; ?>>
			<th><label><?php esc_html_e( 'Colore 2', 'je-color-swatches' ); ?></label></th>
			<td>
				<input type="text" name="jecs_color_2" value="<?php echo esc_attr( $color_2 ); ?>" class="jecs-color-picker" placeholder="<?php esc_attr_e( 'Lascia vuoto per colore singolo', 'je-color-swatches' ); ?>" />
				<p class="description"><?php esc_html_e( 'Se impostato lo swatch sarà diviso a metà.', 'je-color-swatches' ); ?></p>
			</td>
		</tr>
		<tr class="form-field jecs-field-wrap jecs-color-fields" <?php echo in_array( $type, [ 'image', 'text' ], true ) ? 'style="display:none;"' : ''; ?>>
			<th><label><?php esc_html_e( 'Colore 3', 'je-color-swatches' ); ?></label></th>
			<td>
				<input type="text" name="jecs_color_3" value="<?php echo esc_attr( $color_3 ); ?>" class="jecs-color-picker" placeholder="<?php esc_attr_e( 'Lascia vuoto per due o un colore', 'je-color-swatches' ); ?>" />
				<p class="description"><?php esc_html_e( 'Se impostato lo swatch sarà diviso in tre parti uguali.', 'je-color-swatches' ); ?></p>
			</td>
		</tr>
		<tr class="form-field jecs-field-wrap jecs-image-fields" <?php echo $type !== 'image' ? 'style="display:none;"' : ''; ?>>
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
		$src = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
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
