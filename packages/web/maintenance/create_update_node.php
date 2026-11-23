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

use FOG\Base\FOGCore;
use FOG\Items\StorageNode;
use FOG\Managers\StorageNodeManager;
use FOG\Router\Route;

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
        base64_decode((string)filter_input(INPUT_POST, $key))
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
    if ((new StorageNodeManager())->exists($posted, 0, 'name')) {
        return $ip;
    }
    return $posted;
}

/**
 * Moves this server's own storage node record to the address it now has.
 *
 * Forum 18327. A server whose address changed kept its old one in Storage
 * Management, because nothing here could change ngmHostname: newNode only
 * inserts a node at an address nobody holds yet, and nodePass matches by the
 * address. The installer now notices the change (checkServerAddress in
 * lib/common/functions.sh) and posts the old and the new address here.
 *
 * Only the row at the old address changes, and only its address and NIC. Its
 * ID, group, master flag, credentials and paths stay, so image and snapin
 * assignments keep pointing at it. A new address another node already holds
 * is refused: ngmHostname is how every node lookup in this file identifies a
 * node, so two rows at one address would make each later install update both.
 *
 * Same trust as nodePass: anything that can reach this file can already
 * rewrite this node's credentials.
 *
 * @param string $oldip     The address the node is registered under.
 * @param string $ip        The address it has now.
 * @param string $interface The NIC that now holds $ip, or '' to keep it.
 *
 * @return array{0: int, 1: string} HTTP status, and a plain-text reason
 *                                  on a refusal ('' on success).
 */
function moveNodeAddress($oldip, $ip, $interface)
{
    if (!filter_var($oldip, FILTER_VALIDATE_IP)
        || !filter_var($ip, FILTER_VALIDATE_IP)
    ) {
        return [400, 'refused: both addresses must be IP addresses'];
    }
    // A Linux interface name: at most 15 characters, none of them a shell or
    // SQL metacharacter. The consumers escape it; this keeps the stored
    // value an interface name.
    if ($interface !== ''
        && !preg_match('/^[A-Za-z0-9_.:@-]{1,15}$/', $interface)
    ) {
        return [400, 'refused: the interface is not an interface name'];
    }
    if ($oldip !== $ip
        && (new StorageNodeManager())->exists($ip, 0, 'ip')
    ) {
        return [
            409,
            "refused: another storage node is already registered at $ip",
        ];
    }
    foreach (Route::getList('storagenode', ['ip' => $oldip]) as $StorageNode) {
        $node = (new StorageNode($StorageNode->id))->set('ip', $ip);
        if ($interface !== '') {
            $node->set('interface', $interface);
        }
        $node->save();
    }
    return [200, ''];
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
    $exists = (new StorageNodeManager())
        ->exists($ip, '', 'ip');
    if ($exists) {
        return;
    }
    (new StorageNode())
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
        (new StorageNode($StorageNode->id))
            ->set('user', $user)
            ->set('pass', $pass)
            ->save();
        unset($StorageNode);
    }
} elseif (isset($_POST['nodeIp'])) {
    // Silent on success, like the two branches above: the installer reads
    // any '<' in the body as something else answering in this file's place.
    [$status, $reason] = moveNodeAddress(
        $stripped['oldip'] ?? '',
        $ip,
        $stripped['interface'] ?? ''
    );
    http_response_code($status);
    echo $reason;
}
