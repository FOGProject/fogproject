#!/bin/bash
#
# ADR 0023 merge 7 and ADR 0021 merge 9, pinned together because both are
# about a record that used to be kept by default and now is not.
#
#   tests/retention-default-and-login-files.test.sh
#
# MERGE 7 -- a bounded retention default reaches NEW installs only.
#
# The decision splits on install age, and the reason is specific rather than
# squeamish: on an upgrade the administrator never chose to hold host login
# records OR to delete them, and some of them are under a legal obligation to
# retain them. A privacy control that destroys evidence somebody is required
# to keep is not a privacy win. So a new install starts bounded, an upgrade
# stays at 0 and gets a dashboard notice instead.
#
# Two conditions guard the write and this pins both, because dropping either
# one reintroduces silent deletion:
#
#   priorInstall     - .fogsettings existed before this run. NOT $doupdate,
#                      which says whether an upgrade was attempted and is 0
#                      for --no-upgrade on a server that has run for years.
#   an empty table   - catches a re-install over a database that has been
#                      collecting login records all along, which is the same
#                      surprise wearing a different hat.
#
# MERGE 9 -- the two flat login files are retired.
#
# fog_login_accepted.log and fog_login_failed.log were written only by the web
# form, so the iPXE menu, service/checkcredentials.php and authenticateOnly()
# never appeared in them. auditLog records all four. Nothing may create or
# write those files again -- but nothing may DELETE an existing one either:
# on a server that has just upgraded they hold the only record of a login from
# before the upgrade.
#
# Static: greps the installer and the web tier. No network, no root, no
# install, no database.
#
# Exit status 0 = pass or skip, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"
INSTALLER="$REPO/bin/installfog.sh"
LOGIN="$REPO/packages/web/lib/pages/processlogin.page.php"
DASH="$REPO/packages/web/lib/pages/dashboardpage.page.php"

for f in "$FUNCS" "$INSTALLER" "$LOGIN" "$DASH"; do
    [[ -f $f ]] || { echo "ERROR: $f not found" >&2; exit 1; }
done

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
has() { grep -q -- "$2" "$1" && ok "$3" || bad "$3"; }
hasnt() { grep -q -- "$2" "$1" && bad "$3" || ok "$3"; }

echo "retention default and login files:"

# --- merge 7: the new-install default ----------------------------------------
body="$(sed -n '/^applyNewInstallDefaults() {/,/^}/p' "$FUNCS")"
if [[ -z $body ]]; then
    bad "applyNewInstallDefaults() exists in functions.sh"
else
    ok "applyNewInstallDefaults() exists in functions.sh"
    grep -q 'priorInstall' <<<"$body" \
        && ok "it returns early on a machine that had FOG before" \
        || bad "it returns early on a machine that had FOG before"
    grep -q 'SELECT COUNT(\*) FROM userTracking' <<<"$body" \
        && ok "it also counts the userTracking table" \
        || bad "it also counts the userTracking table"
    # The count on its own proves nothing -- it is the early return that makes
    # it a guard, and a mutation that kept the query and dropped the return
    # passed an earlier revision of this test.
    grep -q 'trackedRows -ne 0 \]\] && return' <<<"$body" \
        && ok "and returns early when the table is not empty" \
        || bad "and returns early when the table is not empty"
    grep -q "settingValue='365'" <<<"$body" \
        && ok "it sets 365 days" \
        || bad "it sets 365 days"
    grep -q "AND settingValue='0'" <<<"$body" \
        && ok "and only over a window nobody has chosen" \
        || bad "and only over a window nobody has chosen"
fi

has "$INSTALLER" 'priorInstall=1' \
    "the installer records whether .fogsettings existed"
has "$INSTALLER" 'applyNewInstallDefaults' \
    "the installer calls it"

# priorInstall must be captured BEFORE anything writes .fogsettings, or it is
# always 1 and the default never applies to anybody.
capture="$(grep -n 'priorInstall=1' "$INSTALLER" | head -1 | cut -d: -f1)"
writes="$(grep -n 'writeFogSettings' "$INSTALLER" | head -1 | cut -d: -f1)"
if [[ -n $capture && -n $writes ]]; then
    [[ $capture -lt $writes ]] \
        && ok "and records it before .fogsettings is written" \
        || bad "and records it before .fogsettings is written ($capture vs $writes)"
else
    bad "and records it before .fogsettings is written (could not locate both)"
fi

# --- merge 7: the upgrade notice ---------------------------------------------
notice="$(sed -n '/private static function _userTrackingRetentionNotice/,/^    }/p' "$DASH")"
if [[ -z $notice ]]; then
    bad "the dashboard carries the upgrade notice"
else
    ok "the dashboard carries the upgrade notice"
    grep -q "can('audit.manage')" <<<"$notice" \
        && ok "shown only to someone who can set the window" \
        || bad "shown only to someone who can set the window"
    grep -q 'FOG_USERTRACKING_RETENTION_DAYS' <<<"$notice" \
        && ok "shown only while no window is set" \
        || bad "shown only while no window is set"
    grep -q "getCount('usertracking')" <<<"$notice" \
        && ok "shown only when the table holds rows" \
        || bad "shown only when the table holds rows"
fi
grep -q '_userTrackingRetentionNotice();' "$DASH" \
    && ok "and the dashboard actually calls it" \
    || bad "and the dashboard actually calls it"

# --- merge 9: the flat login files -------------------------------------------
hasnt "$LOGIN" 'fog_login_accepted.log' \
    "the login page no longer writes fog_login_accepted.log"
hasnt "$LOGIN" 'fog_login_failed.log' \
    "the login page no longer writes fog_login_failed.log"
hasnt "$FUNCS" 'touch $webdirdest/fog_login' \
    "the installer no longer creates them"

# Nothing anywhere may name them again OUTSIDE a comment. Matching the
# filename alone is not enough -- both retirement notes mention it on purpose,
# and an earlier revision of this test failed on its own explanation. Code is
# what matters, so comment lines are dropped first.
writers="$(grep -rn 'fog_login_accepted\.log\|fog_login_failed\.log' \
    "$REPO/packages" "$REPO/lib" "$REPO/bin" 2>/dev/null \
    | grep -v ':[[:space:]]*\(#\|//\|\*\|/\*\)')"
[[ -z $writers ]] \
    && ok "nothing in the tree names either file outside a comment" \
    || bad "nothing in the tree names either file outside a comment ($writers)"

# ...and nothing may delete one that is already there.
deleters="$(grep -rn 'rm .*fog_login\|unlink(.*fog_login' \
    "$REPO/packages" "$REPO/lib" "$REPO/bin" 2>/dev/null)"
[[ -z $deleters ]] \
    && ok "and nothing deletes an existing one" \
    || bad "and nothing deletes an existing one ($deleters)"

# The replacement has to be real, or this is just removal.
AUDIT="$REPO/packages/web/lib/fog/user.class.php"
grep -q "Audit::record\|Audit::" "$AUDIT" \
    && ok "the credential funnel still records refusals to auditLog" \
    || bad "the credential funnel still records refusals to auditLog"

echo
echo "  $PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
