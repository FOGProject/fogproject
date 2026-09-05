<?php
/**
 * Printers for an enrolled agent: the desired set and the results.
 *
 * PHP version 7.4+
 *
 * @category PrinterSet
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
use FOG\Items\Printer;
use FOG\Items\PrinterAssociation;
use FOG\Router\Route;

/**
 * The printers capability (design 0010 section 5): a desired set of queues
 * the host is held to, described as a device URI and a driver, with an
 * outcome recorded per assignment.
 *
 * Like SoftwareSet and unlike a snapin, nothing here is a task: the set is
 * read fresh on every state fetch, the agent converges it, and a report
 * refreshes one row per host and printer.
 *
 * The contrast to draw is with PrinterFacts next door: that records what the
 * machine SAYS IT HAS. This sends what it SHOULD have, and keeps what
 * happened when it tried. FOG has had neither half until now -- an install
 * that failed produced nothing an admin could see, and the client retried
 * the same thing on the next poll, forever.
 *
 * @category PrinterSet
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PrinterSet extends FOGBase
{
    /**
     * The three modes, in words.
     *
     * `hostPrinterLevel` stores 0, 1 or 2, and the legacy wire has always
     * sent 0, `a` or `ar` -- two vocabularies for one setting, neither of
     * them written down anywhere an admin can see (design 0010 section 1.3).
     * The agent gets a third that says what it means, and the legacy
     * endpoint keeps sending what it always sent.
     */
    const MODE_OFF = 'off';
    const MODE_ASSIGNED = 'assigned';
    const MODE_EXCLUSIVE = 'exclusive';
    const MODES = [0 => self::MODE_OFF, 1 => self::MODE_ASSIGNED,
        2 => self::MODE_EXCLUSIVE];

    /**
     * What the agent may report for one printer.
     *
     * `converged` means nothing needed doing, which is the resting state and
     * the overwhelmingly common report. The rest are one action each, plus
     * the two ways a provider can decline to act at all.
     */
    const STATUS_CONVERGED = 'converged';
    const STATUSES = [
        'converged', 'installed', 'updated', 'removed', 'failed',
        'unsupported'
    ];

    /**
     * The statuses that mean the printer is now as it should be, so any
     * error recorded against it is stale.
     */
    const SETTLED_STATUSES = ['converged', 'installed', 'updated', 'removed'];

    /**
     * Longest error message kept. A provider's stderr can run to pages; the
     * column is a varchar(255) because this is a line an admin reads in a
     * report, not a log.
     */
    const MAX_ERROR = 255;

    /**
     * The desired set for a host, with the mode.
     *
     * Resolved through Resolver::resolvePrinters -- the same call
     * PrinterClient makes for the legacy client -- so the two clients cannot
     * be told different things about the same host.
     *
     * @param Host $Host the principal
     *
     * @return array
     */
    public static function desired(Host $Host)
    {
        $hostID = (int)$Host->get('id');
        $level = (int)$Host->get('printerLevel');
        $resolved = Resolver::resolvePrinters([$hostID])[$hostID]
            ?? ['printers' => [], 'default' => null];

        $printers = [];
        $default = '';
        foreach ((array)($resolved['printers'] ?? []) as $id) {
            $Printer = new Printer((int)$id);
            if (!$Printer->isValid()) {
                continue;
            }
            $name = (string)$Printer->get('name');
            $printers[] = [
                'id' => (int)$Printer->get('id'),
                'name' => $name,
                // Derived on read when nothing was set explicitly, so a
                // printer created years ago against pConfig/pIP/pPort works
                // without anybody editing it (Items\Printer::uri()).
                'uri' => $Printer->uri(),
                // Empty means driverless (IPP Everywhere), which is a value
                // and not a missing field.
                'driver' => $Printer->driver()
            ];
            if (null !== ($resolved['default'] ?? null)
                && (int)$resolved['default'] === (int)$id
            ) {
                $default = $name;
            }
        }

        return [
            'manage' => self::MODES[$level] ?? self::MODE_OFF,
            'default' => $default,
            'printers' => $printers
        ];
    }

    /**
     * Records what the agent did about one assigned printer.
     *
     * Written onto the ASSOCIATION rather than a status table of its own,
     * because the outcome belongs to "this host was told to have this
     * printer" and dies with it: unassigning the printer should take the
     * failure with it, and a CASCADE on printerAssoc does that for free.
     *
     * @param Host  $Host      the host the certificate bound
     * @param int   $printerID the printer reported on
     * @param array $body      the reported result
     *
     * @throws \RuntimeException with an HTTP code when refused
     *
     * @return string the status recorded
     */
    public static function report(Host $Host, $printerID, array $body)
    {
        $hostID = (int)$Host->get('id');
        $printerID = (int)$printerID;

        // The host may only report on printers it was actually told to
        // have. Checked against the resolver rather than against
        // printerAssoc directly, so a printer reaching the host through a
        // GROUP grant is accepted -- and one reaching it through neither is
        // a host reporting on somebody else's row.
        $resolved = Resolver::resolvePrinters([$hostID])[$hostID]
            ?? ['printers' => []];
        $set = array_map('intval', (array)($resolved['printers'] ?? []));
        if (!in_array($printerID, $set, true)) {
            throw new \RuntimeException('not in this host\'s printer set', 404);
        }

        $status = (string)($body['status'] ?? '');
        if (!in_array($status, self::STATUSES, true)) {
            throw new \RuntimeException('unknown status', 400);
        }
        // `details` and not `error`: every item report on this route carries
        // the provider's output in `details` (a snapin's tail, a package
        // manager's log), and a printer's failure message is the same thing
        // -- lpadmin's own words. One field name for one meaning across the
        // protocol beats a per-capability spelling.
        $error = self::errorFor($status, (string)($body['details'] ?? ''));

        $ids = Route::getIds(
            'printerassociation',
            ['hostID' => $hostID, 'printerID' => $printerID],
            'id'
        );
        $id = (int)(array_shift($ids) ?: 0);
        if ($id < 1) {
            // Resolved through a group, so there is no host-direct row to
            // stamp. The result is still real and still worth an audit line;
            // it simply has nowhere on the association to live.
            self::_audit($Host, $printerID, $status, $error);
            return $status;
        }
        $Assoc = new PrinterAssociation($id);
        if (!$Assoc->isValid()) {
            self::_audit($Host, $printerID, $status, $error);
            return $status;
        }
        $Assoc
            // Named for the ATTEMPT, not the success: this is stamped
            // whenever the agent acted, so a name like paInstalledAt would
            // claim an install happened on every occasion one did not.
            ->set(
                'appliedAt',
                self::niceDate()->setTimezone(self::storageTimeZone())
                    ->format('Y-m-d H:i:s')
            )
            ->set('error', $error)
            ->save();

        // A converged heartbeat is not news. Auditing every poll that
        // reported the same nothing would bury the results that matter.
        if (self::STATUS_CONVERGED !== $status) {
            self::_audit($Host, $printerID, $status, $error);
        }

        return $status;
    }

    /**
     * The error to record for a reported status.
     *
     * A settled status CLEARS whatever was there. Leaving it would make the
     * report show an error against a printer that is now installed, which
     * is worse than showing nothing at all -- an admin chasing a stale
     * message is worse off than one chasing none.
     *
     * A failure keeps its message, truncated: a provider's stderr runs to
     * pages and this is a line somebody reads in a report, not a log.
     *
     * @param string $status the reported status
     * @param string $error  the reported message
     *
     * @return string
     */
    protected static function errorFor($status, $error)
    {
        if (in_array($status, self::SETTLED_STATUSES, true)) {
            return '';
        }

        return substr(trim($error), 0, self::MAX_ERROR);
    }
    /**
     * One audit line for a printer result.
     *
     * @param Host   $Host      the host
     * @param int    $printerID the printer
     * @param string $status    what the agent said it did
     * @param string $error     the message, if any
     *
     * @return void
     */
    private static function _audit(Host $Host, $printerID, $status, $error)
    {
        $Printer = new Printer((int)$printerID);
        Audit::record(
            [
                'type' => 'agent.result',
                'subjectType' => 'host',
                'subjectID' => (int)$Host->get('id'),
                'subjectLabel' => (string)$Host->get('name'),
                'renderable' => 1,
                'text' => substr(
                    sprintf(
                        'printer "%s" %s%s',
                        (string)$Printer->get('name'),
                        $status,
                        '' === $error ? '' : ': ' . $error
                    ),
                    0,
                    Audit::MAX_DETAIL
                ),
                'authSource' => Principal::AUTH_SOURCE
            ]
        );
    }
}
