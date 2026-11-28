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

/**
 * Hostgetkey returns the host token for hostinfo getting
 *
 * @category Hostgetkey
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/*
 * A machine entry point: FOS asks for its host's key here, with no
 * credential. Without the declaration the host's task is scoped to a user
 * that does not exist, and the lookup finds nothing. See
 * Authorization::_hasNoPrincipal().
 */
define('FOG_MACHINE_REQUEST', true);

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
    /*
     * A token is single use and short lived. Mint a new one every time and
     * store only its sha256, so a database read yields nothing usable. A lock
     * HOST_TOKEN_TTL seconds old or older is abandoned (the host died before
     * it used the token) and is taken over.
     *
     * Raw SQL, not HostManager::update(): the manager cannot express the
     * OR-with-< condition, and it cannot report affected rows. One request
     * changes the row and gets the token. A second request at the same time
     * changes nothing and is refused.
     */
    global $DB;
    $newToken = FOGCore::createSecToken();
    $now = time();
    $DB->query(
        'UPDATE `hosts` SET `hostInfoKey`=:hash,`hostInfoLock`=1,'
        . '`hostInfoLockTime`=:now '
        . 'WHERE `hostID`=:id AND (`hostInfoLock`=0 '
        . 'OR `hostInfoLockTime`<=:expired)',
        [],
        [
            ':hash' => hash('sha256', $newToken),
            ':now' => $now,
            ':id' => FOGCore::$Host->get('id'),
            ':expired' => $now - FOGCore::HOST_TOKEN_TTL
        ]
    );
    if ($DB->affectedRows() !== 1) {
        throw new \Exception(_('Host token is currently in use'));
    }
    // Echo the token just minted: only its hash is stored.
    throw new \Exception($newToken);
} catch (\Exception $e) {
    echo $e->getMessage();
    exit(1);
}
