<?php
/**
 * Logs the user who logged in
 *
 * PHP version 7.4+
 *
 * @category UserTrack
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Client;

use FOG\Items\UserTracking;

/**
 * Logs the user who logged in
 *
 * @category UserTrack
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class UserTrack extends FOGClient
{
    /**
     * Module associated shortname
     *
     * @var string
     */
    public $shortName = 'usertracker';
    /**
     * The actions as their passed
     *
     * @var array
     */
    protected $actions = [
        'login' => UserTracking::ACTION_LOGIN,
        'start' => UserTracking::ACTION_SERVICE_START,
        'logout' => UserTracking::ACTION_LOGOUT
    ];

    /**
     * Function returns data that will be translated to json
     *
     * @return array
     * @throws Exception
     */
    public function json()
    {
        if (!isset($_REQUEST['action'])
            && !isset($_REQUEST['user'])
        ) {
            return ['' => ''];
        }
        $action = strtolower(
            $_REQUEST['action']
        );
        $user = strtolower(
            $_REQUEST['user']
        );
        // GH-1245: an empty date= is not a supplied date. niceDate() now reads
        // empty as "no value" rather than "now", so the client sending the
        // parameter with nothing in it has to fall to the same branch as not
        // sending it at all -- otherwise the row records the zero date.
        if (isset($_REQUEST['date'])
            && '' !== trim((string) $_REQUEST['date'])
        ) {
            $tmpDate = self::niceDate($_REQUEST['date']);
        } else {
            $tmpDate = self::niceDate();
        }
        if (!in_array($action, array_keys($this->actions))) {
            return [
                'error' => sprintf(
                    '%s, %s, %s',
                    _('Postfix requires an action of login'),
                    _('logout'),
                    _('or start to operate')
                )
            ];
        }
        if (strpos($user, chr(92))) {
            $user = explode(chr(92), $user);
            $user = $user[1];
        } elseif (strpos($user, chr(64))) {
            $user = explode(chr(64), $user);
            $user = $user[0];
        }
        if ($user == null) {
            return ['error' => 'us'];
        }
        self::getClass('UserTracking')
            ->set('hostID', self::$Host->get('id'))
            ->set('username', $user)
            ->set('action', $this->actions[$action])
            // 'createdTime', not 'datetime'. UserTracking maps utDateTime to
            // the friendly key createdTime, and set() resolves a key against
            // databaseFields/databaseFieldsFlipped/additionalFields only --
            // 'datetime' is in none of them, so set() threw "Invalid key
            // being set" and caught its own exception into debug(), which is
            // silent at the default log level.
            //
            // The row still saved, because save() fills an unset createdTime
            // with 'now'. So a client supplying date= got that date in
            // utDate and the server's clock in utDateTime -- one row, two
            // dates, whenever the two differ (a queued or offline login
            // reported late, which is the only reason the parameter exists).
            ->set('createdTime', $tmpDate->format('Y-m-d H:i:s'))
            ->set('date', $tmpDate->format('Y-m-d'))
            // ADR 0020 phase 3. createdBy is not set here on purpose:
            // save() fills it with 'fog' because no operator is signed in
            // on a fog-client request, and 'fog' is the correct actor for
            // this row. The person in utUserName is the endpoint's OS
            // account, which is what the event is ABOUT.
            ->set('ip', self::$remoteaddr)
            // The denormalized host name, so the row stays readable after
            // its host is deleted -- deletemass('host') leaves these rows
            // behind and the grid resolves the name from the id live.
            ->set('subjectLabel', self::$Host->get('name'))
            ->save();
        return ['' => ''];
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\UserTrack', 'UserTrack');
