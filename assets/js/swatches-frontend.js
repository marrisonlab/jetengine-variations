/* JetEngine Color Swatches – Frontend JS */
/* global jQuery, jecsData */
(function ($) {
	'use strict';

	/**
	 * Gestisce i click sugli swatch:
	 * - Nella pagina singolo prodotto: aggiorna il select nativo WooCommerce
	 *   per attivare la gestione variazioni di WC.
	 * - Nel listing JetEngine: gestisce solo lo stato visivo (active).
	 */

	var isSingleProduct = $('body').hasClass('single-product');

	/**
	 * Aggiorna la label dell'attributo mostrando il valore selezionato.
	 * es. "Colore" → "Colore: NERO", oppure ripristina l'originale se vuoto.
	 */
	function updateLabel(taxonomy, selectedLabel) {
		var $label = $('label[for="' + taxonomy + '"]');
		if (!$label.length) return;

		if (!$label.data('jecs-original')) {
			$label.data('jecs-original', $label.text());
		}
		var original = $label.data('jecs-original');

		if (selectedLabel) {
			var escaped = $('<span>').text(selectedLabel).html();
			$label.html(original + ': <span style="text-transform:capitalize;">' + escaped + '</span>');
		} else {
			$label.text(original);
		}
	}

	function handleSwatchClick() {
		var $swatch   = $(this);
		var $swatches = $swatch.closest('.jecs-swatches');
		var taxonomy  = $swatches.data('taxonomy');
		var term      = $swatch.data('term');

		/* Toggle attivo */
		if ($swatch.hasClass('jecs-swatch--active')) {
			$swatch.removeClass('jecs-swatch--active');
			if (isSingleProduct) {
				syncNativeSelect(taxonomy, '');
			}
			updateLabel(taxonomy, '');
			return;
		}

		$swatches.find('.jecs-swatch').removeClass('jecs-swatch--active');
		$swatch.addClass('jecs-swatch--active');
		updateLabel(taxonomy, $swatch.attr('aria-label') || '');

		if (isSingleProduct) {
			syncNativeSelect(taxonomy, term);
		} else {
			updateListingImage($swatch);
		}
	}

	/**
	 * Nel listing, aggiorna l'immagine del card con quella della variazione selezionata.
	 * Usa overlay per mantenere le proprietà CSS e dimensioni dell'immagine originale.
	 */
	function updateListingImage($swatch) {
		var imgUrl = $swatch.attr('data-image');
		if (!imgUrl) return;
		var $card = $swatch.closest('.e-con-inner, .e-con, .jet-listing-item, .jet-loop-item');
		if (!$card.length) return;
		var $img = $card.find('img.jet-listing-dynamic-image__img');
		if (!$img.length) return;

		/* Rimuovi overlay esistente se presente */
		var $wrapper = $img.closest('.jecs-img-wrapper');
		if ($wrapper.length) {
			$wrapper.find('.jecs-hover-overlay').remove();
			$img.unwrap();
		}

		/* Crea nuovo overlay con immagine variazione */
		var computedStyle = window.getComputedStyle($img[0]);
		var $overlay = $('<img class="jecs-hover-overlay">').css({
			position: 'absolute',
			top: 0,
			left: 0,
			width: $img[0].style.width || computedStyle.width,
			height: $img[0].style.height || computedStyle.height,
			'max-width': $img[0].style.maxWidth || computedStyle.maxWidth,
			'max-height': $img[0].style.maxHeight || computedStyle.maxHeight,
			opacity: 0,
			transition: 'opacity 0.2s ease',
			'object-fit': $img[0].style.objectFit || computedStyle.objectFit || 'cover',
			'border-radius': computedStyle.borderRadius || '0',
			'box-sizing': computedStyle.boxSizing || 'border-box'
		}).attr('src', imgUrl).attr('srcset', '');

		$img.css('position', 'relative').wrap('<span class="jecs-img-wrapper" style="display:inline-block;position:relative;"></span>').after($overlay);
		$overlay[0].offsetHeight;
		$overlay.css('opacity', '1');
	}

	/**
	 * Sincronizza il select nativo WooCommerce con lo swatch selezionato.
	 * I select di WooCommerce hanno id="pa_colore" o simili.
	 */
	function syncNativeSelect(taxonomy, termSlug) {
		var $select = $('select#' + taxonomy + ', .variations select[name="attribute_' + taxonomy + '"]');
		if (!$select.length) return;

		$select.val(termSlug).trigger('change');
	}

	/**
	 * Quando WooCommerce resetta le variazioni (pulsante "Cancella"), 
	 * rimuove la classe active dagli swatch corrispondenti.
	 */
	$(document).on('click', '.reset_variations', function () {
		var $form = $(this).closest('.variations_form');
		$form.find('.jecs-swatch--active').removeClass('jecs-swatch--active');
		$form.find('.jecs-swatches').each(function () {
			updateLabel($(this).data('taxonomy'), '');
		});
	});

	/**
	 * Risponde al cambio del select nativo (es. l'utente cambia da selettore WC)
	 * e riflette la selezione sugli swatch.
	 */
	$(document).on('change', '.variations select', function () {
		var name = $(this).attr('name'); // attribute_pa_colore
		if (!name) return;
		var taxonomy = name.replace('attribute_', '');
		var val      = $(this).val();

		$('.jecs-swatches[data-taxonomy="' + taxonomy + '"]').each(function () {
			$(this).find('.jecs-swatch').removeClass('jecs-swatch--active');
			if (val) {
				var $active = $(this).find('.jecs-swatch[data-term="' + val + '"]').addClass('jecs-swatch--active');
				updateLabel(taxonomy, $active.attr('aria-label') || val);
			} else {
				updateLabel(taxonomy, '');
			}
		});
	});

	/* ---- Delega click (funziona anche con elementi aggiunti da JetEngine/AJAX) ---- */
	$(document).on('click', '.jecs-swatch:not(.jecs-swatch--oos)', handleSwatchClick);

	/* ---- Hover image: swap con prima immagine della galleria nel listing ---- */
	/*
	 * Usa native addEventListener in capture phase (terzo parametro = true).
	 * La capture phase bypassa qualsiasi stopPropagation chiamato da Elementor/JetEngine,
	 * garantendo che l'evento arrivi sempre al nostro handler.
	 */

	/* Riscrive URL immagine alla stessa dimensione dell'immagine principale */
	function jecsResizeImage( url, referenceUrl ) {
		if ( ! url || ! referenceUrl ) return url;

		/* Estrae dimensione dall'URL di riferimento (es. -550x772.jpg) */
		var sizeMatch = referenceUrl.match( /-(\d+)x(\d+)\.([^.]+)$/ );
		if ( ! sizeMatch ) return url;

		var width = sizeMatch[1];
		var height = sizeMatch[2];
		var ext = sizeMatch[3];

		/* Rimuove eventuali dimensioni esistenti dall'URL target */
		var resizedUrl = url.replace( /-\d+x\d+\.[^.]+$/, '.' + ext );

		/* Aggiunge la nuova dimensione */
		return resizedUrl.replace( '.' + ext, '-' + width + 'x' + height + '.' + ext );
	}

	function jecsPreload( img ) {
		var src = img.getAttribute('data-hover-src');
		if ( src && ! img.getAttribute('data-jecs-preloaded') ) {
			var resizedSrc = jecsResizeImage( src, img.src );
			( new Image() ).src = resizedSrc;
			img.setAttribute('data-jecs-preloaded', '1');
			img.setAttribute('data-jecs-hover-src-resized', resizedSrc );
		}
	}

	/* Preload all'avvio e dopo AJAX JetEngine */
	function jecsPreloadAll() {
		document.querySelectorAll('img[data-hover-src]').forEach( jecsPreload );
	}
	document.addEventListener('DOMContentLoaded', jecsPreloadAll);
	$(document).on('jet-engine/listing/after-render jet-engine/listing/after-ajax-render', jecsPreloadAll);

	/* mouseover in capture: il mouse entra nel container o in un suo figlio */
	document.addEventListener('mouseover', function ( e ) {
		var container = e.target.closest('.jet-listing-dynamic-image');
		if ( ! container ) return;
		var img = container.querySelector('img[data-hover-src]');
		if ( ! img ) return;
		if ( img.getAttribute('data-jecs-hovering') ) return; // già in hover
		var hoverSrc = img.getAttribute('data-hover-src');
		if ( ! hoverSrc ) return;
		/* Salva originali la prima volta */
		if ( ! img.getAttribute('data-jecs-orig-src') ) {
			img.setAttribute('data-jecs-orig-src',    img.src);
			img.setAttribute('data-jecs-orig-srcset', img.srcset || '');
		}
		img.setAttribute('data-jecs-hovering', '1');
		/* Usa l'URL ridimensionato se disponibile, altrimenti usa l'originale */
		var resizedHoverSrc = img.getAttribute('data-jecs-hover-src-resized') || hoverSrc;
		/* Crea overlay con immagine hover, copiando proprietà CSS originali */
		var computedStyle = window.getComputedStyle(img);
		var $overlay = $('<img class="jecs-hover-overlay">').css({
			position: 'absolute',
			top: 0,
			left: 0,
			width: img.style.width || computedStyle.width,
			height: img.style.height || computedStyle.height,
			'max-width': img.style.maxWidth || computedStyle.maxWidth,
			'max-height': img.style.maxHeight || computedStyle.maxHeight,
			opacity: 0,
			transition: 'opacity 0.2s ease',
			'object-fit': img.style.objectFit || computedStyle.objectFit || 'cover',
			'border-radius': computedStyle.borderRadius || '0',
			'box-sizing': computedStyle.boxSizing || 'border-box'
		}).attr('src', resizedHoverSrc).attr('srcset', '');
		$(img).css('position', 'relative').wrap('<span class="jecs-img-wrapper" style="display:inline-block;position:relative;"></span>').after($overlay);
		/* Force reflow */
		$overlay[0].offsetHeight;
		$overlay.css('opacity', '1');
	}, true );

	/* mouseout in capture: il mouse lascia il container (non solo i figli) */
	document.addEventListener('mouseout', function ( e ) {
		var container = e.target.closest('.jet-listing-dynamic-image');
		if ( ! container ) return;
		/* Ignora eventi tra figli dello stesso container */
		if ( e.relatedTarget && container.contains( e.relatedTarget ) ) return;
		var img = container.querySelector('img[data-hover-src]');
		if ( ! img || ! img.getAttribute('data-jecs-hovering') ) return;
		var $wrapper = $(img).closest('.jecs-img-wrapper');
		var $overlay = $wrapper.find('.jecs-hover-overlay');
		if ( ! $overlay.length ) return;
		img.removeAttribute('data-jecs-hovering');
		$overlay.css('opacity', '0');
		setTimeout(function () {
			$overlay.remove();
			$(img).unwrap(); /* rimuove il wrapper span */
		}, 200);
	}, true );

}(jQuery));
