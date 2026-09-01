# Group Shared State

A **group** owns its snapins, printers and modules. For everything else it is
still a lens over its **member hosts**: the group edit page *derives* what it
shows from the union of its members, so an admin can see what hosts already
have in common before changing anything.

> **Two kinds of shared state, and only one of them is still a lens:**
> 1. **Grants** (snapins, printers, modules) — the group **owns** these. A
>    ticked box is a row about the group, and every member gets the item,
>    including hosts added later. Nothing is copied onto a host.
> 2. **Configuration values** (Active Directory, auto‑logout, kernel/general
>    fields) — a host holds a *value*, shown as a muted **`Hosts: …`** hint:
>    the shared value when every member agrees, or `(varies)` when they
>    differ. These still **push to all members**, once, and are
>    **no‑clobber**: a blank field leaves each host's value alone.

> **⚠️ The push‑to‑all controls are deprecated.** Everything in
> [Configuration values](#configuration-values-shared-hints) below applies a
> value once, to the hosts that are members at the moment the button is
> pressed — a host added later does not get it, and a host removed keeps it.
> Nothing records that the write happened, so nothing can replay it. Use
> **Edit selected hosts** on the Hosts list instead; these controls carry a
> deprecation notice on the page and will be removed in a later release. The
> reasoning is in [ADR 0038](adr/0038-a-group-grants-it-does-not-copy.md),
> decision 10.
>
> The grant half is finished: snapins, printers and modules are group‑owned
> and are **not** deprecated.

---

## Table of contents

- [Snapins are granted, not copied](#snapins-are-granted-not-copied)
  - [Printers work the same way, but are worked out live](#printers-work-the-same-way-but-are-worked-out-live)
- [Associations (the group's own grants)](#associations-the-groups-own-grants)
  - [What a host ends up with](#what-a-host-ends-up-with)
  - [Ticking and unticking](#ticking-and-unticking)
  - [Order and defaults](#order-and-defaults)
- [Configuration values (shared hints)](#configuration-values-shared-hints)
  - [The no‑clobber convention](#the-no-clobber-convention)
  - [Active Directory](#active-directory)
  - [Auto‑logout](#auto-logout)
  - [General fields](#general-fields)
- [Out of scope](#out-of-scope)

---

## Snapins are granted, not copied

A group can now hold a snapin **grant** of its own. The grant is a row about the
*group* (`groupSnapinAssoc`), not a pile of rows copied onto the hosts that were
members at the time, and that single change fixes both halves of the old
behavior:

- a host **added** to the group afterward is covered by the grant, without
  anyone re‑pushing anything;
- a host **removed** from the group stops being covered, instead of silently
  keeping a snapin nobody can now explain.

### When it is worked out — and the one thing everybody gets wrong

**The list is resolved when a task is created, and written onto the task.
Editing the group afterward does not change a task that is already in flight.
Re‑tasking is the only way to pick a change up.**

That sentence is the whole rule, and it is worth reading twice, because the
natural assumption is that the group is consulted at the moment the snapin runs.
It is not. `snapinTasks` records what was decided when the task was made, which
is what makes a queued task reproducible: you can look at a task from last
Tuesday and see what it was actually going to install, not what the group would
say today.

If you change a group's snapins and want the change to reach machines with
tasks already queued, **cancel and re‑task them**.

### What a host ends up with, in order

1. The snapins on the **host itself**, in the order the host has them.
2. Then the snapins its **groups grant**, groups in the order they are given
   (an explicit order on the group, then by name), and within a group in the
   order the grant has them.

A snapin reached **both** ways appears **once**, in the position the *host*
gave it. A group grant never reorders something an admin deliberately placed on
a host.

Group order is an explicit setting rather than alphabetical for one reason:
**renaming a group must never silently change what installs on a thousand
machines.** An install that never sets it behaves alphabetically, which is the
answer an admin can predict.

### Printers work the same way, but are worked out live

A group can hold a **printer** grant too, and the precedence is identical: the
host's own printers first, then what its groups grant, a printer reached both
ways appearing once at the host's position. A **host's own default printer wins
outright**; otherwise the default comes from the first group in order that names
one.

The difference is *when*. There is no task to attach a printer list to — the FOG
client reconciles its printers on a schedule, and a removal has to reach the
machine — so **the printer list is worked out fresh on every client request**.
A change to a group's printers reaches its members on their next check-in, with
no re-tasking.

> **On printer level `ar` ("FOG Handles all printers"), the list is
> authoritative in both directions:** the client removes every installed
> printer that is not on it, including ones FOG did not add. That has always
> been true of this mode; it is worth re-reading now that a group can add to
> the list.

---

## Associations (the group's own grants)

The **Snapins**, **Printers** and **Modules** tabs are plain on/off lists of
what **this group grants**. A ticked box is a row about the group; it says
nothing about any particular host.

| State | Checkbox | Meaning |
|-------|----------|---------|
| granted | checked | this group grants the item to its members |
| not granted | unchecked | this group says nothing about the item |

There is no third state, and no coverage badge. Until ADR 0038's unit E these
tabs showed a derived **All / Some / None** tri‑state with an `n / total`
badge and a Has/Missing host drill‑down — a whole vocabulary whose only job
was to reconstruct, after the fact, what the group would have looked like if
it had ever owned anything. It owns something now, so the derivation is gone
along with the machinery behind it (`_groupAssocList()`,
`getAssocHostsList()`, and the tri‑state renderer in `fog.group.edit.js`).

### What a host ends up with

A host's effective list is its **own** rows unioned with the grants of every
group it belongs to, worked out at read time by `FOG\Assign\Resolver`. So:

- adding a host to the group is enough for it to gain the group's snapins,
  printers and modules; removing it is enough to lose them again;
- a host's own associations are untouched by anything on these tabs — a
  snapin given directly to one host stays with that host if the group revokes
  its grant;
- **modules carry one extra rule.** A module is a *switch*, so a host may hold
  one OFF (a `moduleStatusByHost` row at `msState = 0`) against every group
  that grants it, and the host wins. A grant is presence‑only — a group can
  turn a module on and can never turn one off — so two groups can only ever
  union. Set it on the host's own Modules tab, which is a three‑way
  *On / Off / Not set* select rather than a checkbox.

### Ticking and unticking

Ticking writes one grant row; unticking deletes it. Neither touches a member
host, so neither is destructive to per‑host state, and both take effect for
every current and future member at once.

**Granting a snapin does not run it.** The grant decides what a snapin
*deployment* will include; deploy it from the group's Tasks tab when you want
it to run. (The retired `persistentgroups` plugin ran snapins on a host as it
joined a group; nothing does that now, deliberately.)

### Order and defaults

The two per‑item decisions an admin can make are columns on the grant row, so
they belong to the group rather than being recomputed from its members:

- **Snapin run order** — the *Snapin Run Order* card orders the group's own
  grants (`gsaSequence`). A host runs its own snapins first, then the granted
  ones in this order.
- **Default printer** — one of the granted printers can be marked the group's
  default (`gpaIsDefault`). A host that has chosen its own default keeps it.

---

## Configuration values (shared hints)

Per‑host configuration fields show a muted hint beneath the control:

| Hint | Meaning |
|------|---------|
| `Hosts: bzImage (all)` | every member host holds that value |
| `Hosts: (varies)` | member hosts differ |
| `Hosts: (empty on all)` | none of the hosts have a value |

The hint is **information only** — it never prefills the input.

### The no‑clobber convention

Saving a group config tab pushes to all member hosts, but:

- **Blank field** → leave each host's value **unchanged** (no clobber).
- Literal **`NULL`** (case‑insensitive) → **clear** the field on every host.
- **Any other value** → push that value to every host.

This is what lets you, say, set one kernel argument across a group without
wiping every other per‑host field.

### Active Directory

- **Domain joining** is a tri‑state select: **No change** (leave each host's
  join state alone), **Enable on all**, or **Disable on all**.
- Domain / OU / username follow the no‑clobber convention above. The password's
  32‑asterisk placeholder means "unchanged".
- Selecting **Enable on all** populates the blank fields from the FOG AD
  defaults (same as the host page) — only when you choose it, never just from
  existing state.
- A **Current member‑host AD state** summary shows join/domain/OU/username
  uniformity above the form.

### Auto‑logout

Blank by default (the global minimum is shown only as a placeholder). A blank
save leaves each host's auto‑logout alone; a number pushes to all (under five
minutes disables it). The hint reads `Hosts: N min (all)`, `(varies)`, or
`(default on all)`.

### General fields

Kernel, kernel arguments, init, primary disk, BIOS/EFI exit, and product key
each carry a `Hosts: …` hint. The kernel/args/init/disk inputs prefill from the
**group's own template** (the group stores these); the hint reports the
*members'* state independently. Push still honors the no‑clobber convention.

### Enforce hostname / AD‑join reboots

A tri‑state select — **No change / Enable on all / Disable on all** — with a
`Hosts: enabled (all) / disabled (all) / (varies)` hint. *No change* leaves each
host alone. (Stored in the `hostEnforce` `enum('0','1')` column, written as a
string — passing an int would index the enum rather than match its value.)

---

## Out of scope

- **Force reboot** is a global setting (`FOG_TASK_FORCE_REBOOT`) and a per‑task
  option, not per‑host configuration, so it has no group shared‑state control.
