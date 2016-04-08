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
        return @ftp_alloc(static::$link,$filesize,$result);
    }
    public function cdup() {
        return @ftp_cdup(static::$link);
    }
    public function chdir($directory) {
        return @ftp_chdir(static::$link,$directory);
    }
    public function chmod($mode = 0,$filename) {
        if (!$mode) $mode = $this->get('mode');
        return @ftp_chmod(static::$link,$mode,$filename);
    }
    public function close() {
        if (static::$link) @ftp_close(static::$link);
        static::$link = null;
        return $this;
    }
    public function connect($host = '',$port = 0,$timeout = 90,$autologin = true,$connectmethod = 'ftp_connect') {
        try {
            static::$currentConnectionHash = password_hash(serialize($this->data),PASSWORD_BCRYPT,['cost'=>11]);
            if (static::$link && static::$currentConnectionHash == static::$lastConnectionHash) return $this;
            if (!$host) $host = $this->get('host');
            if (!$port) $port = $this->getSetting('FOG_FTP_PORT') ? $this->getSetting('FOG_FTP_PORT') : $this->get('port');
            if (!$timeout) $timeout = $this->getSetting('FOG_FTP_TIMEOUT') ? $this->getSetting('FOG_FTP_TIMEOUT') : $this->get('timeout');
            if ((static::$link = @$connectmethod($host,$port,$timeout)) === false) static::ftperror();
            if ($autologin) {
                $this->login();
                $this->pasv($this->get('passive'));
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        static::$lastConnectionHash = static::$currentConnectionHash;
        return $this;
    }
    public function delete($path,$recursive = true,$recur_delete_run = false) {
        if ($recursive) return $this->recursive_delete($path);
        if ($recur_delete_run) return @ftp_delete(static::$link,$path);
        if (@ftp_delete(static::$link,$path) === false) static::ftperror();
        return $this;
    }
    public function recursive_delete($path) {
        if ($this->exists($path) && !($this->delete($path,false,true) || $this->rmdir($path))) {
            $filelist = $this->nlist($path);
            if ($filelist) {
                array_map(function(&$file) {
                    $this->recursive_delete($file);
                    unset($file);
                },(array)$filelist);
            }
        }
        return $this;
    }
    public function exec($command) {
        return @ftp_exec(static::$link,$command);
    }
    public function fget($handle,$remote_file,$mode = 0,$resumepos = 0) {
        if (!$mode) $mode = $this->get('mode');
        if ($resumepos) return @ftp_fget(static::$link,$handle,$remote_file,$mode,$resumepos);
        return @ftp_fget(static::$link,$handle,$remote_file,$mode);
    }
    public function fput($remote_file,$handle,$mode = 0,$startpos = 0) {
        if (!$mode) $mode = $this->get('mode');
        if ($startpos) return @ftp_fput(static::$link,$remote_file,$handle,$mode,$startpos);
        return @ftp_fput(static::$link,$remote_file,$handle,$mode);
    }
    private static function ftperror() {
        $error = error_get_last();
        throw new Exception(sprintf('%s: %s, %s: %s, %s: %s, %s: %s',_('Type'),$error['type'],_('File'),$error['file'],_('Line'),$error['line'],_('Message'),$error['message']));
    }
    public function get_option($option) {
        return @ftp_get_option(static::$link,$option);
    }
    public function pull($local_file,$remote_file,$mode = 0,$resumepos = 0) {
        if (!$mode) $mode = $this->get('mode');
        if ($resumepos) return @ftp_get(static::$link,$local_file,$remote_file,$mode,$resumepos);
        return @ftp_get(static::$link,$local_file,$remote_file,$mode);
    }
    public function login($username = null,$password = null) {
        try {
            static::$currentLoginHash = password_hash(serialize(static::$link),PASSWORD_BCRYPT,['cost'=>11]);
            if (static::$currentLoginHash == static::$lastLoginHash) return $this;
            if (!$username) $username = $this->get('username');
            if (!$password) $password = $this->get('password');
            if (@ftp_login(static::$link,$username,$password) === false) static::ftperror();
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        static::$lastLoginHash = static::$currentLoginHash;
        return $this;
    }
    public function mdtm($remote_file) {
        return @ftp_mdtm(static::$link,$remote_file);
    }
    public function mkdir($directory) {
        return @ftp_mkdir(static::$link,$directory);
    }
    public function nb_continue() {
        return @ftp_nb_continue(static::$link);
    }
    public function nb_fget($handle,$remote_file,$mode = 0,$resumepos = 0) {
        if (!$mode) $mode = $this->get('mode');
        if ($resumepos) return @ftp_nb_fget(static::$link,$handle,$remote_file,$mode,$resumepos);
        return @ftp_nb_fget(static::$link,$handle,$remote_file,$mode);
    }
    public function nb_fput($remote_file,$handle,$mode =0,$startpos = 0) {
        if (!$mode) $mode = $this->get('mode');
        if ($startpos) return @ftp_nb_fput(static::$link,$remote_file,$handle,$mode,$resumepos);
        return @ftp_nb_fput(static::$link,$remote_file,$handle,$mode);
    }
    public function nb_get($local_file,$remote_file,$mode = 0,$resumepos = 0) {
        if (!$mode) $mode = $this->get('mode');
        if ($resumepos) return @ftp_nb_get(static::$link,$local_file,$remote_file,$mode,$resumepos);
        return @ftp_nb_get(static::$link,$local_file,$remote_file,$mode);
    }
    public function nb_put($remote_file,$local_file,$mode =0,$startpos = 0) {
        if (!$mode) $mode = $this->get('mode');
        if ($startpos) return @ftp_nb_put(static::$link,$remote_file,$local_file,$mode,$resumepos);
        return @ftp_nb_put(static::$link,$remote_file,$local_file,$mode);
    }
    public function nlist($directory) {
        return @ftp_nlist(static::$link,$directory);
    }
    public function pasv($pasv = false) {
        if (!$pasv) $pasv = $this->get('passive');
        return @ftp_pasv(static::$link,$pasv);
    }
    public function put($remote_file,$local_file,$mode = 0,$startpos = 0) {
        if (!$mode) $mode = $this->get('mode');
        if ($startpos) return @ftp_put(static::$link,$remote_file,$local_file,$mode,$resumepos);
        return @ftp_put(static::$link,$remote_file,$local_file,$mode,$resumepos);
    }
    public function pwd() {
        return @ftp_pwd(static::$link);
    }
    public function quit() {
        return $this->close();
    }
    public function raw($command) {
        return @ftp_raw(static::$link,$command);
    }
    public function rawlist($directory,$recursive = false) {
        return @ftp_rawlist(static::$link,$directory,$recursive);
    }
    public function rename($oldname,$newname,$recurse_rename = true) {
        return @ftp_rename(static::$link,$oldname,$newname);
    }
    public function rmdir($directory) {
        return @ftp_rmdir(static::$link,$directory);
    }
    public function set_option($option,$value) {
        return @ftp_set_option(static::$link,$option,$value);
    }
    public function site($command) {
        return @ftp_site(static::$link,$command);
    }
    public function size($remote_file,$rawsize = true) {
        if ($rawsize) return $this->rawsize($remote_file);
        return @ftp_size(static::$link,$remote_file);
    }
    public function rawsize($remote_file) {
        if (!$this->exists($remote_file)) return 0;
        $size = 0;
        $filelist = $this->rawlist($remote_file);
        if (!$filelist) {
            $filelist = $this->rawlist(dirname($remote_file));
            $filename = basename($remote_file);
            $filelist = preg_grep("#$filename#",$filelist);
        }
        array_map(function(&$file) use (&$size) {
            $fileinfo = preg_split('#\s+#',$file,null,PREG_SPLIT_NO_EMPTY);
            $size += $fileinfo[4];
            unset($file);
        },(array)$filelist);
        return $size;
    }
    public function ssl_connect($host = '',$port = 0,$timeout = 90,$autologin = true) {
        try {
            if (!$host) $host = $this->get('host');
            if (!$port) $port = $this->getSetting('FOG_FTP_PORT') ? $this->getSetting('FOG_FTP_PORT') : $this->get('port');
            if (!$timeout) $timeout = $this->getSetting('FOG_FTP_TIMEOUT') ? $this->getSetting('FOG_FTP_TIMEOUT') : $this->get('timeout');
            $this->connect($host,$port,$timeout,$autologin,'ftp_ssl_connect');
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        return $this;
    }
    public function systype() {
        return @ftp_systype(static::$link);
    }
    public function exists($path) {
        $dirlisting = $this->nlist(dirname($path));
        return in_array($path,$dirlisting);
    }
}
