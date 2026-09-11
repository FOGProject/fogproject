<?php
/**
 * A failure notification must say WHY, and must survive a stale web tree.
 *
 * TaskError sends HOST_IMAGE_FAIL with ImageName and Reason (the flattened,
 * MAX_REASON-bounded opening of what FOS reported). Both bundled listeners
 * used to ignore all of it and push the fixed string "This host has failed to
 * image" -- which tells an admin nothing the task list did not already show,
 * and made the whole stored-report path invisible to anyone not looking at
 * the server.
 *
 * Three things are pinned, and each fails silently rather than loudly:
 *
 *   the reason is READ. Dropping it puts the notification back to a fixed
 *     string and nobody notices, because the push still arrives;
 *   every added key is read DEFENSIVELY. These events fire only when
 *     something has already gone wrong, and a web tree can be older than the
 *     plugin -- `$data['Reason']` on a payload that carries only HostName is
 *     a PHP warning at the worst possible moment;
 *   the substitution happens OUTSIDE _(). A msgid built at runtime matches
 *     no catalog entry, so interpolating into the translated string
 *     untranslates the whole sentence for every non-English install --
 *     permanently, and with no error anywhere.
 *
 * Source-level: these classes extend the plugin's Event base, which extends
 * FOG's, so neither loads without a booted FOG, a session and a database.
 *
 * Usage: php tests/imaging-failure-reason.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__) . '/packages/web/lib/plugins';

$fails = [];

$files = [
    'pushbullet' => $root . '/pushbullet/events/imagefail_pushbullet.event.php',
    'slack' => $root . '/slack/events/imagefail_slack.event.php'
];

foreach ($files as $plugin => $file) {
    if (!is_file($file)) {
        $fails[] = "$plugin: imagefail listener is missing at $file";
        continue;
    }
    $src = file_get_contents($file);
    // Comments stripped first, every time: the prose above each of these
    // changes names the very thing being looked for and would satisfy the
    // search on its own.
    $code = preg_replace('#/\*.*?\*/#s', '', $src);
    $code = preg_replace('#^\s*//.*$#m', '', $code);

    // Scoped to onEvent(), so a mention anywhere else in the file cannot
    // stand in for the listener actually using it.
    if (!preg_match('#function onEvent\(.*?\n    \}#s', $code, $m)) {
        $fails[] = "$plugin: could not read onEvent(), so nothing below is"
            . ' actually checked';
        continue;
    }
    $fn = $m[0];

    if (false === strpos($fn, "\$data['Reason']")) {
        $fails[] = "$plugin: the failure notification never reads Reason, so"
            . ' it says only that imaging failed and not why';
    }
    if (false === strpos($fn, "\$data['ImageName']")) {
        $fails[] = "$plugin: the failure notification never names the image";
    }
    // Defensive reads. `?? ''` is the shape; a bare read is the bug.
    foreach (['Reason', 'ImageName'] as $key) {
        if (!preg_match(
            "#\\\$data\['" . $key . "'\]\s*\?\?#",
            $fn
        )) {
            $fails[] = "$plugin: \$data['$key'] is read without a default, so"
                . ' a server that still sends only HostName turns this'
                . ' notification into a PHP warning';
        }
    }
    // Both must have a fallback string, or an older server pushes a sentence
    // with a hole in it ("failed imaging : ").
    if (!preg_match('#\$reason\s*=\s*_\(#', $fn)
        || !preg_match('#\$image\s*=\s*_\(#', $fn)
    ) {
        $fails[] = "$plugin: a missing key falls through without a translated"
            . ' placeholder, so the message renders with an empty slot';
    }
    // The substitution is outside _(). sprintf(_('...%1$s...'), $x) is right;
    // _("...$x...") or _('...' . $x) is the bug this pins.
    if (preg_match('#_\(\s*"[^"]*\$#', $fn)
        || preg_match("#_\(\s*'[^']*'\s*\.#", $fn)
    ) {
        $fails[] = "$plugin: a variable is interpolated INSIDE _(), so the"
            . ' msgid is built at runtime, matches no catalog entry and never'
            . ' translates';
    }
    if (!preg_match('#sprintf\(\s*\n?\s*_\(#', $fn)) {
        $fails[] = "$plugin: the message is not composed with sprintf() around"
            . ' a literal msgid, so the translated sentence cannot carry the'
            . ' image and the reason';
    }
    // Positional specifiers, so a translator can reorder. %s alone locks the
    // sentence to English word order.
    if (false === strpos($fn, '%1$s')) {
        $fails[] = "$plugin: the msgid uses bare %s rather than positional"
            . ' specifiers, so a translator cannot reorder the sentence';
    }
}

if ($fails) {
    echo 'FAIL: ' . count($fails) . " problem(s):\n";
    foreach ($fails as $f) {
        echo "  - $f\n";
    }
    exit(1);
}
echo "ok: both failure notifications report the reason, defensively\n";
exit(0);
