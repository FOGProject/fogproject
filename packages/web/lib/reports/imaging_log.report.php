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
        Route::listem('imaginglog');
        $ImagingLogs = json_decode(
            Route::getData()
        );
        $ImagingLogs = $ImagingLogs->imaginglogs;
        foreach ((array)$ImagingLogs as &$ImagingLog) {
            if (!$ImagingLog->host->id) {
                continue;
            }
            $start = $ImagingLog->start;
            $end = $ImagingLog->finish;
            if (!self::validDate($start)) {
                continue;
            }
            $diff = self::diff($start, $end);
            $start = self::niceDate($start);
            $end = self::niceDate($end);
            $hostname = $ImagingLog->host->name;
            $hostid = $ImagingLog->host->id;
            $hostmac = $ImagingLog->host->primac;
            $hostdesc = $ImagingLog->host->description;
            $typename = $ImagingLog->type;
            if (in_array($typename, array_keys($imgTypes))) {
                $typename = $imgTypes[$typename];
            }
            $createdBy = (
                $ImagingLog->createdBy ?:
                self::$FOGUser->get('name')
            );
            if ($ImagingLog->image->id) {
                $imagename = $ImagingLog->image->name;
                $imagepath = $ImagingLog->image->path;
            } else {
                $imagename = $ImagingLog->image;
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
                'ImagingLog',
                _('Export CSV'),
                _('Export CSV'),
                self::$csvfile,
                'ImagingLog',
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
