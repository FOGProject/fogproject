<?php
/**
 * Desired state for an enrolled agent, and the results it reports back.
 *
 * PHP version 7.4+
 *
 * @category State
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Agent;

use FOG\Assign\Resolver;
use FOG\Audit\Audit;
use FOG\Base\FOGBase;
use FOG\Items\Host;
use FOG\Items\PowerManagement;
use FOG\Router\Route;

/**
 * The convergence half of the protocol (design 0001 section 2): the server
 * holds a desired state per host, the agent fetches it when the revision
 * moves, reconciles, and reports.
 *
 * A capability is listed for a host when its legacy module is on for that
 * host -- the global FOG_CLIENT_*_ENABLED setting and the host's resolved
 * module set, exactly the two checks FOGClient makes for the old client --
 * so an admin's existing per-host and per-group module choices carry over
 * unchanged. Desired state is built only for the capabilities listed, and
 * the revision is a digest of that state, so "anything changed?" costs the
 * poll one string compare.
 *
 * Results are audit rows for now (renderable, on the host): the place FOG
 * already shows what happened to a host, and no schema until inventory
 * needs one.
 *
 * @category State
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class State extends FOGBase
{
    /**
     * Capability name => the legacy module short name that switches it.
     */
    const CAPABILITIES = [
        'hostname' => 'hostnamechanger',
        'taskreboot' => 'taskreboot',
        'snapin' => 'snapinclient',
        'software' => 'software',
        'power' => 'powermanagement'
    ];

    /**
     * Results the agent reports that are not a capability of their own:
     * the reboot coordinator's decisions (design 0001 section 6).
     */
    const RESULT_SOURCES = ['reboot'];

    /**
     * Capabilities whose reports can address one server-owned row: the
     * class's report(Host, id, body) keeps the row, reads the exit code
     * against its return-code table and answers the outcome the agent
     * acts on.
     *
     * @var array<string, class-string>
     */
    const ITEM_REPORTS = [
        'snapin' => Snapins::class,
        'software' => SoftwareSet::class,
    ];

    /**
     * What the agent may report for one capability.
     */
    const RESULT_STATUSES = ['applied', 'unchanged', 'pending_reboot', 'failed'];

    /**
     * The capabilities this server offers this host.
     *
     * @param Host $Host the principal
     *
     * @return array capability names, in CAPABILITIES order
     */
    public static function capabilities(Host $Host)
    {
        $global = self::getGlobalModuleStatus();
        $on = (array)Route::getIds(
            'module',
            ['id' => $Host->resolvedModules()],
            'shortName'
        );
        $out = [];
        foreach (self::CAPABILITIES as $capability => $shortName) {
            if (!empty($global[$shortName]) && in_array($shortName, $on, true)) {
                $out[] = $capability;
            }
        }
        return $out;
    }

    /**
     * The desired state, with its revision.
     *
     * @param Host $Host the principal
     *
     * @return array
     */
    public static function desired(Host $Host)
    {
        $capabilities = self::capabilities($Host);
        $state = ['capabilities' => $capabilities];
        if (in_array('hostname', $capabilities, true)) {
            $state['hostname'] = [
                'name' => (string)$Host->get('name'),
                // The host's "Enforce Hostname | AD Join Reboots" flag: may
                // the agent reboot to finish a rename. The agent's reboot
                // coordinator owns the when; this is only the permission.
                'enforce' => (bool)$Host->get('enforce')
            ];
        }
        if (in_array('taskreboot', $capabilities, true)) {
            // What Client\Jobs answers the old client: a task in a state
            // that needs the machine to boot into FOS. Present only while
            // one waits, so queueing or canceling a task moves the
            // revision and the agent fetches the change on its next poll.
            $Task = $Host->get('task');
            $state['task'] = null;
            if ($Task->isValid() && $Task->isInitNeededTasking()) {
                $state['task'] = [
                    'id' => (int)$Task->get('id'),
                    'type' => (string)$Task->getTaskTypeText(),
                    // FOG_TASK_FORCE_REBOOT: reboot for the task even
                    // with users logged in.
                    'force' => (bool)self::getSetting('FOG_TASK_FORCE_REBOOT')
                ];
            }
        }
        if (in_array('snapin', $capabilities, true)) {
            // The host's snapin queue in run order (Agent\Snapins). Tasks
            // leave it as they complete, so the revision moves with the
            // queue and an empty list is the resting state.
            $state['snapins'] = Snapins::queue($Host);
        }
        if (in_array('software', $capabilities, true)) {
            // The desired package set in run order with the drift
            // interval (Agent\SoftwareSet). Status reports do not touch
            // it, so a reporting host does not move its own revision.
            $state['software'] = SoftwareSet::desired($Host);
        }
        if (in_array('power', $capabilities, true)) {
            // Design 0004. Schedules are what Client\PM hands the legacy
            // client: the host's own rows and its groups' grants through
            // the resolver, minus `wol`, which the server sends itself
            // (TaskScheduler) since a sleeping machine cannot ask. The
            // agent fires them with its own cron matcher. On-demand rows
            // are the task half: present until the agent reports it
            // accepted them (result(), below), so an admin's click moves
            // the revision and the agent fetches it on its next poll.
            $hostID = (int)$Host->get('id');
            $resolved = Resolver::resolvePowerManagement([$hostID]);
            $schedules = [];
            foreach ($resolved[$hostID] ?? [] as $schedule) {
                if ('wol' === $schedule['action']) {
                    continue;
                }
                $schedules[] = [
                    'cron' => (string)$schedule['cron'],
                    'action' => (string)$schedule['action']
                ];
            }
            $ondemand = [];
            foreach (self::_ondemand($hostID) as $row) {
                $ondemand[] = [
                    'id' => (int)$row['pmID'],
                    'action' => (string)$row['pmAction']
                ];
            }
            $state['power'] = [
                'schedules' => $schedules,
                'ondemand' => $ondemand
            ];
        }
        if (count($capabilities) > 0) {
            // The policy every reboot obeys, whatever asked for it:
            // FOG_GRACE_TIMEOUT is the warning logged-in users get.
            $state['reboot'] = [
                'grace' => (int)self::getSetting('FOG_GRACE_TIMEOUT')
            ];
        }
        $state['revision'] = self::revision($state);
        return $state;
    }

    /**
     * The revision of a desired state: a digest of everything but itself.
     *
     * @param array $state the desired state, revision ignored
     *
     * @return string 16 hex characters
     */
    public static function revision(array $state)
    {
        unset($state['revision']);
        ksort($state);
        return substr(hash('sha256', (string)json_encode($state)), 0, 16);
    }

    /**
     * Records what the agent did with one capability.
     *
     * @param Host  $Host the principal
     * @param array $body revision, capability, status, detail, and
     *                    optionally item (id plus what the capability's
     *                    report class reads)
     *
     * @throws \RuntimeException 400 on a body that is not a result; an
     *                           item report's own codes (404, 409, 503)
     *
     * @return string|null the outcome of an item report, else null
     */
    public static function result(Host $Host, array $body)
    {
        $capability = (string)($body['capability'] ?? '');
        if (!isset(self::CAPABILITIES[$capability])
            && !in_array($capability, self::RESULT_SOURCES, true)
        ) {
            throw new \RuntimeException('unknown capability', 400);
        }
        $status = (string)($body['status'] ?? '');
        if (!in_array($status, self::RESULT_STATUSES, true)) {
            throw new \RuntimeException('unknown status', 400);
        }
        // A report about one thing under the capability goes to that
        // capability's report class, which keeps the row and answers the
        // outcome. One route for every kind of report: a new artifact
        // type is a new entry here, never a new path (protocol-v1.md).
        $item = $body['item'] ?? null;
        if (is_array($item)) {
            $class = self::ITEM_REPORTS[$capability] ?? null;
            if (null === $class) {
                throw new \RuntimeException('capability has no item reports', 400);
            }
            return (string)$class::report($Host, (int)($item['id'] ?? 0), $item);
        }
        $revision = substr(preg_replace('/[^a-f0-9]/', '', (string)($body['revision'] ?? '')), 0, 16);
        $detail = substr(trim((string)($body['detail'] ?? '')), 0, Audit::MAX_DETAIL);
        if ('power' === $capability && 'applied' === $status) {
            // The agent accepted the host's on-demand actions: they are
            // consumed, the way Client\PM consumes them on read for the
            // legacy client, except here only once the agent has them.
            self::_consumeOndemand((int)$Host->get('id'));
        }
        Audit::record(
            [
                'type' => 'agent.result',
                'subjectType' => 'host',
                'subjectID' => (int)$Host->get('id'),
                'subjectLabel' => (string)$Host->get('name'),
                'renderable' => 1,
                'text' => sprintf(
                    '%s %s at revision %s%s',
                    $capability,
                    $status,
                    $revision,
                    '' === $detail ? '' : ': ' . $detail
                ),
                'authSource' => Principal::AUTH_SOURCE
            ]
        );
        return null;
    }

    /**
     * The host's pending on-demand power rows: an admin's "shutdown now"
     * or "reboot now" from the host list, stored as powerManagement rows
     * with pmOndemand = 1 and no cron fields.
     *
     * @param int $hostID the host
     *
     * @return array rows with pmID and pmAction
     */
    private static function _ondemand($hostID)
    {
        $out = [];
        $find = [
            'hostID' => (int)$hostID,
            'onDemand' => 1,
            'action' => ['shutdown', 'reboot']
        ];
        foreach (Route::getIds('powermanagement', $find, 'id') as $id) {
            $PM = new PowerManagement((int)$id);
            $out[] = [
                'pmID' => (int)$PM->get('id'),
                'pmAction' => (string)$PM->get('action')
            ];
        }
        return $out;
    }

    /**
     * Deletes the host's on-demand power rows once the agent has them.
     *
     * @param int $hostID the host
     *
     * @return void
     */
    private static function _consumeOndemand($hostID)
    {
        Route::deletemass(
            'powermanagement',
            [
                'onDemand' => [1],
                'hostID' => (int)$hostID,
                'action' => ['shutdown', 'reboot']
            ]
        );
    }
}
