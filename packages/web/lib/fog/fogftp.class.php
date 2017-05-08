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
    private $_link;
    /**
     * The connection hash
     *
     * @var string
     */
    private $_lastConnectionHash;
    /**
     * The last login hash
     *
     * @var string
     */
    private $_lastLoginHash;
    /**
     * The current connection hash
     *
     * @var string
     */
    private $_currentConnectionHash;
    /**
     * The current login hash
     *
     * @var string
     */
    private $_currentLoginHash;
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
            $this->_link,
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
        return ftp_cdup($this->_link);
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
            $this->_link,
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
            $this->_link,
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
        if ($this->_link) {
            ftp_close($this->_link);
        }
        $this->_link = null;
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
     *
     * @return object
     */
    public function connect(
        $host = '',
        $port = 0,
        $timeout = 90,
        $autologin = true,
        $connectmethod = 'ftp_connect'
    ) {
        try {
            $this->_currentConnectionHash = password_hash(
                serialize($this->data),
                PASSWORD_BCRYPT,
                ['cost'=>11]
            );
            if ($this->_link
                && $this->_currentConnectionHash == $this->_lastConnectionHash
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
            $this->_link = $connectmethod($host, $port, $timeout);
            if ($this->_link === false) {
                $this->ftperror($this->data);
            }
            if ($autologin) {
                $this->login();
                $this->pasv($this->get('passive'));
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        $this->_lastConnectionHash = $this->_currentConnectionHash;
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
        if (!$this->rmdir($path)
            && !ftp_delete($this->_link, $path)
        ) {
            $filelist = $this->nlist($path);
            foreach ((array)$filelist as &$file) {
                $this->delete($file);
                unset($file);
            }
            $this->rmdir($path);
            $rawfilelist = $this->rawlist("-a $path");
            $path = trim($path, '/');
            $path = trim($path);
            foreach ((array)$rawfilelist as &$file) {
                $chunk = preg_split("/\s+/", $file);
                if (in_array($chunk[8], array('.', '..'))) {
                    continue;
                }
                $tmpfile = sprintf(
                    '/%s/%s',
                    $path,
                    $chunk[8]
                );
                $this->delete($tmpfile);
                unset($file);
            }
            $this->delete($path);
        }
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
            $this->_link,
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
                $this->_link,
                $handle,
                $remote_file,
                $mode,
                $resumepos
            );
        }
        return ftp_fget(
            $this->_link,
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
                $this->_link,
                $remote_file,
                $handle,
                $mode,
                $startpos
            );
        }
        return ftp_fput(
            $this->_link,
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
    public function ftperror($data)
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
            $this->_link,
            $option
        );
    }
    /**
     * Pulls files, quite literally ftp_get but get is
     * a common method that doesn't tie with with this get
     *
     * @param string $local_file  the local file
     * @param string $remote_file the remote file
     * @param mixed  $mode        the mode to get file
     * @param mixed  $resumepos   the position to continue from
     *
     * @return ftp_get
     */
    public function pull(
        $local_file,
        $remote_file,
        $mode = 0,
        $resumepos = 0
    ) {
        if (!$mode) {
            $mode = $this->get('mode');
        }
        if ($resumepos) {
            return ftp_get(
                $this->_link,
                $local_file,
                $remote_file,
                $mode,
                $resumepos
            );
        }
        return ftp_get(
            $this->_link,
            $local_file,
            $remote_file,
            $mode
        );
    }
    /**
     * Perform the login
     *
     * @param string $username the username to login with
     * @param string $password the password to login with
     *
     * @throws Exception
     * @return object
     */
    public function login(
        $username = null,
        $password = null
    ) {
        try {
            $this->_currentLoginHash = password_hash(
                serialize($this->_link),
                PASSWORD_BCRYPT,
                ['cost'=>11]
            );
            if ($this->_currentLoginHash == $this->_lastLoginHash) {
                return $this;
            }
            if (!$username) {
                $username = $this->get('username');
            }
            if (!$password) {
                $password = $this->get('password');
            }
            if (ftp_login($this->_link, $username, $password) === false) {
                $this->ftperror($this->data);
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        $this->_lastLoginHash = $this->_currentLoginHash;
        return $this;
    }
    /**
     * MDTM as a method
     *
     * @param string $remote_file the remote file
     *
     * @return ftp_mdtm
     */
    public function mdtm($remote_file)
    {
        return ftp_mdtm($this->_link, $remote_file);
    }
    /**
     * Creates directory on ftp site
     *
     * @param string $directory the directory to make
     *
     * @return ftp_mkdir
     */
    public function mkdir($directory)
    {
        return ftp_mkdir($this->_link, $directory);
    }
    /**
     * Continue non-blocking
     *
     * @return ftp_nb_continue
     */
    public function nb_continue()
    {
        return ftp_nb_continue($this->_link);
    }
    /**
     * Fget non-blocking
     *
     * @param resource $handle      the file handle local
     * @param string   $remote_file the file to get
     * @param mixed    $mode        the mode to fget the file
     * @param mixed    $resumepos   the position to continue from
     *
     * @return ftp_nb_fget
     */
    public function nb_fget(
        $handle,
        $remote_file,
        $mode = 0,
        $resumepos = 0
    ) {
        if (!$mode) {
            $mode = $this->get('mode');
        }
        if ($resumepos) {
            return ftp_nb_fget(
                $this->_link,
                $handle,
                $remote_file,
                $mode,
                $resumepos
            );
        }
        return ftp_nb_fget(
            $this->_link,
            $handle,
            $remote_file,
            $mode
        );
    }
    /**
     * Fput non-blocking
     *
     * @param string   $remote_file the file to get
     * @param resource $handle      the file handle local
     * @param mixed    $mode        the mode to fget the file
     * @param mixed    $startpos    the position to continue from
     *
     * @return ftp_nb_fput
     */
    public function nb_fput(
        $remote_file,
        $handle,
        $mode = 0,
        $startpos = 0
    ) {
        if (!$mode) {
            $mode = $this->get('mode');
        }
        if ($startpos) {
            return ftp_nb_fput(
                $this->_link,
                $remote_file,
                $handle,
                $mode,
                $startpos
            );
        }
        return ftp_nb_fput(
            $this->_link,
            $remote_file,
            $handle,
            $mode
        );
    }
    /**
     * Get non-blocking
     *
     * @param string $local_file  the file handle local
     * @param string $remote_file the file to get
     * @param mixed  $mode        the mode to fget the file
     * @param mixed  $resumepos   the position to continue from
     *
     * @return ftp_nb_get
     */
    public function nb_get(
        $local_file,
        $remote_file,
        $mode = 0,
        $resumepos = 0
    ) {
        if (!$mode) {
            $mode = $this->get('mode');
        }
        if ($resumepos) {
            return ftp_nb_get(
                $this->_link,
                $local_file,
                $remote_file,
                $mode,
                $resumepos
            );
        }
        return ftp_nb_get(
            $this->_link,
            $local_file,
            $remote_file,
            $mode
        );
    }
    /**
     * Put non-blocking
     *
     * @param string $remote_file the file to get
     * @param string $local_file  the file handle local
     * @param mixed  $mode        the mode to fget the file
     * @param mixed  $startpos    the position to continue from
     *
     * @return ftp_nb_put
     */
    public function nb_put(
        $remote_file,
        $local_file,
        $mode = 0,
        $startpos = 0
    ) {
        if (!$mode) {
            $mode = $this->get('mode');
        }
        if ($startpos) {
            return ftp_nb_put(
                $this->_link,
                $remote_file,
                $local_file,
                $mode,
                $startpos
            );
        }
        return ftp_nb_put(
            $this->_link,
            $remote_file,
            $local_file,
            $mode
        );
    }
    /**
     * FTP nlist
     *
     * @param string $directory the directory to list
     *
     * @return ftp_nlist
     */
    public function nlist($directory)
    {
        return ftp_nlist(
            $this->_link,
            $directory
        );
    }
    /**
     * FTP Pasv or not
     *
     * @param mixed $pasv the pasv mode
     *
     * @return ftp_pasv
     */
    public function pasv($pasv = false)
    {
        if (!$pasv) {
            $pasv = $this->get('passive');
        }
        return ftp_pasv(
            $this->_link,
            $pasv
        );
    }
    /**
     * Put
     *
     * @param string $remote_file the file to put
     * @param string $local_file  the file handle local
     * @param mixed  $mode        the mode to fget the file
     * @param mixed  $startpos    the position to continue from
     *
     * @return ftp_put
     */
    public function put(
        $remote_file,
        $local_file,
        $mode = 0,
        $startpos = 0
    ) {
        if (!$mode) {
            $mode = $this->get('mode');
        }
        if ($startpos) {
            return ftp_put(
                $this->_link,
                $remote_file,
                $local_file,
                $mode,
                $resumepos
            );
        }
        return ftp_put(
            $this->_link,
            $remote_file,
            $local_file,
            $mode,
            $resumepos
        );
    }
    /**
     * Print working directory
     *
     * @return ftp_pwd
     */
    public function pwd()
    {
        return ftp_pwd($this->_link);
    }
    /**
     * Alias to close the ftp connection
     *
     * @return close
     */
    public function quit()
    {
        return $this->close();
    }
    /**
     * Perform raw ftp command
     *
     * @param string $command the command to run
     *
     * @return ftp_raw
     */
    public function raw($command)
    {
        return ftp_raw($this->_link, $command);
    }
    /**
     * Rawlist essentially ls -la from ftp perspective
     *
     * @param string $directory the directory to list
     * @param mixed  $recursive to delve deeper
     *
     * @return ftp_rawlist
     */
    public function rawlist(
        $directory,
        $recursive = false
    ) {
        return ftp_rawlist(
            $this->_link,
            $directory,
            $recursive
        );
    }
    /**
     * Rename function
     *
     * @param string $oldname the old name
     * @param string $newname the name to change to
     *
     * @return ftp_rename
     */
    public function rename(
        $oldname,
        $newname
    ) {
        if (!(ftp_rename($this->_link, $oldname, $newname)
            || $this->put($newname, $oldname))
        ) {
            $this->ftperror($this->data);
        }
        return $this;
    }
    /**
     * Remove directory
     *
     * @param string $directory the directory to remove
     *
     * @return ftp_rmdir
     */
    public function rmdir($directory)
    {
        return ftp_rmdir($this->_link, $directory);
    }
    /**
     * Set the options for the ftp session
     *
     * @param string $option the option to set
     * @param mixed  $value  the value to set to
     *
     * @return ftp_set_option
     */
    public function set_option(
        $option,
        $value
    ) {
        return ftp_set_option($this->_link, $option, $value);
    }
    /**
     * Site to run command on
     *
     * @param string $command the command to run
     *
     * @return ftp_site
     */
    public function site($command)
    {
        return ftp_site(
            $this->_link,
            $command
        );
    }
    /**
     * Size of file
     *
     * @param string $remote_file the remote file to get size
     * @param mixed  $rawsize     to use rawlist method
     *
     * @return ftp_size
     */
    public function size(
        $remote_file,
        $rawsize = true
    ) {
        if ($rawsize) {
            return $this->rawsize($remote_file);
        }
        return ftp_size($this->_link, $remote_file);
    }
    /**
     * Size of file raw (string)
     *
     * @param string $remote_file the file to get rawsize
     *
     * @return float
     */
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
        foreach ((array)$filelist as &$file) {
            $fileinfo = preg_split(
                '#\s+#',
                $file,
                null,
                PREG_SPLIT_NO_EMPTY
            );
            $size += (float)$fileinfo[4];
        }
        return $size;
    }
    /**
     * Connect to the ftp server
     *
     * @param string $host      the host to connect to
     * @param int    $port      the port to use
     * @param int    $timeout   the timeout setting of the connection
     * @param bool   $autologin should we auto login
     *
     * @return object
     */
    public function ssl_connect(
        $host = '',
        $port = 0,
        $timeout = 90,
        $autologin = true
    ) {
        try {
            $this->connect(
                $host,
                $port,
                $timeout,
                $autologin,
                'ftp_ssl_connect'
            );
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        return $this;
    }
    /**
     * The system type
     *
     * @return ftp_systype
     */
    public function systype()
    {
        return ftp_systype($this->_link);
    }
    /**
     * Tests if item exits
     *
     * @param string $path the path to test
     *
     * @return bool
     */
    public function exists($path)
    {
        $tmppath = dirname($path);
        $rawlisting = $this->rawlist("-a $tmppath");
        $dirlisting = array();
        foreach ((array)$rawlisting as &$file) {
            $chunk = preg_split('/\s+/', $file);
            if (in_array($chunk[8], array('.', '..'))) {
                continue;
            }
            $dirlisting[] = sprintf(
                '/%s/%s',
                trim(trim($tmppath, '/'), '\\'),
                $chunk[8]
            );
        }
        return in_array($path, $dirlisting);
    }
}
