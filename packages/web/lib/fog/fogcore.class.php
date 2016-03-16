<?php
class FOGCore extends FOGBase {
    public function attemptLogin($username,$password) {
        return self::getClass('User',@max($this->getSubObjectIDs('User',array('name'=>$username),'id')))->validate_pw($password);
    }
    public function stopScheduledTask($task) {
        return self::getClass('ScheduledTask',$task->get('id'))->set('isActive',(int)false)->save();
    }
    public function addUpdateMACLookupTable($macprefix) {
        $this->clearMACLookupTable();
        $macfields = '';
        foreach($macprefix AS $macpre => &$maker) {
            $macfields .= "('".$this->DB->sanitize($macpre)."','".$this->DB->sanitize($maker)."'),";
            unset($maker);
        }
        $macfields = rtrim($macfields,',');
        $OUITable = self::getClass('OUI','',true);
        $OUITable = $OUITable['databaseTable'];
        $this->DB->query("INSERT INTO `$OUITable` (`ouiMACPrefix`,`ouiMan`) VALUES $macfields");
        return $this->DB->fetch()->get();
    }
    public function clearMACLookupTable() {
        $OUITable = self::getClass('OUI','',true);
        $OUITable = $OUITable['databaseTable'];
        $this->DB->query("TRUNCATE TABLE `$OUITable`");
        return $this->DB->fetch()->get();
    }
    public function getMACLookupCount() {
        return self::getClass(OUIManager)->count();
    }
    public function resolveHostname($host) {
        if (filter_var(trim($host),FILTER_VALIDATE_IP)) return trim($host);
        return trim(gethostbyname(trim($host)));
    }
    public function makeTempFilePath() {
        return tempnam(sys_get_temp_dir(),'FOG');
    }
    public function SystemUptime() {
        $data = trim(shell_exec('uptime'));
        $tmp = explode(' load average: ', $data);
        $load = end($tmp);
        $tmp = explode(' up ',$data);
        $tmp = explode(',', end($tmp));
        $uptime = $tmp;
        $uptime = (count($uptime) > 1 ? $uptime[0] . ', ' . $uptime[1] : 'uptime not found');
        return array('uptime'=>$uptime,'load'=>$load);
    }
    public function clear_screen($outputdevice) {
        $this->out(chr(27)."[2J".chr(27)."[;H",$outputdevice);
    }
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
    public function getBroadcast() {
        $output = array();
        exec("/sbin/ip addr | awk -F'[ /]+' '/global/ {print $6}'|grep '[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}'", $IPs, $retVal);
        if (!count($IPs)) exec("/sbin/ifconfig -a | awk '/(cast)/ {print $3}' | cut -d':' -f2' | grep '[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}'", $IPs,$retVal);
        return array_values(array_unique(array_map('trim',(array)$IPs)));
    }
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
            if (!preg_match("/(\d+) +(\d+) +(\d+) +\d+%/",$n,$matches)) continue;
            $hdtotal += (int) $matches[1]*1024;
            $hdused += (int) $matches[2]*1024;
            unset($n);
        }
        unset($l);
        $data['totalspace'] = $this->formatByteSize($hdtotal);
        $data['usedspace'] = $this->formatByteSize($hdused);
        $data['nic'] = '@@nic';
        $NET = shell_exec('cat "/proc/net/dev"');
        $lines = explode("\n",$NET);
        foreach ($lines AS $i => &$line) {
            if (!preg_match('#:#',$line)) continue;
            list($dev_name,$stats_list) = preg_split('/:/',$line,2);
            $stats = preg_split('/\s+/', trim($stats_list));
            $data[$dev_name] = sprintf('%s$$%s$$%s$$%s$$%s',trim($dev_name),$stats[0],$stats[8],($stats[2]+$stats[10]),($stats[3]+$stats[11]));
            unset($line);
        }
        unset($lines);
        $data['end'] = '@@end';
        return $data;
    }
    public function setSessionEnv() {
        $_SESSION['HostCount'] = self::getClass('HostManager')->count();
        $this->DB->query("SET SESSION group_concat_max_len=(1024 * {$_SESSION['HostCount']})");
        $this->DB->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '".DATABASE_NAME."' AND ENGINE != 'MyISAM'");
        $tables = $this->DB->fetch(MYSQLI_NUM,'fetch_all')->get('TABLE_NAME');
        if (is_array($tables)) {
            foreach ((array)$tables AS $i => &$table) $this->DB->query("ALTER TABLE `".DATABASE_NAME."`.`".array_shift($table)."` ENGINE=MyISAM");
            unset($table);
            unset($tables,$table);
        }
        $_SESSION['PluginsInstalled'] = (array)$this->getActivePlugins();
        $_SESSION['FOG_VIEW_DEFAULT_SCREEN'] = $this->getSetting('FOG_VIEW_DEFAULT_SCREEN');
        $_SESSION['FOG_FTP_IMAGE_SIZE'] = $this->getSetting('FOG_FTP_IMAGE_SIZE');
        $_SESSION['Pending-Hosts'] = self::getClass('HostManager')->count(array('pending'=>1));
        $_SESSION['Pending-MACs'] = self::getClass('MACAddressAssociationManager')->count(array('pending'=>1));
        $_SESSION['DataReturn'] = $this->getSetting('FOG_DATA_RETURNED');
        $_SESSION['UserCount'] = self::getClass('UserManager')->count();
        $_SESSION['GroupCount'] = self::getClass('GroupManager')->count();
        $_SESSION['ImageCount'] = self::getClass('ImageManager')->count();
        $_SESSION['SnapinCount'] = self::getClass('SnapinManager')->count();
        $_SESSION['PrinterCount'] = self::getClass('PrinterManager')->count();
        $_SESSION['FOGPingActive'] = $this->getSetting('FOG_HOST_LOOKUP');
        $_SESSION['memory'] = $this->getSetting('FOG_MEMORY_LIMIT');
        $memorySet = preg_replace('#M#','',ini_get('memory_limit'));
        if ((int) $memorySet < $_SESSION['memory']) ini_set('memory_limit',is_numeric($_SESSION['memory']) ? $_SESSION['memory'].'M' : ini_get('memory_limit'));
        $_SESSION['FOG_FORMAT_FLAG_IN_GUI'] = $this->getSetting('FOG_FORMAT_FLAG_IN_GUI');
        $_SESSION['FOG_SNAPINDIR'] = $this->getSetting('FOG_SNAPINDIR');
        $_SESSION['FOG_REPORT_DIR'] = $this->getSetting('FOG_REPORT_DIR');
        $_SESSION['TimeZone'] = (ini_get('date.timezone') ? ini_get('date.timezone') : ($this->getSetting('FOG_TZ_INFO') ? $this->getSetting('FOG_TZ_INFO') : 'UTC'));
        ini_set('max_input_vars',5000);
    }
}
