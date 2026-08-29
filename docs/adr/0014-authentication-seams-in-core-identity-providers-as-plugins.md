# Authentication seams live in core; identity providers are plugins

## Status

accepted

## Context

ADR 0007 settled what an external identity *means* — provenance is a core
fact, authority comes from roles — but it settled it for one plugin. LDAP was
the only way into FOG that was not a local password, and it had grown its own
answer to every question: how a login page offers it, how a proven identity
becomes a session, how a directory group becomes a role. None of those answers
were reusable, and one of them was actively wrong: with no way to say "I have
already proven who this is", the plugin's only option had been to write the
user's real directory password into `users.uPass` so the local compare would
succeed.

Phase 2 added a second provider, OpenID Connect. The real question was not
"how do we do OIDC" but **where the boundary goes**, because the answer is
paid for once and then binds every provider after it — SAML, Kerberos,
whatever an install needs that nobody here has thought of.

### The four things a provider needs that core did not have

All four were verified against `working-1.6` before anything was built:

- **A route.** Every plugin router hook mutates a *class list*
  (`API_VALID_CLASSES` and friends), and `Route::defineRoutes()` implodes
  those into a regex. A plugin could add a *resource*; it could not add a
  *path*. `/oidc/callback` is not a CRUD shape and never will be.
- **A page reachable without a session.** `Authorization::EXEMPT_NODES` was a
  `const`, and `resolvePagePermission()` denies an unmapped node — correctly,
  but it left no way to declare the page a visitor reaches *before* they have
  a session.
- **A way onto the login page.** The only login-adjacent hooks were
  `USER_TYPE_HOOK` and `LoginSuccess`, and neither emits markup.
- **A JWT library.** Verifying an ID token is signature checking against a
  published key set. Hand-rolling it is how `alg: none` keeps being a CVE.

Only the first three are structural. The fourth turned out not to be a
constraint at all: a plugin *can* load its own `vendor/autoload.php` — nothing
in core stopped it, only nothing helped it — and PR 2.1a added the help. The
library's placement is therefore a judgment call, and is recorded below as
one rather than as a necessity.

## Decision

### 1. The seams are core; the providers are not

Four extension points, each **deny-by-default**, documented in
`docs/plugin-development.md` §7c:

| Seam | Declares |
|---|---|
| `API_PLUGIN_ROUTES` | a route under `/ext/`, registered as `ext:<name>` |
| `PAGE_EXEMPT_NODES` | a page node that needs no session |
| `LOGIN_PAGE_PROVIDERS` | a button on the login page |
| `User::establishSession($source)` | a proven identity becoming a session |

The absence of a declaration is load-bearing in each. A route that declares no
permission and is not exactly `'public'` registers and then **denies** — it
answers 403 with a log line saying what to declare, rather than 404. That
inversion is the whole point: this is a new way to reach PHP inside the plugin
system, and Phase 1 was spent closing exactly that kind of hole. The router,
not the plugin, enforces the declared session requirement.

`/ext/` exists because core mints new top-level API paths from its own class
list, so today's free path is not tomorrow's. Under `/ext/` the two namespaces
cannot collide by accident.

### 2. OIDC ships as a plugin, in `FOGProject/fog-plugins`

Not in core. An install that does not use SSO carries no OIDC code, no OIDC
tables and no OIDC attack surface, and a provider bug is a plugin release
rather than a FOG release.

The scope is OIDC only. SAML is excluded on **shape**, not on dependency
weight: SAML's security rests on XML canonicalization and XML signature
verification, a class of parsing whose vulnerabilities (XXE, signature
wrapping, comment truncation) live in the parser rather than the protocol.
Building the seam properly is what makes SAML somebody's plugin later instead
of a core rewrite.

### 3. `firebase/php-jwt` lives in core's `vendor/`, as a guaranteed package

A judgment call, and reversible. Two reasons, both about the fact that this is
the library that decides whether a token is genuine:

- One version means one CVE response. Several provider plugins each vendoring
  their own means several, discovered separately.
- FOG should be able to accept an SSO login without the verifying library
  being an optional download.

Pinned at **v6.10.0**, the last release that runs on PHP 7.4. Nothing between
6.10.0 and 6.11.1 is a security fix. Composer refuses to install any php-jwt
below 7.0.0 without an explicit advisory ignore (CVE-2025-45769); accepting it
is narrow and was verified rather than assumed — upstream's fix is a minimum
key length, the HMAC half cannot apply because the flow allow-lists RS256 and
ES256, and the RSA half sits behind `openssl_pkey_get_private()`, which
returns false for public key material, so a relying party never reaches it.

### 4. Session provenance is a separate fact from account provenance

`users.uAuthSource` is a property of the **account** — which directory owns
it. `$_SESSION['FOG_AUTH_SOURCE']` is a property of the **request** — what
proved it this time.

They are not interchangeable and the difference is not pedantry. An account
owned by LDAP can still be signed in by something else, and after an incident
"did this person come in through the provider or through a local password?" is
precisely the question asked. The break-glass rules below also have to count
sessions by how they were *made*.

### 5. Break-glass is a core invariant, not a plugin's good behavior

**Local password login cannot be disabled.** Not a setting with a scary label —
no setting at all. `User::passwordValidate()` reads none, and
`tests/break-glass-auth-sources.test.php` is what keeps that true, because a
`FOG_DISABLE_LOCAL_LOGIN` added later with the best of intentions would look
reasonable in review and would make an outage unrecoverable.

**No single operation may remove the last administrator who can sign in
without the directory.** Writing `users.uAuthSource` onto them takes their
local password away; deleting them removes them outright. Both are now
refused, by `User::save()` and by
`Authorization::assertAdminRemainsAfterDelete()` respectively. Guarding only
one would be theatre — both leave the identical state.

The guard **preserves rather than requires**. It refuses only when a
locally-authenticating administrator exists right now: an install that has
deliberately moved everybody to a directory has nothing left to protect, and
refusing its operations would brick it to defend a property it already gave
up.

A `fog-user-token` does not count as satisfying the invariant. It reaches the
API and not the UI, and it is a bearer secret that can be rotated, revoked or
lost. It *is* a second, weaker path — token authentication never touches a
password or an auth source — and is worth knowing about; it is not a reason to
call an install safe. API **basic** auth is not such a path: it goes through
`passwordValidate()` and gets the same external-account gate as the browser.

### 6. What a provider grants is an association, per provider group

The same shape ADR 0007 chose for LDAP, for the same reason: a group is a
first-class row so that granting it a role is an ordinary FOG association and
the shared association tab can own it. Rows are scoped to the provider,
because a group name only means something relative to the directory that
published it.

Recomputed on every sign-in, so removing somebody from a group downgrades them
at their next login — which requires a **record of what the plugin granted**
(`oidcUserGrant`, `ldapUserGrant`). Both alternatives to keeping one are
wrong, and it is worth stating why because the second looks right:

- Removing everything not currently granted silently revokes what an admin
  attached by hand, and leaves no way to give a provider-authenticated user
  anything extra.
- Deriving the managed set from the mapping tables means deleting a mapping
  stops the role being the plugin's, so it is never taken away — removing a
  mapping would leave everyone who had it holding the role forever, which is
  the exact opposite of what removing a mapping reads as.

### 7. Provisioning is off by default, and only a provisioned account is stamped

An identity the provider is happy with, that this server has no account for,
is refused. Holding an account at the identity provider is not the same thing
as being allowed into FOG. Turning on just-in-time provisioning is an admin
saying otherwise, for one provider.

An account the flow **created** is stamped with `users.uAuthSource`; an
account an admin created is not, even when a provider signs into it. The stamp
refuses local password login for the row it is on, which is right in the first
case — the password is a random token nobody has seen, so there is nothing to
protect, and the stamp stops the leftover row becoming a login if the plugin
is removed — and wrong in the second, where it would take away exactly the
password break-glass depends on. The distinction is *who made the row*.

## Consequences

- A third-party auth provider is now possible without a core patch. That is
  the point, and it is also the risk: `/ext/` is a new way to reach PHP, and
  it is why every seam denies by default and why the router rather than the
  plugin enforces the gate.
- An install with one local administrator and several directory ones can no
  longer delete or convert that local account. Visible, intended, and
  escapable by giving another administrator a local password first.
- Core carries a dependency it does not itself use. `firebase/php-jwt` exists
  for plugins; core has no JWT of its own. Accepted as the price of one
  version and one CVE response.
- `establishSession()` is now the documented way to make a session. Anything
  that mints one by hand is a bug, and the provenance argument is what makes
  that checkable.
- The four seams are an ABI. Removing or narrowing one is a breaking change
  for every provider plugin, in the same way ADR 0013's reverse alias is.

## Alternatives considered and rejected

**OIDC in core.** Simpler to build and worse to live with: every install
carries the code and the tables whether or not it uses SSO, a provider bug
becomes a FOG release, and the seam never gets built — so the next protocol is
another core rewrite rather than somebody's plugin.

**Each provider plugin vendors its own JWT library.** Now possible (PR 2.1a
made core load a plugin's `vendor/autoload.php`), and rejected for this
library specifically. Two plugins vendoring different majors declare the same
class names and first-registered wins, which for a signature verifier is a
silent downgrade to whichever copy happened to load first.

**Reuse `users.uAuthSource` as session provenance.** It answers a different
question, and using it here would mean an LDAP-owned account signing in by any
other means would still read as an LDAP session.

**Let the plugin decide whether its own route needs a session.** Inverts the
default. A plugin that forgets to declare, or declares something truthy that
is not the exact string `'public'`, would open a route rather than close one.

**Make break-glass a setting.** An install could then switch off the thing
that makes an outage recoverable, and would only find out during the outage.

## References

- ADR 0005 — native RBAC retires the accesscontrol plugin
- ADR 0007 — external identity is provenance; authority comes from roles
- ADR 0009 — plugins become installable artifacts
- `docs/refactor-phase2-plan.md` — the plan this implements, PR by PR
- `docs/plugin-development.md` §7c — the seams, for plugin authors
- Core: #1111, #1112 (auth split), #1125 (library), #1128 (plugin vendor),
  #1130 (extension point), #1131 (session provenance), #1132, #1133 (outbound
  TLS), #1134 (break-glass)
- Plugin: `FOGProject/fog-plugins` #7 (provider row), #8 (the flow), #9 (claim
  mapping and provisioning)
