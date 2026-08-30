#!/usr/bin/env sh
#
# Pins the contract introduced by GH-1513: FOG_VERSION is GENERATED into
# packages/web/commons/version.php and is NOT a tracked value.
#
# Why this needs a gate rather than a comment. The failure mode of putting the
# version back into git is invisible for exactly as long as only one branch is
# open, and then costs a hand-resolved conflict on every open pull request
# every time anything merges -- and the quantity is self-perturbing, so
# bringing a branch up to date moves it again (GH-1510). By the time it is
# noticed it is a busy afternoon, not a diff.
#
# Four things are checked, and each one has been made to fail:
#
#   A  the generated file is gitignored, and is not tracked;
#   B  the generator writes it in the shape the shell parsers read;
#   C  src/Base/System.php defines the version only as a FALLBACK -- it must
#      not unconditionally define it, or the include can never win;
#   D  nothing under .githooks/ writes the version into a tracked file.
#
# Exit 0 = pass, 1 = fail.

set -u

repo=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
generated="$repo/packages/web/commons/version.php"
system="$repo/packages/web/src/Base/System.php"
writer="$repo/.githooks/lib/write-version-file.sh"

rc=0
bad() {
    printf 'FAIL: %s\n' "$1" >&2
    rc=1
}

# --- A. generated, not tracked -------------------------------------------
if git -C "$repo" ls-files --error-unmatch packages/web/commons/version.php >/dev/null 2>&1; then
    bad "packages/web/commons/version.php is TRACKED. It is generated from the commit graph and must stay out of git -- see GH-1510 for what tracking it costs."
fi
if ! git -C "$repo" check-ignore -q packages/web/commons/version.php 2>/dev/null; then
    bad "packages/web/commons/version.php is not gitignored."
fi

# --- B. the generator produces the shape the shell parsers read ----------
# bin/installfog.sh, bin/updatefog.sh and lib/common/utils.sh all awk for
# `define('FOG_VERSION', '...');`. If the generator's output stops matching
# that, the installer silently falls back to the release constant and every
# error log and banner names the wrong build.
if [ ! -x "$writer" ]; then
    bad "$writer is missing or not executable."
else
    tmp=$(mktemp -d)
    saved=""
    if [ -f "$generated" ]; then
        saved="$tmp/saved.php"
        cp "$generated" "$saved"
    fi
    rm -f "$generated"
    sh "$writer" >/dev/null 2>&1
    if [ ! -f "$generated" ]; then
        bad "the generator wrote nothing in a git checkout."
    else
        got=$(awk -F\' /"define\('FOG_VERSION'[,](.*)"/'{print $4}' "$generated" | tr -d '[[:space:]]')
        [ -n "$got" ] || bad "the generated file carries no FOG_VERSION the installer's awk can read."
        got=$(awk -F\' /"define\('FOG_CHANNEL'[,](.*)"/'{print $4}' "$generated" | tr -d '[[:space:]]')
        [ -n "$got" ] || bad "the generated file carries no FOG_CHANNEL."
        php -l "$generated" >/dev/null 2>&1 || bad "the generated file is not valid PHP."

        # The generated value must not be the FALLBACK value.
        #
        # fog-version.sh derives the version prefix from the BRANCH NAME, and
        # a name it does not recognize takes no arm of its case, leaving the
        # committed value standing. A working branch is called something like
        # `generated-version-file`, so without the resolution step in
        # write-version-file.sh every feature branch generated the bare
        # release string and the whole mechanism silently did nothing.
        #
        # Skipped when the commit count is not computable -- a checkout with
        # no master ref -- since the two legitimately coincide there.
        count=$(git -C "$repo" rev-list master..HEAD --count 2>/dev/null || true)
        if [ -n "$count" ] && [ "$count" -gt 0 ] 2>/dev/null; then
            gen=$(awk -F\' /"define\('FOG_VERSION'[,](.*)"/'{print $4}' "$generated" | tr -d '[[:space:]]')
            fallback=$(awk -F\' /"define\('FOG_VERSION'[,](.*)"/'{print $4}' "$system" | tr -d '[[:space:]]')
            if [ "$gen" = "$fallback" ]; then
                bad "the generated version ($gen) is identical to the release fallback, so no build count was resolved -- see the branch-name resolution in write-version-file.sh."
            fi
        fi
    fi
    [ -n "$saved" ] && cp "$saved" "$generated"
    rm -rf "$tmp"
fi

# --- C. System.php defines the version only as a fallback ----------------
# The include has to come first and the defines have to be guarded, or the
# generated value can never take effect: PHP keeps the FIRST definition of a
# constant and warns about the second.
grep -q "commons/version.php" "$system" ||
    bad "src/Base/System.php no longer includes the generated commons/version.php."
grep -q "if (!defined('FOG_VERSION'))" "$system" ||
    bad "src/Base/System.php defines FOG_VERSION unguarded, so the generated file can never win."
grep -q "if (!defined('FOG_CHANNEL'))" "$system" ||
    bad "src/Base/System.php defines FOG_CHANNEL unguarded."

# --- D. no hook writes the version into a tracked file -------------------
# apply-fog-version.sh edits src/Base/System.php in place. CI still uses it to
# stamp a RELEASE; a local hook calling it would reintroduce exactly the
# per-commit churn GH-1510 removed.
for hook in "$repo"/.githooks/pre-commit "$repo"/.githooks/pre-merge-commit \
            "$repo"/.githooks/post-commit "$repo"/.githooks/post-checkout \
            "$repo"/.githooks/post-merge; do
    [ -f "$hook" ] || continue
    if grep -q "apply-fog-version.sh" "$hook"; then
        bad "$(basename "$hook") calls apply-fog-version.sh, which writes the version into the tracked src/Base/System.php."
    fi
done

[ "$rc" -eq 0 ] && echo "generated-version-file: version is generated, gitignored and read as a fallback"
exit "$rc"
