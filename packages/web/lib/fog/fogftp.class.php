<?php
class FOGFTP extends FOGGetSet {
    protected $data = array(
        'host' => '',
        'username' => '',
        'password' => '',
        'port' => 21,
        'timeout' => 90,
        'passive' => true,
        'mode' => FTP_BINARY,
    );
    private static $link;
    private static $lastConnectionHash;
    private static $lastLoginHash;
    private static $currentConnectionHash;
    private static $currentLoginHash;
    public function __destruct() {
        $this->close();
    }
    public function alloc(int $filesize,string &$result) {
        return @ftp_alloc(self::$link,$filesize,$result);
    }
    public function cdup() {
        return @ftp_cdup(self::$link);
    }
    public function chdir(string $directory) {
        return @ftp_chdir(self::$link,$directory);
    }
    public function chmod(int $mode = 0,string $filename) {
        if (!$mode) $mode = $this->get('mode');
        return @ftp_chmod(self::$link,$mode,$filename);
    }
    public function close() {
        if (self::$link) @ftp_close(self::$link);
        self::$link = null;
        return $this;
    }
    public function connect(string $host = '',int $port = 0,int $timeout = 90,bool $autologin = true) {
        try {
            self::$currentConnectionHash = password_hash(serialize($this->data),PASSWORD_BCRYPT,['cost'=>11]);
            if (self::$link && self::$currentConnectionHash == self::$lastConnectionHash) return $this;
            if (!$host) $host = $this->get('host');
            if (!$port) $port = $this->getSetting('FOG_FTP_PORT') ? $this->getSetting('FOG_FTP_PORT') : $this->get('port');
            if (!$timeout) $timeout = $this->getSetting('FOG_FTP_TIMEOUT') ? $this->getSetting('FOG_FTP_TIMEOUT') : $this->get('timeout');
            if ((self::$link = @ftp_connect($host,$port,$timeout)) === false) self::ftperror();
            if ($autologin) {
                $this->login();
                $this->pasv($this->get('passive'));
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            return false;
        }
        self::$lastConnectionHash = self::$currentConnectionHash;
        return $this;
    }
    public function delete(string $path,bool $recursive = true) {
        if ($recursive) return $this->recursive_delete($path);
        try {
            if (@ftp_delete(self::$link,$path) === false) self::ftperror();
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        return $this;
    }
    public function recursive_delete($path) {
        if (!(@ftp_delete(self::$link, $path) || @ftp_rmdir(self::$link,$path))) {
            $filelist = $this->nlist($path);
            if ($filelist) {
                foreach($filelist AS $i => &$file) $this->recursive_delete($file);
                unset($file);
                $this->recursive_delete($path);
            }
        }
        return $this;
    }
    public function exec(string $command) {
        return @ftp_exec(self::$link,$command);
    }
    public function fget(resource $handle,string $remote_file,int $mode = 0,int $resumepos = 0) {
        if (!$mode) $mode = $this->get('mode');
        return @ftp_fget(self::$link,$handle,$remote_file,$mode,$resumepos);
    }
    public function fput(string $remote_file,resource $handle,int $mode = 0,int $startpos = 0) {
        if (!$mode) $mode = $this->get('mode');
        return @ftp_fput(self::$link,$remote_file,$handle,$mode,$startpos);
    }
    private static function ftperror() {
        $error = error_get_last();
        throw new Exception(sprintf('%s: %s, %s: %s, %s: %s, %s: %s',_('Type'),$error['type'],_('File'),$error['file'],_('Line'),$error['line'],_('Message'),$error['message']));
    }
    public function get_option(int $option) {
        return @ftp_get_option(self::$link,$option);
    }
    public function pull(string $local_file,string $remote_file,int $mode = 0,int $resumepos = 0) {
        if (!$mode) $mode = $this->get('mode');
        return @ftp_get(self::$link,$local_file,$remote_file,$mode,$resumepos);
    }
    public function login(string $username = null,string $password = null) {
        try {
            self::$currentLoginHash = password_hash(serialize($this),PASSWORD_BCRYPT,['cost'=>11]);
            if (self::$currentLoginHash == self::$lastLoginHash) return $this;
            if (!$username) $username = $this->get('username');
            if (!$password) $password = $this->get('password');
            if (@ftp_login(self::$link,$username,$password) === false) self::ftperror();
        } catch (Exception $e) {
            echo $e->getMessage();
            return false;
        }
        self::$lastLoginHash = self::$currentLoginHash;
        return $this;
    }
    public function mdtm(string $remote_file) {
        return @ftp_mdtm(self::$link,$remote_file);
    }
    public function mkdir(string $directory) {
        return @ftp_mkdir(self::$link,$directory);
    }
    public function nb_continue() {
        return @ftp_nb_continue(self::$link);
    }
    public function nb_fget(resource $handle,string $remote_file,int $mode = 0,int $resumepos = 0) {
        if (!$mode) $mode = $this->get('mode');
        return @ftp_nb_fget(self::$link,$handle,$remote_file,$mode,$resumepos);
    }
    public function nb_fput(string $remote_file,resource $handle,int $mode =0,int $startpos = 0) {
        if (!$mode) $mode = $this->get('mode');
        return @ftp_nb_fput(self::$link,$remote_file,$handle,$mode,$resumepos);
    }
    public function nb_get(string $local_file,string $remote_file,int $mode = 0,int $resumepos = 0) {
        if (!$mode) $mode = $this->get('mode');
        return @ftp_nb_get(self::$link,$local_file,$remote_file,$mode,$resumepos);
    }
    public function nb_put(string $remote_file,string $local_file,int $mode =0,int $startpos = 0) {
        if (!$mode) $mode = $this->get('mode');
        return @ftp_nb_put(self::$link,$remote_file,$local_file,$mode,$resumepos);
    }
    public function nlist(string $directory) {
        return @ftp_nlist(self::$link,$directory);
    }
    public function pasv(bool $pasv = false) {
        if (!$pasv) $pasv = $this->get('passive');
        return @ftp_pasv(self::$link,$pasv);
    }
    public function put(string $remote_file,string $local_file,int $mode = 0,int $startpos = 0) {
        if (!$mode) $mode = $this->get('mode');
        return @ftp_put(self::$link,$remote_file,$local_file,$mode,$resumepos);
    }
    public function pwd() {
        return @ftp_pwd(self::$link);
    }
    public function quit() {
        return $this->close();
    }
    public function raw(string $command) {
        return @ftp_raw(self::$link,$command);
    }
    public function rawlist(string $directory,bool $recursive = false) {
        return @ftp_rawlist(self::$link,$directory,$recursive);
    }
    public function rename(string $oldname,string $newname) {
        return @ftp_rename(self::$link,$oldname,$newname);
    }
    /*public function rename($remotePath, $localPath) {
        if(@ftp_nlist($this->link,$localPath)) {
            if(!@ftp_rename($this->link, $localPath, $remotePath)) {
                $error = error_get_last();
                throw new Exception(sprintf('%s: Failed to %s file. Remote Path: %s, Local Path: %s, Error: %s', get_class($this), __FUNCTION__, $remotePath, $localPath, $error['message']));
            }
        }
        return $this;
    }*/
    public function rmdir(string $directory) {
        return @ftp_rmdir(self::$link,$directory);
    }
    public function set_option(int $option,$value) {
        return @ftp_set_option(self::$link,$option,$value);
    }
    public function site(string $command) {
        return @ftp_site(self::$link,$command);
    }
    public function size(string $remote_file,$rawsize = true) {
        if ($rawsize) return $this->rawsize($remote_file);
        return @ftp_size(self::$link,$remote_file);
    }
    public function rawsize(string $remote_file) {
        $size = 0;
        $filelist = $this->rawlist($remote_file);
        if (!$filelist || count($filelist) < 1) return 0;
        foreach($filelist AS &$file) {
            $fileinfo = preg_split('#\s+#',$file,null,PREG_SPLIT_NO_EMPTY);
            $size += $fileinfo[4];
            unset($file);
        }
        unset($filelist);
        return $size;
    }
    public function ssl_connect(string $host = '',int $port = 0,int $timeout = 90,bool $autologin = true) {
        try {
            if (self::$link && password_verify(serialize(self::$link),self::$lastConnectionHash)) return $this;
            if (!$host) $host = $this->get('host');
            if (!$port) $port = $this->getSetting('FOG_FTP_PORT') ? $this->getSetting('FOG_FTP_PORT') : $this->get('port');
            if (!$timeout) $timeout = $this->getSetting('FOG_FTP_TIMEOUT') ? $this->getSetting('FOG_FTP_TIMEOUT') : $this->get('timeout');
            if ((self::$link = @ftp_ssl_connect($host,$port,$timeout)) === false) self::ftperror();
            if ($autologin) {
                $this->login();
                $this->pasv($this->get('passive'));
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            return false;
        }
        self::$lastConnectionHash = password_hash(serialize(self::$link),PASSWORD_BCRYPT,['cost'=>11]);
        return $this;
    }
    public function systype() {
        return @ftp_systype(self::$link);
    }
    public function exists($path) {
        $dirlisting = $this->nlist(dirname($path));
        return in_array($path,$dirlisting);
    }
}
