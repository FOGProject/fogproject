<?php
/**
 * Whitespace collapsing must stop at <script>, <pre> and <textarea>.
 *
 * Initiator::sanitizeOutput() shrinks the rendered page by replacing every
 * whitespace run with its last character. A newline followed by indentation
 * therefore becomes one space, and the newline is gone. That is harmless
 * between tags and destructive inside three elements:
 *
 *  - <script>: with no line endings left, the first `//` comment swallows
 *    everything after it. This is not hypothetical -- the pre-paint dark-mode
 *    script in management/other/index.php was dead for exactly this reason,
 *    so data-bs-theme was only set on DOMContentLoaded and anyone following
 *    their OS preference saw the page painted light first.
 *  - <textarea>: the line breaks are the user's own data.
 *  - <pre>: the element exists to preserve them.
 *
 * The failure is silent in all three cases: the page still renders, the
 * script still parses, and nothing is logged.
 *
 * Usage: php tests/output-whitespace-significant-blocks.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$web = $root . '/packages/web';
$initPath = $web . '/commons/init.php';
$shellPath = $web . '/management/other/index.php';

$fails = [];

/**
 * Loads sanitizeOutput() as a standalone function.
 *
 * init.php cannot be included -- it boots the application -- so lift the one
 * method out of it and evaluate that. This deliberately reads the real source
 * rather than a copy: a test with its own copy of the implementation passes
 * whatever the shipped one does.
 *
 * @param string $src The contents of commons/init.php.
 *
 * @return callable|null
 */
function loadSanitizer($src)
{
    if (!preg_match(
        '/public static function sanitizeOutput\(string \$buffer\): string.*?\n    \}/s',
        $src,
        $m
    )) {
        return null;
    }
    $fn = str_replace(
        'public static function sanitizeOutput(string $buffer): string',
        'return function (string $buffer): string',
        $m[0]
    ) . ';';

    return eval($fn);
}

$src = file_get_contents($initPath);
$sanitize = loadSanitizer($src);
if (!$sanitize) {
    $fails[] = 'could not find sanitizeOutput() in commons/init.php';
} else {
    // Indentation matters: a bare "\n" run survives the old expression by
    // accident (the run's last character IS the newline), so a test written
    // without leading spaces passes against the very bug it is meant to
    // catch. Every fixture below is indented for that reason.
    $cases = [
        'a script keeps its line endings' => [
            "    <script>\n    // comment\n    var x = 1;\n    </script>",
            "\n",
        ],
        'a textarea keeps the user\'s line breaks' => [
            "<textarea>line one\n    line two</textarea>",
            "\n",
        ],
        'a pre block keeps its formatting' => [
            "<pre>a\n  b\n    c</pre>",
            "\n",
        ],
    ];
    foreach ($cases as $name => $case) {
        list($input, $needle) = $case;
        if (false === strpos($sanitize($input), $needle)) {
            $fails[] = $name . ': line endings were collapsed';
        }
    }

    // The saving itself must still happen, or this "fix" is just a revert of
    // the whitespace collapsing.
    $plain = $sanitize("<div>\n    <span>hi</span>\n</div>");
    if (false !== strpos($plain, "\n")) {
        $fails[] = 'ordinary markup is no longer collapsed';
    }

    // Markup BETWEEN two protected blocks must still collapse: an
    // implementation that bails out on the first match would pass every
    // check above while quietly giving up on the rest of the page.
    $mixed = $sanitize(
        "<script>\n  //a\n  x=1;\n</script>\n  <div>\n   y\n  </div>\n"
        . "  <script>\n  //b\n  z=2;\n</script>"
    );
    if (false === strpos($mixed, '<div> y </div>')) {
        $fails[] = 'markup between two scripts was not collapsed';
    }
    if (false === strpos($mixed, "//a\n") || false === strpos($mixed, "//b\n")) {
        $fails[] = 'a second script on the page lost its line endings';
    }
}

// The consumer this was found through. The pre-paint theme script has to be
// the first thing in <head> -- before any stylesheet, and not deferred --
// because anything later runs after the browser has already painted.
$shell = file_get_contents($shellPath);
if (!preg_match('/<head>\s*(.*?)<meta charset/s', $shell, $m)) {
    $fails[] = 'could not find the <head> opening in the page shell';
} else {
    $head = $m[1];
    if (false === strpos($head, 'prefers-color-scheme')) {
        $fails[] = 'the pre-paint theme script is not the first thing in <head>';
    }
    if (false !== strpos($head, 'defer') || false !== strpos($head, 'async')) {
        $fails[] = 'the pre-paint theme script must not be deferred';
    }
    if (false !== strpos($head, '<link')) {
        $fails[] = 'a stylesheet is emitted before the pre-paint theme script';
    }
}

// data-bs-theme belongs on <html>: by the time <body> exists the browser may
// already have painted the background.
// Matched on the source LINE, not on the rendered tag: the attribute is
// emitted by a PHP short echo whose own closing tag defeats a `[^>]*` scan.
$htmlTag = '';
foreach (preg_split('/\R/', $shell) as $line) {
    if (0 === strpos(ltrim($line), '<html')) {
        $htmlTag = $line;
        break;
    }
}
if (false === strpos($htmlTag, 'data-bs-theme')) {
    $fails[] = 'data-bs-theme is not stamped on <html>';
}

foreach ($fails as $fail) {
    fwrite(STDERR, 'FAIL: ' . $fail . PHP_EOL);
}
if ($fails) {
    fwrite(STDERR, count($fails) . ' failure(s)' . PHP_EOL);
    exit(1);
}
echo 'ok: whitespace-significant blocks survive output sanitizing' . PHP_EOL;
exit(0);
