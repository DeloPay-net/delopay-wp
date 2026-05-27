/* global WPDelopay */
(function () {
	'use strict';

	if (typeof WPDelopay === 'undefined') {
		return;
	}

	const I = WPDelopay.i18n || {};
	const CART_KEY = 'wp_delopay_cart_v1';
	const CART_EVENT = 'wp-delopay-cart-changed';
	const POLL_INTERVAL_MS = 2500;
	const ADDED_FEEDBACK_MS = 1100;

	const SUCCESS_STATUSES = ['succeeded', 'success', 'partially_captured'];
	const FAILURE_STATUSES = ['failed', 'cancelled', 'expired'];

	const Cart = {
		read() {
			try {
				const raw = window.localStorage.getItem(CART_KEY);
				if (!raw) return { items: {} };
				const parsed = JSON.parse(raw);
				return parsed && typeof parsed === 'object' && parsed.items ? parsed : { items: {} };
			} catch (_e) {
				return { items: {} };
			}
		},
		write(cart, opts) {
			try { window.localStorage.setItem(CART_KEY, JSON.stringify(cart)); }
			catch (_e) { /* quota / private mode — best-effort */ }
			if (!opts || !opts.silent) {
				document.dispatchEvent(new CustomEvent(CART_EVENT, { detail: cart }));
			}
		},
		count() {
			return Object.values(Cart.read().items)
				.reduce((n, q) => n + (parseInt(q, 10) || 0), 0);
		},
		add(id, qty) {
			const key = String(id);
			const amount = Math.max(1, parseInt(qty, 10) || 1);
			const cart = Cart.read();
			cart.items[key] = (cart.items[key] || 0) + amount;
			Cart.write(cart);
		},
		setQty(id, qty, opts) {
			const key = String(id);
			const amount = parseInt(qty, 10) || 0;
			const cart = Cart.read();
			if (amount <= 0) {
				delete cart.items[key];
			} else {
				cart.items[key] = amount;
			}
			Cart.write(cart, opts);
		},
		remove(id, opts) { Cart.setQty(id, 0, opts); },
		clear() { Cart.write({ items: {} }); },
	};
	window.WPDelopayCart = Cart;

	function refreshBadges() {
		const count = Cart.count();
		document.querySelectorAll('[data-delopay-cart-count]').forEach((el) => {
			el.textContent = count > 0 ? String(count) : '';
			el.toggleAttribute('hidden', count <= 0);
			el.classList.toggle('is-empty', count <= 0);
		});
	}

	function restUrl(path, params) {
		const base = WPDelopay.restUrl || '';
		const url  = new URL(base, window.location.href);
		const restRoute = url.searchParams.get('rest_route');
		if (restRoute !== null) {
			url.searchParams.set('rest_route', restRoute + (path || ''));
		} else {
			url.pathname = url.pathname.replace(/\/?$/, '/') + (path || '').replace(/^\/+/, '');
		}
		if (params) {
			Object.keys(params).forEach((k) => {
				if (params[k] !== undefined && params[k] !== null) {
					url.searchParams.set(k, String(params[k]));
				}
			});
		}
		return url.toString();
	}

	function fetchJson(url, options) {
		return fetch(url, Object.assign({ credentials: 'same-origin' }, options || {}))
			.then((r) => r.json().then((body) => ({ ok: r.ok, status: r.status, body })));
	}

	function fetchProductsByIds(ids) {
		if (!ids.length) return Promise.resolve([]);
		return fetchJson(restUrl('products', { ids: ids.join(',') }))
			.then((res) => Array.isArray(res.body) ? res.body : []);
	}

	function indexById(items) {
		const out = {};
		items.forEach((p) => { out[String(p.id)] = p; });
		return out;
	}

	function formatMoney(minor, currency) {
		const n = (minor / 100).toFixed(2);
		return n + (currency ? ' ' + currency : '');
	}

	document.addEventListener('DOMContentLoaded', () => {
		refreshBadges();
		document.addEventListener(CART_EVENT, refreshBadges);

		document.querySelectorAll('.wp-delopay-add-to-cart').forEach(initAddToCart);
		document.querySelectorAll('.wp-delopay-cart').forEach(initCartPage);
		document.querySelectorAll('.wp-delopay-checkout').forEach(initCheckout);
		document.querySelectorAll('.wp-delopay-complete').forEach(initComplete);
	});

	function initAddToCart(btn) {
		btn.addEventListener('click', () => {
			const id = btn.dataset.productId;
			if (!id) return;
			Cart.add(id, 1);
			const original = btn.textContent;
			btn.textContent = I.added || 'Added ✓';
			btn.classList.add('is-added');
			setTimeout(() => {
				btn.textContent = original;
				btn.classList.remove('is-added');
			}, ADDED_FEEDBACK_MS);
		});
	}

	function initCartPage(root) {
		const els = {
			loading:          root.querySelector('.wp-delopay-cart-loading'),
			empty:            root.querySelector('.wp-delopay-cart-empty'),
			content:          root.querySelector('.wp-delopay-cart-content'),
			items:            root.querySelector('.wp-delopay-cart-items'),
			subtotal:         root.querySelector('[data-field="subtotal"]'),
			tpl:              root.querySelector('.wp-delopay-cart-row-template'),
			checkout:         root.querySelector('.wp-delopay-cart-checkout'),
			checkoutExternal: root.querySelector('.wp-delopay-cart-checkout-external'),
			error:            root.querySelector('.wp-delopay-cart-error'),
		};
		const checkoutUrl = root.dataset.checkoutUrl;

		function showEmpty() {
			els.loading.hidden = true;
			els.empty.hidden = false;
			els.content.hidden = true;
		}

		function showLoading() {
			els.loading.hidden = false;
			els.empty.hidden = true;
			els.content.hidden = true;
		}

		function showContent() {
			els.loading.hidden = true;
			els.empty.hidden = true;
			els.content.hidden = false;
		}

		function showFetchError() {
			els.empty.hidden = true;
			els.content.hidden = true;
			els.loading.hidden = false;
			els.loading.textContent = I.cartFetchFailed || 'Could not load your cart.';
		}

		function render() {
			const cart = Cart.read();
			const ids  = Object.keys(cart.items);

			if (!ids.length) {
				showEmpty();
				return;
			}

			showLoading();

			fetchProductsByIds(ids)
				.then((products) => {
					const byId = indexById(products);
					const rows = ids
						.map((id) => byId[id] ? { product: byId[id], qty: cart.items[id] } : null)
						.filter(Boolean);

					if (!rows.length) {
						ids.forEach((id) => { if (!byId[id]) Cart.remove(id, { silent: true }); });
						showEmpty();
						return;
					}

					renderRows(rows);
					showContent();

					ids.forEach((id) => { if (!byId[id]) Cart.remove(id, { silent: true }); });
				})
				.catch(showFetchError);
		}

		function renderRows(rows) {
			els.items.innerHTML = '';
			let subtotal = 0;
			let currency = '';

			rows.forEach(({ product, qty }) => {
				const lineTotal = product.price_minor * qty;
				subtotal += lineTotal;
				currency = currency || product.currency;
				els.items.appendChild(buildRow(els.tpl, product, qty, lineTotal));
			});

			els.subtotal.textContent = formatMoney(subtotal, currency);
			if (els.checkout) els.checkout.disabled = false;
			if (els.checkoutExternal) els.checkoutExternal.disabled = false;
		}

		function buildRow(tpl, product, qty, lineTotal) {
			const node = tpl.content.firstElementChild.cloneNode(true);
			const id = String(product.id);
			node.dataset.productId = id;

			if (product.image_url) {
				const img = document.createElement('img');
				img.src = product.image_url;
				img.alt = '';
				img.loading = 'lazy';
				node.querySelector('.wp-delopay-cart-thumb').appendChild(img);
			}
			node.querySelector('[data-field="name"]').textContent = product.name;
			node.querySelector('[data-field="unit_price"]').textContent =
				formatMoney(product.price_minor, product.currency);
			node.querySelector('[data-field="quantity"]').textContent = String(qty);
			node.querySelector('[data-field="line_total"]').textContent =
				formatMoney(lineTotal, product.currency);

			node.querySelector('.wp-delopay-cart-inc').addEventListener('click', () => Cart.setQty(id, qty + 1));
			node.querySelector('.wp-delopay-cart-dec').addEventListener('click', () => Cart.setQty(id, qty - 1));
			node.querySelector('.wp-delopay-cart-remove').addEventListener('click', () => Cart.remove(id));
			return node;
		}

		document.addEventListener(CART_EVENT, render);
		render();

		if (els.checkout) {
			els.checkout.addEventListener('click', () => {
				if (checkoutUrl) {
					window.location.href = checkoutUrl;
					return;
				}
				els.error.hidden = false;
				els.error.textContent = I.noCheckoutPage || 'No checkout page configured.';
			});
		}

		if (els.checkoutExternal) {
			els.checkoutExternal.addEventListener('click', () => {
				const cart  = Cart.read();
				const items = Object.keys(cart.items).map((id) => ({
					product_id: id,
					quantity:   cart.items[id],
				}));
				if (!items.length) return;

				els.checkoutExternal.disabled = true;
				if (els.checkout) els.checkout.disabled = true;
				els.error.hidden              = true;

				fetchJson(restUrl('orders'), {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce':   WPDelopay.nonce,
					},
					body: JSON.stringify({
						items,
						return_url: WPDelopay.completeUrl || undefined,
					}),
				})
					.then((res) => {
						if (!res.ok) {
							throw new Error((res.body && res.body.error) || I.failed || 'Could not start payment.');
						}
						const url = res.body.checkout_url;
						if (!url) {
							throw new Error('Hosted checkout URL missing — set Checkout origin in DeloPay Settings.');
						}
						window.location.href = url;
					})
					.catch((err) => {
						els.checkoutExternal.disabled = false;
						if (els.checkout) els.checkout.disabled = false;
						els.error.hidden              = false;
						els.error.textContent         = err.message || I.failed || 'Could not start payment.';
					});
			});
		}
	}

	function initCheckout(root) {
		const statusEl  = root.querySelector('.wp-delopay-checkout-status');
		const wrapEl    = root.querySelector('.wp-delopay-checkout-iframe-wrap');
		const iframeEl  = root.querySelector('.wp-delopay-checkout-iframe');
		const errorEl   = root.querySelector('.wp-delopay-checkout-error');
		const summaryEl = root.querySelector('[data-cart-summary]');

		const productId = root.dataset.productId;
		const quantity  = parseInt(root.dataset.quantity, 10) || 1;

		const order = buildOrderRequest(productId, quantity);
		if (order.error) {
			showError(errorEl, statusEl, order.error);
			return;
		}
		const { body, cartItems } = order;
		body.return_url = WPDelopay.completeUrl || undefined;

		if (summaryEl && cartItems.length > 0) {
			renderCartSummary(summaryEl, cartItems);
		}

		fetchJson(restUrl('orders'), {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': WPDelopay.nonce,
			},
			body: JSON.stringify(body),
		})
			.then((res) => {
				if (!res.ok) {
					throw new Error((res.body && res.body.error) || I.failed || 'Could not start payment.');
				}
				const url = res.body.checkout_url;
				if (!url) {
					throw new Error('Checkout URL missing — set Checkout origin in DeloPay Settings.');
				}
				iframeEl.addEventListener('load', () => wrapEl.classList.add('is-ready'));
				iframeEl.src = url;
				wrapEl.hidden = false;
				if (statusEl) statusEl.hidden = true;
			})
			.catch((err) => {
				showError(errorEl, statusEl, err.message || (I.failed || 'Could not start payment.'));
			});
	}

	function buildOrderRequest(productId, quantity) {
		if (productId) {
			return { body: { product_id: productId, quantity }, cartItems: [] };
		}
		const cart = Cart.read();
		const items = Object.keys(cart.items).map((id) => ({
			product_id: id,
			quantity: cart.items[id],
		}));
		if (!items.length) {
			return { error: I.cartEmpty || 'Your cart is empty.' };
		}
		return { body: { items }, cartItems: items };
	}

	function renderCartSummary(summaryEl, items) {
		summaryEl.hidden = false;
		const ids = items.map((i) => i.product_id);
		fetchProductsByIds(ids).then((products) => {
			if (!products.length) return;
			const ul      = summaryEl.querySelector('.wp-delopay-checkout-lines');
			const totalEl = summaryEl.querySelector('[data-field="total"]');
			const byId    = indexById(products);

			let total = 0;
			let currency = '';
			items.forEach((it) => {
				const p = byId[String(it.product_id)];
				if (!p) return;
				const lineTotal = p.price_minor * it.quantity;
				total += lineTotal;
				currency = currency || p.currency;
				const li = document.createElement('li');
				li.textContent = it.quantity + ' × ' + p.name + ' — ' + formatMoney(lineTotal, p.currency);
				ul.appendChild(li);
			});
			totalEl.textContent = (I.total || 'Total') + ': ' + formatMoney(total, currency);
		});
	}

	function showError(errorEl, statusEl, msg) {
		if (statusEl) statusEl.hidden = true;
		if (errorEl) {
			errorEl.hidden = false;
			errorEl.textContent = msg;
		}
	}

	function initComplete(root) {
		const orderId = root.dataset.orderId;
		if (!orderId) return;

		if (window.top && window.top !== window.self) {
			try {
				window.top.location.href = window.location.href;
				return;
			} catch (_e) { /* cross-origin — render in place */ }
		}

		const els = {
			statusEl:  root.querySelector('.wp-delopay-complete-status'),
			detailsEl: root.querySelector('.wp-delopay-complete-details'),
			amountEl:  root.querySelector('[data-field="amount"]'),
			messageEl: root.querySelector('[data-field="message"]'),
			iconEl:    root.querySelector('.wp-delopay-complete-icon'),
		};

		function load() {
			return fetchJson(restUrl('orders/' + encodeURIComponent(orderId)), {
				headers: { 'X-WP-Nonce': WPDelopay.nonce },
			}).then((res) => {
				const data = res.body;
				if (data && data.error) {
					els.statusEl.textContent = data.error;
					els.statusEl.classList.add('is-failure');
					return null;
				}
				renderComplete(data, els);
				if (isSuccess((data.status || '').toLowerCase())) {
					Cart.clear();
				}
				return data.status;
			});
		}

		load().then((status) => {
			if (status && !isTerminal(status)) {
				setTimeout(load, POLL_INTERVAL_MS);
			}
		});
	}

	function renderComplete(data, els) {
		const statusLower = (data.status || '').toLowerCase();

		let label = I.pending || 'Waiting for payment confirmation…';
		let kind  = 'is-pending';
		if (isSuccess(statusLower)) {
			label = I.success || 'Payment received — thank you.';
			kind  = 'is-success';
		} else if (isFailure(statusLower)) {
			label = I.failure || 'Payment failed.';
			kind  = 'is-failure';
		}

		setStateClass(els.statusEl, kind);
		els.statusEl.textContent = label;
		setStateClass(els.iconEl, kind);

		if (els.amountEl) {
			els.amountEl.textContent = (data.amount_minor / 100).toFixed(2) + ' ' + data.currency;
			els.amountEl.hidden = false;
		}
		if (els.messageEl) {
			if (kind === 'is-pending') {
				els.messageEl.textContent = I.willUpdate || "We'll update this page automatically when the payment confirms.";
				els.messageEl.hidden = false;
			} else {
				els.messageEl.hidden = true;
			}
		}

		if (els.detailsEl) {
			els.detailsEl.hidden = false;
			fillField(els.detailsEl, 'order_id',   data.order_id);
			fillField(els.detailsEl, 'payment_id', data.payment_id);
			fillField(els.detailsEl, 'status',     data.status);
		}
	}

	function setStateClass(el, kind) {
		if (!el) return;
		el.classList.remove('is-success', 'is-failure', 'is-pending');
		el.classList.add(kind);
	}

	function fillField(detailsEl, field, value) {
		const el = detailsEl.querySelector('[data-field="' + field + '"]');
		if (el) el.textContent = value;
	}

	function isSuccess(s) { return SUCCESS_STATUSES.indexOf(s) !== -1; }
	function isFailure(s) { return FAILURE_STATUSES.indexOf(s) !== -1; }
	function isTerminal(s) { return isSuccess((s || '').toLowerCase()) || isFailure((s || '').toLowerCase()); }
})();
