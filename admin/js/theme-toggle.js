/* ============================================================
   LectroHub — Theme Toggle
   Persists choice in localStorage under 'lh-theme'. Since
   localStorage is shared across the whole origin, toggling on
   any customer OR admin page updates the theme everywhere.
   ============================================================ */
(function () {
    var STORAGE_KEY = 'lh-theme';

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        document.querySelectorAll('.theme-toggle-btn').forEach(function (btn) {
            btn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
        });
    }

    window.lhSetTheme = function (theme) {
        try { localStorage.setItem(STORAGE_KEY, theme); } catch (e) {}
        applyTheme(theme);
    };

    window.lhToggleTheme = function () {
        var current = document.documentElement.getAttribute('data-theme') || 'light';
        window.lhSetTheme(current === 'dark' ? 'light' : 'dark');
    };

    document.addEventListener('DOMContentLoaded', function () {
        var current = document.documentElement.getAttribute('data-theme') || 'light';
        applyTheme(current);
    });
})();