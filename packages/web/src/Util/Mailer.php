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
        $binary = (string)strtok($command, " \t");
        // mail() runs sendmail_path through the shell, so a bare name such
        // as "sendmail -t -i" is found on PATH. Look it up the same way.
        if (false === strpos($binary, '/')) {
            $path = (string)getenv('PATH');
            foreach (explode(':', $path ?: '/usr/sbin:/usr/bin:/sbin:/bin') as $dir) {
                if ('' !== $dir && is_executable($dir . '/' . $binary)) {
                    return null;
                }
            }
        }
        if (!is_file($binary) || !is_executable($binary)) {
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
        // Set the envelope sender too, so bounces go to the From address
        // and not to the web server account. The value lands on the
        // sendmail command line, so only a plain address goes there: no
        // space, quote or backslash (CVE-2016-10033 split one value into a
        // second option, such as -X <file>), and no leading '-'. Anything
        // else, a "Name <addr>" From included, keeps the MTA default.
        $params = '';
        if (preg_match('/^[A-Za-z0-9._%+][A-Za-z0-9._%+-]*@[A-Za-z0-9.-]+$/D', $from)
            && false !== filter_var($from, FILTER_VALIDATE_EMAIL)
        ) {
            $params = '-f' . $from;
        }
        return mail($to, $subject, $body, $headers, $params);
    }
}
