<?php
/**
 * Backs up the db for us
 *
 * PHP version 5
 *
 * @category Backup_DB
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
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
$backup_name = 'fog_backup_'
    . FOGCore::formatTime('', 'Ymd_His');
$tmpfile = '/tmp/' . $backup_name;
$data = '';
FOGCore::getClass('Mysqldump')->start($tmpfile);
if (!file_exists($tmpfile) || !is_readable($tmpfile)) {
    throw new Exception(_('Could not read file from tmp folder.'));
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
