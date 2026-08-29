#!/bin/bash
#
# Guards the Alpine (osid 3) service wiring in the installer.
#
#   tests/alpine-openrc-services.test.sh
#
# Alpine is the only supported host OS that is not systemd, and it is not
# sysvinit either -- it is OpenRC, where the commands are rc-update and
# rc-service. chkconfig, sysv-rc-conf, insserv and `service` are all absent.
#
# What went wrong without a test here (#863). Every one of these blocks is
# shaped `case ${FOG_os_id} in 1) ... 2) ... esac`, followed by `errorStat $?`. A
# `case` that matches no arm exits 0, so errorStat printed OK -- for a step
# that had not run at all. An Alpine install therefore reported FTP, RPCBind,
# NFS and DHCP as "started" while none of the four had been touched, and
# reported nine FOG daemons as "enabled" when nothing had ever run rc-update
# for them. The failures surfaced much later and a long way from the cause: a
# capture that cannot upload, a deploy that cannot mount, a PXE client that
# gets no lease, a server that comes back from a reboot serving the web UI
# with no scheduler.
#
# The properties pinned here:
#
#   A-C  enableInitScript() enrolls every FOG daemon with rc-update, into a
#        NAMED runlevel, and does not reach for chkconfig/sysv-rc-conf.
#   D-G  the four service blocks each carry an osid 3 arm. Structural rather
#        than behavioral: they live inside functions that rewrite /etc, so
#        running them here would mean writing to the developer's own box.
#   H    TFTP's existing osid 3 arm enables as well as starts -- it started
#        in.tftpd and never enrolled it, so PXE worked until the first reboot.
#   I-J  the MariaDB SERVER resolves on Alpine. Alpine is the one distro that
#        ships both "mariadb" (the server) and "mariadb-client", and bare
#        "mariadb" is matched by installPackages()'s CLIENT arm because that
#        is what it means on Fedora. The Alpine package list named "mariadb",
#        it resolved to mariadb-client, the server was never installed, and
#        the install died at "Setting up and starting MySQL" on an empty
#        datadir. This is the one that made Alpine unusable outright.
#
# Runs entirely on stubs -- no install, no network, no root, no database.
#
# Exit status 0 = pass or skip, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"
COMMONCFG="$REPO/lib/common/config.sh"
ALPINECFG="$REPO/lib/alpine/config.sh"

for f in "$FUNCS" "$COMMONCFG" "$ALPINECFG"; do
    [[ -f $f ]] || { echo "ERROR: $f not found" >&2; exit 1; }
done

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
PASS=0; FAIL=0
ok()    { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad()   { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
check() { [[ $1 == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }
has()   { [[ $1 == *"$2"* ]] && ok "$3" || bad "$3 (not found in '$1')"; }
hasnt() { [[ $1 != *"$2"* ]] && ok "$3" || bad "$3 (unexpectedly present in '$1')"; }

# ---------------------------------------------------------------------------
# A-C: enableInitScript() under OpenRC, run for real against stubs.
# ---------------------------------------------------------------------------
# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1

CALLS="$WORK/calls"
: > "$CALLS"
rc-update()    { echo "rc-update $*"    >> "$CALLS"; }
chkconfig()    { echo "chkconfig $*"    >> "$CALLS"; }
sysv-rc-conf() { echo "sysv-rc-conf $*" >> "$CALLS"; }
insserv()      { echo "insserv $*"      >> "$CALLS"; }
service()      { echo "service $*"      >> "$CALLS"; }
systemctl()    { echo "systemctl $*"    >> "$CALLS"; }
dots()         { :; }
errorStat()    { :; }

FOG_os_id=3
systemctl_cmd=""            # not used; kept out of the way of $systemctl below
systemctl=no
error_log="$WORK/error.log"
initdpath="$WORK/initd"
serviceList="FOGImageReplicator FOGScheduler FOGPluginRunner"
mkdir -p "$initdpath"
for s in $serviceList; do : > "$initdpath/$s"; done

enableInitScript >/dev/null 2>&1
calls="$(cat "$CALLS")"

missing=""
for s in $serviceList; do
    [[ $calls == *"rc-update add $s "* ]] || missing="$missing $s"
done
check "$missing" "" "A. every FOG daemon is enrolled with rc-update"

# B. the runlevel is named. `rc-update add foo` with no runlevel uses whatever
# runlevel the installer happens to be running in, which is not necessarily
# one that comes up at boot.
runlevelless=0
while read -r line; do
    [[ $line == rc-update\ add\ * ]] || continue
    set -- $line
    [[ $# -eq 4 ]] || runlevelless=1
done <<< "$calls"
check "$runlevelless" "0" "B. rc-update names the runlevel explicitly"

hasnt "$calls" "chkconfig"    "C1. chkconfig is not used on Alpine"
hasnt "$calls" "sysv-rc-conf" "C2. sysv-rc-conf is not used on Alpine"

# ---------------------------------------------------------------------------
# D-H: the per-service blocks carry an osid 3 arm.
#
# armsIn <anchor> prints the arm labels of the `case ${FOG_os_id} in` block that
# contains <anchor>, so this survives the blocks moving around the file.
# ---------------------------------------------------------------------------
# blockInfo <anchor> <what> -- <what> is "arms" for the arm labels of the
# `case ${FOG_os_id} in` block containing <anchor>, or "arm3" for the body of its
# osid 3 arm. Anchored on content rather than line numbers so this keeps
# working when the blocks move, and it counts NESTED case/esac (enableInitScript
# has a `case $linuxReleaseName_lower in` inside it).
blockInfo() {
    awk -v anchor="$1" -v what="$2" '
        function isCaseOpen(l) { return (l ~ /[ \t]case[ \t].*[ \t]in[ \t]*$/ || l ~ /^[ \t]*case[ \t].*[ \t]in[ \t]*$/) }
        {
            if (!want) {
                if ($0 ~ /case[ \t]+\${FOG_os_id}[ \t]+in/) {
                    want = 1; d = 1; body = $0 "\n"; arms = ""; arm3 = ""; in3 = 0
                }
                next
            }
            body = body $0 "\n"
            if (in3) arm3 = arm3 $0 "\n"
            if (d == 1 && $0 ~ /^[ \t]*[0-9|*]+\)/) {
                a = $0; sub(/^[ \t]*/, "", a); sub(/\).*/, "", a)
                arms = arms a " "
                if (a == "3") in3 = 1
            }
            if (d == 1 && in3 && $0 ~ /^[ \t]*;;[ \t]*$/) in3 = 0
            if (isCaseOpen($0)) { d++; next }
            if ($0 ~ /^[ \t]*esac/) {
                d--
                if (d == 0) {
                    if (index(body, anchor) > 0) { print (what == "arms" ? arms : arm3); exit }
                    want = 0
                }
            }
        }
    ' "$FUNCS"
}
armsIn() { blockInfo "$1" arms; }
armHas() { [[ $(blockInfo "$1" arm3) == *"$2"* ]] && echo yes || echo no; }

has "$(armsIn 'chkconfig $serviceItem on')" "3" "D. enableInitScript has an osid 3 arm"
check "$(armHas 'chkconfig $serviceItem on' 'rc-update add')" "yes" \
      "D2. ...and it uses rc-update"

has "$(armsIn 'chkconfig vsftpd on')" "3" "E. the vsftpd block has an osid 3 arm"
check "$(armHas 'chkconfig vsftpd on' 'rc-update add vsftpd')" "yes" \
      "E2. ...and it enables vsftpd on boot"

has "$(armsIn 'chkconfig rpcbind on')" "3" "F. the rpcbind block has an osid 3 arm"
check "$(armHas 'chkconfig rpcbind on' 'rc-update add rpcbind')" "yes" \
      "F2. ...and it enables rpcbind on boot"

has "$(armsIn 'nfs-kernel-server')" "3" "G. the NFS block has an osid 3 arm"
check "$(armHas 'nfs-kernel-server' 'rc-update add $nfsItem')" "yes" \
      "G2. ...and it enables the NFS server on boot"

has "$(armsIn 'chkconfig ${DHCP_service_name} on')" "3" "H. the DHCP block has an osid 3 arm"
check "$(armHas 'chkconfig ${DHCP_service_name} on' 'rc-update add ${DHCP_service_name}')" "yes" \
      "H2. ...and it enables the DHCP server on boot"

# TFTP is an if/elif chain, not a case, so anchor on the arm itself.
tftparm="$(sed -n '/elif \[\[ \${FOG_os_id} -eq 3 \]\]; then/,/^[ \t]*else/p' "$FUNCS")"
has "$tftparm" "rc-update add in.tftpd" "I. TFTP is enabled on boot, not only started"

# `service` and the dotted fpm name are both wrong on Alpine.
# Comment lines are excluded -- the fix documents the call it replaced.
livecalls="$(grep -n 'service php-fpm\${WEB_php_version}' "$FUNCS" | grep -vE '^[0-9]+: *#')"
check "$livecalls" "" "J. no live service php-fpm\${WEB_php_version} call (wrong command, wrong name on Alpine)"

# ---------------------------------------------------------------------------
# Two nginx defects found on Alpine that were never Alpine-specific.
# ---------------------------------------------------------------------------
# Alpine's stock nginx.conf declares `ssl_session_cache shared:SSL:2m` in the
# http block. nginx refuses to start when one zone name carries two sizes, so
# a FOG vhost naming the generic zone took the web server down outright.
zonelines="$(grep -c 'ssl_session_cache shared:FOGSSL:' "$FUNCS")"
check "$zonelines" "2" "M. both nginx vhost arms use a FOG-specific session zone"
check "$(grep -c 'ssl_session_cache shared:SSL:' "$FUNCS")" "0" \
      "M2. ...and neither squats on the generic SSL zone"

# `nginx -t` had a diffconfig between it and errorStat, so errorStat read the
# WRONG command's status and a rejected vhost reported OK.
nginxtest="$(grep -A 15 'dots "Testing nginx configuration"' "$FUNCS")"
has "$nginxtest" "local nginxtest=\$?" "N. nginx -t's own status is captured"
has "$nginxtest" "errorStat \$nginxtest" "N2. ...and that is what errorStat reports"

# ---------------------------------------------------------------------------
# K-L: the MariaDB server resolves on Alpine.
# ---------------------------------------------------------------------------
# What Alpine 3.20+ actually carries. "mariadb" is the server here; there is
# no mariadb-server, no mysql-server and no galera package.
alpinePkgs="mariadb mariadb-client mariadb-common mariadb-openrc nginx vsftpd"
pkgAvailableKnown=1
pkgAvailableSet=()
for p in $alpinePkgs; do pkgAvailableSet[$p]=1; done

# Source the shared config for the real $sqlserverlist / $sqlclientlist. It is
# guarded with [[ -z ]] throughout, so a subshell keeps it out of the way of
# anything set above.
sqlserverlist=""; sqlclientlist=""
eval "$(grep -E '^\[\[ -z \$sql(server|client)list \]\]' "$COMMONCFG" | sed 's/^\[\[ -z [^]]*\]\] && //')"

check "$(pkgFirstAvailable $sqlserverlist)" "mariadb" \
      "K. the server slot resolves to Alpine's mariadb package"

# And the Alpine package list has to route through that slot rather than
# naming "mariadb" directly, which installPackages() reads as a CLIENT.
alpinelist="$(grep -E '^ *FOG_packages="bash ' "$ALPINECFG")"
has   "$alpinelist" "mariadb-server" "L1. the Alpine package list names the server slot"
hasnt "$alpinelist" " mariadb "      "L2. ...and not bare mariadb, which maps to the client"

# ---------------------------------------------------------------------------
# O: php.ini extension handling is Arch-only.
# ---------------------------------------------------------------------------
# Alpine enables PHP modules with drop-ins under /etc/php8x/conf.d. Its php.ini
# is the ordinary upstream one, so uncommenting the ";extension=" lines there
# switched on ftp and zip (not installed) and loaded mysqli/pdo_mysql BEFORE
# conf.d/01_mysqlnd.ini -- both then failed to relocate and FOG had no database
# driver. The guard must name osid 4 alone.
extguard="$(grep -B 1 "sed -i 's/;extension=bcmath/" "$FUNCS" | head -1)"
has   "$extguard" 'FOG_os_id} -eq 4' "O1. the ;extension= block is guarded on Arch"
hasnt "$extguard" 'osid -eq 3' "O2. ...and no longer runs on Alpine"

# open_basedir is a plain setting rather than an extension, and Alpine's
# php.ini carries it too, so that one line still applies to both.
obguard="$(grep -B 1 "sed -i 's/\^open_basedir" "$FUNCS" | head -1)"
has "$obguard" 'FOG_os_id} -eq 3' "O3. open_basedir is still handled on Alpine"

# ---------------------------------------------------------------------------
# P: the shipped OpenRC init scripts are actually runnable.
# ---------------------------------------------------------------------------
# The interpreter is /sbin/openrc-run on Alpine -- that is where the package
# puts it and what every one of Alpine's own init scripts names. These shipped
# eight naming /bin/openrc-run, which does not exist, so every one of them died
# with "bad interpreter" the moment the installer tried to start it. The ninth,
# FOGImageReplicator, carried a BLANK LINE above its shebang, which means it
# had no shebang at all and was run by /bin/sh instead. See #863.
INITD="$REPO/packages/init.d/alpine"
if [[ ! -d $INITD ]]; then
    bad "P. packages/init.d/alpine is missing"
else
    badshebang=""
    for f in "$INITD"/*; do
        [[ -f $f ]] || continue
        [[ $(head -1 "$f") == "#!/sbin/openrc-run" ]] || badshebang="$badshebang $(basename "$f")"
    done
    check "$badshebang" "" "P. every Alpine init script starts with #!/sbin/openrc-run"
    # Counted against packages/service rather than against a literal. The
    # property is "every daemon ships an OpenRC script", and a hardcoded
    # number states it only until the next daemon is added -- at which point
    # the test fails for the daemon that DID ship one, and the fix looks like
    # bumping a constant. FOGRetentionRunner was the tenth and made that
    # concrete. Q below is the converse check: every script names a service
    # directory that exists.
    check "$(ls -1 "$INITD" | wc -l | tr -d ' ')" \
        "$(ls -1d "$REPO"/packages/service/FOG* | wc -l | tr -d ' ')" \
        "P2. every daemon under packages/service ships an OpenRC script"
fi

# ---------------------------------------------------------------------------
# Q-S: the init scripts can actually reach the daemon they name.
# ---------------------------------------------------------------------------
# Q. `command=/opt/fog/service/$name/$name` assumed the service name and the
#    code directory were the same string. They are not: FOGScheduler's code
#    lives in FOGTaskScheduler, so start-stop-daemon reported
#    "/opt/fog/service/FOGScheduler/FOGScheduler does not exist". Every path
#    must name a directory that exists under packages/service.
SVCDIR="$REPO/packages/service"
missingdaemon=""
for f in "$INITD"/*; do
    [[ -f $f ]] || continue
    args=$(grep -m1 '^command_args=' "$f" | sed 's/^command_args="//; s/"$//')
    dir=$(basename "$(dirname "$args")")
    [[ -d "$SVCDIR/$dir" ]] || missingdaemon="$missingdaemon $(basename "$f"):$dir"
done
check "$missingdaemon" "" "Q. every init script names a service directory that exists"

# R. The interpreter is a placeholder, not a hard-coded path. Alpine has no
#    unversioned "php", so neither the daemons' own "#!/usr/bin/php -q" nor
#    systemd's "/usr/bin/env php" can work there.
badinterp=""
for f in "$INITD"/*; do
    [[ -f $f ]] || continue
    grep -q '^command=FOGPHPBIN$' "$f" || badinterp="$badinterp $(basename "$f")"
done
check "$badinterp" "" "R. every init script defers the php binary to FOGPHPBIN"

# S. ...and something resolves it. A placeholder nothing substitutes is worse
#    than a wrong path, because the failure names a binary that never existed.
installinit="$(sed -n '/^installInitScript()/,/^}/p' "$FUNCS")"
has "$installinit" 'sed -i "s|FOGPHPBIN|' "S. installInitScript rewrites FOGPHPBIN"
has "$installinit" 'command -v "php${php_apk}"' "S2. ...to Alpine's versioned php binary"

# T. The pidfile directory. /var/run is a tmpfs, so it is empty on every boot
#    and nothing else in FOG creates this path -- the systemd units use no
#    pidfile at all. Without it start-stop-daemon has nowhere to write, and the
#    daemons read as "crashed" from the first reboot onward while rc-update
#    showed them correctly enabled: the exact symptom this issue is about,
#    surviving the fix for it.
nopidpath=""
for f in "$INITD"/*; do
    [[ -f $f ]] || continue
    piddir=$(dirname "$(grep -m1 '^pidfile=' "$f" | cut -d= -f2-)")
    grep -q "checkpath .*$piddir" "$f" || nopidpath="$nopidpath $(basename "$f")"
done
check "$nopidpath" "" "T. every init script creates its own pidfile directory"

# U. External reporting. Alpine has no /etc/cron.d -- busybox crond reads
#    per-user tables under /etc/crontabs, and those carry no user column.
reporting="$(sed -n '/^setupFogReporting()/,/^}/p' "$FUNCS")"
has "$reporting" 'crondfile="/etc/crontabs/' "U. reporting writes a crontab Alpine can read"
has "$reporting" "rc-update add crond" "U2. ...and enables the daemon that reads it"

# U3. And it APPENDS. /etc/crontabs/root is the host's file -- Alpine ships it
#     carrying busybox's run-parts entries for /etc/periodic/* -- unlike the
#     cron.d file on the other distros, which is FOG's own. Writing it whole
#     deletes every scheduled job the machine had.
alpinearm="$(printf '%s\n' "$reporting" | sed -n '/FOG_os_id} -eq 3/,/^    else/p')"
has   "$alpinearm" '>> "${crondfile}"'  "U3. the FOG block is appended to the host crontab"
hasnt "$alpinearm" 'cat > ${crondfile}' "U3b. ...and the host's own entries are not overwritten"
has   "$alpinearm" "FOG_MANAGED_BEGIN"  "U4. the block is marker-delimited, so a re-run replaces it"

# V. Supervision. The daemons exit when the database is not yet accepting
#    connections -- FOG's bootstrap fails before the service's own
#    waitDbReady() is reached. The systemd units cover that with
#    Restart=always / RestartSec=1 / StartLimitIntervalSec=0, so on systemd
#    nobody ever sees it. OpenRC supervises nothing by default, so at boot
#    every FOG daemon died within a second of mariadb still starting and
#    stayed dead -- while starting one by hand afterwards worked, which is
#    what made this read as an rc-update problem rather than a restart one.
nosupervise=""; nodborder=""
for f in "$INITD"/*; do
    [[ -f $f ]] || continue
    grep -q '^supervisor=supervise-daemon$' "$f" || nosupervise="$nosupervise $(basename "$f")"
    grep -q 'after mariadb' "$f" || nodborder="$nodborder $(basename "$f")"
done
check "$nosupervise" "" "V. every init script is supervised, so it is restarted on exit"
check "$nodborder" "" "V2. ...and ordered after the database"

# V3. respawn_max=0 is unlimited. A bounded respawn would give up during a
#     slow database start, which is the whole case this exists for.
check "$(grep -h '^respawn_max=' "$INITD"/* | sort -u)" "respawn_max=0" \
      "V3. the respawn count is unlimited"

# V4. command_background and a supervisor are mutually exclusive in OpenRC.
check "$(grep -l '^command_background' "$INITD"/* 2>/dev/null | wc -l | tr -d ' ')" "0" \
      "V4. no script mixes command_background with a supervisor"

# W. The mode bit. installInitScript uses `cp -f`, which preserves the source's
#    permissions, and OpenRC will not run a non-executable init script. This
#    repo sets core.fileMode=false, so a chmod made locally is invisible to git
#    and a file that went in 644 stays 644 for everyone who clones -- which is
#    how FOGFileDeleter shipped unexecutable while working fine on the box it
#    was written on.
notexec="$(cd "$INITD/../../.." && git ls-files -s packages/init.d/alpine \
    | awk '$1 != "100755" { print $4 }')"
check "$notexec" "" "W. every init script is committed executable"

echo
echo "  $PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
