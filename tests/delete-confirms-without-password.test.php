<?php
/**
 * Delete asks for confirmation, never for a password.
 *
 * Reported on the forum (topic 18239): an account that an OIDC provider
 * created has no local password, so the delete password prompt refused it
 * on every bulk delete. The prompt existed to confirm that somebody meant to
 * delete many items. A password proves who is at the keyboard, not what they
 * meant. FOG also already skipped it on the edit-page delete of a host and
 * on the REST API, so it was never a complete control.
 *
 * WHAT IS PINNED, and the failure each one catches:
 *
 *  1. No server path asks for a password. checkauth() and every call to it
 *     are gone. One call left behind is a fatal on every delete that reaches
 *     it. The role and user group edit-page deletes called it with no
 *     password field, so they answered 401 whenever the setting was on.
 *  2. No delete dialog renders a password field.
 *  3. $.deleteSelected ALWAYS opens the count dialog before it posts. With
 *     FOG_REAUTH_ON_DELETE off it used to delete on the first click, with no
 *     dialog at all, because the password prompt WAS the dialog.
 *  4. It posts no password, and has no 401 retry that prompts for one.
 *  5. Both settings are retired: a schema step deletes the rows, nothing
 *     loads them, the settings page does not list them, and the hidden input
 *     that carried one to the browser is gone.
 *  6. FOG_BCACHE_VER moved, or browsers keep the old fog.common.js.
 *
 * Usage: php tests/delete-confirms-without-password.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('delete-confirms-without-password');

$t = new FogChecks();

$web = dirname(__DIR__) . '/packages/web';

// Comments name every symbol searched for below, so each source check runs
// against a comment-stripped copy. Otherwise the fix and a comment that
// describes the fix are indistinguishable.
$strip = function ($src) {
    $src = preg_replace('#/\*.*?\*/#s', '', $src);
    return preg_replace('#(^|\s)//[^\n]*#', '$1', $src);
};
$read = function ($rel) use ($web, $strip) {
    return $strip((string)file_get_contents($web . '/' . $rel));
};

// ---------------------------------------------------------------------------
// 1. No server path asks for a password.
// ---------------------------------------------------------------------------
$files = new \RecursiveIteratorIterator(
    new \RecursiveDirectoryIterator($web . '/src', \FilesystemIterator::SKIP_DOTS)
);
$scanned = 0;
$callers = [];
foreach ($files as $file) {
    if ('php' !== $file->getExtension()) {
        continue;
    }
    $scanned++;
    // Case-sensitive, whole word: checkAuthAndCSRF() is the CSRF gate and
    // stays.
    if (preg_match('/\bcheckauth\b/', $strip(file_get_contents($file)))) {
        $callers[] = substr($file->getPathname(), strlen($web) + 1);
    }
}
$t->check('the src/ sweep read real files', $scanned > 100);
$t->check(
    'nothing in src/ defines or calls checkauth(): '
    . (count($callers) ? implode(', ', $callers) : 'none'),
    count($callers) < 1
);

// ---------------------------------------------------------------------------
// 2. No delete dialog renders a password field.
// ---------------------------------------------------------------------------
$pages = [
    'src/Base/FOGPage.php',
    'src/Pages/HostManagement.php',
    'src/Pages/FOGConfigurationPage.php',
    'src/Pages/UserManagement.php',
];
foreach ($pages as $rel) {
    $src = $read($rel);
    $t->check(
        "$rel renders no delete password field",
        !preg_match("/'(deletePassword|deletePW|apitokenDeletePassword|apitokenDeletePW)'/", $src)
        && false === strpos($src, "_('Confirm password')")
    );
}

// ---------------------------------------------------------------------------
// 3 and 4. The shared helper always confirms, and never posts a password.
// ---------------------------------------------------------------------------
$common = $read('management/js/fog/fog.common.js');
$deleteSelected = '';
if (preg_match('/\$\.deleteSelected = function\(.*?\n};\n/s', $common, $m)) {
    $deleteSelected = $m[0];
}
$t->check('$.deleteSelected is found', '' !== $deleteSelected);
$t->check(
    '$.deleteSelected opens the confirm dialog unless already confirmed',
    (bool)preg_match(
        '/if \(!opts\.confirmed\) \{\s*\$\.confirmDelete\(/',
        $deleteSelected
    )
);
$t->check(
    'the dialog is not conditional on a setting',
    false === strpos($common, 'shouldReAuth')
    && false === strpos($read('management/other/index.php'), 'reAuthDelete')
);
$t->check(
    '$.deleteSelected posts no password and has no 401 re-prompt',
    '' !== $deleteSelected
    && false === strpos($deleteSelected, 'fogguipass')
    && !preg_match('/status\s*==+\s*401/', $deleteSelected)
);
$confirm = '';
if (preg_match('/\$\.confirmDelete = function\(count, cb, opts\) \{.*?\n};\n/s', $common, $m)) {
    $confirm = $m[0];
}
$t->check('$.confirmDelete(count, cb, opts) is defined', '' !== $confirm);
$t->check(
    '$.confirmDelete reads no password',
    '' !== $confirm && false === stripos($confirm, 'password')
);
$t->check('$.reAuth is gone', false === strpos($common, '$.reAuth'));

// ---------------------------------------------------------------------------
// 5. Both settings are retired.
// ---------------------------------------------------------------------------
$schema = (string)file_get_contents($web . '/commons/schema.php');
// Step 437 by its number. This read "the newest step" until 438 landed, and
// every later step would have failed it for a reason unrelated to deletes.
$from = (int)strpos($schema, "\n// 437\n");
$to = strpos($schema, "\n// 438\n", $from);
$lastStep = (string)substr($schema, $from, false === $to ? null : $to - $from);
$t->check(
    'schema step 437 deletes both settings',
    (bool)preg_match('/DELETE FROM `globalSettings`/', $lastStep)
    && false !== strpos($lastStep, "'FOG_REAUTH_ON_DELETE'")
    && false !== strpos($lastStep, "'FOG_REAUTH_ON_EXPORT'")
);
foreach ([
    'src/Base/FOGCore.php',
    'src/Base/FOGBase.php',
    'src/Pages/FOGConfigurationPage.php',
    'management/other/index.php',
] as $rel) {
    $src = $read($rel);
    $t->check(
        "$rel no longer reads the retired settings",
        false === strpos($src, 'FOG_REAUTH_ON_')
        && false === strpos($src, 'fogdeleteactive')
        && false === strpos($src, 'fogexportactive')
    );
}

// ---------------------------------------------------------------------------
// 6. The browser cache moved.
// ---------------------------------------------------------------------------
$t->check(
    'FOG_BCACHE_VER is at least 367',
    (bool)preg_match(
        "/define\('FOG_BCACHE_VER', (\d+)\)/",
        (string)file_get_contents($web . '/src/Base/System.php'),
        $m
    )
    && (int)$m[1] >= 367
);

$t->finish();
