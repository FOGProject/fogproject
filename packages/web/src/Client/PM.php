<?php
/**
 * Powermanagement Client information
 *
 * PHP version 7.4+
 *
 * @category Powermanagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Client;

use FOG\Assign\Resolver;
use FOG\Router\Route;

/**
 * Powermanagement Client information
 *
 * @category Powermanagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PM extends FOGClient
{
    /**
     * Module associated shortname
     *
     * @var string
     */
    public $shortName = 'powermanagement';
    /**
     * Sends the powermanagement stuff in json format
     *
     * @return array
     */
    public function json()
    {
        $find = [
            'id' => self::$Host->get('powermanagementtasks'),
            'onDemand' => [1]
        ];
        $actions = Route::getIds(
            'powermanagement',
            $find,
            'action'
        );
        $action = '';
        if (in_array('shutdown', $actions)) {
            $action = 'shutdown';
        } elseif (in_array('reboot', $actions)) {
            $action = 'restart';
        }
        Route::deletemass(
            'powermanagement',
            [
                'onDemand' => [1],
                'hostID' => self::$Host->get('id'),
                'action' => ['shutdown', 'reboot']
            ]
        );
        // ADR 0038: the schedules come from the resolver, so a host gets its
        // own rows AND every schedule granted by a group it belongs to. The
        // on-demand half above stays a direct read: it is a task the client
        // consumes and deletes, not a standing statement about the host, and
        // it has no group-granted counterpart to union with.
        //
        // `wol` is dropped here and only here. The resolver returns every
        // action because TaskScheduler needs the wake ones -- a sleeping
        // machine cannot ask for anything, so the server sends the packet --
        // but handing `wol` to a running client would schedule it to wake
        // itself, which is either a no-op or, on a machine that suspends
        // between now and then, a cron the client can no longer run.
        $hostID = (int)self::$Host->get('id');
        $resolved = Resolver::resolvePowerManagement([$hostID]);
        $data = [
            'onDemand' => $action,
            'tasks' => [],
        ];
        foreach ($resolved[$hostID] ?? [] as $schedule) {
            if ('wol' === $schedule['action']) {
                continue;
            }
            $data['tasks'][] = $schedule;
        }
        return $data;
    }
}
