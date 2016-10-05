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
        return $this->send();
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
            && !isset($_REQUEST['date'])
            && $this->json
        ) {
            return array('' => '');
        }
        $action = strtolower(
            base64_decode($_REQUEST['action'])
        );
        $user = strtolower(
            base64_decode($_REQUEST['user'])
        );
        $date = base64_decode($_REQUEST['date']);
        if ($this->newService) {
            $action = strtolower(
                $_REQUEST['action']
            );
            $user = strtolower(
                $_REQUEST['user']
            );
            $date = $_REQUEST['date'];
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
        $tmpDate = self::niceDate($date);
        $date = self::niceDate();
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
        if ($this->json) {
            return array('' => '');
        }
        throw new Exception('#!ok');
    }
}
