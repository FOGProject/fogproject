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
    FOGCore::getHostItem(false);
    $Task = FOGCore::$Host->get('task');
    $TaskType = new TaskType($Task->get('typeID'));
    if (!$Task->isValid()) {
        throw new Exception(
            sprintf(
                '%s: %s (%s)',
                _('No Active Task found for Host'),
                FOGCore::$Host->get('name'),
                FOGCore::$Host->get('mac')->__toString()
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
        if (isset($str)
            && isset($str[0])
            && isset($str[1])
            && isset($str[2])
            && isset($str[3])
            && isset($str[4])
            && isset($str[5])
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
        if (!isset($str[6]) || empty(trim($str[6])) || !$Task->isCapture()) {
            exit;
        }
        $str[6] = trim($str[6]);
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
