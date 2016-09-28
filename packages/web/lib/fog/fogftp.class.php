<?php
/**
 * Handles FTP connections and operations for FOG
 *
 * PHP version 5
 *
 * @category FOGFTP
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Handles FTP connections and operations for FOG
 *
 * @category FOGFTP
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class FOGFTP extends FOGGetSet
{
    /**
     * The default data layout
     *
     * @var array
     */
    protected $data = array(
        'host' => '',
        'username' => '',
        'password' => '',
        'port' => 21,
        'timeout' => 90,
        'passive' => true,
        'mode' => FTP_BINARY,
    );
    /**
     * The link to the FTP server
     *
     * @var resource
     */
    private static $_link;
    /**
     * The connection hash
     *
     * @var string
     */
    private static $_lastConnectionHash;
    /**
     * The last login hash
     *
     * @var string
     */
    private static $_lastLoginHash;
    /**
     * The current connection hash
     *
     * @var string
     */
    private static $_currentConnectionHash;
    /**
     * The current login hash
     *
     * @var string
     */
    private static $_currentLoginHash;
    /**
     * Destroy the ftp object
     *
     * @return void
     */
    public function __destruct()
    {
        $this->close();
    }
    /**
     * FTP Alloc as a method
     *
     * @param double|int $filesize the filesize to allocate
     * @param mixed      $result   the result to allocate for
     *
     * @return ftp_alloc
     */
    public function alloc(
        $filesize,
        &$result
    ) {
        return ftp_alloc(
            self::$_link,
            $filesize,
            $result
        );
    }
    /**
     * Change to parent directory
     *
     * @return ftp_cdup
     */
    public function cdup()
    {
        return ftp_cdup(self::$_link);
    }
    /**
     * Change directory as requested
     *
     * @param string $directory the directory to change to
     *
     * @return ftp_chdir
     */
    public function chdir($directory)
    {
        return ftp_chdir(
            self::$_link,
            $directory
        );
    }
    /**
     * Change permissions on directory
     *
     * @param string $mode     the permissions/mode to set on file
     * @param string $filename the file to change permissions on
     *
     * @return object
     */
    public function chmod(
        $mode,
        $filename
    ) {
        if (!$mode) {
            $mode = $this->get('mode');
        }
        ftp_chmod(
            self::$_link,
            $mode,
            $filename
        );
        return $this;
    }
    /**
     * Close the current ftp session
     *
     * @return object
     */
    public function close()
    {
        if (self::$_link) {
            ftp_close(self::$_link);
        }
        self::$_link = null;
        return $this;
    }
    /**
     * Connect to the ftp server
     *
     * @param string $host          the host to connect to
     * @param int    $port          the port to use
     * @param int    $timeout       the timeout setting of the connection
     * @param bool   $autologin     should we auto login
     * @param string $connectmethod how to connect to the ftp server
     */
    public function connect(
        $host = '',
        $port = 0,
        $timeout = 90,
        $autologin = true,
        $connectmethod = 'ftp_connect'
    ) {
        try {
            self::$_currentConnectionHash = password_hash(
                serialize($this->data),
                PASSWORD_BCRYPT,
                ['cost'=>11]
            );
            if (self::$_link
                && self::$_currentConnectionHash == self::$_lastConnectionHash
            ) {
                return $this;
            }
            if (!$host) {
                $host = $this->get('host');
            }
            list(
                $portOverride,
                $timeoutOverride
            ) = self::getSubObjectIDs(
                'Service',
                array(
                    'name' => array(
                        'FOG_FTP_PORT',
                        'FOG_FTP_TIMEOUT'
                    )
                ),
                'value',
                false,
                'AND',
                'name',
                false,
                ''
            );
            if (!$port) {
                if ($portOverride) {
                    $port = $portOverride;
                } else {
                    $port = $this->get('port');
                }
            }
            if (!$timeout) {
                if ($timeoutOverride) {
                    $timeout = $timeoutOverride;
                } else {
                    $timeout = $this->get('timeout');
                }
            }
            self::$_link = $connectmethod($host, $port, $timeout);
            if (self::$_link === false) {
                self::ftperror($this->data);
            }
            if ($autologin) {
                $this->login();
                $this->pasv($this->get('passive'));
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        self::$_lastConnectionHash = self::$_currentConnectionHash;
        return $this;
    }
    /**
     * Deletes the item passed
     *
     * @param string $path the item to delete
     *
     * @return object
     */
    public function delete($path)
    {
        if (!$this->exists($path)) {
            return $this;
        }
        if (!ftp_delete(self::$_link, $path)
            && !$this->rmdir($path)
        ) {
            $filelist = $this->nlist($path);
            foreach ((array)$filelist as &$file) {
                $this->delete($file);
                unset($file);
            }
            $rawfilelist = $this->rawlist("-a $path");
            $path = trim($path, '/');
            $path = trim($path);
            foreach ((array)$rawfilelist as &$file) {
                $chunk = preg_split("/\s+/", $file);
                if ($chunk[8] === '.' || $chunk[8] === '..') {
                    return;
                }
                $tmpfile = sprintf(
                    '/%s/%s',
                    $path,
                    $chunk[8]
                );
                $this->delete($tmpfile);
                unset($file);
            }
        }
        $this->delete($path);
        return $this;
    }
    /**
     * Execute command via ftp
     *
     * @param string $command the command to execute
     *
     * @return bool
     */
    public function exec($command)
    {
        return ftp_exec(
            self::$_link,
            escapeshellcmd($command)
        );
    }
    /**
     * Get specified portions of a file
     *
     * @param resource $handle      the ftp resource
     * @param string   $remote_file the remote file
     * @param int      $mode        mode of the connection
     * @param int      $resumepos   the position to resume from
     *
     * @return ftp_fget
     */
    public function fget(
        $handle,
        $remote_file,
        $mode = 0,
        $resumepos = 0
    ) {
        if (!$mode) {
            $mode = $this->get('mode');
        }
        if ($resumepos) {
            return ftp_fget(
                self::$_link,
                $handle,
                $remote_file,
                $mode,
                $resumepos
            );
        }
        return ftp_fget(
            self::$_link,
            $handle,
            $remote_file,
            $mode
        );
    }
    /**
     * Get specified portions of a file
     *
     * @param string   $remote_file the remote file
     * @param resource $handle      the ftp resource
     * @param int      $mode        mode of the connection
     * @param int      $startpos    the position to start at
     *
     * @return ftp_fget
     */
    public function fput(
        $remote_file,
        $handle,
        $mode = 0,
        $startpos = 0
    ) {
        if (!$mode) {
            $mode = $this->get('mode');
        }
        if ($startpos) {
            return ftp_fput(
                self::$_link,
                $remote_file,
                $handle,
                $mode,
                $startpos
            );
        }
        return ftp_fput(
            self::$_link,
            $remote_file,
            $handle,
            $mode
        );
    }
    /**
     * Returns the ftp error
     *
     * @param mixed $data the data info
     *
     * @throws Exception
     * @return void
     */
    public static function ftperror($data)
    {
        $error = error_get_last();
        throw new Exception(
            sprintf(
                '%s: %s, %s: %s, %s: %s, %s: %s, %s: %s, %s: %s',
                _('Type'),
                $error['type'],
                _('File'),
                $error['file'],
                _('Line'),
                $error['line'],
                _('Message'),
                $error['message'],
                _('Host'),
                $data['host'],
                _('Username'),
                $data['username']
            )
        );
    }
    /**
     * Get ftp options
     *
     * @param mixed $option the option to get
     *
     * @return ftp_get_option
     */
    public function getOption($option)
    {
        return ftp_get_option(
            self::$_link,
            $option
        );
    }
    public function pull($local_file, $remote_file, $mode = 0, $resumepos = 0)
    {
        if (!$mode) {
            $mode = $this->get('mode');
        }
        if ($resumepos) {
            return ftp_get(self::$_link, $local_file, $remote_file, $mode, $resumepos);
        }
        return ftp_get(self::$_link, $local_file, $remote_file, $mode);
    }
    public function login($username = null, $password = null)
    {
        try {
            self::$_currentLoginHash = password_hash(serialize(self::$_link), PASSWORD_BCRYPT, ['cost'=>11]);
            if (self::$_currentLoginHash == self::$_lastLoginHash) {
                return $this;
            }
            if (!$username) {
                $username = $this->get('username');
            }
            if (!$password) {
                $password = $this->get('password');
            }
            if (ftp_login(self::$_link, $username, $password) === false) {
                self::ftperror($this->data);
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        self::$_lastLoginHash = self::$_currentLoginHash;
        return $this;
    }
    public function mdtm($remote_file)
    {
        return ftp_mdtm(self::$_link, $remote_file);
    }
    public function mkdir($directory)
    {
        return ftp_mkdir(self::$_link, $directory);
    }
    public function nb_continue()
    {
        return ftp_nb_continue(self::$_link);
    }
    public function nb_fget($handle, $remote_file, $mode = 0, $resumepos = 0)
    {
        if (!$mode) {
            $mode = $this->get('mode');
        }
        if ($resumepos) {
            return ftp_nb_fget(self::$_link, $handle, $remote_file, $mode, $resumepos);
        }
        return ftp_nb_fget(self::$_link, $handle, $remote_file, $mode);
    }
    public function nb_fput($remote_file, $handle, $mode = 0, $startpos = 0)
    {
        if (!$mode) {
            $mode = $this->get('mode');
        }
        if ($startpos) {
            return ftp_nb_fput(self::$_link, $remote_file, $handle, $mode, $startpos);
        }
        return ftp_nb_fput(self::$_link, $remote_file, $handle, $mode);
    }
    public function nb_get($local_file, $remote_file, $mode = 0, $resumepos = 0)
    {
        if (!$mode) {
            $mode = $this->get('mode');
        }
        if ($resumepos) {
            return ftp_nb_get(self::$_link, $local_file, $remote_file, $mode, $resumepos);
        }
        return ftp_nb_get(self::$_link, $local_file, $remote_file, $mode);
    }
    public function nb_put($remote_file, $local_file, $mode = 0, $startpos = 0)
    {
        if (!$mode) {
            $mode = $this->get('mode');
        }
        if ($startpos) {
            return ftp_nb_put(self::$_link, $remote_file, $local_file, $mode, $startpos);
        }
        return ftp_nb_put(self::$_link, $remote_file, $local_file, $mode);
    }
    public function nlist($directory)
    {
        return ftp_nlist(self::$_link, $directory);
    }
    public function pasv($pasv = false)
    {
        if (!$pasv) {
            $pasv = $this->get('passive');
        }
        return ftp_pasv(self::$_link, $pasv);
    }
    public function put($remote_file, $local_file, $mode = 0, $startpos = 0)
    {
        if (!$mode) {
            $mode = $this->get('mode');
        }
        if ($startpos) {
            return ftp_put(self::$_link, $remote_file, $local_file, $mode, $resumepos);
        }
        return ftp_put(self::$_link, $remote_file, $local_file, $mode, $resumepos);
    }
    public function pwd()
    {
        return ftp_pwd(self::$_link);
    }
    public function quit()
    {
        return $this->close();
    }
    public function raw($command)
    {
        return ftp_raw(self::$_link, $command);
    }
    public function rawlist($directory, $recursive = false)
    {
        return ftp_rawlist(self::$_link, $directory, $recursive);
    }
    public function rename($oldname, $newname)
    {
        if (!(ftp_rename(self::$_link, $oldname, $newname) || $this->put($newname, $oldname))) {
            self::ftperror($this->data);
        }
        return $this;
    }
    public function rmdir($directory)
    {
        return ftp_rmdir(self::$_link, $directory);
    }
    public function set_option($option, $value)
    {
        return ftp_set_option(self::$_link, $option, $value);
    }
    public function site($command)
    {
        return ftp_site(self::$_link, $command);
    }
    public function size($remote_file, $rawsize = true)
    {
        if ($rawsize) {
            return $this->rawsize($remote_file);
        }
        return ftp_size(self::$_link, $remote_file);
    }
    public function rawsize($remote_file)
    {
        if (!$this->exists($remote_file)) {
            return 0;
        }
        $size = 0;
        $filelist = $this->rawlist($remote_file);
        if (!$filelist) {
            $filelist = $this->rawlist(dirname($remote_file));
            $filename = basename($remote_file);
            $filelist = preg_grep("#$filename#", $filelist);
        }
        array_map(function (&$file) use (&$size) {
            $fileinfo = preg_split('#\s+#', $file, null, PREG_SPLIT_NO_EMPTY);
            $size += $fileinfo[4];
            unset($file);
        }, (array)$filelist);
        return $size;
    }
    public function ssl_connect($host = '', $port = 0, $timeout = 90, $autologin = true)
    {
        try {
            if (!$host) {
                $host = $this->get('host');
            }
            if (!$port) {
                $port = self::getSetting('FOG_FTP_PORT') ? self::getSetting('FOG_FTP_PORT') : $this->get('port');
            }
            if (!$timeout) {
                $timeout = self::getSetting('FOG_FTP_TIMEOUT') ? self::getSetting('FOG_FTP_TIMEOUT') : $this->get('timeout');
            }
            $this->connect($host, $port, $timeout, $autologin, 'ftp_ssl_connect');
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        return $this;
    }
    public function systype()
    {
        return ftp_systype(self::$_link);
    }
    public function exists($path)
    {
        $tmppath = dirname($path);
        $rawlisting = $this->rawlist("-a $tmppath");
        $dirlisting = array_filter(array_map(function (&$file) use ($tmppath) {
            $chunk = preg_split('/\s+/', $file);
            if ($chunk[8] === '.' || $chunk[8] === '..') {
                return false;
            };
            return sprintf('%s%s%s%s', DIRECTORY_SEPARATOR, trim(trim($tmppath, '/'), '\\'), DIRECTORY_SEPARATOR, $chunk[8]);
        }, (array)$rawlisting));
        return in_array($path, $dirlisting);
    }
}
