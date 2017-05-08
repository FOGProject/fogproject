<?php
/**
 * Sets snapin task states
 *
 * PHP version 5
 *
 * @category SetSnapinTaskState
 * @package  FOGProject
 * @author   Lee Rowlett <nah@nah.nah>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Sets snapin task states
 *
 * @category SetSnapinTaskState
 * @package  FOGProject
 * @author   Lee Rowlett <nah@nah.nah>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SetSnapinTaskState extends Hook
{
    /**
     * The name of the hook.
     *
     * @var string
     */
    public $name = 'SetSnapinTaskState';
    /**
     * The description of the hook.
     *
     * @var string
     */
    public $description = 'Sets Snapin Task State to non-default ID';
    /**
     * The active flag.
     *
     * @var bool
     */
    public $active = false;
    /**
     * The node the hook enacts upon/with
     *
     * @var string
     */
    public $node = 'taskstateedit';
    /**
     * Initializes object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        self::$HookManager
            ->register(
                'CHECKEDIN_STATE',
                array(
                    $this,
                    'setCheckedInState'
                )
            )
            ->register(
                'PROGRESS_STATE',
                array(
                    $this,
                    'setProgressState'
                )
            )
            ->register(
                'QUEUED_STATES',
                array(
                    $this,
                    'addQueuedState'
                )
            )
            ->register(
                'TaskActiveSnapinsData',
                array(
                    $this,
                    'setStateWidth'
                )
            );
    }
    /**
     * Sets the checkin state
     *
     * @param array $arguments the arguments to change
     *
     * @return void
     */
    public function setCheckinState($arguments)
    {
        if (isset($_REQUEST['stateid'])) {
            $arguments['checkedInState'] = $_REQUEST['stateid'];
        }
    }
    /**
     * Sets the progress state
     *
     * @param array $arguments the arguments to change
     *
     * @return void
     */
    public function setProgressState($arguments)
    {
        if (isset($_REQUEST['stateid'])) {
            $arguments['progressState'] = $_REQUEST['stateid'];
        }
    }
    /**
     * Adds to the queued states
     *
     * @param array $arguments the arguments to change
     *
     * @return void
     */
    public function addQueuedState($arguments)
    {
        $arguments['queuedStates'] = self::fastmerge(
            $arguments['queuedStates'],
            range(6, 75)
        );
    }
    /**
     * Sets the state width in GUI for display
     *
     * @param array $arguments the arguments to change
     *
     * @return void
     */
    public function setStateWidth($arguments)
    {
        $arguments['attributes'][1] = array('width' => 10);
        $arguments['attributes'][4] = array(
            'width' => 165,
            'class' => 'c'
        );
    }
}
