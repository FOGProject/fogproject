#!/bin/bash
#
# Guards GHSA-qp3r-8mwm-vg6h and the dead-update-source bug class.
#
#   tests/fogupdater-update-source.test.sh
#
# utils/FOGUpdater/fogupdater.sh downloads a tarball and runs its installer
# unattended as root. Whatever answers those requests becomes the FOG server,
# so the transport is not a detail of the download -- it IS the authentication.
# The advisory found three things wrong with it at once:
#
#   1. Both fetches passed --no-check-certificate. Fixed separately, in
#      aa3d699b1; tests/installer-tls-verification.test.sh owns that assertion
#      and this file does not duplicate it.
#   2. The default stable mirrors were four plain-HTTP SourceForge URLs, so on
#      the default path there was no TLS to verify in the first place. An
#      on-path attacker substituted the tarball and got root.
#   3. Nothing about the payload was checked before it was extracted and run.
#
# Removing --no-check-certificate does not close 2 on its own. "https in the
# default string" and "the bytes arrived over TLS" are different statements,
# and two things separate them: $updatemirrors is read from .fogsettings, so an
# admin (or anything that can write that file) can put the http:// mirror back;
# and github.com REDIRECTS the archive endpoint to codeload.github.com, so the
# scheme that matters is the one at the end of the chain, not the one typed.
# curl's --proto '=https' --proto-redir '=https' are what make those one
# statement, which is why they are pinned here per call site rather than once.
#
# The SourceForge half is not only a security fix. SourceForge has carried no
# FOG release since fog_1.4.4, so the stable path had been fetching a 404 page
# for years -- and without --fail, curl/wget write that page to the destination
# and report success, handing it to `tar`. That is the same failure shape as
# fetch-plugins.sh printing its success line after every operation failed
# (#1337): the exit status described the transfer, not the outcome.
#
# The channel assertions guard a different destructive default. FOG_CHANNEL on
# a 1.6 server reads "Beta"; the old script had only stable and dev, resolved
# stable to a 1.5.x version, reported "you are not running the latest", and
# installed 1.5 over a 1.6 server. A channel with no branch this can derive
# must therefore stop, not fall through to stable.
#
# What this checks and what it does not: it asserts on the SOURCE for the shape
# of the calls and for the order of the payload check against the install, and
# it EXECUTES the branch-selection block and requireHttps/fetch for real. It
# does not install FOG, need root, or need the internet -- the one live curl
# goes to 192.0.2.1 (TEST-NET-1, RFC 5737), which must never be routed, and it
# is expected to fail before any connection is attempted because the scheme is
# rejected locally.
#
# Exit status 0 = pass, 1 = fail.

cd "$(dirname "$0")/.." || exit 1
UPD="utils/FOGUpdater/fogupdater.sh"
[[ -r $UPD ]] || { echo "cannot read $UPD -- run this from the repository"; exit 1; }

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }

# Comments are stripped before every source assertion. This file's own prose
# names http:// and sourceforge repeatedly, and so does the reasoning block at
# the top of the script -- a gate its own documentation satisfies is not a gate.
joined() {
    sed -e :a -e '/\\$/N; s/\\\n//; ta' "$1" | grep -vE '^[[:space:]]*#'
}

# body <function-name> <file> -- the text of one shell function, comments kept
# (callers strip when they need to). Every function in this file opens
# "name() {" and closes on a bare brace in column one.
body() {
    awk -v fn="$1" '
        $0 ~ "^" fn "\\(\\) \\{" { inside = 1 }
        inside { print }
        inside && /^}/ { exit }
    ' "$2"
}

echo "1. the update source is https, and stays https"

if joined "$UPD" | grep -qiE 'sourceforge|freeghost'; then
    bad "$UPD still names a SourceForge mirror -- no release has been published there since fog_1.4.4"
else
    ok "no SourceForge mirror remains"
fi

# Any http:// literal at all, not just in the mirror default. The four mirrors
# were one string; the next one will not be.
if joined "$UPD" | grep -qE 'http://'; then
    bad "$UPD contains a cleartext http:// URL"
else
    ok "no cleartext URL in the source"
fi

# Per call site, not once for the file. A second fetch helper added later
# without the flags is exactly how --no-check-certificate spread in the first
# place: by copying a neighboring call that had it.
curls=0
unpinned=0
while IFS= read -r line; do
    [[ -n $line ]] || continue
    curls=$((curls + 1))
    if ! printf '%s\n' "$line" | grep -q -- "--proto '=https'" \
        || ! printf '%s\n' "$line" | grep -q -- "--proto-redir '=https'"; then
        unpinned=$((unpinned + 1))
        printf '        unpinned: %s\n' "$(printf '%s' "$line" | sed 's/^ *//;s/  */ /g' | cut -c1-96)"
    fi
done < <(joined "$UPD" | grep -E '(^|[^-[:alnum:]_])curl[[:space:]]')

if [[ $curls -eq 0 ]]; then
    bad "no curl invocation found in $UPD -- this gate has stopped checking anything"
elif [[ $unpinned -eq 0 ]]; then
    ok "all $curls curl invocation(s) pin the scheme through redirects"
else
    bad "$unpinned curl invocation(s) do not pin --proto/--proto-redir to https"
fi

if joined "$UPD" | grep -E '(^|[^-[:alnum:]_])curl[[:space:]]' | grep -q -- '--fail'; then
    ok "the download fails on an HTTP error instead of saving the error page"
else
    bad "$UPD does not pass --fail -- a 404 body lands in the tarball and reads as a successful download"
fi

echo
echo "2. requireHttps refuses cleartext at runtime, not just by default"

if grep -q '^requireHttps() {' "$UPD"; then
    ok "requireHttps() is defined"
else
    bad "requireHttps() is gone -- an http:// \$updatemirrors from .fogsettings has nothing to stop it"
fi

# Both fetch call sites must be preceded by the check. Pinned by counting: one
# for the version lookup, one inside the mirror loop.
guards=$(joined "$UPD" | grep -cE '^[[:space:]]*requireHttps[[:space:]]+"')
if [[ $guards -ge 2 ]]; then
    ok "both fetch call sites are guarded ($guards requireHttps calls)"
else
    bad "only $guards requireHttps call(s) -- the version lookup and the download each need one"
fi

# Behavioral. handleError is stubbed to report rather than exit, so a
# requireHttps that silently returns is distinguishable from one that objects.
(
    handleError() { echo "REFUSED"; return 0; }
    eval "$(body requireHttps "$UPD")"
    good=$(requireHttps "https://github.com/FOGProject/fogproject" 2>&1)
    bad_=$(requireHttps "http://internap.dl.sourceforge.net/sourceforge/freeghost/" 2>&1)
    [[ -z $good && $bad_ == *REFUSED* ]] && exit 0
    exit 1
)
if [[ $? -eq 0 ]]; then
    ok "requireHttps passes https and refuses http"
else
    bad "requireHttps does not discriminate on scheme"
fi

# Behavioral, and the one that proves the flags are doing the work rather than
# merely being present: curl itself must refuse the scheme.
#
# The exit STATUS is what is asserted, not merely that it failed. 192.0.2.1 is
# TEST-NET-1 and unroutable, so a fetch() with --proto removed also fails --
# on a connect timeout -- and "it failed" would pass for the wrong reason,
# fifteen seconds later. curl exit 1 is CURLE_UNSUPPORTED_PROTOCOL: the URL was
# rejected locally, before any socket. 7/28 would be the connect failing.
(
    eval "$(body fetch "$UPD")"
    fetch "http://192.0.2.1/fog.tar.gz" /dev/null >/dev/null 2>&1
    [[ $? -eq 1 ]] && exit 0
    exit 1
)
if [[ $? -eq 0 ]]; then
    ok "fetch() rejects an http:// URL on the scheme, before connecting"
else
    bad "fetch() did not refuse a cleartext URL outright"
fi

echo
tmp=$(mktemp -d) || { echo "cannot create a temp directory"; exit 1; }
trap 'rm -rf "$tmp"' EXIT
tmp_args="$tmp/git-args"

echo "3. the tracked branch follows the installed channel"

# The selection block, executed for real against each channel. Stubs make the
# two exits observable: handleError prints and returns, so a fall-through to
# stable is visible as a branch value where a refusal was required.
#
# Beta asks the remote, so git is stubbed from the real `git ls-remote --heads`
# output format, "<sha><TAB>refs/heads/<name>". $STUB_HEADS holds the names the
# remote advertises; $STUB_UNREACHABLE makes ls-remote fail as it does with no
# network (exit 128). The stub records its arguments in $STUB_ARGS_FILE.
select_branch() (
    channel="$1"; trunk="$2"; updatebranch="$3"; branch=""
    handleError() { echo "REFUSED $2" >&2; return 0; }
    git() {
        printf '%s\n' "$*" >> "$STUB_ARGS_FILE"
        [[ -n $STUB_UNREACHABLE ]] && return 128
        local n i=0
        for n in $STUB_HEADS; do
            i=$((i + 1))
            printf '%040d\trefs/heads/%s\n' "$i" "$n"
        done
    }
    eval "$(body newestWorkingBranch "$UPD")"
    eval "$(joined "$UPD" | awk '
        /^if \[\[ -n \$updatebranch \]\]; then/ { inside = 1 }
        inside { print }
        inside && /^fi$/ { exit }
    ')"
    echo "$branch"
)

# The stderr line select_branch's handleError stub prints, for the stop cases.
check_exit() {
    local got
    got=$(select_branch "$1" "$2" "$3" 2>&1 >/dev/null)
    if [[ $got == "$4" ]]; then
        ok "channel='$1' -> $4"
    else
        bad "channel='$1' -> '$got', expected '$4'"
    fi
}

check_branch() {
    local got
    got=$(select_branch "$1" "$2" "$3" 2>/dev/null)
    if [[ $got == "$4" ]]; then
        ok "channel='$1' trunk='$2' updatebranch='$3' -> ${4:-<refused>}"
    else
        bad "channel='$1' trunk='$2' updatebranch='$3' -> '$got', expected '${4:-<refused>}'"
    fi
}

check_branch "Patches" ""  ""              "stable"
check_branch "Patches" "1" ""              "dev-branch"
export STUB_ARGS_FILE="$tmp_args"
# Beta follows the newest working-* branch, version-sorted, anchored at
# refs/heads/ (feat/working-x is not a beta line). working-1.6.1 < working-1.7.
STUB_HEADS="stable dev-branch working-1.6 working-1.6.1 working-1.7 feat/working-x zz/working-9" \
    check_branch "Beta" "" "" "working-1.7"
# The one that used to install 1.5 over a 1.6 server: $trunk must not drag a
# beta install onto the 1.5 patch line either.
STUB_HEADS="working-1.6 working-1.7" check_branch "Beta" "1" "" "working-1.7"
# Version sort, not lexical: 1.10 beats 1.9.
STUB_HEADS="working-1.9 working-1.10" check_branch "Beta" "" "" "working-1.10"
# No working-* branch: stop, do not fall through to stable or dev-branch.
STUB_HEADS="stable dev-branch feat/working-x" check_branch "Beta" "" "" ""
STUB_HEADS="stable dev-branch feat/working-x" check_exit "Beta" "" "" "REFUSED 10"
# Remote unreachable: a distinct stop.
STUB_UNREACHABLE=1 check_branch "Beta" "" "" ""
STUB_UNREACHABLE=1 check_exit "Beta" "" "" "REFUSED 11"
# updatebranch still wins, and never asks the remote.
: > "$STUB_ARGS_FILE"
STUB_HEADS="working-1.7" check_branch "Beta" "" "my-branch" "my-branch"
if [[ ! -s $STUB_ARGS_FILE ]]; then
    ok "updatebranch set: the remote is not asked"
else
    bad "updatebranch set but git was still called: $(cat "$STUB_ARGS_FILE")"
fi
# The lookup is https-only, against the same host the download uses, anchored.
: > "$STUB_ARGS_FILE"
STUB_HEADS="working-1.7" select_branch "Beta" "" "" >/dev/null 2>&1
if grep -q 'ls-remote --heads https://github.com/FOGProject/fogproject.git refs/heads/working-\*' "$STUB_ARGS_FILE"; then
    ok "ls-remote asks the https GitHub remote for refs/heads/working-*"
else
    bad "unexpected ls-remote arguments: $(cat "$STUB_ARGS_FILE")"
fi
check_branch "Patches" ""  "my-branch"     "my-branch"
# No branch is derivable for these, so they must stop. A non-empty $branch here
# means a release candidate would be overwritten with whatever stable is.
check_branch "Release Candidate" "" ""     ""
check_branch "Feature"           "" ""     ""

echo
echo "4. the payload is identified before it is executed"

# Order first: a version check that runs after installfog.sh has already been
# invoked is decoration.
#
# The call site is matched whole, not just by name. `if false && ! verifyPayload
# ...` keeps the call, keeps it above the installer, and never runs it -- so a
# grep for the function name passes on a check that has been switched off. The
# anchored pattern makes any added condition a visible failure rather than a
# line that still looks right.
guard='^if ! verifyPayload "\$extractdir" "\$latest"; then$'
verify_line=$(joined "$UPD" | grep -nE "$guard" | head -1 | cut -d: -f1)
install_line=$(joined "$UPD" | grep -n '\./installfog\.sh' | head -1 | cut -d: -f1)
if [[ -z $verify_line ]]; then
    bad "the verifyPayload guard is missing or has grown a condition -- it may no longer run"
elif [[ -n $install_line && $verify_line -lt $install_line ]]; then
    ok "the payload is verified before ./installfog.sh is invoked"
else
    bad "the payload is verified after ./installfog.sh has already run"
fi

# Then behavior, because the line-order check above cannot tell a check that
# FIRES from one that has been made unreachable -- `if false && ...` satisfies
# it, and so does a verifyPayload that returns 0 unconditionally. This runs the
# decision against three real trees: the version we asked for, a different
# version, and an archive that unpacked to nothing.
verify_case() {
    local dir="$1" expect="$2" want="$3" got
    (
        eval "$(body verifyPayload "$UPD")"
        verifyPayload "$dir" "$expect" 2>/dev/null
    )
    got=$?
    if [[ $got -eq $want ]]; then
        ok "verifyPayload: $4"
    else
        bad "verifyPayload: $4 -- returned $got, expected $want"
    fi
}

# Two trees per case, because verifyPayload has to read either spelling: core
# became PSR-4 on working-1.6 and System moved to packages/web/src/Base, while
# dev-branch and stable still ship packages/web/lib/fog/system.class.php. A
# probe that only ever tried one path would pass every check below with the
# other branch silently unverifiable.
mkdir -p "$tmp/good/packages/web/src/Base"  "$tmp/wrong/packages/web/src/Base" \
         "$tmp/goodold/packages/web/lib/fog" "$tmp/wrongold/packages/web/lib/fog" \
         "$tmp/empty"
printf "        define('FOG_VERSION', '1.6.0-beta.4108');\n" > "$tmp/good/packages/web/src/Base/System.php"
printf "        define('FOG_VERSION', '1.5.10.2254');\n"     > "$tmp/wrong/packages/web/src/Base/System.php"
printf "        define('FOG_VERSION', '1.5.10.2254');\n"     > "$tmp/goodold/packages/web/lib/fog/system.class.php"
printf "        define('FOG_VERSION', '1.6.0-beta.4108');\n" > "$tmp/wrongold/packages/web/lib/fog/system.class.php"

verify_case "$tmp/good"     "1.6.0-beta.4108" 0 "accepts the version it resolved (src/Base/System.php)"
verify_case "$tmp/wrong"    "1.6.0-beta.4108" 1 "refuses a package built from another branch (src/Base/System.php)"
verify_case "$tmp/goodold"  "1.5.10.2254"     0 "accepts the version it resolved (lib/fog/system.class.php)"
verify_case "$tmp/wrongold" "1.5.10.2254"     1 "refuses a package built from another branch (lib/fog/system.class.php)"
verify_case "$tmp/empty"    "1.6.0-beta.4108" 1 "refuses a tree with neither spelling"

# The extraction directory is emptied first. Untarring over a previous run left
# files that upstream had deleted in the tree that then got installed.
if joined "$UPD" | grep -qE '^rm -rf "\$extractdir"'; then
    ok "the extraction directory is emptied before the tarball is unpacked"
else
    bad "the tarball is unpacked over whatever a previous run left behind"
fi

# errorStat reports through $error_log, which only bin/installfog.sh sets.
# Reached from a utility its failure arm runs `tail -n 5` with no file
# argument, so tail reads stdin and the update hangs instead of reporting.
if joined "$UPD" | grep -qE '(^|[^[:alnum:]_])errorStat[[:space:]]'; then
    bad "$UPD calls errorStat -- with \$error_log unset its failure arm hangs on tail reading stdin"
else
    ok "failures are reported through handleError, not errorStat"
fi

echo
if [[ $FAIL -eq 0 ]]; then
    echo "PASS ($PASS assertions)"
    exit 0
fi
echo "FAIL ($FAIL of $((PASS + FAIL)) assertions)"
exit 1
