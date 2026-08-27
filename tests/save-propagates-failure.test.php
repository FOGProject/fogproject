<?php
/**
 * A model that overrides save() must propagate a failed parent write.
 *
 * FOGController::save() returns $this on success and false when the write
 * threw -- it catches, logs to history, and returns false. Every model that
 * overrides save() to add association work called it and dropped the
 * result, then returned $this->load(). load() returns an object, so the
 * override was truthy no matter what happened underneath.
 *
 * The consequence is not subtle once you see it: `if (!$obj->save())` is
 * dead code in every caller, and there are a lot of them. A create answers
 * 201 with a success message having written nothing, and the only trace is
 * a "Database save failed" line in the history table that nobody is
 * looking at. That is exactly how the site create page shipped broken --
 * `siteCatchAll` hit a CHECK constraint, MySQL threw 1366, and the page
 * cheerfully reported "Site added!" for every attempt.
 *
 * The association work is the second reason. assocSetter() and the MAC,
 * snapin and permission blocks all key off $this->get('id'); when the
 * parent write did not land there is no row for them to attach to, so
 * continuing writes orphans or silently no-ops.
 *
 * So the invariant is: an override that calls parent::save() must test the
 * result. This asserts the shape rather than the exact spelling, because
 * the point is that the value is CONSUMED -- a model that assigns it and
 * branches some other way is fine, one that discards it is not.
 *
 * Chaining is safe to break this way: a repo-wide search finds no
 * `->save()->` anywhere in packages/web, so returning false cannot turn a
 * failed save into a fatal on a method call against a boolean.
 *
 * DB-free: reads the source.
 *
 * Usage: php tests/save-propagates-failure.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$dir = dirname(__DIR__) . '/packages/web/src';
if (!is_dir($dir)) {
    fwrite(STDERR, "FAIL: cannot read $dir\n");
    exit(1);
}

$failures = [];
$checked = 0;

foreach (glob($dir . '/*/*.php') as $file) {
    $src = file_get_contents($file);
    $name = basename($file);

    // The override, from its signature to the closing brace at class level.
    $start = strpos($src, 'public function save()');
    if (false === $start) {
        continue;
    }
    $end = strpos($src, "\n    }", $start);
    $body = false === $end
        ? substr($src, $start)
        : substr($src, $start, $end - $start);

    if (false === strpos($body, 'parent::save()')) {
        continue;
    }
    $checked++;

    // Comments explain the guard, so they must not satisfy it.
    $code = preg_replace('#//[^\n]*#', '', $body);

    // Consumed = tested, assigned, or returned. Discarded = a statement
    // that is nothing but the call.
    if (preg_match('/(^|\n)\s*parent::save\(\)\s*;/', $code)) {
        $failures[] = "$name discards parent::save()'s return, so a failed "
            . 'database write is reported to the caller as a success and the '
            . 'association work below runs against a row that does not exist';
    }
}

if ($checked < 1) {
    fwrite(STDERR, "FAIL: found no save() override calling parent::save(); "
        . "has the pattern changed? This test would pass vacuously.\n");
    exit(1);
}

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checked):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok  $checked save() overrides propagate failure\n";
exit(0);
