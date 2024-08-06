<?php
/**
 * Injects slack stuff into the api system.
 *
 * PHP version 5
 *
 * @category AddSlackAPI
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Injects slack stuff into the api system.
 *
 * @category AddSlackAPI
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddSlackAPI extends Hook
{
    /**
     * The name of the hook.
     *
     * @var string
     */
    public $name = 'AddSlackAPI';
    /**
     * The description.
     *
     * @var string
     */
    public $description = 'Add Slack stuff into the api system.';
    /**
     * For posterity.
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node the hook works with.
     *
     * @var string
     */
    public $node = 'slack';
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
            'API_VALID_CLASSES',
            [$this, 'injectAPIElements']
        )->register(
            'CUSTOMIZE_DT_COLUMNS',
            [$this, 'customizeDT']
        );
    }
    /**
     * Customize our new columns.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function customizeDT($arguments)
    {
        if ($arguments['classname'] != $this->node) {
            return;
        }
        $arguments['columns'] = [];
        foreach (self::getClass('SlackManager')
            ->getColumns() as $common => &$real
        ) {
            switch ($common) {
                case 'id':
                    $arguments['columns'][] = [
                        'db' => $real,
                        'dt' => 'DT_RowId',
                        'formatter' => function ($d, $row) {
                            return $d;
                        }
                    ];
                    $arguments['columns'][] = [
                        'db' => $real,
                        'dt' => $common,
                        'formatter' => function ($d, $row) {
                            $team = self::getClass(
                                'Slack',
                                $d
                            )->call('auth.test');
                            return $team['team'];
                        }
                    ];
                    break;
                case 'token':
                    $arguments['columns'][] = [
                        'db' => $real,
                        'dt' => $common,
                        'formatter' => function ($d, $row) {
                            $team = self::getClass(
                                'Slack',
                                $row['sID']
                            )->call('auth.test');
                            return $team['user'];
                            ;
                        }
                    ];
                    break;
                default:
                    $arguments['columns'][] = [
                        'db' => $real,
                        'dt' => $common,
                    ];
            }
        }
    }
    /**
     * This function injects slack elements for
     * api access.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function injectAPIElements($arguments)
    {
        $arguments['validClasses'][] = $this->node;
    }
}
