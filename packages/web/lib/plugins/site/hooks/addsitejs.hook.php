<?php
/**
 * Sets the javascript files up for this hook.
 *
 * PHP version 5
 *
 * @category AddSiteJS
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Sets the javascript files up for this hook.
 *
 * @category AddSiteJS
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddSiteJS extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddSiteJS';
    /**
     * The description.
     *
     * @var string
     */
    public $description = 'Add Site JS files.';
    /**
     * For posterity.
     *
     * @var bool
     */
    public $active = true;
    /**
     * What plugin this works against.
     *
     * @var string
     */
    public $node = 'site';
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->registerInstalled([
            ['PAGE_JS_FILES', 'injectJSFiles'],
        ]);
    }
    /**
     * The files we need to inject.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function injectJSFiles($arguments)
    {
        $this->injectPluginJS($arguments, [
            'site' => ['fallback' => true],
            'user' => ['secondary' => true],
            'host' => ['secondary' => true],
            'group' => ['secondary' => true],
        ]);
    }
}
