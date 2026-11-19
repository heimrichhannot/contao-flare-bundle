# Design: rename `flare_calendar_current` to `flare_time_window`

Status: short design for a small change, 2026-11-19; approaches and names chosen by the user. Follows
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
- **Messages:** `errors.calendar.*` become `errors.time_window.*` in `translations/flare.{en,de}.yaml`, wording
  unchanged.
- **Migration `RenameTimeWindowTypeMigration`** *(name my call)*: `shouldRun()` is true while `tl_flare_filter` has a
  row of type `flare_calendar_current`; `run()` sets those rows to `flare_time_window` in one `UPDATE`. Idempotent,
  no marker, needs only the `type` column, never deletes.
- **Order:** on a v0.1 database the rename runs in the first pass, before the backfill, the retire and the columns
  migrations can run (they wait for columns the schema update adds). So those three recognize both keys: their
  frozen type constant becomes a frozen list `['flare_calendar_current', 'flare_time_window']` (the backfill's
  `flare_calendar_current` case gets the new key beside it). They stay correct whichever runs first.
- **Docs:** element table and section in `reference/filter-elements.md` (key), `reference/predicates.md`
  (`TimeWindowPredicate`), `integrations/index.md`, `removed-in-v0.2.md` and `migrating-from-v0.1.md` (the key is
  renamed by the migration; code that names the type or listens to its named events must use the new key);
  `AGENTS.md`.
- **Testing:** the migration (old rows renamed, other types untouched, quiet afterwards, quiet without the table);
  each earlier migration processes a row of the new key; a run of all four migrations on a v0.1-shaped row in the
  rename-first order ends as before. In the sandbox: `contao:migrate` renames the stored filters (10–19), and the
  check lists render as before.

## Left to the builder

The migration's result message and test names. Each earlier migration keeps its own frozen list rather than a shared
constant *(my call)*: migrations describe history and must not change with the code.
