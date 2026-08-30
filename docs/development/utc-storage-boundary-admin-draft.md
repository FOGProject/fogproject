# DRAFT — admin-facing page for `FOGProject/fog-docs`

*Not filed in fog-docs yet: the feature it describes has not been built. This
is the text that would ship with it, written now because the explanation is the
hard part and it is easier to judge the design against the sentence you would
have to say to an admin. It is deliberately written in the docs' own voice
rather than as a design note.*

---
title: Timestamps Before the UTC Change
aliases:
    - Unadjusted Timestamps
    - UTC Change
    - Old Timestamps
description: what the "unadjusted" marker on an older date means, why FOG cannot correct those dates, and what still works normally
context_id: unadjusted-timestamps
tags:
    - management
    - web-management
    - troubleshooting
---

# Timestamps Before the UTC Change

FOG now records every date and time in **UTC**, and shows it to you in your own
timezone — see [[display-timezone|Display Timezone]]. Dates recorded *before*
your server made that change are marked, and this page explains what the mark
means.

You will see it as a small marker beside an older date, with a tooltip along
the lines of:

> Recorded before this server moved to UTC. Written in `America/Chicago`.

## What it means

The date is real and it is very probably exactly what you think it is. What FOG
cannot promise is the *zone* it was written in, and therefore it cannot
confidently convert it into yours.

That is the whole of it. An unadjusted date is not corrupt, not missing, and
not an error. It is a date FOG is declining to guess about.

## Why FOG cannot just fix them

The honest answer is that the information needed to fix them was never
recorded, and it was not one single thing that went unrecorded:

- **Older FOG versions stored the server's local time**, with no note of which
  zone that was. Changing the **FOG_TZ_INFO** setting changed how every
  existing date was read, without changing any of them.
- **Not every date came from the same clock.** Some were written by FOG itself
  and some by the database server, and on an install where those two machines
  are in different timezones the two disagree — in the same table, sometimes in
  the same row.
- **Daylight saving makes one hour a year ambiguous** even when the zone is
  known.

So a bulk correction would have to assume one answer for values that had
several. Where it guessed wrong it would move a date that was already right,
and it would do it silently and permanently: once a stored time has been
shifted there is nothing left to say what it was. Leaving the value alone and
telling you it is unadjusted is the only version of this that can be undone,
because nothing was done.

## What still works normally

- **Sorting** an older date column. Within the pre-change era the dates are
  consistent with each other, so their order is right.
- **Filtering** on them, for the same reason.
- **Reading them.** If your server has always been in one timezone — which is
  the ordinary case — an unadjusted date is simply that timezone's local time,
  and the tooltip names it.

## What to be careful about

- **Comparing an unadjusted date with an adjusted one** across the change, if
  the difference matters to within a few hours. Around the moment your server
  switched, FOG marks a window of dates unadjusted deliberately rather than
  claiming a precision it does not have.
- **Exports and API clients.** The REST API sends an `...Adjusted: false`
  field beside any unadjusted value. A client that ignores it and treats every
  timestamp as UTC will be out by your old timezone's offset for the older
  rows.

## When the markers go away

They do not, and that is on purpose. They are attached to the individual values
rather than to a setting, so they disappear only as the rows they belong to
age out — through normal use, or through the retention policies on the log
tables. There is nothing to turn off and nothing to run.

## Where the record lives

The changeover instant, and the timezone your server was using at the time, are
recorded once during the upgrade and are not editable — not in **FOG
Configuration**, not through the API. That is deliberate: it is the single
reference point that decides how every older date is read, so it is stored
where an accidental edit cannot reach it.
