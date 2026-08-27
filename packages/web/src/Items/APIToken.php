<?php
/**
 * One hashed API token belonging to one user.
 *
 * PHP version 7.4+
 *
 * @category APIToken
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Audit\Audit;
use FOG\Base\FOGController;

/**
 * One hashed API token belonging to one user.
 *
 * ADR 0027. A Bearer credential, separate from users.uAPIToken rather than a
 * new spelling of it: uAPIToken stays plaintext, stays visible in the UI and
 * keeps working as fog-user-token beside fog-api-token, untouched. These are
 * stored hashed, shown once, and are the only thing Authorization: Bearer
 * accepts.
 *
 * DELIBERATELY ABSENT FROM Route::$validClasses. A token-management REST
 * surface would let one API credential mint another, which turns any leaked
 * token into a permanent foothold that survives revoking the one that leaked.
 * Creation and revocation are UI actions, behind a session and CSRF.
 *
 * @category APIToken
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class APIToken extends FOGController
{
    /**
     * Prefix every issued token carries.
     *
     * Present so a token is recognisable on sight -- in a log, a pasted
     * config, a support ticket -- and so leaked-credential scanners have
     * something to match. It is part of the token: the hash covers the whole
     * string, so there is nothing to parse at verification time.
     *
     * @var string
     */
    const PREFIX = 'fog_';

    /**
     * How stale atLastUsed may get before a request refreshes it.
     *
     * Recording last use is what makes it safe to delete a token nobody can
     * account for. Writing it on EVERY request would put an UPDATE on the
     * hot path of every API call, so it is refreshed at most this often --
     * the question the column answers is "was this used this month", and
     * five minutes of resolution answers it just as well.
     *
     * @var int
     */
    const LAST_USED_TTL = 300;

    /**
     * generate()'s answer when the owner already has a token by that name.
     *
     * A distinct sentinel rather than false, because the caller has to tell
     * "you already used that name" from "the write failed" -- the first is
     * something the administrator fixes by typing a different word, the
     * second is not.
     *
     * NOT a UNIQUE index on (atUserID, atName), which is the obvious way to
     * do this and is actively dangerous here: FOGController::save() writes
     * with INSERT ... ON DUPLICATE KEY UPDATE, so a duplicate name would
     * not be rejected -- it would UPDATE the existing row, replacing a live
     * credential's hash with a new one. The old token would stop working,
     * the new one would appear to be the old one, and no audit row would
     * say a token had been revoked. See the silent-overwrite bug class.
     *
     * @var string
     */
    const DUPLICATE_NAME = 'duplicate-name';

    /**
     * The table.
     *
     * @var string
     */
    protected $databaseTable = 'apiTokens';

    /**
     * Friendly names to column names.
     *
     * createdTime and createdBy are spelled as history, taskLog and auditLog
     * spell them, so one viewer reads all of them.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'atID',
        'userID' => 'atUserID',
        'name' => 'atName',
        'hash' => 'atHash',
        'enabled' => 'atEnabled',
        'createdTime' => 'atCreatedTime',
        'createdBy' => 'atCreatedBy',
        'lastUsed' => 'atLastUsed'
    ];

    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'userID',
        'hash'
    ];

    /**
     * Hashes a presented token into what the table stores.
     *
     * Unsalted SHA-256, and that is a decision rather than an omission.
     *
     * A salt defeats PRECOMPUTATION -- one rainbow table or one dictionary
     * pass cracking many hashes at once -- and that requires a guessable
     * input. A token from generate() is 512 bits of CSPRNG output: there is
     * no dictionary and no constructible table, so a salt protects against
     * nothing here while costing the ability to look a token up at all. With
     * a per-row salt you cannot compute the hash until you already know
     * which row you are checking, which means either scanning every token
     * per request or embedding a lookup id in the token.
     *
     * A pepper is the same story plus a rotation problem: changing the key
     * invalidates every token at once. bcrypt is wrong for a different
     * reason -- it is deliberately slow, and that cost would be paid on
     * every API request rather than once per login.
     *
     * THE INVARIANT THIS RESTS ON: tokens stay CSPRNG-generated and at least
     * 256 bits. If one is ever shortened, made user-choosable, or derived
     * from anything predictable, this reasoning is void and salting becomes
     * necessary. See ADR 0027.
     *
     * @param string $token The presented token, exactly as sent.
     *
     * @return string 64 hex characters.
     */
    public static function hashToken($token)
    {
        return hash('sha256', (string)$token);
    }

    /**
     * Issues a new token for a user and stores only its hash.
     *
     * Returns the plaintext, which is the ONLY time it exists anywhere. The
     * caller shows it once; nothing -- not this class, not the UI, not the
     * database -- can produce it again.
     *
     * @param int    $userID The owner.
     * @param string $name   What the token is for.
     *
     * @return string|false The plaintext token, false if it did not store,
     *                      or self::DUPLICATE_NAME if the owner already has
     *                      one by that name.
     */
    public static function generate($userID, $name = '')
    {
        $userID = (int)$userID;
        if ($userID < 1) {
            return false;
        }
        $name = trim((string)$name);
        if ('' === $name) {
            return false;
        }
        // Unique per USER, not per server. Two people can each have a
        // token called "backup script" without ambiguity; one person with
        // two of them cannot tell which row to revoke, which is the whole
        // job this page exists to do.
        //
        // Checked here rather than by a unique index -- see DUPLICATE_NAME
        // for why an index would silently overwrite instead of refusing.
        // That leaves a check-then-insert race, which is the right trade
        // for a button a human clicks: the worst case is two rows with one
        // name, which is untidy, against an index whose worst case is a
        // working credential replaced without a trace.
        if (self::nameTaken($userID, $name)) {
            return self::DUPLICATE_NAME;
        }
        // 512 bits, the same generator users.uAPIToken uses. The prefix is
        // part of the token and therefore part of what gets hashed.
        $token = self::PREFIX . bin2hex(random_bytes(64));
        $row = self::getClass('APIToken')
            ->set('userID', $userID)
            ->set('name', $name)
            ->set('hash', self::hashToken($token))
            ->set('enabled', '1');
        if (!$row->save()) {
            // save() has a history of returning $this over a swallowed SQL
            // error, so a token whose hash did not store must not be handed
            // back as though it works -- it would authenticate nothing and
            // the user would have no way to tell.
            return false;
        }
        $row->audit(Audit::TOKEN_ISSUED, 'apitoken.create');
        return $token;
    }

    /**
     * Does this user already have a token by this name?
     *
     * Compared case-insensitively and on the trimmed value, because the
     * column exists to be read by a person deciding what to revoke and
     * "Backup" beside "backup " is the ambiguity the uniqueness rule is
     * there to prevent -- an exact-match check would let both exist.
     *
     * @param int    $userID The owner.
     * @param string $name   The proposed name.
     *
     * @return bool
     */
    public static function nameTaken($userID, $name)
    {
        $userID = (int)$userID;
        $name = trim((string)$name);
        if ($userID < 1 || '' === $name) {
            return false;
        }
        $rows = self::$DB
            ->query(
                'SELECT COUNT(*) AS `cnt` FROM `apiTokens`'
                . ' WHERE `atUserID` = :uid'
                . ' AND LOWER(TRIM(`atName`)) = LOWER(:name)',
                [],
                [':uid' => $userID, ':name' => $name]
            )
            ->fetch()
            ->get();
        return (int)($rows['cnt'] ?? 0) > 0;
    }
    /**
     * Enables or disables this token, recording the change.
     *
     * Here rather than at the call sites because there are two of them --
     * the user's API tab and the central pane -- and an audit trail with a
     * hole in it is worse than none: it invites the conclusion that nothing
     * happened.
     *
     * @param bool $enabled What to set it to.
     *
     * @return bool Whether anything changed.
     */
    public function setEnabled($enabled)
    {
        $want = $enabled ? '1' : '0';
        if ($want === (string)$this->get('enabled')) {
            // Not a no-op worth recording. A save button that touches
            // nothing should not fill the audit log with rows saying so.
            return false;
        }
        $this->set('enabled', $want)->save();
        $this->audit(
            $enabled ? Audit::TOKEN_ENABLED : Audit::TOKEN_DISABLED,
            'apitoken.edit'
        );
        return true;
    }

    /**
     * Deletes this token, recording it first.
     *
     * Recorded before the destroy because afterwards there is nothing left
     * to read the owner and name off, and nothing else in the system
     * remembers that this token existed. What that ordering costs, and why
     * the row is shaped the way it is, is in audit() below.
     *
     * @return void
     */
    public function revoke()
    {
        $this->audit(Audit::TOKEN_DELETED, 'apitoken.delete');
        $this->destroy();
    }

    /**
     * Writes one audit row about this token.
     *
     * WHY THE SUBJECT IS THE TOKEN AND THE OWNER IS IN THE TEXT, which is
     * the opposite of what it looks like it should be.
     *
     * Audit::record() sets itself as Audit::$_current, and
     * FOGController::destroy() calls Audit::identify() -- which REVISES
     * $_current in place, stamping the destroyed object's own type, id and
     * name over whatever was there (ADR 0021 Decision 7: a delete's header
     * is the only record it leaves). So a row written here naming the OWNER
     * as its subject silently becomes a row naming the TOKEN, moments
     * later, with nothing logged and no error.
     *
     * Reordering does not fix it. Recording AFTER destroy() works for one
     * token and corrupts the previous one in a loop, because the next
     * destroy()'s identify() reaches back into the row the last record()
     * left as $_current -- which is exactly what the central pane's
     * multi-delete does.
     *
     * So every row here uses the subject identify() would impose anyway.
     * The two agree, the rewrite is a no-op, and the owner goes in `text`,
     * which identify() does not touch. All four token events are written
     * the same way so the log reads uniformly rather than the delete being
     * shaped differently from its siblings.
     *
     * The token and its hash are never recorded: a credential does not go
     * in a log (#1261/#1262).
     *
     * @param string $type       An Audit TOKEN_* constant.
     * @param string $permission The permission this exercised.
     *
     * @return void
     */
    public function audit($type, $permission)
    {
        $ownerID = (int)$this->get('userID');
        $owner = self::getClass('User', $ownerID);
        Audit::record(
            [
                'type' => $type,
                'subjectType' => 'apitoken',
                'subjectID' => (int)$this->get('id'),
                'subjectLabel' => (string)$this->get('name'),
                'permission' => $permission,
                // The fact the subject cannot carry. A token id means
                // nothing once the row is gone; whose credential it was is
                // the whole question anybody brings to this log.
                'text' => sprintf(
                    'owner=%s (%d)',
                    $owner->isValid() ? $owner->get('name') : '(deleted user)',
                    $ownerID
                ),
                'affectedCount' => 1,
                'renderable' => 1
            ]
        );
    }

    /**
     * Resolves a presented token to the user it authenticates as.
     *
     * @param string $token The presented token, exactly as sent.
     *
     * @return User|null The owner, or null when the token is unusable.
     */
    public static function resolve($token)
    {
        $token = trim((string)$token);
        if ('' === $token) {
            return null;
        }
        $row = self::getClass('APIToken')
            ->set('hash', self::hashToken($token))
            ->load('hash');
        if (!$row->isValid()) {
            return null;
        }
        // The per-token kill switch. Independent of users.uAllowAPI, which
        // governs fog-user-token and is not consulted here (ADR 0027).
        if ('1' !== (string)$row->get('enabled')) {
            return null;
        }
        $user = self::getClass('User', (int)$row->get('userID'));
        if (!$user->isValid()) {
            // The owner is gone but the token is not. Destroying a user is
            // supposed to destroy its tokens; refuse rather than authenticate
            // as nobody, so a missed cascade fails closed instead of leaving
            // a working ownerless credential.
            return null;
        }
        $row->touch();
        return $user;
    }

    /**
     * Records that this token was used, at most once per LAST_USED_TTL.
     *
     * @return void
     */
    public function touch()
    {
        $last = trim((string)$this->get('lastUsed'));
        // Only ever written with a real datetime. FOG has a standing defect
        // class where save() puts '' into a date column and the cleared
        // sql_mode stores it as 0000-00-00, which would make "never used"
        // indistinguishable from "used at the epoch" -- the fact this column
        // exists to record.
        if ('' !== $last && strtotime($last) > (time() - self::LAST_USED_TTL)) {
            return;
        }
        $this
            ->set('lastUsed', self::formatTime('now', 'Y-m-d H:i:s'))
            ->save();
    }
}
