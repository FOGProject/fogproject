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
     * Returns the systems uptime
     *
     * @return array
     */
    public static function systemUptime()
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
        if (stristr(PHP_OS, 'win')) {
            $cmd = 'wmic cpu get loadpercentage /all';
            exec($cmd, $output);
            if ($output) {
                foreach ($output as &$line) {
                    if ($line && preg_match('/^[0-9]+$/', $line)) {
                        $loadAvg = $line;
                        break;
                    }
                }
            }
            $load = sprintf(
                '%s%% (%s)',
                $loadAvg,
                _('Running Windows')
            );
        } else {
            if (function_exists('sys_getloadavg')) {
                $loadAvg = sys_getloadavg();
                $load = sprintf(
                    '%.2f, %.2f, %.2f',
                    $loadAvg[0],
                    $loadAvg[1],
                    $loadAvg[2]
                );
            } else {
                $load = _('Unavailable');
            }
        }
        return [
            'uptime' => $uptime,
            'load' => $load
        ];
    }
    /**
     * Gets the hardware information of the selected item
     *
     * @return array
     */
    public static function getHWInfo()
    {
        $cpucmd = sprintf(
            '%s | %s | %s | %s | %s',
            'cat /proc/cpuinfo',
            'head -n%d',
            'tail -n1',
            'cut -f2 -d:',
            "sed 's| ||'"
        );
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
        $data['general'] = [
            'kernel' => php_uname('r'),
            'hostname' => php_uname('n'),
            'uptimeload' => implode(' Load: ', self::systemUptime()),
            'cputype' => shell_exec(sprintf($cpucmd, 2)),
            'cpucount' => shell_exec('nproc'),
            'cpumodel' => shell_exec(sprintf($cpucmd, 5)),
            'cpuspeed' => shell_exec(sprintf($cpucmd, 8)),
            'cpucache' => shell_exec(sprintf($cpucmd, 9)),
            'totmem' => self::formatByteSize($totmem),
            'usedmem' => self::formatByteSize($usedmem),
            'freemem' => self::formatByteSize($freemem),
            'fogversion' => FOG_VERSION,
        ];
        $hdtotal = 0;
        $hdused = 0;
        $freespace = explode(
            "\n",
            shell_exec('df -PB1 | grep -vE "^Filesystem|shm" | awk \'!filesystem[$1]++\'')
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
        $data['filesys'] = [
            'totalspace' => self::formatByteSize($hdtotal),
            'usedspace' => self::formatByteSize($hdused),
            'freespace' => self::formatByteSize($hdfree)
        ];
        $netfaces = explode(
            "\n",
            shell_exec("cat '/proc/net/dev'")
        );
        $data['nic'] = [];
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
            $data['nic'][$dev_name] = sprintf(
                '%s$$%s$$%s$$%s$$%s',
                $dev_name,
                $stats[0],
                $stats[8],
                ($stats[2] + $stats[10]),
                ($stats[3] + $stats[11])
            );
            unset($netface);
        }
        print json_encode($data);
        exit;
    }
    /**
     * Sets the environment for us
     *
     * @return void
     */
    public static function setEnv()
    {
        self::$pluginsinstalled = (array)self::getActivePlugins();
        $getSettings = [
            'FOG_HOST_LOOKUP',
            'FOG_MEMORY_LIMIT',
            'FOG_REAUTH_ON_DELETE',
            'FOG_REAUTH_ON_EXPORT',
            'FOG_TZ_INFO',
            'FOG_VIEW_DEFAULT_SCREEN',
        ];
        list(
            $hostLookup,
            $memoryLimit,
            $authdelete,
            $authexport,
            $tzInfo,
            $view
        ) = self::getSetting($getSettings);
        self::$defaultscreen = $view;
        self::$fogpingactive = $hostLookup;
        self::$fogdeleteactive = $authdelete;
        self::$fogexportactive = $authexport;
        $defTz = ini_get('date.timezone');
        if (empty($defTz)) {
            if (empty($tzInfo)) {
                $GLOBALS['TimeZone'] = 'UTC';
            } else {
                $GLOBALS['TimeZone'] = $tzInfo;
            }
        } else {
            $GLOBALS['TimeZone'] = $defTz;
        }
        ini_set('max_input_vars', 10000);
        $memorySet = preg_replace('#M#', '', ini_get('memory_limit'));
        if ($memorySet < $memoryLimit) {
            if (is_numeric($memoryLimit)) {
                ini_set('memory_limit', sprintf('%dM', $memoryLimit));
            }
        }
        return self::getClass(__CLASS__);
    }
}
