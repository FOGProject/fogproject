<?php
/**
 * FOG email goes through one helper, and that helper reaches the mail command.
 *
 * FOG_EMAIL_BINARY never took effect: TaskQueue::_email() read it, filled in
 * ${server-name}, and dropped it. mail() always runs php.ini sendmail_path.
 * The setting is retired (schema 472), and the image report and the FOG
 * Settings test email now share FOG\Util\Mailer, so the test proves the
 * report's path.
 *
 * The send checks run real mail() in a child php with sendmail_path set
 * (-d), pointed at a capture script. So they see what the mail command
 * gets: argv and the message on stdin. argv must be exactly what
 * sendmail_path names: no setting value reaches the command line, so the
 * envelope (bounce) sender stays the mail command's default.
 *
 * Usage: php tests/mailer-sends-through-sendmail-path.test.php
 * Exit status 0 = pass, 1 = fail.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('mailer-sends-through-sendmail-path');

use FOG\Util\Mailer;

// Child mode: one Mailer call under the sendmail_path the parent set.
if (isset($argv[1]) && '--child' === $argv[1]) {
    if ('problem' === $argv[2]) {
        $p = Mailer::problem();
        echo null === $p ? "READY\n" : 'PROBLEM ' . $p . "\n";
    } elseif ('service' === $argv[2]) {
        $w = Mailer::serviceWarning((int)$argv[3]);
        echo null === $w ? "QUIET\n" : 'WARN ' . $w . "\n";
    } else {
        $ok = Mailer::send(
            'one@example.com,two@example.com',
            'Subject line',
            "Body text\n",
            (string)$argv[3]
        );
        echo $ok ? "SENT\n" : "REFUSED\n";
    }
    exit(0);
}

$t = new FogChecks();
$root = dirname(__DIR__);
$work = sys_get_temp_dir() . '/fog-mailer-test-' . getmypid();
mkdir($work, 0700);
$capture = $work . '/capture.sh';
file_put_contents(
    $capture,
    "#!/bin/sh\n"
    . "printf '%s\\n' \"\$@\" > \"$work/argv\"\n"
    . "cat > \"$work/stdin\"\n"
);
chmod($capture, 0700);

/**
 * Run one child under a sendmail_path.
 *
 * @param string $path the sendmail_path
 * @param array  $args child arguments after --child
 *
 * @return string
 */
$child = function ($path, array $args) use ($root) {
    $cmd = array_merge(
        [
            PHP_BINARY, '-d', 'sendmail_path=' . $path,
            $root . '/tests/mailer-sends-through-sendmail-path.test.php',
            '--child'
        ],
        $args
    );
    $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $out = stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    proc_close($proc);
    return trim((string)$out);
};

// problem(): the pre-check the test button runs before it sends.
$t->check(
    'problem() is null when the mail command is executable',
    'READY' === $child($capture . ' -t -i', ['problem'])
);
$t->check(
    'problem() names a mail command that is not installed',
    0 === strpos(
        $child($work . '/no-such-sendmail -t -i', ['problem']),
        'PROBLEM The mail command ' . $work . '/no-such-sendmail '
    )
);

// A bare name is found on PATH by the shell that mail() uses, so the
// pre-check must not refuse it. `sh` is on PATH on every test box.
$t->check(
    'problem() is null for a bare command name found on PATH',
    'READY' === $child('sh -c true', ['problem'])
);
$t->check(
    'problem() names a bare command that is not on PATH',
    0 === strpos($child('no-such-sendmail-xyz -t', ['problem']), 'PROBLEM ')
);

// serviceWarning(): the sendmail of postfix, exim or sendmail accepts a
// message while the mail server is stopped, and it never leaves. A closed
// local SMTP port is how the test button tells. A real listener on a free
// port stands in for a running mail server. Only a command that is
// positively one of those three is checked.
$listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
$openPort = (int)substr(strrchr(stream_socket_get_name($listener, false), ':'), 1);
$probe = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
$closedPort = (int)substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
fclose($probe);
/**
 * Put a program at $dir/$name, a copy of the capture script.
 *
 * @param string $dir  the directory, created when missing
 * @param string $name the program's file name
 *
 * @return string its path
 */
$program = function ($dir, $name) use ($capture) {
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    copy($capture, $dir . '/' . $name);
    chmod($dir . '/' . $name, 0700);
    return $dir . '/' . $name;
};
/**
 * Point $work/sendmail at $target through a symlink, as alternatives do.
 *
 * @param string $target the program the link names
 *
 * @return string the sendmail_path to use
 */
$link = function ($target) use ($work) {
    @unlink($work . '/sendmail');
    symlink($target, $work . '/sendmail');
    return $work . '/sendmail -t';
};
$service = function ($path, $port) use ($child) {
    return $child($path, ['service', (string)$port]);
};

$path = $link($program($work . '/alt', 'sendmail.postfix'));
$t->check(
    'postfix through alternatives: quiet while port 25 answers',
    'QUIET' === $service($path, $openPort)
);
$t->check(
    'postfix through alternatives: warns, naming postfix, when nothing listens',
    0 === strpos(
        $service($path, $closedPort),
        'WARN The mail command is postfix, but nothing is listening on port '
        . $closedPort . ' '
    )
);
// Debian and Arch: a plain sendmail, with postfix's own postconf beside it.
$program($work . '/pf', 'postconf');
$path = $program($work . '/pf', 'sendmail') . ' -t';
$t->check(
    'a plain sendmail beside postconf is postfix (Debian and Arch layout)',
    0 === strpos($service($path, $closedPort), 'WARN The mail command is postfix,')
);
// nullmailer's own plain sendmail. A leftover /etc/postfix/main.cf from a
// removed postfix would not matter: only a postfix program counts.
$path = $program($work . '/nm', 'sendmail') . ' -t';
$t->check(
    'a plain sendmail with no postfix program beside it is not checked (nullmailer)',
    'QUIET' === $service($path, $closedPort)
);
$path = $program($work . '/sm.bin', 'sendmail') . ' -t';
$t->check(
    'a plain sendmail in /usr/lib/sm.bin is sendmail (Debian sendmail)',
    0 === strpos($service($path, $closedPort), 'WARN The mail command is sendmail,')
);
$path = $link($program($work . '/alt', 'exim4'));
$t->check(
    'exim4 is checked and named by its Debian service name',
    0 === strpos($service($path, $closedPort), 'WARN The mail command is exim4,')
);
foreach (['msmtp', 'sendmail.ssmtp', 'esmtp-wrapper', 'nullmailer-inject', 'dma'] as $name) {
    $path = $link($program($work . '/alt', $name));
    $t->check(
        'serviceWarning() does not check ' . $name . ', which is not a daemon MTA',
        'QUIET' === $service($path, $closedPort)
    );
}
@unlink($work . '/sendmail');
fclose($listener);
foreach (['alt', 'pf', 'nm', 'sm.bin'] as $sub) {
    array_map('unlink', glob($work . '/' . $sub . '/*'));
    rmdir($work . '/' . $sub);
}

// send(): what the mail command receives.
$out = $child($capture . ' -t -i', ['send', 'noreply@fog.example.com']);
$argvSeen = is_file($work . '/argv') ? file($work . '/argv', FILE_IGNORE_NEW_LINES) : [];
$stdin = is_file($work . '/stdin') ? (string)file_get_contents($work . '/stdin') : '';
$t->check('send() returns true when the mail command exits 0', 'SENT' === $out);
$t->check(
    'send() adds nothing to the sendmail_path arguments',
    ['-t', '-i'] === $argvSeen
);
$t->check(
    'the message carries the From header',
    false !== strpos($stdin, "From: noreply@fog.example.com\r\n")
        || false !== strpos($stdin, "From: noreply@fog.example.com\n")
);
$t->check(
    'the message carries both recipients and the body',
    false !== strpos($stdin, 'To: one@example.com,two@example.com')
        && false !== strpos($stdin, 'Body text')
);

// A From value shaped to add a sendmail option (CVE-2016-10033: "-X <file>"
// writes a file) must add none.
foreach ([
    'name and address' => 'FOG <noreply@fog.example.com>',
    'space split' => 'a@b.com -X/var/www/html/shell.php',
    'quote split' => "\"a\\\" -X/tmp/x \"@b.com",
    'leading dash' => '-X/tmp/x@b.com',
    'leading dash, plain chars' => '-oQ@b.com',
] as $label => $evil) {
    @unlink($work . '/argv');
    $child($capture . ' -t -i', ['send', $evil]);
    $seen = is_file($work . '/argv') ? file($work . '/argv', FILE_IGNORE_NEW_LINES) : [];
    $t->check(
        'a From built to add a sendmail option adds none (' . $label . ')',
        ['-t', '-i'] === $seen
    );
}

// fromAddress(): default, ${server-name}, and no header injection.
$t->check(
    'an empty FOG_FROM_EMAIL falls back to the default',
    Mailer::DEFAULT_FROM === Mailer::fromAddress('', 'node1')
);
$t->check(
    '${server-name} becomes the node name',
    'noreply@node1.com' === Mailer::fromAddress('noreply@${server-name}.com', 'node1')
);
$t->check(
    'a line break in FOG_FROM_EMAIL cannot add a header',
    'a@b.comBcc: x@y.com' === Mailer::fromAddress("a@b.com\r\nBcc: x@y.com", 'n')
);

$t->check(
    'a line break in the node name cannot add a header',
    false === strpbrk(Mailer::fromAddress('a@${server-name}', "n\r\nBcc: x@y.com"), "\r\n")
);

array_map('unlink', glob($work . '/*'));
rmdir($work);
$t->finish();
