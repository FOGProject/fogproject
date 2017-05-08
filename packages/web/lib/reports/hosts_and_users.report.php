<?php
/**
 * Reports hosts and the users within.
 *
 * PHP version 5
 *
 * @category Hosts_And_Users
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Reports hosts and the users within.
 *
 * @category Hosts_And_Users
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Hosts_And_Users extends ReportManagementPage
{
    /**
     * The page to display.
     *
     * @return void
     */
    public function file()
    {
        $this->title =_('FOG Hosts and Users Login');
        printf(
            $this->reportString,
            'Hosts_and_Users',
            _('Export CSV'),
            _('Export CSV'),
            self::$csvfile,
            'Hosts_and_Users',
            _('Export PDF'),
            _('Export PDF'),
            self::$pdffile
        );
        $report = self::getClass('ReportMaker');
        $report
            ->appendHTML(
                '<table cellpadding="0" cellspacing="0" border="0" width="100%">'
            )->appendHTML(
                '<tr bgcolor="#BDBDBD">'
            )->appendHTML(
                '<td><b>Hostname</b></td>'
            )->appendHTML(
                '<td><b>MAC</b></td><td>'
            )->appendHTML(
                '<b>Registered</b></td></tr>'
            )->addCSVCell('Hostname')
            ->addCSVCell('MAC')
            ->addCSVCell('Registered')
            ->endCSVLine();
        $cnt = 0;
        foreach ((array)self::getClass('HostManager')
            ->find('', '', '', '', '', 'name') as &$Host
        ) {
            $bg = ($cnt++ % 2 == 0 ? "#E7E7E7" : '');
            $report->appendHTML(
                sprintf(
                    '<tr bgcolor="%s"><td>%s</td><td>%s</td><td>%s</td></tr>',
                    $bg,
                    $Host->get('name'),
                    $Host->get('mac'),
                    $Host->get('createdTime')
                )
            )->addCSVCell(
                $Host->get('name')
            )->addCSVCell(
                $Host->get('mac')
            )->addCSVCell(
                $Host->get('createdTime')
            );
            if (count($Host->get('users')) < 1) {
                $report->endCSVLine();
                continue;
            }
            $report
                ->endCSVLine()
                ->appendHTML(
                    '<table cellpadding="0" cellspacing="0" border="0" width="100%">'
                )->appendHTML(
                    '<tr bgcolor="#BDBDBD"><td><b>Username</b></td>'
                )->appendHTML(
                    '<td><b>Action</b></td>'
                )->appendHTML(
                    '<td><b>Date</b></td><td>'
                )->appendHTML(
                    '<b>Time</b></td></tr>'
                )->addCSVCell('Username')
                ->addCSVCell('Action')
                ->addCSVCell('Time')
                ->endCSVLine();
            foreach ((array)self::getClass('UserTrackingManager')
                ->find(
                    array(
                        'id' => $Host->get('users'),
                        'action' => array(
                            '',
                            0,
                            1
                        )
                    ),
                    '',
                    'datetime',
                    'DESC',
                    '',
                    'username'
                ) as &$User
            ) {
                if ($User->get('username') == 'Array') {
                    continue;
                }
                $bg1 = ($cnt1++ % 2 == 0 ? "#E7E7E7" : '');
                $logintext = ($User->get('action') == 1 ? 'Login' : 'Logout');
                $report
                    ->appendHTML(
                        sprintf(
                            '<tr bgcolor="%s"><td>%s</td>',
                            $bg1,
                            $User->get('username')
                        )
                    )->appendHTML(
                        sprintf(
                            '<td>%s</td><td>%s</td><td>%s</td></tr>',
                            $logintext,
                            $User->get('date'),
                            $User->get('datetime')
                        )
                    )->addCSVCell($User->get('username'))
                    ->addCSVCell($logintext)
                    ->addCSVCell($User->get('date'))
                    ->addCSVCell($User->get('datetime'))
                    ->endCSVLine();
                unset($User);
            }
            $report
                ->appendHTML(
                    '</table>'
                )->appendHTML(
                    '<table cellpadding="0" cellspacing="0" border="0" width="100%">'
                )->appendHTML(
                    '<tr bgcolor="#BDBDBD"><td><b>Hostname</b></td>'
                )->appendHTML(
                    '<td><b>MAC</b></td><td><b>Registered</b></td></tr>'
                )->addCSVCell('Hostname')
                ->addCSVCell('MAC')
                ->addCSVCell('Registered')
                ->endCSVLine();
            unset($Host);
        }
        $report->appendHTML('</table>');
        $report->outputReport(0);
        $_SESSION['foglastreport'] = serialize($report);
    }
}
