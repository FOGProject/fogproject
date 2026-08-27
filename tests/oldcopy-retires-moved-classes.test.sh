#!/bin/bash
#
# An --oldcopy upgrade must not resurrect retired class files.
#
#   tests/oldcopy-retires-moved-classes.test.sh
#
# configureHttpd() wipes the web root and rebuilds it, so a file the new
# release dropped is genuinely gone -- unless FOG_copy_back_old is yes, in
# which case the whole pre-wipe backup is copied back in first and the new
# tree is laid over the top. Anything the release stopped shipping survives
# that, silently, for the rest of the server's life.
#
# It was a latent problem for any dropped class file. Moving core to PSR-4
# retired 201 of them at once: every lib/**/*.class.php that used to hold a
# core class now ships as src/<Bucket>/<Class>.php. On an --oldcopy upgrade
# both spellings end up on disk, and while the autoloader answers a bare name
# from src/ first -- so the classes are inert -- Initiator::classFileList()
# still walks the stale ones, they still enter the class map, and their
# directories are still on the include_path built from it. That is a server
# that differs from a freshly installed one with nothing to say so.
#
# The three things this pins, in the order they are easy to get wrong:
#
#   1. a class file the release no longer ships is REMOVED from the restored
#      tree -- the whole point;
#   2. a class file it still ships is left alone. The obvious wrong
#      implementation deletes every *.class.php it finds, which takes
#      lib/router/altorouter.class.php with it;
#   3. lib/fog/config.class.php survives. It is generated later in
#      configureHttpd() and is never in $webdirsrc, so a rule asked purely of
#      the source tree classifies it as retired. Deleting it is survivable --
#      it is rewritten a few steps on -- but only by luck, and the keep is
#      cheaper than the reasoning.
#
# Plus the bundled-plugin boundary: a plugin's class files sit one level
# deeper, at lib/plugins/<name>/class/, and belong to the fog-plugins release
# rather than to this loop. maxdepth is what holds that, and maxdepth is
# exactly the kind of thing a later edit widens without noticing.
#
# The loop is EXECUTED against a fixture, not read, for the reason
# tests/webroot-preserved-files.test.sh gives: a textual check passes on a
# loop that names the right variables and gets the condition backwards.
#
# No root, no network, no FOG install.
#
# Usage: bash tests/oldcopy-retires-moved-classes.test.sh
# Exit status 0 = pass, 1 = fail.

root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
functions="$root/lib/common/functions.sh"

pass=0
fail=0

check() {
    if [[ $2 -eq 0 ]]; then
        pass=$((pass + 1))
    else
        fail=$((fail + 1))
        printf '  FAIL  %s\n' "$1"
    fi
}

if [[ ! -r $functions ]]; then
    echo "FAIL: cannot read $functions" >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# The loop itself, lifted out of configureHttpd().
# ---------------------------------------------------------------------------
snippet=$(awk '
    /^            local relpath$/ { grab = 1 }
    grab { print }
    grab && /^            done < <\(find / { exit }
' "$functions")

if [[ -z $snippet ]]; then
    echo "FAIL: could not find the retired-class-file loop in" >&2
    echo "  lib/common/functions.sh. If it moved or was rewritten, point this" >&2
    echo "  test at it -- do not delete the assertion." >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# Run it against a fixture standing in for one --oldcopy upgrade. $webdirdest
# is the restored backup; $webdirsrc is what the new release ships.
# ---------------------------------------------------------------------------
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

webdirsrc="$work/src"
webdirdest="$work/dest"
error_log="$work/error.log"

mkdir -p "$webdirsrc/lib/fog" "$webdirsrc/lib/router" "$webdirsrc/src/Items"
mkdir -p "$webdirdest/lib/fog" "$webdirdest/lib/router" \
    "$webdirdest/lib/db" "$webdirdest/lib/plugins/site/class"

# What the NEW release ships under lib/. Everything else moved to src/.
: > "$webdirsrc/lib/router/altorouter.class.php"
: > "$webdirsrc/src/Items/Host.php"

# What the pre-wipe backup put back.
echo old > "$webdirdest/lib/router/altorouter.class.php"
# Retired by the PSR-4 move: these are src/Items/Host.php, src/Db/PDODB.php and
# src/Base/System.php now. system.class.php is here rather than in the "still
# shipped" set on purpose -- it is the file the installed FOGUpdater reads, so
# leaving a stale copy behind would let a half-upgraded server keep answering
# with the old version (F-49).
echo stale > "$webdirdest/lib/fog/host.class.php"
echo stale > "$webdirdest/lib/db/pdodb.class.php"
echo stale > "$webdirdest/lib/fog/system.class.php"
# A name with a space, because the loop reads null-delimited for a reason.
echo stale > "$webdirdest/lib/fog/old thing.class.php"
# Generated into lib/fog/ later in configureHttpd(); never in $webdirsrc.
echo generated > "$webdirdest/lib/fog/config.class.php"
# A bundled plugin's own class file, one level deeper.
echo plugin > "$webdirdest/lib/plugins/site/class/site.class.php"

# Wrapped in a function, which is where it really runs (configureHttpd) and
# what makes its `local` declaration legal.
eval "fog_retire_class_files() {
$snippet
}"
fog_retire_class_files

check "a class file the release no longer ships is removed" \
    "$([[ ! -e $webdirdest/lib/fog/host.class.php \
        && ! -e $webdirdest/lib/db/pdodb.class.php \
        && ! -e $webdirdest/lib/fog/system.class.php ]]; echo $?)"

check "a retired name containing a space is removed too" \
    "$([[ ! -e "$webdirdest/lib/fog/old thing.class.php" ]]; echo $?)"

check "a class file the release still ships is left alone" \
    "$([[ -s $webdirdest/lib/router/altorouter.class.php ]]; echo $?)"

check "the generated config.class.php survives" \
    "$([[ -s $webdirdest/lib/fog/config.class.php ]]; echo $?)"

check "a bundled plugin's class file is not this loop's to delete" \
    "$([[ -s $webdirdest/lib/plugins/site/class/site.class.php ]]; echo $?)"

# ---------------------------------------------------------------------------
# The loop has to run on the RESTORED tree, before the new files are laid over
# it. Run it after, and every retired file it was meant to delete has already
# been joined by the new tree -- harmless -- but every file the new release
# ships has also arrived, so the comparison it makes is against a copy of
# itself and the loop becomes a no-op that still reports OK.
# ---------------------------------------------------------------------------
order=$(awk '
    /Copying back old web folder as is/       { back = NR }
    /Removing retired class files/            { retire = NR }
    /Copying new files to web folder/         { new = NR }
    END { print (back && retire && new && back < retire && retire < new) ? "ok" : "wrong" }
' "$functions")
check "retirement runs after the restore and before the new tree is laid" \
    "$([[ $order == ok ]]; echo $?)"

if [[ $fail -gt 0 ]]; then
    printf 'FAIL (%d of %d)\n' "$fail" "$((pass + fail))"
    exit 1
fi
printf 'ok  %d checks passed\n' "$pass"
exit 0
