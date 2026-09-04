<?php
/**
 * Shared FOGPagePost helpers for FOGPage subclasses.
 *
 * Holds the POST/write surface every *Management page submits to: the create/edit/association POST orchestrators, schedule-type validation, and the JSON-response helpers they answer with.
 *
 * Extracted verbatim from FOGPage so the controller base stops growing
 * without bound. A trait's methods compile into the using class exactly as
 * if declared there (same $this, same access to inherited statics like
 * self::$HookManager), so behavior is identical and every existing call
 * site keeps resolving unchanged. The file carries the `.class.php` suffix
 * so the existing filename-keyed autoloader resolves `use FOGPagePost;`
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

use FOG\Items\Site;
use FOG\Router\HTTPResponseCodes;
use FOG\Router\Route;
use FOG\Util\FOGCron;

trait FOGPagePost
{
    /**
     * Emits a JSON response body and terminates the request.
     *
     * This is the universal terminal shared by every AJAX endpoint:
     * set the HTTP status, echo the (already-encoded) body, and exit.
     * Callers that fire a hook must do so before calling this; use
     * jsonHookResponse() when a result hook needs to mutate the body.
     *
     * @param int    $code The HTTP status code to send.
     * @param string $body The response body (already JSON-encoded).
     *
     * Annotated void until 1.6.0-beta, which made every caller look like it
     * fell through -- including FOGConfigurationPage::_jsonExit(), which is
     * itself declared as never returning.
     *
     * @return never
     */
    protected static function jsonSend($code, $body)
    {
        http_response_code($code);
        echo $body;
        exit;
    }

    /**
     * Answers a GET that reached a POST-only endpoint.
     *
     * WHY THIS EXISTS, and it is a dispatcher constraint rather than a
     * nicety. FOGPageManager::render() resolves the sub to a method BEFORE
     * it considers an Ajax or Post suffix:
     *
     *     if (... || !method_exists($class, $method) || empty($method)) {
     *         $method = 'index';
     *     }
     *     if (self::$ajax && method_exists($class, $method.'Ajax')) { ... }
     *     if (self::$post && method_exists($class, $method.'Post')) { ... }
     *
     * So a sub implemented ONLY as <sub>Post is never reached. The name is
     * not found, $method is rewritten to 'index', and the request is
     * answered by the node's own list -- HTTP 200, valid JSON, and nothing
     * anywhere saying the endpoint does not exist. That is how the host
     * list's mass edit shipped unreachable: the browser POSTed to
     * sub=masseditform, got the 86-row host list back, read data.msg as
     * undefined and rendered an empty modal.
     *
     * A page therefore declares the bare method too, and points it here.
     * The Post handler still runs for the POST -- the dispatcher appends
     * the suffix once the bare name resolves -- so the central
     * checkAuthAndCSRF() on that path is unchanged, and this only ever
     * answers the verb the endpoint does not implement.
     *
     * @return never
     */
    protected static function methodNotAllowed()
    {
        header('Content-type: application/json');
        header('Allow: POST');
        self::jsonSend(
            HTTPResponseCodes::HTTP_METHOD_NOT_ALLOWED,
            json_encode(
                [
                    'error' => _('This endpoint accepts POST only.'),
                    'title' => _('Method not allowed')
                ]
            )
        );
    }
    /**
     * Fires a result hook then emits the JSON response.
     *
     * Preserves the existing per-method hook contract exactly: the
     * caller passes the same by-reference argument array it always
     * has (including 'code' and 'msg'), so plugins registered on
     * $hook can still mutate the status code and body. The (possibly
     * mutated) values are read back from that same array after the
     * event fires. PHP preserves the member references when the array
     * is passed by value, so $args['code']/$args['msg'] resolve to the
     * caller's $code/$msg exactly as the inline code did.
     *
     * @param array  $args The by-reference hook argument array; must
     *                      contain 'code' and 'msg' keys.
     * @param string $hook The hook event name to fire.
     *
     * @return void
     */
    protected function jsonHookResponse(array $args, $hook)
    {
        self::$HookManager->processEvent($hook, $args);
        $this->jsonSend($args['code'], $args['msg']);
    }

    /**
     * Shared scaffold for the create (addPost) AJAX handlers.
     *
     * Owns the boilerplate every create endpoint repeated verbatim:
     * the auth/CSRF gate, the JSON content-type header, the
     * "<BASE>_POST" pre-event, the $serverFault flag, the try/catch
     * that turns a thrown Exception into the proper HTTP status, the
     * success/fail hook names and JSON body, and the terminal
     * jsonHookResponse() that fires the result hook and emits the
     * response.
     *
     * The page-specific part — reading $_POST, validating, building
     * and saving the entity — lives in the $build closure. The closure
     * receives $serverFault by reference (set it true before throwing
     * to signal an HTTP 500 rather than a 400) and must return the
     * saved entity, which is handed to the result hook under
     * $entityKey so listeners registered on "<BASE>_SUCCESS" /
     * "<BASE>_FAIL" still see it exactly as before.
     *
     * @param string   $entityKey    Payload key for the entity (e.g. 'Group').
     * @param string   $hookBase     Hook prefix (e.g. 'GROUP_ADD'); the
     *                               _POST/_SUCCESS/_FAIL events derive from it.
     * @param string   $successMsg   Translated success message body.
     * @param string   $successTitle Translated success title.
     * @param string   $failTitle    Translated failure title.
     * @param callable $build        Closure(&$serverFault): returns the entity.
     *
     * @return void
     */
    protected function handleAddPost(
        $entityKey,
        $hookBase,
        $successMsg,
        $successTitle,
        $failTitle,
        callable $build
    ) {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        self::$HookManager->processEvent($hookBase . '_POST');
        $serverFault = false;
        $Entity = null;
        try {
            $Entity = $build($serverFault);
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = $hookBase . '_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => $successMsg,
                    'title' => $successTitle
                ]
            );
        } catch (\Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = $hookBase . '_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => $failTitle
                ]
            );
        }
        $args = [
            $entityKey => &$Entity,
            'hook' => &$hook,
            'code' => &$code,
            'msg' => &$msg,
            'serverFault' => &$serverFault
        ];
        self::$HookManager->processEvent($hook, $args);
        // Merged AFTER the hook, not before, so a listener that replaces $msg
        // wholesale cannot silently drop the object. No listener does that
        // today, but that is a snapshot, and a caller that stopped receiving
        // the created object would fail in a confusing place.
        $msg = self::attachCreatedObject($msg, $entityKey, $Entity);
        $this->jsonSend($code, $msg);
    }
    /**
     * Adds the created entity to a create response under 'object'.
     *
     * Serialized through Route::getter(), the same path a single-entity API
     * GET uses, so a create answers in the shape callers already know and a
     * client can act on the result -- associating it, linking to it -- without
     * a second request to find out what it just made.
     *
     * Run through stripSensitive() because this helper is shared: Host goes
     * through it too, and a host's serialization carries ADPass, productKey
     * and its tokens. A create response is a new place for those to surface,
     * and the client gains nothing from them -- it just supplied them.
     *
     * Failure is silent by design. The entity is already saved and the success
     * message is already built; losing the convenience payload must not turn a
     * successful create into an error.
     *
     * @param string $msg       The JSON response body built so far.
     * @param string $entityKey The payload key, e.g. 'Group'.
     * @param mixed  $Entity    The saved entity, or null when the create failed.
     *
     * @return string
     */
    protected static function attachCreatedObject($msg, $entityKey, $Entity)
    {
        if (!($Entity instanceof FOGController) || !$Entity->isValid()) {
            return $msg;
        }
        $payload = json_decode($msg, true);
        if (!is_array($payload) || isset($payload['error'])) {
            return $msg;
        }
        try {
            $classname = strtolower($entityKey);
            $object = Route::getter($classname, $Entity);
            if (!is_array($object)) {
                return $msg;
            }
            $payload['object'] = Route::stripSensitive($classname, $object);
        } catch (\Exception $e) {
            return $msg;
        }
        $encoded = json_encode($payload);
        return false === $encoded ? $msg : $encoded;
    }

    /**
     * Shared scaffold for the update (editPost) AJAX handlers.
     *
     * The edit counterpart of handleAddPost(). It owns the same
     * boilerplate, with three differences inherent to editing an
     * existing entity: the "<BASE>_POST" pre-event carries the loaded
     * entity ([$entityKey => &$this->obj]) so listeners can inspect or
     * replace it; the success status is HTTP 202 Accepted rather than
     * 201 Created; and the entity is the page's own $this->obj, so the
     * $build closure mutates it in place and need not return anything.
     *
     * The page-specific part — reading $_POST, applying changes to
     * $this->obj, and saving — lives in the $build closure. The closure
     * receives $serverFault by reference (set it true before throwing to
     * signal an HTTP 500 rather than a 400). $this->obj is handed to the
     * result hook under $entityKey so listeners on "<BASE>_SUCCESS" /
     * "<BASE>_FAIL" still see it exactly as before.
     *
     * @param string   $entityKey    Payload key for the entity (e.g. 'Group').
     * @param string   $hookBase     Hook prefix (e.g. 'GROUP_EDIT'); the
     *                               _POST/_SUCCESS/_FAIL events derive from it.
     * @param string   $successMsg   Translated success message body.
     * @param string   $successTitle Translated success title.
     * @param string   $failTitle    Translated failure title.
     * @param callable $build        Closure(&$serverFault): mutates $this->obj.
     *
     * @return void
     */
    protected function handleEditPost(
        $entityKey,
        $hookBase,
        $successMsg,
        $successTitle,
        $failTitle,
        callable $build
    ) {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            $hookBase . '_POST',
            [$entityKey => &$this->obj]
        );
        $serverFault = false;
        try {
            $build($serverFault);
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = $hookBase . '_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => $successMsg,
                    'title' => $successTitle
                ]
            );
        } catch (\Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = $hookBase . '_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => $failTitle
                ]
            );
        }
        $this->jsonHookResponse(
            [
                $entityKey => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
    }

    /**
     * The same POST, written from the other end of the association.
     *
     * assocPost() calls a method on the page's own object. That only works
     * when the page's class is the one the association table is keyed to --
     * assocSetter() derives the column it diffs on from the OWNING class
     * name, so `siteRoleGrants` can be driven from a Site and not from a
     * Role, whichever end an administrator happens to be looking at.
     *
     * So this inverts it: for each id the tab submitted, load THAT object
     * and call its add/remove with the page object's id. One table, one
     * writer, two doors -- which is the property that keeps the two ends
     * from drifting, and the reason every other association tab in FOG is
     * editable from both.
     *
     * The caller is responsible for the permission check. A reverse tab is
     * a second door onto somebody else's association, so it must take that
     * association's permission and not merely the edit right that got the
     * admin onto this page.
     *
     * @param string $ownerClass   class owning the association (e.g. 'Site')
     * @param string $addMethod    its add method (e.g. 'addGrantRole')
     * @param string $removeMethod its remove method (e.g. 'removeGrantRole')
     *
     * @return void
     */
    protected function assocPostInverse($ownerClass, $addMethod, $removeMethod)
    {
        self::checkAuthAndCSRF();
        $subjectID = (int)$this->obj->get('id');
        if ($subjectID < 1) {
            return;
        }
        $method = '';
        $items = [];
        if (isset($_POST['confirmadd'])) {
            $method = $addMethod;
            $items = filter_input_array(
                INPUT_POST,
                ['additems' => ['flags' => FILTER_REQUIRE_ARRAY]]
            );
            $items = $items['additems'];
        } elseif (isset($_POST['confirmdel'])) {
            $method = $removeMethod;
            $items = filter_input_array(
                INPUT_POST,
                ['remitems' => ['flags' => FILTER_REQUIRE_ARRAY]]
            );
            $items = $items['remitems'];
        }
        if ('' === $method) {
            return;
        }
        foreach (self::positiveIntIds($items) as $ownerID) {
            $owner = self::getClass($ownerClass, $ownerID);
            if (!$owner->isValid()) {
                continue;
            }
            $owner->{$method}([$subjectID]);
            $owner->save();
        }
    }

    /**
     * Handles a standard association add/remove POST: reads the additems /
     * remitems arrays and dispatches them to the object's add/remove methods.
     * When $orderMethod is supplied, also honors an ordered-id-list POST
     * array (used by the group/host snapin and software tabs to persist run
     * order), under the wire name $orderField.
     *
     * @param string $addMethod    obj method to add associations (e.g. 'addGroup')
     * @param string $removeMethod obj method to remove associations (e.g. 'removeGroup')
     * @param string $orderMethod  obj method to set ordering from the
     *                             $orderField POST array, or null when the
     *                             tab has no ordering
     * @param string $orderField   POST field name carrying the ordered id
     *                             list. Defaults to 'snapinorder', the
     *                             original (and still only pre-existing)
     *                             caller; a second ordered association
     *                             (software) needs its own name so the two
     *                             do not share one wire field.
     *
     * @return void
     */
    protected function assocPost($addMethod, $removeMethod, $orderMethod = null, $orderField = 'snapinorder')
    {
        self::checkAuthAndCSRF();
        if (isset($_POST['confirmadd'])) {
            $items = filter_input_array(
                INPUT_POST,
                [
                    'additems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $items = $items['additems'];
            if (count($items ?: []) > 0) {
                $this->obj->{$addMethod}($items);
            }
        }
        if (isset($_POST['confirmdel'])) {
            $items = filter_input_array(
                INPUT_POST,
                [
                    'remitems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $items = $items['remitems'];
            if (count($items ?: []) > 0) {
                $this->obj->{$removeMethod}($items);
            }
        }
        if ($orderMethod !== null && isset($_POST[$orderField])) {
            $order = filter_input_array(
                INPUT_POST,
                [
                    $orderField => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $order = $order[$orderField];
            if (count($order ?: []) > 0) {
                $this->obj->{$orderMethod}($order);
            }
        }
    }

    /**
     * Validates the posted schedule type for the host/group deploy() create-task
     * handlers and resolves its parameters. Honors the SCHEDULE_TYPES hook,
     * rejects an unknown type, a past single-run time, or an invalid cron field.
     *
     * @throws Exception If the schedule type or any cron/time field is invalid.
     *
     * @return array Keyed: scheduleType, scheduleDeployTime (single), and
     *               min/hour/dom/month/dow (cron); unused entries are null.
     */
    protected function validateScheduleType()
    {
        $scheduleType = strtolower(
            (string)filter_input(INPUT_POST, 'scheduleType')
        );
        $scheduleTypes = [
            'cron',
            'instant',
            'single'
        ];
        self::$HookManager->processEvent(
            'SCHEDULE_TYPES',
            ['scheduleTypes' => &$scheduleTypes]
        );
        foreach ($scheduleTypes as $ind => &$val) {
            $scheduleTypes[$ind] = trim(
                strtolower(
                    $val
                )
            );
            unset($val);
        }
        if (!in_array($scheduleType, $scheduleTypes)) {
            throw new \Exception(_('Invalid scheduling type'));
        }
        $schedule = [
            'scheduleType' => $scheduleType,
            'scheduleDeployTime' => null,
            'min' => null,
            'hour' => null,
            'dom' => null,
            'month' => null,
            'dow' => null
        ];
        // Schedule Delayed/Cron checks.
        switch ($scheduleType) {
            case 'single':
                $scheduleSingleTime = filter_input(INPUT_POST, 'scheduleSingleTime');
                /*
                 * GH-1245: reject the missing time instead of scheduling now.
                 *
                 * niceDate() used to read an absent or empty value as the
                 * current time, so a single schedule with no time silently
                 * became "run immediately". It now reads empty as "no value",
                 * which would trip the past-time check below with a message
                 * that does not describe what happened.
                 */
                if (null === $scheduleSingleTime
                    || '' === trim((string) $scheduleSingleTime)
                ) {
                    throw new \Exception(_('A scheduled time is required'));
                }
                // The viewer typed this into a picker on a page rendered
                // in their own zone, so it is read in theirs and not the
                // install's -- see viewerDate(). The past-time check
                // below then compares two values that mean the same
                // thing.
                $scheduleDeployTime = self::viewerDate($scheduleSingleTime);
                if ($scheduleDeployTime < self::niceDate()) {
                    throw new \Exception(_('Scheduled time is in the past'));
                }
                $schedule['scheduleDeployTime'] = $scheduleDeployTime;
                break;
            case 'cron':
                $min = strval(
                    filter_input(INPUT_POST, 'scheduleCronMin')
                );
                $hour = strval(
                    filter_input(INPUT_POST, 'scheduleCronHour')
                );
                $dom = strval(
                    filter_input(INPUT_POST, 'scheduleCronDOM')
                );
                $month = strval(
                    filter_input(INPUT_POST, 'scheduleCronMonth')
                );
                $dow = strval(
                    filter_input(INPUT_POST, 'scheduleCronDOW')
                );
                $tmin = FOGCron::checkMinutesField($min);
                $thour = FOGCron::checkHoursField($hour);
                $tdom = FOGCron::checkDOMField($dom);
                $tmonth = FOGCron::checkMonthField($month);
                $tdow = FOGCron::checkDOWField($dow);
                if (!$tmin) {
                    throw new \Exception(_('Minutes field is invalid'));
                }
                if (!$thour) {
                    throw new \Exception(_('Hours field is invalid'));
                }
                if (!$tdom) {
                    throw new \Exception(_('Day of Month field is invalid'));
                }
                if (!$tmonth) {
                    throw new \Exception(_('Month field is invalid'));
                }
                if (!$tdow) {
                    throw new \Exception(_('Day of Week field is invalid'));
                }
                $schedule['min'] = $min;
                $schedule['hour'] = $hour;
                $schedule['dom'] = $dom;
                $schedule['month'] = $month;
                $schedule['dow'] = $dow;
        }
        return $schedule;
    }

    /**
     * Saves the Site tab shared by the host, user, group and usergroup
     * edit pages.
     *
     * Replaces whatever memberships the object had with the one selected,
     * which is what a single dropdown can express. renderSiteTab() warns
     * first when that would drop more than one, so the replacement is
     * never silent.
     *
     * Selecting the blank option removes the object from every site. That
     * is a real choice and not an error -- but for a USER it means they
     * fall back to whatever the catch-all grants, and if there is no
     * catch-all they see nothing.
     *
     * @param string $node the owning node (host|user|group|usergroup)
     * @param object $obj  the owning object
     *
     * @return void
     */
    protected function siteTabPost($node, $obj)
    {
        self::checkAuthAndCSRF();
        if (!isset(self::$siteTabMap[$node])) {
            return;
        }
        list($route, $field, $manager) = self::$siteTabMap[$node];
        $objectID = (int)$obj->get('id');
        if ($objectID < 1) {
            return;
        }
        $siteID = (int)filter_input(INPUT_POST, 'site');
        $current = self::siteIDsFor($node, $objectID);

        // Nothing to do. Worth the check rather than delete-then-insert
        // anyway: this runs on every save of the tab, and rewriting an
        // unchanged row churns the id sequence for no reason.
        if ($siteID > 0 && $current === [$siteID]) {
            return;
        }
        if ($siteID < 1 && empty($current)) {
            return;
        }

        Route::deletemass($route, [$field => $objectID]);
        if ($siteID < 1) {
            return;
        }
        // Guard against pointing at a site that no longer exists: the
        // membership tables carry no foreign keys, and a stale row would
        // otherwise sit there granting scope for an id that could later be
        // reused by a different site.
        $site = new Site($siteID);
        if (!$site->isValid()) {
            throw new \Exception(_('The selected site no longer exists'));
        }
        self::getClass($manager)
            ->insertBatch(
                [$field, 'siteID'],
                [[$objectID, $siteID]]
            );
    }
    /**
     * Applies the create form's Site field to the object just created.
     *
     * Same rule as the edit tab, so it is the same code -- but only when
     * the field was actually posted. siteAddField() renders nothing on a
     * server with no sites, and a hook may drop it; in either case the
     * absent field means "nothing was asked for", not "no site", and
     * treating it as the latter would delete the catch-all membership
     * User::save() had just given a brand new account.
     *
     * @param string $node the owning node (host|user|group|usergroup)
     * @param object $obj  the object just created
     *
     * @return void
     */
    protected function siteAddPost($node, $obj)
    {
        if (null === filter_input(INPUT_POST, 'site')) {
            return;
        }
        $this->siteTabPost($node, $obj);
    }
}
