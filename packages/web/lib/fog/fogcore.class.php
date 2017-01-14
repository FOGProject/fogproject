<?php
/**
 * The core elements accessible for all else
 *
 * PHP version 5
 *
 * @category FOGCore
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The core elements accessible for all else
 *
 * @category FOGCore
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class FOGCore extends FOGBase
{
    /**
     * Attempts to login
     *
     * @param string $username the username to attempt
     * @param string $password the password to attempt
     *
     * @return object
     */
    public function attemptLogin($username, $password)
    {
        return self::getClass('User')
            ->validatePw($username, $password);
    }
    /**
     * Clears the mac lookup table
     *
     * @return bool
     */
    public function clearMACLookupTable()
    {
        $OUITable = self::getClass('OUI', '', true);
        $OUITable = $OUITable['databaseTable'];
        return self::$DB->query("TRUNCATE TABLE `$OUITable`");
    }
    /**
     * Returns the count of mac lookups
     *
     * @return int
     */
    public function getMACLookupCount()
    {
        return self::getClass('OUIManager')->count();
    }
    /**
     * Resolves a hostname to its IP address
     *
     * @param string $host the item to test
     *
     * @return string
     */
    public function resolveHostname($host)
    {
        $host = trim($host);
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }
        $host = gethostbyname($host);
        $host = trim($host);
        return $host;
    }
    /**
     * Returns the systems uptime
     *
     * @return array
     */
    public function systemUptime()
    {
        exec('cat /proc/uptime', $data);
        $uptime = explode(' ', $data[0]);
        $idletime = $uptime[1];
        $uptime = (float)$uptime[0];
        $day = 86400;
        $days = floor($uptime/$day);
        $uptimestr = sprintf(
            'Up: %d %s%s ',
            $days,
            _('day'),
            $days != 1 ? 's' : ''
        );
        $utdelta = $uptime - ($days * $day);
        $hour = 3600;
        $hours = floor($utdelta/$hour);
        $uptimestr .= sprintf(
            '%d %s%s ',
            $hours,
            _('hr'),
            $hours != 1 ? 's' : ''
        );
        $utdelta -= $hours*$hour;
        $minute = 60;
        $minutes = floor($utdelta/$minute);
        $uptimestr .= sprintf(
            '%d %s%s',
            $minutes,
            _('min'),
            $minutes != 1 ? 's' : ''
        );
        $uptime = $uptimestr;
        $loadAvg = sys_getloadavg();
        $load = sprintf(
            '%.2f, %.2f, %.2f',
            $loadAvg[0],
            $loadAvg[1],
            $loadAvg[2]
        );
        return array(
            'uptime' => $uptime,
            'load' => $load
        );
    }
    /**
     * Gets the broadcast address of the server
     *
     * @return array
     */
    public function getBroadcast()
    {
        $output = array();
        $cmd = sprintf(
            '%s | %s | %s',
            '/sbin/ip -4 addr',
            "awk -F'[ /]+' '/global/ {print $6}'",
            "grep '[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}'"
        );
        exec($cmd, $IPs, $retVal);
        if (!count($IPs)) {
            $cmd = sprintf(
                '%s | %s | %s | %s',
                '/sbin/ifconfig -a',
                "awk '/(cast)/ {print $3}'",
                "cut -d':' -f2",
                "grep '[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}'"
            );
            exec($cmd, $IPs, $retVal);
        }
        $IPs = array_map('trim', (array)$IPs);
        $IPs = array_filter($IPs);
        $IPs = array_values($IPs);
        return $IPs;
    }
    /**
     * Gets the hardware information of the selected item
     *
     * @return array
     */
    public function getHWInfo()
    {
        $data['general'] = '@@general';
        $data['kernel'] = php_uname('r');
        $data['hostname'] = php_uname('n');
        $data['uptimeload'] = implode(' Load: ', $this->systemUptime());
        $cpucmd = sprintf(
            '%s | %s | %s | %s | %s',
            'cat /proc/cpuinfo',
            'head -n%d',
            'tail -n1',
            'cut -f2 -d:',
            "sed 's| ||'"
        );
        $data['cputype'] = shell_exec(sprintf($cpucmd, 2));
        $data['cpucount'] = shell_exec('nproc');
        $data['cpumodel'] = shell_exec(sprintf($cpucmd, 5));
        $data['cpuspeed'] = shell_exec(sprintf($cpucmd, 8));
        $data['cpucache'] = shell_exec(sprintf($cpucmd, 9));
        $memcmd = sprintf(
            '%s | %s | %s | %s',
            'free -b',
            'head -n2',
            'tail -n1',
            "awk '{print \$%d}'"
        );
        $totmem = shell_exec(sprintf($memcmd, 2));
        $usedmem = shell_exec(sprintf($memcmd, 3));
        $freemem = shell_exec(sprintf($memcmd, 4));
        $data['totmem'] = $this->formatByteSize($totmem);
        $data['usedmem'] = $this->formatByteSize($usedmem);
        $data['freemem'] = $this->formatByteSize($freemem);
        $data['filesys'] = '@@fs';
        $hdtotal = 0;
        $hdused = 0;
        $freespace = explode(
            "\n",
            shell_exec('df -PB1 | grep -vE "^Filesystem|shm"')
        );
        $patmatch = '/(\d+) +(\d+) +(\d+) +\d+%/';
        $hdtotal = $hdused = $hdfree = 0;
        foreach ((array)$freespace as &$free) {
            if (!preg_match($patmatch, $free, $matches)) {
                continue;
            }
            $hdtotal += (float)$matches[1];
            $hdused += (float)$matches[2];
            unset($free);
        }
        $hdfree = (float)$hdtotal - $hdused;
        $data['totalspace'] = $this->formatByteSize($hdtotal);
        $data['usedspace'] = $this->formatByteSize($hdused);
        $data['freespace'] = $this->formatByteSize($hdfree);
        $data['nic'] = '@@nic';
        $netfaces = explode(
            "\n",
            shell_exec("cat '/proc/net/dev'")
        );
        foreach ((array)$netfaces as $netface) {
            if (!preg_match('#:#', $netface)) {
                continue;
            }
            list(
                $dev_name,
                $stats_list
            ) = preg_split('/:/', $netface, 2);
            $stats_list = trim($stats_list);
            $stats = preg_split(
                '/\s+/',
                $stats_list
            );
            $dev_name = trim($dev_name);
            $data[$dev_name] = sprintf(
                '%s$$%s$$%s$$%s$$%s',
                $dev_name,
                $stats[0],
                $stats[8],
                ($stats[2] + $stats[10]),
                ($stats[3] + $stats[11])
            );
            unset($netface);
        }
        $data['end'] = '@@end';
        return $data;
    }
    /**
     * Sets the session environment for us
     *
     * @return void
     */
    public static function setSessionEnv()
    {
        $_SESSION['PluginsInstalled'] = (array)self::getActivePlugins();
        $getSettings = array(
            'FOG_FORMAT_FLAG_IN_GUI',
            'FOG_FTP_IMAGE_SIZE',
            'FOG_HOST_LOOKUP',
            'FOG_MEMORY_LIMIT',
            'FOG_REPORT_DIR',
            'FOG_SNAPINDIR',
            'FOG_TZ_INFO',
            'FOG_VIEW_DEFAULT_SCREEN'
        );
        list(
            $formatFlag,
            $ftpImage,
            $hostLookup,
            $memoryLimit,
            $reportDir,
            $snapinDir,
            $tzInfo,
            $view
        ) = self::getSubObjectIDs(
            'Service',
            array('name' => $getSettings),
            'value',
            false,
            'AND',
            'name',
            false,
            ''
        );
        $_SESSION['FOG_VIEW_DEFAULT_SCREEN'] = $view;
        $_SESSION['FOG_FTP_IMAGE_SIZE'] = $ftpImage;
        $_SESSION['DataReturn'] = $dataReturn;
        $_SESSION['FOGPingActive'] = $hostLookup;
        $_SESSION['memory'] = $memoryLimit;
        $_SESSION['FOG_SNAPINDIR'] = $snapinDir;
        $_SESSION['FOG_REPORT_DIR'] = $reportDir;
        $defTz = ini_get('date.timezone');
        $_SESSION['FOG_FORMAT_FLAG_IN_GUI'] = $formatFlag;
        if (empty($defTz)) {
            if (empty($tzInfo)) {
                $_SESSION['TimeZone'] = 'UTC';
            } else {
                $_SESSION['TimeZone'] = $tzInfo;
            }
        } else {
            $_SESSION['TimeZone'] = $defTz;
        }
        ini_set('max_input_vars', 10000);
        $_SESSION['Pending-Hosts'] = self::getClass('HostManager')
            ->count(array('pending' => 1));
        if (self::$DB->getColumns('hostMAC', 'hmMAC') > 0) {
            $_SESSION['Pending-MACs'] = self::getClass(
                'MACAddressAssociationManager'
            )->count(array('pending' => 1));
        }
        $memorySet = preg_replace('#M#', '', ini_get('memory_limit'));
        if ($memorySet < $_SESSION['memory']) {
            if (is_numeric($_SESSION['memory'])) {
                ini_set('memory_limit', sprintf('%dM', $_SESSION['memory']));
            }
        }
        $_SESSION['SESS_DONE'] = true;
        return self::getClass(__CLASS__);
    }
}
