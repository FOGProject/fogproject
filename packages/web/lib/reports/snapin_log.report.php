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
        $this->headerData = array(
            _('Host Name'),
            _('Snapin Name'),
            _('State'),
            _('Return Code'),
            _('Return Desc'),
            _('Create Date'),
            _('Create Time')
        );
        $this->templates = array(
            '${host_name}<br/><small>'
            . _('Started/Checked in')
            . ': ${checkin}<br/>'
            . _('Completed')
            . ': ${complete}</small>',
            '${snap_name}',
            '${snap_state}',
            '${snap_return}',
            '${snap_detail}',
            '${snap_create}',
            '${snap_time}',
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
        foreach ((array)self::getClass('SnapinTaskManager')
            ->find() as &$SnapinTask
        ) {
            $start = self::niceDate($SnapinTask->get('checkin'));
            $end = self::niceDate($SnapinTask->get('complete'));
            if (!self::validDate($start)) {
                continue;
            }
            $Snapin = $SnapinTask->getSnapin();
            if (!$Snapin->isValid()) {
                continue;
            }
            $SnapinJob = $SnapinTask->getSnapinJob();
            if (!$SnapinJob->isValid()) {
                continue;
            }
            $Host = $SnapinJob->getHost();
            if (!$Host->isValid()) {
                continue;
            }
            $State = new TaskState($SnapinTask->get('stateID'));
            $this->data[] = array(
                'host_name' => $Host->get('name'),
                'checkin' => $SnapinTask->get('checkin'),
                'complete' => $SnapinTask->get('complete'),
                'snap_name' => $Snapin->get('name'),
                'snap_state' => $State->get('name'),
                'snap_return' => $SnapinTask->get('return'),
                'snap_detail' => $SnapinTask->get('detail'),
                'snap_create' => self::formatTime(
                    $Snapin->get('createdTime'),
                    'Y-m-d'
                ),
                'snap_time'=> self::formatTime(
                    $Snapin->get('createdTime'),
                    'H:i:s'
                )
            );
            $this->ReportMaker
                ->addCSVCell($Host->get('id'))
                ->addCSVCell($Host->get('name'))
                ->addCSVCell($Host->get('mac')->__toString())
                ->addCSVCell($Snapin->get('id'))
                ->addCSVCell($Snapin->get('name'))
                ->addCSVCell($Snapin->get('description'))
                ->addCSVCell($Snapin->get('file'))
                ->addCSVCell($Snapin->get('args'))
                ->addCSVCell($Snapin->get('runWith'))
                ->addCSVCell($Snapin->get('runWithArgs'))
                ->addCSVCell($State->get('name'))
                ->addCSVCell($SnapinTask->get('return'))
                ->addCSVCell($SnapinTask->get('detail'))
                ->addCSVCell(
                    self::formatTime(
                        $Snapin->get('createdTime'),
                        'Y-m-d'
                    )
                )
                ->addCSVCell(
                    self::formatTime(
                        $Snapin->get('createdTime'),
                        'H:i:s'
                    )
                )
                ->addCSVCell(
                    self::formatTime(
                        $SnapinJob->get('createdTime'),
                        'Y-m-d'
                    )
                )
                ->addCSVCell(
                    self::formatTime(
                        $SnapinJob->get('createdTime'),
                        'H:i:s'
                    )
                )
                ->addCSVCell(
                    self::formatTime(
                        $SnapinTask->get('checkin'),
                        'Y-m-d'
                    )
                )
                ->addCSVCell(
                    self::formatTime(
                        $SnapinTask->get('checkin'),
                        'H:i:s'
                    )
                )
                ->addCSVCell(
                    self::formatTime(
                        $SnapinTask->get('complete'),
                        'Y-m-d'
                    )
                )
                ->addCSVCell(
                    self::formatTime(
                        $SnapinTask->get('complete'),
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
        $this->ReportMaker->appendHTML($this->__toString());
        $this->ReportMaker->outputReport(0);
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
    }
}
