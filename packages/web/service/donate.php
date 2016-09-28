<?php
/**
 * Dark coin mining handler
 * Dark coin mining is only used for donations in a non-currency mode
 * and is not required to be on or running.
 *
 * PHP version 5
 *
 * @category Donate
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Dark coin mining handler
 * Dark coin mining is only used for donations in a non-currency mode
 * and is not required to be on or running.
 *
 * @category Donate
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
try {
    $miningInfo = array(
        'FOG_MINING_ENABLE',
        'FOG_MINING_FULL_RESTART_HOUR',
        'FOG_MINING_FULL_RUN_ON_WEEKEND',
    );
    list(
        $enabled,
        $abortHour,
        $ignoreWeekends
    ) = FOGCore::getSubObjectIDs(
        'Service',
        array('name' => $miningInfo),
        'value',
        false,
        'AND',
        'name',
        false,
        ''
    );
    if (!$enabled) {
        throw new Exception(
            _('Donations are disabled')
        );
    }
    $date = FOGCore::niceDate();
    if ($ignoreWeekends && $date->format('N') > 5) {
        throw new Exception('#!OK');
    }
    if ($abortHour == $date->format('G')) {
        throw new Exception(
            _('Restarting the client...')
        );
    }
    throw new Exception('#!OK');
} catch (Exception $e) {
    echo $e->getMessage();
}
