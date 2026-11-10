/* ══════════════════════════════════════════
   LectroHub — Customer Shared JS
   Place in: /LectroHub/customer/js/main.js
   Include in head.php:
     <script src="/LectroHub/customer/js/main.js"></script>
══════════════════════════════════════════ */


/* ── Toast ── */
function showToast(msg, icon = '✓') {
    const t = document.getElementById('toast');
    if (!t) return;
    document.getElementById('toastMsg').textContent  = msg;
    document.getElementById('toastIcon').textContent = icon;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}


/* ── Mobile nav drawer ── */
function toggleDrawer() {
    document.getElementById('navDrawer')?.classList.toggle('open');
}

/* ── User account dropdown ── */
function toggleUserDrop() {
    const menu = document.getElementById('userDropMenu');
    const btn  = document.querySelector('#userDropdown .nav-user-btn');
    if (!menu) return;
    const isOpen = menu.style.display === 'block';
    menu.style.display = isOpen ? 'none' : 'block';
    btn?.setAttribute('aria-expanded', !isOpen);
}

document.addEventListener('click', e => {
    // Close user dropdown when clicking outside
    const dropdown = document.getElementById('userDropdown');
    const menu     = document.getElementById('userDropMenu');
    if (menu && dropdown && !dropdown.contains(e.target)) {
        menu.style.display = 'none';
        dropdown.querySelector('.nav-user-btn')?.setAttribute('aria-expanded', 'false');
    }
    // Close nav drawer when clicking outside
    const drawer = document.getElementById('navDrawer');
    if (drawer && !e.target.closest('nav') && !e.target.closest('#navDrawer')) {
        drawer.classList.remove('open');
    }
});


/* ── Nav search (shared across all pages) ── */
document.addEventListener('DOMContentLoaded', () => {
    ['navSearch', 'navSearchMobile'].forEach(id => {
        document.getElementById(id)?.addEventListener('keydown', e => {
            if (e.key === 'Enter' && e.target.value.trim()) {
                window.location = `products.php?q=${encodeURIComponent(e.target.value)}`;
            }
        });
    });
});


/* ── Update nav cart badge ── */
function updateCartBadge(count) {
    const badge = document.querySelector('.cart-badge');
    if (count > 0) {
        if (badge) {
            badge.textContent = count;
        } else {
            const btn = document.querySelector('.nav-btn-cart');
            if (btn) {
                const span = document.createElement('span');
                span.className   = 'cart-badge';
                span.textContent = count;
                btn.appendChild(span);
            }
        }
    } else if (badge) {
        badge.remove();
    }
}


/* ── Update nav wishlist badge ── */
function updateWishlistBadge(count) {
    const badge = document.querySelector('.wishlist-badge');
    if (count > 0) {
        if (badge) {
            badge.textContent = count;
        } else {
            const btn = document.querySelector('.nav-wishlist');
            if (btn) {
                const span = document.createElement('span');
                span.className   = 'wishlist-badge';
                span.textContent = count;
                btn.appendChild(span);
            }
        }
    } else if (badge) {
        badge.remove();
    }
}


/* ── Add to cart (product cards on index/products/deals) ── */
function addToCart(id, name) {
    fetch('cart_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=add&product_id=${id}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(`${name} added to cart!`, '🛒');
            updateCartBadge(data.cart_count);
        } else {
            showToast(data.message || 'Could not add to cart.', '✗');
        }
    })
    .catch(() => showToast('Something went wrong.', '✗'));
}


/* ── Toggle wishlist (product cards on index/products) ── */
function toggleWishlist(id) {
    fetch('wishlist_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=toggle&product_id=${id}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const added = data.action === 'added';
            showToast(added ? 'Saved to wishlist' : 'Removed from wishlist', '♡');
            updateWishlistBadge(data.wishlist_count);

            /* FIX: previously looked up the heart via a substring match
               on the onclick attribute:
                 `.product-wishlist[onclick*="toggleWishlist(${id})"]`
               That's a bug — `onclick*="toggleWishlist(1)"` also matches
               toggleWishlist(10), toggleWishlist(12), toggleWishlist(100),
               etc., since "toggleWishlist(1" is a substring of all of
               them. querySelector returns the FIRST match in the DOM,
               so clicking one product's heart could silently flip a
               completely different product's heart instead (and leave
               the one you actually clicked showing the wrong state).

               Every product card that renders a heart already gives it
               a unique id="wl<id>" (see products.php, product.php).
               getElementById is an exact match, so this can no longer
               cross-wire two different product ids. */
            const heart = document.getElementById('wl' + id);
            if (heart) {
                heart.classList.toggle('active', added);
                heart.textContent = added ? '♥' : '♡';
            }
        } else {
            showToast(data.message || 'Could not update wishlist.', '✗');
        }
    })
    .catch(() => showToast('Could not update wishlist.', '✗'));
}


/* ════════════════════════════════════════
   CART PAGE  (cart.php)
════════════════════════════════════════ */

/* ── Update item quantity ── */
function updateQty(id, delta) {
    const valEl  = document.getElementById('qtyVal' + id);
    if (!valEl) return;
    const newQty = parseInt(valEl.textContent) + delta;
    if (newQty < 1) { removeItem(id); return; }

    fetch('cart_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=update&product_id=${id}&qty=${newQty}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) location.reload();
    });
}

/* ── Remove item ── */
function removeItem(id) {
    fetch('cart_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=remove&product_id=${id}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const el = document.getElementById('cartItem' + id);
            if (el) {
                el.style.transition = 'opacity .3s, transform .3s';
                el.style.opacity    = '0';
                el.style.transform  = 'translateX(20px)';
                setTimeout(() => {
                    el.remove();
                    updateCartBadge(data.cart_count);
                    location.reload();
                }, 300);
            }
        }
    });
}

/* ── Clear cart ── */
function clearCart() {
    if (!confirm('Clear your entire cart?')) return;
    fetch('cart_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=clear'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) location.reload();
    });
}

/* ── Apply coupon (cart page) ── */
function applyCoupon() {
    const code  = document.getElementById('couponInput')?.value.trim();
    const msgEl = document.getElementById('couponMsg');
    if (!code) {
        msgEl.textContent = 'Please enter a coupon code.';
        msgEl.className   = 'coupon-msg error';
        return;
    }

    // subtotal is now computed server-side from the session cart —
    // no need to send it from here.
    fetch('coupon_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=apply&code=${encodeURIComponent(code)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            msgEl.textContent = `✓ ${data.message}`;
            msgEl.className   = 'coupon-msg success';
            if (data.total !== undefined) {
                const totalEl = document.getElementById('summaryTotal');
                if (totalEl) totalEl.textContent = '€' + parseFloat(data.total).toFixed(2);
            }
        } else {
            msgEl.textContent = data.message || 'Invalid coupon.';
            msgEl.className   = 'coupon-msg error';
        }
    })
    .catch(() => {
        msgEl.textContent = 'Could not apply coupon.';
        msgEl.className   = 'coupon-msg error';
    });
}


/* ════════════════════════════════════════
   WISHLIST PAGE  (wishlist.php)
════════════════════════════════════════ */

/* ── Add single item to cart from wishlist ── */
function addToCartFromWishlist(id, btn) {
    btn.disabled  = true;
    btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg> Adding…';

    fetch('cart_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=add&product_id=${id}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.classList.add('in-cart');
            btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg> In Cart';
            updateCartBadge(data.cart_count);
            showToast('Added to cart!', '🛒');
        } else {
            btn.disabled  = false;
            btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg> Add to Cart';
            showToast(data.message || 'Could not add to cart.', '✗');
        }
    })
    .catch(() => { btn.disabled = false; showToast('Something went wrong.', '✗'); });
}

/* ── Add all wishlist items to cart ── */
function addAllToCart() {
    const cards = document.querySelectorAll('.wl-card');
    let added = 0;
    const promises = [];

    cards.forEach(card => {
        const id  = card.id.replace('wlCard', '');
        const btn = document.getElementById('cartBtn' + id);
        if (!btn || btn.disabled) return;

        promises.push(
            fetch('cart_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=add&product_id=${id}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    btn.classList.add('in-cart');
                    btn.disabled  = true;
                    btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg> In Cart';
                    updateCartBadge(data.cart_count);
                    added++;
                }
            })
        );
    });

    Promise.allSettled(promises).then(() => {
        showToast(
            added > 0 ? `${added} item${added !== 1 ? 's' : ''} added to cart!` : 'All items already in cart.',
            added > 0 ? '🛒' : 'ℹ️'
        );
    });
}

/* ── Remove single item from wishlist page ── */
function removeFromWishlist(id) {
    fetch('wishlist_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=remove&product_id=${id}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const card = document.getElementById('wlCard' + id);
            if (card) {
                card.style.transition = 'opacity .3s, transform .3s';
                card.style.opacity    = '0';
                card.style.transform  = 'scale(0.92)';
                setTimeout(() => {
                    card.remove();
                    updateWishlistBadge(data.wishlist_count);
                    if (data.wishlist_count === 0) {
                        location.reload();
                    } else {
                        const badge = document.querySelector('.wl-count-badge');
                        if (badge) badge.textContent = `${data.wishlist_count} item${data.wishlist_count !== 1 ? 's' : ''}`;
                    }
                }, 300);
            }
            showToast('Removed from wishlist.', '♡');
        }
    })
    .catch(() => showToast('Something went wrong.', '✗'));
}

/* ── Clear entire wishlist ── */
function clearWishlist() {
    if (!confirm('Remove all items from your wishlist?')) return;
    fetch('wishlist_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=clear'
    })
    .then(r => r.json())
    .then(data => { if (data.success) location.reload(); })
    .catch(() => showToast('Something went wrong.', '✗'));
}

const COMPARE_MAX = 4;

/* ── Bind compare checkboxes after DOM ready ──
   Deliberately NOT using inline onchange="" in the HTML: on reload,
   browsers can restore checkbox .checked state and replay a synthetic
   'change' event during HTML parsing — before this deferred script has
   even run. An inline attribute would try to call toggleCompare() at
   that moment and throw "toggleCompare is not defined". Binding here,
   after DOMContentLoaded, guarantees the function already exists by
   the time any listener can possibly fire. */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.wl-compare-input').forEach(cb => {
        cb.addEventListener('change', () => {
            toggleCompare(parseInt(cb.dataset.productId, 10), cb);
        });
    });
});

/* ── Toggle compare selection (wishlist page checkboxes) ── */
function toggleCompare(id, checkbox) {
    const wasChecked = checkbox.checked;
    const label = document.getElementById('compareLabel' + id);
    checkbox.disabled = true; // prevent double-fires while the request is in flight

    fetch('compare_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=toggle&product_id=${id}`
    })
    .then(r => r.json())
    .then(data => {
        checkbox.disabled = false;

        if (!data.success) {
            // Revert the checkbox — usually hit the 4-item cap
            checkbox.checked = !wasChecked;
            showToast(data.message || 'Could not update comparison.', '✗');
            return;
        }

        setCompareLabelState(label, data.in_compare);
        updateCompareUI(data.compare_count, data.compare_ids || []);
        showToast(data.in_compare ? 'Added to comparison' : 'Removed from comparison', '⚖️');
    })
    .catch(() => {
        checkbox.disabled = false;
        checkbox.checked = !wasChecked;
        showToast('Something went wrong.', '✗');
    });
}

/* ── Flip a single compare label's visual state (icon + text) ── */
function setCompareLabelState(label, isComparing) {
    if (!label) return;
    label.classList.toggle('is-comparing', isComparing);
    const text = label.querySelector('.wl-compare-label-text');
    if (text) text.textContent = isComparing ? 'Comparing' : 'Compare';
}

/* ── Sync compare bar + checkbox disabled states after a toggle ── */
function updateCompareUI(count, ids) {
    const countText = document.getElementById('wlCompareCountText');
    if (countText) countText.textContent = count;

    const goBtn = document.getElementById('wlCompareGoBtn');
    if (goBtn) {
        if (count >= 2) {
            goBtn.style.pointerEvents = '';
            goBtn.style.opacity = '';
        } else {
            goBtn.style.pointerEvents = 'none';
            goBtn.style.opacity = '.5';
        }
    }

    // At the cap, disable every unchecked box; otherwise re-enable them all
    document.querySelectorAll('.wl-compare-check .wl-compare-input').forEach(cb => {
        if (!cb.checked) cb.disabled = count >= COMPARE_MAX;
    });
}


/* ════════════════════════════════════════
   ORDERS PAGE  (orders.php)
════════════════════════════════════════ */

/* ── Toggle order detail panel ── */
function toggleOrder(id) {
    const detail = document.getElementById('orderDetail' + id);
    const btn    = document.getElementById('toggleBtn' + id);
    if (!detail) return;
    const isOpen = detail.style.display !== 'none';

    detail.style.display = isOpen ? 'none' : 'block';
    btn?.setAttribute('aria-expanded', !isOpen);

    if (!isOpen) {
        detail.style.opacity   = '0';
        detail.style.transform = 'translateY(-6px)';
        requestAnimationFrame(() => {
            detail.style.transition = 'opacity .25s ease, transform .25s ease';
            detail.style.opacity    = '1';
            detail.style.transform  = 'translateY(0)';
        });
    }
}

/* ── Reorder ── */
function reorderItems(orderId) {
    showToast('Adding items to cart…', '🛒');
    fetch('cart_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=reorder&order_id=${orderId}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Items added to cart!', '✓');
            updateCartBadge(data.cart_count);
            setTimeout(() => window.location = 'cart.php', 900);
        } else {
            showToast(data.message || 'Could not reorder.', '✗');
        }
    })
    .catch(() => showToast('Something went wrong.', '✗'));
}

/* ── Cancel order ── */
function cancelOrder(orderId) {
    if (!confirm('Cancel order #' + String(orderId).padStart(5, '0') + '? This cannot be undone.')) return;
    fetch('order_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=cancel&order_id=${orderId}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Order cancelled.', '✓');
            setTimeout(() => location.reload(), 900);
        } else {
            showToast(data.message || 'Could not cancel order.', '✗');
        }
    })
    .catch(() => showToast('Something went wrong.', '✗'));
}


/* ════════════════════════════════════════
   CHECKOUT PAGE  (checkout.php)
════════════════════════════════════════ */

/* ── Address selection ── */
function selectSavedAddr(id) {
    document.querySelectorAll('.addr-option').forEach(el => el.classList.remove('selected'));
    const target = id === 'new'
        ? document.getElementById('addrLabelNew')
        : document.getElementById('addrLabel' + id);
    if (target) target.classList.add('selected');

    const newFields = document.getElementById('newAddrFields');
    if (newFields) {
        const isNew = id === 'new';
        newFields.style.display = isNew ? 'block' : 'none';
        newFields.querySelectorAll('.addr-required').forEach(inp => {
            inp.required = isNew;
        });
    }
}

/* ── NOTE: Payment-method selection (selectPay) is intentionally
   NOT defined here.

   checkout.php defines its own complete selectPay(id) inline
   (handling Card / PayPal / KBZPay / WavePay, showing the right
   fields panel, and toggling `required` correctly on the wallet
   phone input for whichever panel is visible).

   main.js is loaded with `defer`, so it always runs AFTER
   checkout.php's inline <script> at the bottom of the page. A
   previous version of this file defined its own much simpler
   selectPay(id) — one that only knew about `cardFields` — and
   because it ran later, it silently overwrote checkout.php's
   real function. That made the KBZPay/WavePay phone-number
   fields (and PayPal panel) impossible to see: clicking those
   payment options just hid `cardFields` and did nothing else,
   so the wallet phone input was never shown, never required
   correctly, and the form still submitted with an empty
   wallet_phone — which the server then rejected with "phone
   number is required," even though the field was never visible.

   Do not re-add a selectPay() function to this shared file. If
   another page needs one, give it a distinct, page-specific name
   (e.g. dealsSelectPay) instead of reusing this name. ── */


/* ════════════════════════════════════════
   PRODUCTS PAGE  (products.php)
════════════════════════════════════════ */

/* ── Filter sidebar (mobile) ── */
function openSidebar() {
    document.getElementById('shopSidebar')?.classList.add('open');
    document.getElementById('filterOverlay')?.classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeSidebar() {
    document.getElementById('shopSidebar')?.classList.remove('open');
    document.getElementById('filterOverlay')?.classList.remove('show');
    document.body.style.overflow = '';
}

/* ── Price slider ── */
function updatePriceDisplay() {
    const sMin = document.getElementById('sliderMin');
    const sMax = document.getElementById('sliderMax');
    if (!sMin || !sMax) return;

    const lo = Math.min(parseFloat(sMin.value), parseFloat(sMax.value));
    const hi = Math.max(parseFloat(sMin.value), parseFloat(sMax.value));

    const display = document.getElementById('priceDisplay');
    if (display) display.textContent = '€' + Math.round(lo) + ' – €' + Math.round(hi);

    const iMin = document.getElementById('inputMin');
    const iMax = document.getElementById('inputMax');
    if (iMin) iMin.value = Math.round(lo);
    if (iMax) iMax.value = Math.round(hi);

    updatePriceTrack();
}

function syncSliders() {
    const iMin = document.getElementById('inputMin');
    const iMax = document.getElementById('inputMax');
    if (!iMin || !iMax) return;
    document.getElementById('sliderMin').value = iMin.value;
    document.getElementById('sliderMax').value = iMax.value;
    updatePriceDisplay();
}

function updatePriceTrack() {
    const sMin = document.getElementById('sliderMin');
    const sMax = document.getElementById('sliderMax');
    const fill = document.getElementById('priceTrackFill');
    if (!sMin || !sMax || !fill) return;

    const min   = parseFloat(sMin.min);
    const max   = parseFloat(sMin.max);
    const lo    = Math.min(parseFloat(sMin.value), parseFloat(sMax.value));
    const hi    = Math.max(parseFloat(sMin.value), parseFloat(sMax.value));
    const left  = ((lo - min) / (max - min)) * 100;
    const right = ((hi - min) / (max - min)) * 100;
    fill.style.left  = left + '%';
    fill.style.width = (right - left) + '%';
}

function preparePriceSubmit() {
    const hidMin = document.getElementById('hiddenPmin');
    const hidMax = document.getElementById('hiddenPmax');
    const iMin   = document.getElementById('inputMin');
    const iMax   = document.getElementById('inputMax');
    if (hidMin && iMin) hidMin.value = iMin.value;
    if (hidMax && iMax) hidMax.value = iMax.value;
}

/* ── View toggle (grid / list) ── */
function setView(mode) {
    const grid    = document.getElementById('productsGrid');
    const btnGrid = document.getElementById('btnGrid');
    const btnList = document.getElementById('btnList');
    if (!grid) return;

    grid.classList.toggle('list-view', mode === 'list');
    btnGrid?.classList.toggle('active', mode === 'grid');
    btnList?.classList.toggle('active', mode === 'list');
    btnGrid?.setAttribute('aria-pressed', mode === 'grid');
    btnList?.setAttribute('aria-pressed', mode === 'list');
    localStorage.setItem('shopView', mode);
}


/* ════════════════════════════════════════
   FAQ PAGE  (faq.php)
════════════════════════════════════════ */

function toggleFaq(btn) {
    const item   = btn.closest('.faq-item');
    const answer = item.querySelector('.faq-answer');
    const isOpen = item.classList.contains('open');

    // Close all
    document.querySelectorAll('.faq-item.open').forEach(el => {
        el.classList.remove('open');
        el.querySelector('.faq-answer').style.maxHeight = '0';
    });

    // Open clicked if it was closed
    if (!isOpen) {
        item.classList.add('open');
        answer.style.maxHeight = answer.scrollHeight + 'px';
    }
}


/* ════════════════════════════════════════
   INDEX PAGE  (index.php)
════════════════════════════════════════ */

function subscribeNewsletter(e) {
    e.preventDefault();
    showToast('Subscribed! Check your inbox.', '📬');
    document.getElementById('newsletterEmail').value = '';
}


/* ════════════════════════════════════════
   DEALS PAGE  (deals.php)
════════════════════════════════════════ */

function subscribeDeals(e) {
    e.preventDefault();
    showToast("You're in! Deal alerts coming your way.", '🔔');
    const el = document.getElementById('dealsEmail');
    if (el) el.value = '';
}

/* ════════════════════════════════════════
   PRODUCT DETAIL PAGE  (product.php)
════════════════════════════════════════ */
/* ── Quantity control ── */
let qty = 1;
let maxQty = 1;

document.addEventListener('DOMContentLoaded', () => {
    const qtyEl = document.querySelector('[data-max-qty]');
    if (qtyEl) {
        maxQty = Math.max(1, parseInt(qtyEl.dataset.maxQty || '1', 10) || 1);
        changeQty(0);
    }
});

function changeQty(delta) {
    qty = Math.max(1, Math.min(maxQty, qty + delta));
    document.getElementById('qtyVal').textContent = qty;
    document.getElementById('qtyMinus').disabled = qty <= 1;
    document.getElementById('qtyPlus').disabled  = qty >= maxQty;
}

/* ── Add to cart respecting qty ── */
function addToCartQty(productId, name) {
    fetch('cart_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=add&product_id=${productId}&qty=${qty}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(`${name} × ${qty} added to cart`, '🛒');
            if (typeof updateCartBadge === 'function') updateCartBadge(data.cart_count);
        } else {
            showToast(data.message || 'Could not add to cart', '✗');
        }
    })
    .catch(() => showToast('Something went wrong', 'error'));
}

/* ── Wishlist toggle (product detail page — has its own two hearts:
   the gallery button #wishBtn and the CTA-row button #wishBtnMain) ── */
function handleWishlist(productId) {
    fetch('wishlist_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=toggle&product_id=${productId}`
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) { showToast(data.message || 'Could not update wishlist.', '✗'); return; }
        const added = data.action === 'added';
        const glyph = added ? '♥' : '♡';

        const btn1 = document.getElementById('wishBtn');
        const btn2 = document.getElementById('wishBtnMain');

        if (btn1) { btn1.textContent = glyph; btn1.classList.toggle('active', added); }
        if (btn2) { btn2.textContent = glyph; btn2.classList.toggle('active', added); }

        updateWishlistBadge(data.wishlist_count);
        if (added) {
            showToast('Added to wishlist! Redirecting…', '♥');
            setTimeout(() => window.location = 'wishlist.php', 900);
        } else {
            showToast('Removed from wishlist', '♡');
        }
    })
    .catch(() => showToast('Something went wrong', '✗'));
}

function useCoupon(code) {
    const input = document.getElementById('couponInput');
    if (input) input.value = code;
    applyCoupon();
}