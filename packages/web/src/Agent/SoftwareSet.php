<?php
/**
 * Software for an enrolled agent: the desired set and the reports.
 *
 * PHP version 7.4+
 *
 * @category SoftwareSet
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
use FOG\Items\Software;
use FOG\Items\SoftwareStatus;
use FOG\Router\Route;

/**
 * The software capability (design 0003): a desired set of packages the
 * host is held to by a package manager, reported back with the version
 * the host actually has. Unlike a snapin nothing here is a task: the set
 * is read fresh on every state fetch, the agent converges it, and a
 * report refreshes one status row per host and entry.
 *
 * @category SoftwareSet
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SoftwareSet extends FOGBase
{
    /**
     * The agent's statuses. The four action statuses carry an exit code
     * the server reads against the entry's return-code table; converged
     * means nothing needed doing; the last two mean the backend never
     * ran the action.
     */
    const STATUS_CONVERGED = 'converged';
    const ACTION_STATUSES = ['installed', 'upgraded', 'removed'];
    const STATUSES = [
        'converged', 'installed', 'upgraded', 'removed', 'timeout', 'cannot_run'
    ];

    /**
     * The snapin defaults plus Chocolatey's own "pending reboot detected"
     * code, which it answers before touching anything.
     */
    const DEFAULT_RETURN_CODES = [
        0 => 'success',
        1707 => 'success',
        3010 => 'reboot',
        1641 => 'reboot',
        350 => 'reboot',
        1618 => 'retry'
    ];

    /**
     * The feature token an agent declares when it honors the `backend`
     * field of every software entry (fog-agent 0.1.22 and later).
     *
     * @var string
     */
    const FEATURE = 'software-backends-v1';

    /**
     * The host's software management level (`hosts.hostSoftwareLevel`).
     * '' is Assigned: install and remove only what the host gets. Exclusive
     * also has the agent uninstall every package its software backends
     * installed that the host does not get. Per host, never a group grant
     * (Resolver::resolvePrinterLevel() gives the reason).
     */
    const LEVEL_EXCLUSIVE = 'exclusive';
    const LEVELS = ['', self::LEVEL_EXCLUSIVE];

    /**
     * Reads a posted software level: the trimmed level, or null when it is
     * not one a host can be set to. A missing value (null or false, which
     * filter_input() returns) is not one.
     *
     * @param mixed $raw the posted level
     *
     * @return string|null one of LEVELS, or null
     */
    public static function parseLevel($raw)
    {
        if (!is_string($raw)) {
            return null;
        }
        $raw = trim($raw);
        return in_array($raw, self::LEVELS, true) ? $raw : null;
    }

    /**
     * parseLevel() for a write: the one refusal both write paths (the
     * Software tab POST and mass edit) use, so neither can store a value
     * the agent would read differently from the page.
     *
     * @param mixed $raw the posted level
     *
     * @throws \Exception when the value is not one of LEVELS
     * @return string one of LEVELS
     */
    public static function requireLevel($raw)
    {
        $level = self::parseLevel($raw);
        if (null === $level) {
            throw new \Exception(_('Please select a software level!'));
        }
        return $level;
    }

    /**
     * The level a host runs at: 'exclusive' or ''. The one reading the
     * agent's desired set, the level card and the grid all use, so a stored
     * value such as 'exclusive ' cannot mean different things to them.
     *
     * @param Host $Host the host
     *
     * @return string one of LEVELS
     */
    public static function levelOf(Host $Host)
    {
        return self::parseLevel((string)$Host->get('softwareLevel')) ?? '';
    }

    /**
     * Splits a host's resolved links into what it gets and what an
     * Exclusive host must retain.
     *
     * retain is the disabled entries, plus the enabled entries sendable()
     * drops for this request. Neither is sent as an entry, and the agent
     * must never remove a package FOG still assigns. gets is the rest, in
     * run order.
     *
     * @param array $links       Resolver::resolveSoftwareDetail() rows
     * @param array $software    softwareID => ['backend', 'package',
     *                           'enabled']; a link with no row is skipped
     * @param bool  $allBackends whether the agent declared the feature; the
     *                           preview has no request and passes true
     *
     * @return array [gets, retain], each [['id', 'backend', 'package'], ...]
     */
    public static function splitResolved(
        array $links,
        array $software,
        $allBackends
    ) {
        $gets = [];
        $retain = [];
        foreach ($links as $link) {
            $id = (int)$link['id'];
            if (!isset($software[$id])) {
                continue;
            }
            $row = [
                'id' => $id,
                'backend' => (string)$software[$id]['backend'],
                'package' => (string)$software[$id]['package']
            ];
            if ($software[$id]['enabled']
                && self::sendable($row['backend'], $allBackends)
            ) {
                $gets[] = $row;
            } else {
                $retain[] = $row;
            }
        }
        return [$gets, $retain];
    }

    /**
     * Did the request declare the software backends feature?
     *
     * @param array $body the decoded JSON body
     *
     * @return bool
     */
    public static function requested(array $body)
    {
        return in_array(self::FEATURE, (array)($body['features'] ?? []), true);
    }

    /**
     * Is an entry with this backend sent to an agent?
     *
     * An agent that does not declare the feature (0.1.21 and earlier) runs
     * every entry through Chocolatey whatever its backend says, and such
     * agents stay in the field while a ring delays them or an admin has
     * turned self-update off. A Snap entry would then run as a Chocolatey
     * command. So those agents
     * get Chocolatey entries only; an empty backend means Chocolatey.
     *
     * @param string $backend     the entry's backend
     * @param bool   $allBackends whether the agent declared the feature
     *
     * @return bool
     */
    public static function sendable($backend, $allBackends)
    {
        return $allBackends
            || Software::DEFAULT_BACKEND === ((string)$backend ?: Software::DEFAULT_BACKEND);
    }

    /**
     * The desired set for a host, in run order, with the drift interval.
     * Disabled entries are left out rather than sent as absent: turning
     * an entry off stops managing the package, it does not remove it.
     * Entries for a backend the agent cannot honor are left out too (see
     * sendable()).
     *
     * @param Host $Host        the principal
     * @param bool $allBackends whether the agent declared the feature
     *
     * @return array
     */
    public static function desired(Host $Host, $allBackends = false)
    {
        $hostID = (int)$Host->get('id');
        $links = Resolver::resolveSoftwareDetail([$hostID])[$hostID] ?? [];
        $entries = [];
        $rows = [];
        $loaded = [];
        $byID = [];
        foreach ($links as $link) {
            $Software = new Software((int)$link['id']);
            if (!$Software->isValid()) {
                continue;
            }
            $loaded[(int)$Software->get('id')] = $Software;
            $byID[(int)$Software->get('id')] = $link;
            $rows[(int)$Software->get('id')] = [
                'backend' => (string)$Software->get('backend'),
                'package' => (string)$Software->get('package'),
                'enabled' => (bool)$Software->get('isEnabled')
            ];
        }
        list($gets, $retained) = self::splitResolved($links, $rows, $allBackends);
        $retain = [];
        foreach ($retained as $row) {
            $retain[] = [
                'backend' => $row['backend'],
                'package' => $row['package']
            ];
        }
        foreach ($gets as $row) {
            $Software = $loaded[$row['id']];
            $link = $byID[$row['id']];
            $entries[] = [
                'id' => (int)$Software->get('id'),
                'backend' => (string)$Software->get('backend'),
                'package' => (string)$Software->get('package'),
                'version' => (string)$Software->get('version'),
                // The link's override when it has one, else the entry's
                // own state (GH-2129).
                'state' => $link['effective'],
                'source' => (string)$Software->get('source'),
                'args' => (string)$Software->get('args'),
                'timeout' => (int)$Software->get('timeout'),
                'stop_snapins' => (bool)$Software->get('stopSnapins')
            ];
        }
        $desired = [
            'drift_interval' => (int)self::getSetting('FOG_SOFTWARE_DRIFT_INTERVAL'),
            // Empty url = no bootstrap; the agent then reports cannot_run
            // for a host without Chocolatey (design 0003 section 8).
            'bootstrap' => [
                'url' => trim((string)self::getSetting('FOG_SOFTWARE_CHOCO_BOOTSTRAP_URL')),
                'nupkg_url' => trim((string)self::getSetting('FOG_SOFTWARE_CHOCO_NUPKG_URL'))
            ],
            'entries' => $entries
        ];
        // Only an Exclusive host carries the key, so the state revision of
        // every other host does not change.
        if (self::LEVEL_EXCLUSIVE === self::levelOf($Host)) {
            $desired['manage'] = self::LEVEL_EXCLUSIVE;
            // The packages of entries that are not sent (disabled, or a
            // backend this agent cannot honor), which the agent must not
            // uninstall. Omitted when empty.
            if (count($retain) > 0) {
                $desired['retain'] = $retain;
            }
        }
        return $desired;
    }

    /**
     * The entry's outcome for a report: the same reading as a snapin's,
     * with converged always a success and a backend that never ran the
     * action always a failure.
     *
     * @param Software $Software the entry
     * @param string   $status   the agent's status
     * @param int      $exitcode the backend's exit code
     *
     * @return string one of Snapins::OUTCOMES
     */
    public static function outcome(Software $Software, $status, $exitcode)
    {
        if (self::STATUS_CONVERGED === $status) {
            return Snapins::OUTCOME_SUCCESS;
        }
        if (!in_array($status, self::ACTION_STATUSES, true)) {
            return Snapins::OUTCOME_FAILED;
        }
        $table = Snapins::parseReturnCodes(
            (string)$Software->get('returnCodes'),
            self::DEFAULT_RETURN_CODES
        );
        $exitcode = (int)$exitcode;
        if (isset($table[$exitcode])) {
            return $table[$exitcode];
        }
        return 0 === $exitcode ? Snapins::OUTCOME_SUCCESS : Snapins::OUTCOME_FAILED;
    }

    /**
     * Records one entry's report on the host and answers the outcome.
     *
     * @param Host  $Host       the principal
     * @param int   $softwareID the entry
     * @param array $body       status, installed_version, exit_code, details
     *
     * @throws \RuntimeException 404 for an entry not in the host's set,
     *                           400 for an unknown status
     *
     * @return string the outcome
     */
    public static function report(Host $Host, $softwareID, array $body)
    {
        $hostID = (int)$Host->get('id');
        $softwareID = (int)$softwareID;
        $set = Resolver::resolveSoftware([$hostID])[$hostID] ?? [];
        if (!in_array($softwareID, $set, true)) {
            throw new \RuntimeException('not in this host\'s software set', 404);
        }
        $Software = new Software($softwareID);
        if (!$Software->isValid()) {
            throw new \RuntimeException('no such software', 404);
        }
        $status = (string)($body['status'] ?? '');
        if (!in_array($status, self::STATUSES, true)) {
            throw new \RuntimeException('unknown status', 400);
        }
        $exitcode = (int)($body['exit_code'] ?? 0);
        $version = substr(trim((string)($body['installed_version'] ?? '')), 0, 64);
        $details = substr(trim((string)($body['details'] ?? '')), 0, Snapins::MAX_DETAILS);
        $outcome = self::outcome($Software, $status, $exitcode);
        // What the row keeps: the action word when it succeeded, else the
        // outcome, else the never-ran status verbatim. Same rule as
        // snapinTasks.stStatus so the two histories read alike.
        $recorded = $status;
        if (in_array($status, self::ACTION_STATUSES, true)
            && Snapins::OUTCOME_SUCCESS !== $outcome
        ) {
            $recorded = $outcome;
        }
        $ids = Route::getIds(
            'softwarestatus',
            ['hostID' => $hostID, 'softwareID' => $softwareID]
        );
        $Status = new SoftwareStatus((int)($ids[0] ?? 0));
        $Status
            ->set('hostID', $hostID)
            ->set('softwareID', $softwareID)
            ->set('installedVersion', $version)
            ->set('status', $recorded)
            ->set('return', $exitcode)
            ->set('details', $details)
            ->set('checked', self::niceDate()->format('Y-m-d H:i:s'))
            ->save();
        // A converged heartbeat is not worth an audit row; an action or a
        // failure is.
        if (self::STATUS_CONVERGED !== $status) {
            Audit::record(
                [
                    'type' => 'agent.result',
                    'subjectType' => 'host',
                    'subjectID' => $hostID,
                    'subjectLabel' => (string)$Host->get('name'),
                    'renderable' => 1,
                    'text' => substr(
                        sprintf(
                            'software "%s" (%s) %s, exit %d, %s%s%s',
                            (string)$Software->get('name'),
                            (string)$Software->get('package'),
                            $status,
                            $exitcode,
                            $outcome,
                            '' === $version ? '' : ', installed ' . $version,
                            '' === $details ? '' : ': ' . $details
                        ),
                        0,
                        Audit::MAX_DETAIL
                    ),
                    'authSource' => Principal::AUTH_SOURCE
                ]
            );
        }
        return $outcome;
    }
}
