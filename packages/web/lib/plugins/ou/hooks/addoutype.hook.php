<?php
/**
 * Adds the ou report type.
 *
 * PHP version 5
 *
 * @category AddOUType
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds the ou report type.
 *
 * @category AddOUType
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddOUType extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddOUType';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add Report Management Type';
    /**
     * The active flag.
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node this hook enacts with.
     *
     * @var string
     */
    public $node = 'ou';
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        self::$HookManager->register(
            'REPORT_TYPES',
            [$this, 'reportTypes']
        );
    }
}
