#!/usr/bin/env sh
#
# Writes FOG_VERSION/FOG_CHANNEL into packages/web/lib/fog/system.class.php.
#
# Pairs with fog-version.sh, which computes what these values should be -
# this script only knows how to write them, not what they should be, so the
# formula never has to be duplicated alongside the write step.
#
# Usage: apply-fog-version.sh <version> <channel>

set -e

version="$1"
channel="$2"

project_dir=$(git rev-parse --show-toplevel)
system_file="$project_dir/packages/web/lib/fog/system.class.php"

sed -i "s/define('FOG_VERSION',.*);/define('FOG_VERSION', '$version');/g" "$system_file"
sed -i "s/define('FOG_CHANNEL',.*);/define('FOG_CHANNEL', '$channel');/g" "$system_file"
