<?php
/**
 * Backs up the db for us
 *
 * PHP version 7.4+
 *
 * @category Backup_DB
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

use FOG\Base\FOGCore;
use FOG\Db\Mysqldump;

/**
 * Backs up the db for us
 *
 * @category Backup_DB
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';

// Restrict to same-machine requests only. The installer calls this via the
// server's own IP, so loopback and SERVER_ADDR are both permitted.
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

$backup_name = 'fog_backup_'
    . FOGCore::formatTime('now', 'Ymd_His');
// Not a fixed name under the system temp dir: this dumps every credential
// in the deployment, and a guessable path is world-readable under the
// default umask, collides with a concurrent run, and is a symlink target
// -- fopen() follows one. Same fix as Schema::exportdb() (GH-1410).
$tmpfile = tempnam(sys_get_temp_dir(), 'fog_backup_');
if (false === $tmpfile) {
    throw new \Exception(_('Could not create tmp file.'));
}
chmod($tmpfile, 0600);
$data = '';
(new Mysqldump())->start($tmpfile);
if (!file_exists($tmpfile) || !is_readable($tmpfile)) {
    throw new \Exception(_('Could not read file from tmp folder.'));
}
$fh = fopen($tmpfile, 'rb');
while (!feof($fh)) {
    $data .= fread($fh, 4096);
}
fclose($fh);
unlink($tmpfile);
echo json_encode(
    [
        'title' => _('Export Success'),
        'msg' => _('Export Complete'),
        '_filename' => $backup_name,
        '_content' => $data
    ]
);
unset($data);
