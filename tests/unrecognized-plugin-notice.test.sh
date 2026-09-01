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
#   5. accesscontrol is named SEPARATELY and given the OPPOSITE advice. It is
#      retired rather than relocated -- 1.6 replaces it with core roles and
#      permissions -- so the "copy it to $fogprogramdir/plugins" line the rest
#      of this notice gives would tell an admin to reinstall the thing the
#      upgrade just removed. It is also unreachable by the scan: the backup
#      step deletes it out of the backup before this function ever runs, so
#      the notice keys on a flag _stripRetiredPlugins sets, and is
#      emitted ahead of both guards that exist only for the comparison.
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

# --- 8. the RETIRED accesscontrol plugin is named, with different advice -----
#
# accesscontrol is not a plugin to relocate. 1.6 replaces it with core roles
# and permissions (schema steps 302-306 migrate the roles and the user
# assignments; step 307 deletes its `plugins` row), so telling an admin to copy
# it into $fogprogramdir/plugins would reinstall the thing the upgrade just
# retired.
#
# It also cannot come from the scan the rest of this function does. The backup
# step calls _stripRetiredPlugins, which DELETES the directory out of the
# backup, and only then does configureHttpd call _warnUnrecognizedPlugins --
# so at scan time there is nothing on disk to find. The flag is the only record.
reset_old_tree
retiredpluginsstripped="accesscontrol"
mkdir -p "${DB_backup_path}/fog_web_${version}.BACKUP/lib/plugins/hostext"
out="$(_warnUnrecognizedPlugins)"
if [[ $out == *"accesscontrol"* ]]; then
    ok "the retired accesscontrol plugin is named"
else
    bad "expected 'accesscontrol' in output, got: $out"
fi
if [[ $out == *"Do NOT copy it to ${fogprogramdir}/plugins"* ]]; then
    ok "accesscontrol is told NOT to be relocated"
else
    bad "expected the do-not-relocate line for accesscontrol, got: $out"
fi
if [[ $out == *"Role Management"* ]]; then
    ok "accesscontrol points at core Role Management"
else
    bad "expected 'Role Management' in output, got: $out"
fi
retiredpluginsstripped=""

# --- 9. no accesscontrol, no accesscontrol notice ----------------------------
reset_old_tree
mkdir -p "${DB_backup_path}/fog_web_${version}.BACKUP/lib/plugins/mycompanytool"
out="$(_warnUnrecognizedPlugins)"
if [[ $out == *"accesscontrol"* ]]; then
    bad "named accesscontrol when it was never there: $out"
else
    ok "no accesscontrol in the old tree produces no accesscontrol notice"
fi

# --- 10. the accesscontrol notice survives both guards on the scan below -----
#
# The two early returns exist for the COMPARISON -- no old lib/plugins/, and no
# bundled set to compare against. Neither has any bearing on a retired plugin
# whose advice is not a comparison, and a storage-node install (which reaches
# configureHttpd with an empty $webdirsrc/lib/plugins, installfog.sh:1353)
# would otherwise swallow it.
reset_old_tree
retiredpluginsstripped="accesscontrol"
out="$(_warnUnrecognizedPlugins)"
if [[ $out == *"accesscontrol"* ]]; then
    ok "accesscontrol is named with no old lib/plugins/ left to scan"
else
    bad "the no-backup guard swallowed the accesscontrol notice: $out"
fi
mkdir -p "${DB_backup_path}/fog_web_${version}.BACKUP/lib/plugins/hostext"
saved_src="$webdirsrc"
webdirsrc="$WORK/emptysrc2"
mkdir -p "$webdirsrc/lib/plugins"
out="$(_warnUnrecognizedPlugins)"
if [[ $out == *"accesscontrol"* ]]; then
    ok "accesscontrol is named with an empty bundled set"
else
    bad "the empty-bundled-set guard swallowed the accesscontrol notice: $out"
fi
webdirsrc="$saved_src"
retiredpluginsstripped=""

# --- 11. _stripRetiredPlugins: removes them, records them, never fails ----
#
# The removal is not new -- it predates this notice and is correct. What is
# pinned here is that it still happens, that the flag is set only when the
# directory was really there, and that it returns 0: it sits between a cp and
# an `errorStat $?` reporting on the BACKUP, so a non-zero here would report a
# failed backup that did not fail.
reset_old_tree
retiredpluginsstripped=""
mkdir -p "${DB_backup_path}/fog_web_${version}.BACKUP/lib/plugins/accesscontrol/class"
_stripRetiredPlugins
rc=$?
if [[ ! -d "${DB_backup_path}/fog_web_${version}.BACKUP/lib/plugins/accesscontrol" ]]; then
    ok "accesscontrol is removed from the backup"
else
    bad "accesscontrol was left in the backup"
fi
[[ $retiredpluginsstripped == *accesscontrol* ]] && ok "the strip records that it happened" \
    || bad "the strip did not record that accesscontrol was there"
[[ $rc -eq 0 ]] && ok "the strip returns 0 (it feeds an errorStat for the backup)" \
    || bad "the strip returned $rc"

reset_old_tree
retiredpluginsstripped=""
mkdir -p "${DB_backup_path}/fog_web_${version}.BACKUP/lib/plugins/hostext"
_stripRetiredPlugins
rc=$?
if [[ $retiredpluginsstripped != *accesscontrol* ]]; then
    ok "the strip records nothing when accesscontrol was not there"
else
    bad "the strip claimed accesscontrol was there when it was not"
fi
[[ $rc -eq 0 ]] && ok "the strip returns 0 with nothing to remove" \
    || bad "the strip returned $rc with nothing to remove"

# --- 12. still never fatal with the accesscontrol notice to print -----------
reset_old_tree
retiredpluginsstripped="accesscontrol"
_warnUnrecognizedPlugins >/dev/null
rc=$?
[[ $rc -eq 0 ]] && ok "the accesscontrol notice is non-fatal too" \
    || bad "should never be fatal, returned $rc"
retiredpluginsstripped=""

# --- 13. the production retirement list, read from functions.sh -------------
#
# The point of $retiredplugins being top level rather than assigned inside the
# backup step: this asserts the list the INSTALLER uses, so a name dropped from
# it fails here instead of silently losing its notice. Everything below sets
# $retiredpluginsstripped by hand to exercise the reporting, which would keep
# passing against an empty production list.
if [[ $retiredplugins == *accesscontrol* ]]; then
    ok "accesscontrol is on the installer's retirement list"
else
    bad "accesscontrol missing from retiredplugins='$retiredplugins'"
fi
if [[ $retiredplugins == *persistentgroups* ]]; then
    ok "persistentgroups is on the installer's retirement list"
else
    bad "persistentgroups missing from retiredplugins='$retiredplugins'"
fi

# --- 14. persistentgroups is retired too, with its OWN advice ---------------
#
# ADR 0038. It is on the same list for the same reason -- core replaced it, so
# "copy it to $fogprogramdir/plugins" would reinstall what the upgrade removed
# -- but its advice is not accesscontrol's, and the difference is the point:
# this plugin left a database TRIGGER behind that deleting its files never
# reached, so the notice has to say the trigger is gone and that reinstalling
# brings it back. Schema step 402 is what actually drops it.
reset_old_tree
retiredpluginsstripped="persistentgroups"
mkdir -p "${DB_backup_path}/fog_web_${version}.BACKUP/lib/plugins/hostext"
out="$(_warnUnrecognizedPlugins)"
if [[ $out == *"persistentgroups"* ]]; then
    ok "the retired persistentgroups plugin is named"
else
    bad "expected 'persistentgroups' in output, got: $out"
fi
if [[ $out == *"TRIGGER"* ]]; then
    ok "the notice says the database trigger was dropped"
else
    bad "expected the trigger to be mentioned, got: $out"
fi
if [[ $out == *"Do NOT copy it to ${fogprogramdir}/plugins"* ]]; then
    ok "persistentgroups is told NOT to be relocated"
else
    bad "expected the do-not-relocate line for persistentgroups, got: $out"
fi
if [[ $out == *"Role Management"* ]]; then
    bad "gave persistentgroups accesscontrol's advice: $out"
else
    ok "persistentgroups does not get accesscontrol's Role Management advice"
fi
retiredpluginsstripped=""

# --- 15. both at once, each with its own advice -----------------------------
#
# The list is iterated, so two retired plugins in one old tree must produce two
# notices rather than the first one only.
reset_old_tree
retiredpluginsstripped="accesscontrol persistentgroups"
out="$(_warnUnrecognizedPlugins)"
if [[ $out == *"accesscontrol"* && $out == *"persistentgroups"* ]]; then
    ok "both retired plugins are named when both were present"
else
    bad "expected both retired plugins, got: $out"
fi
if [[ $out == *"Role Management"* && $out == *"TRIGGER"* ]]; then
    ok "each retired plugin keeps its own advice"
else
    bad "expected both advice blocks, got: $out"
fi
retiredpluginsstripped=""

# --- 16. the strip walks the whole list ------------------------------------
reset_old_tree
retiredpluginsstripped=""
mkdir -p "${DB_backup_path}/fog_web_${version}.BACKUP/lib/plugins/accesscontrol/class"
mkdir -p "${DB_backup_path}/fog_web_${version}.BACKUP/lib/plugins/persistentgroups/src"
mkdir -p "${DB_backup_path}/fog_web_${version}.BACKUP/lib/plugins/hostext"
_stripRetiredPlugins
rc=$?
if [[ ! -d "${DB_backup_path}/fog_web_${version}.BACKUP/lib/plugins/persistentgroups" ]]; then
    ok "persistentgroups is removed from the backup"
else
    bad "persistentgroups was left in the backup"
fi
if [[ -d "${DB_backup_path}/fog_web_${version}.BACKUP/lib/plugins/hostext" ]]; then
    ok "a third-party plugin beside them is NOT removed"
else
    bad "the strip removed a plugin that is not retired"
fi
[[ $retiredpluginsstripped == *accesscontrol* && $retiredpluginsstripped == *persistentgroups* ]] \
    && ok "the strip records every retired plugin it found" \
    || bad "the strip recorded '$retiredpluginsstripped'"
[[ $rc -eq 0 ]] && ok "the multi-plugin strip returns 0" \
    || bad "the strip returned $rc"
retiredpluginsstripped=""

echo
echo "  $PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
