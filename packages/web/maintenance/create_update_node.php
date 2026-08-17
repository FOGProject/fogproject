<?php
/**
 * Creates or updates nodes.
 *
 * PHP version 7.4+
 *
 * @category Create_Update_Node
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Creates or updates nodes.
 *
 * PHP version 7.4+
 *
 * @category Create_Update_Node
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';

// Restrict to same-machine requests only — installer always calls via the
// server's own IP address, never from a remote host.
$_remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
$_serverIp = $_SERVER['SERVER_ADDR'] ?? '';
if ($_remoteIp !== '127.0.0.1'
    && $_remoteIp !== '::1'
    && $_remoteIp !== $_serverIp
) {
    http_response_code(403);
    exit;
}
unset($_remoteIp, $_serverIp);

// Require the fogverified sentinel before doing any work.
if (!isset($_POST['fogverified'])) {
    exit;
}

foreach ((array)$_POST as $key => &$val) {
    if (!isset($val)) {
        continue;
    }
    $stripped[$key] = trim(
        base64_decode(filter_input(INPUT_POST, $key))
    );
    unset($val);
}
/**
 * Decides what a self-registering node is called in Storage Management.
 *
 * The posted name used to be discarded outright -- this file did
 * `$name = $ip = $stripped['ip']`, so the installer's `name=` field had no
 * effect and every node was called after its own address. That is the reason a
 * storage node could not be issued a certificate: nodecert.php builds the SAN
 * from this record, and a Name that is an IP literal produces "DNS:10.0.0.5",
 * which matches no DNS subtree in the Web CA's name constraints.
 *
 * The address stays the fallback, so a node that offers nothing usable ends up
 * exactly where it used to be rather than unregistered.
 *
 * ngmMemberName is UNIQUE (schema step 1551) and FOGController::save() inserts
 * with ON DUPLICATE KEY UPDATE, so accepting a name another node already holds
 * would not fail -- it would quietly rewrite that node's row with this one's
 * address and paths. Hostnames are not unique across a fleet the way addresses
 * are (two default RHEL installs are both `localhost.localdomain`), so the name
 * is checked for a collision before it is taken, not assumed to be free.
 *
 * @param string $posted Name the node asked to be registered under.
 * @param string $ip     The node's address, used when the name is unusable.
 *
 * @return string
 */
function nodeRegistrationName($posted, $ip)
{
    $posted = trim((string) $posted);
    if ($posted === ''
        || filter_var($posted, FILTER_VALIDATE_IP)
        || !filter_var($posted, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
        || preg_match('/^localhost(\..*)?$/i', $posted)
    ) {
        return $ip;
    }
    if (FOGCore::getClass('StorageNodeManager')->exists($posted, 0, 'name')) {
        return $ip;
    }
    return $posted;
}

$ip = $stripped['ip'] ?? '';
$user = $stripped['user'] ?? '';
$pass = $stripped['pass'] ?? '';
if (isset($_POST['newNode'])) {
    $name = nodeRegistrationName($stripped['name'] ?? '', $ip);
    $path       = $stripped['path'] ?? '';
    $ftppath    = $stripped['ftppath'] ?? '';
    $sslpath    = $stripped['sslpath'] ?? '';
    $snapinpath = $stripped['snapinpath'] ?? '';
    $maxClients = $stripped['maxClients'] ?? 10;
    $interface  = $stripped['interface'] ?? '';
    $bandwidth  = $stripped['bandwidth'] ?? 1;
    $webroot    = $stripped['webroot'] ?? '/';
    $exists = FOGCore::getClass('StorageNodeManager')
        ->exists($ip, '', 'ip');
    if ($exists) {
        return;
    }
    FOGCore::getClass('StorageNode')
        ->set('name', $name)
        ->set('path', $path)
        ->set('ftppath', $ftppath)
        ->set('snapinpath', $snapinpath)
        ->set('sslpath', $sslpath)
        ->set('ip', $ip)
        ->set('maxClients', $maxClients)
        ->set('user', $user)
        ->set('pass', $pass)
        ->set('interface', $interface)
        ->set('bandwidth', $bandwidth)
        ->set('webroot', $webroot)
        ->set('isEnabled', '1')
        ->save();
} elseif (isset($_POST['nodePass'])) {
    // $ip, $user, $pass are already base64_decoded via the $stripped loop above.
    // Do NOT call base64_decode() again here.
    $StorageNodes = Route::getList(
        'storagenode',
        ['ip' => $ip]
    );
    foreach ($StorageNodes as &$StorageNode) {
        if ($StorageNode->user === trim($user)
            && $StorageNode->pass === trim($pass)
        ) {
            continue;
        }
        FOGCore::getClass('StorageNode', $StorageNode->id)
            ->set('user', $user)
            ->set('pass', $pass)
            ->save();
        unset($StorageNode);
    }
}
