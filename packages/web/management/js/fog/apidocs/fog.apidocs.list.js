/**
 * Renders this server's OpenAPI document in the API Documentation page.
 *
 * Waits for SwaggerUIBundle rather than assuming it is already there, and the
 * reason is FOG's own navigation. Clicking the menu item does not reload the
 * page: fog.common.js reads X-FOG-JavaScripts off the response and appends the
 * scripts for the new node. On that path document.readyState is already
 * 'complete', so a DOMContentLoaded handler never fires and the naive fallback
 * -- run immediately instead -- runs before the sibling script has finished
 * executing. swagger-ui-bundle.js is 1.5MB, so it loses that race every time,
 * and the page then reported a missing file when the file was there all along.
 *
 * The spec URL comes from a data attribute rather than being templated into
 * the script, so it is escaped once, by PHP, as part of the markup.
 *
 * Deliberately does not pre-fill the signed-in user's API token. It would
 * save a paste, at the cost of writing a live credential into the page for
 * anything with DOM access to read. Use Authorize instead.
 */
/* global SwaggerUIBundle */
(function () {
    'use strict';

    // 1.5MB over a slow link takes a moment; 20s is generous without spinning
    // forever when the asset really is absent.
    var POLL_MS = 100;
    var MAX_TRIES = 200;

    function report(container, specUrl, waitedMs) {
        // Name which of the two things went wrong rather than always blaming
        // the file, which sent someone looking for an asset that was present.
        var heading = document.createElement('div');
        heading.className = 'alert alert-warning';
        heading.textContent = 'The API reference could not be displayed.';
        container.appendChild(heading);

        var detail = document.createElement('p');
        detail.textContent = 'Working out why...';
        container.appendChild(detail);

        var link = document.createElement('p');
        var anchor = document.createElement('a');
        anchor.href = specUrl;
        anchor.target = '_blank';
        anchor.rel = 'noopener';
        anchor.textContent = 'The raw OpenAPI document is still available here.';
        link.appendChild(anchor);
        container.appendChild(link);

        var bundleUrl = '../management/js/swagger-ui-bundle.js';
        window.fetch(bundleUrl, { method: 'HEAD', credentials: 'same-origin' })
            .then(function (response) {
                if (response.ok) {
                    detail.textContent = 'swagger-ui-bundle.js is present but had not finished '
                        + 'loading after ' + Math.round(waitedMs / 1000) + ' seconds. Reload the '
                        + 'page; if it keeps happening, check the browser console for a script '
                        + 'error.';
                } else {
                    detail.textContent = 'swagger-ui-bundle.js is missing from the web root '
                        + '(HTTP ' + response.status + '). Re-run the installer so the '
                        + 'management assets are copied into place.';
                }
            })
            .catch(function () {
                detail.textContent = 'swagger-ui-bundle.js could not be fetched at all. Check '
                    + 'that the web server serves management/js/, then reload.';
            });
    }

    function render(specUrl) {
        SwaggerUIBundle({
            url: specUrl,
            dom_id: '#apidocs-container',
            deepLinking: true,
            presets: [SwaggerUIBundle.presets.apis],
            // BaseLayout deliberately, not StandaloneLayout. The latter adds
            // the topbar with a spec URL box and an Explore button, which
            // invites pointing this at some other server's document. There is
            // one spec worth reading here and it is this server's.
            layout: 'BaseLayout',
            docExpansion: 'list',
            defaultModelsExpandDepth: 1,
            // Same-origin, so the browser sends the session cookie and try-it
            // works against this very server.
            withCredentials: true
        });
    }

    function boot() {
        var container = document.getElementById('apidocs-container');
        if (!container) {
            return;
        }
        var specUrl = container.getAttribute('data-spec-url');
        if (!specUrl) {
            return;
        }
        // A full page load fires DOMContentLoaded and an AJAX navigation calls
        // boot() directly, so guard against rendering twice into one container.
        if (container.getAttribute('data-apidocs-booted') === '1') {
            return;
        }
        container.setAttribute('data-apidocs-booted', '1');

        var tries = 0;
        (function waitForBundle() {
            if (typeof SwaggerUIBundle !== 'undefined') {
                render(specUrl);
                return;
            }
            tries += 1;
            if (tries >= MAX_TRIES) {
                report(container, specUrl, POLL_MS * tries);
                return;
            }
            window.setTimeout(waitForBundle, POLL_MS);
        }());
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());
