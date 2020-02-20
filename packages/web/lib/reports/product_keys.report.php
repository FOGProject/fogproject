<?php
/**
 * Reports Host product keys
 *
 * PHP version 5
 *
 * @category ProductKeys
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Reports Host product keys.
 *
 * @category ProductKeys
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Product_Keys extends ReportManagementPage
{
    /**
     * The page to display.
     *
     * @return void
     */
    public function file()
    {
        $this->title =_('Host Product Keys');
        $csvHead = array(
            _('Host ID') => 'id',
            _('Host Name') => 'name',
            _('Host Desc') => 'description',
            _('Host MAC') => 'mac',
            _('Host Created') => 'createdType',
            _('Host Product Key') => 'productKey',
            _('Host AD Join') => 'useAD',
            _('Host AD OU') => 'ADOU',
            _('Host AD Domain') => 'ADDomain',
            _('Host Kernel') => 'kernel',
            _('Host HD Device') => 'kernelDevice',
            _('Image ID') => 'id',
            _('Image Name') => 'name',
            _('Image Desc') => 'description',
            _('OS Name') => 'name'
        );
        foreach ((array)$csvHead as $csvHeader => &$classGet) {
            $this->ReportMaker->addCSVCell($csvHeader);
            unset($classGet);
        }
        $this->ReportMaker->endCSVLine();
        $this->headerData = array(
            _('Hostname'),
            _('Host MAC'),
            _('Host Product Key'),
            _('Image Name')
        );
        $this->templates = array(
            '${host_name}',
            '${host_mac}',
            '${host_productKey}',
            '${image_name}'
        );
        Route::listem('host');
        $Hosts = json_decode(
            Route::getData()
        );
        $Hosts = $Hosts->hosts;
        foreach ((array)$Hosts as &$Host) {
            $productKey = $Host->productKey;
            $productKeytest = self::aesdecrypt($productKey);
            if ($test_base64 = base64_decode($productKeytest)) {
                if (mb_detect_encoding($test_base64, 'utf-8', true)) {
                    $productKey = $test_base64;
                } elseif (mb_detect_encoding($productKeytest, 'utf-8', true)) {
                    $pruductKey = $productKeytest;
                }
            }
            $Image = $Host->image;
            $imgID = $Image->id;
            $imgName = $Image->name;
            $imgDesc = $Image->description;
            unset($Image);
            $this->data[] = array(
                'host_name' => $Host->name,
                'host_mac' => $Host->primac,
                'host_productKey' => $productKey,
                'image_name' => $imgName
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
                case _('Host AD Join'):
                    $this->ReportMaker->addCSVCell(
                        (
                            $Host->useAD == 1 ?
                            _('Yes') :
                            _('No')
                        )
                    );
                    break;
                case _('Host Product Key'):
                    $this->ReportMaker->addCSVCell($productKey);
                    break;
                default:
                    $this->ReportMaker->addCSVCell($Host->$classGet);
                    break;
                }
                unset($classGet);
            }
            unset($Host);
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
                'Product_Keys',
                _('Export CSV'),
                _('Export CSV'),
                self::$csvfile,
                'Product_Keys',
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
