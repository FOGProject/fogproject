<?php
/**
 * Sends FOG's email, for the image-completion report and the settings test.
 *
 * PHP version 7.4+
 *
 * @category Mailer
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Util;

/**
 * Sends FOG's email, for the image-completion report and the settings test.
 *
 * One send path, so the test button on FOG Settings proves what the real
 * report does. PHP's mail() always runs the command in php.ini
 * `sendmail_path`. That ini value cannot be changed at run time, which is
 * why the old FOG_EMAIL_BINARY setting never took effect.
 *
 * @category Mailer
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Mailer
{
    /**
     * The From address when FOG_FROM_EMAIL is empty.
     *
     * @var string
     */
    const DEFAULT_FROM = 'noreply@fogserver.com';
    /**
     * The ${server-name} value when no storage node applies.
     *
     * @var string
     */
    const DEFAULT_NODE = 'fogserver';
    /**
     * Matches the ${server-name} placeholder in the email settings.
     *
     * @var string
     */
    const SERVER_NAME = '#\$\{server\-name\}#';
    /**
     * The command mail() runs: php.ini sendmail_path.
     *
     * @return string
     */
    public static function command()
    {
        return trim((string)ini_get('sendmail_path'));
    }
    /**
     * Why mail() cannot send, or null when the mail command is present.
     *
     * This checks only that the binary exists and is executable. Whether
     * the mail server then delivers the message is not visible from PHP.
     *
     * @return string|null
     */
    public static function problem()
    {
        $command = self::command();
        if ('' === $command) {
            return _(
                'PHP has no mail command. Set sendmail_path in php.ini.'
            );
        }
        $binary = self::_program();
        if ('' === self::_resolve($binary)) {
            return sprintf(
                _(
                    'The mail command %s is not installed or is not '
                    . 'executable. Install a mail server such as postfix, '
                    . 'or set sendmail_path in php.ini.'
                ),
                $binary
            );
        }
        return null;
    }
    /**
     * A warning when no local mail server is running, or null.
     *
     * The sendmail command of postfix, exim or sendmail only hands the
     * message to the local mail server. It succeeds while that server is
     * stopped, and the message then waits in the queue and never leaves.
     * Each of them listens on SMTP port 25 while it runs, so a closed port
     * on this host usually means the message will not leave.
     *
     * Only those three are checked, and only when the mail command is
     * positively one of them (see _daemonMta()). Any other command, such as
     * msmtp, nullmailer or esmtp, gets no warning: many need no listener on
     * port 25, and a list of exceptions kept missing one. A mail server
     * bound only to a non-loopback address still warns, which is why the
     * text says "may be stopped".
     *
     * This opens a TCP connection to the local host only. It runs no
     * command and takes no input.
     *
     * @param int $port the SMTP port, a parameter only for the tests
     *
     * @return string|null
     */
    public static function serviceWarning($port = 25)
    {
        $binary = self::_resolve(self::_program());
        if ('' === $binary) {
            // problem() already reports a missing command.
            return null;
        }
        $service = self::_daemonMta($binary);
        if (null === $service) {
            return null;
        }
        // Short timeout: a refused local connect returns at once, and only a
        // firewall that drops loopback traffic would make this wait.
        foreach (['127.0.0.1', '[::1]'] as $host) {
            $sock = @fsockopen($host, (int)$port, $errno, $errstr, 0.5);
            if (false !== $sock) {
                fclose($sock);
                return null;
            }
        }
        return sprintf(
            _(
                'The mail command is %1$s, but nothing is listening on '
                . 'port %2$d on this server, so %1$s may be stopped. The '
                . 'mail command accepts a message while %1$s is stopped, '
                . 'and the message then waits in the queue. Start it, for '
                . 'example: systemctl enable --now %1$s'
            ),
            $service,
            (int)$port
        );
    }
    /**
     * Which local mail server the mail command hands off to, or null.
     *
     * realpath() follows /usr/sbin/sendmail -> /etc/alternatives/mta, so
     * the alternatives name (sendmail.postfix) names the program. Debian
     * and Arch install postfix and sendmail as a plain sendmail file
     * instead, and nullmailer ships a plain sendmail too, so a plain name
     * counts only beside a program of that mail server.
     *
     * @param string $binary the resolved mail command
     *
     * @return string|null postfix, exim, exim4 or sendmail
     */
    private static function _daemonMta($binary)
    {
        $path = (string)realpath($binary);
        $real = basename($path);
        // Debian names the exim program and its service exim4.
        if (preg_match('/^(exim|exim4|sendmail\.exim)$/', $real)) {
            return 'exim4' === $real ? 'exim4' : 'exim';
        }
        // A program the package owns, not its configuration: a removed
        // postfix leaves main.cf behind, and nullmailer then installs its
        // own plain sendmail beside it.
        $dir = dirname($path);
        $plain = 'sendmail' === $real;
        if ('sendmail.postfix' === $real
            || ($plain && is_executable($dir . '/postconf'))
        ) {
            return 'postfix';
        }
        // Debian's sendmail package keeps its programs in /usr/lib/sm.bin.
        if ('sendmail.sendmail' === $real
            || ($plain && 'sm.bin' === basename($dir))
        ) {
            return 'sendmail';
        }
        return null;
    }
    /**
     * The first word of sendmail_path: the program, before its arguments.
     *
     * @return string
     */
    private static function _program()
    {
        return (string)strtok(self::command(), " \t");
    }
    /**
     * The full path of a mail command, or '' when it is not executable.
     *
     * mail() runs sendmail_path through the shell, so a bare name such as
     * "sendmail -t -i" is found on PATH. This looks it up the same way.
     *
     * @param string $binary the first word of sendmail_path
     *
     * @return string
     */
    private static function _resolve($binary)
    {
        if ('' === $binary) {
            return '';
        }
        if (false === strpos($binary, '/')) {
            $path = (string)getenv('PATH');
            foreach (explode(':', $path ?: '/usr/sbin:/usr/bin:/sbin:/bin') as $dir) {
                if ('' !== $dir && is_executable($dir . '/' . $binary)) {
                    return $dir . '/' . $binary;
                }
            }
        }
        return is_file($binary) && is_executable($binary) ? $binary : '';
    }
    /**
     * The From address: FOG_FROM_EMAIL with ${server-name} filled in.
     *
     * @param string $setting  the FOG_FROM_EMAIL value
     * @param string $nodeName the storage node name for ${server-name}
     *
     * @return string
     */
    public static function fromAddress($setting, $nodeName)
    {
        $from = trim((string)$setting);
        if ('' === $from) {
            $from = self::DEFAULT_FROM;
        }
        $from = preg_replace(self::SERVER_NAME, (string)$nodeName, $from);
        // A line break would start a new mail header. Stripped after the
        // fill, so a node name cannot carry one in either.
        return trim(str_replace(["\r", "\n"], '', $from));
    }
    /**
     * Hand one message to the mail command.
     *
     * True means the local mail command accepted the message. It does not
     * mean the message was delivered.
     *
     * @param string $to      recipients, comma separated
     * @param string $subject the subject line
     * @param string $body    the plain text body
     * @param string $from    the From address, from fromAddress()
     *
     * @return bool
     */
    public static function send($to, $subject, $body, $from)
    {
        $headers = sprintf(
            "From: %s\r\nX-Mailer: PHP/%s",
            $from,
            phpversion()
        );
        // No additional_params on purpose. The envelope (bounce) sender
        // stays the mail command's default, as it always has been, so a
        // relay rule written for it keeps matching. An admin who wants a
        // different one adds -f to sendmail_path in php.ini. No setting
        // value reaches the sendmail command line (CVE-2016-10033 class).
        return mail($to, $subject, $body, $headers);
    }
}
