<?php
/**
 * Hostname loop simply checks the host doesn't
 * already exist.
 *
 * PHP version 5
 *
 * @category Hostnameloop
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Hostname loop simply checks the host doesn't
 * already exist.
 *
 * @category Hostnameloop
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
try {
    $host = $_REQUEST['host'];
    $host = trim($host);
    $host = base64_decode($host);
    $host = trim($host);
    $Host = FOGCore::getClass('Host')
        ->set('name', $host)
        ->load('name');
    if ($Host->isValid()) {
        $msg = sprintf(
            "\t%s\n\t%s: %s",
            _('A host with that name already exists'),
            _('The primary mac associated is'),
            $Host->get('mac')->__toString()
        );
        throw new Exception($msg);
    }
    $msg = '#!ok';
} catch (Exception $e) {
    $msg = $e->getMessage();
}
echo $msg;
exit;
