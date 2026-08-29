# Context: PKI zones + customization preservation

Continuation notes for picking this work up in a new session. Companion to the
design/plan docs under `docs/superpowers/`.

## What this is

Branches off `working-1.6`:

| Branch | Contains |
|---|---|
| `customization-preservation` | Install-time preservation of admin customizations |
| `pki-three-zone-phase1` | The **superseded** four-tier PKI, stacked on the above |
| `pki-additive-intermediates` | The shipping PKI, stacked on `pki-three-zone-phase1` |

`pki-additive-intermediates` contains everything. A PR from it brings all three.

## Why the PKI was redesigned mid-flight

`pki-three-zone-phase1` minted a **new** `FOG Server ROOT CA` above a
`FOG Server CA` client intermediate. It worked and was verified on hardware,
but the new root could only ever apply to fresh installs: an existing server
cannot adopt a new anchor without pushing it to every fog-client first. So the
separation never reached a server already in the field, which is where the
problem actually is.

The shipping design keeps the **existing** CA as the anchor and hangs two
constrained intermediates off it:

```
FOG Server CA                     existing, unchanged, published as ca.cert.der
├── FOG Web CA                    serverAuth  + name constraints
├── FOG Secure Boot CA            codeSigning + name constraints
└── .srvprivate.key/srvpublic.crt the comm keypair, left exactly where it was
```

An ordinary update now applies it everywhere. Confirmed by test: the CA
certificate, `ca.cert.der` and the communication private key are all
byte-identical across the upgrade, and `srvpublic.crt` is adopted rather than
re-issued.

It also resolved a follow-up for free. The old design needed a `zazzles` change
so fog-client would pin the root rather than an intermediate; here the pinned
certificate **is** the root, so a client that trusts it already trusts the web
certificate. Nothing outside this repo has to change.

`--split-pki`, `--legacy-pki`, `--root-ca-*`, `--client-ca-*`, `pkiMode` and
the `commLeaf` branch in `certDecrypt()` are all gone. There is one layout.

## Two bugs found on the way, both pre-existing

**The CA private key was readable by the web user.** `$sslpath` lives under
`$snapindir`, and `configureSnapins()` chowns that tree to
`$username:$apacheuser` at 775 — *after* `createSSLCA`, so any permission set
during certificate creation was silently reverted later in the same install.
Fixed by pruning `$sslpath` from the recursion and hardening afterwards, from
`_hardenPkiPermissions`. The **Certificates** page under FOG Configuration
re-runs the check from inside PHP, which is the only place it can be answered
honestly.

**The web certificate was re-signed on every run.** The guard was
`[[ ! -x $sslpubcert ]]`, which is true of every certificate ever written.
Harmless while one key did every job; fatal once the signer can be taken
offline. It is now re-issued only when the name set changes, tracked by a hash
of `ca.cnf`.

## Verified

Against the real functions in `createSSLCA`'s own order (not per-function —
see Lessons below):

- Fresh install produces the full hierarchy; every chain verifies; the web key
  is provably a different keypair from the comm key.
- Upgrade from a flat install: CA, `ca.cert.der` and `.srvprivate.key`
  byte-identical; `srvpublic.crt` adopted from the backup `configureHttpd`
  makes before wiping the web tree.
- Three consecutive runs re-issue nothing.
- An offlined CA key neither regenerates the CA nor fails the run.
- A `pathlen:0` CA declines to issue intermediates and falls back to signing
  the web certificate directly, exactly as before.
- A rename outside the name constraints is detected after signing and warns
  with the `rm -rf` that fixes it.
- Name constraints bind: an out-of-scope leaf is rejected by `openssl verify`,
  an in-scope one passes.
- The installer's HMAC and `nodecert.php`'s agree byte for byte.
- `fog-sign-node-cert` refuses a traversal request id, a command substitution,
  an unknown type, a name list smuggling an openssl config section, a request
  with no DNS name, and a name outside the CA's constraints — leaving no
  certificate behind in the last case.

Earlier, on the real dev server (fog-dev, CentOS Stream 9, Apache), against the
superseded four-tier layout: fresh install, `--legacy-pki`, existing-install
upgrade, signed kernels carrying leaf + intermediate, and custom vhost blocks /
renamed backgrounds / custom kernel names surviving repeated installs.

## NOT verified — read this before shipping

1. **Secure Boot with name constraints, on hardware.** Both enrollment routes
   (MokManager and Setup Mode `db`) were confirmed on real UEFI hardware
   *before* `nameConstraints` was added to the Secure Boot CA. That extension
   is critical, and it sits in the one certificate firmware and shim actually
   parse. **This is the release gate.** `--no-sb-name-constraints` exists so a
   rejection is a flag flip and a re-issue of one intermediate, not a
   re-enrollment of a fleet.

   Measured, and the reason the signing leaf now carries a DNS SAN: OpenSSL
   applies DNS constraints to the subject CN when a certificate has no DNS SAN.
   `CN=evil.example.com` under a `corp.local` constraint is rejected; the
   signing CN passes only because it contains spaces and so is never read as a
   hostname. Depending on that quirk would mean renaming that CN stops the
   fleet booting.

2. **Existing servers that enrolled the old self-signed MOK must re-enroll.**
   Deliberate, and the reason this landed before Secure Boot reached stable —
   after that, the same change costs a firmware trip to every machine. The
   installer prints a prominent notice and leaves `MOK.{key,pem}` on disk.

3. **Node certificate issuance across a network.** The endpoint, the signing
   helper, and the HMAC agreement are each verified in isolation. The two
   halves have never been run against each other on two machines. Every failure
   path falls back to the self-signed certificate nodes have always used, so a
   node install cannot break against an un-updated master — that fallback is
   the thing to check first if this misbehaves.

4. **nginx.** All vhost work was verified on Apache only. The managed-block
   splice and the `netbootproto` redirect exclusion both have nginx branches
   that have never executed.

5. **PXE boot** was not testable from this shell, but the earlier Secure Boot
   hardware verification necessarily exercised it.

## Lessons that cost real bugs

Eleven bugs were found by running against a live server; **none** were caught
by sandbox testing. The recurring cause: harnesses reproduced the *function*
but not the *sequence that calls it*.

- `spliceManagedBlock` wiped custom content, because `createSSLCA` `mv`s the
  file aside before calling it — the sandbox always had the file in place.
- `customizationsDir` resolved at source time, before `$fogprogramdir`
  existed, writing backups to `/customizations` at the filesystem root.
- Kernel backup swept in FOG's own PHP and reverted `boot.php` every update.
- `_ensureSecureBootKeys` runs *before* `createSSLCA`, so `pkiMode` and
  `$sslpath` were unset and the split branch silently never matched.
- xattrs survive in-place overwrite, so an "is this file FOG's?" test based on
  their presence missed the exact case it was written for. Checksums fixed it.
- `configureSnapins` runs *after* `createSSLCA` and reverted every permission
  it set — which is why the CA key was web-readable for years.
- The node certificate request had to move out of `createSSLCA` entirely: the
  master only issues to a registered node, and a node registers at the very end
  of its own install, so every first install was refused.

If you touch any of this, test the caller's ordering, not just the function.
The harness in this session sourced `functions.sh` and replayed `createSSLCA`'s
exact call order, which is the minimum that finds these.

## Useful commands

```bash
# on the dev server
cd /home/fog/fogInstalls/git/bin
./installfog.sh --uninstall --purge-ssl --force   # clean slate incl. CA
rm -rf /opt/fog/secureboot                        # also reset Secure Boot
./installfog.sh -Y

# verify the hierarchy
openssl verify -CAfile /opt/fog/snapins/ssl/CA/.fogCA.pem \
  -untrusted /opt/fog/snapins/ssl/CA/web/.fogWebCA.pem \
  /opt/fog/snapins/ssl/CA/web/.webLeaf.pem
openssl verify -CAfile /opt/fog/snapins/ssl/CA/.fogCA.pem \
  /opt/fog/snapins/ssl/.srvpublic.crt
sbverify --list /var/www/html/fog/service/ipxe/bzImage

# the permissions that must survive a second and third run
stat -c '%U:%G %a' /opt/fog/snapins/ssl/CA/.fogCA.key      # root:root 400
stat -c '%U:%G %a' /opt/fog/snapins/ssl/.srvprivate.key    # root:<apache> 640
stat -c '%U:%G %a' /opt/fog/secureboot/ca/.fogSBCA.key     # root:root 400

# take a key to a vault
/opt/fog/bin/fog-offline-ca-key /mnt/vault
/opt/fog/bin/fog-offline-ca-key /mnt/vault --zone secureboot
```

Everything under `$sslpath` is a **dotfile** — `ls -a` or it looks empty.

## Known bugs / unfinished, reported but NOT yet acted on

**Kernel/init dropdown still lists non-kernels.** `FOGPage::kernelFileList()`
(`packages/web/lib/fog/fogpage.class.php`) filters by excluding `.php`, image
extensions, `.conf`, `.efi` and `.unsigned`, then treats everything remaining
that is not init-shaped as a kernel. That still leaves `memdisk`, `grub.exe`
and `memtest.bin` in the Host/Group Kernel dropdown, where they are not
bootable choices.

The exclusion-by-extension approach is the wrong shape — it was a quick
narrowing of an even broader bug (the list originally included `boot.php` and
`bg.png`). Better: subtract what FOG ships, the same rule
`backupPreservedCustomizations()` already uses successfully — read
`packages/web/service/ipxe` from the source tree and treat anything present
there as not-a-kernel. `memdisk`/`memtest.bin`/`grub.exe` are all shipped, so
they would fall out automatically, while a custom kernel of any name survives.

Caveat: `FOG_MEMTEST_KERNEL` legitimately wants `memtest.bin`, so that one
setting needs its own list rather than the general kernel list.

**Dropdown wanted for the default kernel/init too.** It was applied to
`FOG_TFTP_PXE_KERNEL`/`_32`/`_ARM`/`FOG_MEMTEST_KERNEL` on the FOG
Configuration page, but the reported experience is that a dropdown is still
missing where the default is selected — verify which field was meant before
changing anything. There is no global default *init* setting today; inits are
per-host/group only, which may be part of the gap.

## Still open

- The dev-branch port: the PKI hierarchy, key isolation and Secure Boot
  intermediate, but **not** the node endpoint and **not** the db/KEK enrollment
  task (1.6 only). dev-branch lacks `--external-ca`, the managed vhost block
  and `_pkiZoneDir`/`_linkCanonical`, and `createSSLCA` there hardcodes
  `/opt/fog/snapins/ssl/`, so this is a port rather than a cherry-pick.
- The update-related settings and `profile.d` work, deliberately deferred.
