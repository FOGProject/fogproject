<?php
/**
 * Shared FOGPageRender helpers for FOGPage subclasses.
 *
 * Holds the GET/read surface every *Management page renders: create/edit form builders, association/history/edit tab renderers, and the AJAX read endpoints that feed their tables.
 *
 * Extracted verbatim from FOGPage so the controller base stops growing
 * without bound. A trait's methods compile into the using class exactly as
 * if declared there (same $this, same access to inherited statics like
 * self::$HookManager), so behaviour is identical and every existing call
 * site keeps resolving unchanged. The file is named FOGPageRender.class.php
 * so the existing filename-keyed autoloader resolves `use FOGPageRender;`
 * with no autoloader change.
 *
 * @category Page
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
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
     *                          the same thing as their blue neighbours, and the
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
        $noun = ''
    ) {
        $this->headerData = [
            $colHeader,
            _('Associated')
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
        $buttons .= self::makeButton(
            "$tabSlug-remove",
            _('Remove selected'),
            'btn btn-danger float-start',
            $props
        );

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
        echo $this->assocDelModal($delItem);
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
                Initiator::e($createNode),
                Initiator::e(
                    self::makeTabUpdateURL($tabSlug, $ownerId)
                )
            )
        );
        return self::makeModal(
            "$tabSlug-createModal",
            $label,
            sprintf(
                '<div id="%s-create-form"></div>',
                Initiator::e($tabSlug)
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
     * Shared scaffold for the standard edit() tab pages.
     *
     * Sets the canonical "Edit: <name> ID: <id>" page title from the
     * loaded $this->obj and echoes the assembled tab markup. The page's
     * own edit() keeps building its page-specific $tabData entries and
     * hands the finished array here; the title and the tabFields() echo
     * are the only shared bookends, so they live here.
     *
     * The $obj argument is passed straight through to tabFields() with
     * the same -1 default, preserving its three behaviours: -1 rebuilds
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
}
