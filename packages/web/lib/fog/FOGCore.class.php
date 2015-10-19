<?php
class FOGCore extends FOGBase {
    public function attemptLogin($username,$password) {
        return $this->getClass('User',@max($this->getSubObjectIDs('User',array('name'=>$username),'id')))->validate_pw($password);
    }
    public function stopScheduledTask($task) {
        return $this->getClass('ScheduledTask',$task->get('id'))->set('isActive',(int)false)->save();
    }
    /** getSetting($key)
        Get's global Setting Values
     */
    public function getSetting($key) {
        $value = '';
        $Services = $this->getClass(ServiceManager)->find(array(name=>$key));
        foreach ($Services AS $i => &$Service) {
            if ($Service->isValid()) {
                $value = $Service->get(value);
                break;
            }
        }
        return $value;
    }
    /** setSetting($key, $value)
        Set's a new default value.
     */
    public function setSetting($key, $value) {
        $Services = $this->getClass(ServiceManager)->find(array(name=>$key));
        foreach ($Services AS $i => &$Service) {
            $Service->set(value,$value);
            if (!$Service->save()) return false;
            break;
        }
        unset($Service);
        return $this;
    }
    /** addUpdateMACLookupTable($macprefix,$strMan)
        Updates/add's MAC Manufacturers
     */
    public function addUpdateMACLookupTable($macprefix) {
        $this->clearMACLookupTable();
        foreach($macprefix AS $macpre => &$maker) $macArray[] = "('".$this->DB->sanitize($macpre)."','".$this->DB->sanitize($maker)."')";
        unset($maker);
        $sql = "INSERT INTO `oui` (`ouiMACPrefix`,`ouiMan`) VALUES ".implode((array)$macArray,',');
        return $this->DB->query($sql);
    }
    /** clearMACLookupTable()
        Clear's all entries in the table.
     */
    public function clearMACLookupTable() {
        return !$this->DB->query("TRUNCATE TABLE %s",$this->getClass(OUI)->databaseTable);
    }
    /** getMACLookupCount()
        returns the number of MAC's loaded.
     */
    public function getMACLookupCount() {
        return $this->getClass(OUIManager)->count();
    }
    /** resolveHostname($host)
        Returns the hostname.  Useful for Hostname dns translating for the server (e.g. fogserver instead of 127.0.0.1) in the address
        bar.
     */
    public function resolveHostname($host) {
        if (filter_var(trim($host),FILTER_VALIDATE_IP)) return trim($host);
        return trim(gethostbyname(trim($host)));
    }
    /** makeTempFilePath()
        creates the temporary file.
     */
    public function makeTempFilePath() {
        return tempnam(sys_get_temp_dir(),'FOG');
    }
    /** SystemUptime()
        Returns the uptime of the server.
     */
    public function SystemUptime() {
        $data = trim(shell_exec('uptime'));
        $tmp = explode(' load average: ', $data);
        $load = end($tmp);
        $tmp = explode(' up ',$data);
        $tmp = explode(',', end($tmp));
        $uptime = $tmp;
        $uptime = (count($uptime) > 1 ? $uptime[0] . ', ' . $uptime[1] : 'uptime not found');
        return array(uptime=>$uptime,load=>$load);
    }
    /** clear_screen($outputdevice)
        Clears the screen for information.
     */
    public function clear_screen($outputdevice) {
        $this->out(chr(27)."[2J".chr(27)."[;H",$outputdevice);
    }
    /** wait_interface_ready($interface,$outputdevice)
        Waits for the network interface to be ready so services operate.
     */
    public function wait_interface_ready($interface,$outputdevice) {
        while (true) {
            $retarr = array();
            exec('netstat -inN',$retarr);
            array_shift($retarr);
            array_shift($retarr);
            foreach($retarr AS $i => &$line) {
                $t = substr($line,0,strpos($line,' '));
                if ($t == $interface) {
                    $this->out('Interface now ready..',$outputdevice);
                    break 2;
                }
            }
            unset($line);
            $this->out('Interface not ready, waiting..',$outputdevice);
            sleep(10);
        }
    }
    /** getBroadcast()
     * Gets the interfaces broadcast ip
     */
    public function getBroadcast() {
        $output = array();
        exec("/sbin/ip addr | awk -F'[ /]+' '/global/ {print $6}'|grep '[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}'", $IPs, $retVal);
        if (!count($IPs)) exec("/sbin/ifconfig -a | awk '/(cast)/ {print $3}' | cut -d':' -f2' | grep '[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}'", $IPs,$retVal);
        foreach ($IPs AS $i => &$IP) {
            $IP = trim($IP);
            $output[] = $IP;
        }
        unset($IP);
        return array_values(array_unique((array)$output));
    }
    /** getHWInfo()
     * Returns the hardware information for hwinfo link on dashboard.
     * @return $data
     */
    public function getHWInfo() {
        $data['general'] = '@@general';
        $data['kernel'] = trim(php_uname('r'));
        $data['hostname'] = trim(php_uname('n'));
        $data['uptimeload'] = trim(shell_exec('uptime'));
        $data['cputype'] = trim(shell_exec("cat /proc/cpuinfo | head -n2 | tail -n1 | cut -f2 -d: | sed 's| ||'"));
        $data['cpucount'] = trim(shell_exec("grep '^processor' /proc/cpuinfo | tail -n 1 | awk '{print \$3+1}'"));
        $data['cpumodel'] = trim(shell_exec("cat /proc/cpuinfo | head -n5 | tail -n1 | cut -f2 -d: | sed 's| ||'"));
        $data['cpuspeed'] = trim(shell_exec("cat /proc/cpuinfo | head -n8 | tail -n1 | cut -f2 -d: | sed 's| ||'"));
        $data['cpucache'] = trim(shell_exec("cat /proc/cpuinfo | head -n9 | tail -n1 | cut -f2 -d: | sed 's| ||'"));
        $data['totmem'] = $this->formatByteSize(trim(shell_exec("free -b | head -n2 | tail -n1 | awk '{ print \$2 }'")));
        $data['usedmem'] = $this->formatByteSize(trim(shell_exec("free -b | head -n2 | tail -n1 | awk '{ print \$3 }'")));
        $data['freemem'] = $this->formatByteSize(trim(shell_exec("free -b | head -n2 | tail -n1 | awk '{ print \$4 }'")));
        $data['filesys'] = '@@fs';
        $t = shell_exec('df | grep -vE "^Filesystem|shm"');
        $l = explode("\n",$t);
        foreach ($l AS $i => &$n) {
            if (preg_match("/(\d+) +(\d+) +(\d+) +\d+%/",$n,$matches)) {
                if (is_numeric($matches[1])) $hdtotal += $matches[1]*1024;
                if (is_numeric($matches[2])) $hdused += $matches[2]*1024;
            }
        }
        unset($n);
        $data['totalspace'] = $this->formatByteSize($hdtotal);
        $data['usedspace'] = $this->formatByteSize($hdused);
        $data['nic'] = '@@nic';
        $NET = shell_exec('cat "/proc/net/dev"');
        $lines = explode("\n",$NET);
        foreach ($lines AS $i => &$line) {
            if (preg_match('/:/',$line)) {
                list($dev_name,$stats_list) = preg_split('/:/',$line,2);
                $stats = preg_split('/\s+/', trim($stats_list));
                $data[$dev_name] = trim($dev_name).'$$'.$stats[0].'$$'.$stats[8].'$$'.($stats[2]+$stats[10]).'$$'.($stats[3]+$stats[11]);
            }
        }
        unset($line);
        $data['end'] = '@@end';
        return $data;
    }
    /**
     * track($list, $c = 0, $i = 0)
     * @param $list the data to bencode.
     * @param $c completed jobs (seeders)
     * @param $i incompleted jobs (leechers)
     * @return void
     * Will "return" but through throw/catch statement.
     */
    public function track($list, $c = 0, $i = 0) {
        if (is_string($list)) return 'd14:failure reason'.strlen($list).':'.$list.'e';
        $p = '';
        foreach((array)$list AS $i => &$d) {
            $peer_id = '';
            if (!$_REQUEST['no_peer_id']) $peer_id = '7:peer id'.strlen($this->hex2bin($d[2])).':'.$this->hex2bin($d[2]);
            $p .= 'd2:ip'.strlen($d[0]).':'.$d[0].$peer_id.'4:porti'.$d[1].'ee';
        }
        unset($d);
        return 'd8:intervali'.$this->getSetting(FOG_TORRENT_INTERVAL).'e12:min intervali'.$this->getSetting(FOG_TORRENT_INTERVAL_MIN).'e8:completei'.$c.'e10:incompletei'.$i.'e5:peersl'.$p.'ee';
    }
    /**
     * valdata($g,$fixed_size=false)
     * Function simply checks if the required data is met and valid
     * Could use for other functions possibly too.
     * @param $g the request/get/post info to validate.
     * @return void
     * Sends info back to track.
     */
    public function valdata($g,$fixed_size=false) {
        try {
            if (!$_REQUEST[$g]) throw new Exception($this->track('Invalid request, missing data'));
            if (!is_string($_REQUEST[$g])) throw new Exception($this->track('Invalid request, unkown data type'));
            if ($fixed_size && strlen($_REQUEST[$g]) != 20) throw new Exception($this->track('Invalid request, length on fixed argument not correct'));
            if (strlen($_REQUEST[$g]) > 80) throw new Exception($this->track('Request too long'));
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }
    public function setSessionEnv() {
        /** This allows the database concatination system based on number of hosts */
        $this->DB->query("SET SESSION group_concat_max_len=(1024 * {$_SESSION[HostCount]})");
        /** This below ensures the database is always MyISAM */
        $this->DB->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '".DATABASE_NAME."' AND ENGINE != 'MyISAM'");
        /** $tables just stores the tables to cycle through and change as needed */
        $tables = $this->DB->fetch(MYSQLI_NUM,'fetch_all')->get('TABLE_NAME');
        foreach ((array)$tables AS $i => &$table) $this->DB->query("ALTER TABLE `".DATABASE_NAME."`.`".array_shift($table)."` ENGINE=MyISAM");
        unset($table);
        /** frees the memory of the $tables and $table values */
        unset($tables,$table);
        $_SESSION['PluginsInstalled'] = (array)$this->getActivePlugins();
        $_SESSION['FOG_VIEW_DEFAULT_SCREEN'] = $this->getSetting('FOG_VIEW_DEFAULT_SCREEN');
        $_SESSION['FOG_FTP_IMAGE_SIZE'] = $this->getSetting('FOG_FTP_IMAGE_SIZE');
        $_SESSION['Pending-Hosts'] = $this->getClass('HostManager')->count(array('pending'=>1));
        $_SESSION['Pending-MACs'] = $this->getClass('MACAddressAssociationManager')->count(array('pending'=>1));
        $_SESSION['DataReturn'] = $this->getSetting('FOG_DATA_RETURNED');
        $_SESSION['UserCount'] = $this->getClass('UserManager')->count();
        $_SESSION['HostCount'] = $this->getClass('HostManager')->count();
        $_SESSION['GroupCount'] = $this->getClass('GroupManager')->count();
        $_SESSION['ImageCount'] = $this->getClass('ImageManager')->count();
        $_SESSION['SnapinCount'] = $this->getClass('SnapinManager')->count();
        $_SESSION['PrinterCount'] = $this->getClass('PrinterManager')->count();
        $_SESSION['FOGPingActive'] = $this->getSetting('FOG_HOST_LOOKUP');
        // Set the memory limits
        $_SESSION['memory'] = $this->getSetting('FOG_MEMORY_LIMIT');
        ini_set('memory_limit',is_numeric($_SESSION[memory])?$_SESSION[memory].'M' : ini_get('memory_limit'));
        $_SESSION[chunksize]=8192;
        $_SESSION[FOG_FORMAT_FLAG_IN_GUI] = $this->getSetting(FOG_FORMAT_FLAG_IN_GUI);
        $_SESSION[FOG_SNAPINDIR] = $this->getSetting(FOG_SNAPINDIR);
        $_SESSION[FOG_REPORT_DIR] = $this->getSetting(FOG_REPORT_DIR);
        /** $TimeZone set the TimeZone based on the stored data */
        $_SESSION[TimeZone] = (ini_get('date.timezone')?ini_get('date.timezone'):$this->getSetting(FOG_TZ_INFO));
        ini_set('max_input_vars',5000);
        ini_set('upload_max_filesize',$this->getSetting(FOG_MAX_UPLOADSIZE).'M');
        ini_set('post_max_size',$this->getSetting(FOG_POST_MAXSIZE).'M');
    }
}
