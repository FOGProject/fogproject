<?php
/**
 * Updates the progress information
 *
 * PHP version 5
 *
 * @category Progress
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Updates the progress information
 *
 * @category Progress
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
try {
    $Host = FOGCore::getHostItem(false);
    $Task = $Host->get('task');
    $TaskType = new TaskType($Task->get('typeID'));
    if (!$Task->isValid()) {
        throw new Exception(
            sprintf(
                '%s: %s (%s)',
                _('No Active Task found for Host'),
                $Host->get('name'),
                $Host->get('mac')->__toString()
            )
        );
    }
    $Image = $Task->getImage();
    if (!$Image->isValid()) {
        throw new Exception(_('Invalid image'));
    }
    $str = explode('@', base64_decode($_REQUEST['status']));
    $imagingTasks = $TaskType->isImagingTask();
    if ($imagingTasks) {
        if ($str[0]
            && $str[1]
            && $str[2]
            && $str[3]
            && $str[4]
            && $str[5]
        ) {
            $Task->set('bpm', $str[0])
                ->set('timeElapsed', $str[1])
                ->set('timeRemaining', $str[2])
                ->set('dataCopied', $str[3])
                ->set('dataTotal', $str[4])
                ->set('percent', trim($str[5]))
                ->set('pct', trim($str[5]))
                ->save();
        }
        $str[6] = trim($str[6]);
        if (empty($str[6])) {
            return;
        }
        if (strpos($Image->get('size'), $str[6]) !== false) {
            return;
        }
        $Image->set(
            'size',
            sprintf(
                '%s%s:',
                trim($Image->get('size')),
                $str[6]
            )
        )->save();
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
exit;
