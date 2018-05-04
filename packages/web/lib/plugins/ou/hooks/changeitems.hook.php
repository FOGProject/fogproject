<?php
/**
 * Changes the elements we need.
 *
 * PHP version 5
 *
 * @category ChangeItems
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Changes the elements we need.
 *
 * @category ChangeItems
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ChangeItems extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'ChangeItems';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add OU During client checkin';
    /**
     * The active flag.
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node this hook enacts with.
     *
     * @var string
     */
    public $node = 'ou';
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
            'HOSTNAME_CHANGER_CLIENT',
            [$this, 'changeADItems']
        );
    }
    /**
     * Sets up host for the new OU
     *
     * @param mixed $arguments The items to change.
     *
     * @return void
     */
    public function changeADItems($arguments)
    {
        if (!$arguments['Host']->isValid()) {
            return;
        }
        Route::listem(
            'ouassociation',
            ['hostID' => $arguments['Host']->get('id')]
        );
        $OUAssocs = json_decode(
            Route::getData()
        );
        foreach ($OUAssocs as &$OUAssoc) {
            Route::indiv('ou', $OUAssoc->ouID);
            $OU = json_decode(
                Route::getData()
            );
            $arguments['val']['ADOU'] = $OU->ou;
            unset($OUAssoc);
        }
    }
}
