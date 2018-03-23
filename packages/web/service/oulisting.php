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
    $ouCount = FOGCore::getClass('OUManager')
        ->count();
    if ($ouCount < 0) {
        throw new Exception(
            _('There are no ous on this server')
        );
    }
    $ouids = FOGCore::getSubObjectIDs('OU');
    $ounames = FOGCore::getSubObjectIDs(
        'OU',
        ['id' => $ouids],
        'name'
    );
    foreach ((array)$ouids as $index => $ouid) {
        printf(
            '\tID# %d\t-\t%s\n',
            $ouid,
            $ounames[$index]
        );
        unset(
            $ouid,
            $ounames[$index],
            $ouids[$index]
        );
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
exit;
