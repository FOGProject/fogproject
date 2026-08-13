# External identity is provenance; authority comes from roles, mapped per directory group

## Status

accepted

## Context

Native RBAC (ADR 0005) made two compatibility choices that were safe on
their own: a user with no role rows is an implicit administrator, and an
API class absent from the registry is allowed. The LDAP plugin predates
RBAC and had always created users with no roles, because before RBAC the
privilege tier lived in `users.uType` (990/991 for LDAP admin/user).
After RBAC nothing read `uType` for authorization.

Those two facts met, and the result was that **every LDAP-authenticated
user was a full FOG administrator** — UI and API. With "Use Group Match"
off, which was the shipped default, that included every account that
could bind to the directory at all. The plugin's three defensive
mechanisms (hide LDAP users from lists, filter them out of role
membership, destroy the row at logout) were written for the two-tier
`uType` model and, under RBAC, combined into a guarantee that an LDAP
user could never *hold* a role — so the escalation could not be fixed by
hand either. Separately, the user's real directory password was
bcrypt-stored in `users.uPass`, and the LDAP bind password was readable
in cleartext over `/api/`.

Root cause in one sentence: a fail-open authorization default met an
identity provider that produces users with no roles, and nothing
connected the two.

## Decision

**Provenance is a core fact; authority is not.** `users.uAuthSource`
records which identity provider created an account. `Authorization`
denies by default for any user whose `uAuthSource` is non-empty and who
holds no roles, instead of treating them as an implicit administrator.
Local users keep the pre-existing stance. `uType` is retired as an
authority: 990/991 remain in the column but are inert, and
`isSchemaAdmin()` now keys on RBAC with a self-retiring pre-316 fallback.

**An externally-sourced account cannot be authenticated by a local
password compare.** `USER_LOGGING_IN` carries `&$authenticated`, and the
plugin no longer writes the directory password at all. This replaces a
guard (`isLdapType()`) that was provably dead code.

**A directory group is a first-class entity, and what it grants is an
ordinary association.** `LDAPGroups` rows are scoped to one LDAP server;
`ldapGroupRoleAssoc` and `ldapGroupUserGroupAssoc` map them to roles and
user groups through the same association tabs every other entity uses,
editable from either end. When group matching is off, a bindable account
receives one configured fallback role
(`FOG_PLUGIN_LDAP_NOMATCH_ROLE`) — never administrator.

**The sync is authoritative only over what it granted.** `ldapUserGrant`
records each grant per user. On sign-in the managed set is *what was
previously recorded* ∪ *what the directory grants now*; anything an
admin attached by hand is untouched.

## Why

Provenance had to be its own column rather than reusing `uType`. 990/991
is a global claim on a shared generic field: it is admin-editable at
runtime, writable over the API and CSV import, and the next auth plugin
would have to invent its own magic numbers and hope they did not
collide. `uAuthSource` is namespaced per provider and is a *standing*
property of the resolver rather than a check that runs once at login —
so the fix does not depend on the plugin never having a bug.

Recording grants, rather than inferring them from the mapping tables,
closes the hole an admin is most likely to hit. "Managed" used to mean
"appears as a mapping target", so removing the **last** mapping to a
role took that role out of the managed set entirely and everyone holding
it kept it forever. Removing a mapping reads as "revoke this", and it
silently did not.

Two deviations from the original recommendation, both from review:

- **Per-directory-group mapping, not install-wide settings.** The
  original plan was two globalSettings (admin role, user role) on the
  grounds that the old code collapsed every server into one access tier,
  so per-server mapping would be a new feature rather than a fix. That
  reasoning held for the *tier ladder* but not for the shape admins
  actually want, which is "this AD group gets this role". Mapping per
  group also removes the tier concept entirely instead of re-encoding
  it, and scoping each group to a server keeps two same-named groups on
  different directories distinct.
- **Group-matching-off yields the fallback role, not administrator.**
  "Authentication implies administration" is not a defensible default,
  and this was the single highest-impact line in the report.

## Consequences

- Upgrades change local users' access not at all. LDAP users lose
  implicit administrator on first sign-in after the upgrade and hold
  exactly what their mapped groups grant, so **mappings must be
  configured before or immediately after upgrading** or LDAP admins will
  find themselves without access. The plugin's schema step seeds
  `ldapUserGrant` from existing assignments so the first post-upgrade
  sign-in revokes correctly rather than silently keeping stale access.
- Plugin schema lists are strictly **append-only**: `Plugin::installdb()`
  passes the stored `pSchema` as `$applied`, so rewriting an existing
  step means it is skipped on installs that already ran it. Repairing an
  in-place rewrite costs additional idempotent steps.
- Directory group names may contain `+`, which `Route::_buildSql()`
  rewrites into a SQL wildcard in a scalar filter value. Every LDAP
  mapping lookup therefore uses raw bound SQL, not `Route::getIds()`.
- LDAP users are now visible and manageable in User Management. The hook
  that hid them was removed; it also carried a latent double-`WHERE` bug
  when stacked with the site plugin.
- Still open, deliberately deferred: dropping the `users.uType` column
  (an API/CSV contract break), removing the now-inert `USER_TYPES_FILTER`
  firings, encrypting `lsBindPwd` at rest, and nested/transitive
  directory groups.
