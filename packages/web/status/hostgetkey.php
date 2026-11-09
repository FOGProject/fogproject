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

use FOG\Base\FOGCore;
use FOG\Managers\HostManager;

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
        throw new \Exception(_('Host Invalid'));
    }
    #if (FOGCore::$useragent) {
    #    throw new Exception(_('Accessed inappropriately'));
    #}
    if (!FOGCore::$Host->get('task')->isValid()) {
        throw new \Exception(_('Invalid Tasking'));
    }
    /**
     * Aisle 016: this endpoint is unauthenticated and MAC-resolved. The token
     * it returns opens service/hostinfo.php, which sent plaintext AD
     * credentials and the product key to any token holder (re-enabled behind
     * this token by aaee1b60c, after 6daab8cc4 had removed them). A task merely
     * being QUEUED is enough to reach here, so a scheduled mass deployment left
     * every target harvestable for the whole scheduling window. Bind issuance
     * to network position where the admin has been able to declare one.
     *
     * FOG_HOSTKEY_ALLOWED_SOURCES defaults to empty, which means no restriction
     * here, so a USB-booted FOS still gets its token. GHSA-666x-5fjv-v8j9:
     * while it is empty, hostinfo.php sends no join password and no product
     * key, so the token alone no longer reaches either. Reuse the existing
     * 'Invalid Tasking' string rather than inventing a new one, so a refused
     * caller cannot distinguish "not allowed from here" from "no task" and FOS
     * sees a message it already handles.
     */
    if (!FOGCore::hostKeySourceAllowed(filter_input(INPUT_SERVER, 'REMOTE_ADDR'))) {
        throw new \Exception(_('Invalid Tasking'));
    }
    if (FOGCore::$Host->get('token') && FOGCore::$Host->get('tokenlock')) {
        throw new \Exception(_('Host token is currently in use'));
    }
    if (!FOGCore::$Host->get('token')) {
        (new HostManager())->update(
            ['id' => FOGCore::$Host->get('id')],
            '',
            [
                'token' => FOGCore::createSecToken(),
                'tokenlock' => true
            ]
        );
        throw new \Exception(FOGCore::$Host->get('token'));
    }
    if (FOGCore::$Host->isValid() && !FOGCore::$Host->get('tokenlock')) {
        (new HostManager())->update(
            ['id' => FOGCore::$Host->get('id')],
            '',
            ['tokenlock' => true]
        );
        throw new \Exception(FOGCore::$Host->get('token'));
    }
} catch (\Exception $e) {
    echo $e->getMessage();
    exit(1);
}
