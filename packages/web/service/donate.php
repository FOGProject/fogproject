<?php
require('../commons/base.inc.php');
try {
    $miningInfo = array(
        'FOG_MINING_ENABLE',
        'FOG_MINING_FULL_RESTART_HOUR',
        'FOG_MINING_FULL_RUN_ON_WEEKEND',
    );
    $serviceSettings = FOGCore::getClass('ServiceManager')->find(array('value'=>$miningInfo), 'AND', 'name', 'ASC', '=', false, false, 'value', true, '');
    $enabled = array_shift($serviceSettings);
    if (!$enabled) {
        throw new Exception(_('Donations are disabled'));
    }
    $abortHour = array_shift($serviceSettings);
    $ignoreWeekends = array_shift($serviceSettings);
    $date = FOGCore::nice_date();
    if ($ignoreWeekends && $date->format('N') > 5) {
        throw new Exception('#!OK');
    }
    if ($abortHour == $date->format('G')) {
        throw new Exception(_('Restarting the client...'));
    }
    throw new Exception('#!OK');
} catch (Exception $e) {
    echo $e->getMessage();
}
