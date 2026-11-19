# Calendar Columns Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or
> superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `flare_calendar_current` reads the date columns the editor picks (start, optional end, opt-in Contao
recurrence), treats empty values and an end before the start one way on every table, and fails as a broken filter —
never as an SQL error — on a bad column or a configured stop before the start.

**Architecture:** `DcaHelper` resolves a picked field from the list table's DCA `sql` into its physical column and kind
(integer, other → read through `CAST(… AS SIGNED)`, date → rejected). The element checks its columns and its period
in `buildEffect()` and hands the predicate column names, the cast list, overlap and recurrence; the predicate writes
one condition. A prefill decided on the `type` save is written with the type (5.x: `onbeforesubmit`; 4.13: directly).
A run-once migration adds and fills the picker columns before any schema diff.

**Tech Stack:** PHP 8.2+, Symfony, Contao 4.13/5.x, Doctrine DBAL, PHPUnit 9, Mago; the Contao 5.3 sandbox (DDEV,
MariaDB 10.11) for the database and backend test.

**Spec:** `dev/superpowers/specs/2026-11-19-calendar-columns-design.md` (C1–C17, Goal 6's exceptions). Read it first.

## Global Constraints

- `declare(strict_types=1);` in every PHP file under `src/` and `tests/`; PER style, 4-space indent, 120 chars.
- SQL runs on MySQL 5.7/8 and MariaDB; migrations write plain DDL valid on all three and never delete.
- Unix timestamps only (C11). `FilterConditionsBuilder::setParameter()` already binds an `int` as
  `ParameterType::INTEGER`; the predicate passes ints and states no type.
- Element type `flare_calendar_current` keeps its name; targeting (`isTargeted`, `targetAlias`) is ignored.
- Messages an editor may see go through `AbstractFilterElement::trans()` (`flare` domain), en and de.
- Tests: `make test <one path>` (PHPUnit 9 takes only the first path: run files one by one, or `make test`);
  `make phpstan`; before every commit `mago lint --minimum-fail-level note` (local binary, as CI).
- Commits: conventional prefix, no `Co-Authored-By` trailer, never push. Docs commit in the `docs/` worktree (branch
  `docs/main`), separately.

## Review Focus

1. A filter with *Overlap* stored on whose end picker was cleared later: the start must lie in the period, no SQL on
   an empty column — Task 3 `testOverlapNeedsAnEndColumn`, `testOverlapIsOnlyPassedWithAnEndColumn`.
2. *Consider recurrence* on while its pickers are empty (a type change on a non-event table): broken with a
   translated message, never an SQL error — Task 3 `testRecurrenceWithoutItsColumnsIsBroken`.
3. A broken column or an inverted period together with a value that empties the period: the checks run before
   `abort()`, so the filter is reported broken — Task 3 `testChecksRunBeforeAnEmptyIntersectionAborts`.
4. A type change submitted together with an invalid other field on 5.x: neither the type nor the prefill is written —
   Task 5 `testARejectedSubmitWritesNoPrefill`; sandbox Task 8.
5. A first `contao:migrate --with-deletes` on a v0.2 database: every calendar filter still gets its columns — Task 6
   `testAFirstRunWithDeletesMigratesEveryRow`; sandbox Task 8.

---

### Task 1: Sandbox fixtures and baseline (controller, before any code)

Run by the controller session, not a subagent: it needs DDEV and records the state before the change. The branch's
code equals `v0.2` (`6bf0a6f1`) at this point, so the sandbox runs today's element.

Sandbox: `~/Kunden/ericges/contao-5.3-sandbox` (see its `CLAUDE.md`; `ddev console …`, `ddev mysql …`). It runs this
checkout through its symlinked path repository. Sandbox changes stay uncommitted unless the user says otherwise.
Work files go to `~/Kunden/ericges/contao-5.3-sandbox/var/flare-calendar-check/` (ignored).

- [ ] **Step 1: Snapshot** — `ddev snapshot --name flare-calendar-columns-before`. Expected: snapshot created.
- [ ] **Step 2: Event fixtures** — insert into `tl_calendar_events` (calendar `pid` 3, published, unique alias,
  titles prefixed `CC `) the rows below as `adjustTime()` writes them (`startDate`/`endDate` the days, `startTime`/
  `endTime` as listed, `addTime` 1; recurring rows `recurring` 1, `repeatEach`
  `a:2:{s:4:"unit";s:5:"weeks";s:5:"value";s:1:"1";}`). Timestamps in PHP's time zone:
  `ddev exec php -r 'echo strtotime("2030-03-10 10:00");'`.

  | Title | Start | End | Recurrence |
  |---|---|---|---|
  | `CC one day` | 2030-03-10 10:00 | 2030-03-10 12:00 | — |
  | `CC several days` | 2030-03-08 00:00 | 2030-03-12 23:59 | — |
  | `CC ends in March` | 2030-02-25 00:00 | 2030-03-05 23:59 | — |
  | `CC reversed` | 2030-03-10 18:00 | 2030-03-10 10:00 | — (one day, end time before start time) |
  | `CC weekly limited` | 2030-01-05 10:00 | 2030-01-05 12:00 | 4, `repeatEnd` 2030-02-02 12:00 (end + 4 weeks) |
  | `CC weekly unlimited` | 2030-01-10 10:00 | 2030-01-10 12:00 | 0, `repeatEnd` 4294967295 |
  | `CC later` | 2030-04-20 10:00 | 2030-04-20 12:00 | — |

- [ ] **Step 3: Template** — `templates/content_element/flare_listview/calendar_check.html.twig` in the sandbox,
  extending `@Contao/content_element/flare_listview.html.twig`, empty `filter` block, `list` block printing one
  `<li data-entry="{{ entry.row.id }}">{{ entry.row.title }}</li>` per `flare_list.entries`; the `failure` block
  stays. `ddev console cache:clear`.
- [ ] **Step 4: Lists and pages** — three FLARE lists (driver `flare_events`, `tl_calendar_events`), each with
  exactly one published `flare_calendar_current` filter (no control, no value, not exclusive) and a published page
  with only a list view of it (template variant `calendar_check`, no pagination limit), reachable by `curl` without
  login: `CC fixed` (period `2030-03-01` – `2030-03-31 23:59`, *Extend events* off), `CC fixed overlap` (same,
  *Extend events* on), `CC driver` (changed by the driver). Note the filter ids.
- [ ] **Step 5: Driver** — `var/flare-calendar-check/run.sh <phase> <overlap-column>`: for each case, `UPDATE` the
  `CC driver` filter's `elm_configureStart`/`elm_startAt`/`elm_configureStop`/`elm_stopAt` (`str` + the expression,
  or `''` for an open side) and the overlap column, `curl` its page past any HTTP cache, assert HTTP 200 and the
  list markup, and write the sorted `data-entry` titles to `<phase>/<case>.txt`.

  | Case | Start | Stop | Overlap |
  |---|---|---|---|
  | `march` / `march-ov` | `2030-03-01` | `2030-03-31 23:59` | off / on |
  | `narrow` / `narrow-ov` | `2030-03-09` | `2030-03-11` | off / on |
  | `turn` / `turn-ov` | `2030-02-26` | `2030-03-06` | off / on |
  | `reversed-ov` | `2030-03-10 09:00` | `2030-03-10 11:00` | on |
  | `series-inside` | `2030-01-20` | `2030-01-21` | off |
  | `series-end` | `2030-02-02 11:00` | `2030-02-02 13:00` | off |
  | `series-beyond` | `2030-02-02 12:01` | `2030-02-03` | off |
  | `until-jan` | open | `2030-01-31` | off |
  | `from-april` | `2030-04-01` | open | off |
  | `open` | open | open | off |

- [ ] **Step 6: Baseline** — render `CC fixed` and `CC fixed overlap` into `before/fixed.txt` and
  `before/fixed-overlap.txt`, then `run.sh before elm_hasExtendedEvents`. Expected: `march` lists `CC one day`,
  `CC several days`, `CC reversed`, `CC weekly unlimited` and the sandbox's four (unlimited recurring) events;
  `reversed-ov` lists `CC reversed` (by its end); `series-end` lists `CC weekly limited`, `series-beyond` does not.
- [ ] **Step 7: Second snapshot** — `ddev snapshot --name flare-calendar-columns-baseline` (fixtures in, nothing
  migrated); Task 8 restores it for the first-run-with-deletes check.

---

### Task 2: Physical column and kind in `DcaHelper`

**Files:**
- Create: `src/Util/SqlColumnKind.php`, `src/Util/SqlColumn.php`
- Modify: `src/Util/DcaHelper.php`
- Test: `tests/Util/DcaHelperTest.php` (create)

**Interfaces:**
- Produces (`@internal`): `enum SqlColumnKind { case Integer; case Date; case Other; }`;
  `final readonly class SqlColumn { __construct(public string $name, public SqlColumnKind $kind) }`;
  `DcaHelper::classifySql(array|string|null $sql): ?SqlColumnKind` (null for an empty `sql`);
  `DcaHelper::getColumn(string $table, string $field): ?SqlColumn` (loads the DCA; null when the field is missing or
  has no `sql`; `name` = an array `sql`'s `name`, else `$field`).

- [ ] **Step 1: Write the failing tests** — `testClassifiesSqlDefinitions` (data provider):
  `"bigint(20) NULL"`, `"int(10) unsigned NOT NULL default 0"`, `"smallint(5) unsigned NOT NULL default 0"`,
  `" INT(10) NULL"`, `"integer"`, `['type' => 'boolean', 'default' => false]`, `['type' => 'integer']`,
  `['type' => 'bigint']` → `Integer`; `"date NULL"`, `"datetime NOT NULL"`, `"timestamp NULL"`, `"time NULL"`,
  `"year(4) NULL"`, `['type' => 'date']`, `['type' => 'date_immutable']`, `['type' => 'datetime_immutable']`,
  `['type' => 'datetimetz']`, `['type' => 'time_immutable']`,
  `['type' => 'string', 'columnDefinition' => 'DATE NULL']` → `Date`; `"varchar(10) NULL"`,
  `"char(1) NOT NULL default ''"`, `"decimal(10,2)"`, `['type' => 'string', 'length' => 10]`,
  `['type' => 'dateinterval']`, `['type' => 'date', 'columnDefinition' => 'varchar(10) NULL']` → `Other`;
  `null`, `''`, `[]` → `null`.
  `testGetColumnReadsTheTablesDca` (`Tests\Filter\Element\StubsContaoDcaTrait`, table `tl_test`): `start` with
  `"bigint(20) NULL"` → `SqlColumn('start', Integer)`; `renamed` with `['type' => 'integer', 'name' => 'physical']`
  → `SqlColumn('physical', Integer)`; a field without `sql` → `null`; an unknown field → `null`.
- [ ] **Step 2: Run** — `make test tests/Util/DcaHelperTest.php`. Expected: FAIL (classes/methods missing).
- [ ] **Step 3: Implement** — a declaration word is the leading `/^\s*([a-z]+)/i`, lowercased; integer words
  `tinyint smallint mediumint int integer bigint`, date words `date datetime timestamp time year`. A string `sql` and
  an array `sql`'s `columnDefinition` are read by that word; otherwise the array's `type`: `smallint integer bigint
  boolean` → Integer, `date date_immutable datetime datetime_immutable datetimetz datetimetz_immutable time
  time_immutable` → Date, any other type → Other.
- [ ] **Step 4: Run** — same command. Expected: PASS.
- [ ] **Step 5: Commit** — `feat(util): resolve a DCA column's physical name and SQL kind`.

---

### Task 3: Runtime — predicate and element read the picked columns

Predicate and element change together: the element feeds the predicate's options, and either half alone leaves the
suite red.

**Files:**
- Modify: `src/Filter/Predicate/CalendarCurrentPredicate.php`,
  `src/Filter/Element/CalendarCurrentFilterElement.php` (all but `buildDca()`), `translations/flare.en.yaml`,
  `translations/flare.de.yaml`
- Test: `tests/Filter/Predicate/CalendarCurrentPredicateTest.php` (create; builder as in `DcaSelectPredicateTest`:
  mocked connection, `quoteIdentifier` → `` `x` ``, alias `main`),
  `tests/Filter/Element/CalendarCurrentFilterElementTest.php`

**Interfaces:**
- Consumes: Task 2 `DcaHelper::getColumn()`, `SqlColumn`, `SqlColumnKind`.
- Produces config keys (C1): `start_field`, `stop_field`, `recurring_field`, `recurrences_field`,
  `repeat_end_field` (`?string`, null), `overlap`, `use_recurrence` (`bool`, false); `start_at`/`stop_at` unchanged;
  `has_extended_events` removed. Model columns: `elm_periodStartField`, `elm_periodStopField`, `elm_overlap`,
  `elm_useRecurrence`, `elm_recurringField`, `elm_recurrencesField`, `elm_repeatEndField` (Task 4 adds them).
- Produces predicate options: `start`, `stop` (`int|DateTimeInterface`, required, normalized to int — unchanged);
  `start_field` (`string`, required); `stop_field` (`?string`, null); `overlap` (`bool`, false);
  `recurring_field`, `recurrences_field`, `repeat_end_field` (`?string`, null; recurrence applies when
  `recurring_field` is set, and then the other two are required — a normalizer throws `InvalidOptionsException`
  otherwise); `cast_fields` (`list<string>`, []: the columns read as `CAST(col AS SIGNED)`). All names are physical
  column names. `has_extended_events` is removed. Parameters `start`, `stop`.

The condition is one `$builder->where()` string, with `n(x)` = `$builder->column(x)`, wrapped as
`CAST(… AS SIGNED)` when `x` is in `cast_fields`:

```
n(s) IS NOT NULL AND n(s) <> 0[ AND <end_ok>] AND ((<main branch>)[ OR (<recurrence branch>)])
<end_ok>, with stop_field:                 (n(e) IS NULL OR n(e) = 0 OR n(e) >= n(s))
main branch, no overlap or no stop_field:  n(s) BETWEEN :start AND :stop
main branch, overlap and stop_field:       n(s) <= :stop AND <e> >= :start
<e>:                CASE WHEN n(e) IS NULL OR n(e) = 0 THEN n(s) ELSE n(e) END
recurrence branch:  n(r) IS NOT NULL AND n(r) <> 0 AND n(s) <= :stop
                    AND (COALESCE(n(c), 0) = 0 OR (n(c) > 0 AND n(re) >= :start))
```

`buildEffect()` order: (1) the column checks — start; end if set; the three recurrence columns if `use_recurrence`
— against `$context->list->dc` via `getColumn()`; (2) C17: `start_at` and `stop_at` both set and `start_at > stop_at`
→ `FilterException`; (3) the intersection with the value as today (`abort()` when empty); (4) `addPredicate()` with
the physical names, `stop_field`/`overlap` only when an end column is set, the recurrence fields only with
`use_recurrence`, `cast_fields` = the checked columns of kind `Other`. Messages (new keys; `%role%` =
`trans('errors.calendar.role.<role>')`, dates as `Y-m-d H:i`):

| Key | en | de |
|---|---|---|
| `errors.calendar.no_column` | `No column is selected for the %role%.` | `Für %role% ist keine Spalte ausgewählt.` |
| `errors.calendar.unknown_column` | `The column "%column%" selected for the %role% does not exist in %table%.` | `Die für %role% ausgewählte Spalte „%column%“ gibt es in %table% nicht.` |
| `errors.calendar.date_column` | `The column "%column%" selected for the %role% is an SQL date column; the filter reads Unix timestamps only.` | `Die für %role% ausgewählte Spalte „%column%“ ist eine SQL-Datumsspalte; der Filter liest nur Unix-Zeitstempel.` |
| `errors.calendar.inverted_period` | `The configured end of the period (%stop%) lies before its start (%start%).` | `Das konfigurierte Ende des Zeitraums (%stop%) liegt vor seinem Beginn (%start%).` |
| `errors.calendar.role.start` / `.stop` / `.recurring` / `.recurrences` / `.repeat_end` | `start` / `end` / `recurring flag` / `number of recurrences` / `repeat end` | `den Beginn` / `das Ende` / `die Wiederholung` / `die Anzahl der Wiederholungen` / `das Wiederholungsende` |

- [ ] **Step 1: Write the failing predicate tests**, each asserting `getConditions()` against the exact string above
  and `getParameters() === ['start' => 100, 'stop' => 200]`:
  - `testTheStartLiesInThePeriod` (`start_field` `startTime`):
    `` `main.startTime` IS NOT NULL AND `main.startTime` <> 0 AND ((`main.startTime` BETWEEN :start AND :stop)) ``
  - `testAnEndBeforeTheStartNeverMatches` (`stop_field` `endTime`, no overlap) → `<end_ok>` present
  - `testOverlapComparesTheEffectiveEnd` (`stop_field` `endTime`, `overlap` true)
  - `testOverlapNeedsAnEndColumn` (`overlap` true, no `stop_field`) → same string as the first test
  - `testRecurrenceAddsContaosApproximation` (`recurring`, `recurrences`, `repeatEnd`) — includes
    `(n(c) > 0 AND n(re) >= :start)`
  - `testCastColumnsAreReadAsNumbers` (`start_field` `s`, `stop_field` `e`, both in `cast_fields`, overlap): every
    `n(s)`/`n(e)` is `` CAST(`main.s` AS SIGNED) ``, also both sides of `n(e) >= n(s)`
  - `testParametersAreBoundAsIntegers`: `build(null)->getTypes() === ['start' => ParameterType::INTEGER, 'stop' =>
    ParameterType::INTEGER]`
  - `testPartialRecurrenceIsRejected`: resolving `['start' => 1, 'stop' => 2, 'start_field' => 's',
    'recurring_field' => 'r']` through an `OptionsResolver` configured by the predicate throws
    `InvalidOptionsException`
- [ ] **Step 2: Write the failing element tests** (stub `tl_test` with `StubsContaoDcaTrait`: `startTime`/`endTime`
  `"bigint(20) NULL"`, `recurring` `['type' => 'boolean']`, `recurrences` `"smallint(5) unsigned NOT NULL default
  0"`, `repeatEnd` `"int(10) unsigned NOT NULL default 0"`, `startStr` `"varchar(10) NULL"`, `day` `"date NULL"`,
  `renamed` `['type' => 'integer', 'name' => 'physical']`, `virtual` without `sql`); `BASE` becomes the new keys
  with `start_field` `startTime`. Options are read from the resolved proposition (unused ones are `null`):
  - `testTheTransformReadsTheColumns`: a row with all seven columns → the config keys; empty pickers → `null`.
  - `testThePredicateGetsTheColumns`: with end and recurrence → `start_field`, `stop_field`, `overlap`, the three
    recurrence fields, `cast_fields` `[]`.
  - `testThePredicateGetsThePhysicalName`: `start_field` `renamed` → option `start_field` `physical`.
  - `testOverlapIsOnlyPassedWithAnEndColumn`: `overlap` true, no `stop_field` → `overlap` false, `stop_field` null.
  - `testRecurrenceIsOnlyPassedWhenSwitchedOn`: pickers set, `use_recurrence` false → recurrence options null.
  - `testOtherColumnsAreCast`: `start_field` `startStr` → `cast_fields` `['startStr']`.
  - `testAnEmptyStartIsBroken` → `FilterException`, message `errors.calendar.no_column`.
  - `testAnUnknownColumnIsBroken` (`nope` and `virtual`) → `errors.calendar.unknown_column`.
  - `testADateColumnIsBroken` (`day`) → `errors.calendar.date_column`.
  - `testRecurrenceWithoutItsColumnsIsBroken` (`use_recurrence` true, fields null) → `errors.calendar.no_column`.
  - `testAnInvertedPeriodIsBroken` (`bound(2000, 1000)`, no value) → `errors.calendar.inverted_period`.
  - `testChecksRunBeforeAnEmptyIntersectionAborts`: an unknown column with `bound(1000, 2000)` and value
    `tryFrom(3000, null)` → `FilterException`, not `AbortFilteringException`.
  - `testAnEmptyIntersectionAborts` stays (value outside a valid period → `AbortFilteringException`), without the
    `has_extended_events` data provider; the other period tests run on the new `BASE`;
    `testTheTransformSatisfiesTheSchema` uses the new columns.
- [ ] **Step 3: Run** — `make test tests/Filter/Predicate/CalendarCurrentPredicateTest.php`, then
  `make test tests/Filter/Element/CalendarCurrentFilterElementTest.php`. Expected: both FAIL.
- [ ] **Step 4: Implement** the predicate's `configureOptions()` and `buildConditions()`, then the element's
  `configureOptions()`, `transformFilterModel()` (`$model->elm_… ?: null`, `(bool)` for the checkboxes) and
  `buildEffect()` as specified; the checks in private methods; the translations.
- [ ] **Step 5: Run** — the two files one by one, then `make test`. Expected: PASS (`buildDca()` and its tests are
  unchanged until Task 4).
- [ ] **Step 6: Commit** — `feat(filter): calendar filter reads the editor's columns and fails on a bad setup`.

---

### Task 4: Backend fields, palette and options

**Files:**
- Modify: `contao/dca/tl_flare_filter.php`, `src/Filter/Element/CalendarCurrentFilterElement.php` (`buildDca()`),
  `contao/languages/en/tl_flare_filter.php`, `contao/languages/de/tl_flare_filter.php`,
  `src/Model/DocumentsFilterModelTrait.php`
- Test: `tests/Filter/Element/CalendarCurrentFilterElementTest.php`

**Interfaces:**
- Consumes: Task 2 `DcaHelper::classifySql()`, `DcaHelper::getFieldOptions()`.
- Produces: the column definitions Task 6 repeats in plain DDL — pickers `"varchar(64) NOT NULL default ''"`,
  `elm_overlap`/`elm_useRecurrence` `['type' => 'boolean', 'default' => false]`.

DCA fields (static, `exclude` true): `elm_periodStartField` (select, `mandatory`, `includeBlankOption`, `chosen`,
`tl_class` `w50 clr`), `elm_periodStopField` (select, `includeBlankOption`, `chosen`, `submitOnChange`, `w50`), the
three recurrence pickers (as the start picker, `w50`), `sql` as above; `elm_overlap` (checkbox, `w50 m12`) and
`elm_useRecurrence` (checkbox, `submitOnChange`, `w50 clr`). `elm_hasExtendedEvents` is removed from the DCA (C1,
C12).

`buildDca()`: palette
`{date_start_legend},elm_configureStart;{date_stop_legend},elm_configureStop;{date_columns_legend},elm_periodStartField,elm_periodStopField,elm_useRecurrence`
with `elm_overlap` after `elm_periodStopField` when `$context->filterModel?->elm_periodStopField` is not empty;
`selector('elm_useRecurrence', 'elm_recurringField,elm_recurrencesField,elm_repeatEndField')`; each of the five
pickers gets `options()` = `DcaHelper::getFieldOptions($context->listModel->dc, …)` keeping fields whose `sql`
classifies as `Integer` or `Other` (C3). The `elm_startAt`/`elm_stopAt` handling stays.

Labels: `elm_periodStartField` en `['Start column', 'The column holding the start as a Unix timestamp.']` de
`['Spalte Beginn', 'Die Spalte, die den Beginn als Unix-Zeitstempel enthält.']`; `elm_periodStopField` en
`['End column', 'The column holding the end as a Unix timestamp. Leave empty if entries are points in time; an entry
ending before it starts is never shown.']` de `['Spalte Ende', 'Die Spalte, die das Ende als Unix-Zeitstempel
enthält. Leer lassen, wenn Einträge Zeitpunkte sind; ein Eintrag, der vor seinem Beginn endet, wird nie
angezeigt.']`; `elm_overlap` en `['Overlap', 'Also match entries that end within the period or span it, not only
those that start within it.']` de `['Überlappung', 'Auch Einträge finden, die im Zeitraum enden oder ihn
überspannen, nicht nur solche, die in ihm beginnen.']`; `elm_useRecurrence` en `['Consider recurrence', 'Also match
recurring entries whose series reaches into the period, by Contao\'s recurrence model.']` de `['Rekurrenz
berücksichtigen', 'Auch wiederkehrende Einträge finden, deren Serie in den Zeitraum reicht (Wiederholungsmodell von
Contao).']`; `elm_recurringField` en `['Recurring column', 'The column marking an entry as recurring.']` de
`['Spalte Wiederholung', 'Die Spalte, die einen Eintrag als wiederkehrend kennzeichnet.']`; `elm_recurrencesField`
en `['Recurrences column', 'The column holding the number of recurrences (0 = unlimited).']` de `['Spalte Anzahl
Wiederholungen', 'Die Spalte mit der Anzahl der Wiederholungen (0 = unbegrenzt).']`; `elm_repeatEndField` en
`['Repeat end column', 'The column holding the end of the last recurrence as a Unix timestamp.']` de `['Spalte
Wiederholungsende', 'Die Spalte, die das Ende der letzten Wiederholung als Unix-Zeitstempel enthält.']`;
`date_columns_legend` en `Date columns` de `Datumsspalten`. Remove the `elm_hasExtendedEvents` labels and model
property; add the seven properties to `DocumentsFilterModelTrait`.

- [ ] **Step 1: Write the failing tests** (`applyDca()` stubs the list table `tl_calendar_events` with the event
  columns plus `day` `"date NULL"` and `virtual` without `sql`):
  - `testThePaletteListsTheColumnPickers` (no end picker) → the palette above, without `elm_overlap`.
  - `testOverlapAppearsWithAnEndColumn` (`elm_periodStopField` `endTime`) → `…elm_periodStopField,elm_overlap,…`.
  - `testRecurrencePickersAreItsSubpalette` → `__selector__` contains `elm_useRecurrence`, `subpalettes` maps it to the
    three pickers.
  - `testPickersOfferColumnsWithoutDateTypes` → each picker's options contain `startTime`, `repeatEnd`, not `day`,
    not `virtual`.
  - Replace `testThePaletteHasNoLimitLegend`.
- [ ] **Step 2: Run** — `make test tests/Filter/Element/CalendarCurrentFilterElementTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement** the fields, `buildDca()`, labels and model docs as specified.
- [ ] **Step 4: Run** — `make test`, `make phpstan`. Expected: PASS, no new errors.
- [ ] **Step 5: Commit** — `feat(backend): column pickers, overlap and recurrence for the calendar filter`.

---

### Task 5: One-time prefill, written with the type (C9, C14)

**Files:**
- Create: `src/Contao/Callback/FlareFilter/CalendarColumnsPrefillCallbacks.php`
- Modify: `tests/DependencyInjection/ServicesConfigTest.php` (add to `provideServices()`)
- Test: `tests/Contao/Callback/FlareFilter/CalendarColumnsPrefillCallbacksTest.php`

**Interfaces:**
- Consumes: Task 2 `DcaHelper::getColumn()`; `CalendarCurrentFilterElement::TYPE`.
- Produces (`@internal`): `final class CalendarColumnsPrefillCallbacks implements ResetInterface`,
  `__construct(Connection $connection, ?bool $writeOnSubmit = null)` — null detects it:
  `version_compare(InstalledVersions::getVersion('contao/core-bundle') ?? '0', '5.0', '>=')`;
  `#[AsCallback(FilterContainer::TABLE_NAME, 'fields.type.save')] onSaveType(mixed $value, DataContainer $dc): mixed`
  (returns `$value`); `#[AsCallback(FilterContainer::TABLE_NAME, 'config.onbeforesubmit')]
  onBeforeSubmit(array $values, DataContainer $dc): array`; `reset(): void` clears the pending prefills.

`onSaveType()` reads the stored row with `SELECT f.type, l.dc FROM tl_flare_filter f LEFT JOIN tl_flare_list l ON
l.id = f.pid WHERE f.id = ?` (`(int) $dc->id`). Only when the submitted type is the calendar type and the stored one
is not, it computes the prefill: each picker = its event column (`startTime`, `endTime`, `recurring`, `recurrences`,
`repeatEnd`) when `getColumn($table, $name)` is not null and not `Date`, else `''`; `elm_useRecurrence` = 1 when all
three recurrence pickers are set, else 0. With `$writeOnSubmit` it keeps the prefill by id; `onBeforeSubmit()`
returns `$values + $pending[$id]` (submitted values win) and drops the entry. Without it (4.13) `onSaveType()` writes
the prefill at once with `update('tl_flare_filter', …, ['id' => $id])`. Before Step 3, confirm in Contao 4.13.55's
`DC_Table::save()` (GitHub) that each field is written right after its callback and that 4.13 has no
`onbeforesubmit_callback`; note it in the commit body.

- [ ] **Step 1: Write the failing tests** (mocked `Connection` and `DataContainer` with `__get('id')` → 7;
  `StubsContaoDcaTrait` for the list tables):
  - `testAnEventTableIsPrefilledOnSubmit` (`writeOnSubmit` true): `onSaveType()` writes nothing;
    `onBeforeSubmit(['type' => 'flare_calendar_current'], $dc)` returns the type plus the five event columns and
    `elm_useRecurrence` 1.
  - `testSubmittedValuesWin`: `onBeforeSubmit()` with `elm_periodStopField` `''` submitted keeps `''`.
  - `testARejectedSubmitWritesNoPrefill`: `onSaveType()`, then no `onBeforeSubmit()`, then `reset()` → no update, and
    a later `onBeforeSubmit()` for id 7 returns its values unchanged.
  - `testContao413WritesAtOnce` (`writeOnSubmit` false) → one update with the six columns, `['id' => 7]`.
  - `testATableWithoutEventColumnsGetsEmptyPickers` → all `''`, `elm_useRecurrence` 0.
  - `testRecurrenceStaysOffWithoutAllItsColumns` (only `startTime`, `recurring`) → those two set,
    `elm_useRecurrence` 0.
  - `testADateTypedEventColumnIsNotPrefilled` (`startTime` `"datetime NULL"`) → start `''`.
  - `testASaveThatKeepsTheTypeWritesNothing` (stored type calendar) and `testAnotherTypeWritesNothing`.
  - `testARowReturningToTheTypeIsPrefilledAfresh` (stored `flare_published`, stale pickers) → all six columns set.
  - each `onSaveType()` call returns the submitted value.
- [ ] **Step 2: Run** — `make test tests/Contao/Callback/FlareFilter/CalendarColumnsPrefillCallbacksTest.php`.
  Expected: FAIL.
- [ ] **Step 3: Implement** as specified.
- [ ] **Step 4: Run** — that file, then `tests/DependencyInjection/ServicesConfigTest.php`. Expected: PASS.
- [ ] **Step 5: Commit** — `feat(backend): prefill the calendar column pickers once, with the type change`.

---

### Task 6: `CalendarColumnsMigration` (C8, C12)

**Files:**
- Create: `src/Migration/CalendarColumnsMigration.php`
- Modify: `tests/Migration/FakeFilterTableTrait.php`, `tests/DependencyInjection/ServicesConfigTest.php`,
  `AGENTS.md` (the `src/Migration/` entry)
- Test: `tests/Migration/CalendarColumnsMigrationTest.php`

**Interfaces:**
- Consumes: Task 4's column definitions.
- Produces: `CalendarColumnsMigration extends AbstractMigration`, `__construct(Connection $connection)`, marker
  column `calendarColumnsFilled`.

Pattern of `RetireCalendarLimitMigration` (read it): frozen constants `TABLE`, `MARKER`, `TYPE`; `REQUIRED_COLUMNS`
= `type`, `elm_hasExtendedEvents`; `TARGET_COLUMNS` = the five pickers → `VARCHAR(64) NOT NULL DEFAULT ''`,
`elm_overlap`, `elm_useRecurrence` → `TINYINT(1) NOT NULL DEFAULT 0`. `shouldRun()`: false without the table or a
required column; true when the marker or a target column is missing; else whether pending rows exist. No dependency
on the backfill or the retire migration. `run()`: adds each missing target column (`ALTER TABLE … ADD …`), adds the
marker (`CHAR(1) NOT NULL DEFAULT '0'`) and switches its default to `'1'`, updates each pending calendar row in one
statement with its marker — `startTime`, `endTime`, `elm_useRecurrence` 1, `recurring`, `recurrences`,
`repeatEnd`, `elm_overlap` = `(int) (bool) (int) elm_hasExtendedEvents` — and marks the rest done. Result:
`"{name} ({n} calendar filters set to the event columns)"`. The class docblock explains why it adds its columns and
the guard (C12). `AGENTS.md`: add the migration (marker, own columns, guard, only after the rename).

`FakeFilterTableTrait`: `addRow()` defaults for `elm_hasExtendedEvents` (`''`), the pickers (`''`) and the two
checkboxes (`0`); `executeStatement()` applies any `ALTER TABLE … ADD <column> … DEFAULT <value>` (the column joins
`$this->columns`, every row gets the default), as it does for markers.

- [ ] **Step 1: Write the failing tests** (pattern of `RetireCalendarLimitMigrationTest`):
  - `testACalendarRowGetsTheEventColumns` (`elm_hasExtendedEvents` `''`) → one update with the seven values, overlap
    0, marker `'1'`.
  - `testExtendedEventsBecomeOverlap` (`'1'`) → `elm_overlap` 1.
  - `testItAddsMissingTargetColumns`: a table with `type` and `elm_hasExtendedEvents` only → `shouldRun()` true;
    after `run()` the seven columns exist and the row is filled.
  - `testOtherTypesAreMarkedDone` → no per-row update, all markers `'1'`.
  - `testItNeedsItsSource`: without `elm_hasExtendedEvents` (and with `hasExtendedEvents`, before the rename) → false.
  - `testItDoesNotWaitForTheOtherMigrations`: backfill pending, no `exclusive`/`outputs` columns → still true.
  - `testShouldRunGoesQuietAfterARun`, `testRowsCreatedAfterwardsAreNeverPending`.
  - `testTheDeleteRunLeavesItQuiet`: after a run, drop the marker and `elm_hasExtendedEvents` → `shouldRun()` false.
  - `testAFirstRunWithDeletesMigratesEveryRow`: the v0.2 schema (with `elm_hasExtendedEvents`, without the targets,
    the exclusive columns present), a calendar row; one pass running every migration whose `shouldRun()` is true
    (`BackfillFilterValuesMigration`, `RetireCalendarLimitMigration`, this one), then a simulated schema diff with
    deletes (drops every column outside the DCA: `elm_hasExtendedEvents`, the markers, `elm_isLimited`), then a
    second pass → the row holds the event columns, and this migration's `shouldRun()` is false.
- [ ] **Step 2: Run** — `make test tests/Migration/CalendarColumnsMigrationTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement** as specified.
- [ ] **Step 4: Run** — `make test`, `make phpstan`, `mago lint --minimum-fail-level note`. Expected: PASS, clean.
- [ ] **Step 5: Commit** — `feat(migration): add and fill the calendar filters' column pickers`.

---

### Task 7: Docs (docs worktree, branch `docs/main`)

**Files:**
- Modify: `docs/docs/reference/filter-elements.md` (table row; *Calendar time window*: columns, overlap,
  recurrence, empty values, an end before the start, Unix timestamps only, broken on a missing or date column or an
  inverted period), `docs/docs/reference/predicates.md` (`CalendarCurrentPredicate`'s options with an old-to-new
  example: `['start' => …, 'stop' => …, 'has_extended_events' => true]` → `['start' => …, 'stop' => …,
  'start_field' => 'startTime', 'stop_field' => 'endTime', 'overlap' => true, 'recurring_field' => 'recurring',
  'recurrences_field' => 'recurrences', 'repeat_end_field' => 'repeatEnd']`), `docs/docs/migrating-from-v0.1.md`
  (the migration fills the event columns and turns *Extend events* into *Overlap*; Goal 6's behavior changes)

- [ ] **Step 1: Write** the changes from the spec (Intent, Goal 6, C3–C5, C7, C9–C11, C17) in the docs' voice.
- [ ] **Step 2: Check** — `npm run build` in `docs/` if `node_modules` exists. Expected: no broken links.
- [ ] **Step 3: Commit** in `docs/` — `docs: calendar filter reads picked columns`.

---

### Task 8: Sandbox verification (controller, C16)

Run by the controller session after Tasks 2–7, on this branch, in the sandbox prepared in Task 1.

- [ ] **Step 1: Migrate** — `ddev console cache:clear`, `ddev console contao:migrate --no-interaction` (no deletes).
  Expected: the migration reports its calendar filters; `CC fixed` has `elm_overlap` 0, `CC fixed overlap` 1, all
  three and filter 10 hold the event columns with recurrence on.
- [ ] **Step 2: Untouched filters first** — render `CC fixed` and `CC fixed overlap` into `after/`, before the
  driver changes anything. Expected: as `before/`, without `CC reversed` (Goal 6).
- [ ] **Step 3: Driver** — `run.sh after elm_overlap`; `diff -r before after`. Expected: the only differences are
  `CC reversed` lines missing in `after/` (`march`, `narrow`, `reversed-ov`, …).
- [ ] **Step 4: Schema** — `ddev console contao:migrate --schema-only --dry-run`. Expected: nothing for the seven
  new columns (the migration's DDL matches the DCA); the drops it lists are legacy columns only.
- [ ] **Step 5: Test table** — a sandbox DCA `contao/dca/tl_flare_period_check.php` (`id`, `tstamp`, `title`;
  `startInt`/`endInt` `"int(10) unsigned NULL"`; `startStr`/`endStr` `"varchar(10) NULL"`; `recFlag`
  `"char(1) NULL"`, `recCount` `"int(10) NULL"`, `recEnd` `"int(10) unsigned NULL"`; `day` `"date NULL"`),
  `contao:migrate`, rows (str columns hold the same digits; `NULL` stays `NULL`; recurrence columns `NULL` unless
  given):

  | Title | Start | End | Recurrence (flag, count, repeat end) |
  |---|---|---|---|
  | `point` | 2030-03-10 12:00 | NULL | |
  | `blank-end` | 2030-03-11 12:00 | 0 (str `''`) | |
  | `zero` | 0 (str `'0'`) | 2030-03-10 | |
  | `null` | NULL | 2030-03-10 | |
  | `blank` | 0 (str `''`) | 2030-03-10 | |
  | `backwards` | 2030-03-20 | 2030-03-01 | |
  | `endless` | 2030-02-20 | 0 (str `'0'`) | |
  | `spanning` | 2030-02-20 | 2030-03-05 | |
  | `digits` | 999999999 | 1000000500 | |
  | `rec-unlimited` | 2030-01-05 | 2030-01-05 13:00 | `'1'`, 0, NULL |
  | `rec-null-count` | 2030-01-05 | 2030-01-05 13:00 | `'1'`, NULL, NULL |
  | `rec-limited-in` | 2030-01-05 | 2030-01-05 13:00 | `'1'`, 4, 2030-03-05 |
  | `rec-limited-out` | 2030-01-05 | 2030-01-05 13:00 | `'1'`, 4, 2030-02-20 |
  | `rec-negative` | 2030-01-05 | 2030-01-05 13:00 | `'1'`, -1, 2030-12-31 |
  | `rec-off` | 2030-01-05 | 2030-01-05 13:00 | `''`, 0, NULL |
  | `rec-flag-zero` | 2030-01-05 | 2030-01-05 13:00 | `'0'`, 0, NULL |

  Lists (`flare_generic_dc` on the table, template `calendar_check`), created in the backend: `int` (startInt/endInt),
  `str` (startStr/endStr), `rec` (startInt/endInt, recurrence on with the three `rec*` columns). Expected, for `int`
  and `str` alike (the `rec-*` rows match by their own date only, which lies outside every period but the open one):

  | Period | Overlap | Entries |
  |---|---|---|
  | `2030-03-01` – `2030-03-31 23:59` | off | `blank-end`, `point` |
  | same | on | `blank-end`, `point`, `spanning` |
  | `2030-03-02` – `2030-03-15` | on | `blank-end`, `point`, `spanning` |
  | `2030-03-19` – `2030-03-21` | on | none (`backwards` starts inside but ends before it starts) |
  | open – open | off | `blank-end`, `digits`, `endless`, `point`, `spanning`, every `rec-*` |
  | `@1000000200` – `@1000001000` | on | `digits` |
  | same | off | none |

  `rec`, March, overlap off: `blank-end`, `point`, `rec-limited-in`, `rec-null-count`, `rec-unlimited`.
- [ ] **Step 6: Backend** (in a browser; ask the user for a backend login via `request_secret` if needed):
  - a new filter switched to the calendar type on `CC driver`'s list → pickers `startTime`, `endTime`, recurrence on
    with its three columns; on a test-table list → all pickers empty, recurrence off;
  - a type switch submitted with an invalid alias (e.g. one with spaces) → the error shows; the database row keeps
    its old type and empty pickers;
  - clear the end picker on a calendar filter, save, reload → stays empty, *Overlap* hidden;
  - the test table's pickers offer `startInt`, `startStr`, …, not `day`;
  - a filter on `day`, one whose start picker is set to `nope` via SQL, and one with stop `2030-03-01` before start
    `2030-03-31` → the filter overview marks all three broken with Task 3's messages.
- [ ] **Step 7: Failed view without debug** — set `APP_ENV=prod` in the sandbox's `.env.local`,
  `ddev console cache:clear --env=prod`, `curl` the broken lists' pages → `list.failed` markup; restore `.env.local`.
- [ ] **Step 8: Reruns** — `contao:migrate` → the migration does not run. Change `CC fixed`'s end picker to `''`
  and recurrence off in the backend; `contao:migrate --with-deletes --no-interaction` → drops `calendarColumnsFilled`
  and `elm_hasExtendedEvents`, the migration stays quiet; `SELECT` the filter → the end picker still `''`, recurrence
  off; render it → the entries its new config gives.
- [ ] **Step 9: First run with deletes** — `ddev snapshot restore flare-calendar-columns-baseline`,
  `ddev console contao:migrate --with-deletes --no-interaction` → every calendar filter holds the event columns;
  render `CC fixed` and `CC fixed overlap` → as in Step 2.
- [ ] **Step 10: Restore and report** — `ddev snapshot restore flare-calendar-columns-before` if the user wants the
  sandbox back; report each step's outcome, with any difference shown.
