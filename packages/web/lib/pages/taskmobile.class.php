<?php
/**
 * Presents the tasks to mobile.
 *
 * PHP version 5
 *
 * @category TaskMobile
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Presents the tasks to mobile.
 *
 * @category TaskMobile
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class TaskMobile extends FOGPage
{
    /**
     * The node this page displays on.
     *
     * @var string
     */
    public $node = 'task';
    /**
     * Initializes the task mobile page.
     *
     * @param string $name The name if different to load.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Task Management';
        parent::__construct($this->name);
        $this->headerData = array(
            _('Force'),
            _('Task Name'),
            _('Host'),
            _('Type'),
            _('State'),
            _('Kill'),
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(),
            array(),
            array(
                'class' => 'filter-false'
            ),
        );
        $this->templates = array(
            '${task_force}',
            '${task_name}',
            '${host_name}',
            '${task_type}',
            '${task_state}',
            '<a href="?node=${node}&sub=killtask&id=${id}">'
            . '<i class="fa fa-minus-circle fa-2x task"></i></a>',
        );
        global $id;
        if (isset($id)) {
            $this->obj = new Task($id);
        }
    }
    /**
     * The page to display.
     *
     * @return void
     */
    public function index()
    {
        $this->active();
    }
    /**
     * Redirect to index.
     *
     * @return void
     */
    public function search()
    {
        self::redirect(
            sprintf(
                '?node=%s&sub=active',
                $this->node
            )
        );
    }
    /**
     * Redirect to index.
     *
     * @return void
     */
    public function searchPost()
    {
        self::redirect(
            sprintf(
                '?node=%s&sub=active',
                $this->node
            )
        );
    }
    /**
     * Set forced status.
     *
     * @return void
     */
    public function force()
    {
        $this->obj
            ->set('isForced', 1)
            ->save();
        self::redirect(
            sprintf(
                '?node=%s',
                $this->node
            )
        );
    }
    /**
     * Cancels/kills the tasks.
     *
     * @return void
     */
    public function killtask()
    {
        $this->obj->cancel();
        self::redirect(
            sprintf(
                '?node=%s',
                $this->node
            )
        );
    }
    /**
     * The stuff to display.
     *
     * @return void
     */
    public function active()
    {
        $find = array(
            'stateID' => self::fastmerge(
                (array) self::getQueuedStates(),
                (array) self::getProgressState()
            )
        );
        foreach ((array)self::getClass('TaskManager')
            ->find($find) as &$Task
        ) {
            $Host = $Task->getHost();
            $name = sprintf(
                '%s %s',
                (
                    $Task->isForced() ?
                    '*' :
                    ''
                ),
                $Host->get('name')
            );
            unset($Host);
            $this->data[] = array(
                'id' => $Task->get('id'),
                'task_name' => $Task->get('name'),
                'host_name' => $name,
                'task_type' => $Task->getTaskTypeText(),
                'task_state' => $Task->getTaskStateText(),
                'task_force' =>
                (
                    !$Task->isForced() ?
                    '<a href="?node=${node}&sub=force&id=${id}">'
                    . '<i class="fa fa-step-forward fa-2x task"></i></a>' :
                    '<i class="fa fa-play fa-2x task"></i>'
                )
            );
            unset($Task);
        }
        $this->render();
    }
}
