(function ($) {
	'use strict';

	function normalizeHost(host) {
		host = (host || '').toLowerCase().trim();
		if (host.indexOf('www.') === 0) {
			host = host.slice(4);
		}
		return host;
	}

	function parseDomains(raw) {
		if (!raw) {
			return [];
		}
		return raw.split(/[\s,]+/).map(function (d) {
			return normalizeHost(d);
		}).filter(Boolean);
	}

	function extractSlugFromUrl(url) {
		try {
			var parsed = new URL(url);
			var parts = parsed.pathname.replace(/^\/+|\/+$/g, '').split('/');
			var last = parts.pop() || '';
			return last.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
		} catch (e) {
			return '';
		}
	}

	function buildTargetUrl(originalUrl, suffix) {
		if (!originalUrl) {
			return '';
		}
		if (!suffix) {
			return originalUrl;
		}

		try {
			var url = new URL(originalUrl);
			var suffixQuery = suffix.replace(/^[?&]/, '');
			var suffixParams = new URLSearchParams(suffixQuery);
			suffixParams.forEach(function (value, key) {
				url.searchParams.set(key, value);
			});
			return url.toString();
		} catch (e) {
			return originalUrl + (suffix.charAt(0) === '?' ? suffix : '?' + suffix.replace(/^[?&]/, ''));
		}
	}

	function detectPartnerForUrl(url) {
		var host;
		try {
			host = normalizeHost(new URL(url).hostname);
		} catch (e) {
			return '';
		}

		var match = '';
		$('#lw_relink_partner option').each(function () {
			var $opt = $(this);
			if (!$opt.val()) {
				return;
			}
			var domains = parseDomains($opt.data('domains') || '');
			if (domains.indexOf(host) !== -1) {
				match = $opt.val();
				return false;
			}
		});
		return match;
	}

	function isPartnerMode() {
		var partner = $('#lw_relink_partner').val();
		var original = $('#lw_relink_original_url').val();
		return partner && original;
	}

	function updateTargetPreview() {
		var partner = $('#lw_relink_partner').val();
		var original = $('#lw_relink_original_url').val();
		var $target = $('#lw_relink_target_url');
		var $preview = $('#lw_relink_target_preview');
		var $wrap = $('#lw_relink_target_preview_wrap');
		var $desc = $('#lw_relink_target_desc');

		if (partner && original) {
			var suffix = $('#lw_relink_partner option:selected').data('suffix') || '';
			var target = buildTargetUrl(original, suffix);
			$target.val(target).prop('readonly', true);
			$preview.text(target);
			$wrap.show();
			$desc.text(lwRelinkMetabox.i18n.targetComputed || '');
		} else {
			$target.prop('readonly', false);
			$wrap.hide();
			$desc.text(lwRelinkMetabox.i18n.targetManual || '');
		}
	}

	function updateShortSlugSuggestion() {
		var original = $('#lw_relink_original_url').val();
		var $short = $('#lw_relink_short_path');
		if (!original || ($short.val() && $short.data('user-edited'))) {
			return;
		}
		var slug = extractSlugFromUrl(original);
		if (slug) {
			$short.val(slug);
			$short.trigger('input');
		}
	}

	function suggestPartner() {
		var original = $('#lw_relink_original_url').val();
		var $partner = $('#lw_relink_partner');
		if (!original || $partner.data('user-selected')) {
			return;
		}
		var detected = detectPartnerForUrl(original);
		if (detected) {
			$partner.val(detected);
		}
	}

	$(function () {
		$('#lw_relink_original_url').on('input blur', function () {
			suggestPartner();
			updateShortSlugSuggestion();
			updateTargetPreview();
		});

		$('#lw_relink_partner').on('change', function () {
			$(this).data('user-selected', true);
			updateTargetPreview();
		});

		$('#lw_relink_short_path').on('input', function () {
			$(this).data('user-edited', true);
		});

		updateTargetPreview();
	});
}(jQuery));
