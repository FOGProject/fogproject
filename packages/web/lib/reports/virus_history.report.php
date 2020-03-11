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
        Route::listem('virus');
        $Viruses = json_decode(
            Route::getData()
        );
        $Viruses = $Viruses->viruss;
        foreach ((array)$Viruses as &$Virus) {
            self::getClass('HostManager')
                ->getHostByMacAddresses($Virus->mac);
            if (!self::$Host->isValid()) {
                continue;
            }
            $hostName = self::$Host->get('name');
            unset($Host);
            $virusName = $Virus->name;
            $virusFile = $Virus->file;
            $virusMode = (
                $Virus->mode == 'q' ?
                _('Quarantine') :
                _('Report')
            );
            $virusDate = self::niceDate($Virus->date);
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
                    $this->ReportMaker->addCSVCell($Virus->$classGet);
                    break;
                }
                unset($classGet);
            }
            unset($Virus);
            $this->ReportMaker->endCSVLine();
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
                'VirusHistory',
                _('Export CSV'),
                _('Export CSV'),
                self::$csvfile,
                'VirusHistory',
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
