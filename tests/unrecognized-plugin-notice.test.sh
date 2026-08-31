#!/bin/bash
#
# Pins _warnUnrecognizedPlugins(), which configureHttpd() calls right after
# the old web tree is backed up to fog_web_<ver>.BACKUP.
#
#   tests/unrecognized-plugin-notice.test.sh
#
# On 1.5 a plugin had no existence independent of packages/web/lib/plugins/ --
# hand-placing a directory there WAS how you installed one. ADR 0009 gives
# third-party plugins their own root ($fogprogramdir/plugins, so
# /opt/fog/plugins by default) that upgrades never touch, but that fixes
# nothing for a plugin an admin already hand-placed the old way: configureHttpd()
# still does `rm -rf $webdirdest` and re-lays the tree from $webdirsrc, and
# only the bundled set downloadplugins() fetched into $webdirsrc/lib/plugins/
# comes back. The old tree survives only in fog_web_<ver>.BACKUP, and only
# --oldcopy even copies that back -- silently, as a directory nobody is told
# to go looking for.
#
# downloadplugins()'s own failure-path message (lib/common/functions.sh:3039,
# "For an offline install, place the plugin directories in
# packages/web/lib/plugins/ and re-run") does NOT cover this: it only prints
# when fetch-plugins.sh itself fails, on every run whether or not the admin
# ever had a plugin of their own, and it says nothing about what is actually
# sitting in the old tree. This test is what stands in for that gap.
#
# Pinned:
#   1. Silence when there is nothing to say: no backup, no old lib/plugins/,
#      or an old lib/plugins/ containing only names the bundled release
#      already fetched. A notice on every ordinary upgrade is a notice nobody
#      reads by the tenth one.
#   2. Every unrecognized directory is named, not just the first or a count.
#   3. The new location is stated, using $fogprogramdir (not a hard-coded
#      /opt/fog), because that is the variable an admin may have overridden.
#   4. Nothing here is fatal -- the function returns 0 in every case, so it
#      can never abort an install (matches the pattern _warnClientRepin sets
#      for a non-fatal, informational check).
#
# No install, no network, no root.
#
# Exit status 0 = pass or skip, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"

[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }

error_log="$WORK/error.log"
: > "$error_log"

# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1

DB_backup_path="$WORK/backups"
version="1.6.0-test"
webdirsrc="$WORK/src"
fogprogramdir="$WORK/opt/fog"
mkdir -p "$DB_backup_path" "$webdirsrc/lib/plugins"

# Stand in for the bundled set THIS release fetched: two names, the same way
# downloadplugins() would have left them in $webdirsrc/lib/plugins before
# configureHttpd() runs.
mkdir -p "$webdirsrc/lib/plugins/hostext" "$webdirsrc/lib/plugins/snapinclientpush"

reset_old_tree() {
    rm -rf "${DB_backup_path}/fog_web_${version}.BACKUP"
}

echo "unrecognized plugin notice:"

# --- 1. no backup at all: silent ---------------------------------------------
reset_old_tree
out="$(_warnUnrecognizedPlugins)"
rc=$?
if [[ -z $out ]]; then
    ok "no backup directory produces no output"
else
    bad "no backup directory should be silent, got: $out"
fi
[[ $rc -eq 0 ]] && ok "still returns 0 with nothing to report" \
    || bad "returned $rc with nothing to report"

# --- 2. backup exists, no lib/plugins/ at all: silent -------------------------
reset_old_tree
mkdir -p "${DB_backup_path}/fog_web_${version}.BACKUP/management"
out="$(_warnUnrecognizedPlugins)"
if [[ -z $out ]]; then
    ok "a backup with no lib/plugins/ produces no output"
else
    bad "a backup with no lib/plugins/ should be silent, got: $out"
fi

# --- 3. old lib/plugins/ has only names the bundled release also has: silent -
reset_old_tree
mkdir -p "${DB_backup_path}/fog_web_${version}.BACKUP/lib/plugins/hostext"
mkdir -p "${DB_backup_path}/fog_web_${version}.BACKUP/lib/plugins/snapinclientpush"
out="$(_warnUnrecognizedPlugins)"
if [[ -z $out ]]; then
    ok "only recognized (bundled) plugin directories produces no output"
else
    bad "recognized-only should be silent, got: $out"
fi

# --- 4. one hand-placed third-party plugin: named, and told where to go ------
reset_old_tree
mkdir -p "${DB_backup_path}/fog_web_${version}.BACKUP/lib/plugins/hostext"
mkdir -p "${DB_backup_path}/fog_web_${version}.BACKUP/lib/plugins/mycompanytool"
out="$(_warnUnrecognizedPlugins)"
if [[ $out == *"mycompanytool"* ]]; then
    ok "the unrecognized directory is named by name"
else
    bad "expected 'mycompanytool' in output, got: $out"
fi
if [[ $out == *"hostext"* ]]; then
    bad "a recognized (bundled) plugin was named as unrecognized: $out"
else
    ok "the recognized (bundled) plugin is not named"
fi
if [[ $out == *"${fogprogramdir}/plugins"* ]]; then
    ok "the new destination is stated using \$fogprogramdir"
else
    bad "expected '${fogprogramdir}/plugins' in output, got: $out"
fi

# --- 5. multiple hand-placed plugins: every one is named, not just the first -
reset_old_tree
mkdir -p "${DB_backup_path}/fog_web_${version}.BACKUP/lib/plugins/mycompanytool"
mkdir -p "${DB_backup_path}/fog_web_${version}.BACKUP/lib/plugins/anothervendorplugin"
out="$(_warnUnrecognizedPlugins)"
if [[ $out == *"mycompanytool"* && $out == *"anothervendorplugin"* ]]; then
    ok "every unrecognized directory is named, not just one"
else
    bad "expected both names in output, got: $out"
fi

# --- 6. an EMPTY bundled set says nothing rather than naming everything ------
#
# "Unrecognized" is a comparison, so with no bundled set to compare against
# every plugin the admin has would be named as third-party. configureHttpd is
# reached that way for real: configureMinHttpd() -- the storage node path at
# installfog.sh:1353 -- calls it without downloadplugins ever running, and
# lib/plugins is gitignored on 1.6, so a storage-node install from a clone
# arrives here with $webdirsrc/lib/plugins empty.
reset_old_tree
mkdir -p "${DB_backup_path}/fog_web_${version}.BACKUP/lib/plugins/hostext"
mkdir -p "${DB_backup_path}/fog_web_${version}.BACKUP/lib/plugins/mycompanytool"
saved_src="$webdirsrc"
webdirsrc="$WORK/emptysrc"
mkdir -p "$webdirsrc/lib/plugins"
out="$(_warnUnrecognizedPlugins)"
if [[ -z $out ]]; then
    ok "an empty bundled set reports nothing rather than naming every plugin"
else
    bad "expected silence with no bundled set to compare against, got: $out"
fi
webdirsrc="$saved_src"

# --- 7. never fatal: returns 0 even when it has something to report ----------
reset_old_tree
mkdir -p "${DB_backup_path}/fog_web_${version}.BACKUP/lib/plugins/mycompanytool"
_warnUnrecognizedPlugins >/dev/null
rc=$?
if [[ $rc -eq 0 ]]; then
    ok "finding unrecognized plugins still returns 0 (non-fatal)"
else
    bad "should never be fatal, returned $rc"
fi

echo
echo "  $PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
