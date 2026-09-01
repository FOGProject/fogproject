# Group Shared State

A **group** owns no settings of its own — it is a lens over its **member hosts**.
The group edit page therefore *derives* what it shows from the union of its
members, so an admin can see what hosts already have in common before changing
anything.

> **Two kinds of shared state:**
> 1. **Associations** (snapins, printers, modules) — a host either *has* the
>    item or not, shown as a tri‑state checkbox: **All / Some / None** member
>    hosts have it.
> 2. **Configuration values** (Active Directory, auto‑logout, kernel/general
>    fields, default printer) — a host holds a *value*, shown as a muted
>    **`Hosts: …`** hint: the shared value when every member agrees, or
>    `(varies)` when they differ.
>
> Acting on a group still pushes to **all** member hosts — but config fields are
> **no‑clobber**: a blank field leaves each host's value alone, so you only
> change what you intend to.

> **⚠️ This is changing. Snapins, printers and modules have already changed.**
> A group is becoming something that **grants**, rather than something that
> copies rows onto whoever happened to be a member when a button was pressed.
> The first half has landed for **snapins, printers and modules**: see
> [Snapins are granted, not copied](#snapins-are-granted-not-copied) below.
> Everything else on this page still describes the push‑to‑all model and is
> still accurate for the page as it stands today. The reasoning, and what is
> left to move, are in [ADR 0038](adr/0038-a-group-grants-it-does-not-copy.md).
>
> **Modules carry one extra rule.** They are a *switch*, not a thing, so a
> host is allowed to hold one OFF against every group that grants it — lowest
> tier wins. A group grant is presence‑only: a group either grants a module or
> says nothing about it, and can never say "disabled", so two groups can only
> ever union. See ADR 0038 decision 3.

---

## Table of contents

- [Snapins are granted, not copied](#snapins-are-granted-not-copied)
  - [Printers work the same way, but are worked out live](#printers-work-the-same-way-but-are-worked-out-live)
- [Associations (tri‑state)](#associations-tri-state)
  - [What "has it" means](#what-has-it-means)
  - [The badge and Has/Missing drill‑down](#the-badge-and-hasmissing-drill-down)
  - [Toggling](#toggling)
- [Configuration values (shared hints)](#configuration-values-shared-hints)
  - [The no‑clobber convention](#the-no-clobber-convention)
  - [Active Directory](#active-directory)
  - [Auto‑logout](#auto-logout)
  - [General fields](#general-fields)
  - [Default printer](#default-printer)
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

## Associations (tri‑state)

The **Snapins**, **Printers**, and **Modules** tabs each render every item with a
coverage checkbox:

| State | Checkbox | Meaning |
|-------|----------|---------|
| **All** | checked | every member host has the item |
| **Some** | indeterminate | at least one, but not all, hosts have it |
| **None** | unchecked | no host has it |

A **`n / total`** badge sits next to each checkbox (e.g. `5 / 15`).

### What "has it" means

- **Snapins, printers** — a host "has it" when the association row exists. The
  printer **default** flag (`paIsDefault`) is ignored here; it's a separate
  shared *value* (see [Default printer](#default-printer)).
- **Modules** — a host counts only when the module is **enabled**
  (`moduleStatusByHost.msState = 1`). A disabled override pulls the item out of
  *All* into *Some*. This asymmetry is deliberate (see `docs/adr/0001`): showing
  a module as "All hosts have it" while some have it disabled would mislead.

### The badge and Has/Missing drill‑down

Clicking the badge expands an on‑demand row listing, for that one item:

- **Hosts with this (n)** — the member hosts that have it, and
- **Hosts without it (n)** — the member hosts that don't.

The *without* set is exactly what a push‑to‑all will change. The lists are
fetched per item, so large groups stay responsive.

### Toggling

Clicking the checkbox acts on **all** member hosts:

```
None  --click-->  All        (add to every host)
Some  --click-->  All        (add to the hosts that lack it; modules flip
                              a disabled override back to enabled)
All   --click-->  None       (remove from every host)
```

So an indeterminate item resolves to *All* first; the destructive
remove‑from‑all only happens on a second click through the checked state.

> **The module line above is on notice.** "Flip a disabled override back to
> enabled" was harmless while a disabled override could not exist — nothing
> ever wrote one. Under ADR 0038 decision 3 a disabled module row is a
> deliberate host‑level statement that beats every group grant, so a group‑wide
> toggle overriding it is wrong. The tab keeps this behavior until the group
> page rework (unit E) replaces it with a grant.

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

### Default printer

The printer **Default** selector shows a `Hosts default: <printer> (all)`,
`(varies)`, or `(none on all)` hint. Setting a default is an explicit action
that only touches member hosts which have that printer associated.

### Enforce hostname / AD‑join reboots

A tri‑state select — **No change / Enable on all / Disable on all** — with a
`Hosts: enabled (all) / disabled (all) / (varies)` hint. *No change* leaves each
host alone. (Stored in the `hostEnforce` `enum('0','1')` column, written as a
string — passing an int would index the enum rather than match its value.)

---

## Out of scope

- **Force reboot** is a global setting (`FOG_TASK_FORCE_REBOOT`) and a per‑task
  option, not per‑host configuration, so it has no group shared‑state control.
