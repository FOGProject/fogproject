<?php
/**
 * Hostgetkey returns the host token for hostinfo getting
 *
 * PHP version 5
 *
 * @category Hostgetkey
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Hostgetkey returns the host token for hostinfo getting
 *
 * @category Hostgetkey
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
header('Content-Type: text/plain');
try {
    FOGCore::getHostItem(false, true);
    if (!FOGCore::$Host->isValid()) {
        throw new Exception(_('Host Invalid'));
    }
    #if (FOGCore::$useragent) {
    #    throw new Exception(_('Accessed inappropriately'));
    #}
    if (!FOGCore::$Host->get('task')->isValid()) {
        throw new Exception(_('Invalid Tasking'));
    }
    if (FOGCore::$Host->get('token') && FOGCore::$Host->get('tokenlock')) {
        throw new Exception(_('Host token is currently in use'));
    }
    if (!FOGCore::$Host->get('token')) {
        FOGCore::getClass('HostManager')->update(
            ['id' => FOGCore::$Host->get('id')],
            '',
            [
                'token' => FOGCore::createSecToken(),
                'tokenlock' => true
            ]
        );
        throw new Exception(FOGCore::$Host->get('token'));
    }
    if (FOGCore::$Host->isValid() && !FOGCore::$Host->get('tokenlock')) {
        FOGCore::getClass('HostManager')->update(
            ['id' => FOGCore::$Host->get('id')],
            '',
            ['tokenlock' => true]
        );
        throw new Exception(FOGCore::$Host->get('token'));
    }
} catch (Exception $e) {
    echo $e->getMessage();
    exit(1);
}
