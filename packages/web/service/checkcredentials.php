<?php
/**
 * Checks credentials for init based calls
 *
 * PHP version 5
 *
 * @category CheckCredentials
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Checks credentials for init based calls
 *
 * @category CheckCredentials
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';

$remoteIP = filter_input(INPUT_SERVER, 'REMOTE_ADDR');
$remoteIP = filter_var($remoteIP, FILTER_VALIDATE_IP) ? $remoteIP : '0.0.0.0';

$lockoutFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fog_auth_ratelimit_' . md5($remoteIP);
$maxAttempts = 5;
$lockoutDuration = 300; // 5 minutes in seconds

$getAttemptData = function () use ($lockoutFile) {
    if (!file_exists($lockoutFile)) {
        return ['attempts' => 0, 'timestamp' => time()];
    }
    $data = json_decode(@file_get_contents($lockoutFile), true);
    return is_array($data) ? $data : ['attempts' => 0, 'timestamp' => time()];
};

$recordBadAttempt = function () use ($lockoutFile, $lockoutDuration) {
    $data = ['attempts' => 0, 'timestamp' => time()];
    if (file_exists($lockoutFile)) {
        $data = json_decode(@file_get_contents($lockoutFile), true);
        $data = is_array($data) ? $data : ['attempts' => 0, 'timestamp' => time()];
        $timeDiff = time() - ($data['timestamp'] ?? time());
        if ($timeDiff < $lockoutDuration) {
            $data['attempts'] = ($data['attempts'] ?? 0) + 1;
        } else {
            $data = ['attempts' => 1, 'timestamp' => time()];
        }
    } else {
        $data['attempts'] = 1;
        $data['timestamp'] = time();
    }
    @file_put_contents($lockoutFile, json_encode($data), LOCK_EX);
};

$clearAttempts = function () use ($lockoutFile) {
    @unlink($lockoutFile);
};

$attemptData = $getAttemptData();
$timeDiff = time() - ($attemptData['timestamp'] ?? time());
$isLocked = ($attemptData['attempts'] ?? 0) >= 5 && $timeDiff < $lockoutDuration;

if ($isLocked) {
    http_response_code(429);
    echo '#!rl';
    exit;
}

try {
    $username = trim($_REQUEST['username'] ?? '');
    $username = base64_decode($username, true);
    if (!is_string($username)) {
        throw new Exception('#!il');
    }
    $username = trim($username);
    $password = trim($_REQUEST['password'] ?? '');
    $password = base64_decode($password, true);
    if (!is_string($password)) {
        throw new Exception('#!il');
    }
    $password = trim($password);
    $userTest = FOGCore::getClass('User')
        ->passwordValidate($username, $password);
    if (!$userTest) {
        $recordBadAttempt();
        throw new Exception('#!il');
    }
    $clearAttempts();
    echo '#!ok';
} catch (Exception $e) {
    if ($e->getMessage() !== '#!il') {
        $recordBadAttempt();
    }
    echo $e->getMessage();
}
