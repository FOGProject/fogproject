<?php
require_once(WEBROOT.'/commons/init.php');
$service_logpath = sprintf('/%s/%s',trim($FOGCore->getSetting('SERVICE_LOG_PATH'),'/'),$FOGCore->getSetting('SERVICEMASTERLOGFILENAME'));
$service_sleep_time = (int)$FOGCore->getSetting('SERVICESLEEPTIME');
$service_child_pid = 0;
function service_log_message($logpath, $name, $msg) {
    global $FOGCore;
    $logfile = fopen($logpath, "a");
    fwrite($logfile,'['.$FOGCore->formatTime('now','m-d-y g:i:s a')."] $name $msg\n");
    fflush($logfile);
    fclose($logfile);
}
declare(ticks = 1);
function service_signal_handler($signo) {
    global $service_child_pid;
    global $service_logpath;
    service_log_message($service_logpath, 'service_signal_handler', '('.posix_getpid().") received signal $signo.");
    if($service_child_pid > 0) {
        service_log_message($service_logpath, 'service_signal_handler', '('.posix_getpid().") killing child ($service_child_pid).");
        posix_kill($service_child_pid, SIGTERM);
        $service_child_pid = 0;
    }
    service_log_message($service_logpath, 'service_signal_handler', '('.posix_getpid().') exiting.');
    exit(0);
}
function service_register_signal_handler() {
    pcntl_signal(SIGHUP,'service_signal_handler');
    pcntl_signal(SIGINT,'service_signal_handler');
    pcntl_signal(SIGQUIT,'service_signal_handler');
    pcntl_signal(SIGTERM,'service_signal_handler');
}
function service_unregister_signal_handler() {
    pcntl_signal(SIGHUP,SIG_DFL);
    pcntl_signal(SIGINT,SIG_DFL);
    pcntl_signal(SIGQUIT,SIG_DFL);
    pcntl_signal(SIGTERM,SIG_DFL);
}
function service_persist($service_name) {
    global $service_logpath;
    global $service_child_pid;
    global $service_sleep_time;
    $service_child_pid = 0;
    service_log_message($service_logpath, $service_name, 'Start');
    service_register_signal_handler();
    for(;;) {
        $service_child_pid = pcntl_fork();
        if($service_child_pid < 0) {
            service_log_message($service_logpath, $service_name, 'Unable to fork() child process.');
            exit(1);
        } else if($service_child_pid > 0) {
            service_log_message($service_logpath, $service_name, "fork()ed child process ($service_child_pid).");
            while(true) {
                $status = 0;
                $reaped_pid = pcntl_waitpid($service_child_pid, $status, WNOHANG);
                if($reaped_pid == 0) sleep($service_sleep_time);
                else if($reaped_pid > 0) break;
                else {
                    service_log_message($service_logpath, $service_name, 'pnctl_waitpid() failed.');
                    exit(1);
                }
            }
            if(pcntl_wifexited($status)) {
                $code = pcntl_wexitstatus($status);
                service_log_message($service_logpath, $service_name, "child process ($service_child_pid) exited with code $code.");
            } else if(pcntl_wifsignaled($status)) {
                $sigcode = pcntl_wtermsig($status);
                service_log_message($service_logpath, $service_name, "child process ($service_child_pid) exited due to signal $sigcode.");
            } else {
                service_log_message($service_logpath, $service_name, "child process ($service_child_pid) stopped for unknown reason.");
            }
            $service_child_pid = 0;
            sleep((int)$service_sleep_time);
        } else if ($service_child_pid == 0) {
            service_unregister_signal_handler();
            service_log_message($service_logpath, $service_name, 'child process ('.posix_getpid().') is running.');
            return;
        }
    }
    service_log_message($service_logpath, $service_name, 'Parent process ('.posix_getpid().') reached end of loop.');
    exit(0);
}
