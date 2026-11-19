# Design: rename `flare_calendar_current` to `flare_time_window`

Status: short design for a small change, 2026-11-19; approaches and names chosen by the user; a review (Codex) worked
in the same day. Follows
`2026-11-19-calendar-columns-design.md`, which left the type rename for later; implemented on branch
`v0.2-calendar-columns`, after that design's commits, so both land in one PR *(my call)*.

## Intent

The element reads any table's date fields now, and its display name is already *Zeitfenster* / *Time window*. The
internal names still say "calendar" (`flare_calendar_current`, `CalendarCurrent*`). The user wants them to match.
v0.2 has no userland yet, so renaming code costs no compatibility; stored rows of v0.1 databases must keep working.

## Goals and non-goals

- Goals: type key `flare_time_window`; classes `TimeWindowFilterElement`, `TimeWindowPredicate`,
  `TimeWindowCallbacks` (was `CalendarColumnsCallbacks`); message keys `errors.time_window.*` (were
  `errors.calendar.*`); stored rows rewritten; tests, docs and `AGENTS.md` follow.
- Non-goals: renaming the existing migrations (`RetireCalendarLimitMigration`, `CalendarColumnsMigration`), which
  describe historical calendar rows; the config keys, DCA field names and behavior of the element; the historical
  design documents under `dev/superpowers/`.
- Anti-goal: a stored filter that silently stops being a time-window filter, or a migration that skips its rows
  because the type changed under it.

## Approaches

1. **New key, and a migration that rewrites `tl_flare_filter.type`** (chosen by the user). The earlier migrations
   accept both keys, so their order against the rename does not matter. One name at runtime, clean data.
2. New key, the old one kept as an alias in the registry: no data migration, but old rows keep the old value
   forever, and the type select does not know it. Rejected.
3. Only rename the classes, keep the key: cheapest, but "calendar" stays visible in data, events and docs.
   Rejected.

## Design

- **Key and classes:** `TimeWindowFilterElement::TYPE = 'flare_time_window'`; the element, the predicate, the
  callbacks class and their tests are renamed (files moved with `git mv`). Named events follow automatically
  (`flare.filter_element.flare_time_window.*`); no listener in the bundle uses the old name.
- **Messages and labels:** `errors.calendar.*` become `errors.time_window.*` in `translations/flare.{en,de}.yaml`,
  wording unchanged. The element's label catalogues `translations/flare_filter_element.{en,de}.php` key their entry by
  the class constant, so they reference `TimeWindowFilterElement::TYPE` (still *Time window* / *Zeitfenster*).
- **Service id:** the public alias follows the key, `flare.filter_element.flare_time_window` (v0.1's
  `huh.flare.filter_element.flare_calendar_current` is already gone in v0.2).
- **Migration `RenameTimeWindowTypeMigration`** *(name my call)*: `shouldRun()` is true while `tl_flare_filter` has a
  row of type `flare_calendar_current`; `run()` sets those rows to `flare_time_window` in one `UPDATE`. Idempotent,
  no marker, needs only the `type` column, never deletes, and changes nothing but `type`. The comparison is byte-exact
  in both (`CAST(type AS BINARY) = ?`, valid on MySQL 5.7/8 and MariaDB): `type` has a case-insensitive collation,
  and a separately registered custom type such as `Flare_Calendar_Current` must not be converted (review).
- **Order:** on a v0.1 database the rename runs in the first pass, before the backfill, the retire and the columns
  migrations can run (they wait for columns the schema update adds). So those three recognize both keys: their
  frozen type constant becomes a frozen list `['flare_calendar_current', 'flare_time_window']` (the backfill's
  `flare_calendar_current` case gets the new key beside it). They stay correct whichever runs first.
- **Restored versions and undo records** (review): `tl_flare_filter` keeps versions, and `tl_version`/`tl_undo` hold
  serialized rows with the old key. Restoring one after the upgrade writes `flare_calendar_current` back; the filter
  then has an unknown type and its list fails. The migration does not rewrite serialized history *(my call: the
  rows of an earlier schema would be restored wrong anyway)*. The upgrade guide says so: do not restore versions or
  undo records from before the upgrade; if one was restored, `contao:migrate` renames its key again (the rename is
  marker-free), but settings from v0.1 are not recovered by it.
- **Docs:** element table and section in `reference/filter-elements.md` (key), `reference/predicates.md`
  (`TimeWindowPredicate`, its anchor and inbound links), `integrations/index.md`, `AGENTS.md`. In
  `migrating-from-v0.1.md` and `removed-in-v0.2.md` the blanket statements that every element's `::TYPE` string is
  unchanged get this one exception; code that names the type or the service id uses the new key; a v0.1 listener of
  the element's named events needs the event migration table as well as the new key (e.g. `.palette` →
  `flare.filter_element.flare_time_window.dca`); a short troubleshooting note: a backend filter panel that still has
  the old type selected (kept in the user's session) shows no filters — clear and reselect the **Type** filter. The
  archived v0.1 pages stay as they are.
- **Testing:**
  - The migration: old rows renamed, other types untouched (also a mixed-case custom key in the fake's SQL check),
    quiet afterwards, quiet without the table or without a `type` column.
  - An already-migrated, edited row (custom start picker, cleared end picker, recurrence off) with the old key, the
    older migrations' source and marker columns absent: only `type` changes.
  - Each earlier migration processes a row of the new key; their old-key tests stay.
  - All four migrations on a v0.1-shaped row in the rename-first order end as before.
  - Unit tests and code that referenced the old class names (`ServicesConfigTest`, the comment in
    `DateRangeValueTest`) follow.
  - Sandbox: `contao:migrate` renames the stored filters (10–19) and the check lists render as before; a filter of a
    custom type `Flare_Calendar_Current` stays untouched (MariaDB collation); restoring a version from before the
    rename brings the old key back, and `contao:migrate` renames it again.

## Left to the builder

The migration's result message and test names. Each earlier migration keeps its own frozen list rather than a shared
constant *(my call)*: migrations describe history and must not change with the code.
