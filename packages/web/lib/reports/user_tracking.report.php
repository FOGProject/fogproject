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
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->templates = array(
            '${field}',
            '${input}'
        );
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group')
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
        if (is_array($UserNames) && count($UserNames) > 0) {
            $userSelForm = self::selectForm(
                'usersearch',
                $UserNames
            );
            unset($UserNames);
        }
        if (is_array($HostNames) && count($HostNames) > 0) {
            $hostSelForm = self::selectForm(
                'hostsearch',
                $HostNames
            );
            unset($HostNames);
        }
        $fields = array(
            '<label for="usersearch">'
            . _('Enter a username to search for')
            . '</label>' => $userSelForm,
            '<label for="hostsearch">'
            . _('Enter a hostname to search for')
            . '</label>' => $hostSelForm,
            '<label for="performsearch">'
            . _('Perform search')
            . '</label>' => '<button type="submit" name="performsearch" '
            . 'class="btn btn-info btn-block" id="performsearch">'
            . _('Search')
            . '</button>'
        );
        array_walk($fields, $this->fieldsToData);
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '">';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
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
                '<a href="%s%s&userID=${user_id}">${user_name}</a>',
                str_replace(
                    'sub=file',
                    'sub=filedisp',
                    $this->formAction
                ),
                $hostsearch ? '&hostID=${host_id}' : ''
            )
        );
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group')
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
        Route::listem('usertracking');
        $UserTrackings = json_decode(
            Route::getData()
        );
        $UserTrackings = $UserTrackings->usertrackings;
        $sethost = $setuser = array();
        foreach ((array)$UserTrackings as &$User) {
            if (!in_array($User->id, $userIDs)) {
                continue;
            }
            $hostname = $User->host->name;
            $username = $User->username;
            if (isset($sethost[$hostname])
                && isset($setuser[$username])
            ) {
                continue;
            }
            $this->data[] = array(
                'host_id' => $User->hostID,
                'host_name' => $User->host->name,
                'user_id' => base64_encode($User->username),
                'user_name' => $User->username
            );
            $sethost[$hostname] = $setuser[$username] = true;
            unset($User);
        }
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        $this->render(12);
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Display after choices made
     *
     * @return void
     */
    public function filedisp()
    {
        $this->title = _('FOG User tracking history');
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
            unset($userID);
        }
        if (!$hostID) {
            unset($hostID);
        }
        Route::listem('usertracking');
        $UserTrackings = json_decode(
            Route::getData()
        );
        $UserTrackings = $UserTrackings->usertrackings;
        foreach ((array)$UserTrackings as &$User) {
            if (isset($hostID) && $User->host->id != $hostID) {
                continue;
            }
            if (isset($userID) && $User->username != $userID) {
                continue;
            }
            $date = self::niceDate($User->datetime);
            $actions = array(
                0 => _('Logout'),
                1 => _('Login'),
                99 => _('Service Start')
            );
            $logintext = (
                !in_array($User->action, array_keys($actions)) ?
                _('N/A') :
                $actions[$User->action]
            );
            unset($actions);
            $username = $User->username;
            $hostname = $User->host->name;
            $hostmac = $User->host->primac;
            $hostdesc = $User->host->description;
            $date = $date->format('Y-m-d H:i:s');
            $desc = $User->description;
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
        $this->ReportMaker->appendHTML($this->process(12));
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        if (is_array($this->data) && count($this->data) > 0) {
            echo '<div class="text-center">';
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
            echo '</div>';
        }
        $this->ReportMaker->outputReport(0, true);
        echo '</div>';
        echo '</div>';
        echo '</div>';
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
    }
}
