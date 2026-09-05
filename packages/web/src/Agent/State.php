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
use FOG\Items\HostFactState;
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
        'power' => 'powermanagement',
        // Both halves of the hostnamechanger module, kept apart on the wire
        // because they are different acts with different blast radii: a
        // rename touches this machine, a domain join touches somebody's
        // directory and carries a credential. An admin who has turned the
        // module off has turned off both, which is what they meant.
        'directory' => 'hostnamechanger',
        // Gated on the EXISTING printermanager module, not a new switch:
        // admins have been turning that one off for a decade and know
        // where it is, so a host's current choice carries over untouched
        // (design 0010 section 5).
        'printers' => 'printermanager',
        // Gated on the EXISTING powermanagement module, for the printers
        // reason: that is the switch an admin already turns off to stop
        // FOG touching a machine's power, and relaying a wake is FOG using
        // this machine to touch another one's (design 0011 section 4).
        'wake' => 'powermanagement'
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
        'printers' => PrinterSet::class,
        // The row here is the host's own membership, and the agent
        // addresses it by the only id it knows: its host id. Deliberately
        // an ITEM report and not a shape of its own -- the outer `status`
        // is the capability's applied/failed, and a join has its own
        // vocabulary (joined, refused, unsupported) that needs somewhere to
        // live that is not that field.
        'directory' => DirectoryJoin::class,
        // The row here is another host's pending wake, which is the only
        // item report whose id is NOT the reporting host's own. What makes
        // that safe is the pending row itself: a host may only report on a
        // wake it was actually asked to send, so there is no id it can
        // name that it was not already handed.
        'wake' => WakeRelay::class,
    ];

    /**
     * Capabilities with bytes to fetch for one row: the class's
     * payload(Host, id) checks the row is the host's own and streams it.
     *
     * @var array<string, class-string>
     */
    const PAYLOADS = [
        'snapin' => Snapins::class,
    ];

    /**
     * What the agent may report for one capability.
     */
    const RESULT_STATUSES = ['applied', 'unchanged', 'pending_reboot', 'failed'];

    /**
     * Fact kinds an agent reports about its own host => the class that
     * stores one (design 0006). Facts ride the poll request, not `result`:
     * they are what the host observed, not what it did with a task.
     *
     * A third kind is an entry here and a block in the poll, never a new
     * route -- the route rule (protocol-v1.md).
     *
     * @var array<string, class-string>
     */
    const FACT_REPORTS = [
        'inventory' => InventoryFacts::class,
        'software' => SoftwareFacts::class,
        'directory' => DirectoryFacts::class,
        'printers' => PrinterFacts::class,
        'network' => NetworkFacts::class,
        // The second reporter for hosts.hostSbState (design 0012). iPXE
        // writes the same column on every netboot; this one speaks on
        // every poll, which is what a machine that boots its own disk
        // actually does. Both go through
        // SecureBootState::fromBootRequest() so there is one mapping.
        'secureboot' => SecureBootFacts::class,
    ];

    /**
     * The setting that gates fact collection for the whole install.
     */
    const FACTS_SETTING = 'FOG_AGENT_INVENTORY_ENABLED';

    /**
     * FOG's existing module for user tracking (design 0008). Admins have
     * been switching this off for a decade, so sessions honor it rather
     * than inventing a second switch nobody knows to look at.
     */
    const SESSIONS_MODULE = 'usertracker';

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
        if (in_array('printers', $capabilities, true)) {
            // The resolved printer set and the mode, in words (design 0010
            // section 5). Resolved through the same call PrinterClient
            // makes, so the agent and the legacy client cannot be told
            // different things about one host. Results do not touch it, so
            // a reporting host does not move its own revision.
            $state['printers'] = PrinterSet::desired($Host);
        }
        if (in_array('directory', $capabilities, true)) {
            // Design 0009 section 6, and the only block that ever carries a
            // credential. Null for nearly every host, and every reason for
            // null is a reason not to put a join account on a machine --
            // see DirectoryJoin::desired(). Omitted entirely when null so
            // the wire says nothing rather than saying "no credential".
            $directory = DirectoryJoin::desired($Host);
            if (null !== $directory) {
                $state['directory'] = $directory;
            }
        }
        if (in_array('wake', $capabilities, true)) {
            // Design 0011: FOG hosts on this machine's own links that are
            // waiting to be woken. Null for essentially every host on
            // essentially every poll -- a wake is rare and pending for
            // minutes -- so the block is omitted entirely rather than
            // sent empty. There is no destination in it: the agent
            // broadcasts on its own interfaces, so it cannot be aimed.
            $wake = WakeRelay::desired($Host);
            if (null !== $wake) {
                $state['wake'] = $wake;
            }
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
     * Stores the fact blocks a poll carried, and answers what is still
     * wanted from this host.
     *
     * The mirror image of desired(): there the server holds a revision and
     * sends state when the agent's is stale; here the server holds a hash
     * per fact kind and asks for a block when it has none. Either way the
     * expensive thing crosses the wire only when it has moved.
     *
     * The hash is computed here rather than taken from the agent. Two
     * reasons: the server must not trust a caller's claim that its content
     * is unchanged, and a hash the server computes is one it can compare
     * against a block it actually received -- which is what lets an
     * identical resend skip the whole reconcile.
     *
     * @param Host  $Host the host the certificate bound
     * @param array $body the poll request
     *
     * @return array want_<kind> => bool, for the poll answer
     */
    public static function facts(Host $Host, array $body)
    {
        // Always stated, never omitted: an agent cannot tell an absent
        // JSON boolean from a false one, and "this server does not want
        // facts" has to reach a host whose admin just turned the setting
        // off. An agent too old to read it keeps sending, which the
        // ignore below handles.
        $answer = ['collect_facts' => self::factsEnabled()];
        if (!$answer['collect_facts']) {
            // Gate off: never ask, and ignore a block that arrives anyway
            // (an agent that was collecting before the setting changed, or
            // one still on its way to hearing about it).
            return $answer;
        }
        $hostID = (int)$Host->get('id');
        foreach (self::FACT_REPORTS as $kind => $class) {
            $stored = self::_factHash($hostID, $kind);
            $block = $body[$kind] ?? null;
            if (is_array($block)) {
                $hash = substr(
                    hash('sha256', (string)json_encode($block)),
                    0,
                    16
                );
                if ($hash !== $stored) {
                    $class::report($Host, $block);
                    self::_setFactHash($hostID, $kind, $hash);
                    $stored = $hash;
                } else {
                    // Same content, so nothing to write but something to
                    // record: this is the host's last known good report.
                    self::_setFactHash($hostID, $kind, $hash);
                }
            }
            $answer['want_' . $kind] = '' === $stored;
        }

        // The acting half of directory membership (design 0009 section 5),
        // and the one place in facts() that does something rather than
        // record something. It runs on every poll rather than off the
        // report above, because a report only happens when the MACHINE's
        // membership moved, and the other source of drift is an admin
        // editing the host's OU -- which no machine will ever report.
        //
        // Under the facts gate on purpose: placement decides what to do from
        // what the host observed, so an install that collects nothing has
        // nothing to decide from.
        DirectoryFacts::place($Host);

        return $answer;
    }

    /**
     * Records the host's reported user sessions.
     *
     * Deliberately not a FACT_REPORTS entry. Facts are hash-gated by the
     * server, and a session set must not be: the open set is also the
     * evidence a session is still alive, so the agent decides when to send
     * and there is no want_sessions. See design 0008 section 4.
     *
     * @param Host  $Host the host the certificate bound
     * @param array $body the poll request
     *
     * @return array collect_sessions => bool, for the poll answer
     */
    public static function sessions(Host $Host, array $body)
    {
        // Always stated, never omitted, for the same reason collect_facts
        // is: an agent cannot tell an absent JSON boolean from a false one.
        $answer = ['collect_sessions' => self::sessionsEnabled($Host)];
        if (!$answer['collect_sessions']) {
            // Gate off: ignore a block that arrives anyway, from an agent
            // that was collecting before the module was switched off.
            return $answer;
        }
        $block = $body['sessions'] ?? null;
        if (is_array($block)) {
            UserSessions::report($Host, $block);
        }
        return $answer;
    }

    /**
     * Whether this host reports user sessions.
     *
     * Both halves of FOG's module gate, the same way capabilities() reads
     * it: the global switch and the host's resolved module list.
     *
     * @param Host $Host the principal
     *
     * @return bool
     */
    public static function sessionsEnabled(Host $Host)
    {
        $global = self::getGlobalModuleStatus();
        if (empty($global[self::SESSIONS_MODULE])) {
            return false;
        }
        $on = (array)Route::getIds(
            'module',
            ['id' => $Host->resolvedModules()],
            'shortName'
        );
        return in_array(self::SESSIONS_MODULE, $on, true);
    }

    /**
     * Whether this install collects facts at all.
     *
     * @return bool
     */
    public static function factsEnabled()
    {
        return (bool)self::getSetting(self::FACTS_SETTING);
    }

    /**
     * The hash the server holds for one host and fact kind.
     *
     * @param int    $hostID the host
     * @param string $kind   the fact kind
     *
     * @return string the stored hash, '' when there is no row
     */
    private static function _factHash($hostID, $kind)
    {
        $id = self::_factStateID($hostID, $kind);
        if (0 === $id) {
            return '';
        }

        return (string)(new HostFactState($id))->get('hash');
    }

    /**
     * The hostFactState row id for one host and fact kind, 0 for none.
     *
     * @param int    $hostID the host
     * @param string $kind   the fact kind
     *
     * @return int
     */
    private static function _factStateID($hostID, $kind)
    {
        $ids = Route::getIds(
            'hostfactstate',
            ['hostID' => (int)$hostID, 'kind' => $kind],
            'id'
        );

        return (int)(array_shift($ids) ?: 0);
    }

    /**
     * Records the hash and the time for one host and fact kind.
     *
     * @param int    $hostID the host
     * @param string $kind   the fact kind
     * @param string $hash   the hash of the block just stored
     *
     * @return void
     */
    private static function _setFactHash($hostID, $kind, $hash)
    {
        (new HostFactState(self::_factStateID($hostID, $kind)))
            ->set('hostID', (int)$hostID)
            ->set('kind', $kind)
            ->set('hash', $hash)
            ->set('updated', self::niceDate()->format('Y-m-d H:i:s'))
            ->save();
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
