<?php
/**
 * A reverse site-grant tab takes site permissions, not the page's own.
 *
 * The Role and User Group pages each carry a tab that writes
 * `siteRoleGrants` / `siteUserGroupGrants` -- the other end of the Site
 * page's "Granted To" tabs. Both ends writing one table is deliberate and is
 * why every association tab in FOG is editable from both.
 *
 * What is NOT symmetric is the permission. Granting a site to a role is not
 * a change to the role: it widens what everyone holding that role can see,
 * INCLUDING the person making the change if they hold it. So `role.edit`
 * alone must not open it -- that would be a route to widening your own
 * scope, through a right the Site page itself does not hand out. The LDAP
 * plugin's group tabs settled the same question the same way, gating on
 * ldapgroup.edit rather than the page's own right.
 *
 * The failure this pins is silent and one line wide: drop the check, or move
 * it after the write, and the tab keeps working perfectly for an
 * administrator while quietly becoming a privilege-escalation path for a
 * role-scoped one. Nothing errors, and the escalation looks exactly like
 * ordinary use.
 *
 * Source-level: reads the method bodies rather than booting FOG, which needs
 * a database and a session.
 *
 * Usage: php tests/site-grant-reverse-tabs.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$pages = $root . '/packages/web/lib/pages';
$roleFile = $pages . '/rolemanagement.page.php';
$ugFile = $pages . '/usergroupmanagement.page.php';
$postFile = $root . '/packages/web/lib/fog/fogpagepost.class.php';

foreach ([$roleFile, $ugFile, $postFile] as $needed) {
    if (!is_readable($needed)) {
        fwrite(STDERR, "FAIL: cannot read $needed\n");
        exit(1);
    }
}

/**
 * A method body with comments stripped and whitespace flattened.
 *
 * Comments have to go: the prose above each of these methods names every
 * symbol this test searches for, and would satisfy the search on its own.
 *
 * @param string $file   file to read
 * @param string $method method name
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

$failures = [];
$checks = 0;

/**
 * Records one assertion.
 *
 * @param string $what the claim
 * @param bool   $ok   whether it held
 *
 * @return void
 */
function check($what, $ok)
{
    global $failures, $checks;
    $checks++;
    if (!$ok) {
        $failures[] = $what;
    }
}

/*
 * 1. Each POST handler demands site.edit, and demands it BEFORE it writes.
 *
 * Both halves matter. A check that runs after assocPostInverse() has already
 * saved is not a check, and ordering is the half a substring search would
 * miss -- so it is pinned by position.
 */
$posts = [
    'RoleManagement::roleSitePost' => [$roleFile, 'roleSitePost'],
    'UserGroupManagement::usergroupSiteGrantPost' => [
        $ugFile,
        'usergroupSiteGrantPost'
    ]
];

foreach ($posts as $label => $where) {
    list($file, $method) = $where;
    $src = methodSource($file, $method);
    if (null === $src) {
        fwrite(STDERR, "FAIL: cannot find $label\n");
        exit(1);
    }
    $gate = strpos($src, "Authorization::can('site.edit')");
    $write = strpos($src, 'assocPostInverse');
    check("$label requires site.edit", false !== $gate);
    check("$label writes through assocPostInverse", false !== $write);
    check(
        "$label checks the permission BEFORE it writes",
        false !== $gate && false !== $write && $gate < $write
    );
    // The page's own right must not be what opens this. If it ever reads
    // role.edit or usergroup.edit here, the gate has been rewritten into
    // the thing it exists to prevent.
    check(
        "$label does not gate on the page's own edit right",
        false === strpos($src, "can('role.edit')")
        && false === strpos($src, "can('usergroup.edit')")
    );
    // Scope answers are cached per user per request. A grant that changes
    // who can see what and leaves the cache alone is correct only until
    // somebody looks.
    check(
        "$label clears the scope cache",
        false !== strpos($src, 'SiteScope::forgetCaches')
    );
}

/*
 * 2. The tabs are hidden without site.view.
 *
 * Not security on its own -- the POST gate above is what actually refuses --
 * but a tab that renders and then refuses every save is a bug report, and an
 * install using no sites has nothing to put in it.
 */
foreach ([
    'RoleManagement' => [$roleFile, 'edit', 'role-site'],
    'UserGroupManagement' => [$ugFile, 'edit', 'usergroup-sitegrant']
] as $label => $where) {
    list($file, $method, $tabId) = $where;
    $src = methodSource($file, $method);
    if (null === $src) {
        fwrite(STDERR, "FAIL: cannot find $label::$method\n");
        exit(1);
    }
    $view = strpos($src, "Authorization::can('site.view')");
    $tab = strpos($src, "'" . $tabId . "'");
    check("$label offers the $tabId tab", false !== $tab);
    check(
        "$label gates the $tabId tab on site.view",
        false !== $view && false !== $tab && $view < $tab
    );
}

/*
 * 3. assocPostInverse writes through the OWNER, not the page object.
 *
 * That is the whole reason it exists: assocSetter() derives the column it
 * diffs on from the owning class name, so `siteRoleGrants` can only be
 * driven from a Site. An "optimization" back to $this->obj would write
 * nothing and report success.
 */
$inverse = methodSource($postFile, 'assocPostInverse');
if (null === $inverse) {
    fwrite(STDERR, "FAIL: cannot find FOGPagePost::assocPostInverse\n");
    exit(1);
}
check(
    'assocPostInverse loads the owner class',
    false !== strpos($inverse, 'self::getClass($ownerClass,$ownerID)')
);
check(
    'assocPostInverse passes the page object id as the subject',
    false !== strpos($inverse, '$owner->{$method}([$subjectID])')
);
check(
    'assocPostInverse enforces CSRF and auth',
    false !== strpos($inverse, 'self::checkAuthAndCSRF()')
);
// Ids arrive from a POST array. positiveIntIds() is what keeps a 0 or a
// non-numeric out of a getClass() lookup that would otherwise load object 0.
check(
    'assocPostInverse normalizes the submitted ids',
    false !== strpos($inverse, 'self::positiveIntIds($items)')
);

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok  $checks checks passed\n";
exit(0);
