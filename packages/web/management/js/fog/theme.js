/**
 * Dark-mode toggle.
 *
 * The <body> theme class is stamped server-side from the fogTheme cookie (see
 * management/other/index.php) so the first paint already matches the user's
 * choice — there is no light flash on reload. This script only:
 *   - syncs the toggle icon/label with the effective theme on load (this also
 *     covers the "no cookie, OS is dark" case, which the server cannot know), and
 *   - flips the body class live and writes the cookie when the toggle is clicked.
 *
 * Themes: 'dark' | 'light'. No cookie => follow the OS via prefers-color-scheme.
 */
(function () {
    'use strict';

    var COOKIE = 'fogTheme';

    function readCookie(name) {
        var m = document.cookie.match('(?:^|; )' + name + '=([^;]*)');
        return m ? decodeURIComponent(m[1]) : null;
    }

    function writeCookie(name, value) {
        // One year; path=/ so it applies site-wide; lax is fine for a UI pref.
        document.cookie = name + '=' + encodeURIComponent(value) +
            '; path=/; max-age=31536000; samesite=lax';
    }

    function systemPrefersDark() {
        return !!(window.matchMedia &&
            window.matchMedia('(prefers-color-scheme: dark)').matches);
    }

    // The effective theme = explicit cookie choice, else the OS preference.
    function effectiveIsDark() {
        var pref = readCookie(COOKIE);
        if (pref === 'dark') {
            return true;
        }
        if (pref === 'light') {
            return false;
        }
        return systemPrefersDark();
    }

    function apply(isDark) {
        var body = document.body;
        body.classList.toggle('theme-dark', isDark);
        body.classList.toggle('theme-light', !isDark);

        // Let interested widgets (e.g. dashboard charts, which cache resolved
        // colors and cannot read CSS variables) recolor themselves live.
        document.dispatchEvent(new CustomEvent('fog:themechange', {
            detail: { dark: isDark }
        }));

        var toggle = document.getElementById('themeToggle');
        if (!toggle) {
            return;
        }
        var icon = toggle.querySelector('i');
        if (icon) {
            // Show the action the click will perform: sun when dark, moon when light.
            icon.className = isDark ? 'fa fa-sun-o' : 'fa fa-moon-o';
        }
        var label = isDark
            ? (toggle.getAttribute('data-label-dark') || '')
            : (toggle.getAttribute('data-label-light') || '');
        if (label) {
            toggle.setAttribute('title', label);
            toggle.setAttribute('aria-label', label);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Sync the toggle with whatever the body actually resolved to.
        apply(effectiveIsDark());

        var toggle = document.getElementById('themeToggle');
        if (!toggle) {
            return;
        }
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            var next = !document.body.classList.contains('theme-dark');
            writeCookie(COOKIE, next ? 'dark' : 'light');
            apply(next);
        });
    });
}());
