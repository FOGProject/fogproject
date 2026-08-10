# FOG's certificate zones

FOG uses certificates for three unrelated jobs. This describes how they are
separated, how to replace any of them with your own, and what changes on the
endpoints when you do.

> **Status:** applied to every server, including existing ones, on an ordinary
> update. There is no opt-in flag and no second layout to choose between.

## The three zones

| Zone | What it protects | Lifetime | Cost of changing it |
|---|---|---|---|
| **Web TLS** | The browser/API connection to the FOG web UI | 90 days – 1 yr | None. Browsers just need the issuer trusted. |
| **Client Communication** | fog-client's encrypted check-in with the server | 3 – 5 yrs | Medium. Every registered client must re-pin. |
| **Secure Boot** | The signature on the FOS kernels | 10 – 20 yrs | High. Firmware re-enrollment on every machine. |

They have nothing in common except that FOG generates all three, and their
costs differ by orders of magnitude — which is exactly why they should not
share key material.

## Why they were separated

In the historic layout one self-signed CA did the first two jobs, and one
self-signed leaf did the third. That produced two problems that look
unrelated but have the same shape:

**`.srvprivate.key` was the web server's TLS key *and* the key that decrypts
every fog-client handshake.** `FOGBase::certDecrypt()` opens that exact path
on every `authorize()` call. So an ACME renewal with `--key-file`, a purchased
certificate dropped in place, or `--recreate-keys` installed a perfectly valid
certificate and silently broke client authentication, with nothing in the logs
connecting the two.

Confirmed on a real server: `openssl` moduli showed `.srvprivate.key` paired
with `srvpublic.crt` (the web leaf), not with `ca.cert.pem` (the CA).

**The enrolled Secure Boot MOK was the signing certificate itself.** Because
the thing in the firmware was a leaf that can issue nothing, rotating or
revoking the signing key meant a physical MokManager trip to every machine,
and a storage node could not sign kernels at all without being handed the one
key the entire fleet trusts.

Both are the same mistake: one file serving as both a *trust anchor* and an
*operational key*, so the thing you must never change and the thing you want
to change routinely are the same object.

## The layout

```mermaid
graph TD
    Root["FOG Server CA<br/>self-signed · the existing CA · 30y<br/>published as ca.cert.der"]
    Root --> WebCA["FOG Web CA<br/>serverAuth · name-constrained"]
    Root --> SBCA["FOG Secure Boot CA<br/>codeSigning · name-constrained"]
    Root --> Comm["srvpublic.crt + .srvprivate.key<br/>encrypts client check-ins"]

    WebCA --> WebLeaf["web server certificate<br/>served by Apache/nginx"]
    WebCA --> NodeLeaf["storage node certificates"]
    SBCA --> MOK["MOK.der<br/>enrolled in firmware ONCE"]
    SBCA --> Sign["code-signing leaf<br/>rotatable without re-enrollment"]
```

The anchor is the CA your server already has. Nothing above it is created, so
`ca.cert.der` does not change, no fog-client re-pins, and an existing server
gets the separation on an ordinary update.

That also has a useful consequence: because the certificate fog-client pins
**is** the root, the Web CA sits beneath something every client already
trusts. Trusting `ca.cert.der` now validates the web certificate too.

Under `$fogprogramdir/pki/` (default `/opt/fog/pki/`), one subfolder per zone,
each split into `ca/` (the zone's own CA material) and `leaf/` (what that CA
issues day to day) — everything is a dotfile (`ls -a`):

```
root/ca/.fogCA.{key,pem}          the anchor. Key never regenerated, 0400 root:root.
                                   .fogCA.pem is a symlink to wherever the
                                   certificate already lived before this split.
root/leaf/.srvprivate.key         symlink -> $sslpath/.srvprivate.key
root/leaf/.srvpublic.crt          symlink -> $sslpath/.srvpublic.crt
                                   (the comm leaf's real files stay at
                                   $sslpath -- see "Why they were separated")
web/ca/.fogWebCA.{key,pem}        signs the vhost's certificate and node certificates
web/ca/.fogWebCAchain.pem         CA + web intermediate
web/leaf/.webLeaf.{key,pem}       what the web server actually serves
secureboot/ca/.fogSBCA.{key,pem,der}  signs the code-signing leaf; .der is
                                  the same certificate MOK.der publishes, kept
                                  here so it can be verified without reaching
                                  into the web root
secureboot/leaf/sign.{key,pem}    what sbsign actually signs with
secureboot/MOK.{key,pem}          the flat, no-intermediate fallback signing
                                  key -- present only on a root that can't
                                  anchor an intermediate, or before one has
                                  been minted
secureboot/PK.{key,pem}           the platform key, if automatic PK/KEK/db
secureboot/KEK.{key,pem}          enrolment is configured. Never regenerated.
secureboot/admin-MOK.{key,pem}    an admin-supplied --secure-boot-key/-cert
                                  pair, copied in so it survives independent
                                  of wherever the admin originally put it
secureboot/mscerts/               Microsoft's published CA certs, staged here
                                  for the PK/KEK/db builder; fully reproducible
                                  from packages/secureboot/mscerts
```

Nothing PKI-related is left directly under `$fogprogramdir/secureboot` any more
-- an install that already had one migrates every one of the files above into
`pki/secureboot/` in place, and the old directory is left empty once that's
done (safe to remove by hand). `$fogprogramdir/secureboot-staging` is a
separate, web-user-writable directory for in-flight kernel-signing requests --
unrelated key material, and deliberately not part of this tree.

`.srvprivate.key`/`.srvpublic.crt` themselves stay exactly where they have
always been, at `$sslpath` — `root/leaf/` only adds discoverability symlinks
to them, so nothing under `pki/` is flat while the comm keypair's real files
never move.

An install that already ran an earlier layout (flat `CA/.fogCA.*` directly
under `$sslpath`, or the intermediate one-level-down `CA/web/.fogWebCA.*`
split) migrates its key/cert material into the new tree in place on the next
run — no re-issuing, and the old paths keep resolving via symlink where
anything might still reference them directly.

## What an upgrade does and does not change

| | |
|---|---|
| `pki/root/ca/.fogCA.pem` | **unchanged**, byte for byte |
| `ca.cert.der` | **unchanged** — no client re-pins |
| `.srvprivate.key` | **unchanged** — client authentication is unaffected |
| `srvpublic.crt` | the same certificate, adopted rather than re-issued |
| the web certificate | **new**, issued by the Web CA, on its own keypair |
| the Secure Boot MOK | **new** — see below, this one needs action |

The only endpoint-visible change is Secure Boot.

## Private key protection

The CA private key used to be readable by the web user. `$sslpath` lives under
`$snapindir`, and `configureSnapins()` chowned that whole tree to
`$username:$apacheuser` at mode 775 — so a remote code execution in the PHP
application could read the key the entire installation trusts. It also ran
*after* certificate creation, so setting stricter permissions during
`createSSLCA` had no effect at all: they were overwritten later in the same
install.

`$sslpath` is now excluded from that recursion and the permissions are applied
afterwards, from `_hardenPkiPermissions`:

| File | Mode | Why |
|---|---|---|
| `pki/root/ca/.fogCA.key` | `0400 root:root` | nothing on a running server needs it |
| `pki/secureboot/ca/.fogSBCA.key` | `0400 root:root` | same |
| `pki/web/ca/.fogWebCA.key` | `0600 root:root` | used only by root, through the sudo helper |
| `.srvprivate.key` | `0640 root:<apache>` | `certDecrypt()` must read this one |

The **Certificates** page under FOG Configuration re-runs that check from
inside the web application, which is the only place it can be answered
honestly: if PHP can open one of those keys, PHP is what would leak it.

This is *pseudo-offline*. It protects the keys from a compromise of the web
application, not from a compromise of the machine.

## Taking a key offline

```bash
/opt/fog/bin/fog-offline-ca-key /mnt/vault                  # the CA key
/opt/fog/bin/fog-offline-ca-key /mnt/vault --zone secureboot
```

The helper copies the key, verifies the copy still matches the certificate
that stays behind, and only then shreds the original.

**Leave the certificate in place.** Everything chains to it, and the installer
uses its presence to recognise that a CA already exists. Removing the
certificate is what makes the next run mint a fresh one, orphaning every
intermediate beneath it — which is precisely the mistake the obvious manual
version ("move the CA out of the way") makes.

Day to day nothing needs the key. It is required only to issue a **new**
intermediate, or a certificate for a **new** storage node. The installer
detects its absence and says what to restore rather than failing somewhere
inside openssl:

```
 * Cannot issue 'FOG Web CA': the Root CA private key is not on this server
 * That is the correct state for an offline root, but issuing a new
   intermediate needs it. Restore it to:
     /opt/fog/pki/root/ca/.fogCA.key
   re-run the installer, then move it back to your vault.
```

> Removing the key does **not** cause the CA to be regenerated. That is worth
> stating because the obvious implementation gets it wrong — testing for "key
> and cert both present" would mint a fresh CA the first time anyone followed
> this advice.

Before offlining the Secure Boot key, issue signing certificates to every
storage node that needs one. Restoring it later for a new node is supported,
but it is a trip to the vault.

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
signing CA's private key isn't on this server (`fog-offline-ca-key` moved it
out, or the Web CA key is simply missing). The web leaf invocation also
refuses if it's ACME-managed (`acmeLeaf=yes`) — renew that one through your
ACME client instead.

Nothing here runs on a timer. Wire it into your own cron if you want
unattended renewal; `installfog.sh` does not install one for you.

## Name constraints

Both intermediates are issued with `nameConstraints` and an
`extendedKeyUsage`, so neither can issue outside its zone or outside your
network:

```
Web CA:          extendedKeyUsage = serverAuth
Secure Boot CA:  extendedKeyUsage = codeSigning
both:            permitted DNS: this server's hostname and domain
                 permitted IP:  all RFC1918 ranges, plus this server's own
```

Extend or narrow with:

```bash
./installfog.sh --internal-domain branch.example.local   # repeatable
./installfog.sh --internal-subnet 10.20.30.0/24          # repeatable; REPLACES
                                                         # the RFC1918 default
```

Three things about this are easy to get wrong and cost a chain that verifies
nowhere:

- OpenSSL wants an IP subtree as `address/netmask`, never `address/prefixlen`.
  `10.0.0.0/8` does not build.
- RFC 5280 constrains only the name *types* present in `permittedSubtrees`, so
  DNS and IP are always emitted together. A certificate with an IP SAN under a
  DNS-only constraint is a violation.
- IPv4 and IPv6 subtrees never match each other, so a dual-stack server needs
  both. FOG emits the IPv6 entries only when the server actually has IPv6.

**Constraints are fixed when the CA is issued, and a CA is never re-issued.**
Renaming the server, or adding an `--extra-server-name` outside the permitted
domains, produces a valid certificate that nothing accepts. The installer
verifies the leaf against its issuer after signing and says so, naming the
`rm -rf` that lets the CA be re-created with the new constraints.

**On the Secure Boot CA the constraints are opt-out**, via
`--no-sb-name-constraints`. They constrain nothing that matters for code
signing — a code-signing leaf carries no names anyone resolves — and they sit
in the one certificate UEFI and shim actually parse. The flag exists so that a
fleet which rejects the chain is a re-issue of one intermediate rather than a
re-enrolment of every machine.

A related trap, measured rather than assumed: OpenSSL applies DNS constraints
to the subject **CN** when a certificate carries no DNS SAN. A CN of
`evil.example.com` under a `corp.local` constraint is rejected; the Secure Boot
signing CN passes only because "FOG Project Secure Boot Signing" is not
hostname-shaped and so is never read as a DNS name. Depending on that would
mean a rename of that CN stops the fleet booting, so the signing leaf carries a
permitted DNS SAN.

## Bringing your own CA

Each zone is independently replaceable.

```bash
# Web zone -- your PKI issues the web certificate, FOG keeps the rest
./installfog.sh --web-ca-cert /etc/pki/web-int.pem \
                --web-ca-key  /etc/pki/web-int.key \
                --web-ca-root /etc/pki/root.pem

# Secure Boot zone -- your own intermediate is what firmware enrols
./installfog.sh --secureboot-ca-cert /etc/pki/sb-int.pem \
                --secure-boot-key    /etc/pki/sb-leaf.key \
                --secure-boot-cert   /etc/pki/sb-leaf.pem
```

`--external-ca`/`--ca-cert`/`--ca-key`/`--ca-root` still work and target the
**Web** zone, which is what they have always effectively meant.

**The Client Communication zone is not replaceable this way, deliberately.**
It is anchored at the certificate every fog-client has pinned, so replacing it
means re-deploying trust to every registered machine. That is possible — push
the new `ca.cert.der` by GPO or by reinstalling fog-client — but there is no
built-in path for it, because there is no way to do it without touching every
endpoint.

**If your CA carries `pathlen:0`** — an ordinary thing for an enterprise to
issue — it cannot anchor an intermediate. The installer detects this, says so,
signs the web certificate directly from it as before, and leaves Secure Boot on
its self-signed key. Nothing is silently broken.

## Storage nodes

A storage node used to generate its own independent self-signed
`FOG Server CA`, so a fleet of five nodes had six unrelated CAs. Nodes now ask
the master for a certificate from the Web CA.

The node authenticates with the fogstorage database password it already holds
— the same secret it uses to reach the master's database — so nothing new has
to be distributed. The master issues for the names in **its own record** of
that node; the node's request supplies a public key and nothing else, so a node
cannot obtain a certificate covering the master or another node.

Two consequences worth knowing:

- **The node must be registered first.** The request runs after
  `registerStorageNode` for exactly this reason. A node the master does not
  know is refused, by design.
- **Any failure falls back to a self-signed certificate**, exactly as before,
  with an explanation. A node install must not break against a master that has
  not been updated yet.

Issuance is logged to the web server error log on the master.

## Certificate paths

FOG's own consumers — the vhost, `sbsign`, `certDecrypt()` — only ever
reference fixed canonical paths. Those paths may be symlinks, so the real
files can live wherever you keep certificates:

```bash
sed -i "s|^sslprivkey=.*|sslprivkey='/etc/pki/fog/server.key'|" /opt/fog/.fogsettings
./installfog.sh -Y
```

Relocating a certificate then never means editing the vhost.

Two things that bite: SELinux labels follow the symlink **target**, so a
certificate outside the expected directories may need `restorecon` or
`semanage fcontext` on the real path. And a private key relocated into a
world-readable directory silently defeats the separation the `fog-sign-kernel`
sudo helper depends on.

> `.srvprivate.key` is no longer one of these relocatable pointers. It is the
> communication key itself. If your `sslprivkey` used to point elsewhere, the
> installer copies the key material into a file FOG owns and lets your own file
> carry on as the *web* key — so an ACME renewal writing it no longer changes
> what `certDecrypt()` reads.

## Let's Encrypt and ACME

**FOG does not run an ACME client and will not.** Use `certbot`, `acme.sh`, or
whatever your site already runs, and point its install hook at the paths FOG's
vhost reads. Full walkthrough in
[EXTERNAL_CA_AND_LETSENCRYPT.md](EXTERNAL_CA_AND_LETSENCRYPT.md).

Set `acmeLeaf="yes"` in `/opt/fog/.fogsettings` so the installer stops
regenerating the leaf, and so it leaves the permissions on your key alone.

> The historic warning about not letting an ACME client replace
> `.srvprivate.key` no longer applies: the web server does not use that file.
> This is the concrete payoff of the separation.

### Recipe: using acme.sh for the web leaf instead

This is one option among several, not a default — nothing here is installed
or configured automatically. If you'd rather have a publicly-trusted
certificate on the web leaf than FOG's own Web CA, `acmeLeaf=yes` above is the
escape hatch. [acme.sh](https://github.com/acmesh-official/acme.sh) is a
reasonable lightweight client for that — no daemon, no separate CA to run.

**Pick a challenge type first.** Two options, and which one fits depends on
your DNS, not on how you'd like the certificate issued:

- **DNS-01** (usually the better fit for a LAN-only server): the ACME CA looks
  up a `_acme-challenge.<hostname>` TXT record on your domain's *public*
  authoritative nameservers. It never contacts the FOG server or your internal
  resolver at all, so this needs zero inbound connectivity to the box. It only
  works if the hostname is under a domain you manage in public DNS — even if
  the actual A record is never published, or only resolves internally on your
  LAN. acme.sh has around 140 built-in DNS provider plugins (`--dns dns_cf`
  for Cloudflare, and similar for Route53/Azure/etc.) that fully automate
  creating and removing that record.
- **HTTP-01**: needs port 80 reachable from whatever ACME server you point
  acme.sh at. Only helps if that port is actually reachable from the CA,
  which rules it out for most LAN-only boxes.

Neither path requires or assumes an internal CA like step-ca — point acme.sh's
`--server` at one if you already run one, but it's not needed for either
recipe above.

**Issue:**
```bash
acme.sh --issue -d fog.example.com --dns dns_cf        # DNS-01
acme.sh --issue -d fog.example.com -w /var/www/html    # HTTP-01, docroot
```

**Install into the paths FOG already serves from**, rather than acme.sh's own
default cert store, so nothing else needs to change:
```bash
acme.sh --install-cert -d fog.example.com \
    --key-file       /opt/fog/pki/web/leaf/.webLeaf.key \
    --cert-file      /opt/fog/pki/web/leaf/.webLeaf.pem \
    --ca-file        /opt/fog/pki/web/ca/.fogWebCAchain.pem \
    --reloadcmd      "systemctl reload httpd"     # apache2 on Ubuntu
```
`--cert-file` (leaf only) maps to `sslpubcert`; `--ca-file` (intermediate
only) maps to `sslcachain` — matching Apache's
`SSLCertificateFile`/`SSLCertificateChainFile` split. Don't use
`--fullchain-file` for `sslpubcert`, or the vhost ends up listing the
intermediate twice.

**Tell FOG about it**, once, in `.fogsettings`:
```
acmeLeaf=yes
sslprivkey=/opt/fog/pki/web/leaf/.webLeaf.key
sslpubcert=/opt/fog/pki/web/leaf/.webLeaf.pem
sslcachain=/opt/fog/pki/web/ca/.fogWebCAchain.pem
```
Reusing the exact default paths above is what makes `_resolveWebLeafPaths()`
recognize them as already-yours and leave them alone on every later
`installfog.sh` run; `sslcachain` gets the same treatment from
`createWebIntermediateCA()`.

**Renewal** is acme.sh's own cron entry — the `--reloadcmd` above is what
picks up each renewed certificate. `renewal-helper --zone web` already
refuses on an ACME-managed leaf; use `acme.sh --renew -d fog.example.com
--force` instead if you ever need to force one.

## HTTPS and netboot

iPXE can only validate a chain ending in a **public** root, through its
`ca.ipxe.org` cross-signing fallback. It cannot be told to trust anything.

| Web certificate issued by | Web UI / API / fog-client | iPXE netboot |
|---|---|---|
| Public CA (Let's Encrypt) | HTTPS, trusted natively | **HTTPS works**, FQDN only |
| FOG's own PKI | HTTPS once `ca.cert.der` is trusted | HTTP |
| Your internal PKI | HTTPS once your root is trusted | HTTP |

On a **fresh** install with `httpproto=https` and FOG's own PKI, netboot stays
on HTTP automatically while everything else is HTTPS. That avoids the historic
trade where enabling HTTPS meant rebuilding iPXE with the CA baked in — which
works, and forfeits the signed Secure Boot shim, because a locally rebuilt
binary is not the signed one.

An **existing** server keeps whatever it has been doing. One already running
HTTPS netboot has a `TRUST=`-rebuilt iPXE making it work, and dropping it to
HTTP would break a working setup to fix a problem its admin does not have.
Override either way with `--netboot-proto http|https`.

**Public Let's Encrypt for netboot** works only on an FQDN in a domain you
control — it need not be publicly reachable, DNS-01 is enough — and only on
that exact FQDN, not a short hostname and not an IP. Set `FOG_WEB_HOST` to
that FQDN or the generated boot URLs will not match the certificate.

## Secure Boot

The Secure Boot zone follows the same shape as the web zone: the CA issues a
**FOG Secure Boot CA**, that intermediate is what gets enrolled in firmware
(`MOK.der`), and it issues a short-lived **code-signing leaf** that actually
signs the FOS kernels. `sbsign --addcert` embeds the intermediate in the
signature so shim can chain the leaf back to what was enrolled.

The point is rotation. Under the flat model the enrolled certificate *is* the
signer, so replacing a signing key means a physical MokManager trip to every
machine, and a storage node cannot sign at all without holding the fleet's one
trusted key. Enrolling the issuer instead means leaves can be rotated, revoked
or issued per node while the fleet keeps booting.

Verified on a real server: the chain verifies, the leaf carries the
`codeSigning` EKU, `MOK.der` publishes the **intermediate**, and a signed
kernel contains **both** certificates:

```
$ sbverify --list bzImage
 - subject: /CN=FOG Project Secure Boot Signing
   issuer:  /CN=FOG Secure Boot CA
 - subject: /CN=FOG Secure Boot CA
   issuer:  /CN=FOG Server CA
```

**Confirmed on real UEFI hardware, both enrolment routes:** machines boot
FOG's leaf-signed kernels while trusting only the **intermediate** — whether
that intermediate is enrolled as `MOK.der` through MokManager, or written into
`db` through the Setup Mode PK/KEK/db path. Firmware and shim both accept a
chain terminating at the enrolled CA rather than demanding the exact signer.

> That verification predates the `nameConstraints` extension now carried on the
> Secure Boot CA. Re-confirm both routes on hardware before relying on it, and
> use `--no-sb-name-constraints` if a fleet rejects the chain.

### Servers that already enrolled a MOK

A server that generated a self-signed MOK under an earlier build **is moved
onto the intermediate**, and any machine that enrolled the old key must enrol
once more. The installer says so, prominently, and leaves the old
`MOK.{key,pem}` on disk so anything signed with it can still be re-signed.

This is deliberate, and it is why the change landed when it did. The flat MOK
is a signing certificate that can issue nothing, so a server left on it can
never rotate a signing key and never let a storage node sign, without a
firmware trip to every machine. Doing it before Secure Boot reached a stable
release costs one enrolment; doing it after costs a fleet.

### efitools

**`efitools` is unreliable on EL9 and should not be assumed present.** It is a
declared dependency and installs normally on Debian/Ubuntu. On EL9:

- On the **CentOS Stream 9** test box it is unavailable with EPEL *and* CRB
  enabled, and nothing else provides
  `sign-efi-sig-list`/`cert-to-efi-sig-list`.
- The upstream RPM tracker
  ([rpms.remirepo.net](https://rpms.remirepo.net/rpmphp/zoom.php?rpm=efitools))
  lists **Fedora branches only** — no EL9/EPEL rows at all.
- It is nonetheless present and working on at least one **Rocky 9** FOG
  server, source not established.

Only the three userspace tools are needed, and they build in about a minute:

```bash
dnf -y install gcc make openssl-devel git gnu-efi-devel
git clone --depth 1 \
    https://git.kernel.org/pub/scm/linux/kernel/git/jejb/efitools.git
cd efitools
make cert-to-efi-sig-list sign-efi-sig-list efi-updatevar
install -m 0755 cert-to-efi-sig-list sign-efi-sig-list efi-updatevar /usr/bin/
```

`gnu-efi-devel` is required even for the userspace tools — they include
`efi.h`. The EFI binaries (`KeyTool.efi` et al.) are not needed.

**Verified with those tools present:** the installer builds `PK.auth`,
`KEK.auth` and `db.auth`, and `db.auth` embeds `CN=FOG Secure Boot CA` — the
**intermediate** — beside Microsoft's CAs, with the signing leaf's CN absent.
That is what makes leaf rotation safe for Setup-Mode-enrolled clients too.

MOK enrolment via MokManager is unaffected either way; only the unattended
Setup Mode path needs those tools.

## Known follow-up: which certificate fog-client installs

During installation fog-client adds the certificate it pins to the Windows
Root store. Under the historic layout that was the single flat CA, which is
also what this layout anchors at — so nothing is broken and nothing needs to
change for this release.

It is worth stating what that now buys, because it was previously listed as a
blocker. The pinned certificate **is** the root of the whole hierarchy, so a
client that trusts it also trusts the web certificate the Web CA issues.
Confirmed by hand earlier: adding that certificate to the Windows trust store
makes HTTPS work. Under the previous four-tier design this required a change in
the `zazzles` repository to pin the root rather than an intermediate. It no
longer does.

## Still unverified

- **nginx.** Every vhost change was exercised on Apache only. The managed-block
  splice and the `netbootproto` redirect exclusion both have nginx branches
  that have never executed.
- **Secure Boot with name constraints on hardware.** See the note above.
- **Node certificate issuance against a real second machine.** The endpoint,
  the signing helper and the HMAC agreement between installer and endpoint are
  each verified in isolation; the two halves have not been run against each
  other across a network.
