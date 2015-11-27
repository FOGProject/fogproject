<?php
require('../commons/base.inc.php');
try {
    $Host = $FOGCore->getHostItem(false);
    $Task = $Host->get('task');
    if (!$Task->isValid()) throw new Exception(sprintf('%s: %s (%s)', _('No Active Task found for Host'), $Host->get('name'),$Host->get('mac')->__toString()));
    $Image = $Task->getImage();
    if (!$Image->isValid()) throw new Exception(_('Invalid image'));
    $str = explode('@',base64_decode($_REQUEST['status']));
    $imagingTasks = in_array($Task->get('typeID'),array(1,2,8,15,16,17,24));
    if ($imagingTasks) {
        if ($str[0] && $str[1] && $str[2] && $str[3] && $str[4] && $str[5]) {
            $Task->set('bpm', $str[0])
                ->set('timeElapsed', $str[1])
                ->set('timeRemaining', $str[2])
                ->set('dataCopied', $str[3])
                ->set('dataTotal', $str[4])
                ->set('percent',trim($str[5]))
                ->set('pct',trim($str[5]))
                ->save();
            if ($str[6] > (int)$Image->get('size')) $Image->set('size',$str[6])->save();
        }
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
