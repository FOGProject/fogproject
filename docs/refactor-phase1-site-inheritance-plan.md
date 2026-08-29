# PHASE 1 FOLLOW-UP — site scope inherited from roles and user groups

Plan only. Deferred out of Phase 1 on 2026-08-16 with the shape already agreed;
this writes it down against the code as it stands today and stops at the gate.

Baseline: `working-1.6` @ `a257b5c98`, `FOG_SCHEMA` 334.

---

## The problem in one sentence

Site membership is assigned **one user at a time**, so a fifty-person helpdesk
covering two sites is a hundred rows an administrator maintains by hand, and a
new starter is invisible until somebody remembers to add them.

Everything needed to fix that already exists: the user is already in a user
group, and already holds a role. Neither of those carries a site.

---

## The distinction this rests on

Both new tables join a site to a thing that is not a user, and it would be easy
to think one of them already exists. It does not, and the difference is the
whole reason for the second table.

| Direction | Means | Table |
|---|---|---|
| **Object membership** | this user group *is in* this site — it is one of the things the site contains, and a site-scoped admin can see and edit it | `siteUserGroupMembers` (exists) |
| **Subject grant** | members of this user group *get* this site — holding it is what puts you in scope | `siteUserGroupGrants` (new) |

They are the same two ids in the same order and they answer opposite questions.
Overloading one row for both would save a table and cost the ability to grant
somebody access to a site without also making the group they came in through
visible and editable inside it — which is precisely the distinction sites exist
to draw. Agreed 2026-08-16; restated here because it is the decision a reviewer
is most likely to want to reopen.

---

## Facts this is built on

All `VERIFIED` — read in the tree at the baseline commit.

**F1 — there is exactly one place that answers "which sites is this user in".**
`SiteScope::userSiteIDs($userID)` (`packages/web/lib/fog/sitescope.class.php:292`)
runs a single query against `siteUserMembers` and caches per user. Every scope
decision — single-object web, single-object REST, mass delete, list visibility —
reaches it. So inheritance is arms added to one query, not a rule added to four
call sites.

**F2 — the union shape is already the house answer to this exact question.**
`Authorization::getPermissions()` (`authorization.class.php:380-389`) computes
effective permissions as `roleUserAssoc` UNION `userGroupMembers JOIN
roleUserGroupAssoc`: a role assigned directly to the user, or a role reaching
them through a group. Sites should inherit along the same edges or the two
systems disagree about what a group membership means.

**F3 — every join needed already exists.** Only the site-side edges are missing.

| Table | Columns | Meaning |
|---|---|---|
| `siteUserMembers` | `sumSiteID`, `sumUserID` | user is in site (today's only arm) |
| `roleUserAssoc` | `ruaRoleID`, `ruaUserID` | role held directly |
| `roleUserGroupAssoc` | `rugRoleID`, `rugGroupID` | role held via user group |
| `userGroupMembers` | `ugmGroupID`, `ugmUserID` | user is in user group |

**F4 — union semantics make this safe by construction.** `userSiteIDs()` returns
a set that is consumed as "in scope for any of these". Adding arms can only add
ids. **No user can lose access to anything as a result of this change**, on any
install, which is what removes the class of risk Phase 1 spent most of its care
on.

**F5 — the two short circuits sit above the query and are untouched.**
Catch-all membership and `sitesInUse()` both answer before `userSiteIDs()` is
consulted (`sitescope.class.php`, docblock at `:26-58`). An install with no site
in use behaves exactly as it does today.

**F6 — nothing in flight collides.** `FOG_SCHEMA` is 334
(`packages/web/lib/fog/system.class.php:97`), the last step in `schema.php` is
`// 334`, and the only open PR against `working-1.6` (#1123) touches no schema
file. Step **335** is free at the time of writing — re-check before merging,
because two steps numbered the same produce no git conflict and the second to
merge silently overwrites.

---

## The design

### Two tables, following the existing four

```sql
CREATE TABLE IF NOT EXISTS `siteRoleGrants` (
  `srgID` int(11) NOT NULL AUTO_INCREMENT,
  `srgName` varchar(60) NOT NULL DEFAULT '',
  `srgSiteID` int(11) NOT NULL,
  `srgRoleID` int(11) NOT NULL,
  PRIMARY KEY (`srgID`),
  UNIQUE KEY `srgSiteRole` (`srgSiteID`,`srgRoleID`),
  KEY `srgRoleID` (`srgRoleID`)
) ENGINE=InnoDB ...

CREATE TABLE IF NOT EXISTS `siteUserGroupGrants` (
  `suggID` int(11) NOT NULL AUTO_INCREMENT,
  `suggName` varchar(60) NOT NULL DEFAULT '',
  `suggSiteID` int(11) NOT NULL,
  `suggGroupID` int(11) NOT NULL,
  PRIMARY KEY (`suggID`),
  UNIQUE KEY `suggSiteGroup` (`suggSiteID`,`suggGroupID`),
  KEY `suggGroupID` (`suggGroupID`)
) ENGINE=InnoDB ...
```

Shape copied from `siteUserMembers` deliberately, including the `*Name` column
nothing reads: `Route::ids()` orders by name, and `assocSetter()` derives its
column from `strtolower(shortName($this)) . 'ID'`. A table that departs from the
pattern stops working with the shared association machinery.

The `UNIQUE` covers every non-id column but `*Name`, which is the case where
`FOGController::save()`'s `INSERT ... ON DUPLICATE KEY UPDATE` has nothing to
destroy — the same reasoning that settled `OIDCGroups`.

### `userSiteIDs()` becomes four arms

```sql
SELECT `sumSiteID` AS `siteID` FROM `siteUserMembers`
 WHERE `sumUserID` = :uid
UNION
SELECT `suggSiteID` FROM `siteUserGroupGrants`
  JOIN `userGroupMembers` ON `ugmGroupID` = `suggGroupID`
 WHERE `ugmUserID` = :uid_group
UNION
SELECT `srgSiteID` FROM `siteRoleGrants`
  JOIN `roleUserAssoc` ON `ruaRoleID` = `srgRoleID`
 WHERE `ruaUserID` = :uid_role
UNION
SELECT `srgSiteID` FROM `siteRoleGrants`
  JOIN `roleUserGroupAssoc` ON `rugRoleID` = `srgRoleID`
  JOIN `userGroupMembers` ON `ugmGroupID` = `rugGroupID`
 WHERE `ugmUserID` = :uid_rolegroup
```

Four arms rather than three because a role reaches a user by two paths and both
already grant permissions (F2). A role that granted a site only when assigned
directly would mean a user group could confer the role's *permissions* but not
its *scope* — a user who can edit hosts and can see none.

`UNION` (not `UNION ALL`) dedupes; the result is a set, and "most open wins" is
true by construction rather than by a precedence rule anyone has to remember.

Bound as four distinct placeholder names because the DB layer binds positionally
per name.

---

## Commit sequence

Every commit leaves `sh tests/run-all.sh` green.

### PR A — schema step 335 and the two models

Both tables, `SiteRoleGrant` / `SiteRoleGrantManager` /
`SiteUserGroupGrant` / `SiteUserGroupGrantManager`, `schema-expected.php`
regenerated, `FOG_SCHEMA` → 335. Nothing reads the tables yet.

Landing the storage before the reader means a half-applied upgrade is a server
with two empty tables, not a server whose scope query references a table that is
not there.

```bash
php bin/schema-manifest.php check packages/web   # manifest matches schema.php
php tests/schema-gate.test.php
sh tests/run-all.sh
```

### PR B — the four-arm query

`SiteScope::userSiteIDs()` only. This is the commit that changes behavior, and
it is one method so a bisect lands on it.

Extend `tests/site-scope.test.php`, which already asserts the **issued SQL and
the query count** rather than just the boolean — the existing fixtures pass
against either query, so a new arm has to be pinned by the SQL it emits or the
test proves nothing. Cases: each arm alone; two arms granting the same site
(dedupe); role via group only; no membership anywhere still denies.

```bash
php tests/site-scope.test.php
```

### PR C — administering it

Two tabs on the Site page beside the existing four (`sitemanagement.page.php`
has four `renderAssocTab()` calls at `:344`, `:370`, `:396`, `:422`), and the
reverse-direction tab on the Role and User Group pages so the grant is visible
from the side an administrator is usually looking at.

`Route::$validClasses`, `API_CLASS_ENTITIES` and the delete-cascade mapping
(`route.class.php:5390-5416` lists the existing member classes) gain the two new
classes. Deleting a site, a role or a user group must clear its grants.

```bash
php tests/route-filter-fields.test.php
php tests/api-server-owned-fields.test.php
sh tests/run-all.sh
```

### PR D — documentation

`docs/adr/0006` gains a section (it currently describes explicit per-user
assignment as the whole model), and `fog-docs`' `site-scoping.md` gains the
grant tabs and one sentence on why a grant is not a membership.

---

## Alternatives considered and rejected

**Overload `siteUserGroupMembers` for both directions.** Saves a table and costs
the ability to grant access to a site without making the granting group an
object inside it. The tables are identical in shape, so the overload would never
fail loudly — it would just quietly widen what site-scoped admins can edit.

**Derive site from the role's permissions instead of a table.** Roles grant
verbs; sites scope objects. ADR 0006 separated these on purpose, and folding a
site into a permission string would put object identity into a namespace that
`purgePermissions($nodePrefix)` walks by prefix.

**Make role grants replace direct membership rather than add to it.** Any
precedence rule has to answer "role says site 2, direct row says site 1" and
every answer is surprising to somebody. Union has no such case.

**Three arms — skip role-via-user-group.** Rejected on F2: permissions already
flow along that edge, and a role whose permissions reach a user but whose scope
does not produces a user who can edit hosts and can see none.

**Do it inside Phase 1.** It was rejected then for diff size and it is still
right: Phase 1's risk was a migration that could silently revoke access on live
servers, and this is purely additive (F4). Mixing them would have put a
can't-lose-access change in the same PR as a might-lose-access one.

---

## Irreversible steps / data migrations

**None.** Two `CREATE TABLE IF NOT EXISTS` and no data movement — nothing is
read, rewritten or deleted. A revert leaves two unused tables.

There is no upgrade-day behavior change: both tables start empty, every arm but
the first returns nothing, and `userSiteIDs()` answers exactly what it answers
today until an administrator creates a grant.

The `FOG_SCHEMA` bump is one-way in the usual sense — a downgraded web tree sees
`mySchema > FOG_SCHEMA` — which is true of every step and is not specific here.

---

## Third-party plugin author impact

Nothing breaks. `OBJECT_SCOPE_CHECK` composition is unchanged and still
deny-wins: a listener may deny what core allows, never grant what core denies.
A plugin that today reads `siteUserMembers` directly to answer "is this user in
this site" would become wrong — but that was never the supported way to ask;
`SiteScope::userSiteIDs()` is, and it is why the query lives in one place.

---

## INFERRED

- **The four-arm query stays cheap.** Every join is on an indexed integer column
  and the result is cached per user per request, so the cost is one query per
  user rather than four. Not benchmarked against a large install.
- **Nobody is relying on site membership being exhaustively enumerable from
  `siteUserMembers`.** A report or export that lists "users in site X" from that
  table alone would undercount once grants exist. I have not swept for one.

## UNKNOWN

1. **Whether step 335 is still free at merge time.** True at the baseline and
   the only open PR touches no schema file, but the collision is silent and has
   happened before (two different step 322).
2. **Whether an administrator wants a grant to be visible on the user's own
   page** — i.e. "you are in site X *because of* role Y". The tables support
   answering it; whether the user edit page should show it is a UI question this
   plan does not settle.
3. **Whether host groups should grant too.** `siteGroupMembers` is object
   membership for host groups; there is no subject sense of a host group, so
   this plan assumes not. Worth confirming that is how you read it.
