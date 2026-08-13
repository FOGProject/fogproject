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

Toggling an item to **All** writes the missing rows on every host (for modules,
this also flips `state = 0 → 1`); toggling to **None** deletes the rows.

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
