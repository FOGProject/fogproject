<?php
/** Japanese renderer and real AD form POST against a disposable local HTTP/DB fixture. */
if (!getenv('MJ1_DB_DSN')) { echo "SKIP UI save: no scratch DB\n"; exit(0); }
if (getenv('MJ1_DB_ALLOW_RESET') !== '1') { throw new \RuntimeException('scratch reset opt-in required'); }
$pdo = new \PDO(getenv('MJ1_DB_DSN'), getenv('MJ1_DB_USER'), getenv('MJ1_DB_PASSWORD'), [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
if (strpos($pdo->query('SELECT DATABASE()')->fetchColumn(), 'fog_mj1') !== 0) { throw new \RuntimeException('scratch DB required'); }
$pdo->exec('CREATE DATABASE IF NOT EXISTS fog_mj1_ui');
$pdo->exec('USE fog_mj1_ui');
$pdo->exec('DROP TABLE IF EXISTS hostDirectory');
$pdo->exec('DROP TABLE IF EXISTS hosts');
putenv('MJ1_DB_DSN=mysql:host=mj1-db;dbname=fog_mj1_ui;charset=utf8mb4');
$root = dirname(__DIR__);
putenv('LANG=ja_JP.UTF-8');
putenv('LANGUAGE=ja_JP.UTF-8');
$port = random_int(19000, 25000);
$log = tempnam(sys_get_temp_dir(), 'fog-ui-');
$cookie = tempnam(sys_get_temp_dir(), 'fog-ui-cookie-');
$process = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root], [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes);
if (!is_resource($process)) { throw new \RuntimeException('UI server did not start'); }
try {
    $url = 'http://127.0.0.1:' . $port . '/tests/fixtures/directory-operation-ui.php';
    $request = static function ($post = null) use ($url, $cookie) {
        $c = curl_init($url);
        curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
            CURLOPT_COOKIEFILE => $cookie, CURLOPT_COOKIEJAR => $cookie]);
        if ($post !== null) { curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($post)); }
        $body = curl_exec($c); $status = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
        return [$status, $body];
    };
    for ($i = 0; $i < 20; $i++) {
        list($status, $html) = $request();
        if ($status === 200) { break; }
        usleep(100000);
    }
    if ($status !== 200 || strpos($html, 'ドメイン参加状態') === false) {
        throw new \RuntimeException('Japanese AD form did not render: ' . $status . '; ' . file_get_contents($log));
    }
    if (!preg_match('/<input\b[^>]*\bname="rejoin_allow_create"[^>]*>/', $html, $checkbox)
        || strpos($checkbox[0], 'form-control') !== false || strpos($checkbox[0], 'form-check-input') === false) {
        throw new \RuntimeException('checkbox does not use the existing FOG structure');
    }
    foreach ([false, true] as $allow) {
        if (!preg_match('/name="_csrf"\s+value="([^"]+)"/', $html, $token)) { throw new \RuntimeException('CSRF token missing'); }
        $post = ['_csrf' => html_entity_decode($token[1], ENT_QUOTES, 'UTF-8'), 'domain' => '1',
            'domainname' => 'corp.example', 'domainuser' => 'fixture-user', 'rejoin_settings_present' => '1'];
        if ($allow) { $post['rejoin_allow_create'] = '1'; }
        list($status, $html) = $request($post);
        if ($status !== 200) { throw new \RuntimeException('AD form POST failed: ' . $status); }
        $saved = json_decode($pdo->query('SELECT hdRejoinConfig FROM hostDirectory WHERE hdHostID=701')->fetchColumn(), true);
        if (!is_array($saved) || ($saved['allow_create'] ?? null) !== $allow) { throw new \RuntimeException('AD form permission save did not persist'); }
    }
    echo "PASS Japanese AD form render, checkbox structure, CSRF-backed OFF/ON saves\n";

    $readDirectory = static function () use ($pdo) {
        $row = $pdo->query('SELECT hdRejoinConfig, hdRejoinState FROM hostDirectory WHERE hdHostID=701')->fetch(\PDO::FETCH_ASSOC);
        return [
            json_decode($row['hdRejoinConfig'] ?? '', true) ?: [],
            json_decode($row['hdRejoinState'] ?? '', true) ?: []
        ];
    };
    foreach (['failed', 'unknown'] as $phase) {
        $initialConfig = [
            'version' => 1,
            'protocol' => 2,
            'allow_create' => false,
            'name' => 'UI-SCRATCH',
            'domain' => 'corp.example',
            'config_revision' => str_repeat('c', 32)
        ];
        $initialState = [
            'version' => 1,
            'protocol' => 2,
            'phase' => $phase,
            'attempt_id' => str_repeat('a', 32),
            'placement_hold' => true,
            'last_deploy_task_id' => 73
        ];
        $seed = $pdo->prepare('UPDATE hostDirectory SET hdRejoinConfig=?, hdRejoinState=? WHERE hdHostID=701');
        $seed->execute([json_encode($initialConfig), json_encode($initialState)]);

        list($status, $html) = $request();
        if ($status !== 200 || !preg_match('/name="_csrf"\s+value="([^"]+)"/', $html, $token)) {
            throw new \RuntimeException('Protected-state form or CSRF token missing for ' . $phase . ': ' . $status);
        }
        if (false !== strpos($html, 'name="rejoin_retry"')) {
            throw new \RuntimeException('Protected ' . $phase . ' state rendered a Retry control');
        }
        $validToken = html_entity_decode($token[1], ENT_QUOTES, 'UTF-8');
        $basePost = [
            'domain' => '1',
            'domainname' => 'corp.example',
            'domainuser' => 'fixture-user',
            'rejoin_settings_present' => '1'
        ];

        $badPost = $basePost;
        $badPost['_csrf'] = $validToken . '-invalid';
        $badPost['rejoin_retry'] = '1';
        list($status) = $request($badPost);
        list($savedConfig, $savedState) = $readDirectory();
        if ($status !== 403 || $savedConfig !== $initialConfig || $savedState !== $initialState) {
            throw new \RuntimeException('Invalid CSRF changed ' . $phase . ' Retry state');
        }

        list($status, $html) = $request();
        if (200 !== $status || !preg_match('/name="_csrf"\s+value="([^"]+)"/', $html, $token)) {
            throw new \RuntimeException('Fresh CSRF token missing before retry-less POST for ' . $phase);
        }
        $noRetryPost = $basePost;
        $noRetryPost['_csrf'] = html_entity_decode($token[1], ENT_QUOTES, 'UTF-8');
        list($status, $body) = $request($noRetryPost);
        list($savedConfig, $savedState) = $readDirectory();
        if (200 !== $status || false === strpos((string)$body, 'rejoin_attempt_requires_review')
            || $savedConfig !== $initialConfig || $savedState !== $initialState) {
            throw new \RuntimeException(sprintf(
                'Retry-less POST did not reject %s without changing config/state: HTTP %d, body=%s',
                $phase, $status, substr(strip_tags((string)$body), 0, 500)
            ));
        }

        list($status, $html) = $request();
        if (200 !== $status || !preg_match('/name="_csrf"\s+value="([^"]+)"/', $html, $token)) {
            throw new \RuntimeException('Fresh CSRF token missing before explicit Retry POST for ' . $phase);
        }
        $retryPost = $basePost;
        $retryPost['_csrf'] = html_entity_decode($token[1], ENT_QUOTES, 'UTF-8');
        $retryPost['rejoin_retry'] = '1';
        list($status, $body) = $request($retryPost);
        list($savedConfig, $savedState) = $readDirectory();
        if (200 !== $status || false === strpos((string)$body, 'rejoin_attempt_requires_review')
            || $savedConfig !== $initialConfig || $savedState !== $initialState) {
            throw new \RuntimeException(sprintf(
                'Raw explicit Retry did not reject protected %s state unchanged: HTTP %d, body=%s',
                $phase, $status, substr(strip_tags((string)$body), 0, 500)
            ));
        }
    }
    echo "PASS real hostADPost rejects protected-state Retry with invalid/valid CSRF and preserves config/state\n";

    $verifiedConfig = [
        'version' => 1,
        'protocol' => 2,
        'allow_create' => true,
        'name' => 'UI-SCRATCH',
        'domain' => 'corp.example',
        'config_revision' => str_repeat('d', 32),
        'target' => [
            'guid' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'sid' => 'S-1-5-21-1-701',
            'account' => 'UI-SCRATCH$',
            'dn' => 'CN=UI-SCRATCH,OU=Computers,DC=corp,DC=example'
        ]
    ];
    $verifiedState = [
        'version' => 1,
        'protocol' => 2,
        'phase' => 'verified',
        'attempt_id' => str_repeat('b', 32),
        'host_id' => 701,
        'name' => 'UI-SCRATCH',
        'domain' => 'corp.example',
        'config_revision' => str_repeat('c', 32),
        'fingerprint' => str_repeat('f', 64),
        'target' => $verifiedConfig['target'],
        'last_deploy_task_id' => 73,
        'trigger_task_id' => 73,
        'placement_hold' => false,
        'reason' => 'verified'
    ];
    $seed->execute([json_encode($verifiedConfig), json_encode($verifiedState)]);
    list($status, $html) = $request();
    if (200 !== $status || !preg_match('/name="_csrf"\s+value="([^"]+)"/', $html, $token)) {
        throw new \RuntimeException('Verified-state form or CSRF token missing: ' . $status);
    }
    if (false !== strpos($html, 'name="rejoin_retry"')) {
        throw new \RuntimeException('Verified state without fresh false observation unexpectedly rendered Retry');
    }
    $post = [
        '_csrf' => html_entity_decode($token[1], ENT_QUOTES, 'UTF-8'),
        'domain' => '1',
        'domainname' => 'corp.example',
        'domainuser' => 'fixture-user',
        'rejoin_settings_present' => '1',
        'rejoin_retry' => '1'
    ];
    list($status, $body) = $request($post);
    list($savedConfig, $savedState) = $readDirectory();
    if (200 !== $status || false !== strpos((string)$body, 'rejoin_attempt_requires_review')
        || ($savedConfig['retry_authorized'] ?? false) !== true
        || ($savedConfig['config_revision'] ?? null) === $verifiedConfig['config_revision']
        || $savedState !== $verifiedState) {
        throw new \RuntimeException(sprintf(
            'Verified Retry did not authorize a new revision while preserving the full state: HTTP %d, body=%s',
            $status, substr(strip_tags((string)$body), 0, 500)
        ));
    }
    echo "PASS real hostADPost verified Retry grants a new config revision and preserves the entire saved state\n";
} finally {
    proc_terminate($process); proc_close($process); unlink($log); unlink($cookie);
}
