<?php
/**
 * Imaging Log report
 *
 * PHP Version 5
 *
 * @category Imaging_Log
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Imaging Log report
 *
 * @category Imaging_Log
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Imaging_Log extends ReportManagementPage
{
    /**
     * Initial display
     *
     * @return void
     */
    public function file()
    {
        $this->title = _('FOG Imaging Log');
        printf(
            $this->reportString,
            'ImagingLog',
            _('Export CSV'),
            _('Export CSV'),
            self::$csvfile,
            'ImagingLog',
            _('Export PDF'),
            _('Export PDF'),
            self::$pdffile
        );
        $this->headerData = array(
            _('Created By'),
            _('Host'),
            _('Start'),
            _('End'),
            _('Duration'),
            _('Image'),
            _('Type')
        );
        $this->templates = array(
            '${createdBy}',
            '${host_name}',
            '<small>${start_date} ${start_time}</small>',
            '<small>${end_date} ${end_time}</small>',
            '${duration}',
            '${image_name}',
            '${type}'
        );
        $csvHead = array(
            _('Created By'),
            _('Host ID'),
            _('Host Name'),
            _('Host MAC'),
            _('Host Desc'),
            _('Image Name'),
            _('Image Path'),
            _('Start Date'),
            _('Start Time'),
            _('End Date'),
            _('End Time'),
            _('Duration'),
            _('Deploy/Capture')
        );
        $imgTypes = array(
            'up' => _('Capture'),
            'down' => _('Deploy')
        );
        foreach ($csvHead as &$csvHeader) {
            $this->ReportMaker->addCSVCell($csvHeader);
            unset($csvHeader);
        }
        $this->ReportMaker->endCSVLine();
        foreach (self::getClass('ImagingLogManager')
            ->find() as &$ImagingLog
        ) {
            if (!$ImagingLog->get('host')->isValid()) {
                continue;
            }
            $start = $ImagingLog->get('start');
            $end = $ImagingLog->get('finish');
            if (!self::validDate($start)) {
                continue;
            }
            $diff = self::diff($start, $end);
            $start = self::niceDate($start);
            $end = self::niceDate($end);
            $hostname = $ImagingLog->get('host')->get('name');
            $hostid = $ImagingLog->get('host')->get('id');
            $hostmac = $ImagingLog->get('host')->get('mac')->__toString();
            $hostdesc = $ImagingLog->get('host')->get('description');
            $typename = $ImagingLog->get('type');
            if (in_array($typename, array_keys($imgTypes))) {
                $typename = $imgTypes[$typename];
            }
            $createdBy = (
                $ImagingLog->get('createdBy') ?:
                self::$FOGUser->get('name')
            );
            if ($ImagingLog->getImage()->isValid()) {
                $imagename = $ImagingLog->getImage()->get('name');
                $imagepath = $ImagingLog->getImage()->get('path');
            } else {
                $imagename = $ImagingLog->get('image');
                $imagepath = _('Not Valid');
            }
            unset($ImagingLog);
            $startd = $start->format('Y-m-d');
            $startt = $start->format('H:i:s');
            $endd = $end->format('Y-m-d');
            $endt = $end->format('H:i:s');
            $this->data[] = array(
                'createdBy' => $createdBy,
                'host_name' => $hostname,
                'start_date' => $startd,
                'start_time' => $startt,
                'end_date' => $endd,
                'end_time' => $endt,
                'duration' => $diff,
                'image_name' => $imagename,
                'type' => $typename
            );
            $this->ReportMaker
                ->addCSVCell($createdBy)
                ->addCSVCell($hostid)
                ->addCSVCell($hostname)
                ->addCSVCell($hostmac)
                ->addCSVCell($hostdesc)
                ->addCSVCell($imagename)
                ->addCSVCell($imagepath)
                ->addCSVCell($startd)
                ->addCSVCell($startt)
                ->addCSVCell($endd)
                ->addCSVCell($endt)
                ->addCSVCell($diff)
                ->addCSVCell($typename)
                ->endCSVLine();
            unset(
                $createdBy,
                $hostid,
                $hostname,
                $hostmac,
                $hostdesc,
                $imagename,
                $imagepath,
                $startd,
                $startt,
                $endd,
                $endt,
                $diff,
                $typename
            );
        }
        $this->ReportMaker->appendHTML($this->__toString());
        $this->ReportMaker->outputReport(0);
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
    }
}
