<?php
/**
 * Unchecking "remove file data" must keep the file.
 *
 * The delete modal on an image or a snapin carries a checkbox that also
 * queues the payload on the storage node for deletion. Two independent
 * defects made an UNCHECKED box delete it anyway, and there is no undo.
 *
 *  1. fog.image.edit.js and fog.snapin.edit.js handled the uncheck with a
 *     bare `return`, leaving the module-scope `opts` at a previous check's
 *     {andFile: 1}. registerGeneralTab's deleteOpts() hands that same
 *     variable to the POST, so ticking the box, changing your mind and
 *     deleting still sent andFile=1. fog.group.edit.js, the third copy of
 *     this handler, always cleared it -- which is the shape pinned here.
 *
 *  2. FOGPage::deletePost() gated on bare isset($_POST['andFile']), while
 *     FOGPage::deletemulti() has always required == 1. $.deleteSelected
 *     posts the field on every bulk delete with the value 0 when it is
 *     unchecked, so the two paths disagreed about what "no" looks like,
 *     and the single-item path read an explicit no as yes.
 *
 * Both halves are pinned because either one alone deletes the file: the JS
 * sends the wrong payload, and the PHP misreads a right one.
 *
 * Usage: php tests/andfile-unchecked-keeps-the-file.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('andfile-unchecked-keeps-the-file');

$t = new FogChecks();

$web = dirname(__DIR__) . '/packages/web';

// Every symbol searched for below is also named in the comments that explain
// the fix, so each check runs against a comment-stripped copy. Otherwise a
// comment describing the fix is indistinguishable from the fix.
$strip = function ($src) {
    $src = preg_replace('#/\*.*?\*/#s', '', (string)$src);
    return preg_replace('#(^|\s)//[^\n]*#', '$1', $src);
};
$read = function ($rel) use ($web, $strip) {
    return $strip(file_get_contents($web . '/' . $rel));
};

// Collapse whitespace so an assertion is about the code, not its indentation.
$flat = function ($src) {
    return preg_replace('/\s+/', ' ', (string)$src);
};

// ---------------------------------------------------------------------------
// 1. The JS half. Every #andFile / #andHosts change handler that owns an
//    `opts` object must clear it when the box comes off.
// ---------------------------------------------------------------------------
$handlers = [
    'management/js/fog/image/fog.image.edit.js' => 'andFile',
    'management/js/fog/snapin/fog.snapin.edit.js' => 'andFile',
    'management/js/fog/group/fog.group.edit.js' => 'andHosts',
];
foreach ($handlers as $rel => $field) {
    $src = $flat($read($rel));
    // The handler body, from the on('change' through the opts assignment.
    $ok = (bool)preg_match(
        '/\$\(\'#' . $field . '\'\)\.on\(\'change\','
        . '.{0,400}?if \(!this\.checked\) \{ opts = \{\}; return; \}/',
        $src
    );
    $t->check(
        sprintf('%s clears opts when #%s is unchecked', basename($rel), $field),
        $ok
    );
    // And the shape that shipped broken: a bare return with no reset.
    $t->check(
        sprintf('%s does not return without clearing opts', basename($rel)),
        !preg_match(
            '/\$\(\'#' . $field . '\'\)\.on\(\'change\','
            . '.{0,400}?if \(!this\.checked\) \{ return; \}/',
            $src
        )
    );
}

// ---------------------------------------------------------------------------
// 2. The PHP half. Both delete paths must read the value, not its presence.
// ---------------------------------------------------------------------------
$page = $read('src/Base/FOGPage.php');

foreach (['andFile', 'andHosts'] as $field) {
    // Count every gate on this field, and require each to test the value.
    preg_match_all(
        '/isset\(\$_POST\[\'' . $field . '\'\]\)(\s*&&\s*\$_POST\[\''
        . $field . '\'\]\s*==\s*1)?/',
        $page,
        $m,
        PREG_SET_ORDER
    );
    $gates = count($m);
    $valued = 0;
    foreach ($m as $hit) {
        if (!empty($hit[1])) {
            $valued++;
        }
    }
    $t->check(
        sprintf('FOGPage gates on $_POST[\'%s\'] at all (found %d)', $field, $gates),
        $gates > 0
    );
    $t->check(
        sprintf(
            'every $_POST[\'%s\'] gate in FOGPage requires == 1 (%d of %d)',
            $field,
            $valued,
            $gates
        ),
        $gates === $valued
    );
}

// The two paths must not have drifted apart again: deletemulti() assigns the
// flags, deletePost() tests them inline, and both now carry the value test.
$t->check(
    'deletemulti() still requires andFile == 1',
    1 === preg_match(
        '/\$andfiles\s*=\s*isset\(\$_POST\[\'andFile\'\]\)\s*&&\s*'
        . '\$_POST\[\'andFile\'\]\s*==\s*1;/',
        $page
    )
);

// ---------------------------------------------------------------------------
// 3. The thing the flag actually does, so a reader of this test can see what
//    an unchecked box was costing: deleteFile() queues the payload for the
//    FileDeleter daemon, and destroy() does not.
// ---------------------------------------------------------------------------
foreach (['src/Items/Image.php', 'src/Items/Snapin.php'] as $rel) {
    $src = $flat($read($rel));
    $t->check(
        sprintf('%s::deleteFile() queues a FileDeleteQueue row', basename($rel, '.php')),
        (bool)preg_match(
            '/function deleteFile\(\).{0,800}?new FileDeleteQueue\(\)/',
            $src
        )
    );
}

$t->finish();
