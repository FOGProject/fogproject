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

namespace FOG;

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
     * @return string|false The plaintext token, or false if it did not store.
     */
    public static function generate($userID, $name = '')
    {
        $userID = (int)$userID;
        if ($userID < 1) {
            return false;
        }
        // 512 bits, the same generator users.uAPIToken uses. The prefix is
        // part of the token and therefore part of what gets hashed.
        $token = self::PREFIX . bin2hex(random_bytes(64));
        $row = self::getClass('APIToken')
            ->set('userID', $userID)
            ->set('name', trim((string)$name))
            ->set('hash', self::hashToken($token))
            ->set('enabled', '1');
        if (!$row->save()) {
            // save() has a history of returning $this over a swallowed SQL
            // error, so a token whose hash did not store must not be handed
            // back as though it works -- it would authenticate nothing and
            // the user would have no way to tell.
            return false;
        }
        return $token;
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

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\APIToken', 'APIToken');
