<?php
/**
 * Who is logged on across the fleet right now.
 *
 * PHP version 7.4+
 *
 * @category User_Sessions
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Reports;

use FOG\Pages\ReportManagement;

/**
 * Who is logged on across the fleet right now.
 *
 * NOT the Activity page's user tracking view. That reads `userTracking`,
 * an append-only log of login and logout EVENTS, and it cannot answer this
 * question: a logout event needs a network round trip at the moment the
 * machine is going away, so events go missing -- six of eleven sessions on
 * the lab server have no logout at all, and an unclosed login there is
 * indistinguishable from someone still working. This report reads design
 * 0008's `hostUserSession`, where a session is one row with two ends and
 * `husEndedAt IS NULL` genuinely means open, because the agent re-reports
 * its open set and the server closes whatever stops being reported.
 *
 * A FLEET SNAPSHOT, not a history. "Currently logged on" is a state, so
 * there is no window and no chart -- which is the same test that puts
 * Installed_Software under Lists rather than in
 * ReportManagement::AGGREGATIONS. Per-host history lives on the host.
 *
 * GATED ON `host`. Reports share the `report` node by default (the defect
 * ADR 0023 opens with), and a session row is host data carrying a person's
 * name, so it is gated on host.view like the rest of it. That narrows
 * against the default; nothing anyone holds today gets wider.
 *
 * @category User_Sessions
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class User_Sessions extends ReportManagement
{
    /**
     * The most rows a grid or export will carry back.
     *
     * Same stance as Installed_Software: a report says so on screen when it
     * truncates rather than quietly showing a prefix.
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
            _('User'),
            _('Type'),
            _('State'),
            _('Logged on'),
            _('Duration'),
            _('From')
        ];
        $this->attributes = [
            [], [], [], [], [], [], []
        ];

        $payload = $this->reportRows();

        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';

        // Said on the page, not just in the docblock. See the class note.
        printf(
            '<p class="text-muted">%s</p>',
            \Initiator::e(
                _(
                    'Sessions open right now, as last reported by each '
                    . 'host\'s agent. A host that has not checked in '
                    . 'recently may have logged its user off since.'
                )
            )
        );

        echo self::renderReportCap(
            $payload['truncated'],
            self::MAX_ROWS
        );

        $this->render(12, 'usersessionsreport-table');

        echo '</div>';
        echo '</div>';
    }
    /**
     * The rows this report serves.
     *
     * Split from the emit so the grid and the CSV export run the same
     * query -- see ReportManagement::exportAll().
     *
     * @return array
     */
    protected function reportRows()
    {
        // husEndedAt IS NULL is the open slice, and it is trustworthy here
        // in a way the legacy table's unmatched logins are not: a host that
        // reports its open set closes whatever it stops reporting, so a row
        // stays open only while a host keeps saying it is.
        $sql = "SELECT `hostName`,
                       `husUserName`,
                       `husDomain`,
                       `husType`,
                       `husState`,
                       `husStartedAt`,
                       `husRemoteHost`
                  FROM `hostUserSession`
                  LEFT OUTER JOIN `hosts` ON `husHostID` = `hostID`
                 WHERE `husEndedAt` IS NULL
                 ORDER BY `husStartedAt` DESC
                 LIMIT " . (self::MAX_ROWS + 1);

        $rows = (array)self::$DB->query($sql)
            ->fetch(\PDO::FETCH_ASSOC, 'fetch_all')
            ->get();

        $now = time();
        $data = [];
        foreach (array_slice($rows, 0, self::MAX_ROWS) as $row) {
            $user = (string)($row['husUserName'] ?? '');
            $domain = (string)($row['husDomain'] ?? '');
            $started = (string)($row['husStartedAt'] ?? '');
            $data[] = [
                'hostName' => (string)($row['hostName'] ?? ''),
                // Shown qualified. The legacy table stores the name with
                // its domain stripped, which merges CORP\jsmith with
                // LAB\jsmith; keeping them apart is half the point of the
                // new row, so the report must not re-merge them.
                'user' => '' === $domain ? $user : $domain . '\\' . $user,
                'type' => (string)($row['husType'] ?? ''),
                'state' => (string)($row['husState'] ?? ''),
                'startedAt' => $started,
                'duration' => self::elapsed($started, $now),
                'remoteHost' => (string)($row['husRemoteHost'] ?? '')
            ];
        }

        // A capped fetch is not a complete answer, and a CSV taken from one
        // looks exactly like a complete file once it is on disk. The query
        // asks for one row past the cap as a sentinel, so `>` here means
        // that sentinel came back.
        return [
            'data' => $data,
            'truncated' => count($rows) > self::MAX_ROWS
        ];
    }
    /**
     * How long an open session has been running, rendered short.
     *
     * An unusable start yields an empty cell rather than "0m": a session
     * whose start did not parse has an unknown duration, and zero would
     * read as one that just began.
     *
     * @param string $started the session start
     * @param int    $now     the reference time
     *
     * @return string
     */
    protected static function elapsed($started, $now)
    {
        // niceDate(), not strtotime(): the column is written on
        // storageTimeZone() (UTC), and strtotime() would read that string in
        // PHP's default zone. On this server that is UTC-5, which put every
        // open session five hours in the future and blanked the column via
        // the guard below -- the same two-clock mistake the reconcile made
        // in husEndedAt.
        if (!self::validDate($started)) {
            return '';
        }
        $start = self::niceDate($started)->getTimestamp();
        if ($start > $now) {
            return '';
        }
        $mins = (int)floor(($now - $start) / 60);
        if ($mins < 60) {
            return sprintf('%dm', $mins);
        }
        if ($mins < 1440) {
            return sprintf('%dh %dm', intdiv($mins, 60), $mins % 60);
        }
        return sprintf('%dd %dh', intdiv($mins, 1440), intdiv($mins % 1440, 60));
    }
}
