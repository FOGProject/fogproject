<?php
/**
 * The flash-message queue survives a session written by older FOG.
 *
 * FOG 1.5, and 1.6 builds before July 2026, stored
 * $_SESSION['FOG_MESSAGES'] as a string. Current code stores a list of
 * ['title', 'body', 'type'] arrays and appends with `[]`. `[]` on a string
 * is a fatal error, so a browser that still carried an old session got a
 * 500 on every request once User::isLoggedIn() queued the inactivity
 * notice (forums.fogproject.org topic 18308).
 *
 * BEHAVIORAL: the two methods are lifted out of FOGBase.php by the
 * tokenizer and run against a real session, so the test exercises the
 * shipped code rather than a description of it.
 *
 * Usage: php tests/flash-message-legacy-string.test.php
 * Exit status 0 = pass, 1 = fail.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

$file = dirname(__DIR__) . '/packages/web/src/Base/FOGBase.php';
$tokens = token_get_all((string)file_get_contents($file));

/**
 * Return the source of one method, from `public static function` to its
 * closing brace.
 *
 * @param array  $tokens the file's tokens
 * @param string $name   the method name
 *
 * @return string
 */
function flashMethodSource(array $tokens, $name)
{
    $n = count($tokens);
    for ($i = 0; $i < $n; $i++) {
        if (!is_array($tokens[$i]) || T_FUNCTION !== $tokens[$i][0]) {
            continue;
        }
        $j = $i + 1;
        while (is_array($tokens[$j]) && T_WHITESPACE === $tokens[$j][0]) {
            $j++;
        }
        if (!is_array($tokens[$j]) || $tokens[$j][1] !== $name) {
            continue;
        }
        $out = 'public static function';
        $depth = 0;
        for ($k = $i + 1; $k < $n; $k++) {
            $t = $tokens[$k];
            $text = is_array($t) ? $t[1] : $t;
            $out .= $text;
            if ('{' === $text || (is_array($t) && T_CURLY_OPEN === $t[0])) {
                $depth++;
            } elseif ('}' === $text) {
                $depth--;
                if (0 === $depth) {
                    return $out;
                }
            }
        }
    }
    return '';
}

$set = flashMethodSource($tokens, 'setMessage');
$get = flashMethodSource($tokens, 'getMessage');
if ('' === $set || '' === $get) {
    echo "FAIL  could not find setMessage()/getMessage() in FOGBase.php\n";
    exit(1);
}
eval('class FlashUnderTest {' . $set . "\n" . $get . '}');

session_save_path(sys_get_temp_dir());
@session_start();

$pass = 0;
$fail = 0;
$check = static function ($what, $ok) use (&$pass, &$fail) {
    if ($ok) {
        $pass++;
        echo "  ok    $what\n";
        return;
    }
    $fail++;
    echo "  FAIL  $what\n";
};

$msg = ['title' => 'T', 'body' => 'B', 'type' => 'info'];

// The reported case: a legacy string in the queue.
$_SESSION = ['FOG_MESSAGES' => 'Session timeout'];
try {
    FlashUnderTest::setMessage('B', 'T', 'info');
    $check('setMessage() does not fatal on a legacy string', true);
} catch (\Throwable $e) {
    $check('setMessage() does not fatal on a legacy string: ' . $e->getMessage(), false);
}
$check(
    'the legacy string is replaced, the new message kept',
    [$msg] === FlashUnderTest::getMessage()
);

// getMessage() alone, on a legacy string: no empty toast.
$_SESSION = ['FOG_MESSAGES' => 'Session timeout'];
$check('getMessage() drops a legacy string', [] === FlashUnderTest::getMessage());
$check('getMessage() clears the queue', !isset($_SESSION['FOG_MESSAGES']));

// Normal shapes are unchanged.
$_SESSION = [];
FlashUnderTest::setMessage('B', 'T', 'info');
FlashUnderTest::setMessage('B', 'T', 'info');
$check('two messages queue in order', [$msg, $msg] === FlashUnderTest::getMessage());

$_SESSION = ['FOG_MESSAGES' => [$msg]];
FlashUnderTest::setMessage('B', 'T', 'info');
$check('an existing queue is appended to', [$msg, $msg] === FlashUnderTest::getMessage());

$_SESSION = ['FOG_MESSAGES' => []];
$check('an empty queue returns nothing', [] === FlashUnderTest::getMessage());

session_destroy();
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
