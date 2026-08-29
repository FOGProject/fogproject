<?php
/**
 * Gate: FOG's own source carries US spellings.
 *
 * Tom asked for US spellings throughout. The tree was swept once (GH-1457 and
 * FOGProject/fos#168); this is what stops it drifting back, one comment at a
 * time, the way it drifted in.
 *
 * WHY A WHOLE-TREE SCAN RATHER THAN A DIFF
 *
 * The obvious shape is "check only the lines this pull request adds", and it
 * was the first thing tried. It needs a base ref, and the job that runs this
 * lives in FOGProject/fog-workflows where this file cannot see or set the
 * checkout depth. A shallow clone has no merge-base, so the check would find
 * nothing to look at and pass -- for a reason that has nothing to do with
 * spelling, on every pull request, silently and forever. A gate that can only
 * report success is worse than no gate, because it also reports "verified".
 *
 * Scanning the whole tree needs no ref, cannot skip, and is only possible
 * because the sweep already made the tree clean. It follows the same principle
 * the phpstan job is built on (.github/workflows/tests.yml): a gate that can
 * only be satisfied, never one that grows.
 *
 * SCOPE is FOG's own source. Vendored JavaScript, the BS3 stylesheets and the
 * gettext catalogs are excluded -- they are other people's spellings or are
 * generated, and neither is ours to correct.
 *
 * ALLOWED is not a convenience list. Every entry is a string a MACHINE reads,
 * where the UK spelling is the correct one and rewriting it breaks something.
 * Adding to it should be rare and each entry says why.
 *
 * Usage: php tests/us-spelling.test.php
 * Exit 0 = clean, 1 = at least one UK spelling in scope.
 */

$root = dirname(__DIR__);

/*
 * FOG's own source. `docs` and `CLAUDE.md` are in because they are read by
 * people and by agents working here; `packages/web/management` is out apart
 * from js/fog, because everything else under it is vendored.
 */
$scope = [
    'packages/web/src',
    'packages/web/lib',
    'packages/web/commons',
    'packages/web/service',
    'packages/web/api',
    'packages/web/management/js/fog',
    'lib',
    'bin',
    'tests',
    'docs',
    'CLAUDE.md',
];

/*
 * Each of these is read by a machine somewhere, so the UK spelling is load
 * bearing. Checked as a plain substring and blanked out of the line before the
 * words below are looked for.
 */
$allowed = [
    // iPXE's OWN command names. There is no `color` command and no `cpair`
    // alternative; rewriting either breaks every boot menu's appearance.
    'colour --',
    'cpair --',
    // The same help text names the US word and then gives iPXE's spelling in
    // parentheses, which is the only reason the line exists.
    '(colour)',
    'e.g. colour, cpair',
    // globalSettings ROW NAMES, inserted by commons/schema.php. Renaming one
    // orphans the setting on every install that already has it.
    'FOG_IPXE_MAIN_COLOURS',
    'FOG_IPXE_VALID_HOST_COLOURS',
    'FOG_IPXE_INVALID_HOST_COLOURS',
    // A DATABASE VALUE, not source text. Changing it changes fresh installs
    // only, so an upgraded server would read "Cancelled" and a new one
    // "Canceled" -- and the label is translated by its own text, so the new
    // spelling would silently lose every locale's string. Converging them
    // needs a schema step, which is a migration and not a spelling fix.
    "(5,'Cancelled'",
    // Sender and receiver for the task list's Recent pane, in one file.
    // Rewriting either alone empties the pane.
    'value="cancelled"',
    'recent-state-cancelled',
    "case 'cancelled':",
    // Public method, hook name, and the key the hook hands a plugin in its
    // arguments array -- all three are the plugin ABI. A plugin reading
    // $arguments['cancelledState'] breaks the moment that key is respelled.
    'getCancelledState',
    'CANCELLED_STATE',
    'cancelledState',
    // Shell helpers in the localboot tests, named before the sweep.
    'neighboursIntact',
    'seedNeighbours',
    // Referenced by name from other files here and from FOGProject/fos.
    'secureboot-enrolment-diagnostics',
    '0008-secure-boot-enrolment-task-type',
    '0009-secure-boot-enrolment-paths',
    '0025-one-boolean-encoding-normalised-on-load',
    // The analyser's own subcommand.
    'phpstan analyse',
];

/*
 * An explicit list, not a blanket -ise -> -ize rule: advertise, exercise,
 * surprise, otherwise, comprise, precise and a dozen more are -ise in both
 * dialects, and a pattern would corrupt every one of them.
 *
 * Matched case-insensitively on a word boundary, so Colour and COLOURS are
 * caught too. `enrolled` and `enrolling` are absent on purpose -- both
 * dialects double the l there, so they are already correct.
 */
$uk = [
    'enrolment', 'enrolments', 'enrol', 'enrols',
    'recognise', 'recognised', 'recognises', 'recognising',
    'recognisable', 'recognisably', 'unrecognised',
    'normalise', 'normalised', 'normalises', 'normalising',
    'normalisation', 'normaliser', 'renormalised', 'renormalising',
    'canonicalise', 'canonicalised', 'canonicalisation',
    'behaviour', 'behaviours', 'behavioural', 'behaviourally',
    'colour', 'colours', 'coloured', 'colouring',
    'cancelled', 'cancelling',
    'labelled', 'labelling', 'mislabelled', 'mislabelling',
    'relabelled', 'relabelling', 'unlabelled',
    'signalling', 'modelled', 'travelled',
    'catalogue', 'licence', 'centre', 'centres',
    'neighbour', 'neighbours', 'neighbouring',
    'initialise', 'initialised', 'initialises', 'initialisation', 'initialiser',
    'authorise', 'authorised', 'authorises', 'authorisation',
    'serialise', 'serialised', 'serialises', 'serialising',
    'serialiser', 'deserialiser',
    'organise', 'organised', 'organising', 'organisation', 'reorganisation',
    'minimise', 'minimised', 'minimisation',
    'maximise', 'maximised', 'optimise', 'optimised',
    'utilise', 'utilised', 'utilising',
    'prioritise', 'prioritised',
    'summarise', 'summarised', 'summarises',
    'specialise', 'specialised',
    'favour', 'favours', 'favourable',
    'defence', 'offence', 'fulfil', 'whilst',
    'grey', 'greyed',
    // "the analyses" is a legitimate US plural noun. It has never appeared
    // here, and the verb is far more common, so the verb wins; quote it or
    // reword if that ever changes.
    'analyse', 'analysed', 'analyses', 'analyser',
    'afterwards', 'towards', 'amongst',
    'judgement', 'judgements', 'ageing',
    'artefact', 'artefacts', 'programme', 'programmes',
];

/*
 * Two patterns, because one right-hand boundary cannot serve both shapes.
 *
 * WORD/camelCase -- lower-case or Title-case. Left edge is "not preceded by a
 * letter, OR sitting on a camelCase hump", so getCancelledState matches at the
 * C. Right edge is "not followed by a lower-case letter", which keeps `enrol`
 * from firing inside `enrolled` and `enrollment`.
 *
 * ALL CAPS -- for shell constants and menu names. Here the right edge has to
 * reject an upper-case letter too, or `ENROL` matches inside `ENROLL_SECUREBOOT`
 * and the check fails on a correctly spelled task type. `ENROLMENT_MODE` still
 * matches, because `_` is not a letter.
 *
 * Both are case-SENSITIVE. A /i flag on the whole pattern also folds the [a-z]
 * and [A-Z] in the boundary assertions, which collapses the camelCase hump into
 * "preceded by a letter, followed by a letter" -- i.e. no boundary at all. That
 * mistake made the check fire on `labelling` inside `Relabelling`, which reads
 * like a find and is really the guard rail dissolving.
 *
 * What neither can see: a UK word glued inside an all-lower-case identifier
 * (`withenrol`). No boundary separates that from a longer word, so it is out of
 * reach by construction rather than by oversight.
 */
$cased = [];
$upper = [];
foreach ($uk as $word) {
    $cased[] = ucfirst($word);
    $cased[] = $word;
    $upper[] = strtoupper($word);
}
$patterns = [
    '/(?:(?<![A-Za-z])|(?<=[a-z])(?=[A-Z]))(' . implode('|', $cased) . ')(?![a-z])/',
    '/(?<![A-Za-z])(' . implode('|', $upper) . ')(?![A-Za-z])/',
];

/**
 * Every file in scope, minus anything binary.
 *
 * `git ls-files --cached --others --exclude-standard` rather than a filesystem
 * walk or plain `git ls-files`, because each of those is wrong in one
 * direction. A walk drags in `packages/web/lib/plugins/`, which is gitignored,
 * root-owned and populated by the installer rather than by this repository --
 * not our source and not writable from here. Plain `git ls-files` is blind to
 * a file that has not been added yet, which is how a new class passed
 * psr4-layout locally and failed in CI (see bin/psr4-scan.php). `--others
 * --exclude-standard` is exactly the pair: tracked files, plus new ones a
 * developer has written but not staged, minus everything git is told to
 * ignore.
 *
 * @param string $root  repository root
 * @param array  $scope paths relative to it
 *
 * @return array
 */
function collectFiles($root, array $scope)
{
    $cmd = 'git -C ' . escapeshellarg($root)
        . ' ls-files --cached --others --exclude-standard -z --';
    foreach ($scope as $rel) {
        $cmd .= ' ' . escapeshellarg($rel);
    }
    $out = [];
    $status = 0;
    exec($cmd . ' 2>/dev/null', $out, $status);
    if (0 !== $status) {
        // Loud, not skipped. A gate that cannot see the files it grades must
        // say so -- silently passing is the failure this whole check exists
        // to avoid.
        fwrite(STDERR, "FAIL: could not enumerate files ($cmd).\n");
        exit(1);
    }
    $files = [];
    $perScope = array_fill_keys($scope, 0);
    foreach (explode("\0", implode("\n", $out)) as $rel) {
        if ('' === $rel) {
            continue;
        }
        $path = $root . '/' . $rel;
        if (!is_file($path)) {
            continue;
        }
        $files[] = $path;
        foreach ($scope as $entry) {
            if ($rel === $entry || 0 === strpos($rel, $entry . '/')) {
                ++$perScope[$entry];
            }
        }
    }

    /*
     * A scope entry that contributed nothing is the silent-pass this check
     * exists to prevent, and "git returned 0 files" is NOT covered by the exit
     * status above -- git exits 0 quite happily on a checkout where the path
     * has moved, or where an ignore rule now swallows it. The result would be
     * "0 file(s) scanned, no UK spellings in scope": green, and meaningless.
     *
     * Per path rather than a total, because a total still passes when one
     * directory of the seven silently drops out.
     */
    $empty = array_keys($perScope, 0, true);
    if (count($empty)) {
        fwrite(
            STDERR,
            "FAIL: these scope paths matched no files -- moved, renamed or\n"
            . "newly ignored? Fix \$scope; do not delete the entry to go green.\n  "
            . implode("\n  ", $empty) . "\n"
        );
        exit(1);
    }

    sort($files);
    return $files;
}

$problems = [];
$scanned = 0;

foreach (collectFiles($root, $scope) as $path) {
    // This file names every UK spelling there is; it cannot scan itself.
    if ($path === __FILE__) {
        continue;
    }
    $body = @file_get_contents($path);
    if (false === $body || '' === $body) {
        continue;
    }
    // Anything with a NUL in the first 4KB is binary -- memtest.bin and the
    // iPXE payloads live under packages/web/service.
    if (false !== strpos(substr($body, 0, 4096), "\0")) {
        continue;
    }
    ++$scanned;
    $rel = substr($path, strlen($root) + 1);
    foreach (explode("\n", $body) as $n => $line) {
        $stripped = str_replace($allowed, '', $line);
        $found = [];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $stripped, $m)) {
                $found = array_merge($found, $m[1]);
            }
        }
        if (!count($found)) {
            continue;
        }
        $problems[] = sprintf(
            '%s:%d  %s',
            $rel,
            $n + 1,
            implode(', ', array_unique($found))
        );
    }
}

if (count($problems)) {
    fwrite(
        STDERR,
        "FAIL: " . count($problems) . " line(s) carry a UK spelling.\n"
        . "Use the US form. If a machine reads the string and the UK\n"
        . "spelling is load bearing, add it to \$allowed above with the\n"
        . "reason -- do not add the word to \$uk.\n\n"
        . implode("\n", array_slice($problems, 0, 50)) . "\n"
    );
    if (count($problems) > 50) {
        fwrite(STDERR, '... and ' . (count($problems) - 50) . " more.\n");
    }
    exit(1);
}

printf("us-spelling: %d file(s) scanned, no UK spellings in scope\n", $scanned);
