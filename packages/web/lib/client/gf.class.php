<?php
/**
 * Handles GreenFog, now only for legacy client
 *
 * PHP version 5
 *
 * @category Greenfog
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Handles GreenFog, now only for legacy client
 *
 * @category Greenfog
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class GF extends FOGClient implements FOGClientSend
{
    /**
     * Module associated shortname
     *
     * @var string
     */
    public $shortName = 'greenfog';
    /**
     * Creates the send string and stores to send variable
     *
     * @return void
     */
    public function send()
    {
        $gfcount = self::getClass('GreenFogManager')
            ->count();
        if ($gfcount < 1) {
            throw new Exception('#!na');
        }
        $Send = array();
        foreach ((array)self::getClass('GreenFogManager')
            ->find() as $index => &$gf
        ) {
            $actionTemp = $gf->get('action');
            $actionTemp = strtolower($actionTemp);
            $actionTemp = trim($actionTemp);
            $action = '';
            switch ($actionTemp) {
            case 's':
                $action = 'shutdown';
                break;
            case 'r':
                $action = 'reboot';
                break;
            }
            if (empty($action)) {
                continue;
            }
            $val = sprintf(
                '%d@%d@%s',
                $gf->get('hour'),
                $gf->get('min'),
                $action
            );
            $Send[$index] = sprintf(
                "%s\n",
                base64_encode($val)
            );
        }
        $this->send = implode($Send);
    }
}
