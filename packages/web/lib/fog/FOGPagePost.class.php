<?php
/**
 * Shared FOGPagePost helpers for FOGPage subclasses.
 *
 * Holds the POST/write surface every *Management page submits to: the create/edit/association POST orchestrators, schedule-type validation, and the JSON-response helpers they answer with.
 *
 * Extracted verbatim from FOGPage so the controller base stops growing
 * without bound. A trait's methods compile into the using class exactly as
 * if declared there (same $this, same access to inherited statics like
 * self::$HookManager), so behaviour is identical and every existing call
 * site keeps resolving unchanged. The file is named FOGPagePost.class.php
 * so the existing filename-keyed autoloader resolves `use FOGPagePost;`
 * with no autoloader change.
 *
 * @category Page
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
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
     * @return void
     */
    protected static function jsonSend($code, $body)
    {
        http_response_code($code);
        echo $body;
        exit;
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
        } catch (Exception $e) {
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
                $entityKey => &$Entity,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
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
        } catch (Exception $e) {
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
     * Handles a standard association add/remove POST: reads the additems /
     * remitems arrays and dispatches them to the object's add/remove methods.
     * When $orderMethod is supplied, also honours a snapinorder array (used by
     * the group/host snapin tabs to persist execution order).
     *
     * @param string $addMethod    obj method to add associations (e.g. 'addGroup')
     * @param string $removeMethod obj method to remove associations (e.g. 'removeGroup')
     * @param string $orderMethod  obj method to set ordering from the snapinorder
     *                             POST array, or null when the tab has no ordering
     *
     * @return void
     */
    protected function assocPost($addMethod, $removeMethod, $orderMethod = null)
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
        if ($orderMethod !== null && isset($_POST['snapinorder'])) {
            $order = filter_input_array(
                INPUT_POST,
                [
                    'snapinorder' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $order = $order['snapinorder'];
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
            filter_input(INPUT_POST, 'scheduleType')
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
            throw new Exception(_('Invalid scheduling type'));
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
                $scheduleDeployTime = self::niceDate(
                    filter_input(INPUT_POST, 'scheduleSingleTime')
                );
                if ($scheduleDeployTime < self::niceDate()) {
                    throw new Exception(_('Scheduled time is in the past'));
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
                    throw new Exception(_('Minutes field is invalid'));
                }
                if (!$thour) {
                    throw new Exception(_('Hours field is invalid'));
                }
                if (!$tdom) {
                    throw new Exception(_('Day of Month field is invalid'));
                }
                if (!$tmonth) {
                    throw new Exception(_('Month field is invalid'));
                }
                if (!$tdow) {
                    throw new Exception(_('Day of Week field is invalid'));
                }
                $schedule['min'] = $min;
                $schedule['hour'] = $hour;
                $schedule['dom'] = $dom;
                $schedule['month'] = $month;
                $schedule['dow'] = $dow;
        }
        return $schedule;
    }
}
