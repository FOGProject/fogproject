<?php
/**
 * Enforce the Site object-scope boundary.
 *
 * PHP version 5
 *
 * @category SiteScopeCheck
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Enforce the Site object-scope boundary.
 *
 * This is the plugin half of the core OBJECT_SCOPE_CHECK contract
 * (Authorization::objectInScope). Core fires the event for a single object
 * after the verb permission has already passed; with no listener the
 * boundary does not exist. This listener denies any host/user/group/
 * usergroup that is not within the acting user's site scope.
 *
 * @category SiteScopeCheck
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SiteScopeCheck extends Hook
{
    /**
     * The name of the hook.
     *
     * @var string
     */
    public $name = 'SiteScopeCheck';
    /**
     * The description.
     *
     * @var string
     */
    public $description = 'Enforce the site boundary for single-object access.';
    /**
     * For posterity.
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node the hook works with.
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
            ['OBJECT_SCOPE_CHECK', 'checkObjectScope'],
        ]);
    }
    /**
     * Flip 'allowed' to false when the target object is outside the acting
     * user's site scope. Core has already excluded unrestricted users and
     * id-less requests before firing, so we only consult membership here.
     *
     * @param mixed $arguments The scope-check arguments to modify.
     *
     * @return void
     */
    public function checkObjectScope($arguments)
    {
        // Another listener already denied; nothing to add.
        if (!$arguments['allowed']) {
            return;
        }
        if (!Site::inScope(
            $arguments['node'],
            $arguments['id'],
            $arguments['userID']
        )) {
            $arguments['allowed'] = false;
        }
    }
}
