/**
 * Color theme picker.
 *
 * data-bs-theme on <html> is the single source of truth (Bootstrap 5 /
 * AdminLTE 4 native theming, and FOG's own chrome keys off the same
 * attribute). It is already correct before this file runs:
 *
 *   - a forced 'light'/'dark' preference is stamped server-side on <html>
 *     (management/other/index.php), and
 *   - an unset preference leaves the attribute off, which the pre-paint
 *     script in <head> takes as its cue to resolve prefers-color-scheme.
 *
 * So this script never decides the FIRST paint. It only keeps the picker in
 * step, applies a new choice live, and -- in system mode -- follows the OS if
 * it changes while the page is open.
 *
 * Three states, and '' is not 'light': see FOGBase::displayTheme().
 *
 * Stored in userPrefs rather than a cookie, because the theme belongs to the
 * person and not the machine they happen to be sitting at. The login page has
 * no session and therefore no picker; it follows the system.
 */
(function () {
    'use strict';

    var PREF = 'display.theme';
    // Only for adopting a choice made before the preference existed.
    var LEGACY_COOKIE = 'fogTheme';

    function systemPrefersDark() {
        return !!(window.matchMedia &&
            window.matchMedia('(prefers-color-scheme: dark)').matches);
    }

    // The theme actually shown, given a stored preference of '' | light | dark.
    function effective(pref) {
        if (pref === 'dark' || pref === 'light') {
            return pref;
        }
        return systemPrefersDark() ? 'dark' : 'light';
    }

    function apply(pref) {
        var theme = effective(pref);
        document.documentElement.setAttribute('data-bs-theme', theme);

        // Let interested widgets (e.g. dashboard charts, which cache resolved
        // colors and cannot read CSS variables) recolor themselves live.
        document.dispatchEvent(new CustomEvent('fog:themechange', {
            detail: { dark: theme === 'dark' }
        }));

        // The picker used to be a navbar dropdown whose ICON carried the
        // state, so this also rewrote that icon and its title. The three
        // choices now live in the preferences dialog, where the tick below is
        // the state display and there is no icon to keep in step.
        //
        // What survives is the carrier: a hidden element holding the STORED
        // preference, which is not always the value on <html> -- '' means the
        // browser resolved it, and the tick has to distinguish "system, which
        // happens to be dark" from "dark". Its absence is also how this file
        // recognizes the login page, which has no session and so no stored
        // preference to read or write.
        var carrier = document.getElementById('themePref');
        if (!carrier) {
            return;
        }
        carrier.setAttribute('data-theme-pref', pref);

        // Tick the chosen row. invisible rather than d-none so the three rows
        // keep the same width and the menu does not jump as the tick moves.
        var items = document.querySelectorAll('[data-theme-choice]');
        Array.prototype.forEach.call(items, function (item) {
            var tick = item.querySelector('.theme-choice-tick');
            if (!tick) {
                return;
            }
            tick.classList.toggle(
                'invisible',
                item.getAttribute('data-theme-choice') !== pref
            );
        });
    }

    function readCookie(name) {
        var m = document.cookie.match('(?:^|; )' + name + '=([^;]*)');
        return m ? decodeURIComponent(m[1]) : null;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var carrier = document.getElementById('themePref');
        if (!carrier) {
            // The login page: no session, so no preference to read or write.
            // The pre-paint script has already resolved the system value.
            return;
        }

        var pref = carrier.getAttribute('data-theme-pref') || '';

        // One-time adoption of the choice made when the theme lived in a
        // cookie. Only when the user has no stored preference yet, so it can
        // never overwrite a deliberate later choice. The cookie is expired
        // either way, so this runs at most once per browser.
        var legacy = readCookie(LEGACY_COOKIE);
        if (legacy === 'dark' || legacy === 'light') {
            if (pref === '') {
                pref = legacy;
                fogPrefStore(PREF, pref);
            }
            document.cookie = LEGACY_COOKIE +
                '=; path=/; max-age=0; samesite=lax';
        }

        apply(pref);

        document.querySelectorAll('[data-theme-choice]').forEach(function (item) {
            item.addEventListener('click', function (e) {
                e.preventDefault();
                var chosen = item.getAttribute('data-theme-choice') || '';
                // Applied before the request rather than after it: the theme
                // is a client-side attribute flip, so waiting on the network
                // would only add lag to something that cannot fail visibly.
                // An empty value clears the preference, which is what "follow
                // the system" means -- the store deletes the row rather than
                // holding an empty string.
                apply(chosen);
                fogPrefStore(PREF, chosen, function (err) {
                    if (err) {
                        $.notifyFromAPI(err.responseJSON, err);
                    }
                });
            });
        });

        // In system mode, follow the OS while the page is open. Without this
        // a user who flips their desktop to dark at dusk keeps a light FOG
        // until they navigate, which reads as the setting not working.
        if (window.matchMedia) {
            var mq = window.matchMedia('(prefers-color-scheme: dark)');
            var onChange = function () {
                if ((toggle.getAttribute('data-theme-pref') || '') === '') {
                    apply('');
                }
            };
            if (mq.addEventListener) {
                mq.addEventListener('change', onChange);
            } else if (mq.addListener) {
                // Safari < 14.
                mq.addListener(onChange);
            }
        }
    });
}());
