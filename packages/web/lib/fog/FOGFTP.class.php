<?php
class FOGFTP extends FOGGetSet {
    // Data
    /** @var $data stores the data for this session */
    public $data = array(
        'host'		=> '',
        'username'	=> '',
        'password'	=> '',
        'port'		=> '',
        'timeout'	=> '',
    );
    /** @var $link ftp_connect the session link */
    private $link;
    /** @var $loginLink ftp_login the link as logged in */
    private $loginLink;
    /** @var $lastConnectionHash md5 of the serialized data */
    private $lastConnectionHash;
    /** @var $passiveMode true or false */
    public $passiveMode = true;
    /** @function __destruct() close the session if no longer needed
     * @return null
     */
    public function __destruct() {$this->close(true);}
    /** @function connect() connect the ftp session
     * @return $this object or error
     */
        public function connect() {
            // Ensure all set variables are clean first
            unset($error,$result);
            // Get the port from FOG
            $this->set('port',$this->getClass('FOGCore')->getSetting('FOG_FTP_PORT'));
            // Get the timeout from FOG
            $this->set('timeout',$this->getClass('FOGCore')->getSetting('FOG_FTP_TIMEOUT'));
            // Store the session md5 of the serialize
            $connectionHash = md5(serialize($this->data));
            // Are/were we already connected?
            $connected = (($this->link && $this->lastConnectionHash == md5(serialize($this->data))) || !$this->get('host') || !$this->get('username') || !$this->get('password') || !$this->get('port'));
            // Return if we are/were connected
            if ($connected) return $this;
            if (!($this->link = @ftp_connect($this->get('host'), $this->get('port'), $this->get('timeout')))) {
                $error = error_get_last();
                throw new Exception(sprintf('%s: Failed to connect. Host: %s, Error: %s', get_class($this), $this->get('host'), $error['message']));
            } else if (!($this->loginLink = @ftp_login($this->link, stripslashes($this->get('username')), $this->get('password')))) {
                $error = error_get_last();
                throw new Exception(sprintf('%s: Login failed. Host: %s, Username: %s, Password: %s, Error: %s', get_class($this), $this->get('host'), stripslashes($this->get('username')), $this->get('password'), $error['message']));
            }
            if ($this->passiveMode) @ftp_pasv($this->link, true);
            // Store connection hash
            $this->lastConnectionHash = $connectionHash;
            return $this;
        }
    public function close($if = true) {
        // Close only if connected
        if ($this->link && $if) @ftp_close($this->link);
        // unset connection variable
        unset($this->link);
        // Return
        return $this;
    }
    public function put($remotePath, $localPath, $mode = FTP_BINARY) {
        // Put file
        if (!@ftp_put($this->link, $remotePath, $localPath, $mode)) {
            $error = error_get_last();
            throw new Exception(sprintf('%s: Failed to %s file. Remote Path: %s, Local Path: %s, Error: %s', get_class($this), __FUNCTION__, $remotePath, $localPath, $error['message']));
        }
        // Return
        return $this;
    }
    public function rename($remotePath, $localPath) {
        if(@ftp_nlist($this->link,$localPath)) {
            if(!@ftp_rename($this->link, $localPath, $remotePath)) {
                $error = error_get_last();
                throw new Exception(sprintf('%s: Failed to %s file. Remote Path: %s, Local Path: %s, Error: %s', get_class($this), __FUNCTION__, $remotePath, $localPath, $error['message']));
            }
        }
        return $this;
    }
    public function nlist($remotePath) {return @ftp_nlist($this->link,$remotePath);}
        public function exists($path) {
            $dirlisting = $this->nlist(dirname($path));
            return in_array($path,$dirlisting);
        }
    public function chdir($path) {return @ftp_chdir($this->link, $path);}
        public function size($pathfile) {
            $size = 0;
            $filelist = @ftp_rawlist($this->link,$pathfile);
            if ($filelist) {
                foreach($filelist AS $file) {
                    $fileinfo = preg_split('#\s+#',$file,null,PREG_SPLIT_NO_EMPTY);
                    $size += $fileinfo[4];
                }
            }
            return ($size > 0 ? $size : 0);
        }
    public function mkdir($remotePath) {return @ftp_mkdir($this->link,$remotePath);}
        public function delete($path) {
            if (!(@ftp_delete($this->link, $path)||@ftp_rmdir($this->link,$path))) {
                $filelist = @ftp_nlist($this->link,$path);
                if ($filelist) {
                    foreach($filelist AS $file) $this->delete($file);
                    $this->delete($path);
                }
            }
            // Return
            return $this;
        }
}
