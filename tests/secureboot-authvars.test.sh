#!/bin/bash
#
# Guards the "signed variable update that silently contains nothing" bug class.
#
#   tests/secureboot-authvars.test.sh
#
# packages/secureboot/fog-build-sb-authvars turns this server's keys plus
# Microsoft's published CAs into the PK/KEK/db updates a client writes in Setup
# Mode. It has one failure mode that is far worse than not building at all: a
# db that is missing Microsoft's UEFI CA 2011 breaks FOG's OWN shim, so a client
# that enrolled it can no longer PXE boot -- recoverable only at the firmware
# screen, per machine.
#
# That is not hypothetical. During development cert-to-efi-sig-list was handed
# the DER certificates Microsoft publishes; it reads PEM only, and on DER it
# **exits 0** after writing a 44-byte header containing no certificate. Every
# Microsoft CA vanished from the db and the build reported success. An exit
# status is therefore not evidence here, and this checks the bytes.
#
# Needs openssl, python3 and efitools. Skips (exit 0) rather than failing when
# efitools is absent, so it is safe in a pre-commit hook on a machine that does
# not have it.
#
# Exit status 0 = pass or skip, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
BUILDER="$REPO/packages/secureboot/fog-build-sb-authvars"
MSCERTS="$REPO/packages/secureboot/mscerts"

[[ -f $BUILDER ]] || { echo "ERROR: $BUILDER not found" >&2; exit 1; }
[[ -d $MSCERTS ]] || { echo "ERROR: $MSCERTS not found" >&2; exit 1; }

for t in cert-to-efi-sig-list sign-efi-sig-list; do
    command -v "$t" >/dev/null 2>&1 || {
        echo "SKIP: efitools is not installed ($t missing)"
        exit 0
    }
done

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

PASS=0
FAIL=0
ok()   { echo "PASS: $1"; PASS=$((PASS + 1)); }
bad()  { echo "FAIL: $1"; FAIL=$((FAIL + 1)); }

mkdir -p "$WORK/keys" "$WORK/out"
for n in MOK PK KEK; do
    openssl req -x509 -new -nodes -newkey rsa:2048 -sha256 -days 1 \
        -subj "/CN=FOG authvar test $n/" \
        -keyout "$WORK/keys/$n.key" -out "$WORK/keys/$n.pem" 2>/dev/null
    openssl x509 -in "$WORK/keys/$n.pem" -outform der -out "$WORK/keys/$n.der" 2>/dev/null
done

# Stamp the builder's config path the same way _publishSecureBootAuthVars does.
write_conf() {
    cat > "$WORK/conf" <<EOF
SECUREBOOT_KEY=$WORK/keys/MOK.key
SECUREBOOT_CERT=${1:-$WORK/keys/MOK.pem}
SECUREBOOT_STAGING=$WORK/staging
SECUREBOOT_PK_KEY=$WORK/keys/PK.key
SECUREBOOT_PK_CERT=$WORK/keys/PK.pem
SECUREBOOT_KEK_KEY=$WORK/keys/KEK.key
SECUREBOOT_KEK_CERT=$WORK/keys/KEK.pem
SECUREBOOT_MSCERTS=${2:-$MSCERTS}
SECUREBOOT_AUTHVARS=$WORK/out
EOF
    sed "s|^CONF=.*|CONF=\"$WORK/conf\"|" "$BUILDER" > "$WORK/builder"
    chmod 755 "$WORK/builder"
}

run_builder() { rm -f "$WORK"/out/*.auth; "$WORK/builder" >"$WORK/stdout" 2>"$WORK/stderr"; }

# --- 1. the happy path builds all three ---
write_conf
if run_builder && [[ -s $WORK/out/db.auth && -s $WORK/out/KEK.auth && -s $WORK/out/PK.auth ]]; then
    ok "1. builds PK.auth, KEK.auth and db.auth"
else
    bad "1. builder did not produce all three blobs ($(cat "$WORK/stderr"))"
fi

# --- 2. THE regression. Every certificate is really embedded. ---
#
# Compares the certificates parsed back out of db.auth against the DER files on
# disk by sha256, so a 44-byte empty signature list cannot pass. MicCorUEFCA2011
# is called out by name because it is the one that signs FOG's shim.
python3 - "$WORK" "$MSCERTS" > "$WORK/certs" 2>"$WORK/pyerr" <<'PY'
import sys, struct, hashlib
work, mscerts = sys.argv[1], sys.argv[2]
d = open(f"{work}/out/db.auth", "rb").read()
# EFI_VARIABLE_AUTHENTICATION_2: 16-byte EFI_TIME, then a WIN_CERTIFICATE whose
# dwLength covers the whole certificate structure. The signature lists follow.
dwLength = struct.unpack_from('<I', d, 16)[0]
esl = d[16 + dwLength:]
off = 0
while off < len(esl):
    listsz, hdrsz, sigsz = struct.unpack_from('<III', esl, off + 16)
    if listsz <= 0:
        break
    body = esl[off + 28 + hdrsz: off + listsz]
    for i in range(len(body) // sigsz):
        cert = body[i * sigsz:(i + 1) * sigsz][16:]   # skip the 16-byte owner GUID
        print(hashlib.sha256(cert).hexdigest())
    off += listsz
PY
if [[ -s $WORK/certs ]]; then
    missing=""
    for f in MicWinProPCA2011 MicCorUEFCA2011 WindowsUEFICA2023 MicrosoftUEFICA2023 MicCorOptionROMCA2023; do
        want="$(sha256sum "$MSCERTS/$f.crt" | awk '{print $1}')"
        grep -qx "$want" "$WORK/certs" || missing="$missing $f"
    done
    want="$(sha256sum "$WORK/keys/MOK.der" | awk '{print $1}')"
    grep -qx "$want" "$WORK/certs" || missing="$missing FOG-signing-cert"
    if [[ -z $missing ]]; then
        ok "2. db.auth embeds every Microsoft db CA and the FOG certificate"
    else
        bad "2. db.auth is missing:$missing (empty signature lists?)"
    fi
else
    bad "2. could not parse any certificate out of db.auth ($(cat "$WORK/pyerr"))"
fi

# --- 3. KEK.auth likewise ---
python3 - "$WORK" > "$WORK/kcerts" 2>/dev/null <<'PY'
import sys, struct, hashlib
work = sys.argv[1]
d = open(f"{work}/out/KEK.auth", "rb").read()
dwLength = struct.unpack_from('<I', d, 16)[0]
esl = d[16 + dwLength:]
off = 0
while off < len(esl):
    listsz, hdrsz, sigsz = struct.unpack_from('<III', esl, off + 16)
    if listsz <= 0:
        break
    body = esl[off + 28 + hdrsz: off + listsz]
    for i in range(len(body) // sigsz):
        print(hashlib.sha256(body[i * sigsz:(i + 1) * sigsz][16:]).hexdigest())
    off += listsz
PY
missing=""
for f in MicCorKEKCA2011 MicCorKEK2KCA2023; do
    grep -qx "$(sha256sum "$MSCERTS/$f.crt" | awk '{print $1}')" "$WORK/kcerts" || missing="$missing $f"
done
grep -qx "$(sha256sum "$WORK/keys/KEK.der" | awk '{print $1}')" "$WORK/kcerts" || missing="$missing FOG-KEK"
[[ -z $missing ]] && ok "3. KEK.auth embeds both Microsoft KEK CAs and the FOG KEK" \
                  || bad "3. KEK.auth is missing:$missing"

# --- 4. the signing chain the UEFI spec requires ---
#
# db must be authorized by KEK and KEK by PK, or this server can never update an
# already-enrolled client's db again -- it would have stranded every machine it
# enrolled. Checked by which signer certificate the PKCS#7 blob carries.
python3 - "$WORK" > "$WORK/chain" 2>/dev/null <<'PY'
import sys, struct
work = sys.argv[1]
ders = {n: open(f"{work}/keys/{n}.der", "rb").read() for n in ("PK", "KEK", "MOK")}
for name in ("db", "KEK", "PK"):
    d = open(f"{work}/out/{name}.auth", "rb").read()
    dwLength = struct.unpack_from('<I', d, 16)[0]
    p7 = d[40:16 + dwLength]          # 16 EFI_TIME + 24 WIN_CERTIFICATE header
    found = [n for n, v in ders.items() if v in p7] or ["NONE"]
    print(name, ",".join(found))
PY
CHAIN="$(tr '\n' ';' < "$WORK/chain")"
if [[ $CHAIN == "db KEK;KEK PK;PK PK;" ]]; then
    ok "4. db is signed by KEK, KEK by PK, PK by itself"
else
    bad "4. wrong signing chain (\"$CHAIN\", want \"db KEK;KEK PK;PK PK;\")"
fi

# --- 5. refuse rather than ship a FOG-only db ---
#
# Without Microsoft's CAs, a client that enrolled this would lose both Windows
# and FOG's own shim. Building it anyway is the one outcome worth failing the
# whole install over.
mkdir -p "$WORK/emptycerts"
write_conf "" "$WORK/emptycerts"
if run_builder; then
    bad "5. builder produced a db with no Microsoft certificates"
else
    ok "5. builder refuses when the Microsoft certificates are missing"
fi

# --- 6. nothing is published when the build fails ---
#
# A half-published set is worse than none: a client that wrote db and KEK but
# found no PK sits in Setup Mode looking enrolled while enforcing nothing.
if [[ ! -e $WORK/out/db.auth && ! -e $WORK/out/KEK.auth && ! -e $WORK/out/PK.auth ]]; then
    ok "6. a failed build publishes no blobs at all"
else
    bad "6. a failed build left blobs behind in the output directory"
fi

# --- 7. an incomplete config is refused, not half-honoured ---
write_conf
sed -i '/^SECUREBOOT_PK_KEY=/d' "$WORK/conf"
if run_builder; then
    bad "7. builder ran with no platform key configured"
else
    ok "7. builder refuses an incomplete config"
fi

echo "----"
echo "$PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]] || exit 1
