#!/bin/bash
#
# Guards what service/localboot/ publishes and what it must never publish.
#
#   tests/localboot-publish.test.sh
#
# The directory used to be built from two lists that described the same bytes
# twice -- a "menu" of binaries under their TFTP names at the root, and a "kit"
# of the same binaries renamed fog*.efi under esp/. That became six archives, and
# then three: fog-ipxe v2.0.0-fog.8 stopped shipping the 10secdelay/ EFI builds
# the -10sec archives were made from, so those three were published holding a
# shim and no FOG binary at all (GH-1195), and with the boot script on disk the
# delay is a line of text rather than a second set of binaries.
#
# What this pins, in rough order of how badly it fails if wrong:
#
#   1. NOTHING chains anything, and every copy of autoexec.ipxe is identical.
#      One folder per route -- fog-ipxe/, secureboot-upstream/, secureboot-fog/
#      and the -customca/ pair -- each with its own copy of the same script, plus
#      one at the archive root. The archive used to hold a chain ladder at the
#      root and different boot logic in a subfolder; a chained binary resolves
#      autoexec.ipxe by FLAT NAME through the synthetic handle efi_image_exec()
#      installs, so it re-read the ladder and chained itself until the firmware
#      died. Every mock file's CONTENT is its own path in the tree, so provenance
#      is checked by reading the file rather than by trusting the copy loop.
#   1a. The archive is packed FLAT -- no wrapper directory named after itself,
#      which on Windows produced fog-esp-x86_64\fog-esp-x86_64\.
#   1b. secureboot-fog/ carries FOG's build under BOTH ipxe.efi and snponly.efi,
#      because a locally booted shim asks for whichever name it can derive and
#      falls back to ipxe.efi when firmware will not report its own filename
#      (ipxe/ipxe#1684). Checked by CONTENT: those files wear upstream names and
#      must contain FOG's bytes.
#   1c. Which tree feeds which folder flips on stock/ existing. stock/ is the
#      PUBLISHED set snapshotted before a --rebuild-ipxe-with-my-ca build, so
#      when it exists the tree ROOT is the CA-embedded one. Getting that backwards
#      silently ships the wrong binaries in both folders.
#   2. The manifest is valid JSON with sums that match the bytes. It is written
#      by hand rather than by jq (see _jsonStr) precisely so the whole feature
#      does not vanish when a package is missing -- which puts the burden of
#      proving the encoding right here. Validated with python3, deliberately a
#      different implementation from the one that wrote it.
#   3. Nothing unpublishable leaks in: the retired autoexec/ builds, and the
#      BIOS artifacts (.kpxe/.usb/.iso), which are not PE images at all.
#   3a. rEFInd comes from the WEB tree (it has never existed in the TFTP tree)
#      and the binary in each archive matches the arch the archive is named for.
#   4. The degraded shapes still produce something useful rather than failing:
#      an HTTPS-only install stages no secureboot/, and a server may hold no
#      enrolment material.
#   5. _restampIpxeManifest keeps .fog-ipxe-manifest matching the bytes on disk
#      after signing rewrites them. Without it _copyIpxeTree reports all 45
#      .efi as admin-modified on the SECOND install of a Secure Boot server and
#      stops updating FOG's own binaries, permanently.
#
# Needs bash, sha256sum, python3, and tar or unzip. No network, no root, no
# install. sbsign is NOT needed: the signing path is exercised through
# _restampIpxeManifest directly, which is where the bug was.
#
# Exit status 0 = pass or skip, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"

[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }
command -v sha256sum >/dev/null 2>&1 || { echo "SKIP: sha256sum is not installed"; exit 0; }
# Probed by RUNNING it, not by command -v: on Windows "python3" resolves to an
# App Execution Alias that exists on PATH and then refuses to run, so a
# presence check passes and every assertion downstream of it silently compares
# empty strings. A test that reports a false pass is worse than one that skips.
# Bounded with a timeout, because "refuses to run" turned out to be optimistic:
# on Windows the python3 App Execution Alias can BLOCK rather than fail, which
# hangs the whole suite on the first probe instead of falling through to py. A
# test that hangs is worse than one that skips, for the same reason a false pass
# is. `timeout` is coreutils and may be absent, so it is used only if present.
PY=""
_pyprobe() {
    if command -v timeout >/dev/null 2>&1; then
        timeout 10 "$1" -c 'print("ok")' 2>/dev/null
    else
        "$1" -c 'print("ok")' 2>/dev/null
    fi
}
for c in python3 python py; do
    if [[ "$(_pyprobe "$c")" == "ok" ]]; then PY="$c"; break; fi
done
[[ -n $PY ]] || { echo "SKIP: no working python interpreter found"; exit 0; }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

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

version="1.6.0-test"
ipxeVer="v2.0.0-fog.test"
apacheuser="$(id -un)"

# A python reader, so the manifest is parsed by something other than the shell
# that wrote it.
cat > "$WORK/mq.py" <<'PYEOF'
import json, sys
m = json.load(open(sys.argv[1]))
cmd = sys.argv[2]
arg = sys.argv[3] if len(sys.argv) > 3 else None
arg2 = sys.argv[4] if len(sys.argv) > 4 else None


def arch(path):
    for a in m["archives"]:
        if a["path"] == path:
            return a
    raise SystemExit("no such archive: " + path)


if cmd == "paths":
    print("\n".join(sorted(a["path"] for a in m["archives"])))
elif cmd == "roots":
    print("\n".join(sorted(a["root"] for a in m["archives"])))
elif cmd == "count":
    print(len(m["archives"]))
elif cmd == "field":
    print(arch(arg)[arg2])
elif cmd == "files":
    print("\n".join(sorted(f["name"] for f in arch(arg)["contents"])))
elif cmd == "filesum":
    print([f["sha256"] for f in arch(arg)["contents"] if f["name"] == arg2][0])
elif cmd == "filenote":
    print([f["note"] for f in arch(arg)["contents"] if f["name"] == arg2][0])
elif cmd == "filerole":
    print([f["role"] for f in arch(arg)["contents"] if f["name"] == arg2][0])
elif cmd == "filesigned":
    print([f["fogSigned"] for f in arch(arg)["contents"] if f["name"] == arg2][0])
elif cmd == "kernels":
    print("\n".join(sorted(k["name"] for k in m["kernels"])))
elif cmd == "kernelpath":
    print([k["path"] for k in m["kernels"] if k["name"] == arg][0])
elif cmd == "top":
    print(m[arg])
PYEOF

# Windows python writes CRLF, so multi-line output would carry a stray CR into
# every comparison and into filenames read out of it. A no-op on Linux.
mq() { "$PY" "$WORK/mq.py" "$@" | tr -d '\015'; }

# Every mock file's content IS its path in the tree, so a copy landing in the
# wrong archive is caught by reading it rather than inferred from a size.
# $3 = yes puts a stock/ tree in place, which is how the installer records that
# --rebuild-ipxe-with-my-ca ran: _preserveStockIpxe() snapshots the PUBLISHED set
# into stock/ before the build, and buildipxe.sh then builds into the tree ROOT.
# So with stock/ present the root is the CA-embedded set and stock/ is generic --
# the opposite of the intuitive reading, and the thing to get right.
mk_tree() {
    local root="$1" withsb="$2" withstock="${3:-no}" d n
    rm -rf "$root"
    # autoexec/ is retired (_retireStaleEfiPaths removes it) and 10secdelay/ no
    # longer holds EFI files. Both are fabricated anyway, as negative controls:
    # nothing published may come from either.
    for d in "" "i386-efi/" "arm64-efi/" "10secdelay/" "autoexec/"; do
        mkdir -p "${root}/${d}"
        for n in ipxe snp snponly intel realtek; do
            printf '%s' "${d}${n}.efi" > "${root}/${d}${n}.efi"
        done
    done
    # Not PE images; an ESP cannot boot them and they must never be published.
    printf 'kpxe' > "${root}/undionly.kkpxe"
    printf 'usb'  > "${root}/ipxe.usb"
    printf 'iso'  > "${root}/ipxe.iso"
    printf 'lkrn' > "${root}/ipxe.lkrn"
    if [[ $withsb == yes ]]; then
        mkdir -p "${root}/secureboot/arm64-efi"
        for n in snponly.efi snponly-shimx64.efi ipxe.efi ipxe-shimx64.efi mmx64.efi; do
            printf '%s' "secureboot/${n}" > "${root}/secureboot/${n}"
        done
        for n in snponly.efi snponly-shimaa64.efi ipxe.efi ipxe-shimaa64.efi mmaa64.efi; do
            printf '%s' "secureboot/arm64-efi/${n}" > "${root}/secureboot/arm64-efi/${n}"
        done
    fi
    if [[ $withstock == yes ]]; then
        for d in "stock/" "stock/i386-efi/" "stock/arm64-efi/"; do
            mkdir -p "${root}/${d}"
            for n in ipxe snp snponly intel realtek; do
                printf '%s' "${d}${n}.efi" > "${root}/${d}${n}.efi"
            done
        done
    fi
}

mk_web() {
    local root="$1" withenrol="$2" withrefind="${3:-yes}" n
    rm -rf "$root"
    mkdir -p "${root}/service/ipxe" "${root}/service/secureboot"
    for n in bzImage bzImage32 arm_Image init.xz init_32.xz arm_init.cpio.gz; do
        printf '%s' "$n" > "${root}/service/ipxe/${n}"
    done
    # rEFInd lives ONLY here, never in the TFTP tree -- which is exactly the
    # mistake GH-1185 records, the publisher having copied from $tftpdirdst
    # alone. Content is the filename, so an archive that got the wrong arch's
    # binary is caught by reading it.
    if [[ $withrefind == yes ]]; then
        for n in refind.efi refind_x64.efi refind_ia32.efi refind_aa64.efi refind.conf; do
            printf '%s' "$n" > "${root}/service/ipxe/${n}"
        done
    fi
    if [[ $withenrol == yes ]]; then
        for n in MOK.der PK.auth KEK.auth db.auth \
                 fog-enroll-mok.sh fog-enroll-mok.desktop; do
            printf '%s' "$n" > "${root}/service/secureboot/${n}"
        done
    fi
}

extract() {
    mkdir -p "$2"
    case "$1" in
        *.zip)    unzip -qq -o "$1" -d "$2" ;;
        *.tar.gz) tar -xzf "$1" -C "$2" ;;
    esac
}
top_entries() {
    case "$1" in
        *.zip)    unzip -Z1 "$1" ;;
        *.tar.gz) tar -tzf "$1" ;;
    esac | sed 's#/.*##' | sort -u
}

echo "localboot publish:"

# --- the normal case ---------------------------------------------------------
tftpdirdst="$WORK/tftp"
webdirdest="$WORK/web"
mk_tree "$tftpdirdst" yes
mk_web "$webdirdest" yes
PKI_sb_codesign_key=""
PKI_sb_codesign_cert=""

_publishLocalBootFiles >/dev/null
BOOT="$webdirdest/service/localboot"
MAN="$BOOT/manifest.json"

if [[ -f $MAN ]]; then ok "manifest.json is written"; else bad "manifest.json missing"; fi
if "$PY" -c 'import json,sys; json.load(open(sys.argv[1]))' "$MAN" >/dev/null 2>&1; then
    ok "manifest.json is valid JSON (parsed by python, not by what wrote it)"
else
    bad "manifest.json is not valid JSON"
    "$PY" -c 'import json,sys; json.load(open(sys.argv[1]))' "$MAN" 2>&1 | head -3
fi

is "$(mq "$MAN" count)" "3" "three archives are published -- one per architecture"
# 3: archives lost their wrapper directory, so each entry lost its "root" key,
# and the upstream Secure Boot set moved from the archive root into secureboot-upstream/.
# Both change the paths a consumer would build, so the number had to move with
# them.
is "$(mq "$MAN" top schema)" "3" "the manifest declares its schema"
if mq "$MAN" field "fog-esp-x86_64${EXT}" root >/dev/null 2>&1 \
   && [[ -n "$(mq "$MAN" field "fog-esp-x86_64${EXT}" root 2>/dev/null)" ]]; then
    bad "the manifest still carries a \"root\" key for a wrapper that no longer exists"
else
    ok "no \"root\" key, because there is no wrapper directory to strip"
fi
is "$(mq "$MAN" top fogVersion)" "1.6.0-test" "the manifest records the FOG version"
is "$(mq "$MAN" top ipxeVersion)" "v2.0.0-fog.test" "the manifest records the iPXE version"

# Derived from the filesystem, not from the manifest: the archive format falls
# back to tar.gz where zip is absent, and a test that could not tell the
# difference would compare empty paths and pass.
if [[ -f $BOOT/fog-esp-x86_64.zip ]]; then EXT=".zip"; else EXT=".tar.gz"; fi
is "$(mq "$MAN" paths | tr '\n' ' ')" \
   "fog-esp-arm64${EXT} fog-esp-i386${EXT} fog-esp-x86_64${EXT} " \
   "one archive per architecture, and no delay variants"

# Nothing loose. The whole point of the reorganisation.
is "$(find "$BOOT" -mindepth 1 -maxdepth 1 -type d | wc -l | tr -d ' ')" "0" \
   "the published directory has no subdirectories at all"
is "$(find "$BOOT" -name '*.efi' | wc -l | tr -d ' ')" "0" \
   "no loose .efi is published beside the archives"
if [[ -f $BOOT/index.php ]] && grep -q '404' "$BOOT/index.php"; then
    ok "the 404 stub is in place"
else
    bad "the 404 stub is missing"
fi

# --- provenance: each archive gets ITS arch's binaries -----------------------
#
# Three pairs, not six. The 10secdelay/ rows this loop used to carry were the
# reason the -10sec archives were worth having, and are why they had to go: the
# EFI builds behind them no longer exist, so the archives were published empty
# of FOG binaries and nothing noticed.
for pair in "fog-esp-x86_64${EXT}|ipxe.efi|refind.efi" \
            "fog-esp-i386${EXT}|i386-efi/ipxe.efi|refind_ia32.efi" \
            "fog-esp-arm64${EXT}|arm64-efi/ipxe.efi|refind_aa64.efi"; do
    a="${pair%%|*}"; rest="${pair#*|}"
    want="${rest%%|*}"; wantrefind="${rest#*|}"
    d="$WORK/x/${a%%.*}"
    rm -rf "$d"; extract "$BOOT/$a" "$d"
    is "$(cat "$d/fog-ipxe/fogipxe.efi" 2>/dev/null)" "$want" \
       "$a fog-ipxe/fogipxe.efi comes from $want"
    is "$(cat "$d/refind/${wantrefind}" 2>/dev/null)" "$wantrefind" \
       "$a carries the web tree's ${wantrefind}"
done

# NO WRAPPER DIRECTORY. The archive's contents are its top level, so extracting
# it gives one folder (whatever the extractor names it) rather than a folder
# containing a folder. On Windows the wrapper produced
# fog-esp-x86_64\fog-esp-x86_64\ and the README's "copy the contents of this
# folder" then named the wrong one.
tops="$(top_entries "$BOOT/fog-esp-x86_64${EXT}" | sort | tr '\n' ' ')"
if [[ $tops == *"fog-esp-x86_64"* ]]; then
    bad "the archive still carries a wrapper directory: $tops"
else
    ok "the archive is packed flat, with no wrapper directory"
fi

# --- what a full archive contains --------------------------------------------
X="$WORK/x/fog-esp-x86_64"
for f in secureboot-upstream/snponly-shimx64.efi secureboot-upstream/snponly.efi \
         secureboot-upstream/ipxe-shimx64.efi secureboot-upstream/ipxe.efi secureboot-upstream/mmx64.efi \
         secureboot-upstream/autoexec.ipxe \
         fog-ipxe/fogipxe.efi fog-ipxe/fogsnp.efi fog-ipxe/fogintel.efi \
         fog-ipxe/fogrealtek.efi fog-ipxe/fogsnponly.efi fog-ipxe/autoexec.ipxe \
         refind/refind.efi refind/refind.conf \
         autoexec.ipxe README.txt MANIFEST.json \
         secureboot-upstream/MOK.der secureboot-upstream/PK.auth secureboot-upstream/KEK.auth \
         secureboot-upstream/db.auth \
         secureboot-upstream/fog-enroll-mok.sh secureboot-upstream/fog-enroll-mok.desktop; do
    [[ -f $X/$f ]] || bad "x86_64 archive is missing $f"
done
[[ -f $X/fog-ipxe/fogsnponly.efi ]] && ok "fogsnponly.efi is published (it was excluded before)"
# The upstream set must travel TOGETHER IN ONE DIRECTORY: shim derives its second
# stage AND MokManager by name from its OWN directory, so splitting the pair
# across directories breaks the rewrite it does to find them. secureboot-upstream/ is that
# directory now; it used to be the archive root.
for f in snponly-shimx64.efi snponly.efi ipxe-shimx64.efi ipxe.efi mmx64.efi; do
    [[ -f $X/secureboot-upstream/$f ]] || bad "upstream $f is not in secureboot-upstream/"
done
ok "the upstream shim set travels together in secureboot-upstream/, where shim resolves its names"
# And a script beside them, or the loader they hand to has nothing to boot with.
[[ -f $X/secureboot-upstream/autoexec.ipxe ]] \
    || bad "secureboot-upstream/ has no autoexec.ipxe -- upstream's loader would have no script"
# x86_64 follows bootmenu.class.php's refind.efi-over-refind_x64.efi preference,
# so the ESP and the PXE path agree on which binary is canonical.
if [[ -f $X/refind/refind.efi && ! -e $X/refind/refind_x64.efi ]]; then
    ok "x86_64 takes refind.efi over refind_x64.efi, as the boot menu does"
else
    bad "x86_64 does not follow the boot menu's rEFInd preference"
fi
[[ -f $X/secureboot-upstream/MOK.der ]] && ok "MOK.der travels in the same directory as MokManager"
[[ -f $X/secureboot-upstream/db.auth ]] && ok "the Setup Mode variable updates travel too"

# Upstream's names are the ones shim resolves to; FOG's must not take them.
# Expected values are the mock's CONTENT, which is its path in the TFTP tree --
# so these prove provenance, not just presence. secureboot/ on the right-hand
# side is the source tree; secureboot-upstream/ on the left is the archive.
is "$(cat "$X/secureboot-upstream/snponly.efi")" "secureboot/snponly.efi" \
   "secureboot-upstream/snponly.efi is UPSTREAM's copy, which is what shim's certificate vouches for"
is "$(cat "$X/secureboot-upstream/ipxe.efi")" "secureboot/ipxe.efi" \
   "secureboot-upstream/ipxe.efi is upstream's copy for the same reason"
# THE ONE THAT MATTERS MOST in the new layout: secureboot-fog/ wears upstream's
# filenames but must contain FOG's build. Reading the content is the only way to
# tell, and getting this wrong would ship an archive whose "FOG build" folder is
# actually upstream's loader -- which would fail on exactly the hardware the
# folder exists for.
is "$(cat "$X/secureboot-fog/ipxe.efi")" "ipxe.efi" \
   "secureboot-fog/ipxe.efi is FOG's build, not upstream's, despite the name"
is "$(cat "$X/secureboot-fog/snponly.efi")" "ipxe.efi" \
   "secureboot-fog/snponly.efi is the SAME FOG build under the second name shim may ask for"
is "$(cat "$X/secureboot-fog/snponly-shimx64.efi")" "secureboot/snponly-shimx64.efi" \
   "secureboot-fog/ still carries upstream's real shims"
[[ -f $X/secureboot-fog/mmx64.efi && -f $X/secureboot-fog/MOK.der ]] \
    && ok "secureboot-fog/ carries MokManager and MOK.der, so enrolment is possible from it"
is "$(cat "$X/fog-ipxe/fogsnponly.efi")" "snponly.efi" \
   "FOG's snponly ships under the fog prefix in fog-ipxe/ instead"

# Nothing unpublishable leaked in.
for junk in undionly.kkpxe ipxe.usb ipxe.iso ipxe.lkrn; do
    [[ -e $X/$junk ]] && bad "BIOS artifact $junk was published"
done
ok "no BIOS artifact (.kpxe/.usb/.iso/.lkrn) is published"
# Every .efi at any depth, not just "$X"/*.efi -- the FOG builds moved into
# fog-ipxe/ and a top-level-only glob would stop covering the ones most likely to
# have come from the wrong place.
leaked() { find "$X" -name '*.efi' -type f -exec grep -l "$1" {} + 2>/dev/null; }
if [[ -n "$(leaked 'autoexec/')" ]]; then
    bad "a retired autoexec/ build was published: $(leaked 'autoexec/')"
else
    ok "the retired autoexec/ builds are not published"
fi
if [[ -n "$(leaked '10secdelay')" ]]; then
    bad "a binary came from the retired 10secdelay/ EFI set: $(leaked '10secdelay')"
else
    ok "nothing comes from 10secdelay/ -- it holds BIOS files only now"
fi

# --- NOTHING IN THE ARCHIVE CHAINS ANOTHER BINARY ----------------------------
#
# THE regression this guards, and it is worth stating precisely because the
# archive shipped it. When iPXE chains an EFI image, the chained image resolves
# autoexec.ipxe through the synthetic filesystem handle efi_image_exec()
# installs, and that handle serves registered images BY FLAT NAME -- so the
# chained binary reads whatever the CHAINING iPXE registered under that name, not
# the file beside itself. With a chain ladder at the archive root, a chained
# fog*.efi re-read the ladder, chained itself, and recursed until the firmware
# ran out of pool memory. Directory separation does not help: flat-name lookup
# ignores directories.
#
# So no copy of the script may contain a `chain` to a local .efi at all. A
# `chain tftp://.../default.ipxe` is the point of the script and must stay.
for s in autoexec.ipxe fog-ipxe/autoexec.ipxe secureboot-upstream/autoexec.ipxe; do
    [[ -f $X/$s ]] || continue
    if grep -qE '^[[:space:]]*chain[^|]*\.efi' "$X/$s"; then
        bad "$s chains a .efi -- a chained binary re-reads this script by flat name and loops"
    fi
done
ok "no copy of autoexec.ipxe chains a local .efi, so no chain loop is constructible"

# --- every copy of the boot script is identical and complete -----------------
#
# Since fog-ipxe v2.0.0-fog.8 no EFI binary carries a compiled-in script, so this
# file is the only thing that makes any of them find a DHCP server. An archive
# whose copies differ reintroduces "which one did this machine read?" into every
# bug report -- which is exactly how the chain loop hid.
for s in autoexec.ipxe fog-ipxe/autoexec.ipxe secureboot-upstream/autoexec.ipxe; do
    [[ -f $X/$s ]] || continue
    for want in 'dhcp net0' 'dhcp net1' 'dhcp net2' ':proxycheck' \
                ':nextservercheck' ':netboot' 'default.ipxe'; do
        grep -q -- "$want" "$X/$s" || bad "$s is missing \"$want\""
    done
done
ok "every copy of autoexec.ipxe carries the full DHCP/proxyDHCP/next-server walk"
L="$X/fog-ipxe/autoexec.ipxe"
for s in fog-ipxe/autoexec.ipxe secureboot-upstream/autoexec.ipxe; do
    [[ -f $X/$s ]] || continue
    if cmp -s "$X/autoexec.ipxe" "$X/$s"; then
        ok "$s is byte-identical to the root copy"
    else
        bad "$s differs from the root copy -- they must come from one generator"
    fi
done

# The delay is two lines of text now, not a second set of archives. Commented
# out by default so an admin who hits it at 2am uncomments a line instead of
# reinstalling; written live when --boot-delay asked for it.
is "$(grep -c '^#sleep 10' "$L")" "1" \
   "the pre-DHCP sleep ships commented out, ready to uncomment"
is "$(grep -c '^sleep ' "$L")" "0" \
   "no delay is active when --boot-delay was not given"
if grep -q 'FOG-BOOT-DELAY-BEGIN' "$L"; then
    bad "the sentinel block is present with no delay configured"
else
    ok "no sentinel block without --boot-delay"
fi
BOOT_dhcp_delay_seconds=15
_publishLocalBootFiles >/dev/null
rm -rf "$WORK/xd"; extract "$BOOT/fog-esp-x86_64${EXT}" "$WORK/xd"
LD="$WORK/xd/fog-ipxe/autoexec.ipxe"
is "$(grep -c '^sleep 15' "$LD")" "1" \
   "--boot-delay 15 writes a live sleep into fog-ipxe/autoexec.ipxe"
if grep -q 'FOG-BOOT-DELAY-BEGIN' "$LD" && grep -q 'FOG-BOOT-DELAY-END' "$LD"; then
    ok "the delay is bracketed by the same sentinels _applyBootDelay uses"
else
    bad "the delay is written without the sentinels that make it replaceable"
fi
# EVERY copy gets it, which is the inverse of what this asserted before. The
# delay used to be withheld from the root copy because that copy was a chain
# ladder inside upstream's loader, where a sleep delayed nothing. The root copy
# is FOG's boot logic now and upstream's loader does the DHCP itself, so a copy
# without the delay is a copy that ignores --boot-delay.
for s in autoexec.ipxe secureboot-upstream/autoexec.ipxe; do
    [[ -f $WORK/xd/$s ]] || continue
    is "$(grep -c '^sleep 15' "$WORK/xd/$s")" "1" \
       "--boot-delay 15 reaches $s too"
done
unset BOOT_dhcp_delay_seconds
_publishLocalBootFiles >/dev/null
rm -rf "$WORK/x/fog-esp-x86_64"; extract "$BOOT/fog-esp-x86_64${EXT}" "$WORK/x/fog-esp-x86_64"

# --- manifest sums match the bytes -------------------------------------------
sum_of() { sha256sum "$1" 2>/dev/null | cut -d' ' -f1; }
is "$(mq "$MAN" field "fog-esp-x86_64${EXT}" sha256)" "$(sum_of "$BOOT/fog-esp-x86_64${EXT}")" \
   "the manifest's archive sha256 matches the archive"
is "$(mq "$MAN" field "fog-esp-x86_64${EXT}" size)" \
   "$(wc -c < "$BOOT/fog-esp-x86_64${EXT}" | tr -d '[:space:]')" \
   "the manifest's archive size matches the archive"
badsum=0
while IFS= read -r f; do
    [[ -f $X/$f ]] || { badsum=1; continue; }
    [[ "$(mq "$MAN" filesum "fog-esp-x86_64${EXT}" "$f")" == "$(sum_of "$X/$f")" ]] || badsum=1
done < <(mq "$MAN" files "fog-esp-x86_64${EXT}")
is "$badsum" "0" "every per-file sha256 in the manifest matches the extracted file"

# The manifest has to name the subdirectories, and this is the assertion that
# proves it does. _espKitContentsJson used a -maxdepth 1 walk and stored bare
# basenames; left that way it would omit fog-ipxe/ and refind/ entirely, and the
# checksum loop above would still pass because it walks the manifest rather than
# the directory. An under-reporting manifest reads exactly like a correct one.
for f in fog-ipxe/fogipxe.efi fog-ipxe/autoexec.ipxe secureboot-upstream/snponly.efi \
         secureboot-upstream/autoexec.ipxe refind/refind.efi refind/refind.conf; do
    mq "$MAN" files "fog-esp-x86_64${EXT}" | grep -qx "$f" \
        || bad "the manifest does not list $f"
done
ok "the manifest names files by path relative to the archive root, subdirs included"
# All three copies share one role now, because they are one file. The path still
# has to be what the manifest keys on -- the basename appears three times.
for s in autoexec.ipxe fog-ipxe/autoexec.ipxe secureboot-upstream/autoexec.ipxe; do
    is "$(mq "$MAN" filerole "fog-esp-x86_64${EXT}" "$s")" "boot-script" \
       "$s is described as the boot script"
done
is "$(mq "$MAN" filerole "fog-esp-x86_64${EXT}" secureboot-upstream/snponly-shimx64.efi)" "shim" \
   "the shim is still described as a shim from its new path"
is "$(mq "$MAN" filerole "fog-esp-x86_64${EXT}" secureboot-upstream/MOK.der)" "enrolment-cert" \
   "MOK.der is still described as the enrolment certificate from its new path"
is "$(mq "$MAN" filerole "fog-esp-x86_64${EXT}" refind/refind.efi)" "chainloader" \
   "rEFInd is described as the local-boot chainloader"
is "$(mq "$MAN" filesigned "fog-esp-x86_64${EXT}" refind/refind.conf)" "False" \
   "refind.conf is not claimed to be signed -- it is data, not a PE image"
if [[ -n "$(mq "$MAN" filenote "fog-esp-x86_64${EXT}" refind/refind.efi)" ]]; then
    ok "rEFInd carries a note saying what it is for"
else
    bad "rEFInd is published with no explanation"
fi

# A file cannot carry its own checksum, so MANIFEST.json is absent from its own
# inventory -- deliberate, and worth pinning so it is not "fixed" into a lie.
if mq "$MAN" files "fog-esp-x86_64${EXT}" | grep -qx 'MANIFEST.json'; then
    bad "MANIFEST.json lists itself, so its own sum cannot be right"
else
    ok "MANIFEST.json is absent from its own inventory"
fi

# The in-archive manifest agrees with the root one.
if "$PY" -c 'import json,sys; json.load(open(sys.argv[1]))' "$X/MANIFEST.json" >/dev/null 2>&1; then
    ok "the in-archive MANIFEST.json is valid JSON"
else
    bad "the in-archive MANIFEST.json is not valid JSON"
fi
is "$("$PY" -c 'import json,sys;print(" ".join(sorted(f["name"] for f in json.load(open(sys.argv[1]))["contents"])))' "$X/MANIFEST.json")" \
   "$(mq "$MAN" files "fog-esp-x86_64${EXT}" | tr '\n' ' ' | sed 's/ $//')" \
   "the in-archive manifest lists the same files as the root manifest"

# The notes are what replaced curation-by-omission; an empty one for the file
# that used to be excluded would put the advice nowhere.
if [[ -n "$(mq "$MAN" filenote "fog-esp-x86_64${EXT}" fog-ipxe/fogsnponly.efi)" ]]; then
    ok "fogsnponly.efi carries the caveat that used to be an exclusion"
else
    bad "fogsnponly.efi is published with no explanation of when it fails"
fi

# --- kernels are listed, not copied ------------------------------------------
is "$(mq "$MAN" kernels | tr '\n' ' ')" \
   "arm_Image arm_init.cpio.gz bzImage bzImage32 init.xz init_32.xz " \
   "the FOS kernel/init set is listed in the manifest"
is "$(mq "$MAN" kernelpath bzImage)" "../ipxe/bzImage" \
   "kernels are referenced by relative path, so any hostname resolves them"
if [[ -e $X/bzImage ]]; then
    bad "a kernel was copied into an archive"
else
    ok "no kernel is copied into an archive (60-80MB not moved)"
fi

# --- HTTPS-only install: nothing stages secureboot-upstream/ --------------------------
mk_tree "$tftpdirdst" no
mk_web "$webdirdest" yes
_publishLocalBootFiles >/dev/null
is "$(mq "$MAN" count)" "3" "an HTTPS-only install still publishes three archives"
rm -rf "$WORK/n"; extract "$BOOT/fog-esp-x86_64${EXT}" "$WORK/n"
N="$WORK/n"
if [[ -e $N/secureboot-upstream/snponly-shimx64.efi || -e $N/secureboot-upstream/mmx64.efi ]]; then
    bad "shim material appeared without a staged secureboot-upstream/"
else
    ok "no shim material when none was staged"
fi
[[ -f $N/fog-ipxe/fogipxe.efi ]] && ok "FOG's own builds still ship without a shim"
# The ROOT copy is no longer gated on a loader. It used to be a chain ladder that
# only upstream's loader read, so withholding it made sense; it is FOG's boot
# logic now, and it is also iPXE's volume-root fallback, so it ships always.
if [[ -f $N/autoexec.ipxe ]]; then
    ok "the root autoexec.ipxe ships even with no upstream loader -- it is the boot script now"
else
    bad "the root autoexec.ipxe is still gated on a loader it no longer depends on"
fi
if [[ -f $N/fog-ipxe/autoexec.ipxe ]]; then
    ok "fog-ipxe/autoexec.ipxe ships even with no upstream loader"
else
    bad "fog-ipxe/autoexec.ipxe was gated on a loader that does not read it"
fi
# secureboot-upstream/ IS gated: a script beside no binary serves nobody.
if [[ -e $N/secureboot-upstream/autoexec.ipxe ]]; then
    bad "secureboot-upstream/autoexec.ipxe shipped with no upstream loader beside it to read it"
else
    ok "secureboot-upstream/autoexec.ipxe is omitted when no loader was staged"
fi
[[ -f $N/secureboot-upstream/db.auth ]] && ok "the Setup Mode route survives an HTTPS-only install"

# --- i386 has no shim, but does have the Setup Mode route --------------------
mk_tree "$tftpdirdst" yes
_publishLocalBootFiles >/dev/null
rm -rf "$WORK/i"; extract "$BOOT/fog-esp-i386${EXT}" "$WORK/i"
I="$WORK/i"
if [[ -e $I/secureboot-upstream/snponly-shimx64.efi ]]; then
    bad "an x64 shim was put in the i386 archive"
else
    ok "the i386 archive has no shim -- upstream signs none for ia32"
fi
[[ -f $I/secureboot-upstream/db.auth ]] && ok "the i386 archive DOES carry db.auth (the only SB route it has)"
[[ -f $I/fog-ipxe/fogipxe.efi ]] && ok "the i386 archive carries FOG's builds"
if [[ -f $I/fog-ipxe/autoexec.ipxe ]]; then
    ok "the i386 archive carries FOG's boot script beside its builds"
else
    bad "the i386 archive has FOG binaries and no script for them to read"
fi
if [[ -e $I/refind/refind_x64.efi || -e $I/refind/refind.efi ]]; then
    bad "an x64 rEFInd was put in the i386 archive"
else
    ok "the i386 archive gets refind_ia32.efi, not an x64 build"
fi
# db is the ONLY Secure Boot route on i386 -- no Microsoft-signed shim exists for
# ia32, so there is no MOK route to fall back on. The README has to say so rather
# than leaving an admin to discover that MokManager is absent.
if grep -q 'in db' "$I/README.txt" && grep -qi 'no shim' "$I/README.txt"; then
    ok "the i386 README names db as its route and says the shim route is absent"
else
    bad "the i386 README does not explain its only Secure Boot route"
fi
# No README may promise a fallback chain, because no archive has one any more.
# The old text told the admin "autoexec.ipxe already tries them in that order",
# which is now false everywhere -- and was already false for an archive with no
# loader.
for r in "$I/README.txt" "$X/README.txt"; do
    if grep -q 'already tries them in that order' "$r"; then
        bad "$r promises a fallback chain that no longer exists"
    fi
done
ok "no README promises a fallback chain"
for r in "$I/README.txt" "$X/README.txt"; do
    if grep -q 'NOTHING HERE CHAINS ANYTHING ELSE' "$r"; then
        ok "$(basename "$(dirname "$r")")/README.txt says plainly that nothing chains"
    else
        bad "$r does not tell the admin that binaries are picked, not chained"
    fi
done

# --- a server that rebuilt iPXE with its own CA ------------------------------
#
# stock/ present means the TREE ROOT is CA-embedded and stock/ is the generic set.
# Getting that backwards would silently ship the wrong binaries in both folders --
# and it nearly did: the first cut tested "is the CA dir non-empty", which is
# false on x86_64 because FOG's x86_64 builds live at the tree root with an EMPTY
# path prefix. The custom-CA folders then never appeared on the one architecture
# that matters most. Hence the content checks below rather than presence checks.
mk_tree "$tftpdirdst" yes yes
mk_web "$webdirdest" yes
_publishLocalBootFiles >/dev/null
rm -rf "$WORK/ca"; extract "$BOOT/fog-esp-x86_64${EXT}" "$WORK/ca"
C="$WORK/ca"
is "$(cat "$C/fog-ipxe/fogipxe.efi" 2>/dev/null)" "stock/ipxe.efi" \
   "fog-ipxe/ takes the GENERIC build from stock/ when a CA rebuild happened"
is "$(cat "$C/fog-ipxe-customca/fogipxe.efi" 2>/dev/null)" "ipxe.efi" \
   "fog-ipxe-customca/ takes the CA-EMBEDDED build from the tree root"
is "$(cat "$C/secureboot-fog/ipxe.efi" 2>/dev/null)" "stock/ipxe.efi" \
   "secureboot-fog/ stands the generic build in as the shim's second stage"
is "$(cat "$C/secureboot-fog-customca/ipxe.efi" 2>/dev/null)" "ipxe.efi" \
   "secureboot-fog-customca/ stands the CA-embedded build in instead"
is "$(cat "$C/secureboot-fog-customca/snponly.efi" 2>/dev/null)" "ipxe.efi" \
   "and under the second name too, so either shim resolves"
for d in fog-ipxe fog-ipxe-customca secureboot-upstream secureboot-fog \
         secureboot-fog-customca; do
    [[ -f $C/$d/autoexec.ipxe ]] || bad "$d/ has no autoexec.ipxe"
    cmp -s "$C/autoexec.ipxe" "$C/$d/autoexec.ipxe" \
        || bad "$d/autoexec.ipxe differs from the root copy"
done
ok "all five folders carry the same boot script"
[[ -f $C/secureboot-fog-customca/MOK.der ]] \
    && ok "MOK.der reaches the CA-embedded shim folder too, so it can be enrolled from there"
# Back to the no-rebuild shape, or every later assertion inherits stock/.
mk_tree "$tftpdirdst" yes
_publishLocalBootFiles >/dev/null
rm -rf "$WORK/nostock"; extract "$BOOT/fog-esp-x86_64${EXT}" "$WORK/nostock"
if [[ -e $WORK/nostock/fog-ipxe-customca || -e $WORK/nostock/secureboot-fog-customca ]]; then
    bad "custom-CA folders appeared on a server that never rebuilt iPXE"
else
    ok "no custom-CA folders without a rebuild"
fi
is "$(cat "$WORK/nostock/fog-ipxe/fogipxe.efi" 2>/dev/null)" "ipxe.efi" \
   "without a rebuild fog-ipxe/ takes the tree root, which is then the generic set"

# --- a server with no enrolment material at all ------------------------------
mk_web "$webdirdest" no
_publishLocalBootFiles >/dev/null
is "$(mq "$MAN" count)" "3" "a server publishing no enrolment material still succeeds"
rm -rf "$WORK/e"; extract "$BOOT/fog-esp-x86_64${EXT}" "$WORK/e"
E="$WORK/e"
if [[ -e $E/secureboot-upstream/MOK.der || -e $E/secureboot-upstream/db.auth ]]; then
    bad "enrolment material appeared on a server that publishes none"
else
    ok "no enrolment material when the server has none to give"
fi
[[ -f $E/fog-ipxe/fogipxe.efi ]] && ok "the boot binaries ship regardless"

# --- a server with no rEFInd at all ------------------------------------------
#
# configureMinHttpd never lays down service/ipxe, so a storage node has no rEFInd
# to publish, and an admin can have removed one. The archives are still a working
# netboot kit without a local-boot chainloader; failing the publish over it would
# trade the whole feature for a missing extra.
mk_tree "$tftpdirdst" yes
mk_web "$webdirdest" yes no
out="$(_publishLocalBootFiles)"
is "$(mq "$MAN" count)" "3" "a server with no rEFInd still publishes three archives"
if [[ $out == *Failed* ]]; then
    bad "a missing rEFInd failed the publish"
else
    ok "a missing rEFInd is not a failure"
fi
rm -rf "$WORK/nr"; extract "$BOOT/fog-esp-x86_64${EXT}" "$WORK/nr"
NR="$WORK/nr"
if [[ -d $NR/refind ]]; then
    bad "an empty refind/ directory was published"
else
    ok "no refind/ directory when there is no rEFInd to put in it"
fi
[[ -f $NR/fog-ipxe/fogipxe.efi ]] && ok "the boot binaries ship without rEFInd"
if grep -q 'BOOTING THE LOCAL OS AGAIN' "$NR/README.txt"; then
    bad "the README describes a rEFInd the archive does not contain"
else
    ok "the README omits the rEFInd section when no rEFInd shipped"
fi

# --- an empty tree must report failure, not publish nothing quietly ----------
mk_tree "$tftpdirdst" no
rm -f "$tftpdirdst"/*.efi "$tftpdirdst"/*/*.efi "$tftpdirdst"/*/*/*.efi
out="$(_publishLocalBootFiles)"
if [[ $out == *Failed* ]]; then
    ok "an empty tree reports Failed rather than publishing an empty directory"
else
    bad "an empty tree was published silently (got: ${out})"
fi

# --- re-running is stable ----------------------------------------------------
mk_tree "$tftpdirdst" yes
mk_web "$webdirdest" yes
_publishLocalBootFiles >/dev/null
first_files="$(mq "$MAN" files "fog-esp-x86_64${EXT}" | tr '\n' ' ')"
first_sum="$(mq "$MAN" filesum "fog-esp-x86_64${EXT}" fog-ipxe/fogipxe.efi)"
_publishLocalBootFiles >/dev/null
is "$(mq "$MAN" files "fog-esp-x86_64${EXT}" | tr '\n' ' ')" "$first_files" \
   "a re-run publishes the same file list"
is "$(mq "$MAN" filesum "fog-esp-x86_64${EXT}" fog-ipxe/fogipxe.efi)" "$first_sum" \
   "a re-run publishes the same bytes"

# --- .fog-ipxe-manifest survives signing -------------------------------------
#
# The regression this pins costs a Secure Boot server every future iPXE update:
# _copyIpxeTree records a sum, signing rewrites the file, and on the next run
# every .efi looks like the admin replaced it.
echo
echo "ipxe manifest re-stamp:"
tftpdirsrc="$WORK/sign-src"
tftpdirdst="$WORK/sign-dst"
mkdir -p "$tftpdirsrc/i386-efi" "$tftpdirdst"
printf 'unsigned-ipxe'  > "$tftpdirsrc/ipxe.efi"
printf 'unsigned-snp'   > "$tftpdirsrc/i386-efi/snp.efi"
printf 'admin-owned'    > "$tftpdirsrc/custom.efi"
_copyIpxeTree
is "$ipxeSkipped" "" "baseline: nothing preserved on a first copy"

# The admin genuinely replaced one file; its ORIGINAL sum must stay recorded.
printf 'ADMIN BUILD' > "$tftpdirdst/custom.efi"
_copyIpxeTree
is "$ipxeSkipped" "custom.efi" "baseline: the admin's file is preserved"

# Stand in for sbsign: rewrite the bytes of the two FOG files in place, exactly
# as signing does, then re-stamp only those.
printf 'signed-ipxe' > "$tftpdirdst/ipxe.efi"
printf 'signed-snp'  > "$tftpdirdst/i386-efi/snp.efi"
_restampIpxeManifest "$tftpdirdst" ipxe.efi i386-efi/snp.efi

_copyIpxeTree
if [[ $ipxeSkipped == *ipxe.efi* && $ipxeSkipped != *custom* ]]; then
    bad "signed binaries are still reported as admin-modified"
else
    ok "signed binaries are NOT reported as admin-modified"
fi
is "$ipxeSkipped" "custom.efi" "and the admin's own file is still protected"
is "$(cat "$tftpdirdst/ipxe.efi")" "unsigned-ipxe" \
   "FOG's own binary updates again on the next run"
is "$(cat "$tftpdirdst/custom.efi")" "ADMIN BUILD" \
   "the admin's binary still survives"

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]] || { echo "  (see $error_log)"; exit 1; }
exit 0
