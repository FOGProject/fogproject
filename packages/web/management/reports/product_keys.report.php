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
     * The node this page displays from.
     *
     * @var string
     */
    public $node = 'report';
    /**
     * Initializes the report page.
     *
     * @param string $name The name if other than this.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Product Keys';
        parent::__construct($this->name);
        $this->index();
    }
    /**
     * The page to display.
     *
     * @return void
     */
    public function index()
    {
        $this->title =_('Host Product Keys');
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
        $report = self::getClass('ReportMaker');
        $report
            ->appendHTML(
                '<table cellpadding="0" cellspacing="0" border="0" width="100%">'
            )->appendHTML(
                '<tr bgcolor="#BDBDBD">'
            )->appendHTML(
                '<td><b>Hostname</b></td>'
            )->appendHTML(
                '<td><b>MAC</b></td><td>'
            )->appendHTML(
                '<b>Registered</b></td></tr>'
            )->addCSVCell('Hostname')
            ->addCSVCell('MAC')
            ->addCSVCell('Registered')
            ->addCSVCell('Product Key')
            ->endCSVLine();
        $cnt = 0;
        foreach ((array)self::getClass('HostManager')
            ->find('', '', '', '', '', 'name') as &$Host
        ) {
            $bg = ($cnt++ % 2 == 0 ? "#E7E7E7" : '');
            $report->appendHTML(
                sprintf(
                    '<tr bgcolor="%s"><td>%s</td><td>%s</td><td>%s</td></tr>',
                    $bg,
                    $Host->get('name'),
                    $Host->get('mac'),
                    $Host->get('createdTime')
                )
            )->addCSVCell(
                $Host->get('name')
            )->addCSVCell(
                $Host->get('mac')
            )->addCSVCell(
                $Host->get('createdTime')
            )->addCSVCell(
                self::aesdecrypt($Host->get('productKey'))
            )->endCSVLine();
            unset($Host);
        }
        $report->appendHTML('</table>');
        $report->outputReport(0);
        $_SESSION['foglastreport'] = serialize($report);
    }
}
