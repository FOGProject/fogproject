<?php
/**
 * Hosts whose legacy FOG Client cannot authenticate to this server.
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
use FOG\Router\Route;

/**
 * Hosts whose legacy FOG Client cannot authenticate to this server.
 *
 * A client that fails the handshake retries on every check-in, forever, and
 * FOG answered each attempt with HTTP 200 and recorded nothing. The only
 * evidence was fog.log on the machine itself, so nobody knew which hosts
 * needed Reset Encryption Data (forum topic 18312).
 *
 * FOGPage::authorize() now keeps the current failure run on the host row
 * (schema 459), and this report lists every host that has one. A host leaves
 * the list on its next successful handshake, not when somebody presses the
 * reset button, so an empty report means the clients really recovered.
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
            _('Failing since'),
            _('Failures'),
            _('From address'),
            _('Last check-in'),
            _('What to do')
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

        printf(
            '<p class="text-muted">%s</p>',
            \Initiator::e(
                _(
                    'Hosts whose FOG Client was refused by this server and '
                    . 'has not authenticated since. A host leaves this list '
                    . 'on its next successful check-in. The handshake '
                    . 'identifies a host by its MAC address, so compare the '
                    . 'From address with the host before acting on a row.'
                )
            )
        );

        echo self::renderReportCap(
            $payload['truncated'],
            self::MAX_ROWS
        );

        $this->render(12, 'clientauthreport-table');

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
        // Oldest run first: the host that has been stuck longest is the one
        // most likely to have been forgotten.
        $sql = "SELECT `hostID`,
                       `hostName`,
                       `hostAuthFailSince`,
                       `hostAuthFailCode`,
                       `hostAuthFailCount`,
                       `hostAuthFailIP`,
                       `hostLastCheckin`
                  FROM `hosts`
                 WHERE `hostAuthFailCount` > 0
                 ORDER BY `hostAuthFailSince` ASC, `hostName` ASC
                 LIMIT " . (self::MAX_ROWS + 1);

        $rows = (array)self::$DB->query($sql)
            ->fetch(\PDO::FETCH_ASSOC, 'fetch_all')
            ->get();

        $data = [];
        foreach (array_slice($rows, 0, self::MAX_ROWS) as $row) {
            $code = (string)($row['hostAuthFailCode'] ?? '');
            $data[] = [
                'hostLink' => Route::entityLink(
                    'host',
                    (int)$row['hostID'],
                    (string)($row['hostName'] ?? '')
                ),
                'problem' => self::problem($code),
                'since' => self::stamp($row['hostAuthFailSince'] ?? ''),
                'count' => (int)($row['hostAuthFailCount'] ?? 0),
                'ip' => (string)($row['hostAuthFailIP'] ?? ''),
                'checkin' => self::stamp($row['hostLastCheckin'] ?? ''),
                'action' => self::action($code)
            ];
        }

        // A capped fetch is not a complete answer. The query asks for one
        // row past the cap as a sentinel.
        return [
            'data' => $data,
            'truncated' => count($rows) > self::MAX_ROWS
        ];
    }
    /**
     * What the stored code means, in words.
     *
     * @param string $code 'ist' or 'dec'
     *
     * @return string
     */
    protected static function problem($code)
    {
        switch ($code) {
            case 'ist':
                return _('Security token does not match');
            case 'dec':
                return _('Server cannot decrypt the client key');
        }
        // A code this version does not know, shown as stored rather than
        // as a blank cell.
        return $code;
    }
    /**
     * What an administrator does about it.
     *
     * The two need different fixes, which is why the report names them
     * apart. A token mismatch ends with Reset Encryption Data. A key the
     * server cannot decrypt does not: the client encrypts for a key this
     * server does not hold. When one host shows it, that client was set up
     * against another server. When most hosts show it at once, this
     * server's own client key changed, for example in a reinstall.
     *
     * @param string $code 'ist' or 'dec'
     *
     * @return string
     */
    protected static function action($code)
    {
        if ('dec' === $code) {
            return _(
                'The client encrypts for a key this server does not hold. '
                . 'Reset Encryption Data does not fix this. If most hosts '
                . 'show it, this server\'s client key changed: restore it. '
                . 'Otherwise reinstall the FOG Client against this server.'
            );
        }
        return _(
            'Reset Encryption Data on the host, then check fog.log '
            . 'on the client.'
        );
    }
    /**
     * A stored datetime in display time, or '' for none.
     *
     * @param string $value the stored value
     *
     * @return string
     */
    protected static function stamp($value)
    {
        if (!self::validDate($value)) {
            return '';
        }
        return self::niceDate($value)->format('Y-m-d H:i:s');
    }
}
