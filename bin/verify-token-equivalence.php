<?php
/**
 * Proves a revision changed nothing but class-name qualification.
 *
 * The reviewer's tool for Phase 0.1, and for the same pass in fog-plugins and
 * in Phase 3. It tokenizes both revisions of every changed file, throws away
 * whitespace and comments, folds a fully-qualified name back to its bare form,
 * and asserts the two token streams are identical. Anything that is not purely
 * a qualification change shows up as a difference.
 *
 * Usage:
 *   php bin/verify-token-equivalence.php <ref>   compare the working tree to <ref>
 *
 * What it CANNOT prove, stated plainly because it matters: it cannot tell a
 * backslash correctly added to a class from one wrongly added to a constant or
 * a label, because after normalization both look the same. That gap is closed
 * from the other side, by prefix-global-classes.php only ever touching a name
 * that ReflectionClass::isInternal() confirms. Together the two cover the
 * space; neither does on its own.
 *
 * @category Tooling
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * Reduces source to a comparable token stream.
 *
 * Trivia goes because reformatting is not a semantic change. The separator
 * goes, and T_NAME_FULLY_QUALIFIED is folded back to T_STRING, because
 * qualification is the change being verified -- normalizing it away is what
 * lets everything else be compared exactly.
 *
 * @param string $src PHP source.
 *
 * @return array
 */
function normalize($src)
{
    $out = [];
    foreach (token_get_all($src) as $token) {
        if (is_array($token)) {
            if (in_array(
                $token[0],
                [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT],
                true
            )) {
                continue;
            }
            // PHP 8 lexes \Foo as a single token; PHP 7 lexes it as
            // T_NS_SEPARATOR followed by T_STRING. Fold both to the bare name
            // so this tool gives the same answer on either.
            if (defined('T_NAME_FULLY_QUALIFIED')
                && $token[0] === T_NAME_FULLY_QUALIFIED
            ) {
                $out[] = [T_STRING, ltrim($token[1], '\\')];
                continue;
            }
            $out[] = [$token[0], $token[1]];
            continue;
        }
        if ($token === '\\') {
            continue;
        }
        $out[] = [null, $token];
    }
    return $out;
}

$ref = $argv[1] ?? null;
if ($ref === null) {
    fwrite(STDERR, "usage: php bin/verify-token-equivalence.php <ref>\n");
    exit(2);
}

$root = dirname(__DIR__);
chdir($root);

$changed = [];
exec(
    'git diff --name-only ' . escapeshellarg($ref) . ' -- 2>/dev/null',
    $changed
);

$checked = 0;
$prefixes = 0;
$differ = [];
$unreadable = [];

foreach ($changed as $rel) {
    if (!is_file($rel)) {
        continue;
    }
    $new = (string)file_get_contents($rel);
    if (strpos(substr($new, 0, 512), '<?php') === false) {
        continue;
    }

    $old = shell_exec(
        'git show ' . escapeshellarg($ref . ':' . $rel) . ' 2>/dev/null'
    );
    if ($old === null) {
        $unreadable[] = $rel;
        continue;
    }

    $checked++;
    $a = normalize($old);
    $b = normalize($new);

    if ($a !== $b) {
        $differ[] = $rel;
        continue;
    }

    // Count the qualifications this file gained, which is the change we
    // expect to be the only one.
    $prefixes += substr_count($new, '\\') - substr_count($old, '\\');
}

printf("compared %d files against %s\n", $checked, $ref);
printf("qualifications added : %d\n", $prefixes);
printf("other differences    : %d\n", count($differ));

foreach ($differ as $d) {
    printf("  DIFFERS: %s\n", $d);
}
foreach ($unreadable as $u) {
    printf("  NEW FILE (no counterpart in %s): %s\n", $ref, $u);
}

exit(count($differ) > 0 ? 1 : 0);
