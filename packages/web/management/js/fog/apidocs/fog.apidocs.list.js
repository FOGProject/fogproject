/**
 * Renders this server's OpenAPI document in the API Documentation page.
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

    function render() {
        var container = document.getElementById('apidocs-container');
        if (!container) {
            return;
        }
        var specUrl = container.getAttribute('data-spec-url');
        if (!specUrl) {
            return;
        }
        if (typeof SwaggerUIBundle === 'undefined') {
            container.textContent =
                'Swagger UI failed to load. Check that '
                + 'management/js/swagger-ui-bundle.js is present.';
            return;
        }

        SwaggerUIBundle({
            url: specUrl,
            dom_id: '#apidocs-container',
            deepLinking: true,
            presets: [SwaggerUIBundle.presets.apis],
            layout: 'BaseLayout',
            docExpansion: 'list',
            defaultModelsExpandDepth: 1,
            // Same-origin, so the browser sends the session cookie and
            // try-it works against this very server.
            withCredentials: true,
            // The spec names exactly one server, this one. Offering a
            // picker would only invite calls at something else.
            requestInterceptor: function (request) {
                return request;
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', render);
    } else {
        render();
    }
}());
