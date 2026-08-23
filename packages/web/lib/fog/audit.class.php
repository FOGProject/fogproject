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
     * How much of a failure reason markOutcome() stores.
     */
    const MAX_DETAIL = 512;
    /**
     * The synthetic actor for a write no person made.
     *
     * Not a new convention: FOGController::save()'s createdBy auto-fill
     * already produces exactly this when no user is valid.
     */
    const MACHINE_ACTOR = 'fog';
    /**
     * The authSource for a write no credential was presented for.
     *
     * FOS's task and registration endpoints identify a host by the MAC in
     * the request and nothing else -- FOGBase::getHostItem() reads the mac,
     * normalizes it and looks the host up; there is no token check on that
     * path. So "anonymous" is the accurate word, not a placeholder, and
     * together with the empty `permission` these rows carry it makes FOG's
     * whole unauthenticated write surface a two-column query (ADR 0021
     * Decision 4).
     */
    const SOURCE_ANONYMOUS = 'anonymous';
    /**
     * This request's correlation id, or null before one was needed.
     *
     * @var string|null
     */
    private static $_correlationID = null;
    /**
     * The header this request wrote at its authorization gate, if any.
     *
     * @var object|null
     */
    private static $_current = null;
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

        self::$_current = $audit;

        return $audit;
    }
    /**
     * The header this request wrote at its authorization gate.
     *
     * Merge 6 hangs change rows off it: the model layer knows WHAT changed
     * and nothing about who was allowed to do it, so it reads the header
     * from here rather than being handed one through forty call sites.
     *
     * @return object|null
     */
    public static function current()
    {
        return self::$_current;
    }
    /**
     * Names the subject on a header written before the subject existed.
     *
     * Registration is the case this exists for: the interesting fact is that
     * a host was created, and the id it was created with does not exist
     * until the INSERT returns. Recording the header first is what lets the
     * create's own auditChange rows attach to it; this fills in the two
     * fields that could not be known then.
     *
     * Same standing as markOutcome(): it revises a row written moments
     * earlier in the same request, before anybody could have read it, and it
     * cannot reach an older one.
     *
     * @param string $subjectType lowercased class name
     * @param int    $subjectID   the id the insert produced
     * @param string $subjectLabel a name that stays readable after a delete
     *
     * @return void
     */
    public static function identify($subjectType, $subjectID, $subjectLabel = '')
    {
        if (!self::$_current instanceof AuditLog
            || !self::$_current->isValid()
        ) {
            return;
        }
        try {
            self::$_current
                ->set('subjectType', strtolower((string)$subjectType))
                ->set('subjectID', (int)$subjectID)
                ->set('subjectLabel', (string)$subjectLabel)
                ->save();
        } catch (\Exception $e) {
            self::_writeFailed((string)$e->getMessage());
        }
    }
    /**
     * Revises this request's header now the outcome is known.
     *
     * The gate can only say "this was allowed". Whether it then WORKED is
     * known later, at the response -- and "allowed, and it failed" is a
     * different fact from "allowed", particularly when someone is reading
     * the trail to find out why a change did not stick.
     *
     * This is the one UPDATE the audit trail performs on itself, and it is
     * not in tension with append-only: it revises a row written moments
     * earlier in the same request, before anybody could have read it. There
     * is no path here that revises an OLDER row, and none that lowers a
     * denial to anything else.
     *
     * $detail is why. Without it a `failed` row says only that something
     * went wrong, and the trail cannot answer the first question anybody
     * asks of it -- every call site below already holds the reason (the
     * HTTP status it is about to send, the message it is about to throw)
     * and used to discard it on the way past.
     *
     * Untranslated, like every other alText: this is machine detail, and a
     * reason stored in the locale of whoever happened to trip it is not
     * greppable six months later.
     *
     * It does NOT overwrite. A row whose text was set at record() time
     * carries a more specific reason than anything known here, so the
     * detail fills an empty field and otherwise stands aside.
     *
     * @param string $outcome one of the outcome constants
     * @param string $detail  untranslated reason, or '' when none is known
     *
     * @return void
     */
    public static function markOutcome($outcome, $detail = '')
    {
        if (!self::$_current instanceof AuditLog
            || !self::$_current->isValid()
        ) {
            return;
        }
        // A denial is final. Nothing that happens afterwards can turn
        // "refused" into "failed", and letting it would lose the only row
        // that says somebody was turned away.
        if (self::DENIED === self::$_current->get('outcome')) {
            return;
        }
        try {
            self::$_current->set('outcome', (string)$outcome);
            $detail = (string)$detail;
            if ('' !== $detail && '' === (string)self::$_current->get('text')) {
                // alText is longtext, so the cap is not about the column. A
                // PDOException carries the whole failing statement and every
                // bound placeholder, and this row is read in a grid.
                self::$_current->set('text', self::_trim($detail));
            }
            self::$_current->save();
        } catch (\Exception $e) {
            self::_writeFailed((string)$e->getMessage());
        }
    }
    /**
     * Bounds a reason string to something a log viewer can print.
     *
     * @param string $detail the reason
     *
     * @return string
     */
    private static function _trim($detail)
    {
        return strlen($detail) > self::MAX_DETAIL
            ? substr($detail, 0, self::MAX_DETAIL) . '...'
            : $detail;
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
