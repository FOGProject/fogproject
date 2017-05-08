<?php
/**
 * The fileintegiry type hook
 *
 * PHP version 5
 *
 * @category AddFileIntegrityType
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The fileintegiry type hook
 *
 * @category AddFileIntegrityType
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddFileIntegrityType extends Hook
{
    /**
     * The hook name
     *
     * @var string
     */
    public $name = 'AddFileIntegrityType';
    /**
     * The hook description
     *
     * @var string
     */
    public $description = 'Add Report Management Type';
    /**
     * The active flag
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node to enact within
     *
     * @var string
     */
    public $node = 'fileintegrity';
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
