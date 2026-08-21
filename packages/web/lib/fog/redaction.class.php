<?php
/**
 * Decides whether a field's VALUE may be written down.
 *
 * PHP version 7.4+
 *
 * @category Redaction
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * Decides whether a field's VALUE may be written down.
 *
 * ADR 0021 Decision 6. The audit trail records what changed, and the HARD
 * constraint on it is that a secret's value must never land in a row. That
 * question -- "is this field a credential?" -- is not an API-emitter concern
 * any more, so it gets an owner of its own here.
 *
 * WHY THIS IS NOT ANOTHER LIST. Two credential leaks landed in one week and
 * both had an opt-in registry that somebody forgot to add to:
 *
 *   - 58483d6: storagenode.pass was never declared, and the storage GROUP
 *     grid embeds the whole master node object, so the node's FTP password
 *     went to anyone holding storagegroup.view. Checking /storagenode/list
 *     said clean.
 *   - #1261/#1262: the SQL fault log -- a mechanism added days earlier
 *     specifically to record failures -- wrote the failed statement's bound
 *     values, passwords included, into a 0755 file.
 *
 * An audit trail is a third mechanism whose whole job is writing down what
 * happened, at greater volume and with a longer retention. So this defaults
 * CLOSED: a field whose name looks like a credential is redacted whether or
 * not anyone remembered to declare it, and the cost of forgetting is a
 * redacted field rather than a leaked one.
 *
 * THREE LAYERS, because one was what failed:
 *
 *   1. CREDENTIAL_PATTERN over the friendly key. Modelled directly on
 *      Route::SENSITIVE_SETTING_PATTERN, which exists so a credential
 *      setting added later is masked by default instead of leaking until
 *      somebody remembers. A field that matches and is NOT a credential goes
 *      on $patternExempt -- an explicit, reviewable act.
 *   2. Route's own registry, for credentials the pattern misses. host's
 *      sec_tok and prev_sec_tok are the live examples: "tok" is not "token".
 *      Read through Route::sensitiveFieldMap(), never the properties, or
 *      plugin-declared fields are skipped -- that accessor's own docblock
 *      says so and it is the same mistake as 58483d6.
 *   3. tests/audit-redaction-coverage.test.php, which walks every model and
 *      fails on a key that matches the pattern and is classified nowhere. A
 *      new credential column is caught by CI, not by memory.
 *
 * TIER-2 SEMANTICS FOR BOTH TIERS. Route keeps two tiers because fog-client
 * legitimately reads host.ADPass back to join a domain, so that field is
 * stripped from lists and kept on a single GET. There is no equivalent
 * legitimate reader of an audit row's old password, so anything in either
 * tier is redacted here.
 *
 * @category Redaction
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Redaction extends FOGBase
{
    /**
     * A friendly key matching this is a credential until said otherwise.
     *
     * Substring, not whole word, and that is the point: ADPass, ADPassLegacy
     * and clientSecret are all credentials and none of them is the bare word.
     * The cost of the wide net is the exempt list below, which is four
     * entries and reviewable.
     *
     * KEY is in it because productKey, pub_key and storagenode.key are all
     * secrets. It is also the noisiest term, which is why hotkey and
     * keysequence are exempted rather than the term being dropped.
     *
     * @var string
     */
    const CREDENTIAL_PATTERN = '#(PASS|PWD|SECRET|TOKEN|KEY)#i';
    /**
     * Keys that match the pattern and are not credentials.
     *
     * Per class, not per key name: `hotkey` being harmless on a PXE menu says
     * nothing about a field of that name appearing on some other model later.
     *
     * Every entry is checked against the tree by
     * tests/audit-redaction-coverage.test.php, so an exemption cannot outlive
     * the column it was written for and silently cover a future one.
     *
     * @var array class => [friendly keys]
     */
    public static $patternExempt = [
        // hostInfoLock: a boolean saying the host's info key is locked, not
        // the key itself. host.token IS the key and is in Route's tier 1.
        'host' => [
            'tokenlock',
        ],
        // A menu hotkey is a keyboard key, and keysequence is the Konami-code
        // style unlock sequence for a menu entry -- neither is a secret, and
        // the sequence is already rendered into the iPXE menu in clear.
        'pxemenuoptions' => [
            'hotkey',
            'keysequence',
        ],
        // passreset is "reset the password on next boot", a flag; and
        // bypassbitlocker matches only because "bypass" contains "pass".
        'task' => [
            'passreset',
            'bypassbitlocker',
        ],
    ];
    /**
     * Cached per-class union of both Route tiers plus the exempt list.
     *
     * @var array|null
     */
    private static $_map = null;
    /**
     * Normalizes a class name to the key Route's registries use.
     *
     * Callers arrive with 'Host', 'FOG\Host' and 'host' depending on whether
     * they had an object, a class string or a route parameter.
     *
     * @param mixed $class object or class name
     *
     * @return string
     */
    private static function _key($class)
    {
        return strtolower(trim(self::shortName($class)));
    }
    /**
     * The declared-sensitive keys for a class, both tiers unioned.
     *
     * @param mixed $class object or class name
     *
     * @return array lowercased friendly keys
     */
    public static function declaredFor($class)
    {
        if (null === self::$_map) {
            // sensitiveFieldMap() fires API_SENSITIVE_FIELDS, and
            // processEvent() reaches Route::getIds('hookevent') -- so it
            // needs a booted FOG with a database. Outside one (CI, the test
            // harness) read the core tiers directly.
            //
            // This is the same degradation Route itself performs: it
            // pre-memoizes with the core tiers BEFORE firing the event, so a
            // re-entrant caller also sees core-only. A caller can therefore
            // miss a PLUGIN-declared field, never a core one -- and anything
            // credential-shaped is still caught by CREDENTIAL_PATTERN below,
            // which is the whole reason the pattern is the first layer.
            //
            // Not a try/catch around a maybe-API: the condition is a fact
            // about whether FOG is booted, tested directly.
            $map = self::$HookManager instanceof HookManager
                ? Route::sensitiveFieldMap()
                : [
                    'fields' => Route::$sensitiveFields,
                    'always' => Route::$sensitiveAlwaysFields
                ];
            $merged = [];
            foreach (['fields', 'always'] as $tier) {
                foreach ((array)($map[$tier] ?? []) as $cls => $keys) {
                    $cls = self::_key($cls);
                    foreach ((array)$keys as $key) {
                        $merged[$cls][strtolower($key)] = true;
                    }
                }
            }
            self::$_map = $merged;
        }

        return array_keys(self::$_map[self::_key($class)] ?? []);
    }
    /**
     * Is this field exempted from the pattern for this class?
     *
     * @param mixed  $class object or class name
     * @param string $field friendly key
     *
     * @return bool
     */
    public static function isPatternExempt($class, $field)
    {
        $exempt = array_map(
            'strtolower',
            (array)(self::$patternExempt[self::_key($class)] ?? [])
        );

        return in_array(strtolower((string)$field), $exempt, true);
    }
    /**
     * Must this field's value be withheld?
     *
     * @param mixed  $class object or class name
     * @param string $field friendly key
     *
     * @return bool
     */
    public static function isSensitive($class, $field)
    {
        $field = strtolower(trim((string)$field));
        if ('' === $field) {
            return false;
        }
        // The registry wins outright. A declared credential is a credential
        // even if somebody also, wrongly, exempted it.
        if (in_array($field, self::declaredFor($class), true)) {
            return true;
        }
        if (self::isPatternExempt($class, $field)) {
            return false;
        }

        return 1 === preg_match(self::CREDENTIAL_PATTERN, $field);
    }
    /**
     * The pair of values an auditChange row may record for this field.
     *
     * Redacted means NULL in both columns and `redacted = 1`. Not a masked
     * string, not a length, not a hash: the fact worth keeping is "this
     * column changed", and anything derived from the value is a disclosure
     * with extra steps.
     *
     * @param mixed  $class object or class name
     * @param string $field friendly key
     * @param mixed  $old   value before
     * @param mixed  $new   value after
     *
     * @return array ['old' => mixed, 'new' => mixed, 'redacted' => int]
     */
    public static function values($class, $field, $old, $new)
    {
        if (self::isSensitive($class, $field)) {
            return ['old' => null, 'new' => null, 'redacted' => 1];
        }

        return ['old' => $old, 'new' => $new, 'redacted' => 0];
    }
    /**
     * Drops the memoized map. For tests that mutate the registries.
     *
     * @return void
     */
    public static function resetCache()
    {
        self::$_map = null;
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\Redaction', 'Redaction');
