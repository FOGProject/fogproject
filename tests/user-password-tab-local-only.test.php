<?php
/**
 * A directory-owned account must not be offered a password it cannot use.
 *
 * User::passwordValidate() refuses a local credential outright for any
 * account carrying uAuthSource -- that refusal is the whole break-glass
 * position and is pinned by tests/break-glass-auth-sources.test.php. The
 * consequence for the UI had not been drawn: the user edit page still
 * offered a Password tab for those accounts, so an administrator could
 * type a password, be told "User updated!", and have stored something
 * nothing will ever accept.
 *
 * That is worse than a missing feature. It reads as "I have given them a
 * local password", which is exactly the belief somebody would rely on
 * during a directory outage -- the one moment it is discovered to be
 * false, and the one moment there is no time to find out.
 *
 * Three properties, and they fail in three different ways:
 *
 *   1. The tab is offered only to an account that authenticates here.
 *      Ungating it restores the original lie in full.
 *   2. The POST refuses too. Without it the removal is cosmetic: the
 *      tab-update URL is guessable, and a page left open before the
 *      account was linked still holds a live form.
 *   3. The General tab says where the account signs in, when it is not
 *      here. Without this the tab is simply absent with nothing on the
 *      page explaining it, which is its own support ticket.
 *
 * Property 3 is not decoration and it is why 1 is safe to do at all: the
 * supported way to give such an account a local password is to clear its
 * auth source first, and an administrator cannot decide to do that if
 * nothing tells them there is one.
 *
 *   4. That clear is reachable from the page, and is a CLEAR ONLY. Until
 *      it was, recovering an account meant a REST call or a hand-written
 *      UPDATE -- a bad place to be standing when the reason you want it is
 *      that the directory is down. The direction matters far more than the
 *      control does: writing an auth source takes local password login
 *      away from an account, which is how an install locks itself out, and
 *      a general-details form must not be able to do that as a side
 *      effect.
 *
 * Source assertions -- rendering the page needs a database, a session and
 * a loaded user.
 *
 * Usage: php tests/user-password-tab-local-only.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
chdir($root);

$fails = [];
$pageFile = 'packages/web/lib/pages/usermanagement.page.php';
if (!is_readable($pageFile)) {
    echo "cannot read $pageFile -- run this from the repository\n";
    exit(1);
}

/**
 * Source with comments stripped and whitespace squashed.
 *
 * Comments first: the prose above each change names authsource, the
 * Password tab and passwordValidate(), so a test reading raw text would be
 * satisfied by its own documentation with the code deleted.
 *
 * @param string $file the file
 *
 * @return string
 */
function squashed($file)
{
    $out = '';
    foreach (token_get_all(file_get_contents($file)) as $t) {
        if (is_array($t)) {
            if (in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $out .= $t[1];
            continue;
        }
        $out .= $t;
    }
    return preg_replace('/\s+/', '', $out);
}

/**
 * Body of the first `if` whose condition contains a needle.
 *
 * Braces are counted on tokens rather than on the squashed string, so a
 * brace inside a string literal cannot close the block early. Containment
 * is the entire claim here, and a containment claim computed from a
 * substring search is not one.
 *
 * More than one guard can test the same condition -- the render and the
 * POST both ask whether this account has an auth source -- so a caller can
 * name something the body itself must contain. Without that the function
 * answers about whichever happens to come first in the file, and a
 * mutation that closes the real guard early and reopens an unconditional
 * one still lands inside the returned block.
 *
 * NOTE: whitespace TOKENS are dropped, but whitespace inside a string
 * literal is not. Needles containing a translated label keep their spaces.
 *
 * @param string $file      the file
 * @param string $condition text the condition must contain, squashed
 * @param string $contains  text the body must contain, or '' for the first
 *
 * @return string|null body, or null
 */
function guardBody($file, $condition, $contains = '')
{
    $pieces = [];
    foreach (token_get_all(file_get_contents($file)) as $c) {
        if (is_array($c)
            && in_array(
                $c[0],
                [T_COMMENT, T_DOC_COMMENT, T_WHITESPACE],
                true
            )
        ) {
            continue;
        }
        $pieces[] = is_array($c) ? [$c[0], $c[1]] : [null, $c];
    }
    $n = count($pieces);
    for ($i = 0; $i < $n; $i++) {
        if (T_IF !== $pieces[$i][0]
            || $i + 1 >= $n
            || '(' !== $pieces[$i + 1][1]
        ) {
            continue;
        }
        $depth = 0;
        $cond = '';
        for ($j = $i + 1; $j < $n; $j++) {
            $tok = $pieces[$j][1];
            if (null === $pieces[$j][0] && '(' === $tok) {
                $depth++;
            } elseif (null === $pieces[$j][0] && ')' === $tok) {
                if (0 === --$depth) {
                    break;
                }
            }
            $cond .= $tok;
        }
        if (false === strpos($cond . ')', $condition)) {
            continue;
        }
        $wantedBody = $contains;
        // A braceless guard cannot contain anything, so a single-statement
        // rewrite reads as "not found" rather than passing with an empty
        // body.
        if ($j + 1 >= $n || '{' !== $pieces[$j + 1][1]) {
            return null;
        }
        $depth = 0;
        $body = '';
        for ($k = $j + 1; $k < $n; $k++) {
            $type = $pieces[$k][0];
            $tok = $pieces[$k][1];
            if ((null === $type && '{' === $tok)
                || T_CURLY_OPEN === $type
                || T_DOLLAR_OPEN_CURLY_BRACES === $type
            ) {
                $depth++;
            } elseif (null === $type && '}' === $tok) {
                if (0 === --$depth) {
                    if ('' === $wantedBody
                        || false !== strpos($body, $wantedBody)
                    ) {
                        return $body;
                    }
                    // Wrong guard; keep looking from after it.
                    $body = null;
                    $i = $k;
                    break;
                }
            }
            if ($depth > 0 && $k > $j + 1) {
                $body .= $tok;
            }
        }
        if (null !== $body) {
            return null;
        }
    }
    return null;
}

/**
 * One method's body, cut at the start of the next method.
 *
 * @param string $s      squashed source
 * @param string $needle the declaration, squashed
 *
 * @return string body, or ''
 */
function methodBody($s, $needle)
{
    $at = strpos($s, $needle);
    if (false === $at) {
        return '';
    }
    $next = strpos($s, 'function', $at + strlen($needle));
    return false === $next ? substr($s, $at) : substr($s, $at, $next - $at);
}

$page = squashed($pageFile);

/*
 * 1. The tab is inside a guard on the account authenticating locally.
 *
 * Presence of the guard is not the property; the tab being INSIDE it is.
 * A guard that tests the right thing and then registers the tab after its
 * closing brace looks correct in a diff and changes nothing at all.
 */
$emptySource = "''===trim((string)\$this->obj->get('authsource'))";
$guard = guardBody($pageFile, $emptySource);
if (null === $guard) {
    if (false !== strpos($page, "'id'=>'user-changepw'")) {
        $fails[] = 'the Password tab is registered without a guard on the'
            . " account's authentication source; a directory-owned account"
            . ' is offered a password that passwordValidate() will never'
            . ' accept';
    } else {
        $fails[] = 'no guard on the account authentication source was found'
            . ' in ' . $pageFile;
    }
} elseif (false === strpos($guard, "'id'=>'user-changepw'")) {
    $fails[] = 'the Password tab is no longer inside the local-account'
        . ' guard; if it moved out, every account is offered it again';
}

/*
 * And exactly one registration. A second, unguarded push -- added later
 * for a different account shape -- puts the tab back while every
 * assertion above still passes.
 */
$registrations = substr_count($page, "'id'=>'user-changepw'");
if ($registrations > 1) {
    $fails[] = 'the Password tab is registered ' . $registrations
        . ' times; only the guarded one is verified here';
}

/*
 * 2. The POST refuses, and refuses BEFORE it writes.
 *
 * Order is the assertion. A check that runs after set('password', ...)
 * throws the same exception and has already changed the object, and
 * whether that reaches the database then depends on where the caller
 * catches -- which is not a thing to leave to chance for a credential.
 */
$post = methodBody($page, 'publicfunctionuserChangePWPost()');
if ('' === $post) {
    $fails[] = 'UserManagement::userChangePWPost() is missing';
} else {
    $checkAt = strpos($post, "\$this->obj->get('authsource')");
    $throwAt = strpos($post, 'thrownew');
    $writeAt = strpos($post, "set('password'");
    if (false === $checkAt || false === $throwAt) {
        $fails[] = 'userChangePWPost() no longer refuses a password write'
            . ' for an account with an authentication source; the hidden'
            . ' tab is then cosmetic, because the tab-update URL is'
            . ' guessable and a stale open page still holds the form';
    } elseif (false !== $writeAt && $throwAt > $writeAt) {
        $fails[] = 'userChangePWPost() writes the password before deciding'
            . ' whether it is allowed to';
    }
}

/*
 * 3. The General tab names the auth source, read-only.
 *
 * The explanation for the missing tab. Editable would be a different and
 * much larger decision -- clearing that column hands the account back to
 * local password login, which is an authentication decision and not a
 * text box.
 */
$general = methodBody($page, 'publicfunctionuserGeneral()');
if ('' === $general) {
    $fails[] = 'UserManagement::userGeneral() is missing';
} else {
    if (false === strpos($general, "get('authsource')")) {
        $fails[] = 'the General tab no longer shows the authentication'
            . ' source, so an account simply has no Password tab with'
            . ' nothing on the page to explain why';
    }
    if (false === strpos($general, "'authsource',_('SignsInWith')")) {
        $fails[] = 'the authentication source field lost its label';
    }
    /*
     * makeInput's 12th argument is $readonly. Pinning the field without
     * pinning this leaves it free to become an ordinary text box, and
     * typing into that one converts the account.
     */
    $fieldAt = strpos($general, "'authsource',\n");
    $inputAt = strpos($general, "self::makeInput('form-control','authsource'");
    if (false === $inputAt) {
        $fails[] = 'the authentication source is no longer rendered through'
            . ' makeInput as a form-control field';
    } else {
        $tail = substr($general, $inputAt, 260);
        if (false === strpos($tail, "-1,-1,'',true")) {
            $fails[] = 'the authentication source field is no longer'
                . ' read-only; clearing that column hands the account back'
                . ' to local password login, which is an authentication'
                . ' decision and not a text box';
        }
    }
}

/*
 * 4. The way back, and only the way back.
 *
 * Scoped to the region the guard actually covers -- from the guard's
 * opening brace to the $buttons assignment that follows it -- rather than
 * to the method as a whole, so a checkbox rendered unconditionally for
 * every account does not pass by being somewhere in the same function.
 */
$guarded = guardBody(
    $pageFile,
    "''!==\$authSource",
    "_('Signs In With')"
);
if (null === $guarded) {
    $fails[] = 'the authentication source is no longer rendered inside a'
        . ' guard on the account having one';
} elseif (false === strpos($guarded, "'returnlocal'")) {
    $fails[] = 'the General tab offers no way to return the account to'
        . ' local login inside the same guard; recovering one then means a'
        . ' REST call or a hand-written UPDATE, which is a bad place to be'
        . ' standing when the reason you want it is that the directory is'
        . ' down -- and a control rendered outside that guard offers it on'
        . ' accounts that have nothing to return';
}
/*
 * Exactly one. A second copy elsewhere on the page is one that is not
 * covered by the guard above.
 */
$controls = substr_count($page, "_('ReturnToLocalLogin')");
if ($controls > 1) {
    $fails[] = 'the return-to-local control is rendered ' . $controls
        . ' times; only the guarded one is verified here';
}

/*
 * And the POST half: clears, and ONLY clears.
 *
 * The assertion that matters is the second one. A form that can also SET
 * an auth source can take local password login away from the last
 * administrator who has one -- User::save() has a guard for exactly that,
 * but a general-details form has no business being the thing that trips
 * it, and the failure mode if the guard were ever weakened is an install
 * nobody can sign in to.
 */
$generalPost = methodBody($page, 'publicfunctionuserGeneralPost()');
if ('' === $generalPost) {
    $fails[] = 'UserManagement::userGeneralPost() is missing';
} else {
    if (false === strpos($generalPost, "isset(\$_POST['returnlocal'])")) {
        $fails[] = 'userGeneralPost() no longer acts on the return-to-local'
            . ' request, so ticking the box does nothing';
    }
    if (false === strpos($generalPost, "set('authsource','')")) {
        $fails[] = 'userGeneralPost() no longer clears the authentication'
            . ' source';
    }
    /*
     * Every write to the column in this method must be the empty string.
     * Anything else is the page learning to hand an account to a
     * directory, which is the direction that locks people out.
     */
    if (preg_match_all("#set\('authsource',([^)]*)\)#", $generalPost, $m)) {
        foreach ($m[1] as $written) {
            if ("''" !== $written) {
                $fails[] = 'userGeneralPost() writes ' . $written . ' to the'
                    . ' authentication source; this form may only CLEAR it,'
                    . ' because setting one takes local password login away'
                    . ' from the account';
            }
        }
    }
    /*
     * Guarded on there being something to clear, so an ordinary Update on
     * an ordinary local account cannot mark the field dirty and drag it
     * through User::save()'s break-glass check for no reason.
     */
    if (false === strpos($generalPost, "\$this->obj->get('authsource')")) {
        $fails[] = 'userGeneralPost() clears the authentication source'
            . ' without first checking there is one';
    }
}

if (count($fails) > 0) {
    echo 'FAIL: ' . count($fails) . " problem(s):\n";
    foreach ($fails as $f) {
        echo '  - ' . $f . "\n";
    }
    exit(1);
}
echo "ok: only a locally-authenticating account is offered a password,"
    . " and there is a way back\n";
exit(0);
