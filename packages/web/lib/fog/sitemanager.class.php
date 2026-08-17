<?php
/**
 * Site manager.
 *
 * PHP version 7.4+
 *
 * @category SiteManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * Site manager.
 *
 * No schema()/install()/uninstall() here, unlike the plugin manager this
 * replaces. Core tables are created by commons/schema.php (step 331) and
 * migrated by step 332; a manager that could also create them would be a
 * second, divergent definition of the same tables.
 *
 * @category SiteManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SiteManager extends FOGManagerController
{
    /**
     * The table name.
     *
     * @var string
     */
    public $tablename = 'sites';
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\SiteManager', 'SiteManager');
