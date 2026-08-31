#!/bin/bash
#
# Pins bin/fetch-plugins.sh against a half-deleted plugin tree.
#
#   tests/plugin-tree-integrity.test.sh
#
# packages/web/lib/plugins is gitignored on working-1.6 (ADR 0009 moved the
# bundled plugins to FOGProject/fog-plugins) and still TRACKED on dev-branch
# and stable. Checking out either of those and coming back therefore deletes
# every plugin file whose path the two branches share -- git owns those paths
# on the other branch and simply removes them -- while the .fog-plugins-version
# stamp survives, because no branch tracks it.
#
# What that leaves is the worst shape available: a tree that looks installed.
# Measured on a 1.6 server, 85 of 278 files were gone, among them every
# class/ directory, and the plugins table still said installed, so
# Hook::registerInstalled() went on registering hooks whose model classes no
# longer existed. Deleting any host answered HTTP 406,
# `Class "locationassociation" does not exist` -- raised in core, caused by a
# plugin, naming nothing that points at the checkout that did it. The old
# script could not help, because it asked only "which release is this" and the
# answer was still correct.
#
# So the stamp is paired with a manifest and the script re-fetches when files
# named in it are missing. The two cases below that are NOT about damage
# matter just as much: a hand-placed offline tree must still be left alone,
# and a re-fetch that cannot reach the network must not destroy the tree it
# was trying to repair.
#
# Needs curl (file:// only), tar and sha256sum. No network, no root, no
# install.
#
# Exit status 0 = pass or skip, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
SCRIPT="$REPO/bin/fetch-plugins.sh"

[[ -f $SCRIPT ]] || { echo "ERROR: $SCRIPT not found" >&2; exit 1; }
for tool in curl tar sha256sum; do
    command -v "$tool" >/dev/null 2>&1 || { echo "SKIP: $tool is not installed"; exit 0; }
done

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
is()  { [[ "$1" == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }
has() { [[ -e "$1" ]] && ok "$2" || bad "$2 (missing $1)"; }
hasnt() { [[ -e "$1" ]] && bad "$2 ($1 exists)" || ok "$2"; }

VER="v9.9.9"

# A stand-in release, laid out the way fog-plugins ships (ADR 0035): buckets
# under <plugin>/src, plus files outside src/ so a partial deletion can be
# told from a total one.
build_release() {
    local src="$WORK/src"
    rm -rf "$src" "$WORK/rel"
    mkdir -p "$src/location/src/Items" "$src/location/src/Managers" \
        "$src/location/src/Hooks" "$src/location/js" "$src/oidc/src/Items"
    echo '<?php // location' > "$src/location/src/Items/LocationAssociation.php"
    echo '<?php // manager'  > "$src/location/src/Managers/LocationManager.php"
    echo '<?php // hook'     > "$src/location/src/Hooks/LocationDeleteMassItems.php"
    echo '// js'             > "$src/location/js/fog.location.list.js"
    echo '<?php // oidc'     > "$src/oidc/src/Items/OIDC.php"
    mkdir -p "$WORK/rel/$VER"
    tar -czf "$WORK/rel/$VER/fog-plugins-${VER}.tar.gz" -C "$src" .
    (cd "$WORK/rel/$VER" && sha256sum "fog-plugins-${VER}.tar.gz" \
        > "fog-plugins-${VER}.tar.gz.sha256")
}

# The script derives its destination from its own location, so it runs from a
# throwaway tree rather than being handed an override it would not otherwise
# have. pluginsVer in the environment keeps it from reading System.php.
FAKE="$WORK/repo"
DEST="$FAKE/packages/web/lib/plugins"
mkdir -p "$FAKE/bin"
cp "$SCRIPT" "$FAKE/bin/fetch-plugins.sh"

run() {
    ( export pluginsVer="$VER" pluginsurl="file://$WORK/rel"
      bash "$FAKE/bin/fetch-plugins.sh" "$@" 2>&1 )
}

build_release

echo "plugin tree integrity:"

# --- a fetch records what it laid down ---------------------------------------
out="$(run)"
is "$?" "0" "first fetch succeeds"
has "$DEST/.fog-plugins-version" "stamp written"
has "$DEST/.fog-plugins-manifest" "manifest written"
is "$(wc -l < "$DEST/.fog-plugins-manifest")" "5" "manifest lists every file"
is "$(grep -c 'fog-plugins-' "$DEST/.fog-plugins-manifest")" "0" \
    "manifest lists neither marker file"

# --- an intact tree is left alone --------------------------------------------
before="$(find "$DEST" -type f -printf '%p\n' | sort | sha256sum)"
out="$(run)"
case "$out" in
    *"already at"*) ok "intact tree is a no-op" ;;
    *) bad "intact tree is a no-op (said: $out)" ;;
esac
is "$(find "$DEST" -type f -printf '%p\n' | sort | sha256sum)" "$before" \
    "no-op changed nothing"

# --- the branch-switch case: files gone, stamp still right -------------------
# Exactly what `git checkout dev-branch && git checkout working-1.6` leaves.
rm -rf "$DEST/location/src/Items"
out="$(run)"
case "$out" in
    *incomplete*) ok "half-deleted tree is reported incomplete" ;;
    *) bad "half-deleted tree is reported incomplete (said: $out)" ;;
esac
has "$DEST/location/src/Items/LocationAssociation.php" \
    "the class the delete hook needs is restored"
has "$DEST/location/src/Managers/LocationManager.php" "sibling class restored"

# --- an install predating the manifest heals itself --------------------------
rm -f "$DEST/.fog-plugins-manifest"
rm -f "$DEST/oidc/src/Items/OIDC.php"
run >/dev/null
has "$DEST/.fog-plugins-manifest" "a tree with no manifest is refetched"
has "$DEST/oidc/src/Items/OIDC.php" "and repaired while it is there"

# --- a hand-placed tree is still untouchable ---------------------------------
rm -rf "$DEST"
mkdir -p "$DEST/mine"
echo 'mine' > "$DEST/mine/thing.php"
out="$(run)"
case "$out" in
    *"pre-placed"*) ok "unstamped tree is left alone" ;;
    *) bad "unstamped tree is left alone (said: $out)" ;;
esac
hasnt "$DEST/location" "offline tree was not overwritten"
hasnt "$DEST/.fog-plugins-manifest" "offline tree got no manifest"

# --- a repair that cannot reach the release keeps what is there --------------
rm -rf "$DEST"
run >/dev/null
rm -rf "$DEST/location/src/Items"
out="$( export pluginsVer="$VER" pluginsurl="file://$WORK/nowhere"
        bash "$FAKE/bin/fetch-plugins.sh" 2>&1 )"
is "$?" "1" "unreachable release fails loudly"
has "$DEST/location/src/Hooks/LocationDeleteMassItems.php" \
    "failed repair left the surviving files alone"
has "$DEST/.fog-plugins-version" "failed repair left the stamp alone"

# --- a swap that cannot be performed must not report success ----------------
# The swap at the end of the script is the only step whose failure produces a
# WRONG ANSWER rather than an error, so it is the only one checked. `set -e` is
# deliberately off -- the download loop needs to tolerate failures and retry --
# and with the rm/mv unguarded the script printed its errors to stderr, fell
# through to "Plugins at <version>" and exited 0 while the old release was
# still on disk. Hit for real on a tree left root-owned by a previous install,
# during the pin bump that carried the Font Awesome 7 migration: the installer's
# downloadplugins reports that run as done, so the server ships FA4 plugin icon
# names against a core with no v4 shims, and the stamp still names the old
# release so nothing downstream can tell either.
#
# A read-only destination reproduces it without root: rm cannot unlink the
# children of a directory it has no write bit on. Skipped as root, which
# ignores the mode entirely.
if [[ $EUID -eq 0 ]]; then
    echo "  skip  unwritable destination (running as root)"
else
    rm -rf "$DEST"
    run >/dev/null
    echo "v0.0.1" > "$DEST/.fog-plugins-version"   # an older pin, so a fetch is due
    chmod 555 "$DEST"
    out="$(run)"
    rc=$?
    chmod 755 "$DEST"
    is "$rc" "1" "an unwritable destination fails"
    case "$out" in
        *"Plugins at $VER"*) bad "no success line when nothing was written (said: $out)" ;;
        *) ok "no success line when nothing was written" ;;
    esac
    is "$(cat "$DEST/.fog-plugins-version")" "v0.0.1" "the old tree is still in place"
fi

echo
echo "  $PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
