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

namespace FOG;

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
        'login' => 1,
        'start' => 99,
        'logout' => 0
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
            ->set('datetime', $tmpDate->format('Y-m-d H:i:s'))
            ->set('date', $tmpDate->format('Y-m-d'))
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
