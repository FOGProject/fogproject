# A saved filter is shared by grant, and a grant is a row per target kind

## Status

accepted, and implemented on `working-1.6` as schema 393 (`savedFilters`),
394 (the three `savedFilter*Assoc` junctions) and 395 (the eight foreign keys,
constraint group 8).

## Context

A grid filter that is worth keeping is usually worth handing to somebody. The
smallest thing that would work is a boolean -- private or everyone -- and the
smallest thing that would work *well* is what people actually asked for: show
this to my manager for approval, then to the four people who need it, or to
the group, or to everyone holding a role.

That is a many-to-many relationship between a filter and three different kinds
of target, which leaves two shapes:

- one `savedFilterShare` table with a `type` column and a `targetID`; or
- one junction per target kind.

ADR 0031 is the reason this is written down rather than defaulted. Of the 117
relationships in the foreign-key map, the 16 that carry action `none` are
audit rows and **polymorphic columns** -- a column whose parent table is
chosen by a sibling column, where no constraint is expressible at all. A
`type`/`targetID` pair is exactly that shape, and it would have added three
more unconstrainable relationships to a release whose entire point was
removing them.

## Decision

**Three junction tables**, one each for users, user groups and roles. Every
one of the eight resulting relationships is a declared foreign key with a
real action:

| child | parent | on delete | why |
|---|---|---|---|
| `savedFilters.sfUserID` | `users` | CASCADE | a private filter belongs to its owner and goes with them |
| `savedFilters.sfCreatorID` | `users` | SET NULL | a *global* filter outlives whoever wrote it |
| `savedFilterUserAssoc.*` | `savedFilters`, `users` | CASCADE | a grant to nobody is not a grant |
| `savedFilterGroupAssoc.*` | `savedFilters`, `userGroups` | CASCADE | as above |
| `savedFilterRoleAssoc.*` | `savedFilters`, `roles` | CASCADE | as above |

`sfUserID IS NULL` is what makes a filter global: it has no owner, so there is
no owner for it to be private to. That is also why `sfCreatorID` exists
separately and is SET NULL rather than CASCADE -- deleting the author of a
site-wide filter must not delete the filter.

**Creating a global filter takes `savedfilter.create`; creating a private one
takes nothing beyond being signed in.** A global filter appears in every
user's picker on that grid, so it is a change to what other people see, and
that is an access-control decision rather than a column. `savedfilter.edit`
and `savedfilter.delete` govern the same operations on an existing global
filter. Sharing to specific users, groups or roles needs no permission of its
own: those grants are visible only to people the sharer names, and the target
lists they pick from are already gated by `user.view`, `usergroup.view` and
`role.view` respectively -- so a user can only share to targets they can
already enumerate.

**Visibility is resolved in one query, and reported with a precedence.**
`SavedFilterManager::listFor()` reaches all five paths -- owned, global,
shared by name, shared to a group I am in, shared to a role I hold -- as OR'd
arms of a single SELECT rather than a UNION of per-path selects, so a filter
a user can reach three ways is one row, always. Which of those reasons is
*reported* follows a fixed order, most deliberate grant first:

    mine > user > group > role > global

so the badge in the picker always describes the most specific reason the user
can see it. A manager who is also in the group they shared to sees "shared
with you", not "everyone".

## Consequences

**A filter is never applied on page load.** It is offered in a picker the user
opens deliberately, applied by a click, shown as a chip naming it, and cleared
by that chip's `x`. This is the same rule the saved grid layout already
follows by stripping searches out of the state it persists: a grid that comes
back short with no visible reason is indistinguishable from a broken grid.
What IS remembered per user is whether the header search row is *shown* --
the affordance, never the term.

**Adding a fourth kind of target is a fourth table**, not a new value in a
type column. That is the cost of this decision and it is the intended one: the
schema step is nine lines, and the alternative was three relationships that
the database could never check.

**A per-user dismissal of a filter shared to them is NOT implemented**, and
"just delete it" is the wrong answer for anything shared to a role -- the user
is not entitled to delete it, and the share is not theirs to revoke. The shape
it would take is a fourth junction (`savedFilterDismissAssoc`, filter + user,
both CASCADE) plus somewhere in the picker to see what you have hidden;
without that second half it is a trap rather than a feature. It is additive
against everything above, so nothing here has to change to add it later.
