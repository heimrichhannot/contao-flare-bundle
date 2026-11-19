# Design: the calendar filter reads columns the editor picks

Status: approved by the user, 2026-11-19 (builder check done: C9's one-time prefill and C11 added), written on
branch `v0.2-exclusive-filters` (PR #56) after a design round with the user; #56 is merged (`6bf0a6f1`). Gaps found
when preparing the implementation are settled as C12–C16 (my calls, 2026-11-19); implemented on branch
`v0.2-calendar-columns`.
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

## Today (baseline, `6bf0a6f1`)

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
| C1 | New backend fields on `tl_flare_filter`: `elm_periodStartField` (select, mandatory), `elm_periodStopField` (select, optional, blank = no end column), `elm_overlap` (checkbox; shown only when `elm_periodStopField` is set), `elm_useRecurrence` (checkbox, selector), and in its subpalette `elm_recurringField`, `elm_recurrencesField`, `elm_repeatEndField` (selects, mandatory when shown). Config keys: `start_field`, `stop_field` (`?string`), `overlap` (bool), `use_recurrence` (bool), `recurring_field`, `recurrences_field`, `repeat_end_field` (`?string`). `elm_hasExtendedEvents` leaves the DCA but stays in the table until the manual deletes (migrations never drop); C8's migration reads it (C12). The exact field names are the builder's call. |
| C2 | Own pickers, not `flare_published`'s `elm_fieldStart`/`elm_fieldStop`: those prefill `start`/`stop` (publishing columns), list only `datim` text fields, and a filter that changes its type would carry the other element's column over. *(my call)* |
| C3 | Picker options: every column of the list's table — a DCA field with an `sql` definition (C15), leaving out the SQL date types C11 rejects. Date columns of custom tables often have no `rgxp`, and `tl_calendar_events.repeatEnd` has no widget at all, so a filter by input type would hide valid columns. *(my call)* |
| C4 | Matching, with `S`/`E` the period after intersecting with the value (unchanged), `s` the start column, `e` the effective end (C5): **without overlap** `S <= s <= E`; **with overlap** `s <= E AND e >= S`. With recurrence, OR `recurring AND s <= E AND (recurrences = 0 OR repeatEnd >= S)` — today's branch, Contao's approximation. Overlap equals today's four `has_extended_events` branches for every row with `e >= s`. |
| C5 | Empty values (approved with the spec, see below): a row whose **start** is empty (`NULL`, `''`, `0`) never matches — it has no place in time. An empty **end** means no end: `e` is the start (the entry is a point in time). An end before its start counts as no end. Recurrence: an empty recurring flag is off, empty recurrences is 0 (unlimited), and repeat end is read only when recurrences > 0. |
| C6 | Parameters are bound as integers (`ParameterType::INTEGER`), and every column that is not of an integer type is read through a numeric cast (C13), so a `varchar` column compares numerically, not lexicographically — also against another column. |
| C7 | A picked column the list's table does not have is a `FilterException` from the element (the list fails as for any broken filter; the inspector marks the row broken) — never an SQL error at query time. A required picker left empty in stored data is the same. *(my call; mechanism settled in C15)* |
| C8 | Migration `CalendarColumnsMigration` (name the builder's call), run once per row with its own marker column, after `RetireCalendarLimitMigration`: every `flare_calendar_current` row gets `startTime`, `endTime`, recurrence on with `recurring`, `recurrences`, `repeatEnd`, and `elm_overlap` = `elm_hasExtendedEvents`. That is today's behavior on every table (today's predicate reads exactly these columns). Nothing is deleted. Each row is handled once (marker, guard and order in C12), so a row an editor edits after the migration is never written again. |
| C9 | Prefill (user): each picker is prefilled with its event column (`startTime`, `endTime`, `recurring`, `recurrences`, `repeatEnd`) when the list's table has a column of that name (`DcaHelper::tryGetColumnName()`), else left empty. `elm_useRecurrence` is prefilled on when all three recurrence columns exist *(my call: on `tl_calendar_events` the filter then behaves as today without a click)*. **Once, not on every load** *(my call)*: the prefill happens when a row becomes a `flare_calendar_current` filter (its type is set or changed to it), so an end picker or *Consider recurrence* the editor clears stays cleared. `onLoadField_fieldStart()`'s pattern (`$value ?: default` on load and save) would refill a blank end picker on every event table, and no end column could be chosen there. Mechanism in C14. |
| C11 | Only Unix timestamps (user: Contao stores every date as a timestamp): `int` columns or `varchar` columns of digits. A picked column whose SQL type is `DATE`, `DATETIME`, `TIMESTAMP`, `TIME` or `YEAR` fails the filter as C7 does — the inspector marks it broken — instead of comparing a date with an integer and matching the wrong rows. Rejected: no check with a docs warning (filters silently wrong); converting SQL date types (`UNIX_TIMESTAMP()`, zero dates; more logic for a case Contao does not have). |
| C10 | `elm_hasExtendedEvents`' label and docs move to *Overlap* / *Überlappung*: "Also match entries that end within the period or span it, not only those that start within it." |
| C12 | C8's migration follows `RetireCalendarLimitMigration`: its own migration-managed marker column (not in the DCA; existing rows start pending `'0'`, then the column default becomes `'1'`, so rows created later are never pending; a row's changes and its marker in one statement; rows of other types marked done in the same run). **Guard:** it requires `elm_hasExtendedEvents`, which it reads for `elm_overlap` and which leaves the DCA (C1). `contao:migrate --with-deletes` runs the migrations again after the schema diff has dropped the non-DCA marker; the same diff drops `elm_hasExtendedEvents`, so the migration stays quiet instead of finding every row pending and switching recurrence back on or refilling an end column an editor cleared. As for `elm_isLimited`, the manual deletes run only after all migrations have run (a one-go `--with-deletes` on a v0.1 database would drop the column before the migration reads it). **Order:** it waits for its required columns (the renamed `elm_hasExtendedEvents`, the picker columns the schema update adds) and for `RetireCalendarLimitMigration::shouldRun()` to be false — false for good on a database without `elm_isLimited`, which has nothing to retire. Rejected: an empty start picker as the pending mark instead of a marker — a row switched to the type on a table without `startTime` keeps an empty start (C9 prefills nothing there), and a later run would write the event columns into it. *(my call)* |
| C13 | Reading a column (C5, C6): the element tells the predicate, per picked column, whether its SQL type is an integer type (C15). An integer column is read as it is, so `tl_calendar_events.startTime` keeps its index; any other column is read as `CAST(col AS SIGNED)` (MySQL 5.7/8, MariaDB: `''` and text that is not digits read 0, `NULL` stays `NULL`), so two `varchar` columns compare numerically too. The SQL of C5 is in "Empty values (C5) in SQL" below. A recurring flag counts as on when it reads non-zero (Contao writes `'1'` or `''`). A `varchar` holding text that is not digits reads as its leading digits or 0: outside C11's "`varchar` columns of digits", not checked. *(my call)* |
| C14 | C9's prefill is a save callback on `tl_flare_filter.type`: when the stored type (the record before the save) is not `flare_calendar_current` and the submitted one is, it writes the prefilled pickers and `elm_useRecurrence` to the row through the DBAL connection; any other save writes nothing. The pickers are not in the palette of the submit that changes the type, so nothing in that submit overwrites them. Which record the callback sees as the stored one (`activeRecord` on 4.13, `getCurrentRecord()` on 5.x; `DcaHelper::rowOf()`) the plan verifies on both. A row that gets the type outside a backend save (a copy, a row created by code) is not prefilled: a copy carries its pickers, code sets its own (else C7). Rejected: `config.onsubmit` (runs after the save, the old type is gone); load/save callbacks on the pickers (C9). *(my call)* |
| C15 | Checking a column (C7, C11): a picked column is a column of the list's table when the table's DCA has a field of that name with an `sql` definition — what the schema update turns into a column; any DCA field (`DcaHelper::tryGetColumnName()`) is not enough, and the database is not introspected (a schema query per request). Its type comes from that `sql`: a string by its leading type word (`int(10) unsigned NOT NULL default 0`), an array by its Doctrine `type`. Date types (`date`, `datetime`, `timestamp`, `time`, `year`; Doctrine `date*`, `datetime*`, `datetimetz*`, `time*`) fail (C11); integer types (`tinyint`, `smallint`, `mediumint`, `int`, `integer`, `bigint`; Doctrine `smallint`, `integer`, `bigint`, `boolean`) are read as they are; every other type through the cast (C13). The check runs in `buildEffect()`, which knows the list's table (`$context->list->dc`; the transformer sees only the model), for every column the config uses — start; end if set; the three recurrence columns if recurrence is on — and an empty required picker fails there too, each with a translated message (`flare` domain). The schema allows null for every picker, so the editor gets that message, not an OptionsResolver one; the inspector's broken check runs `buildEffect()` and marks the row. The classification is a helper in the internal `DcaHelper`, which knows no date types today. *(my call)* |
| C16 | Testing without a database: predicate tests assert the SQL and the bound parameters against a mocked connection (as `DcaSelectPredicateTest`; there is no predicate test for the calendar today), which cannot prove the database semantics of C5, C6 and C13. Those are checked by hand once on MySQL 8 and on MariaDB with an SQL script the plan carries (not shipped): a table with `int` and `varchar` columns holding `NULL`, `''`, `0`, an end before its start, timestamps of 9 and 10 digits, and rows shaped like `tl_calendar_events` (one day, several days, recurring limited and unlimited). It runs today's condition and the new one on the event-shaped rows for several periods (open sides included) and compares the matched ids (Goal 6), and the new one on the other rows against the expected ids. *(my call)* |

## Empty values (C5) in SQL

With `n(x)` a column as C13 reads it (`x` for an integer column, `CAST(x AS SIGNED)` otherwise), `:start`/`:stop`
the period (integers), `s`, `e_col`, `r`, `c`, `re` the start, end, recurring, recurrences and repeat end columns:

```
start_ok := n(s) IS NOT NULL AND n(s) <> 0
e        := CASE WHEN n(e_col) IS NULL OR n(e_col) = 0 OR n(e_col) < n(s) THEN n(s) ELSE n(e_col) END

start_ok AND (
      [without overlap] n(s) BETWEEN :start AND :stop
      [with overlap]    n(s) <= :stop AND e >= :start
   OR [with recurrence] (n(r) IS NOT NULL AND n(r) <> 0 AND n(s) <= :stop
                         AND (COALESCE(n(c), 0) = 0 OR n(re) >= :start))
)
```

`''` and `0` read the same once cast, and an integer column cannot hold `''`, so no `<> ''` is needed. Without an end
column, `e` is `n(s)` (overlap is not offered then). The builder may write it differently as long as it runs on
MySQL 5.7/8 and MariaDB and keeps C5 and C13.

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

- Predicate (SQL and parameters, mocked connection, C16): each branch of C4 with and without overlap and recurrence;
  the C5 guards; an integer column read as it is and any other column cast (C13); parameters bound as integers (C6).
- Element: config from the model (C1); the column check (C15) — missing column, column without `sql`, empty required
  picker, SQL date type (string and Doctrine array `sql`), integer and other types handed to the predicate; the DCA
  per selector state.
- Prefill (C9, C14) for a table with and without the event columns; a save that does not change the type writes
  nothing.
- Migration (C8, C12): a v0.1-shaped row, a row of another type, a row already migrated; a second run is quiet; quiet
  without `elm_hasExtendedEvents`; waits for `RetireCalendarLimitMigration`; `contao:migrate` with the other
  migrations in one run.
- Database semantics (C5, C6, C13) and unchanged behavior on `tl_calendar_events` (Goal 6): the manual SQL check of
  C16 on MySQL 8 and MariaDB.

## Left to the builder

Field names (C1), the migration's name and marker column (C8, C12), the exact SQL shape of C5 within C13, the
messages of C15, the legend name and order.

## Docs

`reference/filter-elements.md` (Calendar time window: columns, overlap, recurrence, empty values),
`migrating-from-v0.1.md` (the migration fills the event columns; run the deletes only after all migrations, C12),
`AGENTS.md` (migration list).
