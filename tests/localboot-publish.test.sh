#!/bin/bash
#
# Guards what service/localboot/ publishes and what it must never publish.
#
#   tests/localboot-publish.test.sh
#
# The directory used to be built from two lists that described the same bytes
# twice -- a "menu" of binaries under their TFTP names at the root, and a "kit"
# of the same binaries renamed fog*.efi under esp/. arm64 appeared in four
# places, and the 10-second-delay set was complete in one list and a single file
# in the other. It is now six archives and a manifest.
#
# What this pins, in rough order of how badly it fails if wrong:
#
#   1. The delay variants are not crossed. A -10sec archive whose fogipxe.efi
#      came from the plain tree, or the reverse, is undetectable on the server
#      and shows up as "the delay does nothing" on a switch running STP. Every
#      mock file's CONTENT is its own path in the tree, so provenance is checked
#      by reading the file rather than by trusting the copy loop.
#   2. The manifest is valid JSON with sums that match the bytes. It is written
#      by hand rather than by jq (see _jsonStr) precisely so the whole feature
#      does not vanish when a package is missing -- which puts the burden of
#      proving the encoding right here. Validated with python3, deliberately a
#      different implementation from the one that wrote it.
#   3. Nothing unpublishable leaks in: the EMBED-less autoexec/ builds, and the
#      BIOS artifacts (.kpxe/.usb/.iso), which are not PE images at all.
#   4. The degraded shapes still produce something useful rather than failing:
#      a server whose secureboot/ fetch failed has none of it, and a server may
#      hold no enrolment material. (Not "an HTTPS-only install stages no
#      secureboot/" -- that gate is gone; every mode stages it. See ADR 0015.)
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
    for d in "" "i386-efi/" "arm64-efi/" "10secdelay/" \
             "10secdelay/i386-efi/" "10secdelay/arm64-efi/" "autoexec/"; do
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
    local root="$1" withenrol="$2" n
    rm -rf "$root"
    mkdir -p "${root}/service/ipxe" "${root}/service/secureboot"
    for n in bzImage bzImage32 arm_Image init.xz init_32.xz arm_init.cpio.gz; do
        printf '%s' "$n" > "${root}/service/ipxe/${n}"
    done
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

is "$(mq "$MAN" count)" "6" "six archives are published"
is "$(mq "$MAN" top schema)" "1" "the manifest declares its schema"
is "$(mq "$MAN" top fogVersion)" "1.6.0-test" "the manifest records the FOG version"
is "$(mq "$MAN" top ipxeVersion)" "v2.0.0-fog.test" "the manifest records the iPXE version"

# Derived from the filesystem, not from the manifest: the archive format falls
# back to tar.gz where zip is absent, and a test that could not tell the
# difference would compare empty paths and pass.
if [[ -f $BOOT/fog-esp-x86_64.zip ]]; then EXT=".zip"; else EXT=".tar.gz"; fi
is "$(mq "$MAN" paths | tr '\n' ' ')" \
   "fog-esp-arm64-10sec${EXT} fog-esp-arm64${EXT} fog-esp-i386-10sec${EXT} fog-esp-i386${EXT} fog-esp-x86_64-10sec${EXT} fog-esp-x86_64${EXT} " \
   "one archive per architecture and delay variant"

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

# --- provenance: the delay variants must not be crossed ----------------------
for pair in "fog-esp-x86_64${EXT}|ipxe.efi" \
            "fog-esp-x86_64-10sec${EXT}|10secdelay/ipxe.efi" \
            "fog-esp-i386${EXT}|i386-efi/ipxe.efi" \
            "fog-esp-i386-10sec${EXT}|10secdelay/i386-efi/ipxe.efi" \
            "fog-esp-arm64${EXT}|arm64-efi/ipxe.efi" \
            "fog-esp-arm64-10sec${EXT}|10secdelay/arm64-efi/ipxe.efi"; do
    a="${pair%%|*}"; want="${pair#*|}"
    d="$WORK/x/${a%%.*}"
    rm -rf "$d"; extract "$BOOT/$a" "$d"
    is "$(cat "$d/${a%%.*}/fogipxe.efi" 2>/dev/null)" "$want" \
       "$a fogipxe.efi comes from $want"
done

# One top-level directory named after the archive, so two extracted side by side
# cannot silently overwrite each other -- they carry the same inner filenames.
is "$(top_entries "$BOOT/fog-esp-x86_64${EXT}" | tr '\n' ' ')" "fog-esp-x86_64 " \
   "the archive holds exactly one top-level directory, named for itself"

# --- what a full archive contains --------------------------------------------
X="$WORK/x/fog-esp-x86_64/fog-esp-x86_64"
for f in snponly-shimx64.efi snponly.efi ipxe-shimx64.efi ipxe.efi mmx64.efi \
         fogipxe.efi fogsnp.efi fogintel.efi fogrealtek.efi fogsnponly.efi \
         autoexec.ipxe README.txt MANIFEST.json \
         MOK.der PK.auth KEK.auth db.auth \
         fog-enroll-mok.sh fog-enroll-mok.desktop; do
    [[ -f $X/$f ]] || bad "x86_64 archive is missing $f"
done
[[ -f $X/fogsnponly.efi ]] && ok "fogsnponly.efi is published (it was excluded before)"
[[ -f $X/MOK.der ]] && ok "MOK.der travels with MokManager"
[[ -f $X/db.auth ]] && ok "the Setup Mode variable updates travel too"

# Upstream's names are the ones shim resolves to; FOG's must not take them.
is "$(cat "$X/snponly.efi")" "secureboot/snponly.efi" \
   "snponly.efi is UPSTREAM's copy, which is what shim's certificate vouches for"
is "$(cat "$X/ipxe.efi")" "secureboot/ipxe.efi" \
   "ipxe.efi is upstream's copy for the same reason"
is "$(cat "$X/fogsnponly.efi")" "snponly.efi" \
   "FOG's snponly ships under the fog prefix instead"

# Nothing unpublishable leaked in.
for junk in undionly.kkpxe ipxe.usb ipxe.iso ipxe.lkrn; do
    [[ -e $X/$junk ]] && bad "BIOS artifact $junk was published"
done
ok "no BIOS artifact (.kpxe/.usb/.iso/.lkrn) is published"
if grep -rq 'autoexec/' "$X"/*.efi 2>/dev/null; then
    bad "an EMBED-less autoexec/ build was published"
else
    ok "the EMBED-less autoexec/ builds are not published"
fi

# --- the fallback ladder -----------------------------------------------------
is "$(grep -c '^chain fog' "$X/autoexec.ipxe")" "5" "autoexec.ipxe chains all five builds"
is "$(grep '^chain fog' "$X/autoexec.ipxe" | tail -1)" "chain fogsnponly.efi || goto nofogbinary" \
   "fogsnponly.efi is tried LAST -- it is the one most likely to load and find no NIC"
is "$(grep '^chain fog' "$X/autoexec.ipxe" | head -1)" "chain fogipxe.efi || goto trysnp" \
   "fogipxe.efi is tried first"

X10="$WORK/x/fog-esp-x86_64-10sec/fog-esp-x86_64-10sec"
if [[ -f $X10/autoexec-10sec.ipxe ]]; then
    bad "the delay variant still ships a second script to rename over the first"
else
    ok "the delay archive has ONE autoexec.ipxe -- no rename step"
fi
if grep -q '10-SECOND-DELAY' "$X10/autoexec.ipxe"; then
    ok "the delay archive's script says which set it is"
else
    bad "the delay archive's script does not identify itself"
fi
is "$(grep -c '10secdelay' "$X10/autoexec.ipxe")" "0" \
   "the delay archive's binaries carry the plain names"

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
if [[ -n "$(mq "$MAN" filenote "fog-esp-x86_64${EXT}" fogsnponly.efi)" ]]; then
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
is "$(mq "$MAN" count)" "6" "an HTTPS-only install still publishes six archives"
rm -rf "$WORK/n"; extract "$BOOT/fog-esp-x86_64${EXT}" "$WORK/n"
N="$WORK/n/fog-esp-x86_64"
if [[ -e $N/snponly-shimx64.efi || -e $N/mmx64.efi ]]; then
    bad "shim material appeared without a staged secureboot/"
else
    ok "no shim material when none was staged"
fi
[[ -f $N/fogipxe.efi ]] && ok "FOG's own builds still ship without a shim"
if [[ -e $N/autoexec.ipxe ]]; then
    bad "autoexec.ipxe shipped with no loader that could ever read it"
else
    ok "autoexec.ipxe is omitted when no loader is present to read it"
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
[[ -f $I/fogipxe.efi ]] && ok "the i386 archive carries FOG's builds"
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
if grep -q 'no autoexec.ipxe in this archive' "$I/README.txt"; then
    ok "the i386 README says there is no fallback chain, because there is none"
else
    bad "the i386 README describes an autoexec.ipxe the archive does not contain"
fi
if grep -q 'autoexec.ipxe already tries them' "$X/README.txt"; then
    ok "the x86_64 README does describe the ladder, because it has one"
else
    bad "the x86_64 README omits the fallback ladder it ships"
fi

# --- a server with no enrolment material at all ------------------------------
mk_web "$webdirdest" no
_publishLocalBootFiles >/dev/null
is "$(mq "$MAN" count)" "6" "a server publishing no enrolment material still succeeds"
rm -rf "$WORK/e"; extract "$BOOT/fog-esp-x86_64${EXT}" "$WORK/e"
E="$WORK/e/fog-esp-x86_64"
if [[ -e $E/MOK.der || -e $E/db.auth ]]; then
    bad "enrolment material appeared on a server that publishes none"
else
    ok "no enrolment material when the server has none to give"
fi
[[ -f $E/fogipxe.efi ]] && ok "the boot binaries ship regardless"

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
first_sum="$(mq "$MAN" filesum "fog-esp-x86_64${EXT}" fogipxe.efi)"
_publishLocalBootFiles >/dev/null
is "$(mq "$MAN" files "fog-esp-x86_64${EXT}" | tr '\n' ' ')" "$first_files" \
   "a re-run publishes the same file list"
is "$(mq "$MAN" filesum "fog-esp-x86_64${EXT}" fogipxe.efi)" "$first_sum" \
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
