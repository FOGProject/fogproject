<?php
/**
 * An identity provider outage must never lock an install out of itself.
 *
 * Phase 2 PR 2.5. FOG can now be signed into by an external provider, and
 * users.uAuthSource is what says an account belongs to one. That column has
 * a second effect people do not expect: it makes the account unable to log
 * in with a local password at all (User::passwordValidate()). So an install
 * whose every administrator carries one is reachable only while its
 * directory is -- and an expired client secret, a mistyped issuer or a dead
 * DC then locks everybody out of their own server, with no way back that
 * does not involve the database.
 *
 * Three properties, and they fail differently:
 *
 *   1. Local password login cannot be turned off. Not a setting with a
 *      scary label -- no setting at all. This one is true today and the
 *      risk is that somebody adds the setting later meaning well.
 *   2. No single operation may remove the last administrator who can sign
 *      in without the directory. Two operations can: writing uAuthSource
 *      onto them, and deleting them.
 *   3. The guard PRESERVES, it does not REQUIRE. An install that has
 *      deliberately moved every administrator to a directory has nothing
 *      left to protect, and a guard that started refusing its operations
 *      would brick it to defend a property it already gave up.
 *
 * Most of this needs a database -- counting administrators means reading
 * four tables -- so the decision half was split into
 * Authorization::externalUsersGiven() and is run for real below. The rest
 * is pinned by inspecting what the code does, which is enough: every one of
 * these regresses silently, with a working login and no error anywhere.
 *
 * Usage: php tests/break-glass-auth-sources.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
chdir($root);

$fails = [];
$userFile = 'packages/web/src/Items/User.php';
$authFile = 'packages/web/src/Auth/Authorization.php';

/**
 * Source text of one method, comments and whitespace stripped.
 *
 * Comments go first so the explanatory prose above each method -- which
 * names every symbol this test searches for -- cannot satisfy the search on
 * its own.
 *
 * @param string $file   path to read
 * @param string $method method name to find
 *
 * @return string|null code of the body, or null if not found
 */
function methodSource($file, $method)
{
    $t = token_get_all(file_get_contents($file));
    $n = count($t);
    for ($i = 0; $i < $n; $i++) {
        if (!is_array($t[$i]) || T_FUNCTION !== $t[$i][0]) {
            continue;
        }
        $j = $i + 1;
        while ($j < $n && is_array($t[$j]) && T_WHITESPACE === $t[$j][0]) {
            $j++;
        }
        if ($j >= $n || !is_array($t[$j]) || $t[$j][1] !== $method) {
            continue;
        }
        $depth = 0;
        $src = '';
        $started = false;
        for ($k = $j; $k < $n; $k++) {
            $c = $t[$k];
            if (is_array($c)
                && in_array($c[0], [T_COMMENT, T_DOC_COMMENT], true)
            ) {
                continue;
            }
            if (!is_array($c)) {
                if ('{' === $c) {
                    $depth++;
                    $started = true;
                } elseif ('}' === $c) {
                    if (0 === --$depth && $started) {
                        return preg_replace('#\s+#', '', $src);
                    }
                }
            }
            if ($started) {
                $src .= is_array($c) ? $c[1] : $c;
            }
        }
        return preg_replace('#\s+#', '', $src);
    }
    return null;
}

/*
 * 1. Local password login is not configurable.
 *
 * The whole break-glass position rests on there being no way to switch the
 * local path off, so the test is the absence of one: passwordValidate()
 * must consult no setting at all. A FOG_DISABLE_LOCAL_LOGIN added later
 * with the best of intentions is exactly the change that would make an IdP
 * outage unrecoverable, and it would look reasonable in review.
 */
$validate = methodSource($userFile, 'passwordValidate');
if (null === $validate) {
    $fails[] = 'User::passwordValidate() is missing';
} else {
    if (false !== strpos($validate, 'getSetting(')) {
        $fails[] = 'User::passwordValidate() now reads a setting; local'
            . ' password login must not be something an install can turn'
            . ' off, or an identity provider outage has no way back in';
    }
    /*
     * The other half of the same property: an account WITH an auth source
     * must still be refused a local password, which is what makes a
     * leftover shadow row harmless when the plugin is gone.
     *
     * Both the test and what it is computed FROM. Pinning only the test
     * leaves $isExternal free to be hardcoded false somewhere above it,
     * which reads as a tidy-up and turns every abandoned shadow row back
     * into a login against whatever hash it still carries.
     */
    $isExternal = "\$isExternal=(''!==trim((string)\$tmpUser->get("
        . "'authsource')))";
    if (false === strpos($validate, $isExternal)) {
        $fails[] = 'User::passwordValidate() no longer derives $isExternal'
            . ' from the stored auth source';
    }
    if (false === strpos($validate, '$isExternal&&true!==$authenticated')) {
        $fails[] = 'User::passwordValidate() no longer refuses a local'
            . ' credential for an externally-sourced account; a leftover'
            . ' row from an uninstalled auth plugin becomes a login';
    }
}

/*
 * 2a. Writing uAuthSource is guarded, and guarded on save().
 *
 * There are three ways to write that column and they have nothing in
 * common: a REST PUT /fog/user/{id} (it is an ordinary field and not in
 * Route::$serverOwnedFields), a plugin's own set()/save(), and the CSV
 * import. Guarding save() is what makes it a standing property rather than
 * something each new caller has to remember.
 */
$save = methodSource($userFile, 'save');
if (null === $save) {
    $fails[] = 'User::save() is missing';
} elseif (false === strpos($save, '_assertAuthSourceKeepsBreakGlass()')) {
    $fails[] = 'User::save() no longer checks the auth source write; a PUT,'
        . ' a plugin or a CSV import could hand the last local'
        . ' administrator to a directory';
}

$guard = methodSource($userFile, '_assertAuthSourceKeepsBreakGlass');
if (null === $guard) {
    $fails[] = 'User::_assertAuthSourceKeepsBreakGlass() is missing';
} else {
    if (false === strpos($guard, 'Authorization::assertLocalAdminRemains')) {
        $fails[] = 'User::_assertAuthSourceKeepsBreakGlass() no longer asks'
            . ' whether a locally-authenticating administrator would remain';
    }
    if (false === strpos($guard, "'authSources'=>[\$id=>\$pending]")) {
        $fails[] = 'User::_assertAuthSourceKeepsBreakGlass() no longer'
            . ' simulates the pending value; asking about the state already'
            . ' stored answers a question nobody asked';
    }
    /*
     * Clearing the column only ever gives an account its password back, and
     * refusing that would be the guard preventing the very recovery it
     * exists to protect.
     */
    $clearAt = strpos($guard, "if(''===\$pending){return;}");
    $assertAt = strpos($guard, 'assertLocalAdminRemains');
    if (false === $clearAt) {
        $fails[] = 'User::_assertAuthSourceKeepsBreakGlass() no longer lets'
            . ' an auth source be cleared; that is the recovery path itself';
    } elseif (false !== $assertAt && $clearAt > $assertAt) {
        $fails[] = 'User::_assertAuthSourceKeepsBreakGlass() asserts before'
            . ' letting a clear through, so removing an auth source could be'
            . ' refused';
    }
}

/*
 * 2b. Deleting an administrator is guarded too.
 *
 * Without this half the other one is theater: converting the last local
 * administrator is refused while deleting the same account is not, and both
 * leave the install in the identical state.
 */
$assertDelete = methodSource($authFile, 'assertAdminRemainsAfterDelete');
if (null === $assertDelete) {
    $fails[] = 'Authorization::assertAdminRemainsAfterDelete() is missing';
} elseif (false === strpos($assertDelete, 'assertLocalAdminRemains(')) {
    $fails[] = 'Authorization::assertAdminRemainsAfterDelete() no longer'
        . ' checks the local administrator count; it counts every'
        . ' administrator, so deleting the last one who can sign in without'
        . ' the directory passes it';
}

/*
 * 3. The guard preserves rather than requires.
 *
 * The early return has to come FIRST. An install with no local
 * administrator today has nothing for this to protect, and a guard that
 * refused its operations anyway would be defending a property that install
 * already gave up -- while making it unable to tidy up its own accounts.
 */
$assertLocal = methodSource($authFile, 'assertLocalAdminRemains');
if (null === $assertLocal) {
    $fails[] = 'Authorization::assertLocalAdminRemains() is missing';
} else {
    $bailAt = strpos($assertLocal, 'if(!self::localAdminExists()){return;}');
    $throwAt = strpos($assertLocal, 'thrownew');
    if (false === $bailAt) {
        $fails[] = 'Authorization::assertLocalAdminRemains() no longer'
            . ' returns early when no local administrator exists; an install'
            . ' that has already gone all-directory would be unable to'
            . ' delete or convert anything';
    } elseif (false === $throwAt || $bailAt > $throwAt) {
        $fails[] = 'Authorization::assertLocalAdminRemains() can throw before'
            . ' establishing that there was anything to preserve';
    }
}

/*
 * The auth-source read owns its SQL, for the reason rolesHolding() does: an
 * auth source is an opaque plugin-chosen string, and '*' or '+' in a scalar
 * filter value becomes a SQL LIKE wildcard in the query builder. Here that
 * would mean every account reading as external -- and the guard then
 * refusing every delete on a perfectly healthy install.
 */
$external = methodSource($authFile, '_externalUsers');
if (null === $external) {
    $fails[] = 'Authorization::_externalUsers() is missing';
} else {
    if (false !== strpos($external, 'Route::getIds')) {
        $fails[] = 'Authorization::_externalUsers() reads the auth source'
            . " through Route::getIds(); '*' and '+' in a filter value become"
            . ' SQL LIKE wildcards, and an auth source is an opaque'
            . ' plugin-chosen string';
    }
    /*
     * The SELECT itself, not merely a mention of the column. Narrowing the
     * query while leaving $row['uAuthSource'] in the loop below reads as a
     * tidy-up and leaves every account looking local, so localAdminExists()
     * answers exactly what adminExistsGiven() does and the guard silently
     * never fires again.
     */
    if (false === strpos($external, ',`uAuthSource`FROM`users`')) {
        $fails[] = 'Authorization::_externalUsers() no longer SELECTs'
            . ' users.uAuthSource; every account would read as local and the'
            . ' guard would never refuse anything';
    }
}

/*
 * 4. The classifier, for real. Booting the autoloader is enough to reach it
 *    -- Initiator's constructor only registers the autoloader, and this is a
 *    pure static method, so no database is involved. FOG_CACHE_DIR and
 *    friends are redirected into a throwaway directory first; see the long
 *    note in tests/autoload.test.php for why that line must never become
 *    conditional.
 */
$tmp = sys_get_temp_dir() . '/fog-breakglass-test-' . getmypid();
@mkdir($tmp . '/cache', 0700, true);
@mkdir($tmp . '/log', 0700, true);
register_shutdown_function(
    function () use ($tmp) {
        foreach (glob($tmp . '/*/*') ?: [] as $f) {
            @unlink($f);
        }
        foreach (glob($tmp . '/*') ?: [] as $d) {
            @rmdir($d);
        }
        @rmdir($tmp);
    }
);
define('FOG_CACHE_DIR', $tmp . '/cache');
define('FOG_LOG_DIR', $tmp . '/log');
define('FOG_PLUGIN_DIR', $tmp . '/plugins');

require_once $root . '/packages/web/commons/init.php';
new Initiator();

$cases = [
    'nobody is external' => [
        [1 => '', 2 => '', 3 => ''],
        [],
        []
    ],
    'a stored source makes an account external' => [
        [1 => '', 2 => 'ldap', 3 => ''],
        [],
        [2]
    ],
    // Whitespace is not an auth source. A column padded by a hand-written
    // UPDATE would otherwise read as external and take that account out of
    // the count that decides whether anyone can still get in.
    'whitespace is local' => [
        [1 => '   ', 2 => "\t", 3 => ''],
        [],
        []
    ],
    // The proposed value REPLACES the stored one. Merging instead would
    // make a conversion invisible, which is the direction that locks the
    // install out.
    'a proposal converts a local account' => [
        [1 => '', 2 => ''],
        [1 => 'oidc'],
        [1]
    ],
    // And the other way: a proposal that clears the column has to be able
    // to bring an account back, or the guard could never see a recovery.
    'a proposal can clear a stored source' => [
        [1 => 'ldap', 2 => ''],
        [1 => ''],
        []
    ],
    'a proposal for an unknown id still counts' => [
        [1 => ''],
        [7 => 'saml'],
        [7]
    ],
    // Ids arrive as strings from PDO and as int keys from a change map;
    // the answer is compared with array_diff() against integer user ids, so
    // a string '2' here would silently fail to exclude user 2.
    'ids come back as integers' => [
        ['2' => 'ldap'],
        [],
        [2]
    ],
];
foreach ($cases as $label => $case) {
    list($stored, $proposed, $want) = $case;
    $got = \FOG\Authorization::externalUsersGiven($stored, $proposed);
    sort($got);
    sort($want);
    if ($got !== $want) {
        $fails[] = sprintf(
            'externalUsersGiven, %s: got %s, expected %s',
            $label,
            var_export($got, true),
            var_export($want, true)
        );
    }
    foreach ($got as $id) {
        if (!is_int($id)) {
            $fails[] = sprintf(
                'externalUsersGiven, %s: returned %s, not an int',
                $label,
                var_export($id, true)
            );
        }
    }
}

if (count($fails) > 0) {
    echo 'FAIL: ' . count($fails) . " problem(s):\n";
    foreach ($fails as $f) {
        echo '  - ' . $f . "\n";
    }
    exit(1);
}
echo "ok: an identity provider outage cannot lock the install out\n";
exit(0);
