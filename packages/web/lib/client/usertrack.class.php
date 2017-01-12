<?php
/**
 * Logs the user who logged in
 *
 * PHP version 5
 *
 * @category UserTrack
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Logs the user who logged in
 *
 * @category UserTrack
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class UserTrack extends FOGClient implements FOGClientSend
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
    protected $actions = array(
        'login' => 1,
        'start' => 99,
        'logout' => 0
    );
    /**
     * Function returns data that will be translated to json
     *
     * @return array
     */
    public function json()
    {
        if (!isset($_REQUEST['action'])
            && !isset($_REQUEST['user'])
        ) {
            return array('' => '');
        }
        $action = strtolower(
            $_REQUEST['action']
        );
        $user = strtolower(
            $_REQUEST['user']
        );
        if (isset($_REQUEST['date'])) {
            $tmpDate = self::niceDate($_REQUEST['date']);
        } else {
            $tmpDate = self::niceDate();
        }
        if (!in_array($action, array_keys($this->actions))) {
            return array(
                'error' => sprintf(
                    '%s, %s, %s',
                    _('Postfix requires an action of login'),
                    _('logout'),
                    _('or start to operate')
                )
            );
        }
        if (strpos($user, chr(92))) {
            $user = explode(chr(92), $user);
            $user = $user[1];
        } elseif (strpos($user, chr(64))) {
            $user = explode(chr(64), $user);
            $user = $user[0];
        }
        if ($user == null) {
            return array('error' => 'us');
        }
        $date = self::niceDate();
        self::getClass('UserTracking')
            ->set('hostID', $this->Host->get('id'))
            ->set('username', $user)
            ->set('action', $this->actions[$action])
            ->set('datetime', $tmpDate->format('Y-m-d H:i:s'))
            ->set('date', $tmpDate->format('Y-m-d'))
            ->save();
        return array('' => '');
    }
    /**
     * Sends the data to the client
     *
     * @return void
     */
    public function send()
    {
        if (!isset($_REQUEST['action'])
            && !isset($_REQUEST['user'])
        ) {
            throw new Exception('#!us');
        }
        $action = strtolower(
            base64_decode($_REQUEST['action'])
        );
        $user = strtolower(
            base64_decode($_REQUEST['user'])
        );
        unset($tmpDate);
        if (isset($_REQUEST['date'])) {
            $date = base64_decode($_REQUEST['date']);
            $tmpDate = self::niceDate($date);
        } else {
            $tmpDate = self::niceDate();
        }
        if (!in_array($action, array_keys($this->actions))) {
            throw new Exception(
                _('Postfix requires an action of login, logout, or start to operate')
            );
        }
        if (strpos($user, chr(92))) {
            $user = explode(chr(92), $user);
            $user = $user[1];
        } elseif (strpos($user, chr(64))) {
            $user = explode(chr(64), $user);
            $user = $user[0];
        }
        if ($user == null) {
            throw new Exception('#!us');
        }
        $date = self::niceDate();
        $desc = '';
        if (isset($tmpDate)) {
            if ($tmpDate < $date) {
                $desc = sprintf(
                    '%s: %s %s %s: %s',
                    _('Replay from journal'),
                    _('real insert time'),
                    $date->format('M j, Y g:i:s a'),
                    _('Login time'),
                    $tmpDate->format('M j, Y g:i:s a')
                );
            }
        }
        if ($action == 'start') {
            $user = '';
        }
        $UserTracking = self::getClass('UserTracking')
            ->set('hostID', $this->Host->get('id'))
            ->set('username', $user)
            ->set('action', $this->actions[$action])
            ->set('datetime', $tmpDate->format('Y-m-d H:i:s'))
            ->set('description', $desc)
            ->set('date', $tmpDate->format('Y-m-d'));
        if (!$UserTracking->save()) {
            throw new Exception('#!db');
        }
        throw new Exception('#!ok');
    }
}
