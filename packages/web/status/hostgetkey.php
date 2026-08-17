<?php
/**
 * Hostgetkey returns the host token for hostinfo getting
 *
 * PHP version 7.4+
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
    /**
     * Aisle 016: this endpoint is unauthenticated and MAC-resolved, and the
     * token it returns is the only gate on service/hostinfo.php's plaintext AD
     * credentials and product key. A task merely being QUEUED is enough to reach
     * here, so a scheduled mass deployment leaves every target harvestable for
     * the whole scheduling window. Bind issuance to network position where the
     * admin has been able to declare one.
     *
     * FOG_HOSTKEY_ALLOWED_SOURCES defaults to empty, which means no restriction
     * and therefore no behaviour change on upgrade. Reuse the existing
     * 'Invalid Tasking' string rather than inventing a new one, so a refused
     * caller cannot distinguish "not allowed from here" from "no task" and FOS
     * sees a message it already handles.
     */
    if (!FOGCore::hostKeySourceAllowed(filter_input(INPUT_SERVER, 'REMOTE_ADDR'))) {
        throw new Exception(_('Invalid Tasking'));
    }
    if (FOGCore::$Host->get('token') && FOGCore::$Host->get('tokenlock')) {
        throw new Exception(_('Host token is currently in use'));
    }
    if (!FOGCore::$Host->get('token')) {
        $newToken = FOGCore::createSecToken();
        FOGCore::getClass('HostManager')->update(
            ['id' => FOGCore::$Host->get('id')],
            '',
            [
                'token' => $newToken,
                'tokenlock' => true
            ]
        );
        throw new Exception($newToken);
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
