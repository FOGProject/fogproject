#!/usr/bin/env sh
#
# Regenerates packages/web/management/languages/messages.pot from the PHP
# source and merges that into every committed .po file.
#
# Pulled out of .githooks/pre-commit's old inline updateLanguage() function
# so fog-workflows' CI sweep can call the exact same logic instead of
# reimplementing it - the same reason fog-version.sh/apply-fog-version.sh
# are split out rather than duplicated in CI.
#
# Silently does nothing if xgettext/msgcat/msgmerge aren't installed, same
# as the function it replaces - a tool-less machine produces an incomplete
# commit rather than a failed one, and fog-workflows' daily sweep is what
# corrects that centrally.
#
# Usage: update-language.sh

set -e

command -v xgettext >/dev/null 2>&1 || exit 0
command -v msgcat   >/dev/null 2>&1 || exit 0
command -v msgmerge >/dev/null 2>&1 || exit 0

project_dir=$(git rev-parse --show-toplevel)
languages_dir="$project_dir/packages/web/management/languages"
pot_file="$languages_dir/messages.pot"

xgettext --language=PHP --from-code=UTF-8 --output="$pot_file" --omit-header --no-location $(find "$project_dir/packages/web/" -name "*.php")
msgcat --sort-output -o "$pot_file" "$pot_file"

for PO_FILE in $(find "$languages_dir" -type f -name "*.po"); do
    msgmerge --update --backup=none "$PO_FILE" "$pot_file" 2>/dev/null >/dev/null
    msgcat --sort-output -o "$PO_FILE" "$PO_FILE"
done
