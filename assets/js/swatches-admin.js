/* JetEngine Color Swatches – Admin JS */
/* global jQuery, wp */
(function ($) {
	'use strict';

	function initColorPickers() {
		$('.jecs-color-picker').each(function () {
			if ($(this).data('wpColorPicker')) return;
			$(this).wpColorPicker({
				defaultColor: $(this).attr('placeholder') || '#ffffff',
			});
		});
	}

	function toggleFields($row) {
		var type = $row.find('.jecs-type-select').val();
		if (type === 'color') {
			$row.closest('form, tbody').find('.jecs-color-fields').show();
			$row.closest('form, tbody').find('.jecs-image-fields').hide();
		} else if (type === 'text') {
			$row.closest('form, tbody').find('.jecs-color-fields').hide();
			$row.closest('form, tbody').find('.jecs-image-fields').hide();
		} else {
			/* image */
			$row.closest('form, tbody').find('.jecs-color-fields').hide();
			$row.closest('form, tbody').find('.jecs-image-fields').show();
		}
	}

	$(document).on('change', '.jecs-type-select', function () {
		toggleFields($(this).closest('tr, .form-field'));
	});

	/* Media uploader */
	$(document).on('click', '.jecs-upload-btn', function (e) {
		e.preventDefault();
		var $btn = $(this);
		var $wrap = $btn.closest('.jecs-image-uploader');

		var frame = wp.media({
			title: 'Seleziona immagine swatch',
			button: { text: 'Usa questa immagine' },
			multiple: false,
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			$wrap.find('.jecs-image-id').val(attachment.id);
			$wrap.find('.jecs-image-preview img').attr('src', attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url);
			$wrap.find('.jecs-image-preview').show();
			$wrap.find('.jecs-remove-img-btn').show();
		});

		frame.open();
	});

	$(document).on('click', '.jecs-remove-img-btn', function (e) {
		e.preventDefault();
		var $wrap = $(this).closest('.jecs-image-uploader');
		$wrap.find('.jecs-image-id').val('');
		$wrap.find('.jecs-image-preview').hide();
		$(this).hide();
	});

	/* Init on load */
	$(function () {
		initColorPickers();

		/* Toggle iniziale */
		$('.jecs-type-select').each(function () {
			toggleFields($(this).closest('tr, .form-field'));
		});
	});

}(jQuery));
