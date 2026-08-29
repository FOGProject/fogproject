# FOG working-1.6: Modernization Planning Brief

You are producing **plans, not implementations**. Read this whole file before
doing anything.

There are four phases. They are ordered by dependency, not by importance.
**Plan one phase at a time and stop for my approval at each gate.** Do not
plan Phase 2 until I have approved Phase 1. If you find yourself planning
across phase boundaries, stop and tell me why the boundary is wrong.

---

## Ground rules (apply to every phase)

Before writing anything, read the actual code. Do not plan from assumption.
Every factual claim you make about how FOG currently works must cite
`file:line`, and you must have actually opened that file.

Mark every statement as one of:

- `VERIFIED` - I read this in the code, here is file:line
- `INFERRED` - this follows from what I read but I did not confirm it
- `UNKNOWN` - I could not determine this

If the INFERRED and UNKNOWN sections are short, you have not looked hard
enough. I would rather have twenty honest unknowns than a confident plan
that is wrong in three places.

Each phase plan must contain:

1. A **commit-by-commit sequence** where the application boots and the test
   suite passes after every single commit. No step may leave the tree broken
   pending a later step.
2. For each commit, a **shell command** (grep, test, curl) I can run to verify
   it did what it claims. If a claim cannot be reduced to a command, say so
   explicitly rather than inventing one.
3. **Alternatives considered and rejected**, with reasons. Match the style of
   `docs/adr/0009-plugins-become-installable-artifacts.md`.
4. Every **irreversible step** and every **data migration** called out
   separately, each with its rollback story.
5. What breaks for a **third-party plugin author**, and the deprecation window.

Ask me questions before planning if the codebase does not answer something.
Do not guess and proceed.

At the end of each phase plan, answer this directly: **which claim in this
plan, if false, would hurt most?** I will verify that one by hand before
approving.

---

## Why this order

- Composer must exist before the auth work, because the entire argument for
  provider-based auth is not hand-rolling JWT validation.
- The backslash-prefix pass must happen before anything else is written, or
  new code needs the same treatment later.
- Site and RBAC must settle before auth, because auth maps IdP claims to
  roles and those roles need to be stable first.
- Namespacing goes last because it touches every file. Anything in flight
  during it becomes a merge conflict factory. The bridge autoloader from
  Phase 0 is what allows it to be last without blocking the rest.

---

## PHASE 0: Foundation

Read `packages/web/commons/init.php` (the autoloader, `autoload()` around
line 256) and `packages/web/lib/fog/fogbase.class.php` `getClass()` around
line 531.

Plan three independent changes, as separate PRs, in this order:

**0.1 - Backslash-prefix every unqualified global class reference** across
`packages/web`. `\Exception`, `\DateTime`, `\DateTimeZone`, `\PharData`,
`\ReflectionClass`, `\PDO`, `\RecursiveIteratorIterator`, and anything else
you find. In the global namespace this is a no-op, which is the point: it
must be reviewable as a purely mechanical diff with zero behavior change.
Give me the exact count per class before proposing the change.

This exists because inside a namespace, `catch (Exception $e)` silently
becomes `catch (FOG\Exception $e)`, which fails only on the error path, only
at runtime. Defusing it now, in a boring standalone commit, is what turns
the scary part of Phase 3 into a non-event.

**0.2 - Bridge autoloader.** Make the existing autoloader resolve a
namespaced request by falling back to the short name and `class_alias`ing
the result, so `FOG\User` works before any file is namespaced. Handle the
case where the included file does not declare the expected class name.
Note that `autoload()` builds a lowercased basename map, so a namespaced
lookup currently misses entirely and silently.

**0.3 - composer.json** with PSR-4 `FOG\` mapped to a new, empty
`packages/web/src/`, registered alongside the existing autoloader rather
than replacing it. `spl_autoload_register` is a chain; both can coexist.

For 0.3 the real question is operational, not code. FOG installs on
air-gapped and firewalled networks and currently vendors nothing. Lay out
committing `vendor/` to the repo versus running `composer install` at
install time: failure modes, repo size, update story, what happens when
Packagist is unreachable mid-install, and how `bin/fetch-plugins.sh`
interacts with either choice. Recommend one and argue for it.

Also answer: does anything compare class names as strings, e.g.
`get_class($x) == 'Host'` or `::class` against a literal? Those do not
survive aliasing. Find them all and list them.

**GATE. Stop here for approval.**

---

## PHASE 1: Site into core, alongside RBAC

Read `docs/adr/0006`, `docs/adr/0009`,
`packages/web/lib/fog/authorization.class.php`, and the `site` plugin at the
external plugin root.

Plan moving site's scope enforcement into core alongside RBAC.

**Design constraint I have already decided: no enable flag.** Sites are
unconditional. Zero sites defined means one implicit site containing
everything, which is today's behavior and today's out-of-box experience.
Defining a site is what makes the boundary start to mean something. A
boundary that exists only when a boolean is true is still a boundary that
can be absent. Argue against this if you think I am wrong, but argue
*before* planning, not during.

Answer these from the code specifically:

- `objectInScope()`, `requirePageObjectScope()` and the Mass variant
  currently depend on the site plugin's hook listeners. What exactly happens
  to each when no listener is registered? Quote the code path.
- Do `registry()` or `purgePermissions($nodePrefix)` derive permission node
  names from class names or plugin names? If yes, moving site into core
  changes those strings, and that is a data migration that could silently
  revoke access on live servers. **This is the thing I am most worried
  about. Treat it as the headline question of this phase.**
- The site plugin owns tables with real customer data. Plan the migration
  for three cases: servers with the plugin installed, servers that never had
  it, and a server where the migration fails halfway through.

The plugin must keep existing on disk for at least one release. Plan what it
degrades into: a shim, a no-op, or a hard error telling the admin to remove
it.

Related, and cheap: there is no `'site'` string anywhere in
`plugin.class.php` or `pluginmanagement.page.php`, so nothing stops a future
commit adding `fog_max` to site's manifest and silently deleting the
security boundary via auto-deactivation. Add a CI test asserting site
declares no `fog_max`, regardless of what else this phase does.

**GATE. Stop here for approval.**

---

## PHASE 2: Authorization providers

Read `user.class.php` `login()`, particularly the `USER_LOGGING_IN` hook
around line 127 and the `isExternal` guard at 144-153. Read
`authorization.class.php`. Read the `ldap` plugin's hooks.

`USER_LOGGING_IN` already works for credential-presenting sources like LDAP
and AD. It cannot express OIDC, because there is no password: the browser
leaves for the IdP and returns with an authorization code. Plan the second
seam.

**Scope: OIDC only, not SAML.** OIDC covers Google, Microsoft Entra, Okta,
Authentik, Keycloak, Auth0, and GitHub is close enough to share most of the
code. SAML means a large dependency tree for a project that currently
vendors nothing. Justify or challenge that scoping.

Required:

- **Split `login()`** into `authenticate()` and `establishSession(User)`.
  Right now the only way to create a session is to know a password. Show me
  the exact split against the real method body, including remember-me
  cookies and `UserAuth` token handling. This refactor is worth doing on its
  own merits regardless of SSO.
- **A route reachable without a session** for the OIDC callback. Read the
  router and `resolvePagePermission()` and tell me whether the
  unregistered-page fallback is safe to rely on for an auth endpoint, or
  whether it is a compatibility stance that will be tightened later and
  break silently.
- **Provisioning policy: default deny.** A successful IdP login for a user
  with no existing FOG account fails with a clear message. JIT provisioning
  is a later switch, not the default. Claims map to RBAC roles, never to the
  legacy `type` field that `USER_TYPE_HOOK` rewrites.
- **Break-glass.** Interactive SSO can never be the only way in. The FOG
  client, storage nodes and API consumers do not do browser redirects, and
  an IdP outage must not lock every admin out of the imaging server during
  the same incident that made them need it. Show how `effectiveAdminExists()`
  style reasoning applies to auth sources.
- **Library choice** for JWT and JWKS. Hand-rolling this is how `alg: none`
  bugs happen. Recommend a specific package, justify it on dependency
  weight, and confirm it works under whatever vendoring decision came out of
  Phase 0.

This will be the first genuinely third-party-shaped plugin: it needs a
route, a login page contribution, a settings page, and a session. Where does
ADR 0009's plugin system fail to support it? That gap is more interesting to
me than the OIDC code itself.

**GATE. Stop here for approval.**

---

## PHASE 3: Namespacing

Read `commons/init.php` `autoload()`, `fogbase.class.php` `getClass()`, and
the class hierarchy under `packages/web/lib/fog`.

Phase 0 is merged by now: globals are backslash-prefixed, the bridge
autoloader resolves namespaced names via `class_alias`, and composer PSR-4
maps `FOG\` to `packages/web/src/`.

Plan migrating 227 classes with `class_alias` in **both directions**, so old
and new names work simultaneously and the app works after every commit.

- **Sequence: leaves first**, `FOGBase` / `FOGController` /
  `FOGManagerController` last, since everything descends from those. Derive
  the actual dependency graph from the code. Do not guess at what is a leaf.
- **`getClass()` has 491 call sites and 136 distinct names.** Convert it to
  a short-name-to-FQCN map so those call sites never need editing. This is
  the whole reason the migration is tractable: one chokepoint controls the
  overwhelming majority of class references.
- While in there, remove the `global $sub` that steers `Storage` to
  `StorageNode` or `StorageGroup`. Tell me what that breaks. It is a
  landmine independent of namespacing.
- **Namespace layout:** propose one and justify it. Flat `FOG\`, or
  `FOG\Model`, `FOG\Manager`, `FOG\Page`, `FOG\Hook`? I lean toward
  mirroring the existing directory structure but I want your argument, not
  my assumption confirmed.
- **Case sensitivity:** the current map lowercases everything, which hides
  any inconsistent casing across those 491 string references. PSR-4 is
  case-sensitive and will not hide it. Find the inconsistencies before they
  become runtime failures.
- **Plugins:** aliases stay through all of 1.6. Tell me what a plugin author
  should write today, and draft the 1.7 deprecation note.

Watch for permission nodes, API routes, or database columns derived from
class names. An alias does not change what is already stored in the
database.

---

## Reminder

Do not plan more than one phase per response. Stop at each gate.
