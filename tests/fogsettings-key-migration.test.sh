#!/bin/bash
#
# .fogsettings is SOURCED, so a key rename is a data migration.
#
# GH-1120 renamed all 79 managed keys to CATEGORY_lower_snake_case. Every
# server upgrading from an older FOG has a .fogsettings full of the OLD names,
# and the only thing that carries those values forward is
# migrateDeprecatedKeys(). Two properties have to hold, and neither is visible
# by reading a diff:
#
#   1. The migration runs BEFORE the first read of a renamed key. Three entry
#      points source .fogsettings and then immediately read one --
#      installfog.sh, updatefog.sh, restorekernel.sh -- so "before" has to be
#      true in three places, not one.
#
#   2. Every deprecated key has somewhere to go. writeUpdateFile() strips the
#      old lines and carries NO value, so a key that is retired without a
#      migration pair is not a rename, it is a deletion -- silently, on the
#      next upgrade, under -y.
#
# THE BUG THIS PINS. The migration was inline in installfog.sh, positioned
# after that script's `case $doupdate` statement. doOSSpecificIncludes is
# called INSIDE that case and cases on ${FOG_os_id}, so it read the key ~40
# lines before anything set it:
#
#     * Performing upgrade using these settings
#       Sorry, answer not recognized
#
# The `*)` arm does not exit -- it blanks FOG_os_id and returns -- so the
# distro config was never sourced and $webdirdest/$tftpdirdst stayed empty.
# The very next guard is
#
#     case $currentdir in *$webdirdest*|*$tftpdirdst*)
#
# and with both empty that is `*`, which matches EVERY directory. So the
# install ALSO refused to run from a path that was perfectly fine. One empty
# variable, two misleading messages, neither naming the cause.
#
# Both halves are fixed. The migration is ordered before its first consumer,
# and that guard now tests each variable for non-emptiness before matching on
# it -- so a failed dispatch produces one accurate complaint instead of two,
# only one of which was ever true. The amplifier was never specific to the key
# rename: ANY path reaching that guard without a sourced distro config hit it,
# which is why it is fixed separately rather than left to the migration.
#
# updatefog.sh and restorekernel.sh had it worse: they guard the call as
# `[[ -n ${FOG_os_id} ]] && doOSSpecificIncludes`, so an empty key SKIPPED the
# distro config with no message at all.
#
# HOW THIS TESTS IT. The installer is not run -- not even partially. Nothing
# is installed, no root is needed and no path outside a temp dir is touched.
# What runs is a *resemblance harness*: the same four steps the real scripts
# perform, in the same order, against fixture .fogsettings files written here.
# The `case` statements are copied in shape from the real ones, so the failure
# mode is reproduced rather than described -- and the test proves it is testing
# the right thing by asserting that skipping the migration brings the dispatch
# failure back. The directory guard is evaluated in BOTH shapes, old and new,
# so the amplifier stays documented as well as fixed.
#
# Usage: bash tests/fogsettings-key-migration.test.sh
# Exit status 0 = pass, 1 = fail.

repodir=$(cd "$(dirname "$0")/.." && pwd)
functions="$repodir/lib/common/functions.sh"

failures=0
checks=0

check() {
    checks=$((checks + 1))
    if [ "$2" != "yes" ]; then
        failures=$((failures + 1))
        echo "  - $1"
    fi
}

is() {
    checks=$((checks + 1))
    if [ "$2" != "$3" ]; then
        failures=$((failures + 1))
        echo "  - $1 (got '$2', wanted '$3')"
    fi
}

if [ ! -f "$functions" ]; then
    echo "SKIP: no lib/common/functions.sh in $repodir"
    exit 0
fi

tmp=$(mktemp -d) || exit 1
trap 'rm -rf "$tmp"' EXIT

# ---------------------------------------------------------------------------
# Fixtures.
#
# Written here rather than copied from a real install: a real .fogsettings
# carries a database password and a storage account password, and a test
# fixture must never be a thing anyone hesitates to paste into a bug report.
# The values below are obviously synthetic for that reason.
# ---------------------------------------------------------------------------

cat > "$tmp/old.fogsettings" <<'EOF'
## Fake pre-GH-1120 .fogsettings. Old key names only.
installtype="N"
osid="1"
osname="Redhat"
interface="eno1"
ipaddress="192.0.2.10"
submask="255.255.255.0"
hostname="fog.example.invalid"
dhcpengine="isc"
dhcpd="dhcpd"
docroot="/var/www/html/"
webroot="/fog/"
webserver="httpd"
httpproto="https"
mysqldbname="fog"
snmysqlhost="localhost"
snmysqluser="fogmaster"
snmysqlpass="not-a-real-password"
storageLocation="/images"
username="fogproject"
password="not-a-real-password-either"
fogprogramdir="/opt/fog"
EOF

cat > "$tmp/new.fogsettings" <<'EOF'
## Fake post-GH-1120 .fogsettings. New key names only.
FOG_install_type="N"
FOG_os_id="1"
FOG_os_name="Redhat"
NET_interface="eno1"
NET_fog_server_ip="192.0.2.10"
NET_subnet_mask="255.255.255.0"
NET_hostname="fog.example.invalid"
DHCP_engine="isc"
DHCP_service_name="dhcpd"
WEB_docroot="/var/www/html/"
WEB_root="/fog/"
WEB_server_service="httpd"
WEB_url_proto="https"
DB_name="fog"
DB_host="localhost"
DB_user="fogmaster"
DB_pass="not-a-real-password"
STORAGE_location="/images"
SVC_user="fogproject"
SVC_pass="not-a-real-password-either"
FOG_program_dir="/opt/fog"
EOF

# ---------------------------------------------------------------------------
# The resemblance harness.
#
# Four steps, the same order the real scripts use:
#   1. source functions.sh          (all three do this first)
#   2. source .fogsettings
#   3. migrateDeprecatedKeys        <- the thing under test
#   4. read a renamed key
#
# Step 3 is skipped when $2 is "skip", which is how the regression is pinned.
#
# Runs in a subshell so each case starts from a clean variable space -- these
# scripts are all global state, and a leaked FOG_os_id would make a broken
# migration look like a working one.
# ---------------------------------------------------------------------------
harness() {
    (
        settings=$1
        mode=${2:-migrate}

        # Only the function is taken from functions.sh. Sourcing the whole file
        # would run its top-level code, which expects a real install.
        eval "$(
            sed -n '/^migrateDeprecatedKeys() {/,/^}$/p' "$functions"
        )"
        if ! declare -f migrateDeprecatedKeys >/dev/null 2>&1; then
            echo "NOFUNC"
            exit 0
        fi

        . "$settings"
        [ "$mode" = "skip" ] || migrateDeprecatedKeys

        # Step 4a: what doOSSpecificIncludes does with FOG_os_id. Same shape as
        # the real case statement -- a recognized id picks a distro, anything
        # else is the "Sorry, answer not recognized" arm.
        case ${FOG_os_id} in
            1) distro="Redhat"; webdirdest="${WEB_docroot}fog/"; tftpdirdst="/tftpboot" ;;
            2) distro="Debian"; webdirdest="${WEB_docroot}fog/"; tftpdirdst="/tftpboot" ;;
            3) distro="Alpine"; webdirdest="${WEB_docroot}fog/"; tftpdirdst="/tftpboot" ;;
            4) distro="Arch";   webdirdest="${WEB_docroot}fog/"; tftpdirdst="/tftpboot" ;;
            *) distro="UNRECOGNIZED"; webdirdest=""; tftpdirdst="" ;;
        esac

        # Step 4b: the directory guard that runs immediately after it, in BOTH
        # shapes, because the difference between them is the second half of
        # this bug.
        #
        #   guard_glob  the original `case $currentdir in *$webdirdest*`. An
        #               empty $webdirdest makes that `**`, which matches every
        #               path -- so a failed dispatch was AMPLIFIED into a
        #               second, unrelated-sounding complaint about the install
        #               directory.
        #   guard_safe  the current form, which tests each variable for
        #               non-emptiness before using it in a match.
        currentdir="/home/someone/fogproject/bin"
        case $currentdir in
            *$webdirdest*|*$tftpdirdst*) guard_glob="REJECTED" ;;
            *)                           guard_glob="allowed"  ;;
        esac
        if { [ -n "$webdirdest" ] && case $currentdir in *"$webdirdest"*) true ;; *) false ;; esac; } \
            || { [ -n "$tftpdirdst" ] && case $currentdir in *"$tftpdirdst"*) true ;; *) false ;; esac; }; then
            guard_safe="REJECTED"
        else
            guard_safe="allowed"
        fi

        echo "${distro}|${guard_safe}|${FOG_os_id}|${FOG_os_name}|${NET_interface}|${DB_user}|${WEB_docroot}|${guard_glob}"
    )
}

# ---------------------------------------------------------------------------
# 1. An old-format .fogsettings upgrades cleanly.
# ---------------------------------------------------------------------------

out=$(harness "$tmp/old.fogsettings")
if [ "$out" = "NOFUNC" ]; then
    echo "FAIL: migrateDeprecatedKeys() is not defined in lib/common/functions.sh"
    exit 1
fi
IFS='|' read -r distro guard osid osname iface dbuser docroot guard_glob <<EOF
$out
EOF

is  'old format: the distro is recognized'          "$distro" "Redhat"
is  'old format: FOG_os_id migrates from $osid'     "$osid"   "1"
is  'old format: FOG_os_name migrates from $osname' "$osname" "Redhat"
is  'old format: NET_interface migrates'            "$iface"  "eno1"
is  'old format: DB_user migrates from $snmysqluser' "$dbuser" "fogmaster"
is  'old format: WEB_docroot migrates from $docroot' "$docroot" "/var/www/html/"
# The second symptom, and the one nobody would connect to a key rename.
is  'old format: an unrelated directory is not rejected' "$guard" "allowed"

# ---------------------------------------------------------------------------
# 2. A new-format .fogsettings is untouched.
#
# The migration only ever fills an EMPTY new key, so a server that has already
# upgraded once must come out identical -- and in particular must not have a
# value replaced by an empty old variable.
# ---------------------------------------------------------------------------

IFS='|' read -r distro guard osid osname iface dbuser docroot guard_glob <<EOF
$(harness "$tmp/new.fogsettings")
EOF

is  'new format: the distro is recognized'  "$distro" "Redhat"
is  'new format: FOG_os_id is untouched'    "$osid"   "1"
is  'new format: DB_user is untouched'      "$dbuser" "fogmaster"
is  'new format: WEB_docroot is untouched'  "$docroot" "/var/www/html/"
is  'new format: an unrelated directory is not rejected' "$guard" "allowed"

# ---------------------------------------------------------------------------
# 3. The regression, reproduced.
#
# Without the migration an old-format file must produce BOTH symptoms. If this
# ever passes, the harness has stopped exercising the thing it claims to.
# ---------------------------------------------------------------------------

IFS='|' read -r distro guard osid _ _ _ _ guard_glob <<EOF
$(harness "$tmp/old.fogsettings" skip)
EOF

is  'without the migration: FOG_os_id is empty'             "$osid"   ""
is  'without the migration: the distro falls to the *) arm' "$distro" "UNRECOGNIZED"
# The amplifier, and why it is worth a fix of its own. With the dispatch
# failed, $webdirdest is empty -- and the ORIGINAL glob then rejected every
# directory on earth, which is the second message nobody could connect to a
# key rename. The guarded form does not, so a failed dispatch now produces one
# accurate complaint instead of two, only one of which was true.
is  'the old glob rejects every directory once webdirdest is empty' "$guard_glob" "REJECTED"
is  'the guarded form does not'                                     "$guard"      "allowed"

# ---------------------------------------------------------------------------
# 4. The migration runs before the first read, in every entry point.
#
# The property that actually prevents a recurrence. A source lint, and honest
# about being one -- the harness above can only prove the function works, not
# that a given script calls it early enough.
#
# The consumer is doOSSpecificIncludes, which cases on ${FOG_os_id} -- not
# merely the first line that MENTIONS the key. installfog.sh defaults
# FOG_os_id to "" near its top, long before .fogsettings is read, and that is
# an initialization rather than a read of a migrated value. Anchoring on the
# real consumer keeps this lint precise instead of merely strict.
# ---------------------------------------------------------------------------

for script in installfog.sh updatefog.sh restorekernel.sh; do
    path="$repodir/bin/$script"
    [ -f "$path" ] || continue

    # installfog.sh sources it via $fogpriorconfig; the other two name the path.
    srcline=$(grep -nE '^[[:space:]]*\.[[:space:]]+"?\$\{?(fogpriorconfig|fogprogramdir)' "$path" \
        | head -1 | cut -d: -f1)
    callline=$(grep -n '^[[:space:]]*migrateDeprecatedKeys[[:space:]]*$' "$path" \
        | head -1 | cut -d: -f1)
    useline=$(grep -nE '^[[:space:]]*[^#]*\bdoOSSpecificIncludes\b' "$path" \
        | head -1 | cut -d: -f1)

    check "$script sources .fogsettings"          "$([ -n "$srcline" ] && echo yes)"
    check "$script calls migrateDeprecatedKeys"   "$([ -n "$callline" ] && echo yes)"

    if [ -n "$srcline" ] && [ -n "$callline" ]; then
        check "$script migrates after sourcing .fogsettings" \
            "$([ "$callline" -gt "$srcline" ] && echo yes)"
    fi
    if [ -n "$callline" ] && [ -n "$useline" ]; then
        check "$script migrates BEFORE doOSSpecificIncludes reads FOG_os_id" \
            "$([ "$callline" -lt "$useline" ] && echo yes)"
    fi
done

# The function has to be defined somewhere every caller has already sourced.
# It used to live inline in installfog.sh, which is precisely why the other two
# scripts had no migration at all.
check 'the migration lives in functions.sh, which all three source first' \
    "$(grep -q '^migrateDeprecatedKeys() {' "$functions" && echo yes)"

# And the REAL guard, not the harness' copy of it.
#
# Without this the two guard_glob/guard_safe cases above are decorative: they
# prove the two SHAPES differ, which is arithmetic, not that the shipped code
# uses the safe one. Reverting functions.sh to the unguarded glob passed every
# other check in this file.
# Comments are stripped first -- the fix carries an explanation that QUOTES the
# broken form, and grepping the whole function found that instead of code.
guardcode=$(sed -n '/^doOSSpecificIncludes() {/,/^}$/p' "$functions" \
    | grep -vE '^[[:space:]]*#')
check 'the real directory guard does not glob on a possibly-empty $webdirdest' \
    "$(printf '%s\n' "$guardcode" | grep -q '\*\$webdirdest\*' || echo yes)"
check 'the real directory guard tests $webdirdest for non-emptiness first' \
    "$(printf '%s\n' "$guardcode" | grep -q -- '-n \$webdirdest' && echo yes)"

# ---------------------------------------------------------------------------
# 5. Every deprecated key has a migration pair.
#
# writeUpdateFile() strips the old lines and carries no value, so a key on that
# list with nowhere to go is a silent deletion rather than a rename. Retired
# keys are exempt -- they are listed under the "Retired outright" comment
# because the information is now derived -- so the exemption is read from the
# list itself rather than restated here, and a key moved between the two
# sections needs no edit to this test.
# ---------------------------------------------------------------------------

migbody=$(sed -n '/^migrateDeprecatedKeys() {/,/^}$/p' "$functions")
deplist=$(sed -n '/local -a deprecatedKeys=(/,/^[[:space:]]*)/p' "$functions")

# The list is grouped by comment, and two of those groups are deliberately
# unmigrated: keys retired BEFORE GH-1120, and keys GH-1120 retired outright
# because the information is now derived. Only the "-> PREFIX_*" groups have
# to have a pair. Reading the grouping from the list itself means a key moved
# between sections needs no edit here.
active=$(printf '%s\n' "$deplist" | awk '
    /retired earlier/  { want = 0; next }
    /Retired outright/ { want = 0; next }
    /-> [A-Z]/         { want = 1; next }
    /^[[:space:]]*#/   { next }
    want { print }
' | grep -oE '\b[a-z][A-Za-z0-9_]{2,}\b' | sort -u)

# Keys deliberately dropped rather than migrated, because a twin already
# carries the value. Listed here rather than inferred from the prose in
# functions.sh, and that is the point: dropping a key is then something
# somebody has to come here and write down, instead of something that happens
# by omission. A future rename that loses a value fails this check.
#
#   dodhcp        Y/N twin of bldhcp, both written from the same prompt.
#                 DHCP_enabled seeds from bldhcp because every DECISION read
#                 that one; dodhcp was only ever echoed back.
#   extcacert     \
#   extcakey       > extc*/webExtCA* were six keys holding three values.
#   webExtCACert   / PKI_web_ca_cert/_key take $sslcapem/$sslcakey, which held
#   webExtCAKey   /  the same content; only the imported ROOT is separately
#                    value-carrying, and that one IS migrated
#                    (PKI_web_external_root_cert).
merged="dodhcp extcacert extcakey webExtCACert webExtCAKey"

# Keys can be referenced as $key or ${key:-default} -- webCertFile is folded
# into the vhost pair as ${webCertFile:-$sslpubcert}, so a bare "\$key\b"
# pattern misses it and reports a false gap.
missing=""
for key in $active; do
    case " $merged " in *" $key "*) continue ;; esac
    printf '%s\n' "$migbody" | grep -qE "\\\$\{?${key}\b" || missing="$missing $key"
done

# The exemptions must still BE deprecated keys. A merged key that gets deleted
# from deprecatedKeys stops being stripped from .fogsettings, so the old line
# survives every upgrade and quietly shadows nothing -- harmless, but it means
# this list has drifted from reality and the next reader cannot trust it.
stale=""
for key in $merged; do
    printf '%s\n' "$deplist" | grep -qE "\b${key}\b" || stale="$stale $key"
done
check "every merged-key exemption is still a deprecated key (stale:${stale:- none})" \
    "$([ -z "$stale" ] && echo yes)"

check "every non-retired deprecated key is migrated (missing:${missing:- none})" \
    "$([ -z "$missing" ] && echo yes)"

# ---------------------------------------------------------------------------

if [ "$failures" -gt 0 ]; then
    echo "FAIL ($failures of $checks)"
    exit 1
fi

echo "ok  $checks checks passed"
exit 0
