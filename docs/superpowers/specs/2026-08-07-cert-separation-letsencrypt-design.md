# Web vhost cert separation + Let's Encrypt scaffolding

Part of [FOGProject/fogproject#1013](https://github.com/FOGProject/fogproject/issues/1013).

## Context

FOG already separates the web vhost's certificate from FOG's own self-signed
CA via `--external-ca` (added for #794): an admin can sign the vhost's leaf
certificate with their own intermediate CA, independent of FOG's CA, and
`docs/EXTERNAL_CA_AND_LETSENCRYPT.md` documents the recommended pattern of
feeding an internal ACME CA (e.g. step-ca) into that flow. That separation is
done; it is not part of this design.

What's still missing, and what this design covers:

1. **No renewal automation.** The doc tells an admin to "automate this with an
   ACME renewal hook" but FOG ships none — admins write their own.
2. **No way to change the vhost's server name(s) without an interactive
   prompt.** `$hostname` already drives the vhost's `server_name`/`ServerAlias`
   and the cert's SAN, and is already persisted in `.fogsettings`, but there is
   no CLI flag to set it — under `-Y`/autoaccept (which `updatefog.sh` always
   uses) it silently takes whatever `hostname -f` returns, commonly something
   like `fogserver`.
3. **No way to add an additional server name** alongside the primary one, for
   sites that need the vhost reachable under more than one DNS name at once.

Separately, this design corrects two inaccuracies in
`docs/EXTERNAL_CA_AND_LETSENCRYPT.md` that came up while scoping the above
(already fixed in commits `da54feeb2` and `739f21dd5` on this branch, included
here for completeness):

- iPXE's netboot HTTPS fetches (`boot.php`, kernel, initrd) are **not** coupled
  to FOG's CA the way fog-client is. Upstream iPXE's `src/config/crypto.h`
  unconditionally defines `CROSSCERT="http://ca.ipxe.org/auto"`, a public-CA
  cross-signing fallback that FOG's build never disables and that the
  republished Secure-Boot-signed binaries rely on exclusively (upstream's own
  release build passes no `TRUST=`/`CERT=` at all). A real Let's Encrypt
  certificate on the vhost already validates for iPXE with no FOG-side change,
  independent of Secure Boot status, as long as the booting client can reach
  `ca.ipxe.org` (the common case, not an edge case worth hedging around).
- FOG's SSL CA (`.fogCA.key`/`.fogCA.pem`) and the Secure Boot signing key
  (`MOK.key`/`MOK.pem`, from `_ensureSecureBootKeys()`) are separate, unrelated
  keypairs. Neither this design nor `--external-ca` nor a Let's Encrypt
  certificate touches Secure Boot signing, and nothing about Secure Boot
  touches the CA this design is about.

## Non-goals

- Not re-litigating `--external-ca` or the CA-import flow — this design builds
  on top of it, unchanged.
- Not changing fog-client's pinning model or anything in the `zazzles`/
  `fog-client` repos.
- Not changing iPXE's build or trust configuration.
- Not storing DNS provider credentials — DNS-01 validation relies on
  `acme.sh`'s own plugin configuration, which the admin sets up themselves.
- Not supporting public Let's Encrypt for fog-client's pinning model directly
  — that remains fragile (LE rotates intermediates) and is already documented
  as such; this design's ACME automation only ever renews a leaf against a
  CA already imported via `--external-ca`, which is what keeps fog-client
  working across renewals.

## Architecture

Three independent, additive pieces. None replace or change existing behavior
when unused.

1. **`--hostname <name>`** on `installfog.sh`, passed through by
   `updatefog.sh`. Sets `$hostname` non-interactively — the same variable
   already used for the vhost's `server_name`/`ServerAlias` and the cert's
   `DNS.1` SAN, and already persisted in `.fogsettings` (`hostname` is already
   in `writeUpdateFile()`'s `managedKeys`).
2. **`--extra-server-name <name>`** (repeatable) on `installfog.sh`, passed
   through by `updatefog.sh`. A new, separate, additive list of extra
   `ServerAlias`/`server_name`/SAN entries — the primary `$hostname` and
   auto-detected IPs are unaffected.
3. **`bin/setupacme.sh`** — a new script, run after `--external-ca` is already
   configured. Manages `acme.sh` to issue and renew the vhost's **leaf**
   certificate against the already-imported external CA, installs the renewed
   leaf, reloads the web server, and schedules itself via `/etc/cron.d`.

## Components

### `--hostname <name>`

- `bin/installfog.sh`: new flag → staging var `shostname`, applied after
  `.fogsettings` is sourced, before `lib/common/newinput.sh`'s prompt loop.
  With `shostname` set, `hostname` is non-empty going into that loop's
  `while [[ -z $hostname ]]`, so the interactive prompt is skipped — this
  works the same under `-Y` and interactively.
- `bin/updatefog.sh`: new pass-through flag, forwarded to the child
  `installfog.sh -Y` invocation alongside the existing `$updateVhostFlag`.
- Input validation: hostname-shape check (alphanumeric, dots, hyphens only)
  before acceptance. Rejected with a clear error otherwise — same posture as
  the existing `--git-path` validation (`requires an absolute path`). This
  value is interpolated directly into the vhost config and an OpenSSL CSR
  config file; it must never reach either unchecked.
- No new persistence — `hostname` is already a managed key.

### `--extra-server-name <name>` (repeatable)

- New staging var collects into an array; persisted as a single space-joined
  `.fogsettings` key (`extraServerNames`), added to `writeUpdateFile()`'s
  `managedKeys`.
- Same input validation as `--hostname`, applied per value.
- `createSSLCA()`'s vhost-writing code (both the nginx and Apache branches)
  appends these to the existing `server_name`/`ServerAlias` lines — the same
  place `$vhostaliases` already gets appended for Apache; nginx's
  `server_name $ipaddresses $hostname;` line gets the same treatment.
- Also appended to the CSR's `[alt_names]` block (alongside the existing
  `$sanentries`/`DNS.1 = $hostname`), so the certificate itself covers the
  extra name(s), not just the vhost config.
- Mirrored into `globalSettings` as `FOG_EXTRA_SERVER_NAMES`, informational
  only (same treatment as `FOG_GIT_PATH`/`FOG_UPDATE_CHANNEL`) — visible on the
  Settings page under a category consistent with the existing "FOG Update"
  pattern; editing it there has no effect on the next run.

### `bin/setupacme.sh`

- Precondition check: the CA files `--external-ca` already imports
  (`/opt/fog/snapins/ssl/CA/.fogCA.pem` etc.) must exist. If not, fail
  immediately with a message pointing at `--external-ca` — never try to issue
  against nothing.
- Installs `acme.sh` if not already present (single curl-fetchable script,
  same posture as `installfog.sh`'s own per-distro prerequisite handling).
- Args: ACME directory URL, and validation method using `acme.sh`'s own
  vocabulary directly (`--http01`, or `--dns <acme.sh-plugin-name>`) rather
  than inventing FOG's own — for DNS-01, the admin is responsible for whatever
  provider credentials that plugin itself expects; FOG never stores them.
- Issues via `acme.sh --issue`, installs via `acme.sh --install-cert` with
  `--reloadcmd` set to whatever reloads FOG's already-configured web server —
  `systemctl reload httpd`/`apache2`/`nginx` depending on `$webserver`, the
  same variable `createSSLCA()` already branches on. The exact command is an
  implementation detail, not a design decision: it only ever reloads the one
  web server FOG already knows it configured.
- Schedules its own renewal check via `/etc/cron.d/fog_acme_renew`, written
  using the same `mv -fv` backup + `diffconfig` pattern `setupFogReporting()`
  already uses for `/etc/cron.d/fog_reporting`.

## Data flow

**Install/update time (hostname + extra names):**
`installfog.sh --hostname fog.example.com --extra-server-name fog-legacy.internal`
→ staging vars applied after `.fogsettings` sourced → `createSSLCA()` writes
them into the vhost's `server_name`/`ServerAlias` and the CSR's `[alt_names]`
→ `writeUpdateFile()` persists both to `.fogsettings` → mirrored into
`globalSettings`. On every later `installfog.sh`/`updatefog.sh` run, both
values are already in `.fogsettings`, so the flags don't need repeating (same
pattern as `fog_update_channel`).

**ACME renewal (steady state):**
`/etc/cron.d/fog_acme_renew` fires `acme.sh --cron` on schedule → `acme.sh`
determines the leaf needs renewal → talks to the configured ACME directory URL
using the configured validation method (HTTP-01 hits the vhost directly;
DNS-01 uses the admin's own plugin config) → new leaf issued, still signed by
the same external intermediate imported via `--external-ca` → `--reloadcmd`
installs the leaf where the vhost reads it and reloads the web server.
**The pinned intermediate never changes**, so fog-client keeps working without
re-pinning, and iPXE is unaffected regardless (per the `CROSSCERT` finding
above, or simply because the intermediate didn't change).

**Failure mode:** if `acme.sh --cron` fails (validation failure, network
issue, ACME server down), the old leaf stays in place until it actually
expires. `acme.sh`'s own retry/backoff handles transient failures; no
FOG-specific retry logic is needed on top.

## Error handling

- **Input validation** on `--hostname`/`--extra-server-name`: hostname-shape
  check before any file write; malformed values (spaces, shell metacharacters)
  are rejected outright, not sanitized-and-written.
- **`setupacme.sh` without `--external-ca` configured**: fails immediately
  with a message pointing at `--external-ca`.
- **`acme.sh` missing and uninstallable** (no network, curl fails): fails
  clearly and stops; no silent fallback to a different cert path.
- **Renewal failures**: left to `acme.sh`'s own retry/backoff and exit status.
- **Known limitation, not fixed by this design:** the `#1012` `diffconfig`/
  backup mechanism can't distinguish "an admin hand-edited the vhost" from
  "FOG changed it because `--extra-server-name` was passed" — both look like
  "the file changed" and surface the same "Changed configurations" notice.
  Pre-existing limitation of that mechanism; noted so it isn't mistaken for a
  new bug when it shows up on an otherwise-normal `--extra-server-name` change.

## Testing

No CI framework exists for this repo's shell scripts beyond
`fogproject-install-validation`'s end-to-end distro matrix, so verification
here is manual/integration, same posture as the rest of the `#1012`/`#1013`
work (`bash -n` for syntax, then real installer runs):

- **`--hostname`**: install with the flag → vhost `server_name`/`ServerAlias`
  and the cert's SAN both show the new value; re-run without the flag → value
  persists from `.fogsettings` unchanged (idempotent).
- **`--extra-server-name`**: same checks, plus confirming it's additive
  (auto-detected IPs/hostname still present) and that `FOG_EXTRA_SERVER_NAMES`
  shows up correctly on the Settings page.
- **Input validation**: malformed values for both flags (spaces, `;`, shell
  metacharacters) are rejected before anything is written.
- **`setupacme.sh`**: test against a local `step-ca` instance (matching the
  doc's own recommended setup) rather than production Let's Encrypt, to avoid
  rate limits and real domain-validation infrastructure. HTTP-01 is testable
  directly against step-ca; DNS-01 needs a real provider sandbox and may need
  verification by whoever has one — noted as such in the implementation plan
  rather than blocking on it.
- **Cron entry**: `/etc/cron.d/fog_acme_renew` created with correct
  permissions; `diffconfig` no-ops cleanly on first run and correctly flags a
  later hand-edit.
- **Regression**: an install/update with none of these new flags behaves
  identically to before — everything here is additive/opt-in.
