#!/usr/bin/env sh
#
# Regenerates the gettext translation template and merges it into every .po.
#
# Extracted from .githooks/pre-commit so the hook and CI run byte-identical
# commands. The version formula is already shared this way (fog-version.sh), and
# for the same reason: the moment there are two copies they drift, and the drift
# only shows up as churn in someone else's commit. fog-community-scripts'
# copytosvn.sh is the worked example -- its private copy of the version formula
# fell behind until it stamped FOG_CHANNEL='Alpha' on any branch it did not know.
#
# Like fog-version.sh this only does the work; it stages nothing and commits
# nothing, so the caller decides what to do with the result. The hook `git add`s
# the directory; CI commits it if the tree came back dirty.
#
# Requires xgettext, msgcat and msgmerge (the 'gettext' package). The caller
# checks for them -- see require-tools.sh -- because what to do when they are
# missing differs between the hook (skip, warn) and CI (fail the job).
#
# Usage: update-language.sh [project-dir]
#   project-dir defaults to the top level of the current git checkout.

set -e

project_dir="${1:-$(git rev-parse --show-toplevel)}"

langdir="$project_dir/packages/web/management/languages"
pot="$langdir/messages.pot"

# --omit-header and --no-location keep the output a pure function of the source
# strings: without them every regeneration rewrites the POT-Creation-Date and
# every source line number, so the file differs on every run even when no
# translatable string changed.
xgettext --language=PHP --from-code=UTF-8 --output="$pot" \
    --omit-header --no-location \
    $(find "$project_dir/packages/web/" -name "*.php")

# Sorting makes the file order-independent, so two machines that walked the
# source tree in a different order still produce the same POT.
msgcat --sort-output -o "$pot" "$pot"

for PO_FILE in $(find "$langdir/" -type f -name "*.po"); do
    msgmerge --update --backup=none "$PO_FILE" "$pot" 2>/dev/null >/dev/null
    msgcat --sort-output -o "$PO_FILE" "$PO_FILE"
done
