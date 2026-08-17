<?php
/**
 * Browser-less entry points must not be handed a session.
 *
 * Initiator used to call session_start() unconditionally, and 62 entry
 * points reach it through commons/base.inc.php. Everything under service/
 * and status/, the API and the iPXE endpoints inherited it -- and none of
 * them can carry a cookie back, so each request allocated a session record
 * that was written once, never read again, and left for gc. A PXE boot, or
 * a fleet of fog-clients polling on a timer, is a steady stream of them.
 *
 * It is now conditional on a session cookie being presented or the entry
 * point declaring FOG_WANTS_SESSION. This gate pins the three properties
 * that make that safe, so a future edit cannot quietly undo them.
 *
 * Static on purpose: proving it over HTTP needs a running server with a
 * database, and a test that needs those is a test nobody runs. The HTTP
 * behaviour was verified by hand when the change landed -- see the PR.
 *
 * Usage: php tests/no-session-for-browserless.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
chdir($root);

$fails = [];

// 1. The session_start() in Initiator must be guarded.
$init = 'packages/web/commons/init.php';
$raw = is_readable($init) ? file_get_contents($init) : '';
if ('' === $raw) {
    fwrite(STDERR, "FAIL: cannot read $init\n");
    exit(1);
}
/*
 * Strip comments before looking for the guard. The block explaining WHY the
 * guard exists names both FOG_WANTS_SESSION and session_name(), so searching
 * the raw file would pass on the strength of its own documentation even with
 * the condition deleted.
 */
$src = '';
foreach (token_get_all($raw) as $tok) {
    if (is_array($tok)
        && in_array($tok[0], [T_COMMENT, T_DOC_COMMENT], true)
    ) {
        continue;
    }
    $src .= is_array($tok) ? $tok[1] : $tok;
}
if (false === strpos($src, 'FOG_WANTS_SESSION')) {
    $fails[] = "$init no longer consults FOG_WANTS_SESSION -- session_start()"
        . " is unconditional again";
}
if (false === strpos($src, 'session_name()')) {
    $fails[] = "$init no longer tests for an incoming session cookie, so a"
        . " returning browser would be handed a NEW session every request";
}

/*
 * 2. Exactly one entry point may declare FOG_WANTS_SESSION: the web UI. If a
 *    service or status endpoint starts declaring it, the saving is gone --
 *    and it would be declaring it because something there started using
 *    session state, which check 3 is what really catches.
 */
$declarers = [];
foreach (['packages/web/service', 'packages/web/status', 'packages/web/api',
    'packages/web/management', 'packages/web/maintenance'] as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
    foreach ($it as $f) {
        if (!$f->isFile() || 'php' !== $f->getExtension()) {
            continue;
        }
        $p = $f->getPathname();
        if (false !== strpos(file_get_contents($p), "define('FOG_WANTS_SESSION'")) {
            $declarers[] = $p;
        }
    }
}
sort($declarers);
if ($declarers !== ['packages/web/management/index.php']) {
    $fails[] = 'FOG_WANTS_SESSION should be declared by the web UI alone, but'
        . ' is declared by: ' . (count($declarers) ? implode(', ', $declarers) : 'nothing');
}

/*
 * 3. The load-bearing one. Browser-less code must not touch session state --
 *    if it does, the guard above silently drops whatever it wrote instead of
 *    failing loudly, which is the worst possible outcome.
 */
$browserless = [
    'packages/web/service',
    'packages/web/status',
    'packages/web/lib/reg-task',
    'packages/web/lib/client',
    'packages/web/lib/service',
];
foreach ($browserless as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
    foreach ($it as $f) {
        if (!$f->isFile() || 'php' !== $f->getExtension()) {
            continue;
        }
        $p = $f->getPathname();
        foreach (token_get_all(file_get_contents($p)) as $tok) {
            if (is_array($tok) && T_VARIABLE === $tok[0] && '$_SESSION' === $tok[1]) {
                $fails[] = "$p:{$tok[2]} touches \$_SESSION, but nothing"
                    . ' guarantees a session exists on that path -- the write'
                    . ' will be silently discarded';
            }
        }
    }
}

if (count($fails) > 0) {
    fwrite(STDERR, 'FAIL: ' . count($fails) . " problem(s):\n");
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok: browser-less entry points are handed no session\n";
exit(0);
