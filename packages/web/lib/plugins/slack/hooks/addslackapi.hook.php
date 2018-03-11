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
            'API_MASSDATA_MAPPING',
            [$this, 'adjustMassData']
        );
    }
    /**
     * Adjust the returned data so we don't need to do
     * ajax calls in the list.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function adjustMassData($arguments)
    {
        $items = $arguments['data'];
        foreach ($items['data'] as $ind => &$item) {
            $team = self::getClass(
                'Slack',
                $item['id']
            )->call('auth.test');
            $items['data'][$ind]['id'] = $team['team'];
            $items['data'][$ind]['token'] = $team['user'];
            unset($item);
        }
        $arguments['data']['data'] = $items['data'];
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
        $arguments['validClasses'] = self::fastmerge(
            $arguments['validClasses'],
            ['slack']
        );
    }
}
