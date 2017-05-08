<?php
/**
 * Add task type type reporter.
 *
 * PHP Version 5
 *
 * @category AddTaskTypeType
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Add task type type reporter.
 *
 * @category AddTaskTypeType
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddTaskTypeType extends Hook
{
    /**
     * Name of the hook.
     *
     * @var string
     */
    public $name = 'AddTaskTypeType';
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
     * Node to work with.
     *
     * @var string
     */
    public $node = 'tasktypeedit';
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
