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
shapes the **generic CRUD** behaviour of a class that already exists.

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
SAML is XML, and its security depends on XML canonicalisation and XML
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

## Library choice: `firebase/php-jwt`

Recommended. `INFERRED` on the version facts — I have not fetched Packagist
from this box and the plan must not pretend otherwise; **PR 2.1 begins by
verifying them.**

| Candidate | Runtime deps | Fit |
|---|---|---|
| **`firebase/php-jwt`** | **none** | JWT decode + `JWK::parseKeySet()` for JWKS in one small package |
| `web-token/jwt-framework` | many (PSR container, HTTP client, …) | Complete and correct, and far more than a relying party needs |
| `lcobucci/jwt` | a few | Modern, but recent majors have moved past a 7.4 floor |
| hand-rolled | none | This is how `alg: none` ships. Not on the table |

It lives in core `vendor/` — decided, not forced; see *Open decisions*.

The deciding property is **zero runtime dependencies**, for two reasons that
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

### PR 2.1 — add the library, nothing uses it

Verify the Packagist facts first, then `composer require` under the 7.4
platform pin and commit `vendor/`.

```bash
cd packages/web && composer show firebase/php-jwt | grep -E 'versions|requires'
composer validate --strict && git status --porcelain packages/web/vendor | head
php -r 'require "packages/web/vendor/autoload.php";
        var_dump(class_exists("Firebase\\JWT\\JWT"));'   # true
sh tests/run-all.sh
```

### PR 2.1a — let a plugin have its own `vendor/`

Closes G4 properly rather than working around it. Core loads
`<plugin>/vendor/autoload.php` when present, mirroring the six lines it
already runs for itself, **plus** the collision check that makes it safe: a
plugin vendor re-declaring a class core or another plugin already provides is
refused and logged, not silently first-wins.

Independent of OIDC — every plugin author benefits, and it is the honest
answer to "why can't my plugin use Composer".

```bash
php tests/plugin-vendor-autoload.test.php
# fixture plugin with its own vendor/ resolves its class
# second fixture vendoring a conflicting version is REFUSED, not silently shadowed
```

### PR 2.2 — the extension point (this is the phase's real deliverable)

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

Gate: a fixture plugin in `tests/` registers a route, an exempt node and a
button, and the test asserts all three take effect — and, crucially, that a
plugin route which did **not** declare itself session-less is still gated.

```bash
php tests/plugin-extension-points.test.php
sh tests/run-all.sh
```

### PR 2.3 — `establishSession()` grows a provenance argument

An IdP-established session must be distinguishable from a password one, for
audit and for break-glass. Extends the login history entry and the session
with the auth source. No behaviour change for the password path.

```bash
grep -n 'function establishSession' -A5 packages/web/lib/fog/user.class.php
php tests/ipxe-auth-no-session.test.php
```

### PR 2.4 — the OIDC plugin: discovery, callback, claim → role mapping

Provider config in a plugin-owned table via the `schema()` contract.
**Default deny**: a successful IdP login for an unknown user fails with a
clear message naming the account, and JIT provisioning is a setting that
ships **off**. Claims map to **RBAC roles**, never to the legacy `type` field
— `USER_TYPE_HOOK` rewrites `type` (`user.class.php:172`), so anything
derived from it is not a decision anyone controls.

```bash
curl -sk "$FOG/oidc/callback"                 # 400, not a 500 or a session
curl -sk "$FOG/management/index.php" | grep -c 'Sign in with'
```

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
3. **Non-browser consumers are untouched.** The fog client, storage nodes and
   API tokens do not redirect; `VERIFIED` they never enter `validatePw()` at
   all, so an IdP outage cannot reach them.

```bash
php tests/break-glass-auth-sources.test.php
```

### PR 2.6 — docs + ADR 0014

`docs/plugin-development.md` gains the extension points; ADR 0014 records the
core/plugin split and why the library is in core.

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
