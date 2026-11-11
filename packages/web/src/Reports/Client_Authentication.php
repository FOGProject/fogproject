<?php
/**
 * Hosts whose FOG Client cannot authenticate with this server.
 *
 * PHP version 7.4+
 *
 * @category Client_Authentication
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Reports;

use FOG\Pages\ReportManagement;

/**
 * Hosts whose FOG Client cannot authenticate with this server.
 *
 * A client that fails the handshake keeps failing on every check-in, and
 * until schema 459 the only place that said so was the client's own log on
 * the machine itself. FOGPage::authorize() now records the last failure on
 * the host, and this report lists every host holding one.
 *
 * A host leaves the list on its next successful handshake, not when the
 * reset button is pressed. So after a reset, a row that stays is a client
 * that is still failing.
 *
 * @category Client_Authentication
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Client_Authentication extends ReportManagement
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
            _('Problem'),
            _('Failures'),
            _('Last failure'),
            _('From address'),
            _('Last check-in')
        ];
        $this->attributes = [
            [], [], [], [], [], []
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
                    'Hosts whose FOG Client failed its last attempt to '
                    . 'authenticate with this server. A client in this state '
                    . 'retries on every check-in and runs no modules until '
                    . 'it succeeds. Open the host and use Reset Encryption '
                    . 'Data, or do it for a whole group from the group page. '
                    . 'A host leaves this list the next time its client '
                    . 'authenticates.'
                )
            )
        );
        printf(
            '<p class="text-muted">%s</p>',
            \Initiator::e(
                _(
                    'Many hosts failing with "No usable session key" at once '
                    . 'usually means their clients trust a different server '
                    . 'certificate, for example after FOG was reinstalled. '
                    . 'Resetting encryption data does not fix that; the '
                    . 'client needs this server\'s certificate. A From address '
                    . 'that is not the host\'s own means another machine is '
                    . 'presenting this host\'s MAC address.'
                )
            )
        );

        echo self::renderReportCap(
            $payload['truncated'],
            self::MAX_ROWS
        );

        $this->render(12, 'clientauthentication-table');

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
        $sql = "SELECT `hostID`,
                       `hostName`,
                       `hostIP`,
                       `hostLastCheckin`,
                       `hostAuthFailAt`,
                       `hostAuthFailCode`,
                       `hostAuthFailCount`,
                       `hostAuthFailIP`
                  FROM `hosts`
                 WHERE `hostAuthFailCount` > 0
                 ORDER BY `hostAuthFailAt` DESC
                 LIMIT " . (self::MAX_ROWS + 1);

        $rows = (array)self::$DB->query($sql)
            ->fetch(\PDO::FETCH_ASSOC, 'fetch_all')
            ->get();

        $data = [];
        foreach (array_slice($rows, 0, self::MAX_ROWS) as $row) {
            $data[] = [
                'id' => (int)($row['hostID'] ?? 0),
                'hostName' => (string)($row['hostName'] ?? ''),
                'problem' => self::problem(
                    (string)($row['hostAuthFailCode'] ?? '')
                ),
                'failures' => (int)($row['hostAuthFailCount'] ?? 0),
                'failedAt' => self::when($row['hostAuthFailAt'] ?? null),
                'fromIP' => (string)($row['hostAuthFailIP'] ?? ''),
                'checkin' => self::when($row['hostLastCheckin'] ?? null)
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
     * The failure code in words.
     *
     * @param string $code the stored protocol code, without its #!
     *
     * @return string
     */
    protected static function problem($code)
    {
        switch ($code) {
            case 'ist':
                return _('Security token mismatch');
            case 'ihc':
                return _('No usable session key');
        }
        return $code;
    }
    /**
     * A stored datetime in the server's display timezone, or ''.
     *
     * @param mixed $value the column value
     *
     * @return string
     */
    protected static function when($value)
    {
        if (!self::validDate($value)) {
            return '';
        }
        return self::niceDate($value)->format('Y-m-d H:i:s');
    }
}
