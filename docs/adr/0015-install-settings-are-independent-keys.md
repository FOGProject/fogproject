# The install settings are independent keys, not one compound value

## Status

accepted. Key names updated by
[ADR 0024](0024-fogsettings-unified-key-model.md), which renamed every
`.fogsettings` key and retired `acmeLeaf`. The decision below stands unchanged --
the independence of these settings is the point, and the new names make it more
visible, not less. Only the spellings moved.

## Context

`$httpproto` was one `.fogsettings` key that decided three unrelated things:

1. what protocol FOG uses for its own URLs,
2. whether HTTP is redirected to HTTPS,
3. whether iPXE is recompiled with this server's CA embedded.

Nothing in its name says the last two, and an admin setting `-S/--force-https`
was not choosing them. The bindings were an accident of implementation that
hardened into behavior, and each one caused a distinct failure:

- `downloadipxe()` skipped the release asset on any HTTPS install, so such a
  server never had a pristine copy of the published binaries.
- `downloadipxesecureboot()` skipped **entirely**, so **every `-S` install
  staged no Secure Boot binaries at all** — the feature was missing precisely on
  the servers whose admins had gone furthest out of their way to configure TLS.
- `configureTFTPandPXE()` ran a 10–25 minute cold rebuild on every install *and*
  every update, with no warm path, frequently to reproduce identical bytes.

The reasoning that tied them together was that iPXE validates TLS strictly, has
no `--insecure`, and cannot be told to trust a private CA — so HTTPS netboot
needs the CA compiled in. That part is true. What did not follow is inferring it
from *"the web UI uses HTTPS"*, which says nothing about the netboot transport
and nothing about what the certificate chains to.

Testing settled the two facts the old model could not express. Upstream iPXE
defines `CROSSCERT` unconditionally in `config/crypto.h` and FOG's overlay
replaces only `general.h`/`settings.h`/`console.h` — so upstream's binaries
already validate a **publicly** issued certificate with no rebuild, confirmed in
production against a Let's Encrypt vhost. And a rebuilt binary is not upstream's
*signed* one, so it cannot be the first stage of a Secure Boot chain: rebuilding
makes Secure Boot onboarding **harder**, not easier.

## Decision

Each concern is its own `.fogsettings` key. No compound value hides another.

| Key | Was | Default | Means |
| --- | --- | --- | --- |
| `WEB_url_proto` | `httpproto` | `https` | protocol FOG uses for its own **non-netboot** URLs |
| `BOOT_url_proto` | `netbootproto` | `http` | protocol iPXE uses to fetch `boot.php` |
| `PKI_web_cert_publicly_trusted` | `publicWebCert` | `no` | the web certificate chains to a **public** root |
| `BOOT_rebuild_ipxe_with_my_ca` | `rebuildIpxeWithMyCA` | `no` | rebuild iPXE embedding the configured CA |
| `WEB_https_redirect` | `httpsRedirect` | `no` | force the HTTP→HTTPS redirect |
| *(derived)* | `acmeLeaf` | — | leaf managed outside FOG |

`WEB_` and `BOOT_` being separate namespaces is this ADR's point restated in the
key names: the two `*_url_proto` keys ask the same question of different
subsystems, and neither implies the other.

`acmeLeaf` no longer exists. "The leaf is managed outside FOG" is now derived --
`PKI_web_vhost_cert` is a canonical path, and when it resolves outside the web
PKI zone dir the leaf is somebody else's. See ADR 0024.

Resolution rules:

- `BOOT_url_proto` defaults to `http`, steered to `https` **iff**
  `PKI_web_cert_publicly_trusted` or `BOOT_rebuild_ipxe_with_my_ca` is set. An
  explicit `--netboot-proto` always wins.
- The build happens **iff** `BOOT_rebuild_ipxe_with_my_ca=yes`, and then only
  when the iPXE tag or the embedded CA has actually changed.
- Secure Boot is prepared and everything unsigned is signed, **in every mode**.
- `BOOT_url_proto=https` forced with neither trigger is legal and warned.

`--install-mode` is a preset over these keys — a convenience, never a
replacement. Discrete flags apply after it and override only their own field.

The chosen preset is persisted as `FOG_install_mode` and seeded back into the
preset before it is applied, so the question is asked once rather than on every
upgrade. Two consequences follow from that placement:

- It is applied *after* the line that forces `WEB_url_proto=https`, which is the
  only reason `http-only` can persist at all. Before, that mode left no trace in
  the four keys and had to be passed again on every upgrade or it reverted.
- Any discrete transport flag **clears** it. Once a flag has moved one of the
  four keys off its preset the shape is no longer one of the named modes, and a
  name left behind would have the next run's preset overwrite the very key that
  moved — the same trap `BOOT_url_proto` documents above. Empty means custom,
  and the four keys then stand alone as the model they always were.

### Why `PKI_web_cert_publicly_trusted` is stated, not measured

A probe was prototyped and removed. It was delicate: FOG installs its own CA
into the host trust store by default, so a plain `openssl verify` answers
"trusted" for FOG's *own* leaf — exactly the case that needs the rebuild. The
configured CA has to be excluded first, and that exclusion must use `-trusted`,
not `-CAfile`, because `-CAfile` *adds to* the default locations rather than
replacing them.

Decisively: a value re-derived every run from a trust store that other software
also writes to is not something to hang a 25-minute build on. It has to persist.

### Why the redirect is off by default

Trust in FOG's CA reaches a client machine when **fog-client installs it into
that machine's trusted root store**. On a fresh server nothing has fog-client
yet, so those machines have no inherited trust, and a forced redirect breaks
exactly the machines that cannot fix themselves. The redirect is something an
admin turns on once trust is in place.

HSTS belongs to the same key, and this is the sharper half of the decision. It
was previously emitted on the nginx `:443` server in *both* arms — including on
plain-HTTP installs — while Apache emitted none. A browser that has seen it
refuses plain HTTP to that host for six months **from its own cache**; no
server-side change reaches it. That is the redirect's semantics with a memory.

## Migration

One guess, made once: an existing `httpproto=https` is the only evidence its
admin ever asked for `-S`, so `httpsRedirect` is seeded from it. Everyone else
gets no redirect. `httpproto` then moves to `https` for all, which is safe
because 443 already listens on every install — both web servers emit their
`:443` vhost in both arms — and no redirect follows.

The seeding is guarded on `httpsRedirect` being unset, so it fires exactly once.
Without that guard an admin who turned the redirect off would have it turned
back on by the next upgrade re-reading `httpproto` — and with HSTS on the same
key, that reaches browsers rather than just the server.

## Consequences

- Existing `-S` servers keep their redirect. Everyone else keeps HTTP working
  and gains HTTPS availability.
- **No install rebuilds iPXE unless it asked to**, and none rebuilds twice for
  the same tag and CA.
- Secure Boot works on HTTPS servers for the first time.
- A public-certificate site gets HTTPS netboot with **no** rebuild.
- The four keys are individually addressable, so a future mode is a new preset
  rather than a new meaning bolted onto an existing value.
- `caCreated` must never be used to infer transport again. It is persisted, so
  it is `yes` on every re-run of an existing server; the previous resolver keyed
  on it, which with `httpproto` defaulting to `https` would have put **every
  upgraded install** onto HTTPS netboot — the one configuration that cannot work
  behind a private CA.

## Alternatives rejected

**Keep one key, add values** (`httpproto=https-no-redirect`). Encodes the same
conflation in a longer string and does not compose: there is no sensible value
for "HTTPS, no redirect, public cert, no rebuild".

**Derive everything from the certificate at install time.** The probe above; see
why it was removed.

**Leave the redirect on for `httpproto=https` and skip the new key.** Would keep
HSTS attached to a value that now defaults to `https`, turning it on everywhere
— irreversible for any browser that saw it.

## References

- Tracking issue: FOGProject/fogproject#1116
- Phase 1 audit: `docs/HTTPPROTO_COVERAGE_AUDIT.md` (coverage, the netboot fetch
  set, PKI artifact lifetimes, and the trust model)
- Phase 2: FOGProject/fogproject#1119
- `docs/EXTERNAL_CA_AND_LETSENCRYPT.md` — the `CROSSCERT` research
