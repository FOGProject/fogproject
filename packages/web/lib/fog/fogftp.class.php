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
class FOGFTP
{
    /**
     * The default data layout
     *
     * @var array
     */
    protected $data = [
        'host' => '',
        'username' => '',
        'password' => '',
        'port' => 21,
        'timeout' => 90,
        'passive' => true,
        'mode' => FTP_BINARY
    ];
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
     * Sets the variable for us to use later.
     *
     * @param string $key   The key to set.
     * @param mixed  $value The value to set to.
     *
     * @return self
     */
    public function __set($key, $value)
    {
        return $this->data[$key] = $value;
    }
    /**
     * Gets the variable for us to use later.
     *
     * @param string $key The key to get.
     *
     * @return self
     */
    public function __get($key)
    {
        return $this->data[$key];
    }
    /**
     * Magic class to do ftp functions.
     *
     * @param string $func The ftp_function name to be called.
     * @param array  $args The arguments to pass in.
     *
     * @return mixed
     */
    public function __call($func, $args)
    {
        array_unshift(
            $args,
            $this->_link
        );
        $func = 'ftp_' . $func;
        return $func(...$args);
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
                print_r($this->data, 1),
                PASSWORD_BCRYPT,
                ['cost'=>11]
            );
            if ($this->_link
                && $this->_currentConnectionHash == $this->_lastConnectionHash
            ) {
                return $this;
            }
            if (!$host) {
                $host = $this->host;
            }
            list(
                $portOverride,
                $timeoutOverride
            ) = FOGCore::getSetting(['FOG_FTP_PORT','FOG_FTP_TIMEOUT']);
            if (!$port) {
                if ($portOverride) {
                    $port = $portOverride;
                } else {
                    $port = $this->port;
                }
            }
            if (!$timeout) {
                if ($timeoutOverride) {
                    $timeout = $timeoutOverride;
                } else {
                    $timeout = $this->timeout;
                }
            }
            $this->_link = $connectmethod($host, $port, $timeout);
            if ($this->_link === false) {
                trigger_error(_('FTP Connection Failed'), E_USER_NOTICE);
                $this->ftperror($this->data);
            }
            if ($autologin) {
                $this->login();
                ftp_pasv($this->_link, $this->passive);
            }
            $this->_lastConnectionHash = $this->_currentConnectionHash;
        } catch (Exception $e) {
            FOGCore::error($e->getMessage());
            return false;
        }
        return $this;
    }
    /**
     * Deletes the item passed
     * This is the method called for the delete.
     * It is supposed to be recursive in design and should work
     * and I know what's wrong, cause I'm dumb.
     *
     * @param string $path the item to delete
     *
     * @return object
     */
    public function delete($path)
    {
        # here. But there's no method for the ftp_delete caller
        if (!$this->exists($path)) {
            return $this;
        }
        if (!$this->rmdir($path)
            && !@ftp_delete($this->_link, $path)
        ) {
            $filelist = $this->nlist($path);
            foreach ((array)$filelist as &$file) {
                $this->delete($file);
                unset($file);
            }
            $this->rmdir($path);
            $rawfilelist = $this->rawlist("-a $path");
            $path = trim(trim($path, '/'));
            foreach ((array)$rawfilelist as &$file) {
                $chunk = preg_split("/\s+/", $file);
                if (in_array($chunk[8], ['.', '..'])) {
                    continue;
                }
                $tmpfile = '/'
                    . $path
                    . '/'
                    . $chunk[8];
                $this->delete($tmpfile);
                unset($file);
            }
            $this->delete($path);
        }
        return $this;
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
                is_object($this->_link) ? spl_object_id($this->_link) : spl_object_id($this),
                PASSWORD_BCRYPT,
                ['cost'=>11]
            );
            if ($this->_currentLoginHash == $this->_lastLoginHash) {
                return $this;
            }
            if (!$username) {
                $username = $this->username;
            }
            if (!$password) {
                $password = $this->password;
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
     * List files recursive
     *
     * @param string $path The path to list recursive.
     *
     * @return array;
     */
    public function listrecursive($path)
    {
        $lines = ftp_rawlist($this->_link, $path);
        $rawlist = join("\n", $lines);
        preg_match_all(
            '/^([drwx+\-]{10})\s+(\d+)\s+(\w+)\s+(\w+)\s+(\d+)\s+(.{12}) (.*)$/m',
            $rawlist,
            $matches,
            PREG_SET_ORDER
        );
        $result = [];
        foreach ((array)$matches as $index => &$line) {
            array_shift($line);
            $name = $line[count($line ?: []) - 1];
            $type = $line[0][0];
            $filepath = $path.'/'.$name;
            if ($type == 'd') {
                if (in_array($name, ['.', '..'])) {
                    continue;
                }
                $result = FOGCore::fastmerge(
                    $result,
                    $this->listrecursive($filepath)
                );
            } else {
                $result[] = $filepath;
            }
        }
        return $result;
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
            || ftp_put($this->_link, $newname, $oldname, $this->data['mode']))
        ) {
            $this->ftperror($this->data);
        }
        return $this;
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
        $filelist = ftp_rawlist($this->_link, $remote_file);
        if (!$filelist) {
            $filelist = ftp_rawlist($this->_link, dirname($remote_file));
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
    public function sslConnect(
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
     * Tests if item exits
     *
     * @param string $path the path to test
     *
     * @return bool
     */
    public function exists($path)
    {
        $tmppath = dirname($path);
        $rawlisting = ftp_rawlist($this->_link, "-a $tmppath");
        $dirlisting = [];
        foreach ((array)$rawlisting as &$file) {
            $chunk = preg_split('/\s+/', $file);
            if (in_array($chunk[8], ['.', '..'])) {
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
