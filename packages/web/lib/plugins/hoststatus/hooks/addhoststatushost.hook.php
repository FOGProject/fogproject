<?php
/**
 * Adds the host status to host.
 *
 * @category AddHostStatusHost
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@ehu.eus>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddHostStatusHost extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddHostStatusHost';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add host status to Hosts';
    /**
     * The active flag (always true but for posterity)
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node this hook enacts with.
     *
     * @var string
     */
    public $node = 'host';
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
                'HOST_FIELDS',
                array(
                    $this,
                    'hostFields'
                )
            );
    }
    /**
     * Adjusts the host fields.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function hostFields($arguments)
    {
        global $node;
        global $sub;
        if ($node != 'host') {
            return;
        }
        $ping = self::getClass('Ping', $arguments['Host']->get('ip'))->execute();
        self::arrayInsertAfter(
            '<label for="name">'
            . _('Host Name')
            . '</label>',
            $arguments['fields'],
            '<label for="status">'
            . _('Host Status')
            . '</label>',
            '<div class="input-group">'
            . $this->getPingCodeStr($ping, $arguments['Host']->get('id'))
            . '</div>'
        );
    }
    /**
     * Translates the ping status code to string
     *
     * @return string
     */
    public function getPingCodeStr($val = null, $hostID)
    {
        $strtoupdate = '<i class="icon-ping-%s fa fa-%s %s'
            . '" data-toggle="tooltip" '
            . 'data-placement="right" '
            . 'title="%s'
            . '"></i>';
        ob_start();
        switch ($val) {
                case 0:
                        printf($strtoupdate, 'windows', 'windows', 'green', 'Windows');
                        break;
                case 111:
                        $taskID = self::getSubObjectIDs(
                            'Task',
                            array('hostID' => $hostID,
                                      'stateID' => 2
                                ),
                            'id'
                        );
                        if (is_null($taskID)) {
                            printf($strtoupdate, 'linux', 'linux', 'blue', 'Linux');
                        } else {
                            printf($strtoupdate, 'fos', 'cogs', 'green', 'FOS');
                        }
                        break;
                default:
                        printf($strtoupdate, 'down', 'exclamation-circle', 'red', 'Unknown');
        }
        return ob_get_clean();
    }
}
