<?php
/**
 * Returns a listing of all ous in the system.
 *
 * PHP version 5
 *
 * @category OUlisting
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Returns a listing of all ous in the system.
 *
 * @category OUlisting
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
try {
    Route::names('ou');
    $ounames = json_decode(
        Route::getData()
    );
    if (count($ounames ?: []) < 1) {
        throw new Exception(
            _('There are no ous on this server')
        );
    }
    foreach ($ounames as &$ou) {
        printf(
            '\tID# %d\t-\t%s\n',
            $ou->id,
            $ou->name
        );
        unset($ou);
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
exit;
