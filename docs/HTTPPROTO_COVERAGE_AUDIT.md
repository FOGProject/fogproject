# `httpproto` coverage audit, and the conditional HTTPS redirect

> **Headline:** `httpproto` is honoured by every URL the installer emits, so
> redefining it as *"the protocol FOG uses for its own **non-netboot** URLs"* is
> safe on the installer side. It is **not** safe yet on three other fronts:
> three sites still gate the iPXE download, the Secure Boot staging and the
> local iPXE rebuild on `httpproto` rather than on `netbootproto`; the HTTP→HTTPS
> redirect and HSTS are both welded to `httpproto` with no separate key; and the
> redirect's exclusion list was missing a directory iPXE fetches directly.
> Everything in Part B §4 and §5 was found broken **before** any default was
> flipped, and is fixed on this branch.

Phase 1 of [#1116](https://github.com/FOGProject/fogproject/issues/1116),
answering [#1118](https://github.com/FOGProject/fogproject/issues/1118).

This document has two halves and they age very differently.

- **Part A is reference material.** It describes how the protocols, the netboot
  fetch set and the PKI actually work. It is written to be published as-is and
  is the intended source for
  [#1120](https://github.com/FOGProject/fogproject/issues/1120) — see
  *Publishing notes* at the end.
- **Part B is a point-in-time audit** of `working-1.6`, with `file:line`
  references that will drift. Re-verify before trusting a line number.

---

# Part A — Reference

## A1. Three protocols, not one setting

FOG makes three independent protocol decisions that were historically one
value. Conflating them is what
[#1116](https://github.com/FOGProject/fogproject/issues/1116) exists to undo.

| Decision | Governed by | Why it is separate |
| --- | --- | --- |
| The scheme FOG uses for its **own non-netboot** URLs — the web UI, the API, the client installer download, URLs handed to fog-client | `httpproto` | An ordinary TLS decision. Every consumer here can be told to trust a CA. |
| The scheme **iPXE** uses to fetch `boot.php` and everything downstream of it | `netbootproto` | iPXE can be told to trust nothing. It validates strictly, has no `--insecure`, and its only route to a private CA is a rebuild that costs the signed Secure Boot chain. |
| Whether plain HTTP is **redirected** to HTTPS | the redirect, today welded to `httpproto` | Trust reaches a client machine when fog-client installs FOG's CA into that machine's store. On a fresh server nothing has fog-client yet, so a forced redirect breaks exactly the machines that cannot fix themselves. |

>[!important]
>iPXE can only validate a chain terminating in a **public** root, via its
>`ca.ipxe.org` cross-signing fallback, and only when the certificate was issued
>to an **FQDN** — public CAs do not issue for private IPs, and chaining to an IP
>fails on name mismatch even after the chain validates. A FOG-PKI or internal-CA
>certificate is perfectly good for browsers, the API and fog-client, and simply
>cannot work for HTTPS netboot.

### Why PHP derives its scheme from the request, and why that is correct

`FOGBase::$httpproto` is set from `$_SERVER['HTTPS']` — *how this request
arrived*, not a configured value. For the netboot path this is not an oversight,
it is the mechanism:

The installer writes `default.ipxe` with `chain <netbootproto>://…/boot.php`.
`BootMenu` then builds the whole menu, the `web=` kernel argument and every
`${boot-url}` from `self::$httpproto` — so the entire boot sequence inherits the
protocol iPXE arrived on, with no PHP configuration at all. Replacing that with
a configured `httpproto` would break every HTTPS-web / HTTP-netboot install.

Self-derivation is likewise correct for same-origin work: the API-disabled
redirect, the OpenAPI `servers[0].url`, the Swagger spec URL.

It is **wrong**, or at least insufficient, in two places:

- **Storage-node URLs.** About a dozen call sites pair the *request's* scheme
  with a *node's* IP. A node's protocol is a per-node property and there is no
  column for it; the replicator works around this by trying HTTP and retrying
  HTTPS.
- **The CLI daemons.** `$_SERVER['HTTPS']` is unset outside a request, so
  `$httpproto` is unconditionally `http` in every background service.

## A2. Everything iPXE fetches during a boot

This is the set that a forced HTTPS redirect must not touch, and it is **two
directories**, not one.

| Step | URL | Where it comes from |
| --- | --- | --- |
| 0 | `tftp://${next-server}/default.ipxe` | the embedded/autoexec script |
| 1 | `<netbootproto>://<server><webroot>service/ipxe/boot.php` | `default.ipxe`, written by the installer |
| 2 | `${boot-url}/service/ipxe/` → `grub.exe`, `refind.conf`, `refind*.efi`, `bg.png` / `bgdark.png`, `advanced.php` | `BootMenu` |
| 3 | the kernel (`bzImage`) and init (`init.xz`) | **relative** — iPXE resolves them against `boot.php`'s own URI, so they inherit the netboot scheme with no PHP involvement |
| 4 | `${boot-url}/service/secureboot/MOK.der` (`imgfetch`), `mmx64.efi` / `arm64-efi/mmaa64.efi` (`chain`) | `BootMenu`'s Secure Boot entries |
| 5 | `web=<proto>://<host><webroot>/` | handed to **FOS**, not fetched by iPXE |

>[!important]
>**`<host>` is not one value.** Step 1's host is written by the installer; steps
>2, 4 and 5's come from the `FOG_WEB_HOST` DB row, read by `BootMenu`; step 3's
>is inherited from step 1 because the URLs are relative. Nothing used to compare
>the two — step 1 was `$hostname` and could be a short label, while
>`FOG_WEB_HOST` was seeded from `$ipaddress` and never rewritten, so an HTTPS
>netboot install could fail at step 1, at step 2, or at both, for different
>reasons. Both are now derived from the served certificate's name and
>`FOG_WEB_HOST` is recorded from it; see `docs/adr/0018`.

**The rule: exclude every path iPXE itself fetches — `service/ipxe/` and
`service/secureboot/`.**

>[!warning]
>Everything else FOS reaches under `${web}` — `jobs.php`, `Post_Stage*.php`,
>`progress.php`, the Secure Boot `.auth` files, and the rest — is fetched with
>`curl -Lks`. The `-L` follows the redirect and the `-k` skips verification, so
>those calls survive a redirect that iPXE could not. **That tolerance is
>load-bearing and is written down nowhere else.** If any FOS fetch ever drops
>`-k`, its path has to join the exclusion list.

`service/localboot/` is **not** in this set. It is an admin/browser download of
binaries to copy onto an ESP by hand, not something firmware fetches at boot.

### Two web servers, two mechanisms

- **nginx** — the redirect must be a `location /`, never a server-level
  `return`. Measured against real nginx: a server-level `return` runs in the
  server rewrite phase, *before* location selection, so it fires for every
  request and every exclusion above it becomes dead code, silently. The
  exclusions use `^~`, which is what beats `location /`.
- **Apache** — `RewriteCond` really does guard only the next `RewriteRule`, so
  the conditions sit immediately before it. Multiple `RewriteCond`s are ANDed,
  which is what is wanted: skip the redirect only when the request is for
  none of the excluded paths.

`management/other/ca.cert.der` is exempt in **both**, and deliberately *not*
under the netboot guard — the client that needs it trusts nothing yet, which has
nothing to do with which transport netboot uses. Redirecting it would make
fetching the CA require already trusting the CA.

## A3. PKI artefacts and how long each one lives

Three lifetime classes. The distinction matters because re-issuing something in
the first class orphans whatever already trusts it.

### Create-once — never re-issued while the file exists

| Artefact | What breaks if it churns |
| --- | --- |
| Root CA certificate and key | fog-client pins this as `ca.cert.der`; re-minting orphans every registered client at once |
| Client-communication key and leaf | every client encrypts to that public half |
| Web intermediate CA and key | invalidates the leaf beneath it |
| Web leaf **private key** | a new key forces a new certificate |
| `dhparam.pem` | expensive to generate, no reason to change |
| Secure Boot CA and key | enrolled in firmware, per machine, by hand |
| Secure Boot signing leaf | signed the kernels already deployed |
| PK / KEK | written into firmware in Setup Mode |
| MOK (flat fallback) | enrolled through MokManager behind physical presence |

### Re-issued only on a real input change

The **web leaf certificate**, and only it. The decision is a stamp file,
`.webLeaf.sans`, holding

```
md5( contents of ca.cnf ‖ sha256 fingerprint of the signing CA )
```

which covers both the name set and the identity of the CA that signs it. The
stamp is deleted whenever the leaf's key is regenerated, so the two can never
disagree.

>[!note]
>This replaced a guard of `[[ ! -x $sslpubcert ]]`. Certificates are not
>executable, so that test was true of every certificate ever written and the leaf
>was re-signed on **every single run**. `tests/pki-idempotence.test.sh` now runs
>the PKI path twice and fails if anything long-lived changes.

### Derived per run — safe, because it is a function of the above

Ephemeral OpenSSL configs and CSRs (`ca.cnf`, `req.cnf`, the `.csr` files); the
CA chain and full-chain files, which are concatenations of create-once material;
and the copies republished under the web root (`ca.cert.pem`, `ca.cert.der`,
`srvpublic.crt`, the Secure Boot kit) because configuring the web server wipes
that tree every run.

One case sits deliberately in this class rather than being "fixed": an external
CA's cert/key/chain are re-imported on every run whenever the source files are
still readable. That is a re-*import*, not a regeneration, and it is what lets an
admin rotate their CA — the leaf's stamp includes the CA fingerprint, so a
genuinely changed CA correctly re-issues the leaf beneath it.

## A4. Which root goes where

Three questions that look like one and are not.

| Question | Answer |
| --- | --- |
| What does **fog-client** pin? | The FOG root, published as `ca.cert.der`. |
| What does the **vhost** serve? | The web leaf plus its chain, terminating in whichever root issued it — FOG's, or an external one. |
| What does the **server's own OS trust store** need? | The root that the *served chain* terminates in. Otherwise every HTTPS call made on the server to the server fails to verify. |

With FOG's own PKI all three are the same certificate. With `--external-ca` /
`--web-ca-root` the second and third diverge from the first, because an external
CA replaces **the CA that signs the vhost leaf, and only that** — the root
fog-client pins is untouched. So the trust anchor is a **bundle**: FOG's own
root, plus the root the served chain terminates in, deduplicated by fingerprint.

>[!warning]
>The chain files are not written in a consistent certificate order — the
>generated web CA and the node signer write issuer-first with the root appended,
>while the external-CA import writes the root first. Never select a root from a
>chain **by position**. Both readers select it by `subject == issuer`.
>`tests/trust-anchor.test.sh` pins this.

`--no-ca-trust` declines the OS-trust-store step entirely. It does not affect
browsers: Firefox carries its own NSS store, Chrome reads a per-user one, and the
browser is usually on another machine.

## A5. Bringing your own certificates

Three separate things an admin may want to supply, at three different levels.

### The web leaf (managed outside FOG)

Set `acmeLeaf="yes"` in `.fogsettings` by hand. FOG then leaves the leaf in
place and does not lock its private key down to `root:root 0600`, because an
ACME renewal hook writes that file as whatever user it runs as.

`acmeLeaf` and `publicWebCert` answer different questions — *who manages the
leaf file* versus *what the leaf chains to*. All four combinations are real;
internal ACME with step-ca is `acmeLeaf=yes` with `publicWebCert=no`.

### The web CA (external issuer for the vhost leaf)

`--external-ca` with `--web-ca-cert` / `--web-ca-key` / `--web-ca-root`. FOG
validates that the key matches the certificate, that the certificate is a CA,
and that it chains to the supplied root, then issues the web leaf beneath it.
The FOG root and everything fog-client depends on are untouched.

### The client-communication leaf (external issuer)

Drop the certificate at `$sslpath/.srvpublic.crt` with its key at
`$sslpath/.srvprivate.key`. FOG keeps both from then on and never re-issues.

>[!danger]
>The two must pair. Every registered fog-client encrypts to the public half of
>that key, so a mismatched pair does not degrade anything — it locks out every
>client at once, surfacing per host as a failed check-in with nothing naming
>either file. FOG now checks the modulus and warns loudly, but it cannot repair
>it for you.

### Not implemented: sub-CA delegation of the FOG root

The natural fourth option — the admin's CA issues an intermediate that becomes
FOG's root, so FOG can still sign the communication leaf and node certificates
while everything chains to corporate trust — is **not wired up**, though most of
the machinery exists.

`validateExternalCA` is zone-parameterised and its `flat` zone imports into the
**root** zone's `.fogCA.pem` / `.fogCA.key`, which is exactly delegation. It is
only ever called for the `web` zone, so that branch is dead code today.

Turning it on is not a one-line change, and the reasons are worth recording:

1. The root is resolved and, on a fresh install, **minted** before the
   external-CA branch is reached — the communication leaf needs a signing key
   that early.
2. Root resolution is create-once and keyed on the certificate existing, so an
   upgrade would have to be taught to adopt a delegated root without treating it
   as "already done".
3. There is a canonical-path symlink between the historic root location and the
   root zone that both would write.
4. Decisively: it changes what **every fog-client pins**. That is a migration,
   not a flag.

This belongs with the certificate-management page
([#1121](https://github.com/FOGProject/fogproject/issues/1121)), not with the
installer settings work.

---

# Part B — Audit findings

Point-in-time, against `working-1.6` as of 2026-08-17. Line numbers drift.

## B1. Item 1 — does everything honour `httpproto`?

**Installer: yes, with three exceptions.** Every `curl` to FOG's own web tier
builds its URL from `$httpproto` — node existence probe (`functions.sh:480`),
node registration (`:508`), credential update (`:532`), DB backup (`:581`), web
tier probe (`:619`), schema deploy (`:746`), node certificate request (`:3574`).
So do every operator-facing URL and the final "setup complete" line.

| Finding | Where | Verdict |
| --- | --- | --- |
| `${httpproto:-http}` — the only inline scheme default left | `functions.sh:3574` | will disagree with a `https` global default |
| No `-L` and no status check; prints `Done` unconditionally | `functions.sh:532` | every other call to that endpoint carries `-L` because a redirect swallows the POST |
| Every FOG-server `curl` uses `-k` | `:480,508,532,581,619,746,3574` | nothing breaks on HTTPS, but nothing verifies either; `--cacert $sslcapem` is the natural companion change |
| Doubled slash — `${webroot}` already ends in `/` | `:480,508,532` | same class as the GH-978 fix; message and request disagree |
| `--netboot-proto` implemented and in longopts, absent from `usage()` | `installfog.sh:501`, `:202` | undocumented flag |
| Help text hardcodes `http://` examples | `installfog.sh:123-124,384,400` | cosmetic, becomes misleading |

**Flipping `installfog.sh:695` is not sufficient.** `input.sh:302` prompts
`[y/N]`, so pressing Enter yields HTTP; and `input.sh:309`'s empty arm catches
`-y`, so `installfog.sh -y` forces HTTP regardless of the default.

**PHP: nothing needs migrating, and nothing can express the setting either.**
`FOG_WEB_HOST` and `FOG_WEB_ROOT` are scheme-free, and there is no
`FOG_WEB_PROTOCOL` key. So no stored URL needs rewriting — and there is no
DB-side source of truth for any code path without `$_SERVER`, which is every
background daemon.

Classification of the ~20 `self::$httpproto` call sites:

- **Keep self-deriving (netboot):** `bootmenu.class.php:293`, `:294-301`,
  `:471-472`.
- **Keep self-deriving (same-origin):** `route.class.php:458-467`,
  `openapi.class.php:294-303`, `apidocumentation.page.php:76-80`.
- **Candidates for the configured setting:** `clientmanagement.page.php:65-70`
  (client installer links), `snapinclient.class.php:170-180` (URL handed to
  fog-client).
- **Genuinely per-node — a global setting is the wrong answer:**
  `storagenode.class.php:299-304`, `dashboardpage.page.php:534-539`,
  `fogbase.class.php:3340`, `serverinfo.page.php:74-79`,
  `storagenodemanagement.page.php:1194-1202`, `logtoview.php:180-184`,
  `route.class.php:2181-2187` and `:3839-3844`,
  `fogconfigurationpage.page.php:150-158`.
- **Hardcoded schemes, FOG's own hosts:** `fogservice.class.php:513-522` pins
  `http`, with `https` retries at `:637-644` and `:671-678`. `:586` probes port
  **80** hardcoded, so an HTTPS-only node reads as unreachable.

Riding alongside: `/fog/` is hardcoded at `bootmenu.class.php:472` and six other
sites, so a custom `--webroot` breaks those URLs independently of any protocol
question (GH-529 leftovers).

## B2. Item 2 — the conditional redirect

**The Apache path did not need the exclusion added — it already had it.** Both
web servers gate on `[[ $netbootproto != "$httpproto" ]]`. Sub-question one is
answered: no new mechanism is needed.

**`service/ipxe/` was not the complete set** — see Part A §A2. `service/secureboot/`
is fetched by iPXE directly and was not excluded, so on an
`httpproto=https` / `netbootproto=http` install the Secure Boot menu entries were
redirected onto an HTTPS iPXE cannot validate. **Fixed on this branch.**

**It can be expressed as a rule** — *every path iPXE itself fetches* — rather
than as an opaque list, but only because FOS's own fetches use `-Lks`. The rule
is stated in both generated configs now, with the `-k` dependency called out.

**`ca.cert.der` was exempt in Apache only.** nginx's `location /` caught it, so
on nginx fetching the CA required already trusting the CA. **Fixed on this
branch.**

**HSTS is the sharper problem, and it is not fixed here.**
`add_header Strict-Transport-Security max-age=15768000` is emitted on the nginx
`:443` server in *both* arms (`functions.sh:6046` and `:6179`) — that is, even
when `httpproto=http`. Apache emits none.

>[!danger]
>HSTS achieves client-side exactly what the redirect achieves server-side, is
>sticky for six months, and **is not undone by turning a setting off**. Any
>browser that has once reached the FOG server over HTTPS will refuse plain HTTP
>to it regardless of what the vhost later says. When #1119 introduces
>`httpsRedirect`, HSTS must be tied to *that* key, not to `httpproto` — and the
>existing unconditional emission needs a decision of its own, because servers in
>the field have already sent it.

Smaller redirect findings: the targets hardcode literal `https://`
(`functions.sh:6140`, `:6332`) and use `$host` / `%{HTTP_HOST}` with no port, so
a non-443 HTTPS deployment is not expressible; Apache's `[R,L]` is a **302**
while nginx uses **308**, which differ on POST; and `--no-vhost` (`-F`) silently
decouples `httpproto` from what the web server enforces.

## B3. Item 3 — is anything long-lived regenerated per run?

**No — the class is clean.** Every artefact in Part A §A3's first table is
guarded on its own existence, and the web leaf is guarded by the SAN stamp. The
per-run writes are ephemeral configs, derived concatenations, or republished
copies of create-once material.

This had no automated backstop, which was the actual risk. It has one now
(`tests/pki-idempotence.test.sh`), verified by reintroducing the historic
`[[ ! -x $sslpubcert ]]` guard and confirming the test catches it.

## B4. Item 4 — does an external root reach the OS trust store?

**Verified by reading every assignment: it did not.** This was the fix.

`--ca-root` / `--web-ca-root` land in `$extcaroot` / `$webExtCARoot`.
`validateExternalCA` reads them and writes them into the chain file — and, by its
own comment, deliberately never assigns `$rootCAPem`. Root resolution has no
branch that consults either variable, and it runs *before* the external-CA branch
is reached. So the trust anchor was FOG's own root while the vhost served the
admin's chain, and every server-side HTTPS call to itself still failed to
verify — the exact failure the mechanism exists to remove, silently unfixed on
the installs whose admins had thought hardest about certificates.

Four further defects in the same path, all fixed on this branch:

1. The root was selected from a chain **by position**, and the writers disagree
   on order (Part A §A4), so a storage node of an external-CA master anchored an
   intermediate.
2. The anchor was re-encoded with a single `openssl x509 -in`, which reads only
   the **first** certificate of a bundle and discards the rest with no error —
   which would have silently undone the fix.
3. On a node, `writeUpdateFile` runs *before* the certificate request, so the
   chain path was never persisted; on later runs the early "already issued"
   return left `$sslcachain` naming the node's own self-signed CA.
4. The web leaf's post-issue verification used `-CAfile $rootCAPem`, so it failed
   on **every** external-CA install and printed a warning telling the admin to
   widen `--internal-domain` and `rm -rf` the Web zone — advice unrelated to their
   configuration, one half of which destroys a working PKI. It now verifies
   against the chain's own root, using `-trusted`.

>[!note]
>`-trusted`, not `-CAfile`. `-CAfile` **adds to** the default trust locations
>rather than replacing them, and FOG installs its own CA into the host store by
>default — so a `-CAfile` check can answer "verified" out of the system store
>instead of out of the file it was handed. Verified in both directions against a
>two-CA fixture.

`--web-ca-cert` / `--web-ca-key` / `--web-ca-root` were also **not persisted** in
`.fogsettings`, unlike the `--ca-*` trio they mirror. Now added.

## B5. What Phase 2 had to carry out of this

>[!note]
>**All five were discharged by Phase 2 (#1119).** Kept here as the record of
>what the audit handed forward and how each was answered, not as outstanding
>work.

1. **The three `httpproto` gates.** ✅ Replaced by `_needsLocalIpxeBuild()`,
   which tests `rebuildIpxeWithMyCA` alone. The release asset now downloads on
   every install, and Secure Boot binaries stage in every mode — that gate had
   meant **every `-S` install staged none at all**.
2. **Split the redirect out into `httpsRedirect`.** ✅ Both vhost branches now
   gate on it, **and HSTS moved with it** — it had been emitted on the nginx
   `:443` server in both arms, including on plain-HTTP installs. Also
   unconditional now: `443/tcp` in the firewall advice, since both web servers
   have always emitted their `:443` vhost in both arms.
3. **`_resolveNetbootProto` inverts under the new default.** ✅ Replaced
   outright, not tweaked. It keyed on the persisted `caCreated`, so with
   `httpproto` defaulting to `https` every upgraded install would have resolved
   `netbootproto=https`. It now keys only on `publicWebCert` /
   `rebuildIpxeWithMyCA`, and **reports the outcome** — it previously emitted
   nothing at all.
4. **Fix `input.sh` alongside the default.** ✅ Its HTTPS question was removed
   rather than repaired: it set `httpproto`, which no longer varies. The four
   `--install-mode` presets are asked once instead, with their costs shown.
5. **Document `--netboot-proto`.** ✅ Done, along with every new flag —
   `tests/install-settings-resolution.test.sh` now asserts that each is both
   accepted *and* documented.

---

## Publishing notes for #1120

Part A is written to `fog-docs` house style already: literal `→` for menu paths,
Obsidian callouts, no bare line numbers, each wikilink on one line. Front matter
(`title` / `aliases` / `description` / `context_id` / `tags`) is added at port
time — it is deliberately absent here, since this repo's `docs/` is plain
Markdown.

Suggested destinations, cross-linked rather than merged:

| Section | Destination in `fog-docs` |
| --- | --- |
| A1, A2 | a new page under `docs/installation/network-setup/` — netboot transport and the redirect |
| A3, A4 | `docs/kb/reference/pki-zones.md` |
| A5 | `docs/kb/integrations/external-ca-lets-encrypt.md` |

Part B does not get published. It is working material for #1119, and its line
numbers are already aging.
