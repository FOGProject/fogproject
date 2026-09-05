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
     * The desired set for a host, in run order, with the drift interval.
     * Disabled entries are left out rather than sent as absent: turning
     * an entry off stops managing the package, it does not remove it.
     *
     * @param Host $Host the principal
     *
     * @return array
     */
    public static function desired(Host $Host)
    {
        $hostID = (int)$Host->get('id');
        $ids = Resolver::resolveSoftware([$hostID])[$hostID] ?? [];
        $entries = [];
        foreach ($ids as $id) {
            $Software = new Software((int)$id);
            if (!$Software->isValid() || !$Software->get('isEnabled')) {
                continue;
            }
            $entries[] = [
                'id' => (int)$Software->get('id'),
                'backend' => (string)$Software->get('backend'),
                'package' => (string)$Software->get('package'),
                'version' => (string)$Software->get('version'),
                'state' => (string)$Software->get('state'),
                'source' => (string)$Software->get('source'),
                'args' => (string)$Software->get('args'),
                'timeout' => (int)$Software->get('timeout')
            ];
        }
        return [
            'drift_interval' => (int)self::getSetting('FOG_SOFTWARE_DRIFT_INTERVAL'),
            // Empty url = no bootstrap; the agent then reports cannot_run
            // for a host without Chocolatey (design 0003 section 8).
            'bootstrap' => [
                'url' => trim((string)self::getSetting('FOG_SOFTWARE_CHOCO_BOOTSTRAP_URL')),
                'nupkg_url' => trim((string)self::getSetting('FOG_SOFTWARE_CHOCO_NUPKG_URL'))
            ],
            'entries' => $entries
        ];
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
