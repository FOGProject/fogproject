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
class RegisterClient extends FOGClient implements FOGClientSend
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
     */
    public function json()
    {
        $maxPending = 0;
        $MACs = self::getHostItem(
            true,
            false,
            false,
            true
        );
        list(
            $enforce,
            $maxPending
        ) = self::getSubObjectIDs(
            'Service',
            array(
                'name' => array(
                    'FOG_ENFORCE_HOST_CHANGES',
                    'FOG_QUICKREG_MAX_PENDING_MACS'
                )
            ),
            'value',
            false,
            'AND',
            'name',
            false,
            ''
        );
        $hostname = trim(isset($_REQUEST['hostname']) ? $_REQUEST['hostname'] : '');
        $pendingMACcount = count(self::$Host->get('pendingMACs'));
        if (!self::$Host->isValid()) {
            self::$Host = self::getClass(
                'Host',
                array('name' => $hostname)
            )->load('name');
            if (!(self::$Host->isValid() && !self::$Host->get('pending'))) {
                if (!self::getClass('Host')->isHostnameSafe($hostname)) {
                    if (!self::$json) {
                        echo '#!ih';
                        exit;
                    }
                    return array('error' => 'ih');
                }
                $PriMAC = array_shift($MACs);
                self::$Host = self::getClass('Host')
                    ->set('name', $hostname)
                    ->set(
                        'description',
                        _('Pending Registration created by FOG_CLIENT')
                    )
                    ->set('pending', (string)1)
                    ->set('enforce', (string)$enforce)
                    ->set(
                        'modules',
                        self::getSubObjectIDs(
                            'Module',
                            array('isDefault' => 1)
                        )
                    )
                    ->addPriMAC($PriMAC)
                    ->addAddMAC($MACs);
                if (!self::$Host->save()) {
                    return array('error' => 'db');
                }
                return array('complete' => true);
            }
        }
        if ($pendingMACcount > $maxPending) {
            return array(
                'error' => sprintf(
                    '%s. %s %d %s.',
                    _('Too many MACs'),
                    _('Only allowed to have'),
                    $maxPending,
                    _('additional macs')
                )
            );
        }
        $MACs = self::parseMacList(
            $MACs,
            false,
            true
        );
        $KnownMACs = self::$Host->getMyMacs(false);
        $MACs = array_unique(
            array_diff(
                (array)$MACs,
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
        if (count($MACs)) {
            self::$Host->addPendMAC($MACs);
            if (!self::$Host->save()) {
                return array('error' => 'db');
            }
            return array('complete' => true);
        }
        return array('error' => 'ig');
    }
    /**
     * Creates the send string and stores to send variable
     *
     * @return void
     */
    public function send()
    {
        $maxPending = 0;
        $MACs = self::getHostItem(
            true,
            false,
            true,
            true
        );
        list(
            $enforce,
            $maxPending
        ) = self::getSubObjectIDs(
            'Service',
            array(
                'name' => array(
                    'FOG_ENFORCE_HOST_CHANGES',
                    'FOG_QUICKREG_MAX_PENDING_MACS'
                )
            ),
            'value',
            false,
            'AND',
            'name',
            false,
            ''
        );
        if (count($MACs) > $maxPending + 1) {
            if (self::$json) {
                return array('error' => _('Too many MACs'));
            }
            throw new Exception(_('Too many MACs'));
        }
        $MACs = self::parseMacList(
            $MACs,
            false,
            true
        );
        $KnownMACs = self::$Host->getMyMacs(false);
        $MACs = array_unique(
            array_diff(
                (array)$MACs,
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
        if (count($MACs)) {
            self::$Host->addPendMAC($MACs);
            if (!self::$Host->save()) {
                throw new Exception('#!db');
            }
            throw new Exception('#!ok');
        }
        throw new Exception('#!ig');
    }
}
