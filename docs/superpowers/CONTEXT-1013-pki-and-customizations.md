# Context: three-zone PKI + customization preservation

Continuation notes for picking this work up in a new session. Written
2026-08-07. Companion to the design/plan docs under `docs/superpowers/`.

## What this is

Two stacked branches off `working-1.6` (base commit `1f9306fe0`):

| Branch | Contains |
|---|---|
| `customization-preservation` | Install-time preservation of admin customizations |
| `pki-three-zone-phase1` | The PKI work, **stacked on top of the above** |

The PKI branch contains everything. A PR from it brings both.

## Why it was done in this order

The PKI work needed the vhost managed-block from the customization branch:
splitting the web certificate out of the client-communication path is only
useful if an admin's own `SSLCertificateFile` survives an upgrade, which it
did not before.

## The finding that drove the PKI work

`.srvprivate.key` is **both** the web vhost's TLS private key and the key
`FOGBase::certDecrypt()` opens on every fog-client `authorize()` handshake.
Confirmed on a live server by modulus comparison — it pairs with
`srvpublic.crt`, not `ca.cert.pem`.

Consequence, present in FOG today and independent of this work: overwriting
that file — an ACME renewal with `--key-file`, `--recreate-keys`, a purchased
cert dropped in place — breaks client authentication while installing a
perfectly valid certificate, with nothing in the logs connecting the two.
Pointing the vhost at a *different* file is safe; overwriting FOG's is not.

The split PKI removes the trap by giving client communication its own keypair.

## State

Default on a **fresh** install is now the split PKI:

```
FOG Server ROOT CA
├── FOG Web CA          → web server certificate (vhost)
├── FOG Server CA       → ca.cert.der (pinned by fog-client)
│   └── FOG Client Communication → the key certDecrypt() opens
└── FOG Secure Boot CA  → MOK.der (enrolled in firmware)
    └── FOG Project Secure Boot Signing → signs kernels, --addcert'd
```

An **existing** server (`caCreated=yes`) always resolves to `flat` and is
never switched automatically. `--split-pki` opts in; `--legacy-pki` opts a
fresh install out. Both are fully supported.

## Verified on a real server (fog-dev, CentOS Stream 9, Apache)

- Fresh install with no flags produces the full split hierarchy; all chains
  verify; `pkiMode='split'` persists.
- The comm key and vhost key are provably different keypairs, and
  `srvpublic.crt` matches the comm key.
- `--legacy-pki` produces the original single `CN=FOG Server CA`, zero split
  directories.
- Existing-install upgrade leaves the CA byte-identical and creates no split
  directories.
- Signed kernel carries both the leaf and the Secure Boot intermediate.
- Custom vhost block, renamed background, and custom-named kernels all
  survive repeated installs; FOG's own shipped files are never reverted.

## NOT verified — read this before shipping

1. **No real fog-client has authenticated against a split server.** The comm
   certificate is published at `srvpublic.crt`, the path the client has always
   fetched, so no client change is expected. Expected is not observed. One
   host checking in settles it. If the client instead derives its key from
   `ca.cert.der`, the fix is to let the Client CA double as the comm keypair.
2. **Shim has never been asked to boot a leaf-signed kernel.** The chain is
   built correctly and both certs are embedded, but no UEFI hardware has
   validated it. If it fails, point `secureBootMokCert` at the leaf — one
   variable — and behaviour reverts to today's.
3. **The `db`/Setup-Mode path is untested.** `efitools` was unavailable on the
   CentOS Stream 9 test box even with EPEL and CRB enabled, so
   `fog-build-sb-authvars` never ran with the new `SECUREBOOT_MOK_CERT`. It
   installs normally on Rocky 9 and Debian/Ubuntu — verify there.
4. **nginx is untested.** All vhost work was verified on Apache only. The
   managed-block splice and the `netbootproto` redirect exclusion both have
   nginx branches that have never executed.
5. **PXE boot untested**, and not testable from a shell.

## Timing note

Secure Boot has **not yet shipped in a stable release** (targeted for the
11th). That is why the intermediate landed now: with no release out, no fleet
has enrolled a MOK, so restructuring costs nothing. After a stable ships, the
same change costs a firmware trip to every enrolled machine.

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

If you touch any of this, test the caller's ordering, not just the function.

## Useful commands

```bash
# on the dev server
cd /home/fog/fogInstalls/git/bin
./installfog.sh --uninstall --purge-ssl --force   # clean slate incl. CA
rm -rf /opt/fog/secureboot                        # also reset Secure Boot
./installfog.sh -Y                                # fresh, split by default
./installfog.sh -Y --legacy-pki                   # fresh, flat

# verify
openssl verify -CAfile /opt/fog/snapins/ssl/CA/root/.fogRootCA.pem \
  -untrusted /opt/fog/snapins/ssl/CA/client/.fogClientCA.pem \
  /opt/fog/snapins/ssl/CA/client/comm/.commLeaf.pem
sbverify --list /var/www/html/fog/service/ipxe/bzImage
./restorekernel.sh --list
```

Everything under `$sslpath` is a **dotfile** — `ls -a` or it looks empty.

## Still open from the plan

- Phase 1 Task 1.7: interactive PKI prompt in `lib/common/newinput.sh`.
- Phase 2 entirely: migrating an existing server (needs finding 1 answered).
- Phase 3: root-key offlining helper, per-node Secure Boot leaves, the
  `--external-ca` deprecation decision.
