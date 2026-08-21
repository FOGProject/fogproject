<?php
/**
 * Writes the audit trail.
 *
 * PHP version 7.4+
 *
 * @category Audit
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * Writes the audit trail.
 *
 * The one place that creates AuditLog rows. Callers describe what happened;
 * this fills in the ambient facts about the request -- who, from where, how
 * they authenticated, which request this was -- so no call site has to
 * remember them and none of them can disagree.
 *
 * ADR 0021.
 *
 * THE CORRELATION ID IS REQUEST-SCOPED STATIC STATE, on purpose. FOG already
 * does this deliberately in the closest analogous place: Route::$_rethrowDepth
 * is static specifically so a nested call inherits an outer decision, and its
 * docblock says so. A correlation id is the same thing.
 *
 * It is generated WITHOUT firing a hook and read without one, and that is
 * load bearing rather than stylistic. Route::sensitiveFieldMap() carries
 * forty lines on the trap: processEvent() populates its known-event list by
 * calling Route::getIds('hookevent'), which re-enters Route, which asks for
 * the map again -- "an OOM in ~40 frames". Anything on this path that fired
 * an event could do the same, and it would do it inside the code that is
 * supposed to be recording the failure.
 *
 * NOTHING HERE THROWS. An audit write must not turn a working login into a
 * 500. record() returns the row or false, and the callers that MUST NOT
 * proceed without a row -- shrinking or disabling the trail, ADR 0021
 * Decision 10 -- are the ones that check it. That is a deliberate split:
 * silence is acceptable for recording an event, never for reducing the
 * record.
 *
 * @category Audit
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Audit extends FOGBase
{
    /**
     * The action was permitted and carried out.
     */
    const ALLOWED = 'allowed';
    /**
     * Authorization or authentication refused it.
     */
    const DENIED = 'denied';
    /**
     * It was permitted and then went wrong.
     */
    const FAILED = 'failed';
    /**
     * An iterating action where some objects landed and some did not.
     */
    const PARTIAL = 'partial';
    /**
     * Authentication event types.
     *
     * Dot-namespaced so a reader can filter a family with a prefix, and
     * machine-readable rather than prose: the sentence a person reads is
     * built from these at READ time, in the reader's locale (ADR 0020
     * Decision 5).
     */
    const LOGIN = 'auth.login';
    const LOGOUT = 'auth.logout';
    const LOGIN_FAILED = 'auth.login.failed';
    const TOKEN_REJECTED = 'auth.token.rejected';
    const API_DENIED = 'auth.api.denied';
    /**
     * The synthetic actor for a write no person made.
     *
     * Not a new convention: FOGController::save()'s createdBy auto-fill
     * already produces exactly this when no user is valid.
     */
    const MACHINE_ACTOR = 'fog';
    /**
     * This request's correlation id, or null before one was needed.
     *
     * @var string|null
     */
    private static $_correlationID = null;
    /**
     * The id shared by every audit row this request produces.
     *
     * Generated on first use rather than at boot: most requests audit
     * nothing, and an id nobody records is 16 bytes of entropy spent for
     * nothing.
     *
     * random_bytes() rather than uniqid(): the id is a join key that appears
     * in an operator-visible log, and a time-derived one leaks request timing
     * and collides under concurrency, which would silently merge two people's
     * actions into one apparent operation.
     *
     * @return string 32 hex characters
     */
    public static function correlationID()
    {
        if (null === self::$_correlationID) {
            try {
                self::$_correlationID = bin2hex(random_bytes(16));
            } catch (\Exception $e) {
                // random_bytes() throws only when the platform has no usable
                // CSPRNG. Not a reason to lose the audit row: fall back to a
                // value that is still unique enough to JOIN on within one
                // request, which is all this field is for. It is not a
                // secret and nothing authenticates with it.
                self::$_correlationID = md5(uniqid((string)mt_rand(), true));
            }
        }

        return self::$_correlationID;
    }
    /**
     * Records one action.
     *
     * @param array $row type, subjectType, subjectID, subjectLabel,
     *                   permission, outcome, affectedCount, renderable, text,
     *                   createdBy, authSource. Everything has a default;
     *                   `type` is the only one worth always passing.
     *
     * @return object|false the AuditLog row, or false if it did not store
     */
    public static function record(array $row)
    {
        try {
            $audit = self::getClass('AuditLog')
                ->set('correlationID', self::correlationID())
                ->set('createdBy', $row['createdBy'] ?? self::_actor())
                ->set('ip', (string)self::$remoteaddr)
                ->set('authSource', $row['authSource'] ?? self::_authSource())
                ->set('type', (string)($row['type'] ?? ''))
                ->set('subjectType', (string)($row['subjectType'] ?? ''))
                ->set('subjectID', (int)($row['subjectID'] ?? 0))
                ->set('subjectLabel', (string)($row['subjectLabel'] ?? ''))
                ->set('permission', (string)($row['permission'] ?? ''))
                ->set('outcome', (string)($row['outcome'] ?? self::ALLOWED))
                ->set('affectedCount', (int)($row['affectedCount'] ?? 0))
                ->set('renderable', empty($row['renderable']) ? 0 : 1)
                ->set('text', (string)($row['text'] ?? ''));
            // createdTime is left to save()'s auto-fill, which is where every
            // other model's gets set, so an audit row cannot disagree with a
            // history row about what "now" means.
            $audit->save();
        } catch (\Exception $e) {
            // save() has historically returned success on a swallowed SQL
            // error, so a throw is not the only failure -- see below. This
            // catch exists because the alternative is an exception escaping
            // into a login handler.
            self::_writeFailed((string)$e->getMessage());
            return false;
        }

        // The real check. FOGController::save() sets `id` from insertId on a
        // successful INSERT and throws when it cannot -- but PDODB does not
        // throw on query error by default, so "no id" is the reliable signal
        // that nothing was stored.
        if (!filter_var(
            $audit->get('id'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        )) {
            self::_writeFailed(_('no row id after save'));
            return false;
        }

        return $audit;
    }
    /**
     * Records the change rows for one subject.
     *
     * Callers pass the diff; Redaction decides which values may be stored.
     * A caller must NOT pre-filter, because a caller that decides for itself
     * is exactly how a credential ends up in a log -- twice in one week, by
     * two different subsystems (ADR 0021 Context 4).
     *
     * @param object $audit       the header returned by record()
     * @param mixed  $class       the model, or its name, the fields belong to
     * @param int    $subjectID   which object these changes are for
     * @param array  $diff        friendly key => [old, new]
     *
     * @return int how many change rows stored
     */
    public static function changes($audit, $class, $subjectID, array $diff)
    {
        if (!$audit instanceof AuditLog || !$audit->isValid()) {
            return 0;
        }
        $subjectType = strtolower(self::shortName($class));
        $stored = 0;
        foreach ($diff as $field => $pair) {
            $values = Redaction::values(
                $class,
                $field,
                $pair[0] ?? null,
                $pair[1] ?? null
            );
            try {
                $change = self::getClass('AuditChange')
                    ->set('auditID', (int)$audit->get('id'))
                    ->set('subjectType', $subjectType)
                    ->set('subjectID', (int)$subjectID)
                    ->set('field', (string)$field)
                    ->set('oldValue', $values['old'])
                    ->set('newValue', $values['new'])
                    ->set('redacted', $values['redacted']);
                $change->save();
                if ($change->get('id')) {
                    $stored++;
                }
            } catch (\Exception $e) {
                self::_writeFailed((string)$e->getMessage());
            }
        }

        return $stored;
    }
    /**
     * Who is acting, as far as this request knows.
     *
     * @return string
     */
    private static function _actor()
    {
        if (self::$FOGUser instanceof User
            && self::$FOGUser->isValid()
            && self::$FOGUser->get('name')
        ) {
            return (string)self::$FOGUser->get('name');
        }

        return self::MACHINE_ACTOR;
    }
    /**
     * How this REQUEST authenticated -- not how the account is configured.
     *
     * @return string
     */
    private static function _authSource()
    {
        return (string)User::sessionAuthSource();
    }
    /**
     * An audit write did not store. Say so somewhere that is not this table.
     *
     * error_log rather than logHistory(): logHistory() returns early unless a
     * user is valid, and the events most worth recording -- a rejected login,
     * a machine path -- are precisely the ones with no valid user. A logger
     * whose failure path shares the failure mode it is reporting records
     * nothing, which is the bug #1257/#1258 fixed and the one
     * logging-must-not-depend-on-logging describes.
     *
     * @param string $why what went wrong
     *
     * @return void
     */
    private static function _writeFailed($why)
    {
        error_log(
            sprintf('%s: %s', _('Audit row was not stored'), $why)
        );
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\Audit', 'Audit');
