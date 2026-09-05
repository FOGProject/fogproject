<?php
/**
 * Directory membership an agent reports about its own host.
 *
 * PHP version 7.4+
 *
 * @category DirectoryMembership
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Agent;

use FOG\Audit\Audit;
use FOG\Base\FOGBase;
use FOG\Items\Host;
use FOG\Router\Route;

/**
 * Writes a reported membership block onto the host's `hostDirectory` row
 * (design 0009 section 3).
 *
 * A fact report like InventoryFacts, and registered the same way: an entry
 * in State::FACT_REPORTS and a block in the poll, never a route of its own
 * (the route rule, protocol-v1.md). The server's hash gate, the
 * `want_directory` answer and the audit line all come from being in that
 * registry.
 *
 * What it does NOT do is act on the difference. Comparing the observation
 * against the host's hostADDomain and hostADOU is the report's job, and
 * moving a computer object between OUs is design 0009 section 5, which
 * needs a directory credential FOG does not have. This class only records.
 *
 * Named DirectoryFacts, not Directory, for the same reason InventoryFacts
 * is not Inventory: FOG\Items\HostDirectory is the row this writes.
 *
 * @category DirectoryMembership
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class DirectoryFacts extends FOGBase
{
    /**
     * The reported keys, mapped to HostDirectory property names.
     *
     * A whitelist rather than a filter over the field map, for
     * InventoryFacts' reason: hdHostID and hdID are the server's, and a
     * reported block must not be able to reach them.
     *
     * @var array<string, string>
     */
    const FIELDS = [
        'kind' => 'kind',
        'domain' => 'domain',
        'netbios' => 'netbios',
        'computer_dn' => 'computerDN',
        'machine_account' => 'machineAccount',
        'site' => 'site'
    ];

    /**
     * Longest value stored per column, keyed by property.
     *
     * The columns are varchars and MySQL in strict mode refuses an overlong
     * value, which would fail the whole poll rather than the one field. A
     * DN is the long one: AD allows well over 255 characters once a few
     * nested OUs are involved.
     *
     * @var array<string, int>
     */
    const WIDTHS = [
        'kind' => 32,
        'domain' => 255,
        'netbios' => 64,
        'computerDN' => 1024,
        'machineAccount' => 255,
        'site' => 255
    ];

    /**
     * The kinds a host may report.
     *
     * An unrecognized kind is stored as the empty string rather than passed
     * through: the report groups on it, and a host inventing a value would
     * put an uncontrolled string into a page an admin reads.
     *
     * @var string[]
     */
    const KINDS = ['ad', 'entra', 'workgroup', 'none'];

    /**
     * Records a reported membership block on the host's directory row.
     *
     * Upsert: one row per host, replaced in place. Reached only when the
     * server's hash for the block moved, so every call here is a real
     * change in what the machine says about itself -- which is why the
     * audit line is unconditional.
     *
     * @param Host  $Host  the host the certificate bound
     * @param array $block the reported membership
     *
     * @return void
     */
    public static function report(Host $Host, array $block)
    {
        $hostID = (int)$Host->get('id');
        $Directory = self::row($hostID);

        $joined = !empty($block['joined']);
        $Directory->set('joined', $joined ? 1 : 0);

        foreach (self::FIELDS as $key => $field) {
            $value = substr(
                trim((string)($block[$key] ?? '')),
                0,
                self::WIDTHS[$field]
            );
            if ('kind' === $field && !in_array($value, self::KINDS, true)) {
                // A host that invents a kind gets none, not its own string.
                $value = '';
            }
            if (!$joined && 'kind' !== $field) {
                // An unjoined machine has no membership detail, and a stale
                // domain left on one would compare EQUAL to the desired
                // value and hide the drift this row exists to show. The
                // agent already clears these; clearing again here means a
                // hand-built or older block cannot reintroduce the lie.
                $value = '';
            }
            $Directory->set($field, $value);
        }

        // Stamped on storageTimeZone() like every other datetime FOG
        // writes. niceDate() rather than date(): the two clocks differ on
        // any server whose PHP default zone is not UTC, and mixing them is
        // what made a one-second user session read as five hours.
        $Directory->set(
            'observedAt',
            self::niceDate()->setTimezone(self::storageTimeZone())
                ->format('Y-m-d H:i:s')
        );
        $Directory->save();

        // One renderable line, naming what the machine now says rather than
        // which fields moved: unlike an inventory row, this is three facts
        // an admin can read in a sentence, and "left CORP" is the sentence
        // they need to see.
        Audit::record(
            [
                'type' => 'agent.directory',
                'subjectType' => 'host',
                'subjectID' => $hostID,
                'subjectLabel' => (string)$Host->get('name'),
                'renderable' => 1,
                'affectedCount' => 1,
                'text' => substr(
                    'agent reported directory membership: '
                    . self::describe($Directory),
                    0,
                    Audit::MAX_DETAIL
                ),
                'authSource' => Principal::AUTH_SOURCE
            ]
        );
    }

    /**
     * Puts the host's computer object where the host record asks for it
     * (design 0009 section 5).
     *
     * Called from the poll on EVERY check-in, not from report() -- which
     * runs only when the machine's own report moved. The other thing that
     * creates drift is an admin editing the host's OU, and that changes
     * nothing a machine would ever report, so hanging placement off the
     * report would mean an edited OU never took effect until the machine
     * happened to change domains. Which is the bug design 0009 exists to
     * fix, arrived at from the other direction.
     *
     * Cheap when there is nothing to do: a host with no row, no desired OU
     * or no drift returns before any connection is made.
     *
     * @param Host $Host the host the certificate bound
     *
     * @return void
     */
    public static function place(Host $Host)
    {
        DirectoryPlacement::ensure($Host, self::row((int)$Host->get('id')));
    }

    /**
     * The host's directory row, or a new unsaved one.
     *
     * @param int $hostID the host
     *
     * @return \FOG\Items\HostDirectory
     */
    protected static function row($hostID)
    {
        // Route::getIds, the way State::_factStateID looks up its own row.
        // FOGManagerController has no find(); a manager is the read side of
        // the route layer, not a repository.
        $ids = Route::getIds(
            'hostdirectory',
            ['hostID' => (int)$hostID],
            'id'
        );
        $id = (int)(array_shift($ids) ?: 0);
        if ($id > 0) {
            $Directory = new \FOG\Items\HostDirectory($id);
            if ($Directory->isValid()) {
                return $Directory;
            }
        }
        return (new \FOG\Items\HostDirectory())->set('hostID', (int)$hostID);
    }

    /**
     * One line describing what a host reported, for the audit entry.
     *
     * @param \FOG\Items\HostDirectory $Directory the stored row
     *
     * @return string
     */
    protected static function describe(\FOG\Items\HostDirectory $Directory)
    {
        if (!$Directory->get('joined')) {
            $kind = (string)$Directory->get('kind');
            return 'not joined' . ('' === $kind ? '' : " ($kind)");
        }
        $out = (string)$Directory->get('kind') . ' '
            . (string)$Directory->get('domain');
        $container = $Directory->containerDN();
        if ('' !== $container) {
            $out .= ' in ' . $container;
        }
        return trim($out);
    }
}
