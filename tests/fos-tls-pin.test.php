<?php
/**
 * The fogpin= argument the boot menus hand FOS, against real TLS servers.
 *
 * FOS verifies this server's TLS key against fogpin= (fos#102, fos ADR-0020)
 * with curl --pinnedpubkey. A wrong pin stops every task the host runs, so
 * the pin is checked here three ways: against a value computed independently
 * by the openssl CLI, against curl itself, and against the cases where no pin
 * must be sent at all.
 *
 * FosTlsPin::arg() pins only when the web= host resolves to an address on
 * this machine, and reads the key at that address. Case 4 is the shape the
 * installer's nginx vhost has -- a listener on one specific address and
 * nothing on 127.0.0.1:443 -- which an earlier loopback read never pinned.
 * Cases 5 and 7 cover addresses and names that are not this machine.
 *
 * Needs openssl and curl on the PATH. Binds 127.0.0.1 and 127.0.0.2 only.
 *
 * Usage: php tests/fos-tls-pin.test.php
 * Exit status 0 = pass, 1 = fail.
 */

namespace {
    $root = dirname(__DIR__);
    require_once $root . '/packages/web/src/Boot/FosTlsPin.php';

    foreach (['openssl', 'curl'] as $tool) {
        if ('' === trim((string)shell_exec('command -v ' . $tool))) {
            fwrite(STDERR, "FAIL: $tool is required\n");
            exit(1);
        }
    }

    $dir = sys_get_temp_dir() . '/fos-tls-pin-' . getmypid();
    mkdir($dir);
    /** @var resource[] $servers the s_server processes, filled by $serve */
    $servers = [];
    register_shutdown_function(
        function () use (&$servers, $dir) {
            foreach ($servers as $proc) {
                proc_terminate($proc);
                proc_close($proc);
            }
            array_map('unlink', glob("$dir/*") ?: []);
            rmdir($dir);
        }
    );

    /**
     * Make a self-signed key pair named $name and return its pin, computed
     * by the openssl CLI rather than by the code under test.
     */
    $makeCert = function ($name) use ($dir) {
        exec(
            sprintf(
                'openssl req -x509 -newkey rsa:2048 -nodes -days 1 '
                . '-subj /CN=%1$s -keyout %2$s/%1$s.key -out %2$s/%1$s.pem 2>/dev/null',
                $name,
                $dir
            ),
            $out,
            $rc
        );
        if (0 !== $rc) {
            fwrite(STDERR, "FAIL: could not generate a certificate\n");
            exit(1);
        }
        return trim(
            (string)shell_exec(
                sprintf(
                    'openssl x509 -in %s/%s.pem -pubkey -noout'
                    . ' | openssl pkey -pubin -outform der'
                    . ' | openssl dgst -sha256 -binary | base64',
                    $dir,
                    $name
                )
            )
        );
    };

    /**
     * A free port on 127.0.0.1. 127.0.0.2 shares the loopback device, so
     * the same number is used on both where a case needs a pair.
     */
    $freePort = function () {
        $s = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int)substr(strrchr(stream_socket_get_name($s, false), ':'), 1);
        fclose($s);
        return $port;
    };

    /**
     * Start openssl s_server with certificate $name on $addr:$port and wait
     * until it accepts.
     */
    $serve = function ($name, $addr, $port) use ($dir, &$servers) {
        $proc = proc_open(
            [
                'openssl', 's_server', '-quiet', '-www',
                '-accept', "$addr:$port",
                '-cert', "$dir/$name.pem", '-key', "$dir/$name.key",
            ],
            [['file', '/dev/null', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']],
            $pipes
        );
        $servers[] = $proc;
        for ($i = 0; $i < 50; $i++) {
            $s = @stream_socket_client("tcp://$addr:$port", $e, $m, 0.2);
            if ($s) {
                fclose($s);
                return;
            }
            usleep(100000);
        }
        fwrite(STDERR, "FAIL: s_server did not start on $addr:$port\n");
        exit(1);
    };

    $pin = function ($web) {
        return \FOG\Boot\FosTlsPin::arg($web);
    };

    $failures = 0;
    $checks = 0;
    $check = function ($name, $ok, $detail = '') use (&$failures, &$checks) {
        $checks++;
        if ($ok) {
            echo "PASS: $name\n";
            return;
        }
        $failures++;
        echo "FAIL: $name" . ('' === $detail ? '' : " ($detail)") . "\n";
    };

    $pinA = $makeCert('a');
    $pinB = $makeCert('b');

    // 1. One server, reached the same way over loopback and at web=.
    $p1 = $freePort();
    $serve('a', '127.0.0.1', $p1);
    $got = $pin("https://127.0.0.1:$p1/fog/");
    $check(
        '1. the pin is the served key, as the openssl CLI computes it',
        " fogpin=$pinA" === $got,
        "got '$got', want ' fogpin=$pinA'"
    );

    // 2. The consumer agrees: curl accepts the pin the way FOS passes it.
    exec(
        sprintf(
            'curl -sk -o /dev/null --pinnedpubkey %s https://127.0.0.1:%d/',
            escapeshellarg('sha256//' . substr($got, strlen(' fogpin='))),
            $p1
        ),
        $out,
        $rc
    );
    $check('2. curl --pinnedpubkey accepts the pin', 0 === $rc, "curl exit $rc");

    // 3. Plain http: no key, no pin, and no connection attempted.
    $got = $pin("http://127.0.0.1:$p1/fog/");
    $check('3. http gets no pin', '' === $got, "got '$got'");

    // 4. The installer's nginx shape: TLS on one specific local address,
    //    nothing on 127.0.0.1 at that port. It is this machine, so it pins.
    $p4 = $freePort();
    $serve('b', '127.0.0.2', $p4);
    $got = $pin("https://127.0.0.2:$p4/fog/");
    $check(
        '4. a listener bound to one local address is pinned',
        " fogpin=$pinB" === $got,
        "got '$got', want ' fogpin=$pinB'"
    );

    // 5. An address that is not this machine is refused before any
    //    connection. 192.0.2.0/24 is TEST-NET-1, never assigned to a host.
    $check(
        '5. a non-local address is not local',
        false === \FOG\Boot\FosTlsPin::isLocalAddress('192.0.2.10')
    );
    $check(
        '5b. a loopback address is local',
        true === \FOG\Boot\FosTlsPin::isLocalAddress('127.0.0.2')
    );
    // Timed, because the answer alone cannot tell "refused as non-local"
    // from "tried, and nothing answered": with a default route, a connect to
    // TEST-NET hangs until _servedKeyPin()'s 2s timeout. Without one it fails at
    // once, and this check cannot fail -- it never fails wrongly.
    $t = microtime(true);
    $got = $pin('https://192.0.2.10/fog/');
    $took = microtime(true) - $t;
    $check(
        '5c. a non-local web= host gets no pin, and no connection is tried',
        '' === $got && $took < 0.5,
        sprintf("got '%s' in %.2fs", $got, $took)
    );

    // 6. Nothing listening on a local port. No pin, and no error.
    $got = $pin('https://127.0.0.1:' . $freePort() . '/fog/');
    $check('6. an unreachable server sends no pin', '' === $got, "got '$got'");

    // 7. A name is resolved first: localhost is this machine and pins; a
    //    name that does not resolve does not.
    $got = $pin("https://localhost:$p1/fog/");
    $check(
        '7. a name that resolves to this machine is pinned',
        " fogpin=$pinA" === $got,
        "got '$got', want ' fogpin=$pinA'"
    );
    $got = $pin('https://no-such-host.invalid/fog/');
    $check('7b. a name that does not resolve gets no pin', '' === $got, "got '$got'");

    echo "\nfos-tls-pin: $checks checks, $failures failed\n";
    exit($failures ? 1 : 0);
}
