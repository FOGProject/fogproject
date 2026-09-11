<?php
/**
 * Checks credentials for init based calls
 *
 * PHP version 7.4+
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

/**
 * 200 with the '#!rl' body, not a 429.
 *
 * This endpoint speaks a body-level protocol: '#!ok', '#!il' and '#!rl' are
 * the whole vocabulary, and the rate-limit case was the only one that also
 * moved the status code. FOS reads it with `curl -Lks` and matches on the
 * body, so the 429 bought nothing there and would have cost the body
 * outright under a fetcher that treats 4xx as "no output".
 *
 * Nothing else calls this endpoint, and nothing keys on the status.
 *
 * Refs https://github.com/FOGProject/fogproject/issues/890
 */
if ($isLocked) {
    echo '#!rl';
    exit;
}

/**
 * Read through filter_input rather than $_REQUEST.
 *
 * Initiator::sanitizeItems() walks $_GET, $_POST, $_COOKIE and $_SESSION --
 * but not $_REQUEST, which PHP populates as a separate copy before any of
 * that runs. Reading $_REQUEST therefore bypassed the boot-time
 * sanitisation and made this the one entry point taking raw superglobal
 * input, against the convention every other caller follows.
 *
 * POST is preferred over GET to match what $_REQUEST resolved to under
 * PHP's default request_order of "GP", where POST overwrites GET.
 *
 * Refs https://github.com/FOGProject/fogproject/issues/890
 */
$readParam = function ($name) {
    $value = filter_input(INPUT_POST, $name)
        ?? filter_input(INPUT_GET, $name)
        ?? '';
    return trim((string)$value);
};

try {
    /*
     * Through FOGBase::decodeCredential(), which is this decode lifted out so
     * Registration::_fullReg() can share it. The two validate the SAME
     * credential for the SAME caller, and them disagreeing was the bug:
     * _fullReg() ran the value through stripAndDecode(), whose closing
     * Initiator::e() HTML-escapes it, so this endpoint answered '#!ok' for a
     * password that registration then rejected as "Invalid Login".
     * Forums topic 18228.
     *
     * The false-is-not-base64 arm is kept exactly as it was, throwing before
     * $recordBadAttempt(), so a malformed field still does not count toward
     * the lockout.
     */
    $username = FOGCore::decodeCredential($readParam('username'));
    if (false === $username) {
        throw new Exception('#!il');
    }
    $password = FOGCore::decodeCredential($readParam('password'));
    if (false === $password) {
        throw new Exception('#!il');
    }
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
