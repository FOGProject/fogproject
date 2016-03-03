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
    public function alloc($filesize,&$result) {
        return @ftp_alloc(self::$link,$filesize,$result);
    }
    public function cdup() {
        return @ftp_cdup(self::$link);
    }
    public function chdir($directory) {
        return @ftp_chdir(self::$link,$directory);
    }
    public function chmod($mode = 0,$filename) {
        if (!$mode) $mode = $this->get('mode');
        return @ftp_chmod(self::$link,$mode,$filename);
    }
    public function close() {
        if (self::$link) @ftp_close(self::$link);
        self::$link = null;
        return $this;
    }
    public function connect($host = '',$port = 0,$timeout = 90,$autologin = true) {
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
            throw new Exception($e->getMessage());
        }
        self::$lastConnectionHash = self::$currentConnectionHash;
        return $this;
    }
    public function delete($path,$recursive = true,$recur_delete_run = false) {
        if ($recursive) return $this->recursive_delete($path);
        if ($recur_delete_run) return @ftp_delete(self::$link,$path);
        if (@ftp_delete(self::$link,$path) === false) self::ftperror();
        return $this;
    }
    public function recursive_delete($path) {
        if (!($this->delete($path,false,true) || $this->rmdir($path))) {
            $filelist = $this->nlist($path);
            if ($filelist) {
                foreach($filelist AS $i => &$file) $this->recursive_delete($file);
                unset($file);
                $this->recursive_delete($path);
            }
        }
        return $this;
    }
    public function exec($command) {
        return @ftp_exec(self::$link,$command);
    }
    public function fget($handle,$remote_file,$mode = 0,$resumepos = 0) {
        if (!$mode) $mode = $this->get('mode');
        if ($resumepos) return @ftp_fget(self::$link,$handle,$remote_file,$mode,$resumepos);
        return @ftp_fget(self::$link,$handle,$remote_file,$mode);
    }
    public function fput($remote_file,$handle,$mode = 0,$startpos = 0) {
        if (!$mode) $mode = $this->get('mode');
        if ($startpos) return @ftp_fput(self::$link,$remote_file,$handle,$mode,$startpos);
        return @ftp_fput(self::$link,$remote_file,$handle,$mode);
    }
    private static function ftperror() {
        $error = error_get_last();
        throw new Exception(sprintf('%s: %s, %s: %s, %s: %s, %s: %s',_('Type'),$error['type'],_('File'),$error['file'],_('Line'),$error['line'],_('Message'),$error['message']));
    }
    public function get_option($option) {
        return @ftp_get_option(self::$link,$option);
    }
    public function pull($local_file,$remote_file,$mode = 0,$resumepos = 0) {
        if (!$mode) $mode = $this->get('mode');
        if ($resumepos) return @ftp_get(self::$link,$local_file,$remote_file,$mode,$resumepos);
        return @ftp_get(self::$link,$local_file,$remote_file,$mode);
    }
    public function login($username = null,$password = null) {
        try {
            self::$currentLoginHash = password_hash(serialize($this),PASSWORD_BCRYPT,['cost'=>11]);
            if (self::$currentLoginHash == self::$lastLoginHash) return $this;
            if (!$username) $username = $this->get('username');
            if (!$password) $password = $this->get('password');
            if (@ftp_login(self::$link,$username,$password) === false) self::ftperror();
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        self::$lastLoginHash = self::$currentLoginHash;
        return $this;
    }
    public function mdtm($remote_file) {
        return @ftp_mdtm(self::$link,$remote_file);
    }
    public function mkdir($directory) {
        return @ftp_mkdir(self::$link,$directory);
    }
    public function nb_continue() {
        return @ftp_nb_continue(self::$link);
    }
    public function nb_fget($handle,$remote_file,$mode = 0,$resumepos = 0) {
        if (!$mode) $mode = $this->get('mode');
        if ($resumepos) return @ftp_nb_fget(self::$link,$handle,$remote_file,$mode,$resumepos);
        return @ftp_nb_fget(self::$link,$handle,$remote_file,$mode);
    }
    public function nb_fput($remote_file,$handle,$mode =0,$startpos = 0) {
        if (!$mode) $mode = $this->get('mode');
        if ($startpos) return @ftp_nb_fput(self::$link,$remote_file,$handle,$mode,$resumepos);
        return @ftp_nb_fput(self::$link,$remote_file,$handle,$mode);
    }
    public function nb_get($local_file,$remote_file,$mode = 0,$resumepos = 0) {
        if (!$mode) $mode = $this->get('mode');
        if ($resumepos) return @ftp_nb_get(self::$link,$local_file,$remote_file,$mode,$resumepos);
        return @ftp_nb_get(self::$link,$local_file,$remote_file,$mode);
    }
    public function nb_put($remote_file,$local_file,$mode =0,$startpos = 0) {
        if (!$mode) $mode = $this->get('mode');
        if ($startpos) return @ftp_nb_put(self::$link,$remote_file,$local_file,$mode,$resumepos);
        return @ftp_nb_put(self::$link,$remote_file,$local_file,$mode);
    }
    public function nlist($directory) {
        return @ftp_nlist(self::$link,$directory);
    }
    public function pasv($pasv = false) {
        if (!$pasv) $pasv = $this->get('passive');
        return @ftp_pasv(self::$link,$pasv);
    }
    public function put($remote_file,$local_file,$mode = 0,$startpos = 0) {
        if (!$mode) $mode = $this->get('mode');
        if ($startpos) return @ftp_put(self::$link,$remote_file,$local_file,$mode,$resumepos);
        return @ftp_put(self::$link,$remote_file,$local_file,$mode,$resumepos);
    }
    public function pwd() {
        return @ftp_pwd(self::$link);
    }
    public function quit() {
        return $this->close();
    }
    public function raw($command) {
        return @ftp_raw(self::$link,$command);
    }
    public function rawlist($directory,$recursive = false) {
        return @ftp_rawlist(self::$link,$directory,$recursive);
    }
    public function rename($oldname,$newname,$recurse_rename = true) {
        return @ftp_rename(self::$link,$oldname,$newname);
    }
    public function rmdir($directory) {
        return @ftp_rmdir(self::$link,$directory);
    }
    public function set_option($option,$value) {
        return @ftp_set_option(self::$link,$option,$value);
    }
    public function site($command) {
        return @ftp_site(self::$link,$command);
    }
    public function size($remote_file,$rawsize = true) {
        if ($rawsize) return $this->rawsize($remote_file);
        return @ftp_size(self::$link,$remote_file);
    }
    public function rawsize($remote_file) {
        $size = 0;
        $filelist = $this->rawlist($remote_file);
        if (!$filelist || count($filelist) < 1) $filelist = $this->rawlist(dirname($remote_file));
        $filename = basename($remote_file);
        $filelist = preg_grep("#$filename#",$filelist);
        foreach($filelist AS &$file) {
            $fileinfo = preg_split('#\s+#',$file,null,PREG_SPLIT_NO_EMPTY);
            $size += $fileinfo[4];
            unset($file);
        }
        unset($filelist);
        return $size;
    }
    public function ssl_connect($host = '',$port = 0,$timeout = 90,$autologin = true) {
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
            throw new Exception($e->getMessage());
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
