# Netboot addresses this server by the name in its certificate

## Status

accepted

## Context

A PXE boot over HTTPS fetches from this server twice, and until now the two
fetches got their host from two unrelated places with nothing comparing them.

| Hop | URL | Host came from |
| --- | --- | --- |
| 1 | `default.ipxe` → `service/ipxe/boot.php` | shell `${NET_hostname}` |
| 2..n | `${boot-url}/service/ipxe/*`, `web=`, Secure Boot `MOK.der` / `mmx64.efi` | the `FOG_WEB_HOST` DB row |
| kernel / init | relative, so it inherits hop 1's host | — |

`configureDefaultiPXEfile()` used `${NET_fog_server_ip}` for the entire prior life of that
line. When HTTPS netboot arrived it became `${NET_hostname:-$NET_fog_server_ip}`, guarded
only by `validip` — so the *only* rejected value was an IPv4 literal.
`validhostname()` accepts a single label, and a short name therefore passed every
check on the path.

That produced `https://fog/fog/service/ipxe/boot.php` against a Let's Encrypt
certificate issued to `fog.arrowheaddental.com`. iPXE has no `--insecure` and
fails the handshake on a name mismatch, so the boot stopped before it fetched
anything.

**The guard was wrong in a way that testing could not see.**
`_defaultServerNames()` puts *both* the FQDN and the short `${NET_hostname%%.*}` into
the SAN list, so on a FOG-issued leaf a short name is a genuine SAN and
`${NET_hostname}` works. But `_createWebLeaf()` returns early when the leaf is externally managed or
`PKI_web_cert_publicly_trusted` is set — FOG's SAN list is never applied to a publicly-issued
leaf, which carries only the names its issuer was asked for. Since
`PKI_web_cert_publicly_trusted` is one of exactly two triggers for HTTPS netboot (ADR 0015), the
short-name case is not an edge case: it is roughly half the population that
selects HTTPS netboot at all.

Meanwhile `FOG_WEB_HOST` is seeded from `${NET_fog_server_ip}` on a fresh schema deploy and
was never written again — the string does not appear anywhere in `lib/` or
`bin/`. So a fresh `--install-mode public-cert` install pointed hops 2..n at
`https://<address>/`, which no public CA will ever certify.

The requirement was written down — `docs/PKI_ZONES.md`: *"only on that exact
FQDN, not a short hostname and not an IP"* — and enforced nowhere. The installer
already had the right helper: `_servedCertName()` reads the CN off the leaf the
vhost actually serves, and the vhost's `ServerName` and every installer HTTPS
self-call had been moved onto it. The netboot URL was the one self-reference that
was missed.

## Decision

**One name for the whole boot, taken from the certificate.**

`_resolveNetbootHost()` resolves it once into `$netboothost`, which is
deliberately not `local`: `configureDefaultiPXEfile` and `recordNetbootWebHost`
read the same variable and so cannot disagree. It is idempotent and silent on a
second call.

1. The name is `_servedCertName()` — the CN of the leaf the vhost serves, the
   same value the self-calls verify against.
2. It is then **checked against that certificate** by `_certServesName()`, which
   applies *iPXE's* rule rather than OpenSSL's: per ADR 0016, iPXE's
   `x509_check_name()` honors a `commonName` only when the certificate carries
   no `subjectAltName` at all. Once any SAN exists the CN is ignored. IP SANs are
   ignored throughout — they cannot satisfy a URL built from a name.
3. A failure to satisfy this is **fatal, before anything is written**. An install
   that completes having laid down an unbootable `default.ipxe` is strictly worse
   than one that stops and says which names the certificate does carry.
4. `FOG_WEB_HOST` is **recorded** from the same value by
   `recordNetbootWebHost()`, so hops 2..n agree with hop 1 by construction.

Plain-HTTP netboot is untouched and still uses `${NET_fog_server_ip}`. It never cared about
names, and rewriting it would change the boot URL of every ordinary install.

### `FOG_WEB_HOST` becomes a record, but only under HTTPS netboot

This is the part an admin can be surprised by, so it is stated plainly: when
`BOOT_url_proto=https`, `FOG_WEB_HOST` is overwritten on every install run and an
edit through the Settings tab will not survive. It joins `FOG_GIT_PATH`,
`FOG_EXTRA_SERVER_NAMES` and `SERVICE_LOG_PATH` as a record rather than a
control, for the same reason `SERVICE_LOG_PATH` became one: two things that must
agree, kept in step by deriving one from the other instead of hoping.

The gate matters. On a plain-HTTP install `FOG_WEB_HOST` is a name plenty of
admins set deliberately, no certificate has to match it, and rewriting it would
be a regression dressed as a fix. So the recorder returns immediately unless
netboot is HTTPS.

## Consequences

- HTTPS netboot works on a publicly-issued certificate without the admin having
  to know that `${NET_hostname}` and `FOG_WEB_HOST` are separate values that both had
  to be right.
- A server whose certificate genuinely cannot cover a usable name now fails the
  install instead of shipping a broken TFTP tree. That is a louder failure than
  before on exactly the installs that were already broken.
- `--extra-server-name` is explicitly *not* offered as a remedy in the failure
  message. It only feeds FOG's own SAN list, and the branch fires mostly on
  installs whose leaf FOG did not issue.
- A wildcard-only match is accepted **with a printed note**, because whether
  iPXE's `x509_check_name()` honors a wildcard SAN is unverified — `fog-ipxe`
  is an overlay and carries no upstream `crypto/x509.c` to read. Verify against
  upstream and tighten or relax then.
- `tests/netboot-host.test.sh` pins all of it, including the case most likely to
  be "simplified" later: that the CN must be ignored once any SAN exists. There
  was previously no test over the netboot host at all —
  `install-settings-resolution.test.sh` covers which *protocol* is chosen but
  never calls the function that writes the URL, which is why this shipped.

## Alternatives rejected

**Require an FQDN shape instead of checking the certificate.** Extending the
existing guard to reject a single label is a two-line change and would have fixed
the reported symptom. It still accepts a dotted name the certificate does not
carry — `fog.internal.lan` when the leaf says `fog.arrowheaddental.com` — which
fails identically and is harder to diagnose, because the install claims to have
validated something.

**Read `FOG_WEB_HOST` in the installer and use it for hop 1.** Makes the two hops
agree, but agrees on the wrong thing: the row is an IP on every fresh install, is
admin-editable with no validation, and the installer has no access to it today.
It would move the mismatch rather than remove it.

**Derive hops 2..n from `$_SERVER['HTTP_HOST']` instead of `FOG_WEB_HOST`.** The
elegant option — hop 1 sets the identity and the rest inherit it, exactly as the
*protocol* already self-derives from `$_SERVER['HTTPS']`. Rejected as too broad
for a bug fix: it changes the boot URLs of every existing install, including HTTP
ones where `FOG_WEB_HOST` deliberately differs from the address iPXE dialled, and
it puts a client-supplied header into generated boot URLs.

**Warn instead of failing.** Leaves an install that completes cleanly and cannot
boot — the precise failure mode the original IP guard was made fatal to prevent.

## Amendments

**2026-08-31 (`bd0cf3d37`): an address is allowed, and the row is no longer
overwritten on every run.** iPXE verifies an IP literal against an `iPAddress`
SAN exactly as it verifies a name, so decision point 2's "IP SANs are ignored
throughout" was wrong as a statement about iPXE and `_certServesAddress()` now
applies its rule. And because `FOG_WEB_HOST` is the server's canonical address
for far more than netboot, `recordNetbootWebHost()` keeps an existing value the
served certificate carries and only corrects one it cannot prove. "Overwritten
on every install run" above is therefore no longer true; it is "corrected when
the certificate cannot vouch for it".

**2026-09-03: the kept value is the netboot host for BOTH hops.** The keep
above lived only in the recorder, while `configureDefaultiPXEfile()` had
already taken the certificate's leading name from `_resolveNetbootHost()`. That
reopened the two-source split this ADR exists to remove: `default.ipxe` chained
to `https://<name>/`, the row stayed `<address>`, and on a PXE segment with no
DNS for the name iPXE stopped at hop 1 with "DNS name does not exist" while
every URL after `boot.php` would have worked. Now `_resolveNetbootHost()` asks
the row first and honors it when the served certificate carries it; the
certificate's own name is the fallback, not the default. Both callers read one
answer again. `tests/netboot-host-may-be-an-address.test.sh` section H pins it.

## References

- ADR 0015 — the settings this builds on: `BOOT_url_proto`,
  `PKI_web_cert_publicly_trusted`, `BOOT_rebuild_ipxe_with_my_ca` as independent
  keys.
- ADR 0016 — why iPXE's name checking is the rule to mirror.
- `docs/PKI_ZONES.md` — the Let's Encrypt netboot caveat this enforces.
- `docs/HTTPPROTO_COVERAGE_AUDIT.md` §A2 — the full set of URLs iPXE fetches.
