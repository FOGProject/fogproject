#!/bin/bash
#
# The PKI tree lives at /etc/fog/pki, and the move to it is safe to interrupt.
#
#   tests/pki-tree-relocation.test.sh
#
# FOG's four-zone PKI used to sit at $fogprogramdir/pki -- /opt/fog/pki on a
# default install. Keys and certificates are configuration, so they belong in
# /etc, which is what a backup policy and a config-management run already
# capture; /opt/<pkg> is for a package's own static files. /etc/fog is not new
# (GH-850 makes it a real directory on every install, for fog.conf), so the
# move needs no per-distro branch.
#
# Two properties have to hold, and both are load-bearing:
#
#   (a) $fogprogramdir/pki KEEPS RESOLVING, as a symlink. It is a published
#       path -- PKI_ZONES.md, MULTI_SERVER_CA.md and
#       EXTERNAL_CA_AND_LETSENCRYPT.md name /opt/fog/pki/..., an admin's
#       renewal cron names /opt/fog/pki/renewal-helper, and a .fogsettings
#       written before this change records canonical paths underneath it.
#
#   (b) The move is COPY, then remove only if the copy succeeded -- never mv. /opt and /etc are
#       frequently separate mounts, so mv degrades to copy-then-unlink anyway.
#       Doing it in explicit steps means a failed copy leaves the SOURCE
#       authoritative rather than half a tree on each side, and it is what
#       makes overwriting the source key blocks possible at all.
#
# The stake on getting this wrong is the whole estate: a zone accessor that
# answers /etc/fog/pki while the material still sits under /opt/fog/pki reads
# as "no CA yet", mints a fresh root, and every fog-client stops trusting the
# server. That is why _pkiRootDir() drives the migration itself rather than
# relying on a call placed early enough in installfog.sh.
#
# MUTATION-VERIFIED -- every guard below was removed in turn and this file
# went red for it:
#
#   dropped the trailing `ln -s`                     -> 5 red (the compat path)
#   `cp -a` -> `cp -r`                               -> 1 red (timestamps)
#   dropped `cp ... || return 1`                     -> 2 red (case E)
#   dropped `[[ -L $legacy ]] && return 0`           -> 1 red (case C)
#   dropped `[[ $legacy == "$target" ]] && return 0` -> 1 red (case F)
#   dropped `[[ -z ${fogprogramdir} ]] && return 0`  -> 1 red (case G)
#   _pkiZoneDir echoes ${fogprogramdir}/pki/$1 again -> 5 red (case B)
#   _pkiConfDir likewise                             -> 1 red (case B)
#   the default reverts to ${fogprogramdir}/pki      -> 1 red (case G)
#
# The first cut of this file could not see four of those: three guards were
# duplicated between _pkiRootDir and _migratePkiTree, so removing either copy
# was invisible, and the mode assertions could not tell `cp -a` from `cp -r`
# because an ordinary umask strips nothing off 0600 or 0700. The duplication is
# gone and the timestamp check is what separates the two copies.
#
# Runs on generated fixtures -- no install, no network, no root, and never
# touches the host's own /etc/fog.
#
# Exit status 0 = pass, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"

[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }

WORK="$(mktemp -d)"
trap 'chmod -R u+rwX "$WORK" 2>/dev/null; rm -rf "$WORK"' EXIT

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
is()  { [[ "$1" == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }

error_log="$WORK/error.log"
: > "$error_log"
# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1
dots() { :; }
errorStat() { :; }

# Every case gets its own $fogprogramdir and its own target, so nothing here
# can reach the host's /etc/fog -- which _migratePkiTree would otherwise CREATE.
newcase() {
    CASE="$WORK/$1"
    fogprogramdir="$CASE/opt/fog"
    TARGET="$CASE/etc/fog/pki"
    PKI_root_dir="$TARGET"
    mkdir -p "$fogprogramdir"
}

# A stand-in for the four zones, with a private key whose mode has to survive.
seedLegacyTree() {
    mkdir -p "$fogprogramdir/pki/root/ca" "$fogprogramdir/pki/web/leaf" \
        "$fogprogramdir/pki/secureboot/ca" "$fogprogramdir/pki/conf"
    echo "root-ca-key" > "$fogprogramdir/pki/root/ca/.fogCA.key"
    chmod 0600 "$fogprogramdir/pki/root/ca/.fogCA.key"
    chmod 0700 "$fogprogramdir/pki/root/ca"
    echo "web-leaf" > "$fogprogramdir/pki/web/leaf/.webLeaf.pem"
    echo "sb-ca" > "$fogprogramdir/pki/secureboot/ca/.fogSBCA.pem"
    # A distinctive mtime. Modes survive a plain `cp -r` under an ordinary
    # umask, so they cannot tell -a from -r on their own; timestamps can, and
    # -a is what also carries ownership, which an unprivileged test cannot see.
    touch -d '2020-01-02 03:04:05' "$fogprogramdir/pki/secureboot/ca/.fogSBCA.pem"
    printf 'basicConstraints=CA:FALSE\n' > "$fogprogramdir/pki/conf/ca.cnf"
    echo "#!/bin/sh" > "$fogprogramdir/pki/renewal-helper"
    chmod 0755 "$fogprogramdir/pki/renewal-helper"
}

echo "== an existing tree is moved, and the old name keeps resolving =="
newcase moved
seedLegacyTree
_migratePkiTree "$TARGET"

is "$(cat "$TARGET/root/ca/.fogCA.key" 2>/dev/null)" "root-ca-key" \
    "A: the root CA key arrived at the new tree"
is "$(cat "$TARGET/web/leaf/.webLeaf.pem" 2>/dev/null)" "web-leaf" \
    "A: the web leaf arrived"
is "$(cat "$TARGET/conf/ca.cnf" 2>/dev/null)" "basicConstraints=CA:FALSE" \
    "A: the shared openssl config arrived"
# cp -a, not cp: a 0600 key that lands 0644 under /etc is the move handing the
# key to every local account, and nothing would report it.
is "$(stat -c %a "$TARGET/root/ca/.fogCA.key" 2>/dev/null)" "600" \
    "A: the private key kept its mode"
is "$(stat -c %a "$TARGET/root/ca" 2>/dev/null)" "700" \
    "A: the root CA directory kept its mode"
is "$(stat -c %a "$TARGET/renewal-helper" 2>/dev/null)" "755" \
    "A: the renewal helper is still executable"
is "$(stat -c %y "$TARGET/secureboot/ca/.fogSBCA.pem" 2>/dev/null | cut -d. -f1)" \
    "2020-01-02 03:04:05" \
    "A: the copy is an archive copy, so timestamps came too"

# (a) the compat contract. Every published path is written against this name.
[[ -L "$fogprogramdir/pki" ]] \
    && ok "A: the historic name is now a symlink" \
    || bad "A: the historic name is now a symlink"
is "$(readlink "$fogprogramdir/pki")" "$TARGET" \
    "A: it points at the new tree"
is "$(cat "$fogprogramdir/pki/web/leaf/.webLeaf.pem" 2>/dev/null)" "web-leaf" \
    "A: a documented /opt/fog/pki/... path still reads the file"

# The source tree is gone rather than left as a second, diverging copy. This is
# the half that matters for key material: two authoritative roots is worse than
# either location on its own.
# find -type d does not follow the symlink, so this asks the question the [[ -d ]]
# test cannot: is there still a REAL directory at the old name?
[[ -z "$(find "$fogprogramdir" -maxdepth 1 -name pki -type d 2>/dev/null)" ]] \
    && ok "A: no real directory is left behind at the old path" \
    || bad "A: no real directory is left behind at the old path"

echo "== the accessors answer under the new tree =="
is "$(_pkiZoneDir root)" "$TARGET/root" "B: _pkiZoneDir root"
is "$(_pkiZoneDir web)" "$TARGET/web" "B: _pkiZoneDir web"
is "$(_pkiZoneDir client)" "$TARGET/client" "B: _pkiZoneDir client"
is "$(_pkiZoneDir secureboot)" "$TARGET/secureboot" "B: _pkiZoneDir secureboot"
is "$(_pkiConfDir)" "$TARGET/conf" "B: _pkiConfDir"
is "$(_pkiZoneDir nosuchzone)" "" "B: an unknown zone token still answers nothing"

echo "== running again changes nothing =="
before="$(stat -c %Y "$TARGET/root/ca/.fogCA.key" 2>/dev/null)"
echo "post-move-edit" > "$TARGET/web/leaf/.webLeaf.pem"
_migratePkiTree "$TARGET"
_migratePkiTree "$TARGET"
is "$(cat "$TARGET/web/leaf/.webLeaf.pem" 2>/dev/null)" "post-move-edit" \
    "C: a second run does not re-copy over live files"
is "$(stat -c %Y "$TARGET/root/ca/.fogCA.key" 2>/dev/null)" "$before" \
    "C: and does not rewrite what it already moved"
[[ -L "$fogprogramdir/pki" ]] \
    && ok "C: the compat symlink survives" \
    || bad "C: the compat symlink survives"
# Called directly, so the -L guard is the only thing between here and cp
# copying the target over itself. It has to be a clean no-op, not a failure
# that merely happens to change nothing.
_migratePkiTree "$TARGET"
is "$?" "0" "C: a migration with nothing to do succeeds"

echo "== a fresh install has nothing to move =="
newcase fresh
_migratePkiTree "$TARGET"
[[ -d "$TARGET" ]] \
    && ok "D: the tree is created" \
    || bad "D: the tree is created"
[[ -L "$fogprogramdir/pki" ]] \
    && ok "D: the compat symlink is created with it" \
    || bad "D: the compat symlink is created with it"
is "$(_pkiZoneDir web)" "$TARGET/web" "D: the accessors work straight away"

echo "== a failed copy leaves the source authoritative =="
# The reason this is not an mv. cp reports the failure, nothing is removed, and
# the next run simply redoes the whole move -- rather than leaving half the
# tree on each side with no record of which half.
newcase partial
seedLegacyTree
chmod 000 "$fogprogramdir/pki/root/ca/.fogCA.key"
_migratePkiTree "$TARGET"
rc=$?
chmod 0600 "$fogprogramdir/pki/root/ca/.fogCA.key"
if [[ $EUID -eq 0 ]]; then
    echo "  skip  E: running as root, an unreadable file is still readable"
else
    is "$rc" "1" "E: the migration reports failure"
    [[ -d "$fogprogramdir/pki" && ! -L "$fogprogramdir/pki" ]] \
        && ok "E: the source tree is still a real directory" \
        || bad "E: the source tree is still a real directory"
    is "$(cat "$fogprogramdir/pki/web/leaf/.webLeaf.pem" 2>/dev/null)" "web-leaf" \
        "E: the source content is intact"
fi

echo "== the tree is expressible, so a scratch or custom location works =="
# PKI_root_dir is what lets the tests above exist at all, and what an admin
# with a reason to keep the tree elsewhere sets. When it names the historic
# path, _pkiRootDir must NOT try to migrate a directory onto itself.
newcase samepath
PKI_root_dir="$fogprogramdir/pki"
mkdir -p "$fogprogramdir/pki/web"
is "$(_pkiRootDir)" "$fogprogramdir/pki" \
    "F: an override naming the old path is honored"
[[ ! -L "$fogprogramdir/pki" ]] \
    && ok "F: and nothing is moved onto itself" \
    || bad "F: and nothing is moved onto itself"
_migratePkiTree "$fogprogramdir/pki"
is "$?" "0" "F: and a direct call is a clean no-op rather than a failure"

echo "== the default, when nothing overrides it =="
newcase default
PKI_root_dir=""
fogprogramdir=""
is "$(_pkiRootDir)" "/etc/fog/pki" \
    "G: the tree defaults to /etc/fog/pki"
# No $fogprogramdir means functions.sh was sourced by a utils script outside an
# install -- bin/updatefog.sh, renewal-helper. Answering the default is right;
# creating anything is not, and the guard for that is the only reason the check
# above can safely name the host's real /etc/fog. Aimed at a scratch target so
# a regression fails here instead of writing to /etc.
PKI_root_dir="$TARGET"
_pkiRootDir >/dev/null
[[ ! -e "$TARGET" ]] \
    && ok "G: with no install location, nothing is created" \
    || bad "G: with no install location, nothing is created"

echo
echo "  passed: $PASS  failed: $FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
