<?php
/**
 * Gate: the node sub-menu labels are whole translatable phrases.
 *
 * GH-435. `List All X` / `Create New X` used to be built by sprintf()ing a
 * translated noun into a translated format string. That cannot be translated
 * correctly in any language that inflects: `Creer un nouveau %s` is masculine,
 * so `machine` and `image` need `une nouvelle` and `utilisateur` needs
 * `nouvel`. One format string cannot be all three. The list label additionally
 * appended a literal English `s` to an already-translated noun.
 *
 * WHAT THIS PINS, AND WHY IN THIS SHAPE
 *
 * The methods are lifted out of FOGPage.php and executed, not grepped for.
 * Grepping for the name `_nodeMenuStrings` passes when the body has been
 * gutted, and grepping the call site passes when the call site is dead. Both
 * bodies here are self-contained -- no $DB, no session, no FOGBase -- so
 * running them costs nothing and is the only thing that actually goes red when
 * a case is dropped or the degradation arm is removed.
 *
 * The wiring in _buildSubMenuItems() cannot be executed the same way (it needs
 * self::$foglang and a booted class), so it is anchored as source: both the
 * call AND the guard that keeps the composed fallback reachable for plugins.
 * Anchoring the guard as well as the call is deliberate -- a change that keeps
 * the call but drops `if (!count($menu))` would silently take the fallback out
 * from under every plugin node.
 *
 * Usage: php tests/menu-labels-are-whole-phrases.test.php
 * Exit 0 = clean, 1 = at least one failure.
 */

$root = dirname(__DIR__);
$src = $root . '/packages/web/src/Base/FOGPage.php';
$fails = [];

$text = file_get_contents($src);
if (false === $text) {
    fwrite(STDERR, "cannot read $src\n");
    exit(1);
}

/**
 * Lift one method's source, brace-matched from its signature.
 *
 * @param string $text  file contents
 * @param string $name  method name
 *
 * @return string empty when not found
 */
function liftMethod($text, $name)
{
    $at = strpos($text, ' function ' . $name . '(');
    if (false === $at) {
        return '';
    }
    $open = strpos($text, '{', $at);
    if (false === $open) {
        return '';
    }
    // Brace matching is enough here: neither method contains a brace inside a
    // string or a comment. A method that gains one will fail loudly at eval,
    // which is the right outcome -- it means this lift needs revisiting.
    $depth = 0;
    $n = strlen($text);
    for ($i = $open; $i < $n; $i++) {
        if ('{' === $text[$i]) {
            $depth++;
        } elseif ('}' === $text[$i]) {
            $depth--;
            if (0 === $depth) {
                $head = strrpos(substr($text, 0, $at), "\n");
                return substr($text, $head + 1, $i - $head);
            }
        }
    }
    return '';
}

$lifted = '';
foreach (['_nodeMenuStrings', '_composeMenuLabel'] as $m) {
    $body = liftMethod($text, $m);
    if ('' === $body) {
        $fails[] = "FOGPage::$m() not found -- GH-435 wiring removed?";
        continue;
    }
    $lifted .= $body . "\n";
}

if (count($fails)) {
    foreach ($fails as $f) {
        echo "FAIL: $f\n";
    }
    exit(1);
}

// `_()` is gettext, which is not loaded in a bare CLI test and would translate
// against the test runner's own locale if it were. Identity keeps the
// assertions about the msgids the source actually passes.
if (!function_exists('_')) {
    /**
     * Stand-in for gettext.
     *
     * @param string $s msgid
     *
     * @return string
     */
    function _($s)
    {
        return $s;
    }
}

eval('class MenuProbe { ' . $lifted . ' 
    public static function strings($n) { return self::_nodeMenuStrings($n); }
    public static function compose($f, $v) { return self::_composeMenuLabel($f, $v); }
}');

/*
 * Every node whose pair must be written out. This list is the point of the
 * change: these are core nodes, so their labels are ours to get right, and a
 * node dropped from the switch silently goes back to the composed form.
 */
$nodes = [
    'group' => 'Group', 'host' => 'Host', 'image' => 'Image',
    'ipxe' => 'Ipxe Menu', 'module' => 'Module', 'printer' => 'Printer',
    'role' => 'Role', 'site' => 'Site', 'snapin' => 'Snapin',
    'software' => 'Software',
    'storagegroup' => 'Storage Group', 'storagenode' => 'Storage Node',
    'user' => 'User', 'usergroup' => 'User Group',
];

$msgids = [];
foreach ($nodes as $node => $noun) {
    $pair = MenuProbe::strings($node);
    if (!isset($pair['list'], $pair['add'])) {
        $fails[] = "node '$node' has no written-out label pair";
        continue;
    }
    // The msgid must be the whole phrase. A pair that still carries a %s is
    // the composed form wearing a switch case.
    foreach ($pair as $which => $phrase) {
        if (false !== strpos($phrase, '%')) {
            $fails[] = "node '$node' $which label '$phrase' still has a placeholder";
        }
    }
    if (false === strpos($pair['add'], $noun)) {
        $fails[] = "node '$node' add label '{$pair['add']}' does not name '$noun'";
    }
    $msgids[] = $pair['list'];
    $msgids[] = $pair['add'];
}

/*
 * Plugins are NOT in the switch and must keep the composed fallback: a
 * plugin's node name is not knowable from core, and a plugin ships its own
 * catalog. A `default:` arm added to the switch would break every one of them.
 */
if ([] !== MenuProbe::strings('helloworld')) {
    $fails[] = 'unknown node did not fall through to the composed form';
}

/*
 * The catalog is edited by translators, so the format string in the fallback
 * is not under the codebase's control -- and a bad one fails DIFFERENTLY on
 * the two PHP versions this project supports. PHP 8 throws (ArgumentCountError
 * for a stray %, ValueError for an unknown specifier); 7.4 warns and returns
 * false. Uncaught, that is a 500 on every page on 8 and an empty menu label on
 * 7.4, so both arms are needed and both are exercised here.
 *
 * Not hypothetical: es_ES shipped `Crear nuevo grupo` for `Create New %s`,
 * having dropped the placeholder entirely.
 */
$degrade = [
    'stray percent' => 'Lister 100% des %s',
    'unknown specifier' => 'List %q of %s',
    'no placeholder at all' => 'Crear nuevo grupo',
];
foreach ($degrade as $why => $format) {
    // @ suppresses only the 7.4 warning, which is real output, not the value
    // under test. The assertion is on what comes back.
    $got = @MenuProbe::compose($format, 'Hosts');
    if ('' === (string)$got || false === $got) {
        $fails[] = "compose() returned nothing for a $why catalog format";
    }
}
if ('List All Hosts' !== MenuProbe::compose('List All %s', 'Hosts')) {
    $fails[] = 'compose() broke the ordinary substitution';
}

/*
 * The degrade loop above covers BOTH arms, but only when both interpreters
 * run it. PHP 8 sprintf() never returns false -- it throws -- so on 8.3 the
 * catch handles every case and deleting the `false === $out` arm leaves every
 * assertion green; mutation-checked on this box, which has only 8.3. The
 * suite runs on 7.4 as well (`fogproject / tests (PHP 7.4)` is one of the
 * required contexts), and there it is the reverse: nothing throws, sprintf
 * returns false, and that arm is the only thing between a bad catalog and an
 * empty menu label.
 *
 * So the loop is the real gate and this is the half of it 8.3 cannot see.
 * Anchored on the whole statement rather than the method name, because a name
 * survives the body being gutted.
 */
$compose = liftMethod($text, '_composeMenuLabel');
if (false === strpos($compose, "return '' === (string)\$out ? (string)\$value : (string)\$out;")) {
    $fails[] = 'compose() lost the PHP 7.4 arm -- sprintf() returns false there, '
        . 'it does not throw, so the catch alone leaves 7.4 broken';
}

/*
 * The wiring. _buildSubMenuItems() must call the switch AND must keep the
 * composed fallback reachable behind an emptiness guard.
 */
$build = liftMethod($text, '_buildSubMenuItems');
if ('' === $build) {
    $fails[] = 'FOGPage::_buildSubMenuItems() not found';
} else {
    if (false === strpos($build, '_nodeMenuStrings(')) {
        $fails[] = '_buildSubMenuItems() no longer calls _nodeMenuStrings()';
    }
    if (false === strpos($build, 'if (!count($menu))')) {
        $fails[] = '_buildSubMenuItems() lost the fallback guard for plugin nodes';
    }
    if (false === strpos($build, '_composeMenuLabel(')) {
        $fails[] = '_buildSubMenuItems() no longer composes safely for plugin nodes';
    }
}

/*
 * Catalog side. Two separate claims:
 *
 *  - fr_FR and es_ES were hand-written for GH-435, so an empty msgstr there is
 *    a regression to English on the very locales the issue was raised about.
 *  - NO locale may render a literal %s. Those msgids predate this change and
 *    several catalogs held `Neue %s erstellen` under msgid `Create New Host`,
 *    from a fuzzy match somebody accepted. That IS the user-visible bug.
 */
$catalogs = glob($root . '/packages/web/management/languages/*.UTF-8/LC_MESSAGES/messages.po');
if (count($catalogs) < 2) {
    $fails[] = 'no gettext catalogs found to check';
}
$handWritten = ['fr_FR', 'es_ES'];
foreach ($catalogs as $po) {
    preg_match('#/([^/]+)\.UTF-8/#', $po, $lm);
    $locale = $lm[1] ?? $po;
    $lines = file($po, FILE_IGNORE_NEW_LINES);
    $entries = [];
    $n = count($lines);
    for ($i = 0; $i < $n; $i++) {
        // COMPILED entries only, which is a narrower thing than "live". An
        // obsolete `#~` entry is not compiled, and neither is a `#, fuzzy`
        // one -- msgfmt drops both, so gettext falls back to the msgid and
        // nothing in either is ever shown to a user.
        //
        // That distinction is load-bearing here rather than pedantic. The
        // `regenerate` job runs msgmerge on every pull request, and msgmerge
        // FILLS an empty msgstr with a fuzzy guess: the eight `List All X`
        // msgids this change adds came back from it holding `List All %s` in
        // en_US. Asserting over those would fail the build for a state the
        // catalog reaches on its own, on entries no user can see. What must
        // never carry a %s is a CONFIRMED entry, and that is what is checked.
        if (!preg_match('/^msgid "(.*)"$/', $lines[$i], $m)) {
            continue;
        }
        if ($i > 0
            && 0 === strpos($lines[$i - 1], '#,')
            && false !== strpos($lines[$i - 1], 'fuzzy')
        ) {
            continue;
        }
        if (isset($lines[$i + 1])
            && preg_match('/^msgstr "(.*)"$/', $lines[$i + 1], $s)
        ) {
            $entries[stripcslashes($m[1])] = stripcslashes($s[1]);
        }
    }
    foreach ($msgids as $msgid) {
        if (!array_key_exists($msgid, $entries)) {
            continue;
        }
        $str = $entries[$msgid];
        if (false !== strpos($str, '%s')) {
            $fails[] = "$locale renders a literal %s for '$msgid'";
        }
        if (in_array($locale, $handWritten, true) && '' === $str) {
            $fails[] = "$locale lost its hand-written translation of '$msgid'";
        }
    }
    foreach ($handWritten as $hw) {
        if ($locale !== $hw) {
            continue;
        }
        foreach ($msgids as $msgid) {
            if (!array_key_exists($msgid, $entries)) {
                $fails[] = "$locale has no entry for '$msgid'";
            }
        }
    }
}

/*
 * The only two CONFIRMED translations these msgids had before GH-435. Every
 * other `Create New X` entry in every catalog was `#, fuzzy` -- msgmerge's
 * guess, excluded from the compiled .mo, never seen by anyone, and frequently
 * nonsense (de_DE offered "Neuen Drucker erstellen", Create New PRINTER, for
 * `Create New Storage Node`). The migration composes over fuzzy entries for
 * that reason, and these two are what it must NOT touch: they are a person's
 * work, and they read better than anything composition produces -- no space
 * around the noun, which is correct Japanese.
 */
$confirmed = [
    'Create New Printer' => '新しいプリンターを作成',
    'Create New Snapin' => '新しいスナップインを作成',
];
$ja = $root . '/packages/web/management/languages/ja_JP.UTF-8/LC_MESSAGES/messages.po';
$jaText = file_get_contents($ja);
foreach ($confirmed as $msgid => $msgstr) {
    $want = 'msgid "' . $msgid . '"' . "\n" . 'msgstr "' . $msgstr . '"';
    if (false === strpos((string)$jaText, $want)) {
        $fails[] = "ja_JP lost its confirmed translation of '$msgid'";
    }
}

if (count($fails)) {
    foreach ($fails as $f) {
        echo "FAIL: $f\n";
    }
    echo count($fails) . " failure(s)\n";
    exit(1);
}

echo 'OK: ' . count($msgids) . " menu labels are whole phrases, "
    . count($catalogs) . " catalogs clean\n";
exit(0);
