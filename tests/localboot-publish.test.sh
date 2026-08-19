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
#   1. FOG's own builds are in local/ with their own autoexec.ipxe, and the root
#      autoexec.ipxe -- read by upstream's loader -- is a separate file that
#      chains into local/. Flat, those two are one file, and a fog*.efi launched
#      from the boot manager reads the ladder and chains itself. Every mock
#      file's CONTENT is its own path in the tree, so provenance is checked by
#      reading the file rather than by trusting the copy loop.
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
PY=""
for c in python3 python py; do
    if [[ "$("$c" -c 'print("ok")' 2>/dev/null)" == "ok" ]]; then PY="$c"; break; fi
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
mk_tree() {
    local root="$1" withsb="$2" d n
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
secureBootKey=""
secureBootCert=""

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
is "$(mq "$MAN" top schema)" "2" "the manifest declares its schema"
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
    is "$(cat "$d/${a%%.*}/local/fogipxe.efi" 2>/dev/null)" "$want" \
       "$a local/fogipxe.efi comes from $want"
    is "$(cat "$d/${a%%.*}/refind/${wantrefind}" 2>/dev/null)" "$wantrefind" \
       "$a carries the web tree's ${wantrefind}"
done

# One top-level directory named after the archive, so two extracted side by side
# cannot silently overwrite each other -- they carry the same inner filenames.
is "$(top_entries "$BOOT/fog-esp-x86_64${EXT}" | tr '\n' ' ')" "fog-esp-x86_64 " \
   "the archive holds exactly one top-level directory, named for itself"

# --- what a full archive contains --------------------------------------------
X="$WORK/x/fog-esp-x86_64/fog-esp-x86_64"
for f in snponly-shimx64.efi snponly.efi ipxe-shimx64.efi ipxe.efi mmx64.efi \
         local/fogipxe.efi local/fogsnp.efi local/fogintel.efi \
         local/fogrealtek.efi local/fogsnponly.efi local/autoexec.ipxe \
         refind/refind.efi refind/refind.conf \
         autoexec.ipxe README.txt MANIFEST.json \
         MOK.der PK.auth KEK.auth db.auth \
         fog-enroll-mok.sh fog-enroll-mok.desktop; do
    [[ -f $X/$f ]] || bad "x86_64 archive is missing $f"
done
[[ -f $X/local/fogsnponly.efi ]] && ok "fogsnponly.efi is published (it was excluded before)"
# The upstream set must stay at the top level: shim derives its second stage AND
# MokManager from its OWN directory, so moving either into a subfolder breaks the
# name rewrite it does to find them.
for f in snponly-shimx64.efi snponly.efi ipxe-shimx64.efi ipxe.efi mmx64.efi; do
    [[ -f $X/$f ]] || bad "upstream $f is not at the archive top level"
done
ok "the upstream shim set stays at the top level, where shim resolves its names"
# x86_64 follows bootmenu.class.php's refind.efi-over-refind_x64.efi preference,
# so the ESP and the PXE path agree on which binary is canonical.
if [[ -f $X/refind/refind.efi && ! -e $X/refind/refind_x64.efi ]]; then
    ok "x86_64 takes refind.efi over refind_x64.efi, as the boot menu does"
else
    bad "x86_64 does not follow the boot menu's rEFInd preference"
fi
[[ -f $X/MOK.der ]] && ok "MOK.der travels with MokManager"
[[ -f $X/db.auth ]] && ok "the Setup Mode variable updates travel too"

# Upstream's names are the ones shim resolves to; FOG's must not take them.
is "$(cat "$X/snponly.efi")" "secureboot/snponly.efi" \
   "snponly.efi is UPSTREAM's copy, which is what shim's certificate vouches for"
is "$(cat "$X/ipxe.efi")" "secureboot/ipxe.efi" \
   "ipxe.efi is upstream's copy for the same reason"
is "$(cat "$X/local/fogsnponly.efi")" "snponly.efi" \
   "FOG's snponly ships under the fog prefix in local/ instead"

# Nothing unpublishable leaked in.
for junk in undionly.kkpxe ipxe.usb ipxe.iso ipxe.lkrn; do
    [[ -e $X/$junk ]] && bad "BIOS artifact $junk was published"
done
ok "no BIOS artifact (.kpxe/.usb/.iso/.lkrn) is published"
# Every .efi at any depth, not just "$X"/*.efi -- the FOG builds moved into
# local/ and a top-level-only glob would stop covering the ones most likely to
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

# --- the root ladder chains INTO local/, and never a bare sibling ------------
is "$(grep -c '^chain local/fog' "$X/autoexec.ipxe")" "5" \
   "the root autoexec.ipxe chains all five builds out of local/"
is "$(grep '^chain local/fog' "$X/autoexec.ipxe" | tail -1)" \
   "chain local/fogsnponly.efi || goto nofogbinary" \
   "fogsnponly.efi is tried LAST -- it is the one most likely to load and find no NIC"
is "$(grep '^chain local/fog' "$X/autoexec.ipxe" | head -1)" \
   "chain local/fogipxe.efi || goto trysnp" \
   "fogipxe.efi is tried first"
# THE regression. A bare "chain fogipxe.efi" means the ladder and the binary sit
# in one directory, so the binary reads the ladder that chains it and loops.
if grep -qE '^chain fog[a-z]*\.efi' "$X/autoexec.ipxe"; then
    bad "the root ladder chains a sibling fog*.efi -- that binary would chain itself"
else
    ok "the root ladder never chains a sibling, so nothing can chain itself"
fi

# --- local/autoexec.ipxe is FOG's real boot script ---------------------------
#
# Since fog-ipxe v2.0.0-fog.8 the fog*.efi builds carry no compiled-in script, so
# this file is the only thing that makes them find a DHCP server at all. An
# archive without it boots to iPXE's bare autoboot: no multi-NIC walk, no
# proxyDHCP, no next-server prompt.
L="$X/local/autoexec.ipxe"
for want in 'dhcp net0' 'dhcp net1' 'dhcp net2' ':proxycheck' ':nextservercheck' \
            ':netboot' 'default.ipxe'; do
    grep -q -- "$want" "$L" || bad "local/autoexec.ipxe is missing \"$want\""
done
ok "local/autoexec.ipxe carries the full DHCP/proxyDHCP/next-server ladder"
if grep -q '^chain ' "$X/autoexec.ipxe" && ! grep -q '^chain local/' "$L"; then
    ok "local/autoexec.ipxe boots the network rather than chaining another binary"
else
    bad "local/autoexec.ipxe looks like a copy of the root ladder"
fi

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
bootdelay=15
_publishLocalBootFiles >/dev/null
rm -rf "$WORK/xd"; extract "$BOOT/fog-esp-x86_64${EXT}" "$WORK/xd"
LD="$WORK/xd/fog-esp-x86_64/local/autoexec.ipxe"
is "$(grep -c '^sleep 15' "$LD")" "1" \
   "--boot-delay 15 writes a live sleep into local/autoexec.ipxe"
if grep -q 'FOG-BOOT-DELAY-BEGIN' "$LD" && grep -q 'FOG-BOOT-DELAY-END' "$LD"; then
    ok "the delay is bracketed by the same sentinels _applyBootDelay uses"
else
    bad "the delay is written without the sentinels that make it replaceable"
fi
# The root ladder runs inside upstream's loader, which drives no NIC on an ESP,
# so a sleep there would delay nothing.
if grep -q 'sleep' "$WORK/xd/fog-esp-x86_64/autoexec.ipxe"; then
    bad "the delay was written into the root ladder, where it delays nothing"
else
    ok "the delay goes only where DHCP actually happens"
fi
unset bootdelay
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
# basenames; left that way it would omit local/ and refind/ entirely, and the
# checksum loop above would still pass because it walks the manifest rather than
# the directory. An under-reporting manifest reads exactly like a correct one.
for f in local/fogipxe.efi local/autoexec.ipxe refind/refind.efi refind/refind.conf; do
    mq "$MAN" files "fog-esp-x86_64${EXT}" | grep -qx "$f" \
        || bad "the manifest does not list $f"
done
ok "the manifest names files by path relative to the archive root, subdirs included"
is "$(mq "$MAN" filerole "fog-esp-x86_64${EXT}" local/autoexec.ipxe)" "boot-script" \
   "local/autoexec.ipxe is described as the boot script"
is "$(mq "$MAN" filerole "fog-esp-x86_64${EXT}" autoexec.ipxe)" "chain-script" \
   "the root autoexec.ipxe is described as the chain script, not the same thing"
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
if [[ -n "$(mq "$MAN" filenote "fog-esp-x86_64${EXT}" local/fogsnponly.efi)" ]]; then
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

# --- HTTPS-only install: nothing stages secureboot/ --------------------------
mk_tree "$tftpdirdst" no
mk_web "$webdirdest" yes
_publishLocalBootFiles >/dev/null
is "$(mq "$MAN" count)" "3" "an HTTPS-only install still publishes three archives"
rm -rf "$WORK/n"; extract "$BOOT/fog-esp-x86_64${EXT}" "$WORK/n"
N="$WORK/n/fog-esp-x86_64"
if [[ -e $N/snponly-shimx64.efi || -e $N/mmx64.efi ]]; then
    bad "shim material appeared without a staged secureboot/"
else
    ok "no shim material when none was staged"
fi
[[ -f $N/local/fogipxe.efi ]] && ok "FOG's own builds still ship without a shim"
if [[ -e $N/autoexec.ipxe ]]; then
    bad "the root ladder shipped with no loader that could ever read it"
else
    ok "the root ladder is omitted when no loader is present to read it"
fi
# But FOG's own script is NOT gated on that. The binary that reads it is the FOG
# build, which ships here; withholding it would leave the archive unable to find
# a DHCP server, which is the whole job.
if [[ -f $N/local/autoexec.ipxe ]]; then
    ok "local/autoexec.ipxe ships even with no upstream loader"
else
    bad "local/autoexec.ipxe was gated on a loader that does not read it"
fi
[[ -f $N/db.auth ]] && ok "the Setup Mode route survives an HTTPS-only install"

# --- i386 has no shim, but does have the Setup Mode route --------------------
mk_tree "$tftpdirdst" yes
_publishLocalBootFiles >/dev/null
rm -rf "$WORK/i"; extract "$BOOT/fog-esp-i386${EXT}" "$WORK/i"
I="$WORK/i/fog-esp-i386"
if [[ -e $I/snponly-shimx64.efi ]]; then
    bad "an x64 shim was put in the i386 archive"
else
    ok "the i386 archive has no shim -- upstream signs none for ia32"
fi
[[ -f $I/db.auth ]] && ok "the i386 archive DOES carry db.auth (the only SB route it has)"
[[ -f $I/local/fogipxe.efi ]] && ok "the i386 archive carries FOG's builds"
if [[ -f $I/local/autoexec.ipxe ]]; then
    ok "the i386 archive carries FOG's boot script, having no loader to read a ladder"
else
    bad "the i386 archive has FOG binaries and no script for them to read"
fi
if [[ -e $I/refind/refind_x64.efi || -e $I/refind/refind.efi ]]; then
    bad "an x64 rEFInd was put in the i386 archive"
else
    ok "the i386 archive gets refind_ia32.efi, not an x64 build"
fi
if grep -q 'Setup Mode' "$I/README.txt"; then
    ok "the i386 README names the Setup Mode route"
else
    bad "the i386 README does not explain its only Secure Boot route"
fi
# The README must not describe a fallback chain the archive does not contain.
# It nearly did: the ladder paragraph was unconditional, so an archive with no
# loader -- i386, or any arch on an HTTPS-only install -- told the admin that
# "autoexec.ipxe already tries them in that order" about a file that is not
# there.
if grep -q 'no chain ladder in this archive' "$I/README.txt"; then
    ok "the i386 README says there is no fallback chain, because there is none"
else
    bad "the i386 README describes a ladder the archive does not contain"
fi
if grep -q 'already tries them in that order' "$X/README.txt"; then
    ok "the x86_64 README does describe the ladder, because it has one"
else
    bad "the x86_64 README omits the fallback ladder it ships"
fi

# --- a server with no enrolment material at all ------------------------------
mk_web "$webdirdest" no
_publishLocalBootFiles >/dev/null
is "$(mq "$MAN" count)" "3" "a server publishing no enrolment material still succeeds"
rm -rf "$WORK/e"; extract "$BOOT/fog-esp-x86_64${EXT}" "$WORK/e"
E="$WORK/e/fog-esp-x86_64"
if [[ -e $E/MOK.der || -e $E/db.auth ]]; then
    bad "enrolment material appeared on a server that publishes none"
else
    ok "no enrolment material when the server has none to give"
fi
[[ -f $E/local/fogipxe.efi ]] && ok "the boot binaries ship regardless"

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
NR="$WORK/nr/fog-esp-x86_64"
if [[ -d $NR/refind ]]; then
    bad "an empty refind/ directory was published"
else
    ok "no refind/ directory when there is no rEFInd to put in it"
fi
[[ -f $NR/local/fogipxe.efi ]] && ok "the boot binaries ship without rEFInd"
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
first_sum="$(mq "$MAN" filesum "fog-esp-x86_64${EXT}" local/fogipxe.efi)"
_publishLocalBootFiles >/dev/null
is "$(mq "$MAN" files "fog-esp-x86_64${EXT}" | tr '\n' ' ')" "$first_files" \
   "a re-run publishes the same file list"
is "$(mq "$MAN" filesum "fog-esp-x86_64${EXT}" local/fogipxe.efi)" "$first_sum" \
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
