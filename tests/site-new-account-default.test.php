<?php
/**
 * A user created after the site migration must not be the only account on
 * the server that can see nothing.
 *
 * Schema step 333 creates the catch-all site and puts every account that
 * existed on upgrade day into it, so the migration changes nothing for
 * anybody. The next account created is the one nothing covers: site scope
 * is deny-all, so a user in no site sees no hosts, no users and no groups,
 * with no error anywhere to say why. Every account from the day before
 * works; the first one after does not. That asymmetry is the bug, and it
 * only appears on servers where sites are not otherwise in use -- exactly
 * the servers whose admins never asked for sites at all.
 *
 * Two halves, and both are needed:
 *
 *   - User::save() joins the catch-all on insert, because two of the four
 *     ways a user gets created involve nobody clicking anything (a REST
 *     POST, and the ldap plugin auto-provisioning on first login).
 *     FOGController::save() fires no event, so a hook could not have
 *     reached those.
 *   - the create forms carry a Site field, so the choice is available at
 *     creation time rather than requiring a second trip through the edit
 *     page -- during which, for a user, the account exists and is blind.
 *
 * The gate is the part most likely to be "simplified" away later, so it is
 * asserted on its own: joining unconditionally would mean a user created
 * for one site is silently placed in the catch-all as well, and catch-all
 * membership short circuits ABOVE the per-site query. Every such user
 * would see every site. That is an access-control default, not a tidiness
 * preference.
 *
 * DB-free: reads the source. The behaviour of the SiteScope helpers these
 * call is covered against a fake database in site-scope.test.php.
 *
 * Usage: php tests/site-new-account-default.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$webroot = dirname(__DIR__) . '/packages/web';

$failures = [];
$checks = 0;

/**
 * Returns the source of a named method, signature to the following one.
 */
$bodyOf = function ($src, $needle) {
    $start = strpos($src, $needle);
    if (false === $start) {
        return null;
    }
    $next = preg_match(
        '/\n    (?:public|private|protected)[ a-z]* function /',
        $src,
        $m,
        PREG_OFFSET_CAPTURE,
        $start + strlen($needle)
    );
    return $next
        ? substr($src, $start, $m[0][1] - $start)
        : substr($src, $start);
};

$read = function ($path) use ($webroot) {
    $full = $webroot . '/' . $path;
    if (!is_readable($full)) {
        fwrite(STDERR, "FAIL: cannot read $full\n");
        exit(1);
    }
    return file_get_contents($full);
};

$check = function ($label, $cond) use (&$failures, &$checks) {
    $checks++;
    if (!$cond) {
        $failures[] = $label;
    }
};

$user = $read('lib/fog/user.class.php');
$save = $bodyOf($user, 'public function save(');

if (null === $save) {
    fwrite(STDERR, "FAIL: could not find User::save()\n");
    exit(1);
}

// Comments explain all of this, so they must not satisfy any of it.
$saveCode = preg_replace('#//[^\n]*#', '', $save);

$check(
    'User::save() no longer joins the catch-all, so an account created '
    . 'after the migration is in no site and sees nothing',
    false !== strpos($saveCode, 'SiteScope::joinCatchAll(')
);

$check(
    'User::save() joins the catch-all without gating on sitesInUse(). '
    . 'Unconditional means a user created for one site is put in the '
    . 'catch-all too, and catch-all membership short circuits above the '
    . 'per-site query -- so they would see every site on the server.',
    false !== strpos($saveCode, 'SiteScope::sitesInUse()')
);

// Ordering: the id only exists after the save, and "was this an insert"
// only exists before it. Getting either the wrong side of parent::save()
// gives a join that silently never happens, or happens on every update.
$posNew = strpos($saveCode, '$isNew');
$posSave = strpos($saveCode, 'parent::save()');
$posJoin = strpos($saveCode, 'SiteScope::joinCatchAll(');
$check(
    'User::save() must decide it is an insert BEFORE parent::save() (which '
    . 'is what assigns the id) and join AFTER it (which is when there is an '
    . 'id to join with)',
    false !== $posNew && false !== $posSave && false !== $posJoin
    && $posNew < $posSave && $posSave < $posJoin
);

/*
 * The create-form field. Two calls make it correct rather than merely
 * present: catchAllID() is what it preselects, and sitesInUse() is what
 * decides whether preselecting is appropriate at all.
 */
$render = $read('lib/fog/fogpagerender.class.php');
$field = $bodyOf($render, 'protected static function siteAddField(');
$check(
    'FOGPageRender::siteAddField() is gone',
    null !== $field
);
if (null !== $field) {
    $fieldCode = preg_replace('#//[^\n]*#', '', $field);
    $check(
        'siteAddField() no longer asks catchAllID(), so it cannot '
        . 'preselect the catch-all or tell a pre-migration server apart '
        . 'from one with no sites',
        false !== strpos($fieldCode, 'SiteScope::catchAllID()')
    );
    $check(
        'siteAddField() no longer asks sitesInUse(), so it would '
        . 'preselect the catch-all on a server that genuinely uses sites '
        . '-- every new object would default to seeing everything',
        false !== strpos($fieldCode, 'SiteScope::sitesInUse()')
    );
}

/*
 * The create-form post. It must do nothing when the field was not posted:
 * siteAddField() renders nothing on a server with no sites, and a hook may
 * remove it. An absent field means "nothing was asked for" -- read as "no
 * site" it would delete the catch-all membership User::save() had just
 * granted, which is the bug this whole file is about, reintroduced from
 * the other end.
 */
$post = $read('lib/fog/fogpagepost.class.php');
$addPost = $bodyOf($post, 'protected function siteAddPost(');
$check(
    'FOGPagePost::siteAddPost() is gone',
    null !== $addPost
);
if (null !== $addPost) {
    $addCode = preg_replace('#//[^\n]*#', '', $addPost);
    $check(
        'siteAddPost() no longer distinguishes an absent Site field from '
        . 'an empty one, so a create form without the field would strip '
        . 'the membership the save had just granted',
        false !== strpos($addCode, "filter_input(INPUT_POST, 'site')")
        && false !== strpos($addCode, 'return;')
    );
}

/*
 * And the pages are wired to both. A helper nothing calls is the failure
 * mode these two checks exist for.
 */
$pages = [
    'user' => 'lib/pages/usermanagement.page.php',
    'group' => 'lib/pages/groupmanagement.page.php',
    'usergroup' => 'lib/pages/usergroupmanagement.page.php',
];
foreach ($pages as $node => $path) {
    $src = $read($path);
    $check(
        "the $node create form does not render the Site field",
        false !== strpos($src, 'self::siteAddField(')
    );
    $check(
        "the $node create does not apply the Site field it rendered",
        false !== strpos($src, "\$this->siteAddPost('$node',")
    );
}

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok  $checks new-account site default checks\n";
exit(0);
