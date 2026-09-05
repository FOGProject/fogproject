<?php
/**
 * Shared FOGPageRender helpers for FOGPage subclasses.
 *
 * Holds the GET/read surface every *Management page renders: create/edit form builders, association/history/edit tab renderers, and the AJAX read endpoints that feed their tables.
 *
 * Extracted verbatim from FOGPage so the controller base stops growing
 * without bound. A trait's methods compile into the using class exactly as
 * if declared there (same $this, same access to inherited statics like
 * self::$HookManager), so behavior is identical and every existing call
 * site keeps resolving unchanged. The file carries the `.class.php` suffix
 * so the existing filename-keyed autoloader resolves `use FOGPageRender;`
 * with no autoloader change. Its basename is lowercase like every other
 * class file (GH-1136): the autoloader lowercases both the map key and the
 * lookup, so case buys nothing here and a CamelCase name collides with its
 * own lowercased copy after an --oldcopy upgrade.
 *
 * @category Page
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Base;

use FOG\Auth\Authorization;
use FOG\Auth\SiteScope;
use FOG\Items\Site;
use FOG\Items\TaskType;
use FOG\Managers\SiteManager;
use FOG\Managers\SnapinManager;
use FOG\Router\HTTPResponseCodes;
use FOG\Router\Route;

trait FOGPageRender
{
    /**
     * Renders a standard association tab: a primary box containing the
     * add/remove buttons, the server-side list table, and the dissociate
     * confirmation modal.
     *
     * @param string $tabSlug   node-sub slug (e.g. 'host-group') driving the
     *                          button ids, table id, and tab update URL
     * @param string $boxTitle  translated card title (e.g. _('Host Group Associations'))
     * @param string $colHeader translated first-column header (e.g. _('Group Name'))
     * @param string $delItem   singular item name passed to assocDelModal (e.g. 'group')
     * @param string $sendClass css class for the "Add selected" button. Kept as
     *                          an escape hatch, but every association tab now
     *                          takes the primary default: green read as a
     *                          different KIND of action on tabs that are doing
     *                          the same thing as their blue neighbors, and the
     *                          two were mixed even within one page (host printer
     *                          vs host group). Green stays for genuinely
     *                          different actions like Resume.
     * @param string $helpBlock optional translated help text rendered as
     *                          form-text in the card header (already escaped/safe)
     * @param string $createNode optional node owning a create form (e.g. 'group').
     *                          When given, the tab grows a "Create New X" button
     *                          and the modal it opens. See renderAssocCreate().
     * @param string $noun     optional display noun for that button, for the
     *                          nodes where ucfirst() reads badly -- 'usergroup'
     *                          becomes "Usergroup", 'ou' becomes "Ou". Forwarded
     *                          to renderAssocCreate(), which has always taken
     *                          one; without this pass-through a tab could only
     *                          reach it by calling that helper itself.
     * @param bool   $allowRemove whether the association can be broken at all.
     *                          Defaults true, which is every many-to-many tab:
     *                          a link row exists or it does not, and removing
     *                          it is meaningful. Pass false where membership is
     *                          a single-valued property of the row rather than
     *                          a link -- a storage node's group is a column on
     *                          the node, so there is no association to delete,
     *                          only one to repoint, and "remove" would have to
     *                          invent a group-less state that cannot be stored.
     *                          Suppresses the Remove button and the confirm
     *                          modal it opens, so the tab stops offering an
     *                          operation the model has no way to perform.
     * @param string $assocHeader optional header for the association column.
     *                          Defaults to "Associated", which is right for
     *                          a link row that either exists or does not.
     *                          The host Modules tab passes "State": a module
     *                          has three (on, off, unstated -- ADR 0038), and
     *                          a column headed "Associated" over a control
     *                          offering three answers describes something
     *                          else.
     *
     * @return void
     */
    protected function renderAssocTab(
        $tabSlug,
        $boxTitle,
        $colHeader,
        $delItem,
        $sendClass = 'btn btn-primary float-end',
        $helpBlock = '',
        $createNode = '',
        $noun = '',
        $allowRemove = true,
        $assocHeader = ''
    ) {
        $this->headerData = [
            $colHeader,
            '' !== $assocHeader ? $assocHeader : _('Associated')
        ];
        $this->attributes = [
            [],
            ['width' => 16]
        ];
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                $tabSlug,
                $this->obj->get('id')
            )
            . '" ';

        $buttons = self::makeButton(
            "$tabSlug-send",
            _('Add selected'),
            $sendClass,
            $props
        );
        if ($allowRemove) {
            $buttons .= self::makeButton(
                "$tabSlug-remove",
                _('Remove selected'),
                'btn btn-danger float-start',
                $props
            );
        }

        $createModal = '';
        if ($createNode !== '') {
            $createModal = self::renderAssocCreate(
                $tabSlug,
                $createNode,
                $buttons,
                $this->obj->get('id'),
                $noun
            );
        }

        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo $boxTitle;
        echo '</h4>';
        if ($helpBlock !== '') {
            echo '<p class="form-text">';
            echo $helpBlock;
            echo '</p>';
        }
        echo '</div>';
        echo '<div class="card-body">';
        $this->render(12, "$tabSlug-table", $buttons);
        echo '</div>';
        echo '<div class="card-footer">';
        // Without the button there is nothing to open this, and the confirm
        // modal's own Remove would post the same confirmdel if it were reached.
        echo $allowRemove ? $this->assocDelModal($delItem) : '';
        echo $createModal;
        echo '</div>';
        echo '</div>';
    }

    /**
     * Gives an association tab a "Create New X" button plus the modal it opens,
     * so the thing being associated can be made without leaving the page.
     *
     * Why this exists: admins work out of the host page as their central point,
     * and hitting a tab whose grid does not yet contain the thing they need
     * meant navigating away, creating it, and navigating back. This closes that
     * loop in place -- create, then associate to the entity being edited.
     *
     * The button joins the grid's own action row rather than opening a second
     * card below it: it is one more thing you can do to this list, so it belongs
     * with "Add selected"/"Remove selected". It is deliberately the SAME modal
     * the node's list page shows for its own create -- same header wording, same
     * Cancel/Create footer, same modal-lg -- so the form an admin already knows
     * does not look different depending on where it was opened from.
     *
     * The modal ships EMPTY and the browser pulls the real create form from the
     * target node's addModal endpoint (an AJAX request renders page-manager
     * output only, so that endpoint already answers chrome-free). The list page
     * can build its form inline because it IS that node's page; an edit page on
     * another node cannot -- FOGPage::__construct() redirects when the id does
     * not resolve, so instantiating GroupManagement from HostManagement is not
     * an option. Fetching also means the field list cannot drift from the create
     * page's own, and anything a plugin injects via {NODE}_ADD_FIELDS still
     * appears.
     *
     * ACCESS CONTROL: the button and modal are suppressed unless the acting user
     * holds the target node's create permission. This is presentation only and
     * grants nothing -- the create still POSTs to the real endpoint, which is
     * gated by Authorization::requirePagePermission() exactly as before. It is
     * here so a user who cannot create groups is not shown a form that would
     * only fail.
     *
     * PUBLIC STATIC, and the owner id is a parameter rather than being read from
     * $this->obj, so a PLUGIN can use it too. Plugins inject their tabs through
     * PLUGINS_INJECT_TABDATA from a Hook, which is not a FOGPage and has no
     * $this->obj -- it is handed the object being edited. Every plugin tab wants
     * exactly this button and this modal, so they call in here rather than each
     * hand-rolling markup the shared JS then has to keep guessing at.
     *
     * @param string $tabSlug    The association tab slug (e.g. 'host-group').
     * @param string $createNode The node owning the create form (e.g. 'group').
     * @param string $buttons    Action-row buttons, appended to by reference.
     * @param mixed  $ownerId    Id of the entity being edited (the association
     *                           target), used to build the tab update URL.
     * @param string $noun       Optional display name for the thing being
     *                           created. Defaults to the node capitalised,
     *                           which reads fine for group/printer/snapin but
     *                           not for every node -- 'ou' and 'windowskey'
     *                           would come out as "Ou" and "Windowskey".
     *
     * @return string The modal markup, for the caller to place in the footer.
     */
    public static function renderAssocCreate(
        $tabSlug,
        $createNode,
        &$buttons,
        $ownerId,
        $noun = ''
    ) {
        if (!Authorization::can($createNode . '.create')) {
            return '';
        }
        $label = _('Create New') . ' '
            . ('' !== $noun ? $noun : ucfirst(_($createNode)));
        // float-end so it sits on the right with "Add selected": creating is
        // non-destructive, and destructive actions stay left (the "Remove
        // selected" side) so destroying something takes deliberate travel.
        // Secondary rather than primary keeps "Add selected" the row's primary.
        // The node and the association endpoint ride on the button as data
        // attributes -- that is what the JS reads, so no URL is rebuilt there.
        $buttons .= self::makeButton(
            "$tabSlug-create",
            $label,
            'btn btn-secondary float-end',
            sprintf(
                ' type="button" data-create-node="%s" data-assoc-action="%s" ',
                \Initiator::e($createNode),
                \Initiator::e(
                    self::makeTabUpdateURL($tabSlug, $ownerId)
                )
            )
        );
        return self::makeModal(
            "$tabSlug-createModal",
            $label,
            sprintf(
                '<div id="%s-create-form"></div>',
                \Initiator::e($tabSlug)
            ),
            self::makeButton(
                "$tabSlug-create-cancel",
                _('Cancel'),
                'btn btn-outline-secondary float-start',
                ' type="button" data-bs-dismiss="modal" '
            )
            . self::makeButton(
                "$tabSlug-create-send",
                _('Create'),
                'btn btn-primary float-end',
                ' type="button" '
            ),
            '',
            'primary',
            'modal-lg'
        );
    }

    /**
     * Renders a simple display tab: a box-primary panel with a title and a
     * single DataTable. Shared by the group/host history tabs whose only
     * differences are the column set, the title text and the table id.
     *
     * @param array  $headerData The column headers (already translated).
     * @param array  $attributes The per-column attribute arrays.
     * @param string $title      The box title (already translated).
     * @param string $tableId    The DataTable element id.
     *
     * @return void
     */
    protected function renderHistoryTab(array $headerData, array $attributes, $title, $tableId)
    {
        $this->headerData = $headerData;
        $this->attributes = $attributes;
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo $title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        $this->render(12, $tableId);
        echo '</div>';
        echo '</div>';
    }

    /**
     * Streams a Login/Image history datatable payload as JSON.
     *
     * Shared by the host and group Login/Image history AJAX endpoints, which
     * differ only in the host scope (a single host id for a host, the member
     * host ids for a group) and the route resource to list.
     *
     * @param mixed  $scope the hostID scope (int for a host, array for a group)
     * @param string $route the Route resource to list (e.g. 'usertracking')
     *
     * @return void
     */
    protected function renderHistoryData($scope, $route)
    {
        header('Content-type: application/json');
        Route::listem(
            $route,
            ['hostID' => $scope]
        );
        echo Route::getData();
        exit;
    }

    /**
     * Streams the snapin-task history datatable payload as JSON.
     *
     * Shared by the host and group Snapin history AJAX endpoints; differs only
     * in the host scope. Returns an empty datatable payload (rather than an
     * unscoped lookup) when the scope has no snapin jobs.
     *
     * @param mixed $scope the hostID scope (int for a host, array for a group)
     *
     * @return void
     */
    protected function renderSnapinHistoryData($scope)
    {
        header('Content-type: application/json');
        $checkStates = [
            self::getCancelledState(),
            self::getCompleteState()
        ];

        $snapinJobs = self::positiveIntIds(
            Route::getIds('snapinjob', ['hostID' => $scope])
        );

        // If there are no jobs in scope, return an empty datatable payload and
        // avoid an unscoped snapintask lookup.
        if (count($snapinJobs) < 1) {
            $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
                [
                    'draw' => (int)filter_input(INPUT_POST, 'draw') ?: 0,
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                    'data' => [],
                    '_lang' => 'snapintask'
                ]
            ));
        }

        Route::listem(
            'snapintask',
            [
                'jobID' => $snapinJobs,
                'stateID' => $checkStates
            ]
        );

        echo Route::getData();
        exit;
    }

    /**
     * Builds a standard association list table via getItemsList using a LEFT
     * OUTER JOIN that flags which rows are already associated with the current
     * object.
     *
     * @param string $itemType     item class to list (e.g. 'group')
     * @param string $listType     list/association type key (e.g. 'groupassociation')
     * @param string $assocTable   join table (e.g. 'groupMembers')
     * @param string $itemKey      listed item's key column (e.g. '`groups`.`groupID`')
     * @param string $assocItemKey join table's item column (e.g. '`groupMembers`.`gmGroupID`')
     * @param string $ownerKey     join table's owner column (e.g. '`groupMembers`.`gmHostID`')
     * @param array  $columns      extra association column definition(s)
     *
     * @return void
     */
    protected function assocItemsList(
        $itemType,
        $listType,
        $assocTable,
        $itemKey,
        $assocItemKey,
        $ownerKey,
        array $columns
    ) {
        $join = [
            "LEFT OUTER JOIN `$assocTable` ON "
            . "$itemKey = $assocItemKey "
            . "AND $ownerKey = '" . $this->obj->get('id') . "'"
        ];
        return $this->obj->getItemsList(
            $itemType,
            $listType,
            $join,
            '',
            $columns
        );
    }

    /**
     * Renders a stored datetime for display, or the word "Never".
     *
     * NULL means the event has never happened, which is a different fact
     * from "it happened at the zero date" -- so it gets its own word rather
     * than an empty box the reader has to interpret. validDate() also
     * catches the 0000-00-00 spelling, which a column added at step 353
     * cannot hold but which a hand-edited database still can.
     *
     * Lived on HostManagement as _lastSeenText() until the edit info card
     * needed the same guard for an image's capture date. Every empty-date
     * column in the schema reaches a page this way, so the guard belongs
     * next to the other shared renderers rather than on one page.
     *
     * @param string|null $value  The stored datetime, or null.
     * @param string      $table  The table it came out of.
     * @param string      $column The column it came out of.
     *
     * @return string
     */
    /**
     * Whether dateOrNever() has marked anything on this page as predating the
     * UTC boundary.
     *
     * Per RENDER, not per value: the marker goes beside each affected date,
     * the sentence explaining it appears once. A page that shows five dates
     * of which one is old should not say the same thing five times.
     *
     * Never reset. dateOrNever() runs while a page builds its notes and
     * renderInfoCard() runs afterward, both inside one request, so there is
     * exactly one render to remember.
     *
     * @var bool
     */
    protected static $_unadjustedSeen = false;
    protected static function dateOrNever($value, $table = '', $column = '')
    {
        if (!$value || !self::validDate($value)) {
            return _('Never');
        }

        // toDisplayStored(), not niceDate(): niceDate reads the value in the
        // zone it was STORED in and formatting it straight back hands you the
        // same string you started with, so the viewer's zone would never be
        // applied anywhere a date is shown on a form.
        //
        // The table and column decide whether the value can predate the UTC
        // boundary: a TIMESTAMP column has always held a UTC instant, a
        // DATETIME one holds whatever clock wrote it. Unhinted callers are
        // treated as DATETIME, which is what all five in core are and what a
        // page adding a sixth is overwhelmingly likely to be.
        $type = '' === $table
            ? 'datetime'
            : strtolower(trim((string)self::columnType($table, $column)));

        $isDatetime = 0 !== strpos($type, 'timestamp');
        $out = self::toDisplayStored($value, $isDatetime)
            ->format('Y-m-d H:i:s');

        // A pre-boundary value is real and usually means exactly what the
        // reader thinks; what it is NOT is UTC, and nothing else on the page
        // says so. Mark it and remember that we did, so renderInfoCard() can
        // explain the marker once at the foot of the card rather than
        // repeating a sentence beside every date.
        if (\FOG\Base\StorageEpoch::isPreBoundary($value, $isDatetime)) {
            self::$_unadjustedSeen = true;
            $out .= \FOG\Base\StorageEpoch::MARKER;
        }

        return $out;
    }

    /**
     * Renders the edit page's info card: the record's identity and the few
     * facts about it you cannot see from whichever tab you are on.
     *
     * 1.5 had this as an "Info" dropdown at the head of the tab strip
     * (FOGSubMenu::addNotes), because that strip lived in a col-xs-3 sidebar
     * with no room for anything else. 1.6's tab card is full width, and the
     * whole value of this block is context that SURVIVES a tab switch -- a
     * dropdown that closes on the next click gives you one glance per click,
     * which is the thing it was supposed to save you. So it is a static card
     * above the tabs instead.
     *
     * Full width rather than a sidebar column for the same reason the tab
     * card is full width: a col-3 sidebar would squeeze every tab body on
     * the page (the host page alone has six DataTables grids) for the whole
     * page height, to keep five lines on screen. At md and below a sidebar
     * column stacks to the top anyway, so it would only ever differ on
     * desktop.
     *
     * Plain .card, not card-primary card-outline like the tab card below it:
     * two identically accented outlined cards stacked read as one confused
     * object, and the tabs should keep the visual weight.
     *
     * Values are escaped. 1.5's fixTitle() only collapsed whitespace, so
     * host names, image names and file paths went into that panel raw; that
     * also means these entries are text, and a page wanting markup here has
     * to grow an explicit opt-in rather than smuggling it through a value.
     *
     * @return void
     */
    /**
     * Builds the data- attributes tying one info-card note to a form control.
     *
     * Accepts either a bare CSS selector, or an array carrying the two labels
     * a checkbox toggles between:
     *
     *     '#maxClients'
     *     ['sel' => '#isMaster', 'on' => _('Master'), 'off' => _('Member')]
     *
     * The on/off labels are built here rather than in JS because they are
     * translated strings -- gettext runs server side, and a msgid assembled in
     * JS would never make it into the .pot.
     *
     * @param mixed $source One entry from $this->noteSources, or null.
     *
     * @return string The attributes, leading space included, or ''.
     */
    protected static function noteSourceAttrs($source)
    {
        if (is_string($source)) {
            $source = ['sel' => $source];
        }
        if (!is_array($source) || !($source['sel'] ?? '')) {
            return '';
        }
        $attrs = ' data-note-src="' . \Initiator::e($source['sel']) . '"';
        foreach (['on', 'off'] as $state) {
            if (isset($source[$state])) {
                $attrs .= ' data-note-' . $state . '="'
                    . \Initiator::e($source[$state]) . '"';
            }
        }
        return $attrs;
    }

    /**
     * The info card's one-click task buttons.
     *
     * The host and group LIST grids have carried these since
     * HostManagement::_quickTaskItems(): the two or three task types that
     * take no options, fired straight at the create endpoint instead of
     * fetching an options form only to post it back untouched. This is the
     * same affordance on the edit pages, so an admin already looking at a
     * host or a group does not have to go back to the grid, find the row
     * again and tick it to do the obvious thing to it.
     *
     * Deploy and Capture for a host, Deploy and Multi-Cast for a group --
     * the same pairing _quickTaskItems() documents, and for the same
     * reason: those are the types that need no options, which is the whole
     * reason they can be one click.
     *
     * btn-secondary, and neither of them primary. Nothing here is the card's
     * commit action -- the General tab's Update is -- so these are two
     * shortcuts in a header strip, not a decision cluster in a form footer,
     * and the weight a red button would carry is carried by the
     * confirmation instead. It is also what the list grid's own quick
     * buttons are, since DataTables draws its button bar that way.
     *
     * Filled, NOT btn-outline-secondary, and that is a contrast decision
     * rather than a taste one. Outline keeps #6c757d as the TEXT color, and
     * against the dark card (#212529) that is 3.29:1 -- under the 4.5:1 AA
     * floor for body-sized text. Filled puts white on #6c757d instead and
     * holds 4.69:1 in both themes. Measured against the shipped
     * adminlte4.min.css + fog-default-ui.min.css, not assumed.
     *
     * @param string $node    Page node, e.g. 'host' or 'group'. Also decides
     *                        the permission: ?node=X&sub=deploy resolves to
     *                        X.task via Authorization::_subToAction(), so
     *                        the gate here and the gate the POST hits are
     *                        the same string by construction.
     * @param int    $id      The entity being edited.
     * @param array  $typeIds TaskType ids, in the order they should appear.
     * @param string $target  Already-translated description of what the task
     *                        lands on, e.g. 'host "foo"'. Interpolated into
     *                        the confirmation. Built by the caller because
     *                        only the caller knows whether it is one machine
     *                        or a group of them.
     *
     * @return string The button group markup, or '' if none may be shown.
     */
    public static function renderQuickTaskActions(
        $node,
        $id,
        array $typeIds,
        $target
    ) {
        // Same refusal _quickTaskItems() makes: a user without the
        // permission would be shown buttons whose POST can only be denied.
        if (!Authorization::can($node . '.task')) {
            return '';
        }

        $buttons = '';
        foreach ($typeIds as $typeId) {
            $TaskType = new TaskType($typeId);
            // A server whose taskTypes row was deleted simply loses that
            // button, the same way the accordion and the grid lose theirs.
            if (!$TaskType->isValid()) {
                continue;
            }
            $name = (string)$TaskType->get('name');
            // Built here, not in the script. gettext runs server side, so a
            // sentence assembled in JS would never reach the .pot -- the
            // same reason noteSourceAttrs() builds its on/off labels here.
            $confirm = sprintf(
                _('Create a %1$s task for %2$s?'),
                $name,
                $target
            );
            $buttons .= self::makeButton(
                'quicktask-' . \Initiator::e($node) . '-' . (int)$TaskType->get('id'),
                '<i class="fas fa-' . \Initiator::e((string)$TaskType->get('icon'))
                . '"></i> ' . \Initiator::e($name),
                'btn btn-secondary fog-quicktask',
                'type="button"'
                . ' data-node="' . \Initiator::e($node) . '"'
                . ' data-id="' . (int)$id . '"'
                . ' data-type="' . (int)$TaskType->get('id') . '"'
                . ' data-confirm="' . \Initiator::e($confirm) . '"'
            );
        }
        if ('' === $buttons) {
            return '';
        }

        return '<div class="btn-group" role="group" aria-label="'
            . \Initiator::e(_('Quick tasks'))
            . '">' . $buttons . '</div>';
    }

    protected function renderInfoCard()
    {
        $notes = (array)$this->notes;
        $sources = (array)$this->noteSources;
        $actions = (string)$this->noteActions;
        // Mirrors PLUGINS_INJECT_TABDATA in tabFields(): a plugin that adds
        // a tab to a core page can add its line here too. 1.5's equivalent
        // rode SUB_MENULINK_DATA, which 1.6 repurposed for the sidebar node
        // menu, so there is no back-compat name to keep.
        //
        // 'actions' rides the same event rather than getting one of its own:
        // a plugin adding a button to this card is doing the same thing as a
        // plugin adding a line to it, and a second event would mean two
        // registrations for one card.
        self::$HookManager->processEvent(
            'EDIT_INFO_DATA',
            [
                'notes' => &$notes,
                'noteSources' => &$sources,
                'noteActions' => &$actions,
                'obj' => &$this->obj
            ]
        );
        // Either half is enough to be worth drawing. A page with buttons and
        // no notes is unusual but not wrong, and returning early on the note
        // count alone would silently drop the buttons.
        if (!count($notes) && '' === trim($actions)) {
            return;
        }
        echo '<div class="card mb-3" id="edit-info-card">';
        echo '<div class="card-body py-2">';
        echo '<div class="row row-cols-auto gx-5 gy-2">';
        foreach ($notes as $label => $value) {
            $value = (string)($value ?? '');
            echo '<div class="col">';
            echo '<div class="small text-secondary text-uppercase">';
            echo \Initiator::e($label);
            echo '</div>';
            // Where the page has told us which control this note mirrors,
            // hand the selector to the client so it can repaint the note as
            // the control changes. Without this the card silently disagrees
            // with the form as soon as you type in it, and only a full page
            // reload puts it right.
            echo '<div class="fw-semibold"' . self::noteSourceAttrs(
                $sources[$label] ?? null
            ) . '>';
            // An em dash rather than dropping the entry: a host with no
            // deploy date is telling you something, and a card whose fields
            // come and go is harder to read than one that always says the
            // same things. Muted, matching how the grids already draw an
            // empty cell (Route's imageLink, lastping, deployed).
            echo $value === '' ?
                '<span class="text-muted">&mdash;</span>' :
                \Initiator::e($value);
            echo '</div>';
            echo '</div>';
        }
        // Last in the row and pushed to the far edge with ms-auto, so the
        // buttons sit clear of the notes however many notes there are. A
        // flex auto margin, not a float: the row is display:flex and a float
        // would do nothing here.
        if ('' !== trim($actions)) {
            echo '<div class="col ms-auto d-flex align-items-center"'
                . ' id="edit-info-actions">';
            echo $actions;
            echo '</div>';
        }
        echo '</div>';
        // Once, and only when something above actually carries the marker.
        // A standing disclaimer on every edit page would be noise on the
        // overwhelming majority of them, where every date is post-boundary.
        if (self::$_unadjustedSeen) {
            echo '<div class="small text-secondary mt-2" id="edit-info-unadjusted">';
            echo \Initiator::e(
                trim(\FOG\Base\StorageEpoch::MARKER)
                . ' ' . \FOG\Base\StorageEpoch::note()
            );
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
    }

    /**
     * Shared scaffold for the standard edit() tab pages.
     *
     * Sets the canonical "Edit: <name> ID: <id>" page title from the
     * loaded $this->obj and echoes the assembled tab markup. The page's
     * own edit() keeps building its page-specific $tabData entries and
     * hands the finished array here; the title and the tabFields() echo
     * are the only shared bookends, so they live here.
     *
     * The $obj argument is passed straight through to tabFields() with
     * the same -1 default, preserving its three behaviors: -1 rebuilds
     * the entity from the node/id globals, an explicit object uses it as
     * given, and false skips the TABDATA/plugin hook injection. The page
     * title always derives from $this->obj regardless of $obj.
     *
     * @param array $tabData The page's assembled tab definitions.
     * @param mixed $obj     tabFields() obj arg: -1 (default), an entity,
     *                       or false. Does not affect the title.
     *
     * @return void
     */
    protected function renderEditTabs(array $tabData, $obj = -1)
    {
        $this->title = sprintf(
            '%s: %s %s: %s',
            _('Edit'),
            $this->obj->get('name'),
            _('ID'),
            $this->obj->get('id')
        );
        $this->renderInfoCard();
        echo self::tabFields($tabData, $obj);
    }

    /**
     * Renders the standard create-form scaffold shared by the add() pages:
     * a CSRF-protected horizontal form wrapping a box-solid that holds one or
     * more titled box-primary sections, with the action buttons in the footer.
     *
     * @param string $idBase   Id prefix; yields form id "<idBase>-create-form"
     *                         and container id "<idBase>-create".
     * @param array  $sections List of [title, body] pairs; each renders as a
     *                         box-primary (one for most pages, two for hosts).
     * @param string $buttons  Pre-rendered footer buttons.
     * @param string $enctype  Form enctype (default urlencoded; snapin uses
     *                         multipart/form-data).
     *
     * @return void
     */
    protected function renderCreateForm(
        $idBase,
        array $sections,
        $buttons,
        $enctype = 'application/x-www-form-urlencoded'
    ) {
        echo self::makeFormTag(
            '',
            $idBase . '-create-form',
            $this->formAction,
            'post',
            $enctype,
            true
        );
        echo '<div class="card" id="' . $idBase . '-create">';
        echo '<div class="card-body">';
        foreach ($sections as $section) {
            echo '<div class="card card-primary card-outline">';
            echo '<div class="card-header">';
            echo '<h4 class="card-title">';
            echo $section[0];
            echo '</h4>';
            echo '</div>';
            echo '<div class="card-body">';
            echo $section[1];
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
        echo '<div class="card-footer">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }

    /**
     * Renders the standard Edit -> General tab form.
     *
     * Wraps the verbatim tail shared by every full-CRUD page's xxxGeneral():
     * a tab-update form tag, a single card with the pre-rendered fields in the
     * body, and a footer holding the action buttons plus the delete modal.
     *
     * @param string $idBase   id base (e.g. 'module' -> 'module-general-form')
     * @param string $rendered pre-rendered form fields (self::formFields(...))
     * @param string $buttons  the footer action buttons markup
     * @param string $enctype  form enctype, default urlencoded
     *
     * @return void
     */
    protected function renderGeneralForm(
        $idBase,
        $rendered,
        $buttons,
        $enctype = 'application/x-www-form-urlencoded'
    ) {
        echo self::makeFormTag(
            '',
            $idBase . '-general-form',
            self::makeTabUpdateURL(
                $idBase . '-general',
                $this->obj->get('id')
            ),
            'post',
            $enctype,
            true
        );
        echo '<div class="card">';
        echo '<div class="card-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="card-footer">';
        echo $buttons;
        echo $this->deleteModal();
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }

    /**
     * Renders the standard "Create New X" page form.
     *
     * Wraps the near-identical add() body shared by nearly every management
     * page: set the page title, build the create-form fields via _addFields(),
     * add the uniform Create button, fire the page's *_ADD_FIELDS hook (with
     * the fields, buttons, and the entity class in the payload), then hand the
     * single titled section off to renderCreateForm().
     *
     * The section title shown above the fields is the same text as the page
     * title, matching every page that used this template.
     *
     * @param string      $idBase      renderCreateForm id base (e.g. 'group')
     * @param string      $title       page + section title (already _()'d)
     * @param string      $hookEvent   the *_ADD_FIELDS event name to fire
     * @param string      $entityKey   payload key for the entity class
     * @param string|null $entityClass class to instantiate (defaults to key)
     * @param string      $enctype     form enctype, default urlencoded
     *
     * @return void
     */
    protected function renderAddForm(
        $idBase,
        $title,
        $hookEvent,
        $entityKey,
        $entityClass = null,
        $enctype = 'application/x-www-form-urlencoded'
    ) {
        $this->title = $title;

        $fields = $this->_addFields();

        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary float-end'
        );

        self::$HookManager->processEvent(
            $hookEvent,
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                $entityKey => self::getClass($entityClass ?? $entityKey)
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        $this->renderCreateForm(
            $idBase,
            [[$title, $rendered]],
            $buttons,
            $enctype
        );
    }

    /**
     * Renders the standard create form fragment used inside the "add" modal.
     *
     * Wraps the near-identical addModal() body shared by nearly every
     * management page: build the create-form fields via _addFields(), fire the
     * page's *_ADD_FIELDS hook (fields + entity class, no buttons), then echo a
     * bare form tag, the rendered fields, and the closing tag.
     *
     * @param string      $node        URL node for the form action target
     * @param string      $hookEvent   the *_ADD_FIELDS event name to fire
     * @param string      $entityKey   payload key for the entity class
     * @param string|null $entityClass class to instantiate (defaults to key)
     * @param string      $enctype     form enctype, default urlencoded
     * @param callable    $extra       optional callback returning extra HTML
     *                                 to emit inside the form after the fields
     *                                 (e.g. host's Active Directory section)
     *
     * @return void
     */
    protected function renderAddModalForm(
        $node,
        $hookEvent,
        $entityKey,
        $entityClass = null,
        $enctype = 'application/x-www-form-urlencoded',
        $extra = null
    ) {
        $fields = $this->_addFields();

        self::$HookManager->processEvent(
            $hookEvent,
            [
                'fields' => &$fields,
                $entityKey => self::getClass($entityClass ?? $entityKey)
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            '',
            'create-form',
            '../management/index.php?node=' . $node . '&sub=add',
            'post',
            $enctype,
            true
        );
        echo $rendered;
        if (is_callable($extra)) {
            echo $extra();
        }
        echo '</form>';
    }

    /**
     * Builds the Basic / Power / Advanced task-type accordion.
     *
     * `taskTypes` already carries both axes this needs. `ttIsAdvanced`
     * splits basic from advanced -- Deploy and Multi-Cast are basic,
     * everything from Memtest to Password Reset is advanced -- and
     * `ttIsAccess` says which kind of target a type applies to: `host`,
     * `group`, or `both`. Neither is derived here; the caller passes the
     * access values it wants and gets back the accordion.
     *
     * The anchor is the caller's, because the users of this need different
     * links from the same rows: the host edit page tasks one host and names
     * it in the URL, while the list tasks whatever is selected and has to
     * defer that to the browser. Everything around the anchor -- the
     * collapsible cards, the striped tables, the hooks a plugin adds rows
     * through -- is the same either way.
     *
     * THE POWER PANE, AND WHY IT SITS IN THE MIDDLE. Shut down, restart and
     * wake are tasks in every sense that matters: each acts on the machines
     * you have selected at the moment you press it, and none of them is a
     * standing statement about anything. Two of the three had nowhere to be
     * asked for except a single host's Power Management tab, so there was no
     * way to shut down a selection at all; the third, Wake-Up, was a task
     * type filed under Advanced beside Memtest and the disk wipes, which is
     * not where anyone looks for it.
     *
     * So the three are collected into one pane between Basic and Advanced.
     * Wake-Up MOVES here rather than being duplicated -- it is excluded from
     * whichever of the other two panes `ttIsAdvanced` would have put it in --
     * and it is still rendered with the caller's own anchor, so it queues
     * exactly the task it always did. The pane appears only when the caller
     * supplies $powerAnchor, so a surface that cannot carry out an immediate
     * power action does not offer one.
     *
     * @param array         $access       ttIsAccess values to include.
     * @param callable      $anchor       fn(stdClass $TaskType): string.
     * @param string        $accordionId  DOM id for the accordion wrapper.
     * @param string        $basicHook    Event fired with the basic rows.
     * @param string        $advancedHook Event fired with the advanced rows.
     * @param callable|null $powerAnchor  fn(string $action, string $label,
     *                                    string $icon): string, the link for
     *                                    shutdown and reboot. Null omits the
     *                                    pane entirely.
     *
     * @return string The accordion markup.
     */
    protected function taskTypeAccordion(
        array $access,
        callable $anchor,
        $accordionId,
        $basicHook = '',
        $advancedHook = '',
        callable $powerAnchor = null
    ) {
        $items = Route::getList(
            'tasktype',
            ['access' => $access],
            'AND',
            'id'
        );

        $power = [];
        if (null !== $powerAnchor) {
            // Icons chosen from the set the task types already use, so the
            // pane does not look like it came from somewhere else.
            $power[$powerAnchor('shutdown', _('Shut Down'), 'power-off')] = _(
                'Shuts down the selected machines. The FOG client carries '
                . 'this out at its next check-in, so a machine that is off, '
                . 'or has no client installed, is unaffected.'
            );
            $power[$powerAnchor('reboot', _('Restart'), 'arrow-rotate-right')]
                = _(
                    'Restarts the selected machines. As with Shut Down, the '
                    . 'FOG client carries this out at its next check-in.'
                );
        }

        $panes = [];
        foreach ([0, 1] as $advanced) {
            $data = [];
            foreach ($items as $TaskType) {
                if ($advanced != $TaskType->isAdvanced) {
                    continue;
                }
                // Wake-Up belongs with the other two power actions, not in
                // whichever pane ttIsAdvanced happens to put it in. Moved
                // rather than copied: offering the same task twice in one
                // accordion is worse than offering it in the wrong place.
                if (count($power) > 0
                    && TaskType::WAKE_UP == $TaskType->id
                ) {
                    $power[$anchor($TaskType)] = $TaskType->description;
                    continue;
                }
                $data[$anchor($TaskType)] = $TaskType->description;
            }
            $hook = $advanced ? $advancedHook : $basicHook;
            if ($hook) {
                self::$HookManager->processEvent($hook, ['data' => &$data]);
            }
            $panes[$advanced] = self::stripedTable($data);
        }

        // Basic, Power, Advanced. The order is the answer to "what am I most
        // likely to want": imaging first, the three one-click power actions
        // next, and the things that need thinking about last.
        $cards = [
            [
                'id' => 'Basic',
                'title' => _('Basic Tasks'),
                'class' => 'primary',
                'body' => $panes[0],
                'open' => true
            ]
        ];
        if (count($power) > 0) {
            $cards[] = [
                'id' => 'Power',
                'title' => _('Power'),
                'class' => 'info',
                'body' => self::stripedTable($power),
                'open' => false
            ];
        }
        $cards[] = [
            'id' => 'Advanced',
            'title' => _('Advanced Tasks'),
            'class' => 'warning',
            'body' => $panes[1],
            'open' => false
        ];

        ob_start();
        echo '<div id="' . $accordionId . '">';
        foreach ($cards as $card) {
            $paneId = $accordionId . $card['id'];
            echo '<div class="card card-'
                . $card['class']
                . ' card-outline">';
            echo '<div class="card-header">';
            echo '<h4 class="card-title">';
            echo '<a href="#' . $paneId . '" class="" data-bs-toggle="collapse" '
                . 'data-bs-parent="#' . $accordionId . '">';
            echo $card['title'];
            echo '</a>';
            echo '</h4>';
            echo '</div>';
            echo '<div id="' . $paneId . '" class="collapse'
                . ($card['open'] ? ' show' : '')
                . '">';
            echo '<div class="card-body">';
            echo '<table class="table table-striped">';
            echo '<tbody>';
            echo $card['body'];
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';

        return ob_get_clean();
    }

    /**
     * Builds the task-option form fields shared by every create-task form:
     * the snapin picker, the password-reset account, and the abort/bitlocker/
     * shutdown/wake/debug checkboxes. The caller adds scheduleTypeFields()
     * after these, and owns the form tag and its own hook.
     *
     * Extracted from HostManagement::deploy() and GroupManagement::deploy(),
     * which carried the same 200 lines twice and had already drifted: only
     * the host copy offered the bitlocker bypass. Keeping one copy is what
     * lets deployMulti() exist without a third. The bitlocker block is gated
     * on the task being a capture, and a group cannot capture, so folding
     * the two together adds nothing to a group's form.
     *
     * Takes the type ID rather than a task-type object on purpose. The two
     * callers hold two different things under the same name -- deploy() on
     * the host page holds the decoded API row Route::getItem() answers with,
     * where the predicates are properties, and the group page holds the
     * model, where they are methods. Building the model here is what lets
     * one body serve both.
     *
     * @param int    $type       The task type id.
     * @param string $labelClass The label class used by the deploy form.
     *
     * @return array The field fragment to fastmerge onto the form fields.
     */
    protected function taskingOptionFields($type, $labelClass)
    {
        $TaskType = new TaskType($type);
        $issnapintask = $TaskType->isSnapinTasking();
        $isinitneeded = $TaskType->isInitNeededTasking();
        $iscapturetask = $TaskType->isCapture();
        $isdebug = $TaskType->isDebug();

        $fields = [];

        if ($issnapintask
            && TaskType::SINGLE_SNAPIN == $type
        ) {
            $fields[
                self::makeLabel(
                    $labelClass,
                    'snapin',
                    _('Select Snapin to run')
                )
            ] = (new SnapinManager())
                ->buildSelectBox('', 'snapin');
        } elseif (TaskType::PASSWORD_RESET == $type) {
            $fields[
                self::makeLabel(
                    $labelClass,
                    'account',
                    _('Account Name')
                )
            ] = self::makeInput(
                'form-control',
                'account',
                _('Administrator'),
                'text',
                'account',
                '',
                true
            );
        }
        if ($TaskType->isSnapinTask()) {
            $fields = self::fastmerge(
                $fields,
                [
                    self::makeLabel(
                        $labelClass,
                        'snapinAbortOnFailure',
                        _('Abort snapin sequence on failure')
                    ) => self::makeInput(
                        '',
                        'snapinAbortOnFailure',
                        '',
                        'checkbox',
                        'snapinAbortOnFailure'
                    )
                ]
            );
        }
        if ($isinitneeded) {
            if ($iscapturetask) {
                $fields = self::fastmerge(
                    $fields,
                    [
                        self::makeLabel(
                            $labelClass,
                            'bitlocker',
                            _('Bypass Bitlocker Detection')
                        ) => self::makeInput(
                            '',
                            'bitlocker',
                            '',
                            'checkbox',
                            'bitlocker',
                            '',
                            false,
                            false,
                            -1,
                            -1,
                            ''
                        )
                    ]
                );
            }
            if (!$isdebug) {
                $shutdownchecked = self::getSetting(
                    'FOG_TASKING_ADV_SHUTDOWN_ENABLED'
                ) ? ' checked' : '';
                $fields = self::fastmerge(
                    $fields,
                    [
                        '<div class="hideFromDebug deploy-field-group">'
                        . self::makeLabel(
                            $labelClass,
                            'shutdown',
                            _('Shutdown when complete')
                        ) => self::makeInput(
                            '',
                            'shutdown',
                            '',
                            'checkbox',
                            'shutdown',
                            '',
                            false,
                            false,
                            -1,
                            -1,
                            $shutdownchecked
                        )
                        . '</div>'
                    ]
                );
            }
        }
        if (TaskType::WAKE_UP != $type) {
            $wolchecked = self::getSetting(
                'FOG_TASKING_ADV_WOL_ENABLED'
            ) ? ' checked' : '';
            $fields = self::fastmerge(
                $fields,
                [
                    self::makeLabel(
                        $labelClass,
                        'wol',
                        _('Wake Up')
                    ) => self::makeInput(
                        '',
                        'wol',
                        '',
                        'checkbox',
                        'wol',
                        '',
                        false,
                        false,
                        -1,
                        -1,
                        $wolchecked
                    )
                ]
            );
        }
        if (TaskType::PASSWORD_RESET != $type
            && !$isdebug
            && $isinitneeded
        ) {
            $debugchecked = self::getSetting(
                'FOG_TASKING_ADV_DEBUG_ENABLED'
            ) ? ' checked' : '';
            $fields = self::fastmerge(
                $fields,
                [
                    self::makeLabel(
                        $labelClass,
                        'checkdebug',
                        _('Debug Task')
                    ) => self::makeInput(
                        '',
                        'isDebugTask',
                        '',
                        'checkbox',
                        'checkdebug',
                        '',
                        false,
                        false,
                        -1,
                        -1,
                        $debugchecked
                    )
                ]
            );
        }

        return $fields;
    }

    /**
     * Builds the schedule-type form fields shared by the host/group deploy()
     * create-task forms: the always-present "Schedule Immediately" radio plus,
     * unless this is a debug or password-reset task, the "Schedule Later"
     * (single) and "Schedule Crontab Style" (cron) inputs.
     *
     * @param string $labelClass The label class used by the deploy form.
     * @param bool   $isdebug    Whether this is a debug-session task.
     * @param int    $type       The task type id (to suppress for resets).
     *
     * @return array The field fragment to fastmerge onto the form fields.
     */
    protected function scheduleTypeFields($labelClass, $isdebug, $type)
    {
        $fields = [
            self::makeLabel(
                $labelClass,
                'instant',
                _('Schedule Immediately')
            ) => self::makeInput(
                'instant',
                'scheduleType',
                '',
                'radio',
                'instant',
                'instant',
                false,
                false,
                -1,
                -1,
                ' checked'
            )
        ];
        if (!$isdebug
            && TaskType::PASSWORD_RESET != $type
        ) {
            $fields = self::fastmerge(
                $fields,
                [
                    '<div class="hideFromDebug deploy-field-group">'
                    . self::makeLabel(
                        $labelClass,
                        'delayed',
                        _('Schedule Later')
                    ) => self::makeInput(
                        'delayed',
                        'scheduleType',
                        '',
                        'radio',
                        'delayed',
                        'single'
                    )
                    . '</div>',
                    '<div class="delayedinput d-none deploy-field-group">'
                    . self::makeLabel(
                        $labelClass,
                        'delayedinput',
                        _('Start Time')
                    ) => self::makeInput(
                        'form-control',
                        'scheduleSingleTime',
                        self::niceDate()->format('Y-m-d H:i:s'),
                        'text',
                        'delayedinput',
                        ''
                    )
                    . '</div>',
                    '<div class="hideFromDebug deploy-field-group">'
                    . self::makeLabel(
                        $labelClass,
                        'cron',
                        _('Schedule Crontab Style')
                    ) => self::makeInput(
                        'croninput',
                        'scheduleType',
                        '',
                        'radio',
                        'cron',
                        'cron'
                    )
                    . '</div>',
                    '<div class="croninput d-none deploy-field-group">'
                    . self::makeLabel(
                        $labelClass,
                        'cronMin',
                        _('Cron Entry')
                    ) => '<div class="croninput fogcron d-none"></div><br/>'
                    . self::makeInput(
                        'col-sm-2 croninput cronmin d-none',
                        'scheduleCronMin',
                        _('min'),
                        'text',
                        'cronMin'
                    )
                    . self::makeInput(
                        'col-sm-2 croninput cronhour d-none',
                        'scheduleCronHour',
                        _('hour'),
                        'text',
                        'cronHour'
                    )
                    . self::makeInput(
                        'col-sm-2 croninput crondom d-none',
                        'scheduleCronDOM',
                        _('day'),
                        'text',
                        'cronDom'
                    )
                    . self::makeInput(
                        'col-sm-2 croninput cronmonth d-none',
                        'scheduleCronMonth',
                        _('month'),
                        'text',
                        'cronMonth'
                    )
                    . self::makeInput(
                        'col-sm-2 croninput crondow d-none',
                        'scheduleCronDOW',
                        _('weekday'),
                        'text',
                        'cronDow'
                    )
                    . '</div>'
                ]
            );
        }
        return $fields;
    }

    /**
     * Site membership map for the per-object Site tab.
     *
     * node => [member route, object id field, manager class]. The
     * manager is spelled out rather than derived: ucfirst() on the route
     * yields Sitehostmember, not SiteHostMember, and getClass() would fail
     * to resolve it only at the moment somebody saves the tab.
     *
     * Kept here rather than read
     * from SiteScope: that map is table and column names for the boundary
     * queries, this one is route names for the ORM, and collapsing them
     * would tie a security lookup to a presentation detail.
     *
     * @var array
     */
    protected static $siteTabMap = [
        'host' => ['sitehostmember', 'hostID', 'SiteHostMemberManager'],
        'user' => ['siteusermember', 'userID', 'SiteUserMemberManager'],
        'group' => ['sitegroupmember', 'groupID', 'SiteGroupMemberManager'],
        'usergroup' => [
            'siteusergroupmember',
            'usergroupID',
            'SiteUserGroupMemberManager'
        ]
    ];
    /**
     * The site ids an object currently belongs to.
     *
     * @param string $node     the owning node
     * @param int    $objectID the object id
     *
     * @return array int site ids
     */
    protected static function siteIDsFor($node, $objectID)
    {
        if (!isset(self::$siteTabMap[$node]) || (int)$objectID < 1) {
            return [];
        }
        list($route, $field) = self::$siteTabMap[$node];
        return array_map(
            'intval',
            (array)Route::getIds(
                $route,
                [$field => (int)$objectID],
                'siteID'
            )
        );
    }
    /**
     * The Site field for a "Create New X" form, or nothing if this install
     * has no sites at all.
     *
     * Creating an object and then having to open it again to say where it
     * lives is the shape the site plugin had, and for a USER it is worse
     * than tedious: site scope is deny-all, so between the two steps the
     * account exists and sees nothing.
     *
     * The default differs by what the server is doing, and deliberately:
     *
     *   - no real site yet -- the catch-all is the only option there is, so
     *     it is preselected. This is the same answer User::save() reaches
     *     on its own for the paths with no form behind them; the field is
     *     here so the page agrees with what is about to happen rather than
     *     appearing to offer a choice it does not have.
     *   - real sites exist -- blank. Which site a new object belongs to is
     *     the admin's call at that point, and preselecting the catch-all
     *     would quietly grant every new account sight of everything.
     *
     * Returns [] when there are no sites and no catch-all, which is the
     * pre-schema-333 window: rendering a select box out of a table that
     * may not exist yet would take the create page down with it.
     *
     * @param string $labelClass the form's label class
     *
     * @return array fields fragment to merge onto the create form
     */
    protected static function siteAddField($labelClass = 'col-sm-3 col-form-label')
    {
        if (SiteScope::catchAllID() < 1 && !SiteScope::sitesInUse()) {
            return [];
        }
        $siteID = (
            (int)filter_input(INPUT_POST, 'site') ?:
            (SiteScope::sitesInUse() ? 0 : SiteScope::catchAllID())
        );
        return [
            self::makeLabel(
                $labelClass,
                'site',
                _('Site')
            ) => (new SiteManager())->buildSelectBox($siteID, 'site')
        ];
    }
    /**
     * Renders the Site tab shared by the host, user, group and usergroup
     * edit pages.
     *
     * A single dropdown, as the site plugin had it: an object sits at one
     * site, and that is the shape admins already know.
     *
     * The membership tables are many-to-many though, and the site page's
     * association grids can genuinely put one object in several sites --
     * at which point saving this tab replaces all of them with the one
     * selected. That is the plugin's behavior and it is kept, but it is
     * no longer SILENT: when an object is in more than one site the tab
     * says so and names them, so the replacement is a choice rather than a
     * surprise. Losing a membership with no message is the failure worth
     * spending markup on.
     *
     * @param string $node the owning node (host|user|group|usergroup)
     * @param object $obj  the owning object
     *
     * @return void
     */
    protected function renderSiteTab($node, $obj)
    {
        $objectID = (int)$obj->get('id');
        $current = self::siteIDsFor($node, $objectID);
        $siteID = (
            (int)filter_input(INPUT_POST, 'site') ?:
            (int)reset($current)
        );

        $fields = [
            self::makeLabel(
                'col-sm-3 col-form-label',
                'site',
                _('Site')
            ) => (new SiteManager())->buildSelectBox($siteID, 'site')
        ];

        $buttons = self::makeButton(
            'site-send',
            _('Update'),
            'btn btn-primary float-end'
        );
        // Create-and-associate, same button and modal the grid tabs get.
        // Added before the *_FIELDS event so a listener still sees it, and
        // right after Update so Update stays the rightmost (primary) one.
        $createModal = self::renderAssocCreate(
            $node . '-site',
            'site',
            $buttons,
            $objectID
        );

        self::$HookManager->processEvent(
            strtoupper($node) . '_SITE_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'obj' => &$obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            '',
            $node . '-site-form',
            self::makeTabUpdateURL($node . '-site', $objectID),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Site');
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        if (count($current) > 1) {
            $names = [];
            foreach ($current as $sid) {
                $site = new Site($sid);
                if ($site->isValid()) {
                    $names[] = $site->get('name');
                }
            }
            echo '<div class="alert alert-warning">'
                . sprintf(
                    _(
                        'This is currently in %d sites (%s). Saving here '
                        . 'replaces them all with the single site selected '
                        . 'below. Use the site\'s own page to keep more '
                        . 'than one.'
                    ),
                    count($current),
                    \Initiator::e(implode(', ', $names))
                )
                . '</div>';
        }
        echo $rendered;
        echo '</div>';
        echo '<div class="card-footer">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
        // Outside the form, deliberately: the modal holds a fetched create
        // form, and a <form> inside another <form> is invalid markup -- the
        // browser drops the inner one and the create posts nothing.
        echo $createModal;
    }
    /**
     * The window picker every report shares.
     *
     * A GET FORM, and the range is part of the URL on purpose: a report
     * someone is going to paste into a ticket has to survive being pasted,
     * and a range held only in JS state does not. That is ADR 0030
     * decision 1.
     *
     * IT CANNOT RELY ON THE BROWSER'S OWN SUBMIT. `disableFormDefaults()`
     * in fog.common.js binds submit -> preventDefault on EVERY form on the
     * page, so the native GET never runs and clicking Show does nothing at
     * all -- no error, no request, no change. The form is marked
     * `data-report-window` and fog.report.panels.js navigates on submit.
     * The first cut of this helper shipped without that and the control
     * was inert on every report.
     *
     * `step="1"` IS LOAD-BEARING, not decoration. A datetime-local input
     * defaults to step=60, which makes any value carrying a non-zero
     * SECONDS component fail HTML5 constraint validation -- and an invalid
     * form fires no submit event at all, so the button is simply dead. The
     * default window ends at "now", so the emitted value carries the
     * current second and the control was born invalid on 59 page loads out
     * of 60. That is what "I change the date and click Show and nothing
     * happens" was: no request, no error, no console message.
     *
     * The alternative was truncating the displayed value to the minute,
     * which was rejected: the URL is the source of truth and is meant to be
     * pasted, so a control showing a different range from the one in effect
     * breaks the thing the range is in the URL FOR -- and truncating the
     * end bound would silently drop up to 59 seconds of events.
     *
     * EVERY PARAMETER THAT ADDRESSES THE PAGE IS A HIDDEN FIELD, because a
     * GET submit REPLACES the query string rather than merging into it.
     * `f` IS the report -- the menu is built from the file names in
     * lib/reports, so it is the only thing telling index.php which class to
     * load -- and `sub` is what distinguishes the report from the report
     * index. Dropping either lands on Report Management, which reads as the
     * form having wiped the page.
     *
     * @param string $slug  id prefix for the form and its fields
     * @param string $start current lower bound, 'Y-m-d H:i:s'
     * @param string $end   current upper bound, 'Y-m-d H:i:s'
     * @param string $extra already-escaped markup for report-specific
     *                      controls, dropped in before the submit button
     *
     * @return string
     */
    public static function renderReportWindow(
        $slug,
        $start,
        $end,
        $extra = ''
    ) {
        ob_start();
        printf(
            '<form method="get" action="../management/index.php" '
            . 'class="row g-3 mb-3" id="%s-form" data-report-window="1">'
            . '<input type="hidden" name="node" value="report">'
            . '<input type="hidden" name="sub" value="file">'
            . '<input type="hidden" name="f" value="%s">',
            \Initiator::e($slug),
            \Initiator::e((string) filter_input(INPUT_GET, 'f'))
        );
        foreach (['start' => _('From'), 'end' => _('To')] as $key => $label) {
            echo '<div class="col-md-3">';
            echo self::makeLabel(
                'col-form-label',
                $slug . '-' . $key,
                $label
            );
            printf(
                '<input type="datetime-local" class="form-control" '
                . 'step="1" id="%s-%s" name="%s" value="%s">',
                \Initiator::e($slug),
                \Initiator::e($key),
                \Initiator::e($key),
                // datetime-local wants the ISO 'T' separator; a space-
                // separated value is simply ignored by the control, which
                // then renders blank and posts nothing.
                \Initiator::e(
                    str_replace(' ', 'T', 'start' === $key ? $start : $end)
                )
            );
            echo '</div>';
        }
        echo $extra;
        echo '<div class="col-md-2 d-flex align-items-end">';
        echo self::makeButton(
            $slug . '-go',
            _('Show'),
            'btn btn-primary float-end',
            'type="submit"'
        );
        echo '</div>';
        echo '</form>';

        return ob_get_clean();
    }
    /**
     * The banner an ADR 0030 report shows when its rows hit the cap.
     *
     * EVERY NUMBER ON THESE PAGES IS COMPUTED OFF THE CAPPED SET, so a
     * silent cap makes the tiles quietly wrong for exactly the busy fleets
     * that most need them right -- and, since the CSV export is the same
     * fold, hands out a file that looks complete and is not.
     *
     * Shared rather than written per report. The imaging and snapin reports
     * each had their own copy of this sentence and the other four had none,
     * which is how "some reports warn you" became a thing that was true.
     *
     * The wording carries no noun, deliberately. "%s runs" would have to be
     * built at runtime to say "hosts" on the fleet report, and a msgid
     * assembled at runtime never matches the literal xgettext extracted --
     * so it would silently stop translating. "Rows" is what every one of
     * them shows.
     *
     * @param bool $truncated whether the source hit its cap
     * @param int  $max       the cap that was hit
     *
     * @return string the alert markup, or '' when nothing was cut
     */
    public static function renderReportCap($truncated, $max)
    {
        if (!$truncated) {
            return '';
        }

        return sprintf(
            '<div class="alert alert-warning">%s</div>',
            \Initiator::e(
                sprintf(
                    _(
                        'More than %s rows match this range. Everything '
                        . 'below covers the first %s only -- narrow the '
                        . 'dates for exact figures.'
                    ),
                    number_format((int) $max),
                    number_format((int) $max)
                )
            )
        );
    }
    /**
     * A row of headline numbers.
     *
     * CARDS, NOT AdminLTE's `small-box`. small-box paints a near-white
     * `bg-light` behind its own text; under the dark theme the text turns
     * light and the number becomes invisible against it. The outline card
     * takes the theme's own surface color, so it works in both. Lifted
     * from ImageManagement::_archStat(), which found this the hard way.
     *
     * @param array $tiles ordered list of ['value' =>, 'label' =>,
     *                     'warn' => bool]. `warn` paints the card red when
     *                     the value is above zero -- for counts that are
     *                     bad news rather than progress.
     * @param int   $cols  bootstrap columns each tile occupies at md and up
     *
     * @return string
     */
    public static function renderStatTiles(array $tiles, $cols = 3)
    {
        ob_start();
        echo '<div class="row">';
        foreach ($tiles as $tile) {
            $value = $tile['value'] ?? 0;
            $warn = !empty($tile['warn']) && (float)$value > 0;
            printf(
                '<div class="col-sm-6 col-md-%d">'
                . '<div class="card %s card-outline">'
                . '<div class="card-body text-center">'
                . '<h3 class="mb-0">%s</h3>'
                . '<p class="mb-0 text-muted">%s</p>'
                . '</div></div></div>',
                (int)$cols,
                $warn ? 'card-danger' : 'card-primary',
                // Formatted, not cast: a fleet report counting tens of
                // thousands of runs is unreadable as a bare integer, and
                // number_format is locale-independent here by design --
                // the grid below it is not localized either.
                //
                // One decimal place only when the value has one. A rate
                // tile is a genuine fraction -- three runs across a month
                // is 0.1 a day -- and number_format's default of zero
                // decimals rounds that to "0", which reads as "no imaging
                // happened" directly beside a tile saying three runs did.
                \Initiator::e(
                    is_numeric($value)
                        ? number_format(
                            (float)$value,
                            (float)$value == (int)(float)$value ? 0 : 1
                        )
                        : (string)$value
                ),
                \Initiator::e((string)($tile['label'] ?? ''))
            );
        }
        echo '</div>';

        return ob_get_clean();
    }
    /**
     * One chart, with its data alongside it.
     *
     * THE SERIES IS EMBEDDED, NOT FETCHED. The dashboard's charts poll
     * because their subject is live; a report's window is fixed and already
     * in the URL, so a second round trip would re-run the same aggregation
     * to draw the same picture. Embedding it also means the chart and the
     * grid beneath it are rendered from one request and cannot disagree.
     *
     * A `type="application/json"` block rather than an inline assignment:
     * the browser does not execute it, so nothing here can become script
     * however the values are shaped, and JSON_HEX_TAG closes the one way a
     * string could end the block early.
     *
     * @param string $id     unique element id for this panel
     * @param string $title  translated card title
     * @param array  $chart  ['type' =>, 'labels' => [], 'series' => [
     *                       ['label' =>, 'data' => []] ]]
     * @param int    $cols   bootstrap columns at md and up
     * @param int    $height chart height in pixels
     *
     * @return string
     */
    public static function renderChartPanel(
        $id,
        $title,
        array $chart,
        $cols = 6,
        $height = 260
    ) {
        ob_start();
        printf(
            '<div class="col-md-%d">'
            . '<div class="card card-primary card-outline">'
            . '<div class="card-header"><h3 class="card-title">%s</h3></div>'
            . '<div class="card-body">'
            . '<div class="fog-report-chart" id="%s" '
            . 'data-chart-height="%d"></div>'
            . '<script type="application/json" id="%s-data">%s</script>'
            . '</div></div></div>',
            (int)$cols,
            \Initiator::e((string)$title),
            \Initiator::e((string)$id),
            (int)$height,
            \Initiator::e((string)$id),
            json_encode($chart, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS
                | JSON_HEX_QUOT)
        );

        return ob_get_clean();
    }
}
