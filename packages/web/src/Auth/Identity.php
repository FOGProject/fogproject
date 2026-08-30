<?php
/**
 * The two identities a session can carry.
 *
 * PHP version 7.4+
 *
 * @category Identity
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Auth;

use FOG\Audit\Audit;
use FOG\Base\FOGBase;
use FOG\Items\User;

/**
 * The two identities a session can carry.
 *
 * Impersonation lets an administrator see FOG exactly as another user sees
 * it -- the whole point being that "your dates are in the wrong timezone" is
 * answerable without a phone call. That makes one session carry two
 * identities, and every "current user" read has to pick one. Left implicit,
 * the split goes wrong in the direction that matters most.
 *
 * THE SPLIT
 *
 *   Permissions, site scope and userPrefs  -> the IMPERSONATED user.
 *   alCreatedBy and the session's own auth -> the REAL user.
 *
 * `$_SESSION['FOG_USER']` therefore keeps holding the REAL administrator for
 * the life of the span, and the mask lives in a key of its own. That
 * direction is deliberate and is the single most load-bearing decision here.
 * LoadGlobals binds $GLOBALS['currentUser'] from the mask, and FOGBase binds
 * self::$FOGUser as a REFERENCE to that global (fogbase.php _init), so ONE
 * assignment moves permissions, SiteScope, displayTimeZone(), displayTheme(),
 * the sidebar and the menu together. Audit::_actor() reads the same
 * reference, so the same one assignment would have silently rewritten
 * alCreatedBy to the impersonated user -- an audit trail naming somebody who
 * did not act is worse than no audit trail at all, because it destroys
 * repudiation for the one person who can prove nothing.
 *
 * Putting the REAL user in the pre-existing key is what makes that fail
 * safely rather than dangerously. Anything that reads FOG_USER and was never
 * found during this work keeps naming the administrator, which is true;
 * anything on the view side that was missed simply shows the administrator
 * their own view, which is visible and harmless. Had the target been stored
 * there instead, every reader nobody found would have attributed to the
 * target, silently.
 *
 * `FOG_AUTH_SOURCE` is deliberately NOT touched. It records how THIS SESSION
 * was made (User::sessionAuthSource()), and the session was made by the
 * administrator's password or identity provider. Rewriting it would corrupt
 * the break-glass counting that establishSession() exists to keep honest.
 *
 * WHAT MAY BE DONE WHILE MASKED
 *
 * Reads, plus the impersonated user's own preferences. Nothing else. That is
 * an allowlist rather than a list of forbidden operations, and the reason is
 * on file in this repository: ADR 0021 records `storagenode.pass` leaking
 * because the secrets registry enumerated fields per route -- "naming them
 * per route is what hid this". A refusal list has to be re-audited every time
 * a route is added; an allowlist does not. It also closes a hole a refusal
 * list leaves open, because FOGController::save() auto-fills `createdBy` from
 * self::$FOGUser, so an ordinary create performed while masked would stamp
 * the target's name onto the row itself -- a second attribution forgery that
 * no audit column repairs.
 *
 * ONE LEVEL DEEP, ALWAYS. begin() refuses while a span is open, so
 * "impersonate another" is end-then-start and the audit never has to answer
 * "acting as B, who was being acted as by A".
 *
 * @category Identity
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Identity extends FOGBase
{
    /**
     * The real administrator. Never rewritten by impersonation.
     */
    const SESSION_REAL = 'FOG_USER';
    /**
     * The impersonated user id, when a span is open.
     */
    const SESSION_MASK = 'FOG_IMPERSONATE';
    /**
     * The span identifier shared by every row inside the bracket.
     */
    const SESSION_SPAN = 'FOG_IMPERSONATE_SPAN';
    /**
     * Audit event types bracketing a span (ADR 0021's alType).
     */
    const START = 'impersonation.start';
    const END = 'impersonation.end';
    /**
     * A refused attempt to start one.
     */
    const REFUSED = 'impersonation.refused';
    /**
     * The permission that lets somebody start a span at all.
     *
     * The subset tests below are the real authority -- they decide WHO you
     * may become -- but they are not a grant. Without a permission of its
     * own, every account able to administer anything could impersonate
     * everybody beneath it, with no way for an administrator to hand the
     * capability to a helpdesk role and withhold it from another.
     */
    const PERMISSION = 'impersonate.start';
    /**
     * Whether an impersonated user is told, on their next sign-in, that an
     * administrator viewed their account.
     */
    const NOTIFY_SETTING = 'FOG_IMPERSONATION_NOTIFY';
    /**
     * Memoized real-user name, so one request's audit rows share one read.
     *
     * @var string|null
     */
    private static $_realName = null;
    /**
     * Memoized impersonated-user name.
     *
     * @var string|null
     */
    private static $_maskName = null;
    /**
     * The real administrator's user id, whatever mask is in place.
     *
     * @return int
     */
    public static function realUserID()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return 0;
        }

        return (int)($_SESSION[self::SESSION_REAL] ?? 0);
    }
    /**
     * The impersonated user id, or 0 when no span is open.
     *
     * @return int
     */
    public static function impersonatedUserID()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return 0;
        }

        return (int)($_SESSION[self::SESSION_MASK] ?? 0);
    }
    /**
     * The open span's identifier, or '' when no span is open.
     *
     * @return string
     */
    public static function spanID()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }

        return (string)($_SESSION[self::SESSION_SPAN] ?? '');
    }
    /**
     * Is a span open right now?
     *
     * @return bool
     */
    public static function isImpersonating()
    {
        return self::impersonatedUserID() > 0 && '' !== self::spanID();
    }
    /**
     * The real administrator's username, for alCreatedBy.
     *
     * Deliberately reads the users table rather than self::$FOGUser: that
     * property IS the mask while a span is open, which is the whole problem
     * this class exists to solve.
     *
     * @return string '' when there is no session user
     */
    public static function realUserName()
    {
        if (null !== self::$_realName) {
            return self::$_realName;
        }
        self::$_realName = '';
        $id = self::realUserID();
        if ($id < 1) {
            return self::$_realName;
        }
        // Own SQL, for the reason Authorization::rolesHolding() states: this
        // answers a question about WHO ACTED, and routing it through
        // Route::getIds() would bolt the caller's object scope onto it --
        // and the caller is the mask, which may be scoped to nothing.
        try {
            $res = self::$DB->query(
                'SELECT `uName` FROM `users` WHERE `uID` = :uid',
                [],
                ['uid' => $id]
            );
            if (false !== $res->error) {
                return self::$_realName;
            }
            // fetch()->get() with no field, then read the key -- the idiom
            // SiteScope::_count() uses. get('uName') would also work against
            // a single row, but it behaves differently for a row set, and
            // this read must not depend on which it got.
            $row = $res->fetch(\PDO::FETCH_ASSOC)->get();
            self::$_realName = is_array($row)
                ? (string)($row['uName'] ?? '')
                : '';
        } catch (\Exception $e) {
            // An unreadable users table is not a reason to lose the audit
            // row; Audit falls back to its machine actor.
            self::$_realName = '';
        }

        return self::$_realName;
    }
    /**
     * The impersonated user's username, for alActedAs.
     *
     * @return string '' when no span is open
     */
    public static function impersonatedUserName()
    {
        if (!self::isImpersonating()) {
            return '';
        }
        if (null !== self::$_maskName) {
            return self::$_maskName;
        }
        // self::$FOGUser IS the mask while a span is open, so when it is
        // loaded it already holds the answer and costs nothing.
        if (self::$FOGUser instanceof User
            && self::$FOGUser->isValid()
            && (int)self::$FOGUser->get('id') === self::impersonatedUserID()
        ) {
            return self::$_maskName = (string)self::$FOGUser->get('name');
        }

        return self::$_maskName = (string)(new User(
            self::impersonatedUserID()
        ))->get('name');
    }
    /**
     * Bind the mask, if this session carries one that still holds.
     *
     * Called by LoadGlobals AFTER the hook and event managers have loaded,
     * which is not a detail: Authorization::registry() fires
     * PERMISSION_REGISTRY_DATA, so before load() the registry holds core
     * nodes only. The subset test below expands wildcards against that
     * registry, so running it early would expand an administrator's
     * `oidc.*` to nothing while keeping the target's literal `oidc.view` --
     * and refuse a perfectly legal impersonation on every install with a
     * plugin permission.
     *
     * The authority is rechecked on EVERY request rather than trusted from
     * the session. A span must not outlive the grant that allowed it: if the
     * administrator's roles are cut, or the target is put into a site the
     * administrator cannot reach, the mask drops on the next click.
     *
     * @return void
     */
    public static function bind()
    {
        $maskID = self::impersonatedUserID();
        if ($maskID < 1) {
            return;
        }
        $realID = self::realUserID();
        $mask = new User($maskID);
        $why = '';
        if (!$mask->isValid()) {
            $why = 'impersonated user no longer exists';
        } else {
            $why = self::refusalReason($realID, $maskID);
        }
        if ('' !== $why) {
            // Close the span rather than silently continuing as either
            // party. end() records the bracket close, so an authority that
            // was withdrawn mid-span leaves a readable end rather than a
            // dangling start.
            self::end($why);

            return;
        }
        $GLOBALS['currentUser'] = $mask;
        // displayTimeZone() and displayTheme() memoize per request and both
        // read self::$FOGUser. Nothing between LoadGlobals' first date format
        // and here is supposed to populate them, but "supposed to" is not a
        // guarantee, and a stale memo here means the administrator sees their
        // OWN timezone while believing they are seeing the target's -- which
        // is the exact question impersonation was built to answer, silently
        // answered wrong. Cheaper to drop the memos than to reason about it.
        FOGBase::forgetDisplayPreferences();
    }
    /**
     * Every concrete `node.action` a permission list actually grants.
     *
     * Wildcards are expanded against Authorization::registry() so the two
     * sides of the subset test are comparable. An UNDECLARED string is kept
     * verbatim rather than dropped: a permission left behind by an
     * uninstalled plugin is still held, and dropping it from the target's
     * side would make the target look smaller than they are -- which is the
     * one direction this comparison must never err in.
     *
     * @param array $perms permission strings as getPermissions() returns them
     *
     * @return array set of 'node.action' => true
     */
    public static function expandPermissions(array $perms)
    {
        $registry = Authorization::registry();
        $out = [];
        foreach ($perms as $perm) {
            $perm = trim((string)$perm);
            if ('' === $perm || '*' === $perm) {
                continue;
            }
            $node = strstr($perm, '.', true);
            if (false === $node) {
                // A bare node name; assertCanGrant() accepts one.
                foreach ((array)($registry[$perm] ?? []) as $action) {
                    $out[$perm . '.' . $action] = true;
                }
                continue;
            }
            $action = substr($perm, strlen($node) + 1);
            if ('*' === $action) {
                foreach ((array)($registry[$node] ?? []) as $declared) {
                    $out[$node . '.' . $declared] = true;
                }
                continue;
            }
            $out[$perm] = true;
        }

        return $out;
    }
    /**
     * Is the target's permission set inside the impersonator's?
     *
     * @param int $realID   the administrator
     * @param int $targetID the user they want to become
     *
     * @return string '' when allowed, untranslated machine detail otherwise
     */
    public static function permissionRefusal($realID, $targetID)
    {
        $realPerms = Authorization::getPermissions($realID);
        // '*' is not expanded and compared -- it is answered directly,
        // because that is what can() does. A holder of '*' satisfies every
        // permission string INCLUDING ones no registry declares (can()
        // leaves an unregistered node alone and then matches '*'), so
        // expanding it to the registry would make an administrator look
        // NARROWER than they are and refuse on a stale plugin grant.
        if (in_array('*', $realPerms, true)) {
            return '';
        }
        $target = self::expandPermissions(
            Authorization::getPermissions($targetID)
        );
        if (in_array('*', Authorization::getPermissions($targetID), true)) {
            return 'target holds full access and the impersonator does not';
        }
        $mine = self::expandPermissions($realPerms);
        $extra = array_diff_key($target, $mine);
        if (count($extra)) {
            return 'target holds permissions the impersonator does not: '
                . implode(',', array_slice(array_keys($extra), 0, 10));
        }

        return '';
    }
    /**
     * Is the target's site set inside the impersonator's?
     *
     * Site scope is NOT a permission node, so permissionRefusal() above
     * cannot answer this and a single combined test would quietly do only
     * half the job. A Site A administrator must never reach a Site B user,
     * including one whose permissions happen to nest inside theirs.
     *
     * Three things set arithmetic on userSiteIDs() does not give you, in
     * the order they have to be asked:
     *
     *  - No site in use anywhere means scoping is switched off and everyone
     *    reaches everything (SiteScope::sitesInUse()). Both lists are empty
     *    and nest trivially, which is the right answer for the wrong reason,
     *    so it is stated rather than left to fall out.
     *  - A '*' holder, or a CATCH-ALL member, is never narrowed by a site
     *    boundary at all (Authorization::objectInScope and _boundedUserID()
     *    both short circuit), so they are a site superset of everybody by
     *    construction. THIS IS THE LOAD-BEARING ONE: without it a catch-all
     *    administrator, whose id list is just the catch-all site, computes
     *    as reaching FEWER sites than an ordinary two-site user and is
     *    refused everybody.
     *
     * The catch-all check on the TARGET side is belt and braces, and is
     * labeled as such rather than oversold. Today it cannot change an
     * answer: a catch-all member's userSiteIDs() contains the catch-all's
     * id, an impersonator who is not themselves unscoped does not have that
     * id, and the array_diff below refuses on the arithmetic alone --
     * deleting the branch turns no test red by itself. It is kept for two
     * reasons. The refusal REASON is then accurate rather than naming an
     * id; and "all sites" is not really an id list, so an optimization
     * that made userSiteIDs() report a catch-all member as reaching nothing
     * in particular would flip the arithmetic from "refuse" to "the empty
     * set nests inside anything". tests/impersonation-subset-tests.test.php
     * check 8 pins that behavior directly, because nothing else can.
     *
     * Note that isUnscoped() means SEES EVERYTHING while a user in no site
     * at all sees NOTHING. Two opposite conditions, one English word, and
     * this is exactly the place they would first be confused.
     *
     * @param int $realID   the administrator
     * @param int $targetID the user they want to become
     *
     * @return string '' when allowed, untranslated machine detail otherwise
     */
    public static function siteRefusal($realID, $targetID)
    {
        if (!SiteScope::sitesInUse()) {
            return '';
        }
        if (Authorization::isUnrestricted($realID)
            || SiteScope::isUnscoped($realID)
        ) {
            return '';
        }
        if (SiteScope::isUnscoped($targetID)) {
            return 'target is in a catch-all site and the impersonator is not';
        }
        $extra = array_diff(
            SiteScope::userSiteIDs($targetID),
            SiteScope::userSiteIDs($realID)
        );
        if (count($extra)) {
            return 'target reaches sites the impersonator does not: '
                . implode(',', $extra);
        }

        return '';
    }
    /**
     * May the person actually driving this session start an impersonation?
     *
     * ASKS THE REAL ADMINISTRATOR, NEVER THE MASK. Authorization::can() with
     * no user id answers for the EFFECTIVE identity, which while a span is
     * open is the impersonated user -- and that is right for every other
     * permission in FOG, because seeing what they see is the whole point.
     * Starting an impersonation is the one capability that is not theirs to
     * have: it belongs to the administrator underneath.
     *
     * Getting this wrong is not a lockout that announces itself. It refuses
     * "impersonate another" with "you do not have permission to impersonate
     * users", addressed to an administrator who plainly does, because the
     * sentence is true of the mask it accidentally asked about. The first
     * report of it was exactly that: alice holds no roles, so the dialog
     * said no to the administrator wearing her.
     *
     * It also decides the withdrawn-grant case correctly and quietly. If the
     * grant is revoked mid-span this goes false, so "impersonate another"
     * disappears while "end impersonation" -- which is never permission
     * checked, by design -- stays. The way out can never be the control that
     * is missing.
     *
     * The engine already had this right: refusalReason() and begin() both
     * take the real id explicitly. This exists so the DISPLAY gates cannot
     * reach a different answer than the engine will, and
     * tests/impersonation-start-gate-asks-the-real-user.test.php holds them
     * to using it.
     *
     * @return bool
     */
    public static function canStart()
    {
        return Authorization::can(self::PERMISSION, self::realUserID());
    }
    /**
     * May this administrator impersonate this user?
     *
     * Both subset tests must pass, and two edges are decided here rather
     * than left to emerge from set arithmetic:
     *
     *  - ADMIN IMPERSONATES ADMIN IS ALLOWED. '*' is a superset of
     *    everything so it passes the permission test, and refusing it would
     *    buy nothing: neither party gains any access they did not have. The
     *    audit bracket is the control on this, not a refusal.
     *  - A TARGET IN NO SITE AT ALL IS ALLOWED. The empty set nests inside
     *    anything, and here that is not a loophole -- a user in no site
     *    reaches no scoped object, so becoming them cannot widen the
     *    administrator's reach in any direction. It is also the likeliest
     *    real ticket: a new account that sees nothing and cannot say why.
     *
     * @param int $realID   the administrator
     * @param int $targetID the user they want to become
     *
     * @return string '' when allowed, untranslated machine detail otherwise
     */
    public static function refusalReason($realID, $targetID)
    {
        $realID = (int)$realID;
        $targetID = (int)$targetID;
        if ($realID < 1) {
            return 'no real user in session';
        }
        if ($targetID < 1) {
            return 'no target user';
        }
        if ($realID === $targetID) {
            return 'a user cannot impersonate themselves';
        }
        if (!Authorization::can(self::PERMISSION, $realID)) {
            return 'impersonator does not hold ' . self::PERMISSION;
        }
        /*
         * AN API-ONLY ACCOUNT CANNOT BE IMPERSONATED, because impersonating
         * one would MAKE the thing that account exists to forbid.
         *
         * users.uAPIOnly means "may hold API credentials and act with its
         * roles over REST, and no browser session may ever be made for it".
         * User::isAPIOnly()'s docblock enumerates the three places that
         * enforce it, one per way a session comes into existence:
         * passwordValidate(), establishSession(), and _isLoggedIn().
         *
         * Impersonation is a FOURTH way, added later, and it does not
         * pass through any of the three -- begin() writes the mask into the
         * session directly. So without this the flag is simply bypassable by
         * anyone holding impersonate.start.
         *
         * There is nothing to see, either, which is the softer half of the
         * argument: the account has no UI to look at, so the "what does this
         * user see" question the feature exists to answer has no answer here.
         *
         * Checked AFTER the impersonator's own permission, deliberately.
         * Answering "that account is API-only" to somebody who may not
         * impersonate at all tells them something about an account they had
         * no business asking about.
         *
         * Placed BEFORE the two subset tests, which is also where it is
         * cheapest: this is one row read, and it short-circuits a permission
         * subset and a site subset that would each have queried.
         */
        $target = new User($targetID);
        if ($target->isValid() && $target->isAPIOnly()) {
            return 'target is an API-only account and cannot hold a session';
        }
        $why = self::permissionRefusal($realID, $targetID);
        if ('' !== $why) {
            return $why;
        }

        return self::siteRefusal($realID, $targetID);
    }
    /**
     * Open a span.
     *
     * @param int $targetID the user to become
     *
     * @throws \Exception when the request is refused
     * @return void
     */
    public static function begin($targetID)
    {
        $targetID = (int)$targetID;
        if (self::isImpersonating()) {
            // "Impersonate another" is end-then-start, never a swap, so the
            // audit never has to answer "acting as B, who was being acted as
            // by A". A caller reaching here has skipped the end.
            throw new \Exception(
                _('Already impersonating; end the current session first.')
            );
        }
        $realID = self::realUserID();
        $target = new User($targetID);
        $label = $target->isValid() ? (string)$target->get('name') : '';
        $why = $target->isValid()
            ? self::refusalReason($realID, $targetID)
            : 'target user does not exist';
        if ('' !== $why) {
            Audit::record(
                [
                    'type' => self::REFUSED,
                    'outcome' => Audit::DENIED,
                    'subjectType' => 'user',
                    'subjectID' => $targetID,
                    'subjectLabel' => $label,
                    'permission' => self::PERMISSION,
                    'renderable' => 1,
                    'text' => $why
                ]
            );

            throw new \Exception(
                _('You may not impersonate that user.')
            );
        }
        // Set BEFORE the audit write so the start row carries the span id
        // and the acted-as name, which is what makes it the head of the
        // bracket rather than a row that merely precedes it.
        $_SESSION[self::SESSION_MASK] = $targetID;
        $_SESSION[self::SESSION_SPAN] = self::_newSpanID();
        self::$_maskName = null;
        Audit::record(
            [
                'type' => self::START,
                'outcome' => Audit::ALLOWED,
                'subjectType' => 'user',
                'subjectID' => $targetID,
                'subjectLabel' => $label,
                'permission' => self::PERMISSION,
                'renderable' => 1
            ]
        );
    }
    /**
     * Close the span, if one is open.
     *
     * Never permission-checked, and it must stay that way: impersonate a
     * user holding no roles and a gated exit path traps the administrator as
     * them. That is why the `impersonate` node is exempt (see
     * Authorization::EXEMPT_NODES) and why nothing here consults can().
     *
     * @param string $why untranslated detail when something other than the
     *                    administrator closed it
     *
     * @return void
     */
    public static function end($why = '')
    {
        if (!self::isImpersonating()) {
            return;
        }
        $targetID = self::impersonatedUserID();
        $label = self::impersonatedUserName();
        // Recorded BEFORE the keys are cleared, so the end row carries the
        // same span id as the start and the bracket closes on itself. Same
        // ordering, and the same reason, as User::logout().
        Audit::record(
            [
                'type' => self::END,
                'outcome' => Audit::ALLOWED,
                'subjectType' => 'user',
                'subjectID' => $targetID,
                'subjectLabel' => $label,
                'renderable' => 1,
                'text' => (string)$why
            ]
        );
        unset(
            $_SESSION[self::SESSION_MASK],
            $_SESSION[self::SESSION_SPAN]
        );
        self::$_maskName = null;
        // Put the real administrator back for the REST OF THIS REQUEST, not
        // only for the next one. Without it the mask object outlives the
        // span it belonged to, and the very next thing the ordinary exit
        // path does -- User::logout() -- reads its own name for the logout
        // row and records the TARGET as having ended a session they never
        // had. Same class of mistake as _actor() reading the mask, arriving
        // by a different door.
        $realID = self::realUserID();
        if ($realID > 0) {
            $GLOBALS['currentUser'] = new User($realID);
            FOGBase::forgetDisplayPreferences();
        }
    }
    /**
     * A span identifier.
     *
     * Same shape and same generator as Audit::correlationID(), and stored in
     * a column of its own rather than reusing it. A correlation id is
     * REQUEST scoped by explicit design and its docblock says so; a span
     * outlives many requests. Folding two lifetimes into one column reads
     * fine and then cannot be untangled.
     *
     * @return string 32 hex characters
     */
    private static function _newSpanID()
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Exception $e) {
            // Only when the platform has no usable CSPRNG. The value is a
            // join key, not a secret, and nothing authenticates with it.
            return md5(uniqid((string)mt_rand(), true));
        }
    }
    /**
     * Tell a user, once, that an administrator viewed their account.
     *
     * NO COLUMN, and that is the finding rather than a compromise: "has
     * anyone impersonated me since I last signed in" is already answerable
     * from rows auditLog holds, because both the start events and the
     * logins are in it and both columns this reads are indexed. A flag
     * would have been paying now for a decision nothing forces.
     *
     * Called from User::establishSession() -- after the login row is
     * written, so `>` on the login time cannot match the login that is
     * happening right now.
     *
     * @param int $userID the user who has just signed in
     *
     * @return void
     */
    public static function notifyViewed($userID)
    {
        $userID = (int)$userID;
        if ($userID < 1 || !self::getSetting(self::NOTIFY_SETTING)) {
            return;
        }
        try {
            $rows = self::$DB
                ->query(
                    'SELECT `alCreatedBy`, `alCreatedTime` FROM `auditLog` '
                    . 'WHERE `alType` = :start '
                    . 'AND `alSubjectID` = :uid '
                    . 'AND `alCreatedTime` > COALESCE(('
                    . 'SELECT MAX(`alCreatedTime`) FROM `auditLog` '
                    . 'WHERE `alType` = :login AND `alSubjectID` = :uid2'
                    . "), '1970-01-01') "
                    . 'ORDER BY `alCreatedTime` DESC',
                    [],
                    [
                        'start' => self::START,
                        'login' => Audit::LOGIN,
                        'uid' => $userID,
                        'uid2' => $userID
                    ]
                )
                ->fetch(\PDO::FETCH_ASSOC, 'fetch_all')
                ->get();
        } catch (\Exception $e) {
            // A notice nobody can read is not worth failing a login for.
            return;
        }
        foreach ((array)$rows as $row) {
            self::setMessage(
                sprintf(
                    _('%s viewed your account on %s.'),
                    (string)$row['alCreatedBy'],
                    self::formatTime((string)$row['alCreatedTime'], 'Y-m-d H:i')
                ),
                _('Account viewed by an administrator'),
                'info'
            );
        }
    }
}
