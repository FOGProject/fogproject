# PHASE 2 — Authorization providers

Plan only. Nothing here is implemented except PR 2.0, which already landed.

Baseline: `working-1.6` @ `5d8a7e4f6`. Every claim is marked `VERIFIED`
(I opened the file, `file:line` given), `INFERRED`, or `UNKNOWN`.

**Shape decision, agreed before planning: option B.** The auth seams live in
core; the OIDC provider ships as a plugin on top of a new extension point.
Option B rests on **G1-G3** — a plugin can register neither a route, nor a
session-less node, nor a login-page contribution. (An earlier revision also
leaned on G4; that argument was wrong and has been withdrawn.) The JWT library
is in core as a **decision** rather than a constraint — see *Open decisions*.
The rejected option A is in *Alternatives*.

---

## What is already banked

**PR 2.0 — the `authenticate()` / `establishSession()` split — is merged**
(#1111 working-1.6, #1112 dev-branch). The brief asks for it as Phase 2's
first requirement; it landed early because a security fix in
`service/ipxe/advanced.php` needed the same seam.

`VERIFIED` `user.class.php` now carries `authenticate()` (prove a credential,
no side effects) and `establishSession()` (turn a proven identity into a
logged-in session), with `validatePw()` kept whole as the two composed so no
third-party caller breaks. `FOGBase::authenticateOnly()` is the public seam.

This matters more than "one commit is done": **`establishSession()` is
exactly the entry point OIDC needs.** An IdP callback has no password to
present, so before the split there was literally no way to create a FOG
session other than knowing one. That is the sentence in the brief — *"Right
now the only way to create a session is to know a password"* — and it is no
longer true.

---

## Four findings that reshape the brief

The brief says: *"Where does ADR 0009's plugin system fail to support it?
That gap is more interesting to me than the OIDC code itself."* Here is the
gap, and it is wider than one hole.

### G1 — a plugin cannot register a route. `VERIFIED`

Every plugin-facing hook in the router mutates a **class list**, never a
route table: `API_VALID_CLASSES` (`route.class.php:537`),
`API_TASKING_CLASSES` (`:541`), `API_ACTIVE_TASK_CLASSES` (`:545`), plus
`API_REMOVE_COLUMNS`, `API_MASSDATA_MAPPING`, `API_UNISEARCH_RESULTS`,
`API_INDIVDATA_MAPPING`, `API_GETTER`, `API_SENSITIVE_FIELDS`. Each of those
shapes the **generic CRUD** behavior of a class that already exists.

`defineRoutes()` (`route.class.php:793`) builds its patterns by `implode`-ing
those class lists into a regex. There is no hook between the lists and
`AltoRouter`, so **a plugin can add a resource but cannot add a verb, a path,
or an endpoint that is not `/{class}/...`**. An OIDC callback is
`/oidc/callback` — not a CRUD shape on any class.

### G2 — a plugin cannot declare a session-less page. `VERIFIED`

`Authorization::EXEMPT_NODES` (`authorization.class.php:44`) is a `const`
array: `home, client, schema, hwinfo, logout, login`. A plugin cannot append
to a `const`.

And the fallback the brief asks about is **already closed**:
`resolvePagePermission()` (`:462-490`) used to return `null` (no check) for an
unregistered node and now denies by requiring a permission no role can be
granted. The brief asks *"whether it is a compatibility stance that will be
tightened later and break silently"* — it was tightened already, deliberately,
and the comment at `:463` says so.

**So the answer to the brief's question is: no, and do not rely on it.** An
OIDC callback must be explicitly exempted, and today only core can exempt it.

### G3 — a plugin cannot contribute to the login page. `VERIFIED`

`processlogin.page.php` fires exactly two hooks: `USER_TYPE_HOOK` (`:87`,
before the credential check) and `LoginSuccess` (`:107`, after it). Neither
contributes markup. A "Sign in with…" button has nowhere to come from.

### G4 — nothing *loads* a plugin's Composer autoloader. `VERIFIED`

**Corrected 2026-08-16.** An earlier revision of this plan said plugins "have
no Composer story" and used that to force the shape decision. That was too
strong, and the correction matters because the wrong version was doing
load-bearing work.

What is actually true: **nothing in core loads a plugin's
`vendor/autoload.php`.** That is a missing feature, not an impossibility.
`VERIFIED`:

- Core registers its own Composer autoloader in six lines
  (`commons/init.php:39-44`), guarded by `is_readable()`. Nothing about them
  is special to core.
- Composer autoloaders are `spl_autoload_register` callbacks; several
  coexist in one process.
- Plugin config files are `include`d (`plugin.class.php:347`), so a plugin's
  file body runs, and `FOG_PLUGIN_DIR` is already an autoload root
  (`init.php:317-318`).

So a plugin could `require` its own `vendor/autoload.php` **today**,
unspecified and unordered. No bundled plugin does; `VERIFIED` none carries a
`vendor/` or `composer.json`.

**The real problem is collision, not capability.** Two plugins vendoring
different majors of the same package both declare the same class names, and
whichever autoloader registered first wins — the other plugin silently runs
against a version it was never tested against. For a JWT library that is a
security bug, not an inconvenience.

That argues for three tiers, and PR 2.1a below adds the first and third:

| Tier | What | For |
|---|---|---|
| 1 | Core loads `<plugin>/vendor/autoload.php` when present | Ordinary plugin dependencies |
| 2 | Core ships a small set of **guaranteed** packages plugins may depend on rather than vendor | Where one shared version genuinely matters |
| 3 | Collision detection — refuse/log a plugin vendor re-declaring a provided class | Makes tier 1 fail loudly, not silently |

**Consequence for the shape decision — weaker than the earlier revision
claimed, and stated honestly:** the JWT library does **not** have to be in
core. Putting it there is a tier-2 judgment call, justified by one CVE
response and one version for a security-critical dependency that several auth
providers would otherwise each vendor. **G1–G3 are what option B actually
rests on; G4 no longer carries it.** See *Open decisions*.

### Not a gap: plugins can own tables. `VERIFIED`

`plugin.class.php:48` maps `'schema' => 'pSchema'` and `:1088` documents the
`schema()` contract as *"an ordered, append-only"* migration list with the
applied count tracked per plugin. So provider configuration can live in a
plugin-owned table; it does not have to be squeezed into `globalSettings`.
(This is a **working-1.6-only** capability — see *Third-party impact*.)

---

## Scope: OIDC only, not SAML — challenged, and the scoping holds

The brief asks me to justify or challenge it. It holds, but not for the
reason given.

The brief's reason is dependency weight. That is true but weaker than it
looks now that Phase 0 exists: committed `vendor/` means a big tree costs
repository size, not install-time risk.

The stronger reason is **shape**. OIDC is a browser redirect plus two HTTPS
calls returning JSON, verified with a signature over a well-known key set.
SAML is XML, and its security depends on XML canonicalization and XML
signature verification — a class of parsing where the vulnerabilities
(XXE, signature wrapping, comment truncation) live in the parser rather than
the protocol. `INFERRED`: that is a materially harder thing to ship safely in
a project whose PHP floor is 7.4 and whose install base includes distro
libxml versions nobody is tracking.

So: OIDC only, and the extension point from G1–G3 is what makes SAML
somebody's plugin later rather than a core rewrite. **That is the argument for
building the seam properly even though only one provider will use it at
first.**

---

## Library choice: `firebase/php-jwt` v6.10.0 — now `VERIFIED`, and it cost more than expected

PR 2.1 verified the Packagist facts, as this section told it to. Two of them
came back differently, and both are recorded here because they are the kind of
thing a later reader will otherwise assume was never checked.

| Candidate | Runtime deps | Fit under the 7.4 pin |
|---|---|---|
| **`firebase/php-jwt` v6.10.0** | **none** (`require` is `php` alone) | JWT decode + `JWK::parseKeySet()` in one 100 KB package. **Chosen** |
| `firebase/php-jwt` ≥ 6.10.1 | none | `VERIFIED` requires `php ^8.0`. Unreachable at our floor |
| `lcobucci/jwt` 4.3.0 | `lcobucci/clock` | `VERIFIED` resolves clean on 7.4, no advisories — but **no JWKS parsing**, so JWK→PEM becomes ours |
| `web-token/jwt-library` | many | `VERIFIED` latest needs PHP ≥ 8.2, and the 7.4-era line carries four open advisories |
| hand-rolled | none | This is how `alg: none` ships. Not on the table |

### Finding 1 — v6.10.0 (2023-12-01) is the last release that runs on PHP 7.4

`VERIFIED` by resolving against `config.platform.php = 7.4.0`. Everything from
6.10.1 onward requires `php ^8.0`. So the floor, not the library, is what dates
the dependency. What we forgo between 6.10.0 and 6.11.1 is `VERIFIED` from the
upstream release notes and contains **no security fix**: a `CachedKeySet`
rate-limit expiry fix, PHP 8.4 deprecation mitigations, octet-typed JWK
support, and an error-message wording change.

The 8.4 item is the only one with teeth — v6.10.0 uses the implicit-nullable
form (`string $defaultAlg = null`) that 8.4 deprecates. FOG sets
`error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE)` (`init.php:658`), so it is
silent in FOG's own runtime, but it is real and it is on the list of things a
floor bump would fix.

### Finding 2 — Composer refuses to install it, and that is the interesting part

`VERIFIED`: **every** php-jwt release below 7.0.0 is covered by
**CVE-2025-45769** (`PKSA-y2cr-5h3j-g3ys`, low), and Composer 2.10 blocks
advisory-affected packages *by default*. `composer require` fails outright
until `config.policy.advisories.ignore-id` names that id. This is not a warning
to wave through; without the line, the next person to regenerate `vendor/`
gets an error that reads as though the package no longer exists.

The advisory is *"php-jwt contains weak encryption"* — upstream added minimum
key-length validation, and the fix shipped in 7.0.0, which requires PHP 8.0
(it uses `str_starts_with()`).

**The fix does not apply to FOG's path, and this is worth stating precisely,
because "we accepted a CVE" deserves better than a shrug.** Upstream's
validation has two halves:

- The **HMAC** half rejects a secret shorter than the digest. Irrelevant here:
  the allow-list below is `RS256`/`ES256` only, so `HS*` never runs.
- The **RSA** half is guarded by `if ($key = openssl_pkey_get_private($keyMaterial))`
  on the verification path. `VERIFIED` empirically: `openssl_pkey_get_private()`
  returns `false` for public key material, whether a PEM or an
  `OpenSSLAsymmetricKey`. A relying party verifying an IdP's token with a
  public key from a JWKS therefore **never reaches the length check, even on
  7.x.** Upgrading would not close this for us.

So the residual risk of pinning 6.10.0 is: FOG would accept an IdP signing with
an undersized RSA key. That check is ours to write either way — it joins the
claims checks below, which the library was never going to do for us.

**The honest medium-term answer is a PHP floor bump, and it is deliberately not
made here.** PHP 7.4 has been end-of-life since November 2022; php-jwt is
simply the first dependency to make that visible. Raising the floor to 8.0 is a
project-wide decision that touches the installer, `Initiator::_verCheck()`
(`init.php:683`), `CONTRIBUTING.md` and every install on Debian 11 / Ubuntu
20.04 / stock RHEL 8 — far more than Phase 2 should decide as a side effect.
Recorded as the next open question instead.

It lives in core `vendor/` — decided, not forced; see *Open decisions*.

The deciding property was **zero runtime dependencies**, for two reasons that
are specific to FOG rather than general good taste:

1. Phase 0 chose **committed `vendor/`**, so every transitive dependency is a
   file in the repository and a thing to audit on a CVE. One package with no
   dependencies keeps that honest.
2. The platform is pinned to `php: 7.4.0` (`composer.json` `config.platform`)
   while servers run 8.x — `VERIFIED` this box is PHP 8.3.33. A package with a
   deep tree is far likelier to resolve differently under that pin.

**Non-negotiable in review:** algorithm allow-listing (`RS256`/`ES256` only,
never `none`, never `HS*` with a public key as the secret), `iss`/`aud`/`exp`
/`nonce` all checked, and JWKS fetched over TLS with a cache and a bounded
refresh. The library gives us signature verification; **every one of those
claims checks is ours.**

---

## Commit sequence

The tree boots and `sh tests/run-all.sh` passes after every commit.

### PR 2.0 — the auth split ✅ MERGED (#1111 / #1112)

```bash
php tests/ipxe-auth-no-session.test.php    # ok
```

### PR 2.1 — add the library, nothing uses it ✅ DONE

`firebase/php-jwt` v6.10.0 committed to `packages/web/vendor/`, with the
advisory acceptance from *Finding 2* and a gate that pins both. Nothing in FOG
loads it yet.

```bash
cd packages/web && composer validate         # clean but for the pre-existing version-field warning
php tests/vendor-committed.test.php          # ok
podman run --rm -v .../vendor:/src:ro php:7.4-cli ...   # 17 files, 0 lint failures
sh tests/run-all.sh
```

### PR 2.1a — let a plugin have its own `vendor/` ✅ DONE

Closes G4 properly rather than working around it. Core loads
`<plugin>/vendor/autoload.php` when present, mirroring the six lines it
already runs for itself, **plus** the collision check that makes it safe: a
plugin vendor re-declaring a class core or another plugin already provides is
refused and logged, not silently first-wins.

Independent of OIDC — every plugin author benefits, and it is the honest
answer to "why can't my plugin use Composer".

Landed as `Initiator::_registerPluginAutoloaders()`, called last in the
constructor so plugin loaders sit behind core's Composer loader, the FOG class
map and the built-in resolver. That ordering is the real protection: even with
the collision check wrong, core answers first for any name it can serve.
Documented for plugin authors in `docs/plugin-development.md` §7b.

```bash
php tests/plugin-vendor-autoload.test.php
# four fixture plugins in a throwaway FOG_PLUGIN_DIR:
#   its own vendor/ resolves; a second claiming the same namespace is REFUSED;
#   one vendoring a package CORE provides is refused and core's copy wins;
#   one with no vendor/ is silent
# verified failing both with the call removed and with the check neutered
```

### PR 2.2 — the extension point (this is the phase's real deliverable) ✅ DONE

Three seams, each closing one gap, each independently useful:

- **G1** — a hook in `defineRoutes()` letting a plugin contribute a literal
  path with a handler. Deliberately *not* a general "plugins may run
  anything": a registered path declares whether it needs a session, and the
  router enforces that rather than trusting the plugin.
- **G2** — make the session-less set extensible. `EXEMPT_NODES` stays a
  `const` as the core floor; a registry method merges plugin entries on top,
  so the const remains readable as "what core exempts".
- **G3** — a `LOGIN_PAGE_PROVIDERS` hook contributing a button (label, icon,
  start URL) to the login form.

Landed with a fourth thing the plan did not anticipate, and which turned out
to matter more than any of the three: `resolveApiPermission()` **already**
returned `null` — no check — for a route name it did not recognize, described
in a comment as matching the unregistered-page stance that had since been
tightened everywhere else. Harmless while nothing could add a route; an open
default the moment something could. Flipped to deny first, in its own commit,
after verifying all 29 core route names are mapped.

The seam then enforces, rather than trusts:

- Plugin routes live under a reserved `/ext/` mount point, so a plugin path and
  a core path cannot collide — which also means declaring one public can never
  open a core path by exact-match coincidence. Not `/plugin/`: that is already
  an API class.
- Route names are registered as `ext:<name>`, because the name is what the
  permission layer keys on and an unprefixed `status` would inherit core's
  "no check".
- A route declaring no permission is registered but not declared, so it answers
  403 with a diagnostic instead of 404 with silence.
- `EXEMPT_NODES` stays a `const`; plugin entries merge on top and a node in the
  permission registry is refused, so this cannot be used to turn the gate off
  on `host`.
- Login-button start URLs must be site-absolute or `https://`.

Gate: `tests/plugin-extension-points.test.php`, driving all three seams with a
stub hook manager and no database.

```bash
php tests/plugin-extension-points.test.php
# 8 route fixtures, incl. one declaring nothing (must be DENIED), one with a
# misspelt auth, one at /system/export, one with ../ in the path
# verified failing with: any-truthy auth accepted; the /ext/ check removed;
# the exempt-node registry guard removed; the provider URL check removed
```

```bash
php tests/plugin-extension-points.test.php
sh tests/run-all.sh
```

### PR 2.3 — `establishSession()` grows a provenance argument ✅ DONE

An IdP-established session must be distinguishable from a password one, for
audit and for break-glass. Extends the login history entry and the session
with the auth source. No behavior change for the password path.

Landed as `establishSession($source = self::AUTH_SOURCE_PASSWORD)`, writing
`$_SESSION['FOG_AUTH_SOURCE']` and appending `(<source>)` to the history
entry. Two things the plan did not spell out and that the implementation
had to settle:

- **The value is normalized, not trusted.** A provider plugin supplies it and
  it lands in an audit trail, so anything that is not a plain slug
  (`^[a-z0-9][a-z0-9_-]{0,31}$`, case-folded and trimmed) is recorded as
  `unknown` rather than passed through. The normalization is a separate
  public static — `User::normalizeAuthSource()` — purely so it can be tested
  without a database; `establishSession()` itself writes a history row and
  cannot be exercised DB-free.
- **`logout()` needs no line of its own,** and the gate pins why rather than
  adding a redundant-looking one: `session_unset()` empties `$_SESSION`
  wholesale, so this key and every future one are cleared without anyone
  remembering to. If that ever becomes a selective unset, provenance would
  survive a logout and describe the *next* session — so the test fails on
  the wholesale clear disappearing.

Worth restating because it is the distinction the whole PR rests on:
`users.uAuthSource` is a property of the **account** (which directory owns
it), `$_SESSION['FOG_AUTH_SOURCE']` is a property of the **request** (what
proved it this time). Break-glass in 2.5 needs the second one.

```bash
grep -n 'function establishSession' -A5 packages/web/lib/fog/user.class.php
php tests/session-provenance.test.php
# 13 normalizer cases + the 32-char boundary; static pins on the session
# stamp, on validatePw() still taking the default, and on logout()'s
# wholesale clear
# verified failing with: normalizeAuthSource() bypassed in establishSession()
php tests/ipxe-auth-no-session.test.php
```

### PR 2.4 — the OIDC plugin: discovery, callback, claim → role mapping

Lives in `FOGProject/fog-plugins`, not here, and is **split in two**. The
configuration half is inert on its own, and splitting means the
security-critical half arrives in a diff somebody can read rather than at the
end of a very long one.

#### PR 2.4a — the provider row ✅ DONE (fog-plugins #7)

Provider config in a plugin-owned table via the `schema()` contract, plus the
management page, the permission node and the API surface. Nothing signs
anybody in yet.

Three findings from building it, all worth carrying into 2.4b:

- **`FOGURLRequests` could not be used for the token exchange — so it was
  fixed instead.** It defaulted `CURLOPT_SSL_VERIFYPEER` and `VERIFYHOST` to
  false for *every* request, which is correct for reaching a storage node by
  bare IP with a self-signed certificate and disqualifying for an OIDC token
  exchange, where TLS to the provider *is* the security model. Rather than
  give the plugin a private HTTP client, the class now verifies by default
  and exempts only hosts this install owns — decided by the URL's host, so a
  new caller cannot inherit the exemption by not thinking about it. It also
  stopped attaching the signed-in administrator's session cookie and CSRF
  token to third-party requests. 2.4b uses `FOGURLRequests` directly.
- **`/ext/…` routes get no session.** `api/index.php` does not define
  `FOG_WANTS_SESSION`, and a visitor clicking the login button has no cookie
  yet, so the #1113 gate correctly declines to start one. The `state`, `nonce`
  and PKCE verifier need somewhere to live, so the two browser-facing handlers
  call `session_start()` themselves — a handler mid-redirect knows it is a
  browser, which is exactly the judgment the gate exists to stop *browser-less*
  callers making. Documented in `docs/plugin-development.md` §7c rather than
  added to the route contract as a flag.
- **The rules belong in the model, not the page.** The REST API reaches the
  same columns, and every way a provider row can be wrong is silent: an
  `http://` issuer lets somebody on the path serve their own signing keys, and
  a scope list missing `openid` produces a login that completes and returns no
  ID token.

```bash
php tests/oidc-provider-safety.test.php       # in fog-plugins
# verified failing by accepting an http:// issuer (4) and by defaulting
# just-in-time provisioning on (1)
```

#### PR 2.4b — the flow ✅ DONE (fog-plugins #8)

Discovery, start, callback, ID token verification against the JWKS, and claim
→ role mapping. **Default deny**: a successful IdP login for an unknown user
fails with a clear message naming the account, and JIT provisioning is the
setting that ships **off** (already a column, already defaulted off, pinned by
2.4a's gate). Claims map to **RBAC roles**, never to the legacy `type` field —
`USER_TYPE_HOOK` rewrites `type` (`user.class.php:172`), so anything derived
from it is not a decision anyone controls.

Identity matching, settled before building: the configured claim (default
`preferred_username`) matches `users.uName`, **and** `sub` is recorded on the
first successful login; thereafter the stored `sub` wins and a mismatch
refuses the login. Name alone is what most integrations do and it is
reassignable — a departed user's name reissued by the provider inherits their
FOG account. `sub` alone is unimpeachable and matches no account that exists
today, so every install would start with nobody linked.

Split once more on the same reasoning as 2.4a: **claim → role mapping moved
to 2.4c.** With JIT provisioning off — the default, and the only mode that
exists — a user must already exist in FOG with roles an admin assigned, so
nothing in the flow needs to map a claim to a role. 2.4b therefore stands
alone as a complete, safe feature (SSO sign-in for existing accounts, default
deny for everyone else) and the flow arrives in a diff worth reading closely.

Three things the shape of the code is load-bearing for, each of which leaves
a *working* sign-in if it regresses and so is pinned by the gate:

- the flow values are cleared from the session **before** any validation can
  fail — clearing them after a successful sign-in leaves a usable `state` and
  PKCE verifier behind after every failure, which is the same authorization
  code being presentable twice;
- the discovery document must name the issuer that was asked for —
  `FOGURLRequests` follows redirects, and every endpoint used, including the
  one the client secret is posted to, comes out of that document;
- the identity already in the session is cleared before the new one is
  established — `User::establishSession()` prefers the boot-time `$FOGUser`
  when it is valid, and **both** halves have to go, because emptying
  `$_SESSION` leaves the static that establishSession() actually reads.

```bash
php tests/oidc-flow-safety.test.php           # in fog-plugins
# verified failing by removing the nonce check and by moving the session
# clear inside the try block
curl -sk "$FOG/ext/oidc/callback"             # redirect to login + message,
                                              # never a 500 or a session
curl -sk "$FOG/management/index.php" | grep -c 'Sign in with'
```

#### PR 2.4c — claim → role mapping and JIT provisioning ✅ DONE (fog-plugins #9)

Claims map to **RBAC roles**, never to the legacy `type` field —
`USER_TYPE_HOOK` rewrites `type` (`user.class.php:172`), so anything derived
from it is not a decision anyone controls. Shaped like the LDAP plugin's
`LDAPGroups` + two association tables, because granting a role or a user
group is an ordinary association and the shared association tab needs the
group itself to be the owning object. Turning JIT provisioning on becomes
meaningful at the same moment: a created account with no roles is not
useful, so the column stayed inert until this landed.

Four tables, not three. `oidcUserGrant` records what the plugin granted each
user, and it is the piece that is easy to leave out and impossible to add
later without a migration. The sync has to answer "which of this user's roles
are ours to remove?", and both answers available without a record are wrong:
removing everything not currently granted revokes what an admin attached by
hand, and deriving the managed set from the mapping tables means deleting a
mapping stops the role being ours — so removing a mapping leaves everyone who
had it holding the role forever. The LDAP plugin learned this the same way.

Two decisions worth carrying forward:

- **A provisioned account IS stamped with `users.uAuthSource`; an
  admin-created one is not.** This looks like a contradiction of 2.4's
  decision and is not. The stamp refuses local password login for the row it
  is on. On an account this flow created that is correct — its password is a
  random token nobody has seen, so there is no local login to protect, and
  the stamp stops the leftover row becoming one if the plugin is removed. On
  an account an admin created it would take away exactly the password login
  break-glass depends on. The distinction is *who made the row*.
- **A scalar group claim is one value and is never split.** Every delimiter
  worth guessing is legal inside a group name, and a wrong split invents a
  value that can match a mapping nobody wrote. Providers that emit a
  delimited string are a per-provider option if anybody asks; guessing is
  not.

The claim lookup joins `OIDCGroups` with raw bound SQL for the reason 2.4b
used it for the subject: `_buildSql()` turns `*` and `+` in a scalar filter
value into a SQL `LIKE` wildcard, and a claim value of `*` would otherwise
collect every mapping the provider has.

`OIDCGroups` carries a unique index on (provider, name) where `OIDCProviders`
deliberately carries none. The index is safe here because the key covers
every non-id column of the table, so the `ON DUPLICATE KEY UPDATE` half of a
save can only rewrite what it matched on.

Left out on purpose, and still open: the reverse-direction tabs on the Role
and User Group pages (LDAP has `addldapgrouptabs.hook.php`). They read the
same data from the other end. Worth doing, not worth doubling this diff.

### PR 2.5 — break-glass, and the test that proves it

The brief is right that this is the part that matters. Three properties:

1. **Local password login can never be disabled by configuration.** Not a
   setting with a scary label — no setting at all.
2. **`effectiveAdminExists()`-style reasoning extends to auth sources.** The
   existing guard stops you deleting the last administrator
   (`authorization.class.php`, reached from `fogcontroller.class.php:924`).
   The auth-source analogue: you may not convert the last locally-authenticating
   administrator to an external identity. `VERIFIED` the shape of that guard
   already exists — `assertAdminRemainsAfterDelete()` — and PR 2.5 adds the
   sibling assertion rather than inventing a mechanism.
3. **Non-browser consumers are untouched.** ⚠️ **Half wrong as written, and
   corrected here rather than repeated.** The fog client, storage nodes and
   `fog-user-token` API auth never touch a password or an auth source
   (`route.class.php:1312`), so an IdP outage cannot reach them — and a token
   is therefore a second, weaker way in. But API **basic** auth *is* affected:
   `passwordValidate()` fires `USER_LOGGING_IN` and applies the same
   external-account gate as the browser. A directory-owned account cannot use
   basic auth while its directory is down.

```bash
php tests/break-glass-auth-sources.test.php
```

**Two things the plan did not anticipate, both settled in #1134:**

- **The login path was already safe**, so the messy case never arose. LDAP
  returns early for an account that exists and is not already its own, and
  OIDC stamps only accounts it provisioned. Nothing converts an existing local
  account at login. What was open was the *deliberate* administrative paths —
  a REST `PUT /fog/user/{id}` (uAuthSource is an ordinary field, absent from
  `Route::$serverOwnedFields`), a plugin's own `save()`, and the CSV import —
  which is why the guard sits on `User::save()` rather than on a caller.
- **The guard preserves rather than requires.** It refuses only when a
  locally-authenticating administrator exists now. An install that has already
  moved everybody to a directory has nothing left to protect, and refusing its
  operations would brick it to defend a property it gave up.

### PR 2.6 — docs + ADR 0014 ✅ DONE

`docs/plugin-development.md` §7c gains *What an auth plugin owes the install*
— the three rules a provider author gets wrong by omission — and ADR 0014
records the core/plugin split, the four seams as an ABI, why the library is in
core (a judgment call, not a necessity), and break-glass as a core invariant.

User-facing documentation goes to `FOGProject/fog-docs`, not here: only the
ADR and the plugin-author guide are fogproject's.

---

## Alternatives considered and rejected

**A — all of it in core.** Rejected. Since G4 forces the library into core and
G1–G3 force the seams into core anyway, A's only saving is not designing the
extension point — and that extension point *is* the deliverable the brief
says it cares most about. A also means the second provider is another core
change, which contradicts the phase's own name.

**Rely on the unregistered-page fallback for the callback.** Rejected on
evidence, not taste: it no longer exists (G2). It would have been the
brief's own worst case — an auth endpoint depending on a compatibility stance
that was quietly tightened.

**JIT provisioning on by default.** Rejected, per the brief. An IdP that
authenticates your whole company would otherwise mint a FOG account for
everyone in it on first visit.

**Map claims to `type`.** Rejected. `USER_TYPE_HOOK` rewrites `type`
mid-flight, and Phase 1 already moved authorization to RBAC. Mapping to
`type` would reintroduce the exact confusion `isSchemaAdmin()` was created to
escape.

**Put provider config in `globalSettings`.** Rejected for working-1.6, where
plugins own tables. A client secret in a settings row is one careless settings
export from being shared. (On dev-branch this would be the only option —
which is a reason the port is not automatic, not a reason to do it here.)

---

## Irreversible steps and data migrations

| Step | Reversible? | Rollback |
|---|---|---|
| PR 2.1 commits `vendor/` | soft one-way — removed from tip, stays in the pack | Same standing accepted in Phase 0 |
| PR 2.4 creates a plugin table | yes | `schema()` is append-only; uninstall drops it. 🔴 Phase 1's lesson: **dropping a table does not disable its readers** |
| PR 2.4 stores a client secret | yes, but it is a **secret** | Must be excluded from settings export and from `API_SENSITIVE_FIELDS`-style output before it is ever written |
| PR 2.3 session provenance | yes | Additive |

No `FOG_SCHEMA` bump: the core schema is untouched. **If that changes, both a
`schema.php` migration and a `FOG_SCHEMA` bump are required** or the step is
silently skipped — `tests/schema-gate.test.php` enforces it.

---

## Third-party plugin authors

**Nothing breaks.** Every seam is additive; a plugin that registers nothing
behaves exactly as today. No deprecation window is needed because nothing is
removed or renamed.

Two things become possible: a plugin may register a route, a session-less
node and a login-page button (PR 2.2), and may depend on `Firebase\JWT`
(PR 2.1) — though it must not assume any *other* core `vendor/` package will
stay, since that list is FOG's to change.

One thing authors must know: **the `schema()` plugin-table contract is
working-1.6 only.** A plugin using it will not run on 1.5.x, where
`globalSettings` is the only reachable storage.

---

## INFERRED

- `firebase/php-jwt` runs on PHP 7.4 with no runtime dependencies. Historically
  true; **PR 2.1 verifies it before committing anything.**
- SAML's parser-level risk is materially worse than OIDC's. Well-established
  in general; not measured against FOG's specific install base.
- A plugin-contributed route can be made safe by having the *router* enforce
  the declared session requirement. Prototyped nowhere yet — PR 2.2's fixture
  test is what turns this into `VERIFIED`.

## UNKNOWN

1. **Whether `fog-workflows` CI can run `composer`.** Phase 0 left this open
   and it is now load-bearing: without it, nothing checks `vendor/` against
   `composer.lock`.
2. **What the fog client does when its user's account becomes external.**
   Client auth is token-based and separate, but no one has traced an account
   that changes auth source underneath a live client.
3. **Whether any shipped plugin already collides with a node named `oidc`.**
   Cheap to check at PR 2.4; not checked yet.
4. **Session fixation across the IdP round trip.** `_isLoggedIn()` regenerates
   on a cadence (`user.class.php:391-402`), but whether that is sufficient at
   the callback — where the pre-login session is attacker-influencable — needs
   deciding in PR 2.4, not assumed.
5. **Clock skew tolerance for `exp`/`iat`.** An imaging server with bad NTP is
   not hypothetical.

---

## Open decisions

**Does the 1.6 line raise its PHP floor from 7.4 to 8.0?** Surfaced by PR 2.1
(see *Finding 1*), not created by it. 7.4 has been end-of-life since November
2022, and the floor is now costing real things: a JWT library frozen at
December 2023, an accepted CVE that a supported release would not carry, and
PHP 8.4 deprecations we suppress rather than fix. Against that: the installer
enforces no minimum at all (`functions.sh:2323` only *detects* the version),
`Initiator::_verCheck()` admits 7.4 deliberately, and stock Debian 11 /
Ubuntu 20.04 / RHEL 8 would stop installing. **Not Phase 2's decision** —
recorded so it is made on purpose rather than by the next dependency.


**Where does `firebase/php-jwt` live? DECIDED 2026-08-16: core `vendor/`.**
Not forced by G4 — chosen, on the CVE argument, and reversible. Tom's reason:
FOG should be able to accept an OIDC login without the library being
somebody's optional download.

| | Core (tier 2) | OIDC plugin |
|---|---|---|
| CVE response | one bump, every install | each provider plugin, separately |
| Version skew across providers | impossible | two auth plugins can disagree; PR 2.1a's check turns that into a refusal rather than a silent wrong version |
| Cost to non-SSO installs | ships a library they never load | nothing |
| Precedent set | "core provides security primitives" | "plugins own their dependencies" |

So `firebase/php-jwt` is **tier 2** — core ships it and guarantees it, and a
provider plugin depends on it rather than vendoring its own copy. PR 2.1a's
collision check is what makes that guarantee enforceable instead of a
convention: a plugin vendoring a second copy is refused, not silently first-
wins. Reversible if it turns out wrong — moving the package later is a
`composer.json` change in one repo or the other.

## Which claim, if false, would hurt most?

**"A plugin-contributed route can be gated as safely as a core route."**

Everything else degrades gracefully. If the library is wrong we swap it; if
the claim mapping is wrong we fix a mapping. But PR 2.2 puts a **new way to
reach PHP** into the plugin system, and Phase 1 spent real effort closing
exactly that shape of hole — `resolvePagePermission()` denies unregistered
nodes today *because* plugin pages that registered nothing were reachable by
every authenticated user (`authorization.class.php:463-469`). A route seam
that lets a plugin declare "no session needed" is that hole with a config
option attached, unless the router — not the plugin — is what enforces it.

The check I would want done by hand before approving PR 2.2: **register a
fixture plugin route that declares nothing, and confirm it is denied rather
than reachable.** If the default is "open unless declared", the design is
inverted and the whole seam needs rethinking.

Second place: **"the fog client, storage nodes and API tokens never enter
`validatePw()`."** If false, an IdP outage reaches the imaging path, and the
break-glass argument in PR 2.5 collapses.
