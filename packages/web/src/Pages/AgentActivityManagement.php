<?php
/**
 * What every fog-agent in the install has been doing, grouped by host.
 *
 * PHP version 7.4+
 *
 * @category AgentActivity
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Pages;

use FOG\Base\FOGPage;
use FOG\Router\HTTPResponseCodes;
use FOG\Router\Route;

/**
 * The agent's side of the audit trail, one row per host.
 *
 * The rows are already in `auditLog`, written by the classes under
 * src/Agent and tagged subjectType `host` with the host's id. What was
 * missing was any way to read them by host: AuditManagement lists the whole
 * install in one flat grid with no host filter, so "what has this machine's
 * agent been doing" meant scrolling past every other machine.
 *
 * ONE GRID, GROUPED BY HOST. Every host that has agent activity gets a
 * RowGroup header carrying its name and its event count, and under it that
 * host's newest event. Expanding a header loads the rest of that host's
 * events and shows them as ordinary rows of the same grid.
 *
 * The first version of this page was a summary grid whose rows each opened
 * a nested DataTable through `row.child()`. That never worked in the
 * browser and could not have: a DataTables row has ONE child slot,
 * registerTable() turns Responsive on for every grid, and Responsive claims
 * that slot for its own hidden-column detail. Clicking expand rendered
 * Responsive's field list and the nested table was never constructed --
 * measured on a live install at 1920px with no columns hidden. Nothing
 * errored, which is why it survived a fix (`the expanded host was stuck at
 * ten rows`) aimed at a pager inside a table that did not exist.
 *
 * So the drill-down uses no child row at all. Rows are added to the grid
 * itself, which is also what makes them look like the rest of FOG rather
 * than a grid nested inside a grid with its own scrollbar and its own pager.
 *
 * WHY EACH GROUP KEEPS A ROW. Collapsing is a search filter over the event
 * rows, and RowGroup builds its headers from the rows that SURVIVE the
 * filter -- a group with none left renders no header and the host
 * disappears. Measured, not assumed. So the newest event of every host is
 * seeded into the table and never filtered: it is the anchor its header is
 * drawn from, and it doubles as the thing worth seeing when everything is
 * collapsed, which is what each agent last did.
 *
 * WHY THE SEED IS A SUMMARY QUERY AND THE REST IS NOT. An agent writes a
 * row per changed fact per host and FOG_AUDIT_RETENTION_DAYS defaults to 0,
 * "keep everything forever" -- so the flat event set is unbounded and
 * cannot be loaded client side, which is also why rowGroup over a
 * `serverSide` grid is not an option here (it would group within one page).
 * The seed is bounded by the size of the fleet; each expansion is bounded
 * by a cap. Nothing on this page loads a set bounded by neither.
 *
 * The expand fetches through the SAME endpoint the host page's Agent
 * Activity tab uses, so there is one query behind both surfaces.
 *
 * READ ONLY, like the audit log it reads. `auditlog` has no create, update
 * or delete route anywhere in FOG (ADR 0021 Decision 8), so there is nothing
 * here to wire a row action to.
 *
 * @category AgentActivity
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AgentActivityManagement extends FOGPage
{
    /**
     * The node this page answers for.
     *
     * Its own node rather than an alias onto `audit`. ADR 0021 made
     * audit.view narrow because the audit log discloses attempted usernames
     * and refusals; agent rows are enrollments, inventories and task results
     * reported by a machine, which is a different disclosure and a different
     * audience. Aliasing would have forced anyone who may see what an agent
     * did to also see every failed sign-in in the install.
     *
     * @var string
     */
    public $node = 'agentactivity';
    /**
     * This grid does not select.
     *
     * Read only, like the audit log it reads: `auditlog` has no create, update
     * or delete route anywhere in FOG (ADR 0021 Decision 8), so there is
     * nothing here for a selection to act on.
     *
     * @var bool
     */
    public $selectable = false;
    /**
     * The prefix every type this page shows begins with.
     *
     * A LIKE rather than a list of the eleven current type names: a new
     * fact kind is a registry entry and a block in the poll (the route
     * rule), and it must not also be a third place to remember to edit
     * before its rows become visible.
     *
     * @var string
     */
    const TYPE_PREFIX = 'agent.';
    /**
     * How many hosts the summary will list.
     *
     * The summary is bounded by the fleet, not by the log, so this is high
     * enough that no real install reaches it and low enough that a runaway
     * cannot render a million rows into a browser.
     *
     * @var int
     */
    const MAX_HOSTS = 5000;
    /**
     * Initializes the page.
     *
     * @param string $name the name to construct with.
     */
    public function __construct($name = '')
    {
        $this->name = _('Agent Activity');
        parent::__construct($this->name);
    }
    /**
     * Presents the per-host summary.
     *
     * Variadic to match FOGPage::index(...$args) -- PHP rejects the
     * declaration outright otherwise, so the class does not load at all.
     *
     * @param mixed ...$args unused, present for signature compatibility
     *
     * @return void
     */
    public function index(...$args)
    {
        $this->title = _('Agent Activity');

        // ONE column set for both kinds of row. The grid holds each host's
        // newest event and, once its group is expanded, the rest of that
        // host's events -- and those are the same shape, so they are the
        // same columns. The host itself is not a column: it is the RowGroup
        // header, which is where the per-host counts and the expand control
        // live too.
        $this->headerData = [
            _('When'),
            _('Event'),
            _('Detail'),
            _('Outcome'),
            _('Host')
        ];
        // The host column is hidden and stays hidden -- `noVis` keeps it out
        // of the Column Visibility picker. It exists because RowGroup needs
        // the table SORTED by its group, and a group is only contiguous if
        // something orders it: ordering by time alone lets one host's older
        // rows fall past the next host's newest one, which splits the group
        // and draws its header a second time. Observed before this existed.
        $this->attributes = [
            [],
            [],
            [],
            [],
            ['class' => 'noVis']
        ];

        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Agent Activity');
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';

        $this->render(12, 'agentactivity-table');

        echo '</div>';
        echo '</div>';
    }
    /**
     * Serves the summary: one row per host that has agent activity.
     *
     * Direct SQL rather than a manager read, for the reason getChanges()
     * gives in AuditManagement: this is an aggregate, and Route::listem()
     * has no GROUP BY. It would also materialize an object per audit row in
     * the install to hand back four numbers per host.
     *
     * The join to `hosts` is LEFT: a host deleted after its agent reported
     * still has rows, and dropping them would make the counts here disagree
     * with the audit log itself. Those rows show as the host id with no name
     * rather than vanishing -- on the lab install most of them are that.
     *
     * @return void
     */
    public function getList()
    {
        header('Content-type: application/json');

        // LIMIT is a literal because it is a class constant, never request
        // input. The one value that varies is bound.
        //
        // The derived table groups once, and the outer join back to
        // auditLog on MAX(alID) is what names the LAST event type. The
        // obvious alternative -- GROUP_CONCAT(alType ORDER BY alID DESC)
        // with SUBSTRING_INDEX -- builds a string of every row's type for
        // every host just to read the first one, and silently truncates at
        // group_concat_max_len on any host with a long history.
        $rows = self::$DB->query(
            'SELECT a.`alSubjectID` AS `hostID`, h.`hostName` AS `hostName`, '
            . 's.`events` AS `events`, s.`lastTime` AS `lastTime`, '
            . 'a.`alType` AS `lastType`, a.`alText` AS `lastText`, '
            . 'a.`alOutcome` AS `lastOutcome` '
            . 'FROM `auditLog` a '
            . 'INNER JOIN ('
            . 'SELECT `alSubjectID`, COUNT(*) AS `events`, '
            . 'MAX(`alID`) AS `maxID`, MAX(`alCreatedTime`) AS `lastTime` '
            . 'FROM `auditLog` '
            . 'WHERE `alType` LIKE :prefix AND `alSubjectType` = \'host\' '
            . 'GROUP BY `alSubjectID`'
            . ') s ON s.`alSubjectID` = a.`alSubjectID` '
            . 'AND s.`maxID` = a.`alID` '
            // LEFT, not INNER: a host deleted after its agent reported still
            // has rows, and dropping them would make these counts disagree
            // with the audit log itself. Measured on the lab install, where
            // most rows belong to hosts that no longer exist.
            . 'LEFT JOIN `hosts` h ON h.`hostID` = a.`alSubjectID` '
            . 'ORDER BY s.`lastTime` DESC '
            . 'LIMIT ' . self::MAX_HOSTS,
            [],
            [':prefix' => self::TYPE_PREFIX . '%']
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();

        $out = [];
        foreach ((array) $rows as $row) {
            $id = (int) ($row['hostID'] ?? 0);
            $out[] = [
                'hostID' => $id,
                // A host removed after its agent reported leaves rows with
                // no name to join to. Saying so beats an empty cell that
                // reads as a rendering fault.
                'hostName' => '' === (string) ($row['hostName'] ?? '')
                    ? sprintf(_('(deleted host %d)'), $id)
                    : (string) $row['hostName'],
                'events' => (int) ($row['events'] ?? 0),
                // Displayed in the VIEWER's zone like every other date on a
                // page, not the storage zone it was written in.
                'lastTime' => '' === (string) ($row['lastTime'] ?? '')
                    ? ''
                    : self::toDisplayStored(
                        (string) $row['lastTime']
                    )->format('Y-m-d H:i:s'),
                'lastType' => (string) ($row['lastType'] ?? ''),
                // The newest row IN FULL, not just its type. It is the one
                // row of the host that is always on screen -- the anchor its
                // group header is drawn from -- so it has to carry the same
                // four fields an expanded row does or the collapsed view
                // would have empty cells under populated headings.
                'lastText' => (string) ($row['lastText'] ?? ''),
                'lastOutcome' => (string) ($row['lastOutcome'] ?? '')
            ];
        }

        http_response_code(HTTPResponseCodes::HTTP_SUCCESS);
        echo json_encode(['data' => $out]);
        exit;
    }
    /**
     * Serves one host's agent rows, for an expanded summary row.
     *
     * The same read the host page's Agent Activity tab performs, so the two
     * surfaces cannot drift into showing different histories for one host.
     * Route::listem() puts DataTables' start/length through
     * FOGManagerController::limit(), so an expanded host with thousands of
     * rows still pages.
     *
     * Ordered by id rather than listem()'s default of `name`: an audit row
     * has no name, and id orders the same way createdTime does without ties
     * between rows written in the same second.
     *
     * @return void
     */
    public function getHostActivity()
    {
        header('Content-type: application/json');
        $hostID = (int) Route::queryParam('id');
        if ($hostID < 1) {
            // An absent or malformed id must not fall through to "every
            // host": this endpoint exists to scope, and an unscoped answer
            // is the one thing it must never give.
            http_response_code(HTTPResponseCodes::HTTP_SUCCESS);
            echo json_encode(['data' => []]);
            exit;
        }
        Route::listem(
            'auditlog',
            [
                'type' => self::TYPE_PREFIX . '%',
                'subjectType' => 'host',
                'subjectID' => $hostID
            ],
            false,
            'AND',
            'id'
        );
        http_response_code(HTTPResponseCodes::HTTP_SUCCESS);
        echo Route::getData();
        exit;
    }
}
