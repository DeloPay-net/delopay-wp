/* global WPDelopayAdmin */
(function () {
	'use strict';

	if (typeof WPDelopayAdmin === 'undefined') {
		return;
	}

	const I = WPDelopayAdmin.i18n || {};
	const COPY_FEEDBACK_MS = 1400;
	const RELOAD_DELAY_MS  = 600;
	const POPUP_WIDTH      = 520;
	const POPUP_HEIGHT     = 680;
	const POPUP_WATCH_MS   = 500;
	const URL_DEBOUNCE_MS  = 250;

	function restCall(path, options) {
		const opts = Object.assign({
			credentials: 'same-origin',
			headers: {},
		}, options || {});
		opts.headers = Object.assign({
			'Content-Type': 'application/json',
			'X-WP-Nonce': WPDelopayAdmin.nonce,
		}, opts.headers);
		return fetch(WPDelopayAdmin.restUrl + path, opts)
			.then((r) => r.json().then((body) => ({ ok: r.ok, body })));
	}

	function trimSlash(s) {
		return (s || '').replace(/\/+$/, '');
	}

	document.addEventListener('DOMContentLoaded', () => {
		initCopyButtons();
		initImagePickers();
		initEnvironmentSelectors();
		initImportToggle();
		initDeleteConfirms();
		initConnectControls();
		initRefundForms();
		initColorSwatches();
	});

	function initColorSwatches() {
		document.querySelectorAll('.wp-delopay-color-input').forEach((input) => {
			const swatch = input.parentElement
				? input.parentElement.querySelector('.wp-delopay-color-swatch')
				: null;
			if (!swatch) return;

			const update = () => {
				let value = input.value.trim();
				if (value && value[0] !== '#') value = '#' + value;
				const valid = /^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/.test(value);
				swatch.style.background = valid ? value : (input.dataset.default || '');
			};
			input.addEventListener('input', update);
			input.addEventListener('change', update);
		});
	}

	function initCopyButtons() {
		document.querySelectorAll('.wp-delopay-copy-btn').forEach((btn) => {
			btn.addEventListener('click', () => copyFromTarget(btn));
		});
	}

	function copyFromTarget(btn) {
		const target = document.querySelector(btn.dataset.copyTarget);
		if (!target) return;

		const original = btn.textContent;
		const restore = (label, ok) => {
			btn.textContent = label;
			btn.classList.toggle('wp-delopay-copy-ok', !!ok);
			setTimeout(() => {
				btn.textContent = original;
				btn.classList.remove('wp-delopay-copy-ok');
			}, COPY_FEEDBACK_MS);
		};

		const value = target.value || target.textContent || '';
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(value)
				.then(() => restore('Copied ✓', true))
				.catch(() => fallbackCopy(target, restore));
		} else {
			fallbackCopy(target, restore);
		}
	}

	function fallbackCopy(target, restore) {
		try {
			target.focus();
			target.select();
			const ok = document.execCommand('copy');
			restore(ok ? 'Copied ✓' : 'Copy failed', ok);
		} catch (_e) {
			restore('Copy failed', false);
		}
	}

	function initImagePickers() {
		document.querySelectorAll('.wp-delopay-image-picker').forEach(initImagePicker);
	}

	function initImagePicker(root) {
		const pick     = root.querySelector('.wp-delopay-image-pick');
		const clear    = root.querySelector('.wp-delopay-image-clear');
		const idInput  = root.querySelector('.wp-delopay-image-id');
		const urlInput = root.querySelector('.wp-delopay-image-url');
		const preview  = root.querySelector('.wp-delopay-image-preview');
		const empty    = root.dataset.emptyText || 'No image selected';
		let frame = null;

		function showPreview(url) {
			preview.innerHTML = '';
			const img = document.createElement('img');
			img.src = url;
			img.alt = '';
			img.addEventListener('error', () => {
				preview.innerHTML = '<div class="wp-delopay-image-empty">' + (I.imageLoadFailed || 'Could not load image') + '</div>';
			});
			preview.appendChild(img);
		}

		function showEmpty() {
			preview.innerHTML = '<div class="wp-delopay-image-empty">' + empty + '</div>';
		}

		function refreshClearButton() {
			const hasImage = (idInput.value && idInput.value !== '0')
				|| (urlInput && urlInput.value.trim() !== '');
			clear.hidden = !hasImage;
		}

		pick.addEventListener('click', (e) => {
			e.preventDefault();
			if (typeof wp === 'undefined' || !wp.media) return;
			if (!frame) {
				frame = wp.media({
					title:    I.pickImage || 'Choose product image',
					button:   { text: I.useImage || 'Use this image' },
					library:  { type: 'image' },
					multiple: false,
				});
				frame.on('select', () => {
					const a = frame.state().get('selection').first().toJSON();
					idInput.value = a.id;
					if (urlInput) urlInput.value = '';
					const size = (a.sizes && (a.sizes.medium || a.sizes.large || a.sizes.full)) || { url: a.url };
					showPreview(size.url || a.url);
					refreshClearButton();
				});
			}
			frame.open();
		});

		clear.addEventListener('click', (e) => {
			e.preventDefault();
			idInput.value = '';
			if (urlInput) urlInput.value = '';
			showEmpty();
			refreshClearButton();
		});

		if (urlInput) {
			let urlTimer = null;
			const handleUrlChange = () => {
				const value = urlInput.value.trim();
				if (value === '') {
					if (!idInput.value || idInput.value === '0') {
						showEmpty();
					}
					refreshClearButton();
					return;
				}
				idInput.value = '';
				showPreview(value);
				refreshClearButton();
			};
			urlInput.addEventListener('input', () => {
				clearTimeout(urlTimer);
				urlTimer = setTimeout(handleUrlChange, URL_DEBOUNCE_MS);
			});
			urlInput.addEventListener('change', handleUrlChange);
		}
	}

	function initEnvironmentSelectors() {
		document.querySelectorAll('.wp-delopay-env-select').forEach(initEnvironmentSelector);
	}

	function initEnvironmentSelector(select) {
		const customRows      = document.querySelectorAll('.wp-delopay-env-custom-row');
		const summary         = document.querySelector('[data-env-summary]');
		const ccInput         = document.getElementById('wp_delopay_control_center_url');
		const coInput         = document.getElementById('wp_delopay_checkout_base_url');
		const projInput       = document.getElementById('wp_delopay_project_id');
		const profInput       = document.getElementById('wp_delopay_profile_id');
		const brandingLink    = document.querySelector('[data-branding-link]');
		const brandingRow     = document.querySelector('[data-branding-row]');
		const brandingSection = document.querySelector('[data-branding-section]');

		const urls = {
			production: { control: select.dataset.prodControl,    checkout: select.dataset.prodCheckout },
			sandbox:    { control: select.dataset.sandboxControl, checkout: select.dataset.sandboxCheckout },
		};

		const savedCustom = {
			control:  ccInput ? ccInput.value : '',
			checkout: coInput ? coInput.value : '',
		};

		function updateSummary(env) {
			if (!summary) return;
			if (env === 'custom') {
				summary.textContent = 'Set the control-center and checkout origins below.';
			} else if (urls[env]) {
				summary.innerHTML =
					'Control center: <code>' + urls[env].control + '</code>' +
					' · Checkout origin: <code>' + urls[env].checkout + '</code>';
			}
		}

		function updateBranding() {
			if (!brandingRow || !brandingLink) return;
			const cc   = trimSlash(ccInput ? ccInput.value.trim() : '');
			const proj = projInput ? projInput.value.trim() : '';
			const prof = profInput ? profInput.value.trim() : '';
			const ready = !!(cc && proj && prof);
			if (ready) {
				brandingLink.href = cc +
					'/projects/' + encodeURIComponent(proj) +
					'/shops/' + encodeURIComponent(prof) +
					'?tab=checkout';
			} else {
				brandingLink.removeAttribute('href');
			}
			brandingRow.hidden = !ready;
			if (brandingSection) {
				brandingSection.hidden = !ready;
			}
		}

		function applyEnv(env) {
			const isCustom = env === 'custom';
			customRows.forEach((row) => { row.hidden = !isCustom; });

			if (isCustom) {
				if (ccInput) ccInput.value = savedCustom.control;
				if (coInput) coInput.value = savedCustom.checkout;
			} else if (urls[env]) {
				if (ccInput) ccInput.value = urls[env].control;
				if (coInput) coInput.value = urls[env].checkout;
			}

			updateSummary(env);
			updateBranding();
		}

		[ccInput, coInput].forEach((inp) => {
			if (!inp) return;
			inp.addEventListener('input', () => {
				if (select.value === 'custom') {
					savedCustom.control  = ccInput ? ccInput.value : '';
					savedCustom.checkout = coInput ? coInput.value : '';
				}
				updateBranding();
			});
		});
		[projInput, profInput].forEach((inp) => {
			if (inp) inp.addEventListener('input', updateBranding);
		});

		select.addEventListener('change', () => applyEnv(select.value));
		applyEnv(select.value);
	}

	function initImportToggle() {
		document.querySelectorAll('.wp-delopay-import-toggle').forEach((toggle) => {
			const panelId = toggle.getAttribute('aria-controls');
			const panel   = panelId ? document.getElementById(panelId) : null;
			if (!panel) return;

			toggle.addEventListener('click', (e) => {
				e.preventDefault();
				const isOpen = !panel.hidden;
				panel.hidden = isOpen;
				toggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
				if (!isOpen) {
					const file = panel.querySelector('input[type="file"]');
					if (file) file.focus();
				}
			});
		});
	}

	function initDeleteConfirms() {
		document.querySelectorAll('.wp-delopay-delete-product').forEach((link) => {
			link.addEventListener('click', (e) => {
				if (!window.confirm(I.confirmDelete || 'Delete this product?')) {
					e.preventDefault();
				}
			});
		});
	}

	function initConnectControls() {
		const statusEl = document.querySelector('[data-delopay-connect-msg]');

		function setStatus(msg, kind) {
			if (!statusEl) return;
			statusEl.textContent = msg || '';
			statusEl.classList.remove('is-error', 'is-info', 'is-ok');
			if (kind) statusEl.classList.add('is-' + kind);
		}

		const connectButton = document.querySelector('[data-delopay-connect-button]');
		if (connectButton) {
			initConnectButton(connectButton, setStatus);
		}

		const disconnectButton = document.querySelector('[data-delopay-disconnect-button]');
		if (disconnectButton) {
			initDisconnectButton(disconnectButton, setStatus);
		}
	}

	function initConnectButton(connectButton, setStatus) {
		let popup = null;
		let watcher = null;
		let messageHandler = null;
		let pendingState = '';

		function teardown() {
			if (watcher) { clearInterval(watcher); watcher = null; }
			if (messageHandler) {
				window.removeEventListener('message', messageHandler);
				messageHandler = null;
			}
			popup = null;
		}

		function cancelOnServer() {
			if (!pendingState) return;
			restCall('connect/cancel', {
				method: 'POST',
				body: JSON.stringify({ state: pendingState }),
			}).catch(() => { /* best-effort */ });
			pendingState = '';
		}

		function openPopup(url) {
			const left = Math.round((window.screen.width  - POPUP_WIDTH)  / 2);
			const top  = Math.round((window.screen.height - POPUP_HEIGHT) / 2);
			return window.open(
				url,
				'wp-delopay-connect',
				'popup=yes,width=' + POPUP_WIDTH + ',height=' + POPUP_HEIGHT + ',left=' + left + ',top=' + top
			);
		}

		connectButton.addEventListener('click', () => {
			connectButton.disabled = true;
			setStatus(I.connectOpening || 'Opening…', 'info');

			restCall('connect/start', {
				method: 'POST',
				body: JSON.stringify({
					environment: connectButton.dataset.delopayEnvironment || 'production',
				}),
			})
				.then((res) => {
					if (!res.ok) {
						throw new Error((res.body && (res.body.message || res.body.error)) || 'start failed');
					}
					const expectedOrigin = res.body.site_origin || window.location.origin;
					pendingState = res.body.state || '';

					popup = openPopup(res.body.redirect_url);
					if (!popup) {
						connectButton.disabled = false;
						setStatus(I.connectPopupBlock || 'Popup blocked.', 'error');
						cancelOnServer();
						return;
					}

					setStatus(I.connectWaiting || 'Waiting…', 'info');

					messageHandler = (event) => {
						if (event.origin !== expectedOrigin) return;
						const data = event.data;
						if (!data || data.source !== 'wp-delopay-connect') return;

						teardown();
						connectButton.disabled = false;

						if (data.ok) {
							pendingState = '';
							setStatus(I.connectOk || 'Connected.', 'ok');
							setTimeout(() => window.location.reload(), RELOAD_DELAY_MS);
						} else {
							setStatus((I.connectError || 'Connection failed: ') + (data.error || ''), 'error');
						}
					};
					window.addEventListener('message', messageHandler);

					watcher = setInterval(() => {
						if (popup && popup.closed) {
							teardown();
							connectButton.disabled = false;
							if (pendingState) {
								cancelOnServer();
								setStatus(I.connectCancelled || 'Cancelled.', 'info');
							}
						}
					}, POPUP_WATCH_MS);
				})
				.catch((err) => {
					connectButton.disabled = false;
					setStatus((I.connectFailed || 'Could not start: ') + err.message, 'error');
				});
		});
	}

	function initDisconnectButton(disconnectButton, setStatus) {
		disconnectButton.addEventListener('click', () => {
			if (!window.confirm(I.confirmDisconnect || 'Disconnect this site?')) {
				return;
			}
			disconnectButton.disabled = true;
			setStatus(I.disconnecting || 'Disconnecting…', 'info');

			restCall('connect/disconnect', { method: 'POST' })
				.then((res) => {
					if (!res.ok) {
						throw new Error((res.body && (res.body.message || res.body.error)) || 'disconnect failed');
					}
					window.location.reload();
				})
				.catch((err) => {
					disconnectButton.disabled = false;
					setStatus((I.disconnectFailed || 'Could not disconnect: ') + err.message, 'error');
				});
		});
	}

	function initRefundForms() {
		document.querySelectorAll('.wp-delopay-refund-form').forEach(initRefundForm);
	}

	function initRefundForm(form) {
		form.addEventListener('submit', (e) => {
			e.preventDefault();
			if (!window.confirm(I.confirmRefund || 'Refund this order?')) return;

			const orderId    = form.dataset.orderId;
			const amountInp  = form.querySelector('[name="amount"]');
			const reasonInp  = form.querySelector('[name="reason"]');
			const statusEl   = form.querySelector('.wp-delopay-refund-status');
			const submitBtn  = form.querySelector('button[type="submit"]');

			const amountMinor = Math.round(parseFloat(amountInp.value) * 100);
			if (!Number.isFinite(amountMinor) || amountMinor <= 0) {
				statusEl.textContent = 'Invalid amount.';
				return;
			}

			submitBtn.disabled = true;
			statusEl.textContent = I.refunding || 'Refunding…';

			restCall('admin/refund', {
				method: 'POST',
				body: JSON.stringify({
					order_id:     orderId,
					amount_minor: amountMinor,
					reason:       reasonInp ? reasonInp.value : 'requested_by_customer',
				}),
			})
				.then((res) => {
					if (!res.ok) {
						throw new Error((res.body && res.body.error) || 'Refund failed.');
					}
					statusEl.textContent = I.refundOk || 'Refund submitted.';
					setTimeout(() => window.location.reload(), 800);
				})
				.catch((err) => {
					statusEl.textContent = (I.refundFail || 'Refund failed: ') + err.message;
					submitBtn.disabled = false;
				});
		});
	}
})();
