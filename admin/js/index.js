/* ── Sidebar toggle ── */
    function toggleSb() {
        const sb = document.getElementById('sidebar');
        const ovl = document.getElementById('sbOvl');
        sb.classList.toggle('open');
        ovl.classList.toggle('show');
    }

    function closeSb() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sbOvl').classList.remove('show');
    }

    /* ── Dropdown toggle ── */
    function toggleDrop(id) {
        document.querySelectorAll('.nav-dropdown').forEach(d => {
            if (d.id !== id) d.classList.remove('open');
        });
        document.getElementById(id).classList.toggle('open');
    }
    document.addEventListener('click', e => {
        if (!e.target.closest('.nav-dropdown')) {
            document.querySelectorAll('.nav-dropdown').forEach(d => d.classList.remove('open'));
        }
    });

    /* ── Toast ── */
    let toastTimer;

    function showToast(msg, icon = '✓') {
        const t = document.getElementById('toast');
        document.getElementById('ti').textContent = icon;
        document.getElementById('tm').textContent = msg;
        t.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => t.classList.remove('show'), 3000);
    }

    /* ── Animate chart bars on load ── */
    document.querySelectorAll('.cbar').forEach(b => {
        const h = parseFloat(b.style.getPropertyValue('--h'));
        b.style.setProperty('--h', 0);
        requestAnimationFrame(() => {
            b.style.transition = 'none';
            requestAnimationFrame(() => {
                b.style.transition = '';
                b.style.setProperty('--h', h);
            });
        });
    });

    /* ── Revenue chart: This Year / Last Year toggle ── */
    (function () {
        const sel = document.getElementById('revChartRange');
        const wrap = document.getElementById('revChart');
        if (!sel || !wrap || !window.revChartData) return;

        const labelEls = wrap.parentElement.querySelectorAll('.clabel');
        const bars = wrap.querySelectorAll('.cbar');

        function render(key) {
            const d = window.revChartData[key];
            if (!d) return;
            const max = d.max || 1;

            bars.forEach((bar, idx) => {
                const val = d.sales[idx] ?? 0;
                const pct = Math.round((val / max) * 100) / 100;
                bar.style.setProperty('--h', pct);
                bar.classList.toggle('highlight', key === 'thisYear' && idx === bars.length - 1);
                const tip = bar.querySelector('.ctip');
                if (tip) tip.textContent = `$${val}k · ${d.orders[idx] ?? 0} orders`;
            });

            labelEls.forEach((lbl, idx) => {
                if (d.months[idx]) lbl.textContent = d.months[idx];
            });
        }

        sel.addEventListener('change', () => render(sel.value));
    })();