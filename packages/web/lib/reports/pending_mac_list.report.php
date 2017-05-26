<?php
/**
 * Pending MAC report.
 *
 * PHP Version 5
 *
 * @category Pending_MAC_List
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Pending MAC report.
 *
 * @category Pending_MAC_List
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Pending_MAC_List extends ReportManagementPage
{
    /**
     * The page to display.
     *
     * @return void
     */
    public function file()
    {
        if ($_REQUEST['aprvall'] == 1) {
            self::getClass('MACAddressAssociationManager')
                ->update(
                    '',
                    '',
                    array(
                        'pending' => 0
                    )
                );
            self::setMessage(_('All Pending MACs approved.'));
        }
        $this->title = _('Pending MAC Export');
        printf(
            $this->reportString,
            'PendingMACsList',
            _('Export CSV'),
            _('Export CSV'),
            self::$csvfile,
            'PendingMACsList',
            _('Export PDF'),
            _('Export PDF'),
            self::$pdffile
        );
        if (self::$pendingMACs > 0) {
            printf(
                '<p class="c"><a href="%s&aprvall=1">%s</a></p>',
                $this->formAction,
                _('Approve All Pending MACs for all hosts')
            );
        }
        echo '</h2>';
        $csvHead = array(
            _('Host ID'),
            _('Host name'),
            _('Host Primary MAC'),
            _('Host Desc'),
            _('Host Pending MAC'),
        );
        foreach ((array)$csvHead as $csvHeader => &$classGet) {
            $this->ReportMaker->addCSVCell($csvHeader);
        }
        unset($classGet);
        $this->ReportMaker->endCSVLine();
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class='
            . '"toggle-checkboxAction" id="toggler"/>'
            . '<label for="toggler"></label>',
            _('Host name'),
            _('Host Primary MAC'),
            _('Host Pending MAC'),
        );
        $this->templates = array(
            '<input type="checkbox" name="pendmac[]" value='
            . '"${id}" class="toggle-action" id="pend-${id}"/>'
            . '<label for="pend-${id}"></label>',
            '${host_name}',
            '${host_mac}',
            '${host_pend}',
        );
        $this->attributes = array(
            array(
                'width' => 16,
                'class' => 'l filter-false'
            ),
            array(),
            array(),
            array(),
        );
        foreach ((array)self::getClass('MACAddressAssociationmanager')
            ->find(
                array('pending' => 1)
            ) as &$Pending
        ) {
            $PendingMAC = new MACAddress($Pending->get('mac'));
            $Host = $Pending->getHost();
            if (!$Host->isValid()) {
                continue;
            }
            $hostID = $Host->get('id');
            $hostName = $Host->get('name');
            $hostMac = $Host->get('mac');
            $hostDesc = $Host->get('description');
            $hostPend = $PendingMAC->__toString();
            unset($Host, $PendingMAC);
            $this->data[] = array(
                'id' => $Pending->get('id'),
                'host_name' => $hostName,
                'host_mac' => $hostMac,
                'host_pend' => $hostPend,
            );
            $this->ReportMaker->addCSVCell($hostID);
            $this->ReportMaker->addCSVCell($hostName);
            $this->ReportMaker->addCSVCell($hostMac);
            $this->ReportMaker->addCSVCell($hostDesc);
            $this->ReportMaker->addCSVCell($hostPend);
            $this->ReportMaker->endCSVLine();
            unset($hostID, $hostName, $hostMac, $hostDesc, $hostPend);
            unset($Host, $PendingMAC);
        }
        if (count($this->data) > 0) {
            printf(
                '<form method="post" action="%s">',
                $this->formAction
            );
        }
        $this->ReportMaker->appendHTML($this->__toString());
        $this->ReportMaker->outputReport(false);
        if (count($this->data) > 0) {
            printf(
                '<p class="c"><input name="approvependmac" type='
                . '"submit" value="%s"/>&nbsp;&nbsp;<input name='
                . '"delpendmac" type="submit" value="%s"/></p></form>',
                _('Approve selected pending macs'),
                _('Delete selected pending macs')
            );
        }
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
    }
    /**
     * Approves pending macs
     *
     * @return void
     */
    public function filePost()
    {
        if (isset($_REQUEST['approvependmac'])) {
            self::getClass('MACAddressAssociationManager')->update(
                array('id' => $_REQUEST['pendmac']),
                '',
                array('pending' => 0)
            );
        }
        if (isset($_REQUEST['delpendmac'])) {
            self::getClass('MACAddressAssociationManager')->destroy(
                array('id' => $_REQUEST['pendmac'])
            );
        }
        $appdel = (
            isset($_REQUEST['approvependmac']) ?
            _('approved') : _('deleted')
        );
        self::setMessage(
            sprintf(
                '%s %s %s',
                _('All pending macs'),
                $appdel,
                _('successfully')
            )
        );
        self::redirect("?node=$this->node");
    }
}
