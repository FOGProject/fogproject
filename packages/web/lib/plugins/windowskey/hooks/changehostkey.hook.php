<?php
/**
 * Adds the windows key in the image to the host on
 * deploy completion.
 *
 * PHP version 5
 *
 * @category ChangeHostKey
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds the windows key in the image to the host on
 * deploy completion.
 *
 * @category ChangeHostKey
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ChangeHostKey extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'ChangeHostKey';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add the image associated key to the host.';
    /**
     * The active flag (always true but for posterity)
     *
     * @var bool
     */
    public $active = true;
    /**
     * THe node this hook enacts with.
     *
     * @var string
     */
    public $node = 'windowskey';
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
            'HOST_TASKING_COMPLETE',
            [$this, 'changeHostProductKey']
        );
    }
    /**
     * Changes the host's product key
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function changeHostProductKey($arguments)
    {
        if (!$arguments['Task']->isDeploy()) {
            return;
        }
        $find = ['imageID' => $arguments['Task']->getImage()->get('id')];
        Route::ids(
            'windowskeyassociation',
            $find,
            'windowskeyID'
        );
        $windowskeys = json_decode(
            Route::getData(),
            true
        );

        $cnt = count($values ?: []);
        if ($cnt !== 1) {
            return;
        }
        $find = ['id' => $windowskeys];
        Route::ids(
            'windowskey',
            $find,
            'key'
        );
        $windowskeys = json_decode(
            Route::getData(),
            true
        );
        $productKey = trim(
            array_shift($windowskeys)
        );
        $arguments['Host']->set('productKey', $productKey)->save();
    }
}
