# The install settings are independent keys, not one compound value

## Status

accepted

> **Amended (#1120): the key names in this record are the current camelCase
> spellings.** The decision is unchanged; only the spelling of some keys is.
> When this was written the transport and PKI keys were lower-case
> run-together names — `httpproto`, `netbootproto`, `sslpath`, `catrust`,
> `secureboot`, `externalca`, `extca*` — sitting beside the camelCase keys this
> ADR introduced. Documenting them as one model made the split indefensible, so
> they were unified rather than left half-and-half. No aliases: the old names
> are in `deprecatedKeys`, `_migrateLegacySettingNames()` copies each value
> across once, and the next write removes the stale line. See
> `docs/FOGSETTINGS.md` § Renaming a key.

## Context

`$httpProto` was one `.fogsettings` key that decided three unrelated things:

1. what protocol FOG uses for its own URLs,
2. whether HTTP is redirected to HTTPS,
3. whether iPXE is recompiled with this server's CA embedded.

Nothing in its name says the last two, and an admin setting `-S/--force-https`
was not choosing them. The bindings were an accident of implementation that
hardened into behaviour, and each one caused a distinct failure:

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

| Key | Default | Means |
| --- | --- | --- |
| `httpProto` | `https` | protocol FOG uses for its own **non-netboot** URLs |
| `netbootProto` | `http` | protocol iPXE uses to fetch `boot.php` |
| `publicWebCert` | `no` | the web certificate chains to a **public** root |
| `rebuildIpxeWithMyCA` | `no` | rebuild iPXE embedding the configured CA |
| `httpsRedirect` | `no` | force the HTTP→HTTPS redirect |
| `acmeLeaf` | `no` | leaf managed outside FOG (pre-existing) |

Resolution rules:

- `netbootProto` defaults to `http`, steered to `https` **iff** `publicWebCert`
  or `rebuildIpxeWithMyCA` is set. An explicit `--netboot-proto` always wins.
- The build happens **iff** `rebuildIpxeWithMyCA=yes`, and then only when the
  iPXE tag or the embedded CA has actually changed.
- Secure Boot is prepared and everything unsigned is signed, **in every mode**.
- `netbootProto=https` forced with neither trigger is legal and warned.

`--install-mode` is a preset over these keys — a convenience, never a
replacement. Discrete flags apply after it and override only their own field.

### Why `publicWebCert` is stated, not measured

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

One guess, made once: an existing `httpProto=https` is the only evidence its
admin ever asked for `-S`, so `httpsRedirect` is seeded from it. Everyone else
gets no redirect. `httpProto` then moves to `https` for all, which is safe
because 443 already listens on every install — both web servers emit their
`:443` vhost in both arms — and no redirect follows.

The seeding is guarded on `httpsRedirect` being unset, so it fires exactly once.
Without that guard an admin who turned the redirect off would have it turned
back on by the next upgrade re-reading `httpProto` — and with HSTS on the same
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
  on it, which with `httpProto` defaulting to `https` would have put **every
  upgraded install** onto HTTPS netboot — the one configuration that cannot work
  behind a private CA.

## Alternatives rejected

**Keep one key, add values** (`httpProto=https-no-redirect`). Encodes the same
conflation in a longer string and does not compose: there is no sensible value
for "HTTPS, no redirect, public cert, no rebuild".

**Derive everything from the certificate at install time.** The probe above; see
why it was removed.

**Leave the redirect on for `httpProto=https` and skip the new key.** Would keep
HSTS attached to a value that now defaults to `https`, turning it on everywhere
— irreversible for any browser that saw it.

## References

- Tracking issue: FOGProject/fogproject#1116
- Phase 1 audit: `docs/HTTPPROTO_COVERAGE_AUDIT.md` (coverage, the netboot fetch
  set, PKI artefact lifetimes, and the trust model)
- Phase 2: FOGProject/fogproject#1119
- `docs/EXTERNAL_CA_AND_LETSENCRYPT.md` — the `CROSSCERT` research
