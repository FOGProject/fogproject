<?php
/**
 * Where the fleet actually is, against where it is supposed to be.
 *
 * PHP version 7.4+
 *
 * @category Directory_Membership
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Reports;

use FOG\Items\HostDirectory;
use FOG\Pages\ReportManagement;

/**
 * Where the fleet actually is, against where it is supposed to be.
 *
 * The report FOG has never been able to produce. `hostADDomain` and
 * `hostADOU` are intent -- what an admin typed into a form -- and until
 * design 0009 nothing recorded the other half, so "which of my machines are
 * not where I think they are" had no answer at any price.
 *
 * It matters most for the OU. The legacy client never compares one: it
 * short-circuits on "already joined to the target domain" and reads the OU
 * only as `lpAccountOU` at the initial join, so editing a host's OU does
 * nothing, forever, with no error anywhere. Every host that was moved in the
 * directory by hand, or registered before its OU default was set, is in this
 * report and has never been visible before.
 *
 * A FLEET SNAPSHOT, not a history -- the same test that puts User_Sessions
 * under Lists rather than in ReportManagement::AGGREGATIONS.
 *
 * GATED ON `host`, like User_Sessions and for the same reason: reports share
 * the `report` node by default (the defect ADR 0023 opens with), and this is
 * host data naming a machine's place in someone's directory.
 *
 * @category Directory_Membership
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Directory_Membership extends ReportManagement
{
    /**
     * The most rows a grid or export will carry back.
     *
     * @var int
     */
    const MAX_ROWS = 5000;
    /**
     * Display page.
     *
     * @return void
     */
    public function file()
    {
        $this->title = self::reportTitle();

        $this->headerData = [
            _('Host'),
            _('Desired domain'),
            _('Observed domain'),
            _('Desired OU'),
            _('Observed OU'),
            _('Drift'),
            _('Placement'),
            _('Join'),
            _('Reported'),
            _('Last check-in')
        ];
        $this->attributes = [
            [], [], [], [], [], [], [], [], [], []
        ];

        $payload = $this->reportRows();

        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';

        printf(
            '<p class="text-muted">%s</p>',
            \Initiator::e(
                _(
                    'Hosts set to use Active Directory, with what each one '
                    . 'last reported about itself. An OU difference is not '
                    . 'corrected by the legacy client, which only reads the '
                    . 'OU when it first joins the machine.'
                )
            )
        );

        echo self::renderReportCap(
            $payload['truncated'],
            self::MAX_ROWS
        );

        $this->render(12, 'directorymembershipreport-table');

        echo '</div>';
        echo '</div>';
    }
    /**
     * The rows this report serves.
     *
     * Split from the emit so the grid and the CSV export run the same query
     * -- see ReportManagement::exportAll().
     *
     * @return array
     */
    protected function reportRows()
    {
        // Every host that is SUPPOSED to be in a directory, left-joined to
        // what it reported. A LEFT JOIN rather than an inner one on purpose:
        // a host set to use AD that has never reported is the most
        // interesting row in the report, and an inner join would hide
        // exactly the machines nobody has heard from.
        $sql = "SELECT `hostID`,
                       `hostName`,
                       `hostADDomain`,
                       `hostADOU`,
                       `hostAgentCheckin`,
                       `hdJoined`,
                       `hdKind`,
                       `hdDomain`,
                       `hdNetbios`,
                       `hdComputerDN`,
                       `hdObservedAt`,
                       `hdPlacementAt`,
                       `hdPlacementError`,
                       `hdJoinAt`,
                       `hdJoinError`
                  FROM `hosts`
                  LEFT OUTER JOIN `hostDirectory` ON `hdHostID` = `hostID`
                 WHERE `hostUseAD` = 1
                 ORDER BY `hostName` ASC
                 LIMIT " . (self::MAX_ROWS + 1);

        $rows = (array)self::$DB->query($sql)
            ->fetch(\PDO::FETCH_ASSOC, 'fetch_all')
            ->get();

        $data = [];
        foreach (array_slice($rows, 0, self::MAX_ROWS) as $row) {
            $reported = null !== ($row['hdObservedAt'] ?? null);
            // Built through the item class so the drift rules live in one
            // place: a report re-implementing the DN comparison would
            // eventually disagree with whatever acts on it.
            $Directory = new HostDirectory();
            $Directory
                ->set('joined', (int)($row['hdJoined'] ?? 0))
                ->set('domain', (string)($row['hdDomain'] ?? ''))
                ->set('netbios', (string)($row['hdNetbios'] ?? ''))
                ->set('computerDN', (string)($row['hdComputerDN'] ?? ''));

            $desiredDomain = (string)($row['hostADDomain'] ?? '');
            $desiredOU = (string)($row['hostADOU'] ?? '');

            $data[] = [
                'hostName' => (string)($row['hostName'] ?? ''),
                'desiredDomain' => $desiredDomain,
                'observedDomain' => $reported
                    ? self::observedDomain($row)
                    : '',
                'desiredOU' => $desiredOU,
                'observedOU' => $Directory->containerDN(),
                'drift' => self::drift(
                    $Directory,
                    $desiredDomain,
                    $desiredOU,
                    $reported
                ),
                'placement' => self::placement($row),
                'join' => self::join($row),
                'observedAt' => (string)($row['hdObservedAt'] ?? ''),
                'checkin' => (string)($row['hostAgentCheckin'] ?? '')
            ];
        }

        // A capped fetch is not a complete answer, and a CSV taken from one
        // looks like a complete file once it is on disk. The query asks for
        // one row past the cap as a sentinel.
        return [
            'data' => $data,
            'truncated' => count($rows) > self::MAX_ROWS
        ];
    }
    /**
     * What to show in the observed-domain column.
     *
     * A host that reported "not joined" gets that said in words rather than
     * an empty cell, which would read as "nothing reported" -- the opposite
     * of what it means.
     *
     * @param array $row the joined row
     *
     * @return string
     */
    protected static function observedDomain(array $row)
    {
        if (empty($row['hdJoined'])) {
            $kind = (string)($row['hdKind'] ?? '');
            return '' === $kind
                ? _('not joined')
                : sprintf('%s (%s)', _('not joined'), $kind);
        }
        return (string)($row['hdDomain'] ?? '');
    }
    /**
     * What placement last did about this host.
     *
     * The column exists because the alternative is a silent failure. FOG is
     * writing to somebody's directory here, and an account that has lost its
     * rights, or an OU that was renamed underneath it, would otherwise show
     * up as a Drift value that simply never clears -- which reads like the
     * feature does not work rather than like something needs fixing.
     *
     * @param array $row the joined row
     *
     * @return string
     */
    protected static function placement(array $row)
    {
        $error = trim((string)($row['hdPlacementError'] ?? ''));
        if ('' !== $error) {
            return $error;
        }
        if ('' === trim((string)($row['hdPlacementAt'] ?? ''))) {
            // Never consulted: placement is off, or this host has never
            // needed it. Neither is a problem to report.
            return '';
        }
        return _('ok');
    }
    /**
     * What the agent last did about joining this host.
     *
     * The counterpart to the Placement column and it exists for the same
     * reason: a join that fails leaves a Drift value that never clears,
     * which reads like the feature does not work rather than like a
     * password needs correcting. FOG has never shown this at all -- the
     * legacy client attempts a join on every check-in and reports nothing
     * either way, so an admin's only evidence is the machine still not
     * being in the domain a week later.
     *
     * @param array $row the joined row
     *
     * @return string
     */
    protected static function join(array $row)
    {
        $error = trim((string)($row['hdJoinError'] ?? ''));
        if ('' !== $error) {
            return $error;
        }
        if ('' === trim((string)($row['hdJoinAt'] ?? ''))) {
            // Never attempted: the host is already joined, or it has never
            // reported, or nothing has needed doing. Not a problem.
            return '';
        }
        return _('ok');
    }
    /**
     * The drift verdict for one host.
     *
     * Four states, and the distinction between the last two is the whole
     * point: "never reported" is FOG not knowing, and an empty Drift column
     * would let it pass for agreement.
     *
     * @param HostDirectory $Directory     the observation
     * @param string        $desiredDomain the host's hostADDomain
     * @param string        $desiredOU     the host's hostADOU
     * @param bool          $reported      whether anything was ever reported
     *
     * @return string
     */
    protected static function drift(
        HostDirectory $Directory,
        $desiredDomain,
        $desiredOU,
        $reported
    ) {
        if (!$reported) {
            return _('never reported');
        }
        $drifted = [];
        if ($Directory->domainDrifted($desiredDomain)) {
            $drifted[] = _('domain');
        }
        if ($Directory->ouDrifted($desiredOU)) {
            $drifted[] = _('OU');
        }
        if (empty($drifted)) {
            return _('ok');
        }
        return implode(', ', $drifted);
    }
}
