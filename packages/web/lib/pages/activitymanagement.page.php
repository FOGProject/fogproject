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

namespace FOG;

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
 * Today the filter offers one value. The column set is the commitment; the
 * number of sources is not. As ADR 0020's phases land, userTracking and
 * taskLog become additional entries in _sources() and nothing else here
 * changes.
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
     * Sources this viewer can read, as filter value => [label, api class].
     *
     * A method rather than a property so the labels can be wrapped in _().
     * xgettext extracts from the literal call site only -- _($someVariable)
     * builds its msgid at runtime, finds nothing in the catalog and returns
     * the string untranslated, silently and forever.
     *
     * @return array
     */
    private static function _sources()
    {
        return [
            'history' => [_('Administrative actions'), 'history'],
        ];
    }
    /**
     * The source key the request asked for, validated against the whitelist.
     *
     * Never the raw parameter: it reaches Route::listem() as a class name, so
     * an unrecognized value falls back to the default rather than being
     * passed through.
     *
     * @return string
     */
    private static function _requestedSource()
    {
        $keys = array_keys(self::_sources());
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

        $this->render(12, 'activity-table');

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
     * it afterwards, which is the thing ADR 0023 forbids -- these tables have
     * nothing ageing them out and grow for the life of the install.
     *
     * @return void
     */
    public function getList()
    {
        $sources = self::_sources();
        $source = self::_requestedSource();
        header('Content-type: application/json');
        Route::listem($sources[$source][1]);
        http_response_code(HTTPResponseCodes::HTTP_SUCCESS);
        echo Route::getData();
        exit;
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\ActivityManagement', 'ActivityManagement');
