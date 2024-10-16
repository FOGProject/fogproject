<?php
/**
 * Registers mac's to the host.
 * If using the new client can also register new hosts
 * into a pending status.
 *
 * PHP version 5
 *
 * @category RegisterClient
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Registers mac's to the host.
 * If using the new client can also register new hosts
 * into a pending status.
 *
 * @category RegisterClient
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class RegisterClient extends FOGClient
{
    /**
     * Module associated shortname
     *
     * @var string
     */
    public $shortName = 'hostregister';

    /**
     * Function returns data that will be translated to json
     *
     * @return array
     * @throws Exception
     */
    public function json()
    {
        $MACs = self::getHostItem(
            true,
            false,
            false,
            true
        );
        $keys = [
            'FOG_ENFORCE_HOST_CHANGES',
            'FOG_QUICKREG_MAX_PENDING_MACS'
        ];
        list(
            $enforce,
            $maxPending
        ) = self::getSetting($keys);
        $hostname = filter_input(INPUT_POST, 'hostname');
        if (!$hostname) {
            $hostname = filter_input(INPUT_GET, 'hostname');
        }
        $find = [
            'hostID' => self::$Host->get('id'),
            'pending' => [1]
        ];
        Route::ids(
            'macaddressassociation',
            $find,
            'mac'
        );
        $pendingMACcount = count(json_decode(Route::getData(), true) ?: []);
        if (!self::$Host->isValid()) {
            self::$Host = self::getClass(
                'Host',
                ['name' => $hostname]
            )->load('name');
            if (!(self::$Host->isValid() && !self::$Host->get('pending'))) {
                if (!self::getClass('Host')->isHostnameSafe($hostname)) {
                    if (!self::$json) {
                        echo '#!ih';
                        exit;
                    }
                    return ['error' => 'ih'];
                }
                $PriMAC = array_shift($MACs);
                $find = ['isDefault' => 1];
                Route::ids(
                    'module',
                    $find
                );
                $modules = json_decode(
                    Route::getData(),
                    true
                );
                self::$Host = self::getClass('Host')
                    ->set('name', $hostname)
                    ->set(
                        'description',
                        _('Pending Registration created by FOG_CLIENT')
                    )
                    ->set('pending', "1")
                    ->set('enforce', (string)$enforce)
                    ->set('modules', $modules)
                    ->addPriMAC($PriMAC)
                    ->addMAC($MACs);
                if (!self::$Host->save()) {
                    return ['error' => 'db'];
                }
                return ['complete' => true];
            }
        }
        if ($pendingMACcount > $maxPending) {
            return [
                'error' => sprintf(
                    '%s. %s %d %s.',
                    _('Too many MACs'),
                    _('Only allowed to have'),
                    $maxPending,
                    _('additional macs')
                )
            ];
        }
        $MACs = self::parseMacList(
            $MACs,
            false,
            true
        );
        $KnownMACs = self::$Host->getMyMacs(false);
        $MACs = array_unique(
            array_diff(
                $MACs,
                (array)$KnownMACs
            )
        );
        $lowerAndTrim = function ($element) {
            return strtolower(trim($element));
        };
        $MACs = array_map(
            $lowerAndTrim,
            $MACs
        );
        if (count($MACs ?: [])) {
            self::$Host->addPendMAC($MACs);
            if (!self::$Host->save()) {
                return ['error' => 'db'];
            }
            return ['complete' => true];
        }
        return ['error' => 'ig'];
    }
}
