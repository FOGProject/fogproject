<?php
/**
 * The audit log viewer.
 *
 * PHP version 7.4+
 *
 * @category AuditManagement
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
 * The audit log viewer.
 *
 * Read only, and that is structural rather than a UI choice. ADR 0021
 * Decision 8: `auditlog` and `auditchange` are deliberately absent from
 * Route::$validClasses, so neither has a create, update or delete route
 * anywhere in FOG. This page reads them by calling Route::listem() directly,
 * which builds a query and creates no route -- adding the classes to that
 * list to get a grid would have handed out the other nine operations with it.
 *
 * SEPARATE FROM ?node=activity, though both are log viewers. Activity is the
 * operational narrative -- what happened, for someone working out why a
 * machine did not image. This is the record of who was ALLOWED to do what,
 * including what they were refused, and it necessarily discloses attempted
 * usernames. ADR 0021 Decision 9 gives it its own `audit` node for exactly
 * that reason: `settings.edit` would have been the alternative, and six page
 * nodes map onto that one permission, so it is not a gate.
 *
 * @category AuditManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AuditManagement extends FOGPage
{
    /**
     * The node this page answers for.
     *
     * @var string
     */
    public $node = 'audit';
    /**
     * This grid does not select.
     *
     * Read only. `auditlog` and `auditchange` have no delete route anywhere in
     * FOG (ADR 0021 Decision 8), so there is nothing here for a selection to
     * act on.
     *
     * @var bool
     */
    public $selectable = false;
    /**
     * How many change rows one header may show.
     *
     * Bounded in the query, not in the browser. A create writes one row per
     * column and a plugin's model can carry a lot of them, so this is a
     * backstop rather than an expected ceiling -- the same rule the grid
     * follows and the one ADR 0023 states as HARD: nothing prunes these
     * tables and they grow for the life of the install.
     */
    const MAX_CHANGES = 500;
    /**
     * Initializes the audit page.
     *
     * @param string $name the name to construct with.
     */
    public function __construct($name = '')
    {
        $this->name = _('Audit Log');
        parent::__construct($this->name);
    }
    /**
     * Presents the audit list.
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
        $this->title = _('Audit Log');

        // Action and Permission sit next to each other on purpose. Action is
        // where the request came from (`access.api`, `access.page`,
        // `auth.login`) and on its own it says nothing about what happened --
        // every API write in the install reads `access.api`. Permission is
        // what was actually exercised (`host.delete`, `image.create`), and
        // without it in the grid the only way to tell a create from a delete
        // was to open the row.
        $this->headerData = [
            _('When'),
            _('Who'),
            _('Action'),
            _('Permission'),
            _('Outcome'),
            _('Subject'),
            _('From')
        ];
        $this->attributes = [
            [],
            [],
            [],
            [],
            [],
            [],
            []
        ];

        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Audit Log');
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';

        $this->render(12, 'audit-table');

        echo '</div>';
        echo '</div>';

        // One click opens the whole record: the header's remaining fields,
        // and the change rows hanging off it. The change rows are the only
        // part that costs a request, because a header can have hundreds of
        // them and the grid must not carry that weight on every draw.
        //
        // Dismiss only. Nothing here is editable -- see the class docblock --
        // so there is no commit button for it to sit to the left of.
        echo self::makeModal(
            'audit-modal',
            '<h4 class="card-title">'
            . _('Audit entry')
            . '</h4>',
            '<dl class="row" id="audit-detail"></dl>'
            . '<div id="audit-changes"></div>',
            self::makeButton(
                'audit-close',
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
     * so DataTables' start/length become a real SQL LIMIT. Ordered by id
     * rather than listem()'s default of `name`: an audit row has no name, and
     * id is the only column that orders the same way createdTime does without
     * ties between rows written in the same second.
     *
     * @return void
     */
    public function getList()
    {
        header('Content-type: application/json');
        Route::listem('auditlog', false, false, 'AND', 'id');
        http_response_code(HTTPResponseCodes::HTTP_SUCCESS);
        echo Route::getData();
        exit;
    }
    /**
     * Serves the change rows for one header.
     *
     * Each row names its own subject. A header can cover many of them --
     * an iterating save writes change rows for every object it touched --
     * and for a settings edit the subject is the only identifying part
     * there is, because globalSettings has one editable column and so
     * every such row's field reads `value`.
     *
     * A redacted row carries NULL in both value columns and `redacted = 1`;
     * the page says so rather than showing an empty cell, because "this
     * changed and you may not see what to" and "this changed to nothing" are
     * different facts. Redaction::values() is what put the NULLs there --
     * see ADR 0021 Decision 6.
     *
     * @return void
     */
    public function getChanges()
    {
        header('Content-type: application/json');
        $auditID = (int) Route::queryParam('id');
        $rows = [];
        if ($auditID > 0) {
            // LIMIT is a literal because it is a class constant, never
            // request input; the one value that comes from the request is
            // bound. Direct SQL rather than a manager read: this is a
            // narrow, always-the-same lookup on one indexed column, and the
            // ORM route would materialize an object per row to hand back
            // the six columns printed below.
            $rows = self::$DB->query(
                'SELECT `acSubjectType`, `acSubjectID`, `acSubjectLabel`, '
                . '`acField`, `acOldValue`, `acNewValue`, `acRedacted` '
                . 'FROM `auditChange` WHERE `acAuditID` = :id '
                . 'ORDER BY `acID` ASC LIMIT ' . self::MAX_CHANGES,
                [],
                [':id' => $auditID]
            )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        }
        $out = [];
        foreach ((array) $rows as $row) {
            $out[] = [
                'subjectType' => (string) ($row['acSubjectType'] ?? ''),
                'subjectID' => (int) ($row['acSubjectID'] ?? 0),
                // Empty on every row written before the label column
                // existed, and on a model with no `name` field. The page
                // falls back to type#id there, which is what it printed
                // for everything before this.
                'subjectLabel' => (string) ($row['acSubjectLabel'] ?? ''),
                'field' => (string) ($row['acField'] ?? ''),
                // NOT cast: NULL is the value a redacted row carries and it
                // has to survive to the browser as null, not as ''.
                'oldValue' => $row['acOldValue'],
                'newValue' => $row['acNewValue'],
                'redacted' => (int) ($row['acRedacted'] ?? 0)
            ];
        }
        http_response_code(HTTPResponseCodes::HTTP_SUCCESS);
        echo json_encode(
            [
                'changes' => $out,
                'truncated' => count($out) >= self::MAX_CHANGES
            ]
        );
        exit;
    }
}
