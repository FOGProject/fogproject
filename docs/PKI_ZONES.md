# FOG's certificate zones

FOG uses certificates for three unrelated jobs. This describes how they are
separated and what changes on the endpoints.

> This is the 1.5.x line. The 1.6 line carries the same hierarchy plus storage
> node certificate issuance and the Setup Mode PK/KEK/db enrolment path, which
> are not present here.

## The three zones

| Zone | What it protects | Lifetime | Cost of changing it |
|---|---|---|---|
| **Web TLS** | The browser/API connection to the FOG web UI | 5 yrs default (see "Leaf renewal") | None. Browsers just need the issuer trusted. |
| **Client Communication** | fog-client's encrypted check-in with the server | 3 – 5 yrs | Medium. Every registered client must re-pin. |
| **Secure Boot** | The signature on the FOS kernels | 10 – 20 yrs | High. Firmware re-enrollment on every machine. |

They have nothing in common except that FOG generates all three, and their
costs differ by orders of magnitude — which is exactly why they should not
share key material.

## Why they were separated

**`.srvprivate.key` was the web server's TLS key *and* the key that decrypts
every fog-client handshake.** `FOGBase::certDecrypt()` opens that exact path on
every `authorize()` call. So an ACME renewal with `--key-file`, a purchased
certificate dropped in place, or `--recreate-keys` installed a perfectly valid
certificate and silently broke client authentication, with nothing in the logs
connecting the two.

**The enrolled Secure Boot MOK was the signing certificate itself.** Because
the thing in the firmware was a leaf that can issue nothing, rotating or
revoking the signing key meant a physical MokManager trip to every machine.

Both are the same mistake: one file serving as both a *trust anchor* and an
*operational key*.

## The layout

```
FOG Server CA                     the existing CA, unchanged, published as ca.cert.der
├── FOG Web CA                    serverAuth  + name constraints
│     └── the certificate the web server serves
├── FOG Secure Boot CA            codeSigning + name constraints
│     ├── MOK.der, enrolled in firmware ONCE
│     └── code-signing leaf, rotatable without re-enrollment
└── .srvprivate.key + srvpublic.crt
                                  the client communication keypair, unmoved
```

The anchor is the CA your server already has. Nothing above it is created, so
`ca.cert.der` does not change and no fog-client re-pins.

Under `$fogprogramdir/pki/` (default `/opt/fog/pki/`), one subfolder per
zone, each split into `ca/` (the zone's own CA material) and `leaf/` (what
that CA issues day to day) — everything is a dotfile (`ls -a`):

```
root/ca/.fogCA.{key,pem}          the anchor. Key never regenerated, 0400 root:root.
                                   .fogCA.pem is a symlink to wherever the
                                   certificate already lived before this split.
root/leaf/.srvprivate.key         symlink -> $sslpath/.srvprivate.key
root/leaf/.srvpublic.crt          symlink -> $sslpath/.srvpublic.crt
                                   (the comm leaf's real files stay at
                                   $sslpath -- see "Why they were separated")
web/ca/.fogWebCA.{key,pem}        signs the vhost's certificate
web/ca/.fogWebCAchain.pem         CA + web intermediate
web/leaf/.webLeaf.{key,pem}       what the web server actually serves
secureboot/ca/.fogSBCA.{key,pem}  signs the code-signing leaf
secureboot/leaf/sign.{key,pem}    what sbsign actually signs with
```

`root/leaf/` and the two intermediates' `leaf/` subfolders are the only place
this hierarchy issues something meant to be rotated routinely; see "Leaf
renewal" below.

## What an upgrade does and does not change

| | |
|---|---|
| `CA/.fogCA.pem` | **unchanged**, byte for byte |
| `ca.cert.der` | **unchanged** — no client re-pins |
| `.srvprivate.key` | **unchanged** — client authentication is unaffected |
| `srvpublic.crt` | the same certificate, adopted rather than re-issued |
| the web certificate | **new**, issued by the Web CA, on its own keypair |
| the Secure Boot MOK | **new** — see below, this one needs action |

## Private key protection

The CA private key used to be readable by the web user. `$sslpath` lives under
`$snapindir`, and `configureSnapins()` chowned that whole tree to
`$username:$apacheuser` at mode 775 — so a remote code execution in the PHP
application could read the key the entire installation trusts. It also ran
*after* certificate creation, so setting stricter permissions during
`createSSLCA` had no effect: they were overwritten later in the same install.

`$sslpath` is now excluded from that recursion and the permissions are applied
afterwards, from `_hardenPkiPermissions`:

| File | Mode |
|---|---|
| `pki/root/ca/.fogCA.key` | `0400 root:root` |
| `pki/secureboot/ca/.fogSBCA.key` | `0400 root:root` |
| `pki/web/ca/.fogWebCA.key` | `0600 root:root` |
| `.srvprivate.key` | `0640 root:<apache>` — `certDecrypt()` must read this one |

This is *pseudo-offline*. It protects the keys from a compromise of the web
application, not from a compromise of the machine.

## Taking a key offline

```bash
/opt/fog/bin/fog-offline-ca-key /mnt/vault
/opt/fog/bin/fog-offline-ca-key /mnt/vault --zone secureboot
```

The helper copies the key, verifies the copy still matches the certificate that
stays behind, and only then shreds the original.

**Leave the certificate in place.** Everything chains to it, and the installer
uses its presence to recognise that a CA already exists. Removing the
certificate is what makes the next run mint a fresh one, orphaning every
intermediate beneath it.

Day to day nothing needs the key — only issuing a **new** intermediate does.

## Leaf renewal

The web leaf and the Secure Boot signing leaf default to 5 years — short
enough that a compromised leaf key ages out on its own, long enough that
nothing renews them automatically. To rotate either one sooner:

```bash
/opt/fog/pki/renewal-helper --zone web
/opt/fog/pki/renewal-helper --zone secureboot
```

The web leaf re-issues from the online Web CA (or the root directly, on a
server whose root can't anchor an intermediate) and reloads Apache so it
picks up the new certificate. The Secure Boot leaf re-issues from the Secure
Boot CA and needs no reload — `fog-sign-kernel` reads it fresh from disk on
every signing operation, and nothing has to be re-enrolled in firmware
(that's what the intermediate, not the leaf, being enrolled buys you).

Either invocation refuses and tells you the exact path to restore if the
signing CA's private key isn't on this server (`fog-offline-ca-key` moved
it out, or the Web CA key is simply missing). The web leaf invocation also
refuses if it's ACME-managed (`acmeLeaf=yes`) — renew that one through your
ACME client instead.

Nothing here runs on a timer. Wire it into your own cron if you want
unattended renewal; `installfog.sh` does not install one for you.

## Name constraints

Both intermediates carry `nameConstraints` and an `extendedKeyUsage`, so
neither can issue outside its zone or outside your network. By default:
this server's hostname and domain, plus all RFC1918 ranges.

```bash
./installfog.sh --internal-domain branch.example.local   # repeatable
./installfog.sh --internal-subnet 10.20.30.0/24          # repeatable; REPLACES
                                                         # the RFC1918 default
./installfog.sh --no-sb-name-constraints                 # Secure Boot CA only
```

**Constraints are fixed when the CA is issued, and a CA is never re-issued.**
Renaming the server produces a valid certificate that nothing accepts. The
installer verifies the leaf against its issuer after signing and says so,
naming the `rm -rf` that lets the CA be re-created with the new constraints.

On the Secure Boot CA the constraints are opt-out. They constrain nothing that
matters for code signing and sit in the one certificate UEFI and shim actually
parse, so `--no-sb-name-constraints` exists to make a rejection a re-issue of
one intermediate rather than a re-enrolment of every machine.

A related trap, measured rather than assumed: OpenSSL applies DNS constraints
to the subject **CN** when a certificate carries no DNS SAN. A CN of
`evil.example.com` under a `corp.local` constraint is rejected; the Secure Boot
signing CN passes only because "FOG Project Secure Boot Signing" is not
hostname-shaped. Depending on that would mean a rename of that CN stops the
fleet booting, so the signing leaf carries a permitted DNS SAN.

**If your CA carries `pathlen:0`** it cannot anchor an intermediate. The
installer detects this, says so, signs the web certificate directly from it as
before, and leaves Secure Boot on its self-signed key.

## Secure Boot: servers that already enrolled a MOK

A server that generated a self-signed MOK under an earlier build **is moved
onto the intermediate**, and any machine that enrolled the old key must enrol
once more. The installer says so prominently and leaves the old
`MOK.{key,pem}` on disk so anything signed with it can still be re-signed.

This is deliberate. The flat MOK is a signing certificate that can issue
nothing, so a server left on it can never rotate a signing key and never let a
storage node sign, without a firmware trip to every machine. Doing it before
Secure Boot reached a stable release costs one enrolment; doing it after costs
a fleet.

`sbsign --addcert` embeds the intermediate in the signature so shim can chain
the leaf back to what was enrolled:

```
$ sbverify --list bzImage
 - subject: /CN=FOG Project Secure Boot Signing
   issuer:  /CN=FOG Secure Boot CA
 - subject: /CN=FOG Secure Boot CA
   issuer:  /CN=FOG Server CA
```

## Let's Encrypt and ACME

The historic warning about not letting an ACME client replace
`.srvprivate.key` no longer applies: the web server does not use that file.
That is the concrete payoff of the separation.
