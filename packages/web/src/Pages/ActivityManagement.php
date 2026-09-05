<?php
/**
 * The activity log viewer.
 *
 * PHP version 7.4+
 *
 * @category ActivityManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Pages;

use FOG\Auth\Authorization;
use FOG\Base\FOGPage;
use FOG\Router\HTTPResponseCodes;
use FOG\Router\Route;

/**
 * The activity log viewer.
 *
 * One viewer over every event log, not a page per table. ADR 0020 chose a
 * shared record contract rather than one physical table, so the logs keep
 * their own storage and share a frame -- when, who, from where, what kind of
 * event, what it was about. A shared frame is exactly what lets one grid read
 * all of them, and it is why this is a source FILTER rather than an
 * activity-specific page: an activity-specific page gets rebuilt the moment
 * the second source arrives. See docs/adr/0023.
 *
 * The filter offers three: administrative actions (`history`), endpoint
 * logins (`userTracking`) and task activity (`taskLog`). The last two
 * arrived with ADR 0023 item 5 once ADR 0020's phases 2 to 4 had given
 * their tables the frame, and the promise held -- they are entries in
 * _allSources() and a `summary` column apiece, with nothing else on this
 * page changed. The column set is the commitment; the number of sources
 * is not.
 *
 * A source is offered only to somebody who may read it. See _allSources().
 *
 * Deliberately NOT a ReportManagement subclass, though History_Report shows
 * the same rows. Reports inherit the `report` permission node, and that node
 * is the problem this page sits next to: history and usertracking
 * all resolve to `report` (Authorization::API_CLASS_ENTITIES), so a single
 * report.view grant reads every administrative action, every image ever
 * deployed, and every named person's login and logout. Its own node is what
 * lets this page's gate become a different gate.
 *
 * @category ActivityManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ActivityManagement extends FOGPage
{
    /**
     * The node this page answers for.
     *
     * @var string
     */
    public $node = 'activity';
    /**
     * This grid does not select.
     *
     * Read only: this is a view of the event log, not a table anything here
     * removes rows from.
     *
     * @var bool
     */
    public $selectable = false;
    /**
     * Every source this viewer knows, before any permission is applied.
     *
     * value => [label, api class, extra permission or null].
     *
     * A method rather than a property so the labels can be wrapped in _().
     * xgettext extracts from the literal call site only -- _($someVariable)
     * builds its msgid at runtime, finds nothing in the catalog and returns
     * the string untranslated, silently and forever.
     *
     * THE THIRD ELEMENT IS THE ACCESS-CONTROL SEAM and the reason ADR 0023
     * item 5 is not just three lines added to an array.
     *
     * `getList` resolves to `activity.view` by naming convention, so without
     * this every source would be readable by anyone holding the page. For
     * `userTracking` that is precisely the grant ADR 0023 item 1 closed:
     * the permission registry says of the `usertracking` node that
     * "everything that reads it -- the Hosts And Users report, the Login
     * History tabs on host and group, the REST class -- resolves here", and
     * this viewer is a new reader of it. `taskLog` resolves to `task` for
     * the same reason: Task Management's log pane is gated there today.
     *
     * `history` carries null, which keeps it exactly as it has been since
     * the page shipped -- `activity.view` alone. Requiring `report.view`
     * for it as well was put to the maintainer when item 5 landed and
     * DECLINED (2026-08-22): it is the only source an activity.view-only
     * role can read, so adding the requirement would silently empty the
     * page for them on upgrade, and `activity` exists as a node of its own
     * precisely so this page has its own grant. Settled, not pending.
     *
     * @return array
     */
    private static function _allSources()
    {
        return [
            'history' => [_('Administrative actions'), 'history', null],
            'usertracking' => [
                _('Endpoint logins'),
                'usertracking',
                'usertracking.view'
            ],
            'tasklog' => [_('Task activity'), 'tasklog', 'task.view'],
        ];
    }
    /**
     * The sources the signed-in user may actually read.
     *
     * Filtered rather than denied: a source the user cannot read is not
     * offered, so the grid never draws and then fetches a denial. Same
     * shape as the Login History tabs, which are hidden for the same
     * reason.
     *
     * @return array
     */
    private static function _sources()
    {
        $sources = [];
        foreach (self::_allSources() as $key => $meta) {
            if (null !== $meta[2] && !Authorization::can($meta[2])) {
                continue;
            }
            $sources[$key] = $meta;
        }

        return $sources;
    }
    /**
     * The source key the request asked for, validated against the whitelist.
     *
     * Never the raw parameter: it reaches Route::listem() as a class name, so
     * an unrecognized value falls back to the default rather than being
     * passed through. The whitelist is the PERMITTED set, not the known set,
     * so `?source=usertracking` from somebody without the grant falls back
     * to a source they do hold rather than reaching listem().
     *
     * Returns '' when the user may read none of them, which index() renders
     * as a message and getList() answers as an empty result. It cannot be
     * $keys[0] on an empty array -- that is an undefined index, and on this
     * page it would be an undefined index that then became a class name.
     *
     * That state is UNREACHABLE today, and deliberately so: `history` is
     * ungated, so every holder of the page has at least one source. The
     * guard stays because what makes it unreachable is a single null in
     * _allSources() -- one edit away, by anyone who has not read why it is
     * null. tests/activity-sources.test.php pins the invariant, so making
     * that edit fails a test rather than producing a fatal.
     *
     * @return string
     */
    private static function _requestedSource()
    {
        $keys = array_keys(self::_sources());
        if (count($keys) < 1) {
            return '';
        }
        $want = (string) filter_input(INPUT_GET, 'source');
        if (in_array($want, $keys, true)) {
            return $want;
        }

        return $keys[0];
    }
    /**
     * Initializes the activity page.
     *
     * @param string $name the name to construct with.
     */
    public function __construct($name = '')
    {
        $this->name = _('Activity');
        parent::__construct($this->name);
    }
    /**
     * Presents the activity list.
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
        $this->title = _('Activity');

        $this->headerData = [
            _('Who'),
            _('When'),
            _('What'),
            _('From')
        ];
        $this->attributes = [
            [],
            [],
            [],
            []
        ];

        $source = self::_requestedSource();

        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Activity');
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';

        // A select rather than tabs. Tabs commit the layout to a small fixed
        // number of sources, and the whole point of this page is that the
        // list of sources grows.
        echo '<div class="row mb-3">';
        echo '<div class="col-md-4">';
        echo self::makeLabel(
            'col-form-label',
            'activity-source',
            _('Source')
        );
        echo '<select id="activity-source" class="form-select" '
            . 'name="source">';
        foreach (self::_sources() as $key => $meta) {
            printf(
                '<option value="%s"%s>%s</option>',
                \Initiator::e($key),
                ($key === $source ? ' selected' : ''),
                \Initiator::e($meta[0])
            );
        }
        echo '</select>';
        echo '</div>';
        echo '</div>';

        if ('' === $source) {
            // Holding activity.view without holding any source's own node is
            // a real state -- `activity` is a node of its own and grants
            // nothing else -- and an empty select above an empty grid reads
            // as a broken page rather than as a permissions answer.
            echo '<div class="alert alert-info mb-0">';
            echo _('You do not have permission to read any activity source.');
            echo '</div>';
        } else {
            $this->render(12, 'activity-table');
        }

        echo '</div>';
        echo '</div>';

        // Clicking a row opens this. The column that gets truncated on a
        // narrow viewport is the message, and the message is the one column
        // whose entire value is its text. Filled client side from the row the
        // grid already holds, so opening it costs no request -- the same
        // reason TaskManagement's log modal is built that way.
        //
        // Dismiss only: nothing here is editable, so there is no commit
        // button for it to sit to the left of, and it takes the outline
        // secondary that a modal dismiss always takes.
        echo self::makeModal(
            'activity-modal',
            '<h4 class="card-title">'
            . _('Activity entry')
            . '</h4>',
            '<dl class="row mb-0" id="activity-detail"></dl>',
            self::makeButton(
                'activity-close',
                _('Close'),
                'btn btn-outline-secondary float-start',
                'data-bs-dismiss="modal"'
            ),
            '',
            'default',
            'modal-lg'
        );
    }
    /**
     * Serves the grid's rows.
     *
     * Route::listem() runs the request through FOGManagerController::limit(),
     * so DataTables' start/length become a real SQL LIMIT and an unpaginated
     * request is capped at MAX_ROWS with `truncated` stamped on the envelope.
     * The bound is in the query. Nothing here fetches a full table and trims
     * it afterward, which is the thing ADR 0023 forbids -- these tables have
     * nothing aging them out and grow for the life of the install.
     *
     * @return void
     */
    public function getList()
    {
        $sources = self::_sources();
        $source = self::_requestedSource();
        header('Content-type: application/json');
        if ('' === $source) {
            // Nothing readable. An empty DataTables envelope rather than a
            // denial: the page above already said why, and a 403 here would
            // surface as a grid error on a page that is behaving correctly.
            http_response_code(HTTPResponseCodes::HTTP_SUCCESS);
            echo json_encode(
                [
                    'draw' => (int) filter_input(INPUT_POST, 'draw'),
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                    'data' => []
                ]
            );
            exit;
        }
        Route::listem($sources[$source][1]);
        http_response_code(HTTPResponseCodes::HTTP_SUCCESS);
        echo Route::getData();
        exit;
    }
}
