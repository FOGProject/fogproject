<?php
/**
 * API Documentation Page
 *
 * PHP version 5
 *
 * Renders this server's own OpenAPI description, with a working try-it
 * console.
 *
 * @category ApiDocumentation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * API Documentation Page
 *
 * Renders the document served by GET {webroot}/system/openapi, which is
 * generated per request from this server's live routing and model metadata
 * (see the OpenAPI class).
 *
 * Rendered here, inside the web UI, rather than published to the docs site,
 * and the reason is try-it. A console on a static docs page has no server to
 * call; served from the FOG server itself it is same-origin against the very
 * install the admin is looking at, and describes the classes THIS server
 * exposes, plugin contributions included. A published page could only ever
 * show one version of one stock install.
 *
 * @category ApiDocumentation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ApiDocumentation extends FOGPage
{
    /**
     * The node that's related to this class
     *
     * @var string
     */
    public $node = 'apidocs';
    /**
     * Initializes the page
     *
     * @param string $name the name to initialize with
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = _('API Documentation');
        parent::__construct($this->name);
    }
    /**
     * Displays the API reference.
     *
     * @return void
     */
    public function index(...$args)
    {
        /**
         * The Swagger UI bundle and its stylesheets are registered against
         * the apidocs node in Page, alongside every other page's assets.
         * They were queued from here originally, through
         * getClass('Page')->addJavascript(), which silently did nothing:
         * getClass() news up a fresh Page every call, so the assets landed on
         * a throwaway object and were never emitted.
         */
        $apiEnabled = (bool)self::getSetting('FOG_API_ENABLED');
        $specUrl = sprintf(
            '%s://%s%ssystem/openapi',
            self::$httpproto,
            self::$httphost,
            Route::webrootbase()
        );

        if (!$apiEnabled) {
            /**
             * The whole API tree redirects away when it is disabled, so the
             * spec URL is unreachable too and the console would render an
             * empty document with no explanation. Say why, and say where to
             * fix it, rather than letting it look broken.
             */
            echo '<div class="row"><div class="col-md-12">';
            echo '<div class="card card-warning card-outline">';
            echo '<div class="card-header"><h4 class="card-title">';
            echo _('The API is disabled');
            echo '</h4></div><div class="card-body">';
            echo '<p>';
            echo _(
                'This page documents the FOG REST API, which is currently '
                . 'switched off. Enable it under FOG Configuration > FOG '
                . 'Settings > API System, then return here.'
            );
            echo '</p>';
            echo '</div></div></div></div>';
            return;
        }

        echo '<div class="row"><div class="col-md-12">';
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header"><h4 class="card-title">';
        echo _('REST API reference');
        echo '</h4></div>';
        echo '<div class="card-body">';
        echo '<p>';
        printf(
            // translators: %s is a URL.
            _(
                'Generated from this server\'s own routing and model metadata, '
                . 'so it describes the classes and routes this install '
                . 'exposes. The raw document is at %s.'
            ),
            sprintf(
                '<a href="%1$s" target="_blank" rel="noopener">%1$s</a>',
                htmlentities($specUrl, ENT_QUOTES, 'utf-8')
            )
        );
        echo '</p>';
        echo '<p>';
        echo _(
            'Use Authorize to enter your API tokens before trying a call. '
            . 'The server-wide token is under FOG Configuration > FOG '
            . 'Settings > API System; your personal token is on the API tab '
            . 'of your user account.'
        );
        echo '</p>';
        echo '</div></div></div></div>';

        echo '<div class="row"><div class="col-md-12">';
        /**
         * spec-url is read by the JS rather than being set as an attribute
         * here, so the URL is escaped once, as JSON, by the same code that
         * uses it.
         */
        printf(
            '<div id="apidocs-container" data-spec-url="%s"></div>',
            htmlentities($specUrl, ENT_QUOTES, 'utf-8')
        );
        echo '</div></div>';
    }
}
