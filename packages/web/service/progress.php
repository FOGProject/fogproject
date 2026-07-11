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
    $statusRaw = base64_decode(
        (string)
        (
            filter_input(INPUT_POST, 'status')
            ?: filter_input(INPUT_GET, 'status')
            ?: ''
        ),
        true
    );
    if (false === $statusRaw) {
        throw new Exception(_('Invalid status payload'));
    }
    $imagingTasks = $TaskType->isImagingTask();
    if ($imagingTasks) {
        $str = explode('@', $statusRaw);
        if (isset($str)
            && isset($str[0])
            && isset($str[1])
            && isset($str[2])
            && isset($str[3])
            && isset($str[4])
            && isset($str[5])
        ) {
            $Task->set('bpm', (float)$str[0])
                ->set('timeElapsed', strip_tags($str[1]))
                ->set('timeRemaining', strip_tags($str[2]))
                ->set('dataCopied', strip_tags($str[3]))
                ->set('dataTotal', strip_tags($str[4]))
                ->set('percent', max(0, min(100, (float)$str[5])))
                ->set('pct', max(0, min(100, (float)$str[5])))
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
    echo Initiator::e($e->getMessage());
}
exit;
