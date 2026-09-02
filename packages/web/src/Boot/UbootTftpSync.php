<?php
/**
 * Materializes UbootBootMenu's output as TFTP-served files
 *
 * PHP version 7.4+
 *
 * @category UbootTftpSync
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Boot;

use FOG\Base\FOGBase;
use FOG\Items\Host;
use FOG\Items\MACAddress;
use FOG\Router\Route;

/**
 * Materializes UbootBootMenu's output as TFTP-served files
 *
 * service/uboot/boot.php answers a board's HTTP GET the moment it asks --
 * computed live, nothing stored. A board whose U-Boot has no `wget` (no
 * CONFIG_CMD_WGET) cannot ask that question at all; the only thing it can do
 * is TFTP, and U-Boot's `pxe get` speaks a fixed, un-configurable convention
 * for that: fetch pxelinux.cfg/01-<mac, dash-separated, lowercase> from
 * whatever TFTP root it booted from. That is a real file that has to exist
 * before the board looks for it, which is the one thing FOG's live-computed
 * design was built to avoid -- see forums topic 18229 for the board that
 * made this necessary and UbootBootMenu's class doc for the design this
 * sits beside rather than inside.
 *
 * The write target is pxelinux.cfg under FOG_TFTP_ROOT_DIR -- the directory
 * tftpd actually serves (its chroot), NOT FOG_TFTP_PXE_KERNEL_DIR, which is
 * the HTTP-served kernel directory and invisible to a chrooted tftpd -- over
 * SSH/SFTP (FOG_TFTP_HOST + FOG_TFTP_FTP_USERNAME/PASSWORD), the same
 * mechanism FOGPage.php's kernel-upload action already uses for the same
 * reason: the TFTP root can be a remote storage node, not the web server.
 *
 * Two ways this gets called, both intentionally cheap for the common case
 * where nothing here is in use at all (no hosts with MACs, or a fleet with
 * no wget-less boards): a direct call at the moment a task is queued,
 * completed, or canceled (materialize()/remove(), and the *Many() batch
 * forms for a group action queuing or canceling many hosts at once, so
 * that queuing a 100-host group task opens one SSH session rather than
 * 100), and reconcile() -- run periodically by
 * Service/TaskScheduler.php -- which re-derives "who should have a file"
 * from the database the same way the boot menus do and corrects anything
 * the direct calls missed: a crash between a task save and the write, a
 * host deleted mid-task, a call site this file does not yet reach. The
 * direct calls are the latency fix; reconcile() is what makes staleness
 * bounded rather than permanent.
 *
 * Every public entry point catches its own failures and reports them
 * through self::logFault() rather than throwing. An unreachable TFTP host
 * must never break queuing, canceling, or completing a task -- those
 * matter far more than this file being current for the one fleet that
 * needs it, and reconcile() will catch up once the TFTP host is reachable
 * again.
 *
 * @category UbootTftpSync
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class UbootTftpSync extends FOGBase
{
    /**
     * Filename this convention expects for a MAC, minus the directory.
     *
     * @var string
     */
    const NAME_PATTERN = '/^01(-[0-9a-f]{2}){6}$/';

    /**
     * Connects to the TFTP host and makes sure pxelinux.cfg/ exists there.
     *
     * @return string the remote pxelinux.cfg directory, no trailing slash
     *
     * @throws \Exception when the SSH connection fails
     */
    private static function _connect()
    {
        list($tftpHost, $tftpUser, $tftpPass, $tftpRoot) = self::getSetting(
            [
                'FOG_TFTP_HOST',
                'FOG_TFTP_FTP_USERNAME',
                'FOG_TFTP_FTP_PASSWORD',
                'FOG_TFTP_ROOT_DIR',
            ]
        );
        self::$FOGSSH->username = $tftpUser;
        self::$FOGSSH->password = $tftpPass;
        self::$FOGSSH->host = $tftpHost;
        if (!self::$FOGSSH->connect()) {
            throw new \Exception(
                _('Unable to connect to TFTP server over SSH')
            );
        }
        $dir = '/' . trim((string)$tftpRoot, '/') . '/pxelinux.cfg';
        if (!self::$FOGSSH->exists($dir)) {
            self::$FOGSSH->sftp_mkdir($dir);
        }

        return $dir;
    }

    /**
     * Every pxelinux.cfg filename a host's registered MACs resolve to.
     *
     * More than one when a host has more than one NIC: whichever one it
     * boots from has to find a file, and they are all the same host with
     * the same task, so they all get the same content.
     *
     * @param Host $Host the host to name files for
     *
     * @return string[]
     */
    private static function _pxeFileNames(Host $Host)
    {
        $names = [];
        foreach ((array)$Host->getMyMacs() as $rawMac) {
            $mac = new MACAddress($rawMac);
            if (!$mac->isValid()) {
                continue;
            }
            $names[] = '01-' . strtolower(str_replace(':', '-', (string)$mac));
        }

        return array_values(array_unique($names));
    }

    /**
     * Writes one file's worth of content to the TFTP host.
     *
     * FOGSSH::put() reads from a local path, not a string, so this is a
     * temp-file-and-upload every time -- there is no smaller primitive for
     * "send these bytes" over the sftp wrapper the rest of FOG already
     * uses for this same TFTP host.
     *
     * chmod's 0644 afterward for the same reason FOGPage.php's kernel-upload
     * path already does: an SFTP PUT's resulting permissions follow the
     * server's own umask, not FOG's, and a TFTP daemon reading as a
     * different user than the SFTP login has no way to negotiate that --
     * it either has read access or it does not. Parity with the existing
     * upload path, not a confirmed fix for any specific report.
     *
     * @param string $remotePath the full remote path to write
     * @param string $content    the bytes to write there
     *
     * @return void
     */
    private static function _write($remotePath, $content)
    {
        $tmp = tempnam(sys_get_temp_dir(), 'uboot-tftp-');
        file_put_contents($tmp, $content);
        try {
            self::$FOGSSH->put($tmp, $remotePath);
            self::$FOGSSH->sftp_chmod($remotePath, 0644);
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Renders and writes (or removes) one host's pxelinux.cfg files.
     *
     * @param string $dir  the remote pxelinux.cfg directory
     * @param Host   $Host the host to sync
     *
     * @return void
     */
    private static function _syncOne($dir, Host $Host)
    {
        $names = self::_pxeFileNames($Host);
        if (!$names) {
            return;
        }
        if ($Host->isValid() && $Host->get('task')->isValid()) {
            $content = UbootBootMenu::renderForHost($Host);
            foreach ($names as $name) {
                self::_write($dir . '/' . $name, $content);
            }
        } else {
            foreach ($names as $name) {
                $path = $dir . '/' . $name;
                if (self::$FOGSSH->exists($path)) {
                    self::$FOGSSH->unlinkFile($path);
                }
            }
        }
    }

    /**
     * Materializes the current tasking for one host.
     *
     * @param Host $Host the host that was just tasked
     *
     * @return void
     */
    public static function materialize(Host $Host)
    {
        self::materializeMany([$Host->get('id')]);
    }

    /**
     * Removes one host's tasking files -- its task completed, failed, or
     * was canceled.
     *
     * @param Host $Host the host whose task just ended
     *
     * @return void
     */
    public static function remove(Host $Host)
    {
        self::removeMany([$Host->get('id')]);
    }

    /**
     * Materializes many hosts in one SSH session.
     *
     * The batched form a group task queue uses: queuing 100 hosts at once
     * opens one SSH connection here, not 100 inside the request that
     * queued them.
     *
     * @param int[] $hostIDs the hosts that were just tasked
     *
     * @return void
     */
    public static function materializeMany(array $hostIDs)
    {
        $hostIDs = array_values(array_unique(array_filter($hostIDs)));
        if (!$hostIDs) {
            return;
        }
        try {
            $dir = self::_connect();
            foreach ($hostIDs as $hostID) {
                self::_syncOne($dir, new Host($hostID));
            }
            self::$FOGSSH->disconnect();
        } catch (\Throwable $e) {
            self::logFault('UbootTftpSync::materializeMany: ' . $e->getMessage());
        }
    }

    /**
     * Removes many hosts' tasking files in one SSH session.
     *
     * @param int[] $hostIDs the hosts whose tasks just ended
     *
     * @return void
     */
    public static function removeMany(array $hostIDs)
    {
        $hostIDs = array_values(array_unique(array_filter($hostIDs)));
        if (!$hostIDs) {
            return;
        }
        try {
            $dir = self::_connect();
            foreach ($hostIDs as $hostID) {
                $Host = new Host($hostID);
                foreach (self::_pxeFileNames($Host) as $name) {
                    $path = $dir . '/' . $name;
                    if (self::$FOGSSH->exists($path)) {
                        self::$FOGSSH->unlinkFile($path);
                    }
                }
            }
            self::$FOGSSH->disconnect();
        } catch (\Throwable $e) {
            self::logFault('UbootTftpSync::removeMany: ' . $e->getMessage());
        }
    }

    /**
     * Re-derives every pxelinux.cfg file from the database and corrects
     * whatever is wrong: writes what is missing, deletes what should not
     * be there. Run periodically by Service/TaskScheduler.php.
     *
     * This is the safety net under materialize()/remove(): anything they
     * missed (a crash between a task save and the write, a call site this
     * file does not reach, a host deleted mid-task) is wrong for at most
     * one reconcile cycle, not forever.
     *
     * @return void
     */
    public static function reconcile()
    {
        try {
            $dir = self::_connect();
            $activeHostIDs = array_values(
                array_unique(
                    Route::getIds(
                        'task',
                        [
                            'stateID' => self::fastmerge(
                                self::getQueuedStates(),
                                (array)self::getProgressState()
                            ),
                        ],
                        'hostID'
                    )
                )
            );
            $keep = [];
            foreach ($activeHostIDs as $hostID) {
                $Host = new Host($hostID);
                $names = self::_pxeFileNames($Host);
                if (!$names) {
                    continue;
                }
                $content = UbootBootMenu::renderForHost($Host);
                foreach ($names as $name) {
                    $keep[$name] = true;
                    self::_write($dir . '/' . $name, $content);
                }
            }
            foreach (self::$FOGSSH->scanFilesystem($dir) as $path) {
                $name = basename($path);
                // Only ever touch files this class's own naming convention
                // produced -- anything else in pxelinux.cfg/ is not ours to
                // judge, let alone delete.
                if (!preg_match(self::NAME_PATTERN, $name)) {
                    continue;
                }
                if (!isset($keep[$name])) {
                    self::$FOGSSH->unlinkFile($path);
                }
            }
            self::$FOGSSH->disconnect();
        } catch (\Throwable $e) {
            self::logFault('UbootTftpSync::reconcile: ' . $e->getMessage());
        }
    }
}
