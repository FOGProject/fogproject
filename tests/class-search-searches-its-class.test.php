<?php
/**
 * /{class}/search/{item} searches the class it names. GH-1290.
 *
 * Route::search() used to call unisearch() and read the bucket keyed by its
 * own class name out of the result. Two defects fell out of that shape, and
 * both were silent:
 *
 *   - unisearch() iterates FOGBase::$searchPages (16 entries), not
 *     Route::$validClasses (51), and `continue`s on `task` at the top of the
 *     loop. A class with no bucket produced no ids, listem() matched
 *     nothing, and the route answered 200 with recordsFiltered 0 -- for 17
 *     classes whose models DO have a name column, against terms that were
 *     exact substrings of real rows. Nothing errored and nothing was logged,
 *     so a caller could not tell it apart from "no matches exist", which is
 *     how you ship code against a route that has never worked.
 *   - unisearch() remaps the MODEL for `ipxe` to `pxemenuoptions` but keys
 *     the bucket `ipxe`. search('ipxe') therefore fed pxeMenu ids into a
 *     lookup against ipxeTable -- two tables that share nothing but an
 *     integer -- and returned real rows that were not the ones asked for.
 *     Wrong data presented as a match is worse than no data.
 *
 * The fix shares unisearch()'s per-class body as _searchRows() rather than
 * giving search() a query of its own, and THAT is what most of this file
 * checks. A second implementation would need a second copy of the
 * object-scope boundary and of the credential rule that stops settings
 * search confirming a password a character at a time -- and the day the two
 * drift, nothing fails. The rows just quietly become visible again.
 *
 * What this canNOT check is that any of it returns the right rows.
 * /home/telliott/labs/adr0020/prove_search.php does that against a lab
 * database, including the credential guard, the hostMAC join and the ipxe
 * case.
 *
 * DB-free: source text and class properties.
 *
 * Usage: php tests/class-search-searches-its-class.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('class-search');
FogTestHarness::fakeDb();

$t = new FogChecks();
$web = dirname(__DIR__) . '/packages/web';
$routeFile = $web . '/src/Router/Route.php';
$src = file_get_contents($routeFile);

/**
 * The source text of one method, brace-matched from its signature.
 *
 * @param string $src The file contents.
 * @param string $sig The signature line to start at.
 *
 * @return string
 */
function searchMethodBody($src, $sig)
{
    $at = strpos($src, $sig);
    if (false === $at) {
        return '';
    }
    $open = strpos($src, '{', $at);
    $depth = 0;
    $len = strlen($src);
    for ($i = $open; $i < $len; $i++) {
        if ('{' === $src[$i]) {
            $depth++;
        } elseif ('}' === $src[$i]) {
            $depth--;
            if (0 === $depth) {
                return substr($src, $at, $i - $at + 1);
            }
        }
    }
    return '';
}

/**
 * The same text with every comment removed.
 *
 * Mandatory here, not tidiness. Every negative assertion below is of the
 * form "this string does not appear in that method", and these methods
 * carry long comments that NAME the very things being excluded -- the
 * docblock on _searchRows() says the words "unisearch", "pxemenuoptions"
 * and "hostMAC" while explaining why they are not in the code. A source
 * test that reads its own documentation passes for the wrong reason and
 * keeps passing after the code regresses. Same failure the browser-less
 * session gate hit in GH-1113.
 *
 * @param string $code The PHP source.
 *
 * @return string
 */
function searchStripComments($code)
{
    $out = '';
    foreach (token_get_all('<?php ' . $code) as $tk) {
        if (is_array($tk)) {
            if (in_array($tk[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $out .= $tk[1];
            continue;
        }
        $out .= $tk;
    }
    return $out;
}

$search = searchMethodBody($src, 'public static function search($class, $item)');
$uni = searchMethodBody($src, 'public static function unisearch($item, $limit = 0)');
$rows = searchMethodBody($src, 'private static function _searchRows(');

$t->check('_searchRows() exists', '' !== $rows);
$t->check('search() body was located', '' !== $search);

// Everything below reads CODE, never prose.
$search = searchStripComments($search);
$uni = searchStripComments($uni);
$rows = searchStripComments($rows);
$t->check('search() exists', '' !== $search);
$t->check('unisearch() exists', '' !== $uni);

/*
 * 1. The defect itself: search() must not go through unisearch().
 */
$t->check(
    'search() no longer calls unisearch()',
    false === strpos($search, 'unisearch(')
);
$t->check(
    'search() no longer reads a bucket out of the universal result',
    false === strpos($search, 'json_decode(self::getData())')
);
$t->check(
    'search() queries the class it was asked about',
    false !== strpos($search, 'self::_searchRows($classname, $item)')
);
$t->check(
    'unisearch() uses the same body',
    false !== strpos($uni, 'self::_searchRows(')
);

/*
 * 2. The guards are in the SHARED body, in one copy. Each of these three is
 *    checked both ways: present in the helper, absent from the two callers.
 *    Present-only would pass just as well if a caller kept a stale copy.
 */
$guards = [
    'the object-scope boundary' => '_requestScopeWhere(',
    'the credential rule for settings' => 'isSensitiveSetting(',
    'the hostMAC join that makes hosts findable by MAC' => 'hostMAC',
    'the storagenode hostname arm' => 'ngmHostname',
    'the settings value arm' => 'settingValue',
];
foreach ($guards as $what => $needle) {
    $t->check(
        "$what lives in _searchRows()",
        false !== strpos($rows, $needle)
    );
    $t->check(
        "$what is not duplicated in search()",
        false === strpos($search, $needle)
    );
    $t->check(
        "$what is not duplicated in unisearch()",
        false === strpos($uni, $needle)
    );
}

/*
 * 3. The ipxe remap belongs to the search BOX's bucket, not to the class.
 *    It has to stay in unisearch() and stay out of the shared body, or the
 *    wrong-rows defect comes straight back through the other door.
 */
$t->check(
    'the ipxe -> pxemenuoptions remap is in unisearch()',
    false !== strpos($uni, "'pxemenuoptions'")
);
$t->check(
    'and NOT in the shared body, which must query the class it is given',
    false === strpos($rows, "'pxemenuoptions'")
);
$t->check(
    'search() does no remapping of its own either',
    false === strpos($search, 'pxemenuoptions')
);

/*
 * 4. null and empty mean different things, and both callers rely on it:
 *    unisearch() must not stamp a heading for results that can never
 *    arrive, and search() must not report "no matches" for a question it
 *    never asked.
 */
$t->check(
    '_searchRows() returns null for a class with no name field',
    false !== strpos($rows, "if (!isset(\$classVars['databaseFields']['name'])) {")
    && false !== strpos($rows, 'return null;')
);
$t->check(
    'unisearch() skips a null BEFORE stamping _lang',
    false !== strpos($uni, 'if (null === $rows) {')
    && strpos($uni, 'if (null === $rows) {') < strpos($uni, "\$data['_lang'][\$search] =")
);

/*
 * 5. The document and the route agree on which classes are searchable.
 *
 * OpenAPI::_isSearchable() was a NECESSARY condition only, because
 * $searchPages membership decided the rest. It is sufficient now, so the
 * two must test the same thing -- if they drift, the document starts
 * advertising operations that answer nothing again.
 */
$openapi = file_get_contents($web . '/src/Router/OpenAPI.php');
$isSearchable = searchMethodBody(
    $openapi,
    'private static function _isSearchable($class)'
);
$t->check(
    '_isSearchable() tests the same isset on databaseFields[name]',
    false !== strpos($isSearchable, "isset(\$vars['databaseFields']['name'])")
);
$t->check(
    'and its docblock no longer claims the condition is merely necessary',
    false === strpos(
        $openapi,
        'A necessary condition, not a sufficient one.'
    )
);

/*
 * 6. Every class the document marks searchable really has the field the
 *    helper needs, checked against the live class list rather than a copy.
 */
$checked = 0;
foreach ((array)FOG\Route::$validClasses as $class) {
    $vars = FOG\FOGCore::getClass($class, '', true);
    if (!is_array($vars) || !isset($vars['databaseFields'])) {
        continue;
    }
    $checked++;
    $hasName = isset($vars['databaseFields']['name']);
    $method = new \ReflectionMethod('FOG\OpenAPI', '_isSearchable');
    $method->setAccessible(true);
    $t->check(
        "$class: the document and the helper agree on searchability",
        $hasName === $method->invoke(null, $class)
    );
}
$t->check(
    'the class sweep actually resolved classes',
    $checked > 30
);

$t->finish();
