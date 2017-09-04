<?php
/**
 * Snapin Log report
 *
 * PHP Version 5
 *
 * @category Snapin_Log
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Snapin Log report
 *
 * @category Snapin_Log
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Snapin_Log extends ReportManagementPage
{
    /**
     * Initial display
     *
     * @return void
     */
    public function file()
    {
        $this->title = _('FOG Snapin Log');
        $this->headerData = array(
            _('Host Name'),
            _('Snapin Name'),
            _('State'),
            _('Return Code'),
            _('Return Desc'),
            _('Checkin Time'),
            _('Complete Time')
        );
        $this->templates = array(
            '${host_name}',
            '${snap_name}',
            '${snap_state}',
            '${snap_return}',
            '${snap_detail}',
            '${checkin}',
            '${complete}'
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(),
            array(),
            array(),
            array()
        );
        $csvHead = array(
            _('Host ID'),
            _('Host Name'),
            _('Host MAC'),
            _('Snapin ID'),
            _('Snapin Name'),
            _('Snapin Description'),
            _('Snapin File'),
            _('Snapin Args'),
            _('Snapin Run With'),
            _('Snapin Run With Args'),
            _('Snapin State'),
            _('Snapin Return Code'),
            _('Snapin Return Detail'),
            _('Snapin Creation Date'),
            _('Snapin Creation Time'),
            _('Job Create Date'),
            _('Job Create Time'),
            _('Task Checkin Date'),
            _('Task Checkin Time'),
            _('Task Complete Date'),
            _('Task Complete Time')
        );
        foreach ((array)$csvHead as $i => &$csvHeader) {
            $this->ReportMaker->addCSVCell($csvHeader);
            unset($csvHeader);
        }
        $this->ReportMaker->endCSVLine();
        Route::listem('snapintask');
        $SnapinTasks = json_decode(
            Route::getData()
        );
        $SnapinTasks = $SnapinTasks->snapintasks;
        foreach ((array)$SnapinTasks as &$SnapinTask) {
            $start = self::niceDate($SnapinTask->checkin);
            $end = self::niceDate($SnapinTask->complete);
            if (!self::validDate($start)) {
                continue;
            }
            $Snapin = $SnapinTask->snapin;
            if (!$Snapin->id) {
                continue;
            }
            $SnapinJob = $SnapinTask->snapinjob;
            if (!$SnapinJob->id) {
                continue;
            }
            $Host = $SnapinJob->host;
            if (!$Host->id) {
                continue;
            }
            $State = $SnapinTask->state;
            $this->data[] = array(
                'host_name' => $Host->name,
                'checkin' => $SnapinTask->checkin,
                'complete' => $SnapinTask->complete,
                'snap_name' => $Snapin->name,
                'snap_state' => $State->name,
                'snap_return' => $SnapinTask->return,
                'snap_detail' => $SnapinTask->detail,
                'snap_create' => self::formatTime(
                    $Snapin->createdTime,
                    'Y-m-d'
                ),
                'snap_time'=> self::formatTime(
                    $Snapin->createdTime,
                    'H:i:s'
                )
            );
            $this->ReportMaker
                ->addCSVCell($Host->id)
                ->addCSVCell($Host->name)
                ->addCSVCell($Host->primac)
                ->addCSVCell($Snapin->id)
                ->addCSVCell($Snapin->name)
                ->addCSVCell($Snapin->description)
                ->addCSVCell($Snapin->file)
                ->addCSVCell($Snapin->args)
                ->addCSVCell($Snapin->runWith)
                ->addCSVCell($Snapin->runWithArgs)
                ->addCSVCell($State->name)
                ->addCSVCell($SnapinTask->return)
                ->addCSVCell($SnapinTask->detail)
                ->addCSVCell(
                    self::formatTime(
                        $Snapin->createdTime,
                        'Y-m-d'
                    )
                )
                ->addCSVCell(
                    self::formatTime(
                        $Snapin->createdTime,
                        'H:i:s'
                    )
                )
                ->addCSVCell(
                    self::formatTime(
                        $SnapinJob->createdTime,
                        'Y-m-d'
                    )
                )
                ->addCSVCell(
                    self::formatTime(
                        $SnapinJob->createdTime,
                        'H:i:s'
                    )
                )
                ->addCSVCell(
                    self::formatTime(
                        $SnapinTask->checkin,
                        'Y-m-d'
                    )
                )
                ->addCSVCell(
                    self::formatTime(
                        $SnapinTask->checkin,
                        'H:i:s'
                    )
                )
                ->addCSVCell(
                    self::formatTime(
                        $SnapinTask->complete,
                        'Y-m-d'
                    )
                )
                ->addCSVCell(
                    self::formatTime(
                        $SnapinTask->complete,
                        'H:i:s'
                    )
                )
                ->endCSVLine();
            unset(
                $Host,
                $Snapin,
                $SnapinJob,
                $SnapinTask,
                $State
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
        if (count($this->data) > 0) {
            echo '<div class="text-center">';
            printf(
                $this->reportString,
                'SnapinLog',
                _('Export CSV'),
                _('Export CSV'),
                self::$csvfile,
                'SnapinLog',
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
