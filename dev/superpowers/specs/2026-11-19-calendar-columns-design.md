# Design: the calendar filter reads columns the editor picks

Status: approved by the user, 2026-11-19 (builder check done: C9's one-time prefill and C11 added), written on
branch `v0.2-exclusive-filters` (PR #56) after a design round with the user; implemented in its own PR after #56.
Builds on the calendar rule of `2026-11-13-exclusive-filters-design.md` (§4: the configured period is a hard bound,
a value narrows within it), which stays as it is.

## Intent

`flare_calendar_current` filters entries by a period. Today it only works on `tl_calendar_events`: its predicate
(`Filter/Predicate/CalendarCurrentPredicate`) hard-codes the columns `startTime`, `endTime`, `recurring`,
`recurrences` and `repeatEnd`. The type selection offers the element in every list, and a list whose table lacks
those columns fails only when its query runs. The user wants the element to work on any table with date columns:

- **The columns are picked by the editor** (user).
- **Recurrence is opt-in** (user): only with *Consider recurrence* on does the filter read recurrence, and only then
  does the backend show the recurrence column pickers.
- **`hasExtendedEvents` becomes *Overlap*** (user), a clearer name for what it does. It belongs to the end column,
  not to recurrence: a multi-day event that does not repeat is its common case.
- **Recurrence stays Contao's model** (user): a recurring flag, a number of recurrences (0 = unlimited) and the end
  of the last recurrence. The columns are picked; their meaning is fixed.
- **Prefilled where the event columns exist** (user): a picker is prefilled with the event column of its role when
  the list's table has a column of that name, and left empty otherwise.

## Goals

1. Column pickers on the element: start (required), end (optional), and — behind *Consider recurrence* — recurring,
   recurrences and repeat end.
2. *Overlap* (the renamed `hasExtendedEvents`), shown only when an end column is picked.
3. One defined treatment of empty values (C5), the same for every table.
4. Columns hold Unix timestamps (as Contao stores every date); a column of an SQL date type is rejected (C11).
5. A column the list's table does not have marks the filter as broken instead of failing the query (C7).
6. Stored filters behave exactly as before after the migration (C8).

## Non-goals

- **Targeting** (`isTargeted`, `targetAlias`): the user is replacing it in a coming PR; this design ignores it. The
  element keeps filtering the list's main table.
- **Renaming the type** `flare_calendar_current` (user: later).
- **Exact recurrence**: whether an occurrence of a series actually falls into the period. The filter keeps Contao's
  approximation (C4); computing occurrences is not SQL.
- Changing the period rule, the value's role or the date bounds a control gets (`DateBoundsContract`).

## Today (baseline, `0fcd2a14`..`b25d7e26`)

Config: `start_at`, `stop_at` (`?\DateTimeImmutable`), `has_extended_events` (bool). `buildEffect()` intersects
period and value into `start`/`stop` timestamps (open side: `0` / `DateTimeHelper::maxTimestamp()`) and adds the
predicate, which matches

```
startTime BETWEEN :start AND :end
OR (recurring = 1 AND startTime <= :end AND (recurrences = 0 OR repeatEnd >= :start))
[has_extended_events:]
OR endTime BETWEEN :start AND :end
OR (startTime <= :start AND endTime >= :end)
```

Parameters are bound without a type (as strings). **Empty values are not treated at all:** a `NULL` start never
matches (SQL `NULL` comparison), a `0` or `''` compares as 0. On `tl_calendar_events` this is harmless — Contao's
`adjustTime` always writes `startTime`, `endTime` (= start, or the end of the day, when there is no end date) and
`repeatEnd` (the maximum for unlimited recurrences) — but on another table it is wrong: with an open period start,
a row with end `0` matches the `endTime BETWEEN 0 AND :end` branch whatever its start is. A `varchar` column compared
with a string parameter compares lexicographically.

`flare_published` has pickers `elm_fieldStart`/`elm_fieldStop`. They are not reused (C2).

## Approaches

1. **Extend `flare_calendar_current`** (chosen): pickers and the recurrence switch on the existing element; stored
   rows are migrated to the event columns. One element, no duplicate; the type name is settled later (user).
2. **A new generic period element beside it**, the calendar element kept as the event-only variant. Two elements
   with the same period rule and the same UI drift apart, and the editor has to know which to pick. Rejected.
3. **Column pickers only, recurrence always on**: breaks every table without recurrence columns. Rejected (user:
   recurrence is opt-in).

## Decisions

| # | Decision |
|---|---|
| C1 | New backend fields on `tl_flare_filter`: `elm_periodStartField` (select, mandatory), `elm_periodStopField` (select, optional, blank = no end column), `elm_overlap` (checkbox; shown only when `elm_periodStopField` is set), `elm_useRecurrence` (checkbox, selector), and in its subpalette `elm_recurringField`, `elm_recurrencesField`, `elm_repeatEndField` (selects, mandatory when shown). Config keys: `start_field`, `stop_field` (`?string`), `overlap` (bool), `use_recurrence` (bool), `recurring_field`, `recurrences_field`, `repeat_end_field` (`?string`). `elm_hasExtendedEvents` stays in the table, unused (migrations never drop). The exact field names are the builder's call. |
| C2 | Own pickers, not `flare_published`'s `elm_fieldStart`/`elm_fieldStop`: those prefill `start`/`stop` (publishing columns), list only `datim` text fields, and a filter that changes its type would carry the other element's column over. *(my call)* |
| C3 | Picker options: every column of the list's table (as `elm_fieldGeneric` lists them). Date columns of custom tables often have no `rgxp`, and `tl_calendar_events.repeatEnd` has no widget at all, so a filter by input type would hide valid columns. *(my call)* |
| C4 | Matching, with `S`/`E` the period after intersecting with the value (unchanged), `s` the start column, `e` the effective end (C5): **without overlap** `S <= s <= E`; **with overlap** `s <= E AND e >= S`. With recurrence, OR `recurring AND s <= E AND (recurrences = 0 OR repeatEnd >= S)` — today's branch, Contao's approximation. Overlap equals today's four `has_extended_events` branches for every row with `e >= s`. |
| C5 | Empty values (approved with the spec, see below): a row whose **start** is empty (`NULL`, `''`, `0`) never matches — it has no place in time. An empty **end** means no end: `e` is the start (the entry is a point in time). An end before its start counts as no end. Recurrence: an empty recurring flag is off, empty recurrences is 0 (unlimited), and repeat end is read only when recurrences > 0. |
| C6 | Parameters are bound as integers (`ParameterType::INTEGER`), so a `varchar` column compares numerically, not lexicographically. |
| C7 | A picked column the list's table does not have is a `FilterException` from the element (the list fails as for any broken filter; the inspector marks the row broken) — never an SQL error at query time. A required picker left empty in stored data is the same. *(my call; mechanism left to the builder: the list's DCA or the schema)* |
| C8 | Migration `CalendarColumnsMigration` (name the builder's call), run once per row with its own marker column, after `RetireCalendarLimitMigration`: every `flare_calendar_current` row gets `startTime`, `endTime`, recurrence on with `recurring`, `recurrences`, `repeatEnd`, and `elm_overlap` = `elm_hasExtendedEvents`. That is today's behavior on every table (today's predicate reads exactly these columns). Nothing is deleted; it writes only empty pickers, so a run on an edited row changes nothing the editor set. |
| C9 | Prefill (user): each picker is prefilled with its event column (`startTime`, `endTime`, `recurring`, `recurrences`, `repeatEnd`) when the list's table has a column of that name (`DcaHelper::tryGetColumnName()`), else left empty. `elm_useRecurrence` is prefilled on when all three recurrence columns exist *(my call: on `tl_calendar_events` the filter then behaves as today without a click)*. **Once, not on every load** *(my call)*: the prefill happens when a row becomes a `flare_calendar_current` filter (its type is set or changed to it), so an end picker or *Consider recurrence* the editor clears stays cleared. `onLoadField_fieldStart()`'s pattern (`$value ?: default` on load and save) would refill a blank end picker on every event table, and no end column could be chosen there. |
| C11 | Only Unix timestamps (user: Contao stores every date as a timestamp): `int` columns or `varchar` columns of digits. A picked column whose SQL type is `DATE`, `DATETIME`, `TIMESTAMP`, `TIME` or `YEAR` fails the filter as C7 does — the inspector marks it broken — instead of comparing a date with an integer and matching the wrong rows. Rejected: no check with a docs warning (filters silently wrong); converting SQL date types (`UNIX_TIMESTAMP()`, zero dates; more logic for a case Contao does not have). |
| C10 | `elm_hasExtendedEvents`' label and docs move to *Overlap* / *Überlappung*: "Also match entries that end within the period or span it, not only those that start within it." |

## Empty values (C5) in SQL

The effective end and the start check, per row:

```
start_ok := s IS NOT NULL AND s <> '' AND s <> 0
e        := CASE WHEN e_col IS NULL OR e_col = '' OR e_col = 0 OR e_col < s THEN s ELSE e_col END
```

On an `int` column `<> ''` is true for every non-zero value in MySQL and MariaDB (`''` casts to 0), so the same
expression serves `int` and `varchar` columns. The builder may write it differently as long as it runs on both and
keeps C5. Without an end column, `e` is `s`.

Why not "an empty start matches everything": the filter bounds a period; an entry without a date is not in any
period, and an editor who wants every entry adds no filter. Today's `NULL` start never matches either.

## Backend

```
{date_start_legend}  elm_configureStart [elm_startAt]
{date_stop_legend}   elm_configureStop [elm_stopAt]
{date_columns_legend} elm_periodStartField, elm_periodStopField [elm_overlap],
                      elm_useRecurrence [elm_recurringField, elm_recurrencesField, elm_repeatEndField]
```

`elm_overlap` appears once `elm_periodStopField` is set (`submitOnChange`, the element's `buildDca()` adds it, as it
already switches the date widget per mode). Translations en and de.

## Testing

- Predicate: each branch of C4 with and without overlap and recurrence; C5 per empty form (`NULL`, `''`, `0`) for
  start and end, an end before its start, `int` and `varchar` columns (C6).
- Element: config from the model (C1), the missing-column failure (C7), the SQL-date-type failure (C11), the DCA per
  selector state.
- Prefill (C9) for a table with and without the event columns.
- Migration (C8): a v0.1-shaped row and a row already migrated; a second run is quiet; `contao:migrate` with the
  other migrations in one run.
- Unchanged behavior on `tl_calendar_events` after migration: today's predicate tests pass against the migrated
  config.

## Left to the builder

Field names (C1), the migration's name and marker (C8), how C7 and C11 check a column (the list's DCA `sql`
definition or the database schema), the exact SQL shape of C5, the
legend name and order.

## Docs

`reference/filter-elements.md` (Calendar time window: columns, overlap, recurrence, empty values),
`migrating-from-v0.1.md` (the migration fills the event columns), `AGENTS.md` (migration list).
