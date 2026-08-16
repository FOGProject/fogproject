<?php
/**
 * The oui class.
 *
 * PHP version 5
 *
 * @category OUI
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.rog
 */

namespace FOG;

/**
 * The oui class.
 *
 * @category OUI
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.rog
 */
class OUI extends FOGController
{
    /**
     * The oui table name.
     *
     * @var string
     */
    protected $databaseTable = 'oui';
    /**
     * The oui fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'ouiID',
        'prefix' => 'ouiMACPrefix',
        'name' => 'ouiMan'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'prefix',
        'name'
    ];
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\OUI', 'OUI');
