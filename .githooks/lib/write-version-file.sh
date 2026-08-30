#!/usr/bin/env sh
#
# Writes packages/web/commons/version.php -- the GENERATED, GITIGNORED file
# that carries FOG_VERSION and FOG_CHANNEL for a tree checked out from git.
#
# WHY THIS FILE EXISTS AT ALL
#
# FOG_VERSION is `git rev-list master..HEAD --count`. It is a property of the
# commit graph, and every attempt to keep a copy of it in a TRACKED file has
# failed the same way: the value differs per branch, it lives on one line, and
# so any two branches open at once conflict on it. Worse, the quantity is
# self-perturbing -- getting a branch current adds a commit, which changes the
# count, which earns another rewrite of the line, which has to be pushed and
# pulled, which adds another commit. GH-1510 removed the per-commit and
# per-pull-request writers for exactly that reason.
#
# A derived value belongs in a generated file. This is that file, and it is
# gitignored for the same reason commons/config.class.php is: nothing that is
# computed from the environment should be in the merge surface.
#
# WHO CALLS THIS
#
#   .githooks/post-commit, post-checkout, post-merge -- so a working checkout
#     is always accurate, at no cost, since the output is never staged;
#   bin/installfog.sh -- so an install from a clone stamps the exact build it
#     is deploying, whether or not the caller has hooks enabled.
#
# WHAT HAPPENS WITHOUT IT
#
# Nothing breaks. src/Base/System.php includes this file only if it is
# readable and falls back to the release constants it carries itself, so a
# source zip with no .git, or a checkout by someone who never enabled hooks,
# still reports a truthful -- if less precise -- version.
#
# FAILS OPEN, ALWAYS. A version string is not worth blocking a commit, a
# checkout or an install over. Every error path here exits 0 having written
# nothing, which lands the tree on the fallback above.

set -e

project_dir=$(git rev-parse --show-toplevel 2>/dev/null) || exit 0
[ -n "$project_dir" ] || exit 0

out="$project_dir/packages/web/commons/version.php"
[ -d "$(dirname "$out")" ] || exit 0

# WHICH BRANCH NAME THE FORMULA GETS, AND WHY IT IS NOT ALWAYS THE CHECKED-OUT
# ONE.
#
# fog-version.sh derives the version PREFIX and the channel from the branch
# name -- `working-1.6` gives `1.6.0-beta.<count>` and Beta, `dev-*` gives
# Patches, and so on. A name it does not recognise falls through its case with
# no arm taken, and the committed value stands unchanged. That is right for
# CI, which only ever runs against the long-lived branches, and wrong here: a
# working branch is called something like `generated-version-file`, and taking
# its own name would make every feature branch report the bare release string.
#
# So an unrecognised name is resolved to the long-lived branch this work is
# actually based on -- the one closest to HEAD by commit count. The COUNT is
# unaffected either way, since fog-version.sh measures `master..HEAD` from the
# tree rather than from the name.
branch=$(git branch --show-current 2>/dev/null || true)
case "$branch" in
    working-*|dev-*|stable|rc-*|feature-*) ;;
    *)
        nearest=""
        nearest_distance=""
        for candidate in working-1.6 dev-branch stable; do
            for ref in "refs/heads/$candidate" "refs/remotes/origin/$candidate"; do
                git rev-parse --verify --quiet "$ref" >/dev/null 2>&1 || continue
                distance=$(git rev-list --count "$ref..HEAD" 2>/dev/null) || continue
                if [ -z "$nearest_distance" ] || [ "$distance" -lt "$nearest_distance" ]; then
                    nearest_distance="$distance"
                    nearest="$candidate"
                fi
            done
        done
        # Empty is not a failure: fog-version.sh defaults an empty first
        # argument to the checked-out branch, which is exactly the old
        # behaviour, and its own case falls through to the committed value.
        branch="$nearest"
        ;;
esac

# `head` mode: what the commit that ALREADY EXISTS should carry, with no +1.
# That is the right question here -- this describes the tree on disk, it does
# not predict a commit that is about to be written.
computed=$(sh "$project_dir/.githooks/lib/fog-version.sh" "$branch" head 2>/dev/null) || exit 0

version=$(printf '%s\n' "$computed" | sed -n '1p')
channel=$(printf '%s\n' "$computed" | sed -n '2p')
[ -n "$version" ] || exit 0

# Single quotes are the delimiter in the file, so a value containing one would
# produce a syntax error rather than a wrong version -- which is worse. There
# is no legitimate version string with a quote in it.
case "$version$channel" in
    *\'*) exit 0 ;;
esac

# Written in the same `define('FOG_VERSION', '...');` shape that
# src/Base/System.php uses, deliberately: bin/installfog.sh,
# bin/updatefog.sh and lib/common/utils.sh all read the version by awking
# for that exact pattern, and keeping the shape identical means they work
# against either file with no change to their parsing.
tmp="$out.$$"
cat > "$tmp" <<PHP
<?php
/**
 * GENERATED FILE -- DO NOT COMMIT, DO NOT EDIT.
 *
 * Written by .githooks/lib/write-version-file.sh from the commit graph, and
 * gitignored. src/Base/System.php includes it when present and falls back to
 * its own release constants when it is not. See that script's header for why
 * the version is not a tracked value.
 */

define('FOG_VERSION', '$version');
define('FOG_CHANNEL', '$channel');
PHP
mv -f "$tmp" "$out"
