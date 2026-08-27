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

namespace FOG;

use FOG\Router\HTTPResponseCodes;
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
        $this->title =_('Host Product Keys');

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
        echo _('Host Product Keys');
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        echo $this->render(12, 'hostkeys-table');
        echo '</div>';
        echo '</div>';
    }
    /**
     * Display list of history items.
     *
     * @return void
     */
    public function getList()
    {
        header('Content-type: application/json');
        Route::listem('host');
        http_response_code(HTTPResponseCodes::HTTP_SUCCESS);
        echo Route::getData();
        exit;
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\Product_Keys', 'Product_Keys');
