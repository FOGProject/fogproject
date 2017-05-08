<?php
/**
 * Adds task state type report.
 *
 * PHP Version 5
 *
 * @category AddTaskStateType
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds task state type report.
 *
 * @category AddTaskStateType
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddTaskStateType extends Hook
{
    /**
     * Name of the hook.
     *
     * @var string
     */
    public $name = 'AddTaskStateType';
    /**
     * Description of the hook.
     *
     * @var string
     */
    public $description = 'Add Report Management Type';
    /**
     * Active?
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node to work with.
     *
     * @var string
     */
    public $node = 'taskstateedit';
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
