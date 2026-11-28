<?php
/**
 * The SSH public keys a debug task installs for root in FOS.
 *
 * PHP version 7.4+
 *
 * @category DebugSshKeys
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

declare(strict_types=1);

namespace FOG\Util;

use FOG\Audit\Audit;

/**
 * The SSH public keys a debug task installs for root in FOS (#2193).
 *
 * FOG_DEBUG_SSH_KEYS holds OpenSSH public keys, one per line. A debug task
 * sends them to FOS in its service/hostinfo.php reply, and FOS writes them to
 * root's authorized_keys, so an administrator can reach the debug shell over
 * SSH without anyone at the console. Empty means the feature is off.
 *
 * Only public keys, never a password: a public key is not a secret, so the
 * unauthenticated hostinfo.php reply may carry it. A password would need a
 * protected way to reach FOS.
 *
 * The setting gives root on every machine in a debug task, so it is checked
 * three times and every change is audited:
 *
 * - on write, by normalize(), from both writers: the FOG Settings page and
 *   Setting::save() (the REST create and edit routes). A value that is not
 *   all public keys is refused.
 * - on read, by forTask(). A value written by any other path (setSetting(),
 *   SQL) is normalized again, and one that fails sends no keys at all.
 * - in FOS, which writes only lines that start with a key type.
 *
 * normalize() refuses authorized_keys options (command=, from=) outright, so
 * a key line is always the key type first.
 *
 * @category DebugSshKeys
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class DebugSshKeys
{
    /**
     * The setting that holds the keys.
     *
     * @var string
     */
    const SETTING = 'FOG_DEBUG_SSH_KEYS';
    /**
     * The public key types OpenSSH accepts in authorized_keys. FOS keeps the
     * same list (installDebugSshKeys() in funcs.sh).
     *
     * @var string[]
     */
    const TYPES = [
        'ssh-ed25519',
        'ssh-rsa',
        'ecdsa-sha2-nistp256',
        'ecdsa-sha2-nistp384',
        'ecdsa-sha2-nistp521',
        'sk-ssh-ed25519@openssh.com',
        'sk-ecdsa-sha2-nistp256@openssh.com',
    ];
    /**
     * The value with one valid key per line, or a refusal.
     *
     * Blank lines and # comment lines are dropped. Each other line must be a
     * key type, then base64 key data whose own type field matches it, then
     * an optional comment. Control characters are removed from the comment.
     * A repeated key is kept once.
     *
     * @param string $value the value as entered
     *
     * @throws \InvalidArgumentException code 400, naming the first bad line
     *
     * @return string the keys joined with "\n", or '' for none
     */
    public static function normalize(string $value): string
    {
        $keys = [];
        $lines = (array)preg_split('/\r\n|\r|\n/', $value);
        foreach ($lines as $index => $line) {
            $line = trim((string)$line);
            if ('' === $line || '#' === $line[0]) {
                continue;
            }
            $parts = (array)preg_split('/\s+/', $line, 3);
            $type = (string)$parts[0];
            $data = (string)($parts[1] ?? '');
            // The key data names its own type: a uint32 length, then the
            // type string. A line whose data is not that key is refused.
            $blob = base64_decode($data, true);
            $named = false === $blob || strlen($blob) < 4
                ? ''
                : substr($blob, 4, (int)unpack('N', substr($blob, 0, 4))[1]);
            if (!in_array($type, self::TYPES, true) || $named !== $type) {
                throw new \InvalidArgumentException(
                    sprintf(
                        _('%s line %d is not an OpenSSH public key'),
                        self::SETTING,
                        $index + 1
                    ),
                    400
                );
            }
            $comment = trim((string)preg_replace('/[[:cntrl:]]/', '', (string)($parts[2] ?? '')));
            $keys[] = trim("$type $data $comment");
        }
        return implode("\n", array_values(array_unique($keys)));
    }
    /**
     * The hostinfo.php value for one task: base64 of the keys, or ''.
     *
     * '' unless the task is a debug task: a debug task type (Debug, Debug
     * Deploy, Debug Capture) or the task's own Debug checkbox. Those are the
     * paths that put isdebug=yes on the kernel line. The host's own kernel
     * arguments are not consulted. A stored value that fails normalize()
     * sends nothing, so a bad write cannot reach FOS.
     *
     * @param object $Task     the task hostinfo.php answers for
     * @param object $TaskType that task's type, as hostinfo.php loaded it
     * @param string $value    the setting's stored value
     *
     * @return string
     */
    public static function forTask($Task, $TaskType, string $value): string
    {
        if (!$Task->isValid()) {
            return '';
        }
        // Task::isDebug() covers Debug Deploy, Debug Capture and the Debug
        // checkbox. TaskType::isDebug() also covers the plain Debug type (3),
        // whose mode=onlydebug lives on the type, not the task.
        if (!$Task->isDebug() && !$TaskType->isDebug()) {
            return '';
        }
        $keys = self::normalizeStored($value);
        return '' === $keys ? '' : base64_encode($keys . "\n");
    }
    /**
     * A stored value, normalized, or '' when it fails normalize().
     *
     * For a value already in the database, which another path (setSetting(),
     * SQL) may have written without the check. It fails closed: no keys.
     *
     * @param string $value the stored value
     *
     * @return string
     */
    public static function normalizeStored(string $value): string
    {
        try {
            return self::normalize($value);
        } catch (\InvalidArgumentException $e) {
            return '';
        }
    }
    /**
     * Audits a change to the setting.
     *
     * The row names the keys by fingerprint (SHA256, as ssh-keygen -l
     * prints it), the ones added and the ones removed, so a key that gave
     * someone root can be traced to who added it and when. The keys are
     * public, but the fingerprints are shorter and fit the audit detail.
     *
     * @param string $old        the normalized value before
     * @param string $new        the normalized value after
     * @param string $permission the permission the writer checked
     *
     * @return void
     */
    public static function recordChange(string $old, string $new, string $permission): void
    {
        $before = self::fingerprints($old);
        $after = self::fingerprints($new);
        $added = array_diff($after, $before);
        $removed = array_diff($before, $after);
        if (!$added && !$removed) {
            return;
        }
        Audit::record(
            [
                'type' => 'setting.debugsshkeys',
                'subjectType' => 'setting',
                'subjectLabel' => self::SETTING,
                'permission' => $permission,
                'affectedCount' => count($after),
                'renderable' => 1,
                'text' => trim(
                    ($added ? 'added ' . implode(' ', $added) . ' ' : '')
                    . ($removed ? 'removed ' . implode(' ', $removed) : '')
                ),
            ]
        );
    }
    /**
     * The SHA256 fingerprint of each key in a normalized value.
     *
     * @param string $value a value normalize() returned
     *
     * @return string[]
     */
    public static function fingerprints(string $value): array
    {
        $prints = [];
        foreach (array_filter(explode("\n", $value)) as $line) {
            $data = (string)(explode(' ', $line)[1] ?? '');
            $prints[] = 'SHA256:' . rtrim(
                base64_encode(hash('sha256', (string)base64_decode($data), true)),
                '='
            );
        }
        return $prints;
    }
}
