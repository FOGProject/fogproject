<?php
/**
 * Reports hosts within.
 *
 * PHP version 5
 *
 * @category Host_List
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Reports hosts within.
 *
 * @category Host_List
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Host_List extends ReportManagementPage
{
    /**
     * Display page.
     *
     * @return void
     */
    public function file()
    {
        $this->title = _('Host Listing Export');
        printf(
            $this->reportString,
            'HostList',
            _('Export CSV'),
            _('Export CSV'),
            self::$csvfile,
            'HostList',
            _('Export PDF'),
            _('Export PDF'),
            self::$pdffile
        );
        $csvHead = array(
            _('Host ID') => 'id',
            _('Host Name') => 'name',
            _('Host Desc') => 'description',
            _('Host MAC') => 'mac',
            _('Host Created') => 'createdTime',
            _('Image ID') => 'id',
            _('Image Name') => 'name',
            _('Image Desc') => 'description',
            _('AD Join') => 'useAD',
            _('AD OU') => 'ADOU',
            _('AD Domain') => 'ADDomain',
            _('Kernel') => 'kernel',
            _('HD Device') => 'kernelDevice',
            _('OS Name') => 'name',
        );
        foreach ((array)$csvHead as $csvHeader => &$classGet) {
            $this->ReportMaker->addCSVCell($csvHeader);
            unset($classGet);
        }
        $this->ReportMaker->endCSVLine();
        $this->headerData = array(
            _('Hostname'),
            _('Host MAC'),
            _('Image Name'),
        );
        $this->templates = array(
            '${host_name}',
            '${host_mac}',
            '${image_name}',
        );
        foreach ((array)self::getClass('HostManager')
            ->find() as &$Host
        ) {
            $Image = $Host->getImage();
            $imgID = $Image->get('id');
            $imgName = $Image->get('name');
            $imgDesc = $Image->get('description');
            unset($Image);
            $this->data[] = array(
                'host_name'=>$Host->get('name'),
                'host_mac'=>$Host->get('mac'),
                'image_name'=>$imgName,
            );
            foreach ((array)$csvHead as $head => &$classGet) {
                switch ($head) {
                case _('Image ID'):
                    $this->ReportMaker->addCSVCell($imgID);
                    break;
                case _('Image Name'):
                    $this->ReportMaker->addCSVCell($imgName);
                    break;
                case _('Image Desc'):
                    $this->ReportMaker->addCSVCell($imgDesc);
                    break;
                case _('AD Join'):
                    $this->ReportMaker->addCSVCell(
                        (
                            $Host->get('useAD') == 1 ?
                            _('Yes') :
                            _('No')
                        )
                    );
                    break;
                default:
                    $this->ReportMaker->addCSVCell($Host->get($classGet));
                    break;
                }
                unset($classGet);
            }
            unset($Host);
            $this->ReportMaker->endCSVLine();
        }
        $this->ReportMaker->appendHTML($this->__toString());
        $this->ReportMaker->outputReport(0);
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
    }
}
