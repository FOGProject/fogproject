<?php
/**
 * Service library
 *
 * PHP version 5
 *
 * @category Service_Lib
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Service library
 *
 * @category Service_Lib
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require WEBROOT.'/commons/base.inc.php';
$service_logpath = sprintf(
    '/%s/%s',
    trim(FOGCore::getSetting('SERVICE_LOG_PATH'), '/'),
    FOGCore::getSetting('SERVICEMASTERLOGFILENAME')
);
if (!is_file($service_logpath)) {
    $service_logpath = '/opt/fog/log/servicemaster.log';
}
$service_sleep_time = (int)FOGCore::getSetting('SERVICESLEEPTIME');
if (!$service_sleep_time) {
    $service_sleep_time = 10;
}
$service_child_pid = 0;
/**
 * Sends the service log messages
 *
 * @param string $logpath the path to log
 * @param string $name    the name of the service
 * @param string $msg     the message to log
 *
 * @return void
 */
function Service_Log_message($logpath, $name, $msg)
{
    $logfile = fopen($logpath, "a");
    $msg = sprintf(
        "[%s] %s %s\n",
        FOGCore::formatTime('now', 'm-d-y g:i:s a'),
        $name,
        $msg
    );
    fwrite($logfile, $msg);
    fflush($logfile);
    fclose($logfile);
}
declare (ticks = 1);
/**
 * Signal handler
 *
 * @param mixed $signo the signal number
 *
 * @return void
 */
function Service_Signal_handler($signo)
{
    global $service_child_pid;
    global $service_logpath;
    Service_Log_message(
        $service_logpath,
        'Service_Signal_handler',
        '('.posix_getpid().") received signal $signo."
    );
    if ($service_child_pid > 0) {
        Service_Log_message(
            $service_logpath,
            'Service_Signal_handler',
            '('.posix_getpid().") killing child ($service_child_pid)."
        );
        posix_kill($service_child_pid, SIGTERM);
        $service_child_pid = 0;
    }
    Service_Log_message(
        $service_logpath,
        'Service_Signal_handler',
        '('.posix_getpid().') exiting.'
    );
    exit(0);
}
/**
 * Registers signal handler
 *
 * @return void
 */
function Service_Register_Signal_handler()
{
    pcntl_signal(SIGHUP, 'Service_Signal_handler');
    pcntl_signal(SIGINT, 'Service_Signal_handler');
    pcntl_signal(SIGQUIT, 'Service_Signal_handler');
    pcntl_signal(SIGTERM, 'Service_Signal_handler');
}
/**
 * Unregisters signal handler
 *
 * @return void
 */
function Service_Unregister_Signal_handler()
{
    pcntl_signal(SIGHUP, SIG_DFL);
    pcntl_signal(SIGINT, SIG_DFL);
    pcntl_signal(SIGQUIT, SIG_DFL);
    pcntl_signal(SIGTERM, SIG_DFL);
}
/**
 * Persists the service
 *
 * @param string $service_name the service to persist
 *
 * @return void
 */
function Service_persist($service_name)
{
    global $service_logpath;
    global $service_child_pid;
    global $service_sleep_time;
    $service_child_pid = 0;
    Service_Log_message($service_logpath, $service_name, 'Start');
    Service_Register_Signal_handler();
    for (;;) {
        $service_child_pid = pcntl_fork();
        if ($service_child_pid < 0) {
            Service_Log_message(
                $service_logpath,
                $service_name,
                'Unable to fork child process.'
            );
            exit(1);
        } elseif ($service_child_pid > 0) {
            Service_Log_message(
                $service_logpath,
                $service_name,
                "forked child process ($service_child_pid)."
            );
            while (true) {
                $status = 0;
                $reaped_pid = pcntl_waitpid(
                    $service_child_pid,
                    $status,
                    WNOHANG
                );
                if ($reaped_pid == 0) {
                    sleep($service_sleep_time);
                } elseif ($reaped_pid > 0) {
                    break;
                } else {
                    Service_Log_message(
                        $service_logpath,
                        $service_name,
                        'pnctl_waitpid() failed.'
                    );
                    exit(1);
                }
                sleep($service_sleep_time);
            }
            if (pcntl_wifexited($status)) {
                $code = pcntl_wexitstatus($status);
                Service_Log_message(
                    $service_logpath,
                    $service_name,
                    "child process ($service_child_pid) exited with code $code."
                );
            } elseif (pcntl_wifsignaled($status)) {
                $sigcode = pcntl_wtermsig($status);
                Service_Log_message(
                    $service_logpath,
                    $service_name,
                    "child process ($service_child_pid) exited "
                    . "due to signal $sigcode."
                );
            } else {
                Service_Log_message(
                    $service_logpath,
                    $service_name,
                    "child process ($service_child_pid) "
                    . "stopped for unknown reason."
                );
            }
            $service_child_pid = 0;
        } elseif ($service_child_pid == 0) {
            Service_Unregister_Signal_handler();
            Service_Log_message(
                $service_logpath,
                $service_name,
                'child process ('.posix_getpid().') is running.'
            );
            return;
        }
        sleep($service_sleep_time);
    }
    Service_Log_message(
        $service_logpath,
        $service_name,
        'Parent process ('.posix_getpid().') reached end of loop.'
    );
    exit(0);
}
