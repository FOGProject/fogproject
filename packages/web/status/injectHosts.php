<?php
/**
 * Injects hosts
 *
 * PHP version 5
 *
 * @category Injecthosts
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Injects hosts
 *
 * @category Injecthosts
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
$names = array();
$macs = array();
$numtoinsert = 200;
for ($i=1;$i<=$numtoinsert;$i++) {
    $macs[] = implode(
        ':',
        str_split(
            substr(
                md5(
                    mt_rand()
                ),
                0,
                12
            ),
            2
        )
    );
    $names[] = substr(
        md5(
            mt_rand()
        ),
        0,
        15
    );
}
list(
    $first_id,
    $affected_rows
) = FOGCore::getClass('HostManager')->insertBatch(
    array('name'),
    $names
);
$ids = range($first_id, ($first_id + $affected_rows - 1));
$items = array();
$fields = array(
    'hostID',
    'mac',
    'primary'
);
foreach ($names as $i => &$name) {
    $items[] = array(
        $ids[$i],
        $macs[$i],
        1
    );
    unset($name);
}
FOGCore::getClass('MACAddressAssociationManager')
    ->insertBatch(
        $fields,
        $items
    );
echo _('All done');
