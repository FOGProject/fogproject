<?php
/**
 * FOGURLRequests must verify TLS, and must not leak the session off-site.
 *
 * This class makes every outbound HTTP request the web tier makes: the
 * GitHub kernel and init listings, the kernel download itself, the
 * fogproject.org version check, and the storage-node status endpoints. It
 * used to treat all of those the same way, with two consequences that were
 * written for the last of them and silently applied to the rest:
 *
 *   1. CURLOPT_SSL_VERIFYPEER and VERIFYHOST were false for everything. A
 *      storage node is addressed by bare IP and presents a self-signed
 *      certificate, so verification genuinely cannot work there -- but
 *      api.github.com and fogproject.org present ordinary publicly-signed
 *      certificates, and there the setting bought nothing and cost the
 *      ability to notice a substituted response. Downloading a kernel is
 *      the sharpest case: the file lands on disk and is booted by every
 *      machine that images afterwards.
 *   2. The signed-in administrator's PHP session id (and the CSRF token)
 *      were attached to every request. They are there so a node's status
 *      endpoint can authorise the call; sending them to a third party is a
 *      credential handed over for no purpose.
 *
 * Both are now decided by the URL's host rather than by the caller, so a
 * new caller cannot inherit either by not thinking about it. That decision
 * is the thing under test.
 *
 * The host matcher runs for real: it is a pure static, so it needs no
 * database. The wiring around it is pinned by source inspection, because
 * observing it needs a live curl handle and a populated storage node table.
 *
 * Usage: php tests/url-requests-tls.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
chdir($root);

$fails = [];
$file = 'packages/web/lib/fog/fogurlrequests.class.php';
$src = (string)file_get_contents($file);

/*
 * 1. The host matcher, for real.
 *
 * The cases that matter are the ones that must NOT match. The previous
 * implementation was an unanchored regex of unescaped IPs matched against
 * the whole URL, so a URL merely CONTAINING a node's address matched it --
 * harmless while the answer only chose whether to use a proxy, and not
 * harmless now that it decides whether a certificate is checked.
 */
preg_match('#public static function isFogHost.*?\n    \}#s', $src, $m);
if (empty($m)) {
    $fails[] = 'FOGURLRequests::isFogHost() is gone; the TLS exemption and'
        . ' the session-forwarding decision both key off it';
} else {
    eval(
        'function fogHostProbe($url, array $hosts) '
        . substr($m[0], strpos($m[0], '{'))
    );
    $hosts = [
        '10.0.0.5',
        'node.example.lan',
        '127.0.0.1',
        '::1',
        'fogserver.example.lan'
    ];
    $cases = [
        // Ours: exempt from verification, and allowed the session cookie.
        ['https://10.0.0.5/fog/status/getsize.php', true],
        ['http://10.0.0.5/fog/status/getfiles.php?path=/images', true],
        ['https://NODE.EXAMPLE.LAN/fog/', true],
        ['https://[::1]/fog/', true],
        // Not ours.
        ['https://api.github.com/repos/FOGProject/fos/releases', false],
        ['https://fogproject.org/version/index.php', false],
        // The substring cases the old regex got wrong.
        ['https://example.com/?ref=10.0.0.5', false],
        ['https://10.0.0.5.evil.com/', false],
        ['https://node.example.lan.evil.com/', false],
        ['https://10.0.0.50/', false],
        // Nothing to match on must not match.
        ['not a url', false],
        ['', false]
    ];
    foreach ($cases as $case) {
        list($url, $want) = $case;
        $got = fogHostProbe($url, $hosts);
        if ($got !== $want) {
            $fails[] = sprintf(
                'isFogHost(%s) returned %s, expected %s',
                var_export($url, true),
                var_export($got, true),
                var_export($want, true)
            );
        }
    }
}

/*
 * 2. Verification is the default. Both places that build the shared option
 *    set have to say so -- the property and _baseOptions() -- because the
 *    constructor replaces one with the other and a disagreement between
 *    them would be invisible.
 */
$verifyOff = preg_match_all(
    '#CURLOPT_SSL_VERIFYPEER\s*=>\s*(false|0)\b#',
    $src,
    $offs
);
$exemption = '';
// Either array syntax: working-1.6 writes [], dev-branch writes array().
if (preg_match('#const NODE_TLS_OPTIONS = (?:\[|array\().*?;#s', $src, $m)) {
    $exemption = $m[0];
}
if ('' === $exemption) {
    $fails[] = 'FOGURLRequests::NODE_TLS_OPTIONS is gone; the exemption for'
        . " FOG's own nodes has to be one named thing, not a value repeated"
        . ' wherever somebody needed it';
} else {
    $offInExemption = preg_match_all(
        '#CURLOPT_SSL_VERIFYPEER\s*=>\s*(false|0)\b#',
        $exemption
    );
    if ($verifyOff !== $offInExemption) {
        $fails[] = sprintf(
            'CURLOPT_SSL_VERIFYPEER is disabled in %d place(s) outside'
            . ' NODE_TLS_OPTIONS; verification must be off only for a host'
            . ' this install owns',
            $verifyOff - $offInExemption
        );
    }
}
if (2 !== preg_match_all('#CURLOPT_SSL_VERIFYPEER\s*=>\s*true#', $src)) {
    $fails[] = 'the shared option set no longer defaults'
        . ' CURLOPT_SSL_VERIFYPEER to true in both the property and'
        . ' _baseOptions()';
}

/*
 * 3. The exemption and the session forwarding are conditioned on the host.
 *    Checked by shape rather than by value: what must not come back is an
 *    unconditional cookie line, which is what shipped.
 */
if (!preg_match(
    '#if \(\$isFogHost\s*\n\s*&& !isset\(\$request->options\[CURLOPT_SSL_VERIFYPEER\]\)#',
    $src
)) {
    $fails[] = 'the TLS exemption is no longer gated on $isFogHost, or no'
        . ' longer yields to a caller that named CURLOPT_SSL_VERIFYPEER'
        . ' itself';
}
if (!preg_match(
    '#if \(\$isFogHost\s*\n?\s*&& !isset\(\$options\[CURLOPT_COOKIE\]\)#',
    $src
)) {
    $fails[] = "the session cookie is no longer gated on \$isFogHost; the"
        . " signed-in administrator's session id would be sent to every"
        . ' third-party host this class fetches from';
}
/*
 * And only when there IS a session to forward. session_id() is '' in every
 * CLI daemon and in any API request authenticated by token rather than by
 * cookie, and the header still went out as the bare "PHPSESSID=". An empty
 * value satisfies isset() on the far side, so init.php's browser-less gate
 * opened, use_strict_mode found no id to resume, and PHP minted a throwaway
 * session per request -- ~3,000 an hour from the multicast daemon's node
 * reads alone. Pinned by shape for the same reason as the gate above: what
 * must not come back is a cookie line that runs with no session.
 */
if (!preg_match(
    '#!isset\(\$options\[CURLOPT_COOKIE\]\)\s*\n\s*&& session_id\(\) !== \x27\x27#',
    $src
)) {
    $fails[] = 'the session cookie is no longer gated on there being a'
        . ' session to send; a session-less caller would again send'
        . ' "PHPSESSID=", which mints an empty session on the far side';
}
if (false === strpos($src, 'X-CSRF-Token:')) {
    $fails[] = 'the CSRF token is no longer filtered out for third-party'
        . ' hosts';
}

/*
 * 4. Nothing may go back to matching the URL with a regex. The proxy bypass
 *    and the TLS exemption now share one answer, so a pattern loose enough
 *    for the first is a hole in the second.
 */
if (preg_match('#preg_match\(\$this->_proxy#', $src)) {
    $fails[] = 'the proxy bypass matches the URL against a pattern again;'
        . ' it shares its answer with the TLS exemption, so the comparison'
        . ' has to be a whole-host one';
}

if (count($fails) > 0) {
    fwrite(STDERR, 'FAIL: ' . count($fails) . " problem(s):\n");
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok: outbound requests verify TLS and keep the session at home\n";
exit(0);
