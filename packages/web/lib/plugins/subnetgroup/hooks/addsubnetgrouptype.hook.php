<?php
/**
 * Adds SubnetGroup type for export.
 *
 * PHP version 5
 *
 * @category AddSubnetGroupType
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   sctt <none@none>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds Broadcast type for export.
 *
 * @category AddSubnetGroupType
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   sctt <none@none>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddSubnetGroupType extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddSubnetGroupType';
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
    public $node = 'subnetgroup';
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        self::$HookManager
            ->register(
                'REPORT_TYPES',
                array(
                    $this,
                    'reportTypes'
                )
            );
    }
}
