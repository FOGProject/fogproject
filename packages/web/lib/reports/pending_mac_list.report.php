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
        $aprvall = filter_input(INPUT_GET, 'aprvall');
        if ($aprvall == 1) {
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
        $this->ReportMaker->appendHTML($this->process(12));
        echo '<div class="col-xs-9">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        if (is_array($this->data) && count($this->data) > 0) {
            echo '<div class="text-center">';
            echo '<a href="'
                . $this->formAction
                . '&aprvall=1">'
                . _('Approve All Pending MACs for All Hosts')
                . '</a>';
            echo '</div>';
            echo '<div class="text-center">';
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
            echo '</div>';
        }
        $this->ReportMaker->outputReport(0, true);
        echo '</div>';
        echo '</div>';
        if (is_array($this->data) && count($this->data) > 0) {
            echo '<div class="panel panel-info">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Pending MAC Actions');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            echo '<div class="form-group">';
            echo '<label for="approvependmac" class="control-label col-xs-4">';
            echo _('Approve Selected MACs');
            echo '</label>';
            echo '<div class="col-xs-8">';
            echo '<button name="approvependmac" type="submit" class='
                . '"btn btn-info btn-block" id="approvependmac">';
            echo _('Approve');
            echo '</button>';
            echo '</div>';
            echo '</div>';
            echo '<div class="form-group">';
            echo '<label for="delpendmac" class="control-label col-xs-4">';
            echo _('Delete Selected MACs');
            echo '</label>';
            echo '<div class="col-xs-8">';
            echo '<button name="delpendmac" type="submit" class='
                . '"btn btn-danger btn-block" id="delpendmac">';
            echo _('Delete');
            echo '</button>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        echo '</form>';
        echo '</div>';
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
    }
    /**
     * Approves pending macs
     *
     * @return void
     */
    public function filePost()
    {
        $pendmac = filter_input_array(
            INPUT_POST,
            array(
                'pendmac' => array(
                    'flags' => FILTER_REQUIRE_ARRAY
                )
            )
        );
        $pendmac = $pendmac['pendmac'];
        $pendmacs = self::getSubObjectIDs(
            'MACAddressAssociation',
            array('id' => $pendmac),
            'mac'
        );
        if (isset($_POST['approvependmac'])) {
            self::getClass('MACAddressAssociationManager')->update(
                array('id' => $pendmac),
                '',
                array('pending' => 0)
            );
            $msg = 'approved';
        }
        if (isset($_POST['delpendmac'])) {
            self::getClass('MACAddressAssociationManager')->destroy(
                array('id' => $pendmac)
            );
            $msg = 'deleted';
        }
        unset($pendmac);
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-success">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _("MACs $msg successfully");
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body text-center">';
        echo _("The follow MACs have been ${msg}.");
        echo '<br/>';
        echo '<ul class="nav nav-pills nav-stacked">';
        echo '<li><a href="#">';
        echo implode('</a></li><li><a href="#">', $pendmacs);
        echo '</a></li>';
        echo '</ul>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        //self::redirect("?node=$this->node");
    }
}
