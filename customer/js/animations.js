/* ══════════════════════════════════════════
   LectroHub — Premium Motion Engine  (lh-*)
   Place in: /LectroHub/customer/js/animations.js
   Load AFTER three.js / gsap / lenis (see head.php)
   and BEFORE main.js.

   Design goal: progressive enhancement, sitewide,
   with ZERO markup changes required on cart.php,
   checkout.php, orders.php, wishlist.php, products.php,
   deals.php, faq.php, product.php — it enhances your
   EXISTING class names. Only index.php got a couple of
   new mount points for the video/3D hero.

   Everything here:
     - checks prefers-reduced-motion and bails cleanly
     - uses INLINE styles for hidden/visible states so it
       can never fight with :hover rules already in main.css
     - fails silently if a CDN library didn't load
══════════════════════════════════════════ */
(function () {
    'use strict';

    const REDUCED   = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const NO_HOVER  = window.matchMedia('(hover: none)').matches;
    const HAS_GSAP  = typeof window.gsap !== 'undefined';
    if (HAS_GSAP && window.ScrollTrigger) gsap.registerPlugin(ScrollTrigger);


    /* ══════════════════════════════════════════
       1. SMOOTH SCROLL (Lenis, optional)
    ══════════════════════════════════════════ */
    function initSmoothScroll() {
        if (REDUCED || typeof window.Lenis === 'undefined') return;
        try {
            const lenis = new Lenis({ duration: 1.1, smoothWheel: true });
            function raf(time) {
                lenis.raf(time);
                requestAnimationFrame(raf);
            }
            requestAnimationFrame(raf);
            if (HAS_GSAP && window.ScrollTrigger) {
                lenis.on('scroll', ScrollTrigger.update);
                gsap.ticker.add((time) => lenis.raf(time * 1000));
                gsap.ticker.lagSmoothing(0);
            }
        } catch (e) { /* Lenis unavailable — native scroll is fine */ }
    }


    /* ══════════════════════════════════════════
       2. NAVBAR — glass + shrink on scroll
    ══════════════════════════════════════════ */
    function initNavShrink() {
        const nav = document.querySelector('nav');
        if (!nav) return;
        const onScroll = () => nav.classList.toggle('lh-nav-scrolled', window.scrollY > 24);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }


    /* ══════════════════════════════════════════
       SHARED REVEAL HELPER
       Sets hidden state via inline style, then on
       intersection (or immediately for above-the-
       fold hero content) animates to visible and
       CLEARS all inline styles afterwards — so the
       element returns fully to its normal CSS,
       hover states and all.
    ══════════════════════════════════════════ */
    function hideEl(el, delay) {
        el.style.opacity = '0';
        el.style.transform = 'translateY(28px)';
        el.dataset.lhDelay = delay || 0;
    }

    function revealEl(el) {
        const delay = parseInt(el.dataset.lhDelay || '0', 10);
        setTimeout(() => {
            el.style.transition = 'opacity .8s var(--lh-ease, ease), transform .8s var(--lh-ease, ease)';
            requestAnimationFrame(() => {
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
            });
            setTimeout(() => {
                el.style.transition = '';
                el.style.opacity = '';
                el.style.transform = '';
                delete el.dataset.lhDelay;
            }, 850);
        }, delay);
    }


    /* ══════════════════════════════════════════
       3. HERO LOAD-IN SEQUENCE
       Above-the-fold content animates in on page
       load rather than on scroll.
    ══════════════════════════════════════════ */
    function initHeroLoadIn() {
        const selectors = [
            '.hero-eyebrow', '.hero-title', '.hero-sub', '.hero-ctas', '.hero-stats',
            '.deals-hero-tag', '.deals-hero h1', '.deals-hero p', '.countdown-wrap', '.deal-tabs',
            '.faq-hero-tag', '.faq-hero h1', '.faq-hero p',
            '.co-heading', '.orders-title', '.wl-title', '.cart-heading', '.shop-hero-body'
        ];
        const els = Array.from(document.querySelectorAll(selectors.join(',')));
        if (!els.length) return;
        if (REDUCED) return; // leave everything visible as authored

        els.forEach((el, i) => hideEl(el, 90 * i + 80));
        els.forEach(revealEl);
    }


    /* ══════════════════════════════════════════
       4. SCROLL REVEAL for cards / sections
    ══════════════════════════════════════════ */
    function initScrollReveal() {
        if (REDUCED || !('IntersectionObserver' in window)) return;

        const selectors = [
            '.product-card', '.category-card', '.feature-card', '.deal-card',
            '.order-card', '.wl-card', '.co-card', '.faq-item', '.faq-group-title',
            '.hero-device-card', '.promo-card', '.newsletter-card',
            '.brands-row', '.trust-row', '.pd-desc-box', '.related-header',
            '.deals-section-header', '.flash-banner', '.deals-newsletter'
        ];
        const els = Array.from(document.querySelectorAll(selectors.join(',')));
        if (!els.length) return;

        const io = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    revealEl(entry.target);
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

        els.forEach((el, i) => {
            hideEl(el, (i % 6) * 70);
            io.observe(el);
        });
    }


    /* ══════════════════════════════════════════
       5. MAGNETIC BUTTONS
       Subtle cursor-follow pull on primary CTAs.
       Inline transform on mousemove/mouseleave —
       never persists, so normal :hover states
       return the instant the cursor leaves.
    ══════════════════════════════════════════ */
    function initMagnetic() {
        if (REDUCED || NO_HOVER) return;
        const sel = '.btn-hero-primary, .btn-hero-secondary, .nav-btn-primary, ' +
                    '.btn-add-cart, .btn-place-order, .btn-checkout, .btn-quick-add, ' +
                    '.btn-add-all, .btn-deal-add';
        document.querySelectorAll(sel).forEach(btn => {
            btn.classList.add('lh-magnetic');
            const strength = 16;
            btn.addEventListener('mousemove', (e) => {
                const r = btn.getBoundingClientRect();
                const x = e.clientX - r.left - r.width / 2;
                const y = e.clientY - r.top - r.height / 2;
                btn.style.transform = `translate(${(x / r.width) * strength}px, ${(y / r.height) * strength}px)`;
            });
            btn.addEventListener('mouseleave', () => { btn.style.transform = ''; });
        });
    }


    /* ══════════════════════════════════════════
       6. 3D TILT on product imagery
       Applies to any product image wrap sitewide
       — homepage, shop grid, deals, wishlist,
       product detail gallery — no markup edits.
    ══════════════════════════════════════════ */
    function initTilt() {
        if (REDUCED || NO_HOVER) return;
        const sel = '.product-img-wrap, .gallery-main, .deal-img-wrap, .hero-device-card, .wl-img-wrap';
        document.querySelectorAll(sel).forEach(el => {
            el.classList.add('lh-tilt-target');
            const max = 7;
            el.addEventListener('mousemove', (e) => {
                const r = el.getBoundingClientRect();
                const px = (e.clientX - r.left) / r.width - 0.5;
                const py = (e.clientY - r.top) / r.height - 0.5;
                el.style.transform =
                    `perspective(900px) rotateX(${(-py * max).toFixed(2)}deg) rotateY(${(px * max).toFixed(2)}deg) translateZ(4px)`;
            });
            el.addEventListener('mouseleave', () => { el.style.transform = ''; });
        });
    }


    /* ══════════════════════════════════════════
       7. CUSTOM CURSOR
    ══════════════════════════════════════════ */
    function initCursor() {
        if (REDUCED || NO_HOVER) return;
        const dot = document.createElement('div'); dot.id = 'lhCursorDot';
        const ring = document.createElement('div'); ring.id = 'lhCursorRing';
        document.body.append(dot, ring);

        let tx = 0, ty = 0, rx = 0, ry = 0;
        window.addEventListener('mousemove', (e) => {
            tx = e.clientX; ty = e.clientY;
            dot.style.transform = `translate(${tx}px, ${ty}px) translate(-50%,-50%)`;
        });
        (function loop() {
            rx += (tx - rx) * 0.18;
            ry += (ty - ry) * 0.18;
            ring.style.transform = `translate(${rx}px, ${ry}px) translate(-50%,-50%)`;
            requestAnimationFrame(loop);
        })();

        document.querySelectorAll('a, button, .product-card, .deal-card, .category-card').forEach(el => {
            el.addEventListener('mouseenter', () => ring.classList.add('lh-cursor-active'));
            el.addEventListener('mouseleave', () => ring.classList.remove('lh-cursor-active'));
        });
    }


    /* ══════════════════════════════════════════
       8. ANIMATED STAT COUNTERS (homepage)
    ══════════════════════════════════════════ */
    function initCounters() {
        if (REDUCED || !('IntersectionObserver' in window)) return;
        const els = document.querySelectorAll('.stat-num');
        if (!els.length) return;

        const io = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                io.unobserve(entry.target);
                const el = entry.target;
                const firstNode = el.childNodes[0];
                if (!firstNode) return;
                const raw = firstNode.textContent || '';
                const num = parseFloat(raw.replace(/[^\d.]/g, ''));
                if (!num) return;
                const dur = 1100, start = performance.now();
                function step(now) {
                    const p = Math.min(1, (now - start) / dur);
                    const eased = 1 - Math.pow(1 - p, 3);
                    firstNode.textContent = Math.floor(num * eased);
                    if (p < 1) requestAnimationFrame(step);
                    else firstNode.textContent = num;
                }
                firstNode.textContent = '0';
                requestAnimationFrame(step);
            });
        }, { threshold: 0.5 });

        els.forEach(el => io.observe(el));
    }


    /* ══════════════════════════════════════════
       9. PAGE TRANSITION
       Scoped to primary navigation only (nav,
       drawer, footer, hero CTAs) — deliberately
       NOT global, so it never intercepts the
       cart/wishlist/coupon AJAX click handlers
       that already live on product & action cards.
    ══════════════════════════════════════════ */
    function initPageTransition() {
        if (REDUCED) return;
        const overlay = document.createElement('div');
        overlay.id = 'lhPageTransition';
        document.body.appendChild(overlay);

        const sel = 'nav a[href], .nav-drawer a[href], .footer-col a[href], ' +
                    '.btn-hero-primary, .btn-hero-secondary, .cos-back, ' +
                    '.btn-shop-more, .related-link, .nav-drop-item[href]';
        document.querySelectorAll(sel).forEach(a => {
            const href = a.getAttribute('href');
            if (!href || href.startsWith('#') || href.startsWith('http') ||
                href.startsWith('mailto:') || a.target === '_blank') return;

            a.addEventListener('click', (e) => {
                e.preventDefault();
                overlay.classList.add('is-active');
                setTimeout(() => { window.location = href; }, 300);
            });
        });
    }


    /* ══════════════════════════════════════════
       BOOT
    ══════════════════════════════════════════ */
    document.addEventListener('DOMContentLoaded', () => {
        initSmoothScroll();
        initNavShrink();
        initHeroLoadIn();
        initScrollReveal();
        initMagnetic();
        initTilt();
        initCursor();
        initCounters();
        initPageTransition();
    });
})();