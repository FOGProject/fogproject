<?php
/**
 * Service library
 *
 * PHP version 7.4+
 *
 * @category Service_Lib
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

use FOG\Base\FOGCore;

/**
 * Service library
 *
 * @category Service_Lib
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
// #917: every daemon entry point ran @error_reporting(0), so a fatal in a
// child produced no output anywhere -- the service simply stopped doing work
// while systemd still reported it active. Report real errors instead, but keep
// display_errors off so nothing lands on stdout, where systemd would duplicate
// it into the journal. E_DEPRECATED is masked because this 7.4-era codebase
// emits enough of it to bury anything actionable. This has to run before the
// require below so that loading base.inc.php is covered too; the log
// destination is set further down, once the configured path is known.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
// #944: mysqlnd's read timeout defaults to 86400 -- a full day. If MySQL
// stops answering on an established connection (server hang, dropped session,
// a restart that leaves the socket half-open) the daemon blocks in read() for
// up to 24 hours. That happens here, during the require below, which is
// BEFORE Service_persist() forks -- so systemd reports the unit active with
// Tasks: 1, servicemaster.log stays empty because the first 'Start' line is
// written after the bootstrap, and only systemctl restart recovers it.
//
// PDO::ATTR_TIMEOUT is NOT the fix: under mysqlnd it maps to
// MYSQL_OPT_CONNECT_TIMEOUT and bounds only the TCP connect, which in this
// scenario succeeds -- measured still blocked at 40s with it set. This ini is
// the only knob that bounds the read, and it is PHP_INI_ALL so ini_set works.
//
// Set here rather than in PDODB::$_options on purpose. The web tier already
// has backstops (max_execution_time, php-fpm request_terminate_timeout) and a
// hung request surfaces an error to somebody; a hung daemon is invisible. A
// read deadline in the shared DB layer would bind every present and future
// caller to solve a problem only the daemons have. This file is required by
// all eight daemons and by nothing else.
//
// The value is hard-coded because the timeout exists in order to bound
// reading the database -- it cannot itself be read from a globalSetting. 300s
// is far above any real FOG daemon query and far below a day. When it trips,
// PDO raises 'MySQL server has gone away', which the supervisor logs and
// recovers from by re-forking (#917).
ini_set('mysqlnd.net_read_timeout', '300');
/*
 * A machine entry point: a daemon runs with no signed-in user and cannot
 * acquire one. Declared here rather than in each daemon because this file
 * is what every one of them requires -- see Authorization::_hasNoPrincipal()
 * for what it licenses and why the distinction matters.
 */
define('FOG_MACHINE_REQUEST', true);
require WEBROOT.'/commons/base.inc.php';
$service_logpath = sprintf(
    '/%s/%s',
    trim(FOGCore::getSetting('SERVICE_LOG_PATH'), '/'),
    FOGCore::getSetting('SERVICEMASTERLOGFILENAME')
);
if (!is_file($service_logpath)) {
    $service_logpath = FOG_LOG_DIR . DS . 'servicemaster.log';
}
$service_sleep_time = (int)FOGCore::getSetting('SERVICESLEEPTIME');
if (!$service_sleep_time) {
    $service_sleep_time = 10;
}
$service_child_pid = 0;
// Tag used on error-log lines. The entry point sets $service_name only after
// this file is required, and errors can happen before that, so fall back to
// the script name and let Service_persist() refine it once it knows.
$service_name_tag = basename(isset($argv[0]) ? $argv[0] : 'FOGService');
// Now that the configured path is resolved, send PHP's own error output to
// the same file the service writes to, so there is one place to look.
ini_set('error_log', $service_logpath);
// PHP's own log line carries no pid and no service name, and all eight
// daemons share servicemaster.log -- so a fatal additionally gets an
// attributed line naming the daemon and the process that died. The overlap is
// deliberate: PHP's line has the file and line number, this one has identity.
register_shutdown_function('Service_Fatal_handler');
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
    $msg = sprintf(
        "[%s] %s %s\n",
        FOGCore::formatTime('now', 'm-d-y g:i:s a'),
        $name,
        $msg
    );
    // An unwritable log path used to be fatal: fopen() returns false and on
    // PHP 8 fwrite(false, ...) throws an uncaught TypeError, killing the
    // daemon from inside the very routine meant to explain what went wrong --
    // and under the old @error_reporting(0) it died without a word (#917).
    // Fall back to error_log() so a bad path degrades to journald instead.
    $logfile = @fopen($logpath, 'a');
    if ($logfile === false) {
        error_log(rtrim($msg));
        return;
    }
    fwrite($logfile, $msg);
    fflush($logfile);
    fclose($logfile);
}
/**
 * Records a fatal error against the daemon and process it killed.
 *
 * Runs on every shutdown; only a fatal produces output, so a clean exit
 * stays silent. See the register_shutdown_function() call above for why
 * this exists alongside PHP's own error_log line (#917).
 *
 * @return void
 */
function Service_Fatal_handler()
{
    global $service_logpath;
    global $service_name_tag;
    $error = error_get_last();
    if (!$error) {
        return;
    }
    $fatal = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;
    if (!($error['type'] & $fatal)) {
        return;
    }
    Service_Log_message(
        $service_logpath,
        $service_name_tag,
        '('.posix_getpid().') died on a fatal error: '
        . $error['message']
        . ' in ' . $error['file'] . ' on line ' . $error['line'] . '.'
    );
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
    // SIGCHLD is deliberately left at its default disposition. Setting it
    // to SIG_IGN makes the kernel auto-reap children, which is mutually
    // exclusive with the pcntl_waitpid() call in Service_persist(): the
    // child is collected before we can wait on it, so waitpid() only ever
    // returns -1/ECHILD and the re-fork path below it is dead code (#917).
    // Zombies are prevented here by that waitpid(), not by ignoring SIGCHLD.
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
    pcntl_signal(SIGCHLD, SIG_DFL);
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
    global $service_name_tag;
    $service_child_pid = 0;
    $service_name_tag = $service_name;
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
                    // The child is unwaitable -- in practice ECHILD, meaning
                    // it is already gone and its status was collected by
                    // somebody else. That is a reason to fork a replacement,
                    // not to kill the supervisor; exiting here is what left a
                    // dying child with no log line and no restart (#917).
                    Service_Log_message(
                        $service_logpath,
                        $service_name,
                        "cannot wait on child process ($service_child_pid): "
                        . pcntl_strerror(pcntl_get_last_error())
                        . '. Assuming it is gone and re-forking.'
                    );
                    break;
                }
            }
            // $status only carries a real wait status when we actually
            // reaped; on the ECHILD break above it is still 0 and would
            // otherwise be misreported as a clean "exited with code 0".
            if ($reaped_pid > 0) {
                if (pcntl_wifexited($status)) {
                    $code = pcntl_wexitstatus($status);
                    Service_Log_message(
                        $service_logpath,
                        $service_name,
                        "child process ($service_child_pid) "
                        . "exited with code $code."
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
