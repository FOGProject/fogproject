<?php
/**
 * User tracking report.
 *
 * PHP Version 5
 *
 * @category User_Tracking
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * User tracking report.
 *
 * @category User_Tracking
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class User_Tracking extends ReportManagementPage
{
    /**
     * The page to display.
     *
     * @return void
     */
    public function file()
    {
        $this->title = _('FOG User tracking - Search');
        unset($this->headerData);
        $this->templates = array(
            '${field}',
            '${input}'
        );
        $this->attributes = array(
            array(),
            array()
        );
        $UserNames = self::getSubObjectIDs(
            'UserTracking',
            '',
            'username'
        );
        $UserTrackingHostIDs = self::getSubObjectIDs(
            'UserTracking',
            '',
            'hostID'
        );
        $HostNames = self::getSubObjectIDs(
            'Host',
            array('id' => $UserTrackingHostIDs),
            'name'
        );
        unset($UserTrackingHostIDs);
        $UserNames = array_values(
            array_filter(
                array_unique(
                    (array)$UserNames
                )
            )
        );
        $HostNames = array_values(
            array_filter(
                array_unique(
                    (array)$HostNames
                )
            )
        );
        natcasesort($UserNames);
        natcasesort($HostNames);
        if (count($UserNames) > 0) {
            $userSelForm = self::selectForm('usersearch', $UserNames);
            unset($UserNames);
        }
        if (count($HostNames) > 0) {
            $hostSelForm = self::selectForm('hostsearch', $HostNames);
            unset($HostNames);
        }
        $fields = array(
            _('Enter a username to search for') => $userSelForm,
            _('Enter a hostname to search for') => $hostSelForm,
            '&nbsp;' => sprintf(
                '<input type="submit" value="%s"/>',
                _('Search')
            )
        );
        array_walk($fields, $this->fieldsToData);
        ob_start();
        printf(
            '<form method="post" action="%s">',
            $this->formAction
        );
        $this->render();
        echo '</form>';
        flush();
        ob_flush();
        ob_end_flush();
    }
    /**
     * Form submitted.
     *
     * @return void
     */
    public function filePost()
    {
        $this->title = _('Found login information');
        $this->headerData = array(
            _('Hostname'),
            _('Username'),
        );
        $hostsearch = filter_input(
            INPUT_POST,
            'hostsearch'
        );
        $usersearch = filter_input(
            INPUT_POST,
            'usersearch'
        );
        $this->templates = array(
            sprintf(
                '<a href="%s%s%s">${host_name}</a>',
                str_replace(
                    'sub=file',
                    'sub=filedisp',
                    $this->formAction
                ),
                $hostsearch ? '&hostID=${host_id}' : '',
                $usersearch ? '&userID=${user_id}' : ''
            ),
            sprintf(
                '<a href="%s%s${user_id}">${user_name}</a>',
                str_replace(
                    'sub=file',
                    'sub=filedisp',
                    $this->formAction
                ),
                $hostsearch ? '&hostID=${host_id}' : ''
            )
        );
        $this->attributes = array(
            array(),
            array()
        );
        if (!$hostsearch) {
            $hostsearch = '%';
        }
        if (!$usersearch) {
            $usersearch = '%';
        }
        $hostIDs = self::getSubObjectIDs(
            'Host',
            array('name' => $hostsearch)
        );
        $userIDs = self::getSubObjectIDs(
            'UserTracking',
            array(
                'username' => $usersearch,
                'hostID' => $hostIDs
            )
        );
        if (count($userIDs) < 1) {
            echo _('No Data Found');
        } else {
            foreach (self::getClass('UserTrackingManager')
                ->find(
                    array(
                        'id' => $userIDs
                    ),
                    'AND',
                    'name',
                    'ASC',
                    '=',
                    'username'
                ) as &$User
            ) {
                $this->data[] = array(
                    'host_id' => $User->get('hostID'),
                    'host_name' => $User->get('host')->get('name'),
                    'user_id' => sprintf(
                        '&userID=%s',
                        base64_encode($User->get('username'))
                    ),
                    'user_name' => $User->get('username')
                );
                unset($User);
            }
            $this->render();
        }
    }
    /**
     * Display after choices made
     *
     * @return void
     */
    public function filedisp()
    {
        $this->title = _('FOG User tracking history');
        printf(
            $this->reportString,
            'UserTrackingList',
            _('Export CSV'),
            _('Export CSV'),
            self::$csvfile,
            'UserTrackingList',
            _('Export PDF'),
            _('Export PDF'),
            self::$pdffile
        );
        $this->headerData = array(
            _('Action'),
            _('Username'),
            _('Hostname'),
            _('Time'),
            _('Description')
        );
        $this->templates = array(
            '${action}',
            '${username}',
            '${hostname}',
            '${time}',
            '${desc}'
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(),
            array()
        );
        $this->ReportMaker
            ->addCSVCell(_('Action'))
            ->addCSVCell(_('Username'))
            ->addCSVCell(_('Hostname'))
            ->addCSVCell(_('Host MAC'))
            ->addCSVCell(_('Host Description'))
            ->addCSVCell(_('Time'))
            ->addCSVCell(_('Description'))
            ->endCSVLine();
        $userID = base64_decode(
            filter_input(INPUT_GET, 'userID')
        );
        $hostID = filter_input(INPUT_GET, 'hostID');
        if (!$userID) {
            $userID = '%';
        }
        if (!$hostID) {
            $hostID = '%';
        }
        foreach (self::getClass('UserTrackingManager')
            ->find(
                array(
                    'hostID' => $hostID,
                    'username' => $userID
                )
            ) as &$User
        ) {
            $date = self::niceDate($User->get('datetime'));
            $actions = array(
                0 => _('Logout'),
                1 => _('Login'),
                99 => _('Service Start')
            );
            $logintext = (
                !in_array($User->get('action'), array_keys($actions)) ?
                _('N/A') :
                $actions[$User->get('action')]
            );
            unset($actions);
            $username = $User->get('username');
            $hostname = $User->get('host')->get('name');
            $hostmac = $User->get('host')->get('mac')->__toString();
            $hostdesc = $User->get('host')->get('description');
            $date = $date->format('Y-m-d H:i:s');
            $desc = $User->get('description');
            $this->data[] = array(
                'action' => $logintext,
                'username' => $username,
                'hostname' => $hostname,
                'time' => $date,
                'desc' => $desc
            );
            $this->ReportMaker
                ->addCSVCell($logintext)
                ->addCSVCell($username)
                ->addCSVCell($hostname)
                ->addCSVCell($hostmac)
                ->addCSVCell($hostdesc)
                ->addCSVCell($date)
                ->addCSVCell($desc)
                ->endCSVLine();
            unset(
                $username,
                $hostname,
                $logintext,
                $hostmac,
                $hostdesc,
                $date,
                $desc,
                $User
            );
        }
        $this->ReportMaker->appendHTML($this->__toString());
        $this->ReportMaker->outputReport(false);
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
    }
}
