<?php
class FOGFTP extends FOGGetSet {
    protected $data = array(
        'host' => '',
        'username' => '',
        'password' => '',
        'port' => '',
        'timeout' => '',
    );
    private $link;
    private $loginLink;
    private $lastConnectionHash;
    public $passiveMode = true;
    public function __destruct() {
        $this->close(true);
    }
    public function connect() {
        unset($error,$result);
        $this->set('port',$this->getSetting('FOG_FTP_PORT'));
        $this->set('timeout',$this->getSetting('FOG_FTP_TIMEOUT'));
        $connectionHash = md5(serialize($this->data));
        $connected = $this->link && $this->lastConnectionHash == $connectionHash;
        if ($connected) return $this;
        if (!($this->link = @ftp_connect($this->get('host'), $this->get('port'), $this->get('timeout')))) {
            $error = error_get_last();
            throw new Exception(sprintf('%s: Failed to connect. Host: %s, Error: %s', get_class($this), $this->get('host'), $error['message']));
        } else if (!($this->loginLink = @ftp_login($this->link,stripslashes($this->get('username')),$this->get('password')))) {
            $error = error_get_last();
            throw new Exception(sprintf('%s: Login failed. Host: %s, Username: %s, Password: %s, Error: %s',get_class($this),$this->get('host'),stripslashes($this->get('username')),$this->get('password'),$error['message']));
        }
        if ($this->passiveMode) @ftp_pasv($this->link,true);
        $this->lastConnectionHash = $connectionHash;
        return $this;
    }
    public function close($if = true) {
        if ($this->link && $if) @ftp_close($this->link);
        $this->link = null;
        return $this;
    }
    public function put($remotePath, $localPath, $mode = FTP_BINARY) {
        if (!@ftp_put($this->link, $remotePath, $localPath, $mode)) {
            $error = error_get_last();
            throw new Exception(sprintf('%s: Failed to %s file. Remote Path: %s, Local Path: %s, Error: %s', get_class($this), __FUNCTION__, $remotePath, $localPath, $error['message']));
        }
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
    public function nlist($remotePath) {
        return @ftp_nlist($this->link,$remotePath);
    }
    public function exists($path) {
        $dirlisting = $this->nlist(dirname($path));
        return in_array($path,$dirlisting);
    }
    public function chdir($path) {
        return @ftp_chdir($this->link, $path);
    }
    public function size($pathfile) {
        $size = 0;
        $filelist = @ftp_rawlist($this->link,$pathfile);
        if ($filelist) {
            foreach($filelist AS $i => $file) {
                $fileinfo = preg_split('#\s+#',$file,null,PREG_SPLIT_NO_EMPTY);
                $size += $fileinfo[4];
            }
            unset($file);
        }
        return ($size > 0 ? $size : 0);
    }
    public function mkdir($remotePath) {
        return @ftp_mkdir($this->link,$remotePath);
    }
    public function delete($path) {
        if (!(@ftp_delete($this->link, $path)||@ftp_rmdir($this->link,$path))) {
            $filelist = @ftp_nlist($this->link,$path);
            if ($filelist) {
                foreach($filelist AS $i => &$file) $this->delete($file);
                unset($file);
                $this->delete($path);
            }
        }
        return $this;
    }
}
