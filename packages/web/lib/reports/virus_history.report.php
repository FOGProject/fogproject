<?php
/**
 * Virus report.
 *
 * PHP Version 5
 *
 * @category Virus_History
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Virus report.
 *
 * @category Virus_History
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Virus_History extends ReportManagementPage
{
    /**
     * The page to display.
     *
     * @return void
     */
    public function file()
    {
        $this->title = _('FOG Virus Summary');
        printf(
            $this->reportString,
            'VirusHistory',
            _('Export CSV'),
            _('Export CSV'),
            self::$csvfile,
            'VirusHistory',
            _('Export PDF'),
            _('Export PDF'),
            self::$pdffile
        );
        printf(
            '<form method="post" action="%s"><h2><a href="#">'
            . '<input onclick="this.form.submit()" type='
            . '"checkbox" class="delvid" name="delvall" id='
            . '"delvid" value="all"/><label for="delvid">(%s)'
            . '</label></a></h2></form>',
            $this->formAction,
            _('clear all history')
        );
        $csvHead = array(
            _('Host Name') => 'name',
            _('Virus Name') => 'name',
            _('File') => 'file',
            _('Mode') => 'mode',
            _('Date') => 'date'
        );
        $this->headerData = array(
            _('Host name'),
            _('Virus Name'),
            _('File'),
            _('Mode'),
            _('Date'),
            _('Clear')
        );
        $this->templates = array(
            '${host_name}',
            '<a href="http://www.google.com/search?q=${vir_name}">${vir_name}</a>',
            '${vir_file}',
            '${vir_mode}',
            '${vir_date}',
            sprintf(
                '<input type="checkbox" onclick="this.form.submit()" class='
                . '"delvid" value="${vir_id}" id="vir${vir_id}" name='
                . '"delvid"/><label for="for${vir_id}" class='
                . '"icon icon-hand" title="%s ${vir_name}">'
                . '<i class="fa fa-minus-circle link"></i></label>',
                _('Delete')
            )
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(),
            array(),
            array('class' => 'filter-false')
        );
        foreach ((array)$csvHead as $csvHeader => &$classGet) {
            $this->ReportMaker->addCSVCell($csvHeader);
            unset($classGet);
        }
        $this->ReportMaker->endCSVLine();
        foreach ((array)self::getClass('VirusManager')
            ->find() as &$Virus
        ) {
            $Host = self::getClass('HostManager')
                ->getHostByMacAddresses($Virus->get('mac'));
            if (!$Host->isValid()) {
                continue;
            }
            $hostName = $Host->get('name');
            unset($Host);
            $virusName = $Virus->get('name');
            $virusFile = $Virus->get('file');
            $virusMode = (
                $Virus->get('mode') == 'q' ?
                _('Quarantine') :
                _('Report')
            );
            $virusDate = self::niceDate($Virus->get('date'));
            $this->data[] = array(
                'host_name' => $hostName,
                'vir_id' => $id,
                'vir_name' => $virusName,
                'vir_file' => $virusFile,
                'vir_mode' => $virusMode,
                'vir_date' => self::formatTime(
                    $virusDate,
                    'Y-m-d H:i:s'
                ),
            );
            foreach ((array)$csvHead as $head => &$classGet) {
                switch ($head) {
                case _('Host name'):
                    $this->ReportMaker->addCSVCell($hostName);
                    break;
                case _('Mode'):
                    $this->ReportMaker->addCSVCell($virusMode);
                    break;
                default:
                    $this->ReportMaker->addCSVCell($Virus->get($classGet));
                    break;
                }
                unset($classGet);
            }
            unset($Virus);
            $this->ReportMaker->endCSVLine();
        }
        unset($Virus);
        $this->ReportMaker->appendHTML($this->__toString());
        printf(
            '<form method="post" action="%s">',
            $this->formAction
        );
        $this->ReportMaker->outputReport(false);
        echo '</form>';
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
    }
    /**
     * Form submitted.
     *
     * @return void
     */
    public function filePost()
    {
        if ($_REQUEST['delvall'] == 'all') {
            self::getClass('VirusManager')->destroy();
            self::setMessage(_("All Virus' cleared"));
            self::redirect($this->formAction);
        } elseif (is_numeric($_REQUEST['delvid'])) {
            self::getClass('Virus', $_REQUEST['delvid'])->destroy();
            self::setMessage(_('Virus cleared'));
            self::redirect($this->formAction);
        }
    }
}
