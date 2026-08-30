<?php
/**
 * Reports Host product keys
 *
 * PHP version 7.4+
 *
 * @category ProductKeys
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Reports;

use FOG\Pages\ReportManagement;
use FOG\Router\Route;

/**
 * Reports Host product keys.
 *
 * @category ProductKeys
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Product_Keys extends ReportManagement
{
    /**
     * The page to display.
     *
     * @return void
     */
    public function file()
    {
        $this->title = self::reportTitle();

        $this->headerData = [
            _('Host Name'),
            _('Primary MAC'),
            _('Product Key')
        ];

        $this->attributes = [
            [],
            [],
            []
        ];

        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        echo $this->render(12, 'hostkeys-table');
        echo '</div>';
        echo '</div>';
    }
    /**
     * The rows this report serves.
     *
     * Split from the emit so the grid and the CSV export run the same
     * query -- see ReportManagement::exportAll().
     *
     * @return array
     */
    protected function reportRows()
    {
        Route::listem('host');

        // Decoded rather than echoed straight through, because exportAll()
        // needs the rows as data and Route hands its payload back encoded.
        // getList() re-encodes; that round trip costs a fraction of the
        // query it wraps and buys one read path instead of two.
        return (array) json_decode(Route::getData(), true);
    }
}
