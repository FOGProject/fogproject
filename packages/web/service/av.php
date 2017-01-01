<?php
/**
 * Antivirus handler
 *
 * PHP version 5
 *
 * @category Antivirus
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Antivirus handler
 *
 * @category Antivirus
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
try {
    if (trim($_REQUEST['mode']) != array('q', 's')) {
        throw new Exception(_('Invalid operational mode'));
    }
    $string = explode(':', base64_decode($_REQUEST['string']));
    $vInfo = explode(' ', trim($string[1]));
    $Virus = FOGCore::getClass('Virus')
        ->set('name', $vInfo[0])
        ->set('mac', strtolower($_REQUEST['mac']))
        ->set('file', $string[0])
        ->set('date', FOGCore::formatTime('now', 'Y-m-d H:i:s'))
        ->set('mode', $_REQUEST['mode']);
    if (!$Virus->save()) {
        throw new Exception(_('Failed'));
    }
    throw new Exception(_('Accepted'));
} catch (Exception $e) {
    echo $e->getMessage();
}
