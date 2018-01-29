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
 * @author   Lee Rowlett <nah@nah.com>
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
 * @author   Lee Rowlett <nah@nah.com>
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
        self::$HookManager
            ->register(
                'HOST_TASKING_COMPLETE',
                array(
                    $this,
                    'changeHostProductKey'
                )
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
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if (!$arguments['Task']->isDeploy()) {
            return;
        }
        $WindowsKey = self::getSubObjectIDs(
            'WindowsKeyAssociation',
            array(
                'imageID' => $arguments['Task']
                    ->getImage()
                    ->get('id')
            ),
            'windowskeyID'
        );
        $cnt = self::getClass('WindowsKeyManager')->count(
            array(
                'id' => $WindowsKey
            )
        );
        if ($cnt !== 1) {
            return;
        }
        $WindowsKey = self::getSubObjectIDs(
            'WindowsKey',
            array('id' => $WindowsKey),
            'key'
        );
        $productKey = trim(
            array_shift($WindowsKey)
        );
        $arguments['Host']
            ->set(
                'productKey',
                $productKey
            )->save();
    }
}
