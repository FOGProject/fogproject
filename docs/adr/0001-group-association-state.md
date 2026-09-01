# Group association state is a derived tri-state; modules count only *enabled* hosts

## Status

accepted

## Context

A group owns no associations of its own — it is a lens over its member hosts.
The group edit page therefore has to *derive* how to show each item (snapin,
printer, module) from the union of its member hosts' associations. We show one
of three states per item — **All**, **Some**, or **None** member hosts have it —
rendered as a checked / indeterminate / unchecked checkbox, with an on-demand
drill-down into the Has/Missing host sets.

## Decision

"Has it" is defined per item type, and **modules are deliberately not uniform**
with snapins and printers:

- **Snapins, printers:** a host "has it" when an association row exists
  (printers ignore `paIsDefault`).
- **Modules:** a host "has it" only when the `moduleStatusByHost` row exists
  **and** `msState = 1` (enabled). A disabled override (`msState = 0`) counts as
  *not having it*, so such a host pulls the item out of **All** into **Some**.

  **Correction, 2026-09-01: a disabled override could not exist — and now
  it is the point.** When this ADR was written, nothing in the tree ever
  wrote `msState = 0`: every insert path wrote a literal `1`
  (`Items/Group.php`, `Base/FOGController.php`), the schema deleted any that
  survived an upgrade (schema steps 34 and 231 — both historical, since a
  numbered step replays once), and the client ignored the column entirely.
  The rule above was therefore *equivalent* to the snapin and printer rule:
  the row is there or it is not. Nothing about this ADR's behavior changed,
  because every row already satisfied the stricter condition.

  What that finding did change was the argument built on top of it. ADR 0038
  decision 3 had kept modules out of the declarative half **because** of this
  asymmetry, and the asymmetry was not real. That decision was reversed:
  modules are now the third group grant, and `msState = 0` has a meaning for
  the first time — a host saying OFF, which beats every group grant. A group
  grant carries no state at all (`groupModuleAssoc` has no state column), so
  two groups can only ever union.

  So the rule stated above is now correct as written, for a reason it did not
  have when it was written. What is no longer true is the sentence below it:
  toggling an item to **All** cannot mean "flip `state = 0 → 1`" once 0 is a
  deliberate host-level statement, and the group page's own module tab is
  scheduled for rework in ADR 0038's unit E. Read this section with ADR 0038
  decision 3 beside it.

Toggling an item to **All** writes the missing rows on every host (for modules,
this also flips `state = 0 → 1`); toggling to **None** deletes the rows.

**Superseded for modules by ADR 0038 decision 3.** Once `state = 0` is a host
saying OFF, flipping it to 1 on a group-wide toggle overrides a deliberate
per-host statement, and deleting the row means "unstated" rather than "off".
The group module tab keeps this behavior until unit E replaces it with a
grant; the correction note above has the detail.

Per-host configuration *shared values* (Active Directory, auto-logout,
force-reboot, the printer **default** flag) are explicitly **out of scope** for
this work and deferred to a later phase.

## Why

Showing a module as "All hosts have it" while a third of them have it disabled
would be a lie to the operator — the checkbox must mean what an admin reads it to
mean. Modules are the only one of the three that carries an enabled/disabled
state, so the asymmetry is intrinsic to the data, not an oversight. We rejected
the simpler "uniform row-exists for all three" because it would misreport modules.

## Consequences

A future reader will see modules computed differently from snapins/printers and
might assume a bug — it is intentional. If snapins or printers ever gain a
per-host enabled/disabled state, they should converge on the module rule.
