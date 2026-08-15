/**
 * Renders this server's OpenAPI document in the API Documentation page.
 *
 * The spec URL comes from a data attribute rather than being templated into
 * the script, so it is escaped once, by PHP, as part of the markup.
 *
 * Deliberately does not pre-fill the signed-in user's API token. It would
 * save a paste, at the cost of writing a live credential into the page for
 * anything with DOM access to read. The Authorize button is one paste.
 */
(function () {
    'use strict';

    function render() {
        var container = document.getElementById('apidocs-container');
        if (!container) {
            return;
        }
        var specUrl = container.getAttribute('data-spec-url');
        if (!specUrl) {
            return;
        }
        if (container.querySelector('rapi-doc')) {
            return;
        }

        var doc = document.createElement('rapi-doc');
        doc.setAttribute('spec-url', specUrl);

        // Match the AdminLTE shell rather than shipping RapiDoc's own theme.
        doc.setAttribute('theme', 'light');
        doc.setAttribute('bg-color', '#ffffff');
        doc.setAttribute('text-color', '#212529');
        doc.setAttribute('primary-color', '#007bff');
        doc.setAttribute('render-style', 'read');
        doc.setAttribute('schema-style', 'table');
        doc.setAttribute('nav-bg-color', '#343a40');
        doc.setAttribute('nav-text-color', '#c2c7d0');
        doc.setAttribute('font-size', 'default');

        // The page already carries FOG's own header and menu.
        doc.setAttribute('show-header', 'false');
        doc.setAttribute('show-info', 'true');
        doc.setAttribute('allow-spec-url-load', 'false');
        doc.setAttribute('allow-spec-file-load', 'false');
        doc.setAttribute('allow-server-selection', 'false');

        // Same-origin, so the browser sends the session cookie and try-it
        // works against this very server.
        doc.setAttribute('allow-try', 'true');
        doc.setAttribute('allow-authentication', 'true');
        doc.setAttribute('fetch-credentials', 'same-origin');

        doc.setAttribute('style', 'height:calc(100vh - 220px);min-height:600px;width:100%;');

        doc.addEventListener('spec-loaded', function () {
            container.classList.add('apidocs-loaded');
        });

        container.appendChild(doc);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', render);
    } else {
        render();
    }
}());
