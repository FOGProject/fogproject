<?php
/**
 * Handles SSH connections and operations for FOG
 *
 * PHP version 5
 *
 * @category FOGSSH
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Handles FTP connections and operations for FOG
 *
 * @category FOGSSH
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class FOGSSH
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
        'port' => 22,
        'timeout' => 90,
    ];
    /**
     * The link to the ssh server
     *
     * @var resource
     */
    private $_link;
    /**
     * The link to the sftp instance
     *
     * @var resource
     */
    private $_sftp;
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
    public function sftp()
    {
        if (!isset($this->_sftp) && !($this->_sftp = @ssh2_sftp($this->_link))) {
            $this->ssherror($this->data);
        }
    }
    /**
     * Magic class to do ssh2 functions.
     *
     * @param string $func The ssh2_function name to be called.
     * @param array  $args The arguments to pass in.
     *
     * @return mixed
     */
    public function __call($func, $args)
    {
        if (str_contains($func, 'scp')) {
            $linker = $this->_link;
        } else if (str_contains($func, 'sftp_')) {
            if (!$this->_sftp) {
                $this->sftp();
            }
            $linker = $this->_sftp;
        } else {
            $linker = $this->_link;
        }
        if ($func != 'fetch_stream') {
            array_unshift(
                $args,
                $linker
            );
        }
        $func = 'ssh2_' . $func;
        return $func(...$args);
    }
    /**
     * Connect to the ssh server
     *
     * @param string $host          the host to connect to
     * @param int    $port          the port to use
     * @param bool   $autologin     should we auto login
     * @param string $connectmethod how to connect to the ftp server
     *
     * @return object
     */
    public function connect(
        $host = '',
        $port = 0,
        $autologin = true,
        $connectmethod = 'ssh2_connect'
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
            list($portOverride) = FOGCore::getSetting(['FOG_SSH_PORT']);
            if (!$port) {
                if ($portOverride) {
                    $port = $portOverride;
                } else {
                    $port = $this->port;
                }
            }
            $this->_link = ssh2_connect($host, $port);
            if ($this->_link === false) {
                trigger_error(_('SSH Connection Failed'), E_USER_NOTICE);
                $this->ssherror($this->data);
            }
            if ($autologin) {
                $this->login();
            }
            $this->_lastConnectionHash = $this->_currentConnectionHash;
        } catch (Exception $e) {
            FOGCore::error($e->getMessage());
            return false;
        }
        return $this;
    }
    /**
     * Returns the ssh error
     *
     * @param mixed $data the data info
     *
     * @throws Exception
     * @return void
     */
    public function ssherror($data)
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
            if ($this->auth_password($username, $password) === false) {
                $this->ssherror($this->data);
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        $this->_lastLoginHash = $this->_currentLoginHash;
        return $this;
    }
    /**
     * Checks if a file exists
     *
     * @params string $path The path/file to check if it exists
     *
     * @return bool
     */
    public function exists($path)
    {
        $this->sftp();
        $sftp_wrap = "ssh2.sftp://{$this->_sftp}{$path}";
        return @is_dir($sftp_wrap) || @file_exists($sftp_wrap);
    }
    /**
     * Sets the chmod permissions of the fil
     *
     * @params string $path The path/file to set mode
     * @params int    $mode The mode to set
     *
     * @return bool
     */
    public function sftp_chmod($path, $mode)
    {
        return @ssh2_sftp_chmod($this->_sftp, $path, intval($mode));
    }
    /**
     * Puts the files from one place to another remotely/Uploads the file
     *
     * @param string $localfile  The local file to put on the remote
     * @param string $remotefile The place/name the file is being placed.
     *
     * @throws Exception
     * @return void
     */
    public function put($localfile, $remotefile)
    {
        $sftp = $this->_sftp;
        $stream = @fopen("ssh2.sftp://$sftp$remotefile", 'w');
        if (!$stream) {
            throw new Exception(_("Could not open file"). ": $remotefile");
        }
        $data_to_send = @file_get_contents($localfile);
        if (false === $data_to_send) {
            throw new Exception(_("Could not open local file"). ": $localfile");
        }
        if (false === @fwrite($stream, $data_to_send)) {
            throw new Exception(_("Could not send data from file"). ": $localfile");
        }

        @fclose($stream);
    }
    public function scanFilesystem($remote_file) {
        $sftp = $this->_sftp;
        $dir = "ssh2.sftp://$sftp$remote_file";
        $tmpArray = [];

        if (is_dir($dir)) {
            if ($dh = opendir($dir)) {
                while (($file = readdir($dh)) !== false) {
                    if ($file == '.' || $file == '..') {
                        continue;
                    }
                    $filetype = filetype($dir . DS . $file);
                    if ($filetype == 'dir') {
                        $tmp = $this->scanFilesystem($remote_file.DS.$file.DS);
                        foreach ($tmp as $t) {
                            $tempArray[] = $file . DS . $t;
                        }
                    } else {
                        $tempArray[] = $file;
                    }
                }
                closedir($dh);
            }
        }

        return $tempArray;
    }
    /**
     * Deletes the item passed
     * This is the method called for the delete.
     *
     * @param string $path the item to delete
     *
     * @return object
     */
    public function delete($path)
    {
        if (!$this->exists($path)) {
            return true;
        }
        if (!$this->sftp_rmdir($path)
            && !$this->sftp_unlink($path)
        ) {
            $filelist = $this->scanFilesystem($path);
            foreach ((array)$filelist as $file) {
                $this->delete($path . DS . $file);
            }
            return $this->delete($path);
        }

        return false;
    }
}
