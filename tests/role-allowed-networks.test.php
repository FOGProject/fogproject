<?php
/**
 * A role may limit the networks its members use the web UI and the API from.
 *
 * Issue #2107. The whole feature is one column and four gates, and the
 * failure of each is silent: a missing gate does not break anything, it
 * leaves the restriction unenforced on one way in.
 *
 * WHAT IS PINNED, and the failure each one catches:
 *
 *  1. Role::normalizeNetworks() accepts v4, v6 and CIDR, canonicalizes
 *     comma/space/newline input to one entry per line, de-duplicates, and
 *     THROWS on a bad entry. Executed, not pattern-matched: a typo that is
 *     dropped silently leaves the role less restricted than its author
 *     believes.
 *  2. Role::networkListAllows() matches in-range and refuses out-of-range
 *     addresses for v4, v6 and bare IPs. Executed.
 *  3. User::networkAllows(): no list means unrestricted, loopback gets NO
 *     exception (a reverse proxy on the server makes every request
 *     loopback, so an exception would silently let everyone in), an empty
 *     address with a list in force is refused. sessionPermittedHere(), the
 *     one test LoadGlobals and _isLoggedIn() share, refuses API-only and
 *     out-of-list. Executed against a stub list.
 *  4. The four gates exist where they must: establishSession() (new
 *     sign-in), LoadGlobals (an existing session: moved address, role
 *     edited while signed in, replayed session cookie), _isLoggedIn() plus
 *     the ProcessLogin unbind (remember-me cookie replay) and the Route
 *     block for token / basic-auth clients. These are source pins: running
 *     the gates needs a live session and database, which the lab run
 *     covers.
 *  5. passwordValidate() and authenticate() carry NO network check. The iPXE
 *     menu and service/checkcredentials.php share them, and the same user
 *     must still sign in there from any address.
 *  6. validatePw() hands passwordValidate() `false` and issues the
 *     remember-me credential only AFTER establishSession(), so a sign-in the
 *     gate refuses leaves no two-day credential behind.
 *  7. Schema step 461, FOG_SCHEMA 461 and the manifest column agree.
 *
 * Usage: php tests/role-allowed-networks.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('role-allowed-networks');

$t = new FogChecks();

$web = dirname(__DIR__) . '/packages/web';
$userSrc = file_get_contents($web . '/src/Items/User.php');
$roleSrc = file_get_contents($web . '/src/Items/Role.php');
$routeSrc = file_get_contents($web . '/src/Router/Route.php');
$schemaSrc = file_get_contents($web . '/commons/schema.php');
$manifestSrc = file_get_contents($web . '/commons/schema-expected.php');
$sysSrc = file_get_contents($web . '/src/Base/System.php');

/**
 * The text of one method, from its signature to the next method.
 *
 * @param string $src       The file.
 * @param string $signature The start of the method declaration.
 *
 * @return string '' when the signature is absent.
 */
$bodyOf = function ($src, $signature) {
    $at = strpos($src, $signature);
    if (false === $at) {
        return '';
    }
    $next = preg_match(
        '/\n    (?:public|private|protected)[^\n]*function /',
        $src,
        $m,
        PREG_OFFSET_CAPTURE,
        $at + strlen($signature)
    );
    return $next
        ? substr($src, $at, $m[0][1] - $at)
        : substr($src, $at);
};

// ---------------------------------------------------------------------------
// 1. normalizeNetworks(), executed.
// ---------------------------------------------------------------------------
$t->check(
    'normalizeNetworks() canonicalizes comma, space and newline input',
    FogTestHarness::callStatic(
        'FOG\Items\Role',
        'normalizeNetworks',
        ["10.0.0.0/24, 192.168.1.50\nfe80::/10  ::1"]
    ) === "10.0.0.0/24\n192.168.1.50\nfe80::/10\n::1"
);
$t->check(
    'normalizeNetworks() de-duplicates',
    FogTestHarness::callStatic(
        'FOG\Items\Role',
        'normalizeNetworks',
        ["10.0.0.1\n10.0.0.1, 10.0.0.1"]
    ) === '10.0.0.1'
);
$t->check(
    'normalizeNetworks() treats empty input as no restriction',
    '' === FogTestHarness::callStatic(
        'FOG\Items\Role',
        'normalizeNetworks',
        ["  \n , "]
    )
);
foreach (['10.0.0.0/33', 'fe80::/129', 'banana', '10.0.0.0/x', '10.0.0.0/'] as $bad) {
    $threw = false;
    try {
        FogTestHarness::callStatic(
            'FOG\Items\Role',
            'normalizeNetworks',
            ["10.0.0.1\n" . $bad]
        );
    } catch (\InvalidArgumentException $e) {
        $threw = false !== strpos($e->getMessage(), $bad);
    }
    $t->check("normalizeNetworks() throws naming '$bad'", $threw);
}
$threw = false;
try {
    FogTestHarness::callStatic(
        'FOG\Items\Role',
        'normalizeNetworks',
        [str_repeat("10.0.0.0/24\n", 300)]
    );
} catch (\InvalidArgumentException $e) {
    // 300 identical entries de-duplicate to one, so this must NOT throw.
    $threw = true;
}
$t->check('normalizeNetworks() measures the de-duplicated list', !$threw);
$many = [];
for ($i = 0; $i < 250; $i++) {
    $many[] = "10.0.$i.0/24";
}
$threw = false;
try {
    FogTestHarness::callStatic(
        'FOG\Items\Role',
        'normalizeNetworks',
        [implode("\n", $many)]
    );
} catch (\InvalidArgumentException $e) {
    $threw = true;
}
$t->check('normalizeNetworks() refuses a list over 2048 characters', $threw);

// ---------------------------------------------------------------------------
// 2. networkListAllows(), executed.
// ---------------------------------------------------------------------------
$allows = function ($ip, array $list) {
    return FogTestHarness::callStatic(
        'FOG\Items\Role',
        'networkListAllows',
        [$ip, $list]
    );
};
$t->check('an in-range IPv4 address matches', $allows('10.0.0.77', ['10.0.0.0/24']));
$t->check('an out-of-range IPv4 address does not', !$allows('10.0.1.77', ['10.0.0.0/24']));
$t->check('an IPv6 CIDR matches', $allows('fd00::5', ['fd00::/8']));
$t->check('an IPv6 address outside the CIDR does not', !$allows('fe80::5', ['fd00::/8']));
$t->check('a bare address matches only itself', $allows('192.168.1.50', ['192.168.1.50']));
$t->check('a bare address does not match its neighbor', !$allows('192.168.1.51', ['192.168.1.50']));
$t->check('any one matching entry is enough', $allows('172.16.0.1', ['10.0.0.0/8', '172.16.0.0/12']));

// ---------------------------------------------------------------------------
// 3. User::networkAllows(), executed against a stub list.
// ---------------------------------------------------------------------------
$stub = function (array $list) {
    return new class($list) extends FOG\Items\User {
        private $_list;
        public function __construct(array $list)
        {
            $this->_list = $list;
        }
        public function allowedNetworks(array $overrides = [])
        {
            return $this->_list;
        }
    };
};
$t->check('no list means unrestricted', $stub([])->networkAllows('203.0.113.9'));
$t->check('no list allows an empty address too', $stub([])->networkAllows(''));
$t->check('an address in the list is allowed', $stub(['10.0.0.0/24'])->networkAllows('10.0.0.5'));
$t->check('an address outside the list is refused', !$stub(['10.0.0.0/24'])->networkAllows('203.0.113.9'));
$t->check('IPv4 loopback is refused when not listed', !$stub(['10.0.0.0/24'])->networkAllows('127.0.0.1'));
$t->check('IPv6 loopback is refused when not listed', !$stub(['10.0.0.0/24'])->networkAllows('::1'));
$t->check('loopback is allowed when listed', $stub(['127.0.0.1'])->networkAllows('127.0.0.1'));

// sessionPermittedHere(): the one test both session gates run. Executed with
// a stub list and a stub API-only flag, reading the address the way the
// gates do (REMOTE_ADDR).
$sess = function (array $list, $apiOnly, $addr) {
    $_SERVER['REMOTE_ADDR'] = $addr;
    $u = new class($list, $apiOnly) extends FOG\Items\User {
        private $_list;
        private $_api;
        public function __construct(array $list, $apiOnly)
        {
            $this->_list = $list;
            $this->_api = $apiOnly;
        }
        public function allowedNetworks(array $overrides = [])
        {
            return $this->_list;
        }
        public function isAPIOnly()
        {
            return $this->_api;
        }
    };
    $result = $u->sessionPermittedHere();
    unset($_SERVER['REMOTE_ADDR']);
    return $result;
};
$t->check('session: in-list address is permitted', $sess(['10.0.0.0/24'], false, '10.0.0.9'));
$t->check('session: out-of-list address is refused', !$sess(['10.0.0.0/24'], false, '198.51.100.4'));
$t->check('session: no list is permitted anywhere', $sess([], false, '198.51.100.4'));
$t->check('session: API-only is refused even in-list', !$sess(['10.0.0.0/24'], true, '10.0.0.9'));
$t->check('an empty address is refused when a list is in force', !$stub(['10.0.0.0/24'])->networkAllows(''));

// ---------------------------------------------------------------------------
// 4. The gates exist where they must.
// ---------------------------------------------------------------------------
$esBody = $bodyOf($userSrc, 'public function establishSession(');
$liBody = $bodyOf($userSrc, 'private function _isLoggedIn()');
$pwBody = $bodyOf($userSrc, 'public function passwordValidate(');
$auBody = $bodyOf($userSrc, 'public function authenticate(');
$vpBody = $bodyOf($userSrc, 'public function validatePw(');

$t->check(
    'Gate A: establishSession() checks the address, audits, then throws',
    (bool)preg_match(
        '/networkAllows\(self::requestAddress\(\)\)\)\s*\{\s*'
        . 'self::_auditLoginFailure\(.{0,120}?throw new \\\\Exception/s',
        $esBody
    )
);
// AFTER the rebind: that block can replace $this's id with self::$FOGUser's,
// and the id stamped into $_SESSION['FOG_USER'] is the account to test.
// Testing before it tests an account that never receives the session.
$t->check(
    'Gate A runs after the FOGUser rebind and before the session is stamped',
    false !== strpos($esBody, 'networkAllows(')
    && strpos($esBody, 'self::$FOGUser->isValid()') < strpos($esBody, 'networkAllows(')
    && strpos($esBody, 'networkAllows(') < strpos($esBody, "\$_SESSION['FOG_USER'] =")
);
// LoadGlobals is where an EXISTING session becomes the acting user, for the
// UI, AJAX and session-authed REST alike. _isLoggedIn() returning false is
// ignored by most callers, so without this a session made from an allowed
// network, or a replayed cookie, keeps working from anywhere.
$lgSrc = (string)file_get_contents($web . '/src/Base/LoadGlobals.php');
$lgCheck = strpos($lgSrc, '->sessionPermittedHere()');
$t->check(
    'LoadGlobals drops a session sessionPermittedHere() refuses',
    false !== $lgCheck
    && (bool)preg_match(
        '/if \(\$currentUser->isValid\(\) && !\$currentUser->sessionPermittedHere\(\)\) \{\s*'
        . 'unset\(\$_SESSION\[\'FOG_USER\'\]\);\s*\$currentUser = new User\(0\);/',
        $lgSrc
    )
);
// The remember-me replay binds the cookie's account into self::$FOGUser
// (the global $currentUser, by reference) BEFORE isLoggedIn() runs. The
// pages test only isValid(), so a refusal that leaves it bound renders the
// page anyway.
$plSrc = (string)file_get_contents($web . '/src/Pages/ProcessLogin.php');
$t->check(
    'ProcessLogin unbinds a remember-me account isLoggedIn() refused',
    (bool)preg_match(
        '/if \(self::\$FOGUser->isLoggedIn\(\)->isValid\(\)\) \{\s*'
        . 'return;\s*\}(?:\s*\/\/[^\n]*)*\s*self::\$FOGUser = new User\(0\);\s*'
        . 'self::mainLoginForm\(\);/',
        $plSrc
    )
);
// isLoggedIn() returns an object, always truthy (see its docblock). The lab
// run found the remember-me replay testing it as a boolean, which kept a
// refused account signed in. Every PHP file under packages/ is scanned, with
// comments stripped, so a future caller anywhere is caught: an operator
// (&&, ||, ?, ==, !=), a closing paren (inside if/while), or a leading !.
$boolUses = [];
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(dirname(__DIR__) . '/packages', FilesystemIterator::SKIP_DOTS)
);
foreach ($it as $file) {
    if ('php' !== $file->getExtension()) {
        continue;
    }
    $code = '';
    foreach (token_get_all((string)file_get_contents($file->getPathname())) as $tok) {
        if (is_array($tok) && in_array($tok[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        $code .= is_array($tok) ? $tok[1] : $tok;
    }
    // (?<!\w): the private _isLoggedIn() returns a real bool and is not this.
    if (preg_match('/!\s*[$\w:>-]*->isLoggedIn\(\)|(?<!\w)isLoggedIn\(\)\s*(?:&&|\|\||\?|[!=]=|\))/', $code)) {
        $boolUses[] = $file->getPathname();
    }
}
$t->check(
    'no caller treats isLoggedIn()\'s object return as a boolean: '
    . implode(', ', $boolUses),
    !count($boolUses)
);
$t->check(
    'LoadGlobals tests the real account before impersonation binds a mask',
    false !== $lgCheck
    && false !== strpos($lgSrc, 'Identity::')
    && $lgCheck < strpos($lgSrc, 'Identity::')
    && $lgCheck < strpos($lgSrc, "\$GLOBALS['currentUser'] = \$currentUser;")
);
$t->check(
    'Gate B: _isLoggedIn() returns false when sessionPermittedHere() refuses',
    (bool)preg_match(
        '/if \(!\$this->sessionPermittedHere\(\)\) \{\s*return false;/',
        $liBody
    )
);
$t->check(
    'Gate B runs before the session timers touch anything',
    false !== strpos($liBody, 'sessionPermittedHere()')
    && strpos($liBody, 'sessionPermittedHere()') < strpos($liBody, 'FOG_ALWAYS_LOGGED_IN')
);

$routeAt = strpos($routeSrc, 'if (!$sessionAuthed' . "\n" . '            && !$isunauth');
$gateAt = strpos($routeSrc, 'networkAllows(User::requestAddress())');
$blockEnd = false === $routeAt
    ? false
    : strpos($routeSrc, 'CSRF::requireForStateChanging()', $routeAt);
$t->check(
    'the Route gate sits inside the non-session block, after the auth tests',
    false !== $routeAt
    && false !== $gateAt
    && false !== $blockEnd
    && $gateAt > strpos($routeSrc, 'self::_testAuth();', $routeAt)
    && $gateAt < $blockEnd
);
$t->check(
    'the Route gate audits API_DENIED and answers 403',
    (bool)preg_match(
        "/networkAllows\(User::requestAddress\(\)\).{0,900}?API_DENIED"
        . ".{0,700}?'authSource' => 'network'.{0,500}?HTTP_FORBIDDEN/s",
        $routeSrc
    )
);

// ---------------------------------------------------------------------------
// 5. The shared credential paths carry no network check.
// ---------------------------------------------------------------------------
$t->check(
    'passwordValidate() has no network check (iPXE and FOS share it)',
    '' !== $pwBody && false === strpos($pwBody, 'networkAllows(')
);
$t->check(
    'authenticate() has no network check (iPXE and FOS share it)',
    '' !== $auBody && false === strpos($auBody, 'networkAllows(')
);

// ---------------------------------------------------------------------------
// 6. Remember-me is issued only after the session is accepted.
// ---------------------------------------------------------------------------
$t->check(
    'validatePw() passes false to passwordValidate()',
    (bool)preg_match(
        '/passwordValidate\(\$username, \$password, false\)/',
        $vpBody
    )
);
$t->check(
    'validatePw() issues remember-me only after establishSession()',
    false !== strpos($vpBody, 'establishSession()')
    && false !== strpos($vpBody, '_issueRememberMe()')
    && strpos($vpBody, 'establishSession()') < strpos($vpBody, '_issueRememberMe()')
);
$t->check(
    'passwordValidate() still issues remember-me for third-party callers',
    (bool)preg_match(
        '/if \(\$remember && \$passValid\) \{\s*\$this->_issueRememberMe\(\);/',
        $pwBody
    )
);

// ---------------------------------------------------------------------------
// 7. Schema, version and manifest agree.
// ---------------------------------------------------------------------------
$t->check(
    'schema step 461 adds roles.rAllowedNetworks',
    (bool)preg_match(
        "/\/\/ 461\n.*?ALTER TABLE `roles` \"\s*\. \"ADD COLUMN `rAllowedNetworks`"
        . " varchar\(2048\) NOT NULL DEFAULT ''\"/s",
        $schemaSrc
    )
);
$t->check(
    'FOG_SCHEMA is 461',
    (bool)preg_match("/define\('FOG_SCHEMA', 461\)/", $sysSrc)
);
$t->check(
    'the manifest carries the column, in the CREATE and in columns',
    false !== strpos(
        $manifestSrc,
        "`rAllowedNetworks` varchar(2048) NOT NULL DEFAULT \\'\\',"
    )
    && false !== strpos(
        $manifestSrc,
        "'rAllowedNetworks' => 'varchar(2048) NOT NULL DEFAULT \\'\\''"
    )
);
$t->check(
    'Role maps allowednetworks to rAllowedNetworks',
    false !== strpos($roleSrc, "'allowednetworks' => 'rAllowedNetworks'")
);
$t->check(
    'Role::save() normalizes, then runs the self-lockout guard',
    (bool)preg_match(
        '/function save\(\)\s*\{.{0,900}?normalizeNetworks\(.{0,200}?'
        . '_assertNetworksKeepActingUser\(\);.{0,900}?parent::save\(\)/s',
        $roleSrc
    )
);

$t->finish();
