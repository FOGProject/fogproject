<?php
require('../commons/base.inc.php');
try {
    if (!$FOGCore->getSetting('FOG_MINING_ENABLE') == 1) throw new Exception(_('Donations are disabled!'));
    $abortHour = $FOGCore->getSetting('FOG_MINING_FULL_RESTART_HOUR');
    $ignoreWeekends = $FOGCore->getSetting('FOG_MINING_FULL_RUN_ON_WEEKEND');
    $date = $FOGCore->nice_date();
    if  ($ignoreWeekends == 1 && $date->format('N') > 5) echo '#!OK';
    else {
        if (!($abortHour == ($date->format('G')))) throw new Exception(_('Restarting the client...'));
        echo "#!OK";
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
