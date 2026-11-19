# Calendar Columns Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or
> superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `flare_calendar_current` reads the date columns the editor picks (start, optional end, opt-in Contao
recurrence), treats empty values one way on every table, and fails as a broken filter instead of an SQL error.

**Architecture:** A column classifier in `DcaHelper` tells the element, from the list table's DCA `sql`, whether a
picked column exists and is an integer, another type (read through `CAST(… AS SIGNED)`) or an SQL date type
(rejected). The element checks its columns in `buildEffect()` and hands the predicate column names, the cast list,
overlap and recurrence; the predicate writes one condition. A save callback on `type` prefills the pickers once; a
run-once migration fills the event columns into stored rows.

**Tech Stack:** PHP 8.2+, Symfony, Contao 4.13/5.x, Doctrine DBAL, PHPUnit 9, Mago; the Contao 5.3 sandbox
(DDEV, MariaDB 10.11) for the database and backend test.

**Spec:** `dev/superpowers/specs/2026-11-19-calendar-columns-design.md` (C1–C16). Read it first.

## Global Constraints

- `declare(strict_types=1);` in every PHP file under `src/` and `tests/`; PER style, 4-space indent, 120 chars.
- SQL runs on MySQL 5.7/8 and MariaDB; migrations write plain DDL valid on all three and never delete.
- Unix timestamps only (C11); parameters bound as integers (C6) — `FilterConditionsBuilder::setParameter()` already
  binds an `int` as `ParameterType::INTEGER`, so the predicate passes ints and states no type.
- Element type `flare_calendar_current` keeps its name; targeting (`isTargeted`, `targetAlias`) is ignored.
- Messages an editor may see go through `AbstractFilterElement::trans()` (`flare` domain), en and de.
- Tests: `make test [path]`; static analysis: `make phpstan`; lint before every commit: `mago lint --minimum-fail-level
  note` (local binary, CI runs the same).
- Commits: conventional prefix, no `Co-Authored-By` trailer, never push. Docs commit in the `docs/` worktree (branch
  `docs/main`), separately.

## Review Focus

1. A filter with *Overlap* stored on whose end picker was cleared later: the start must lie in the period (no
   overlap, no SQL on an empty column) — Task 3 `testOverlapNeedsAnEndColumn` and
   `testOverlapIsOnlyPassedWithAnEndColumn`.
2. *Consider recurrence* on while the recurrence pickers are empty (a type change on a non-event table): a broken
   filter with a translated message, never an SQL error — Task 3 `testRecurrenceWithoutItsColumnsIsBroken`.
3. A broken column with an empty period intersection: the column check runs before `abort()`, so the inspector
   still reports the filter as broken — Task 3 `testColumnsAreCheckedBeforeAnEmptyPeriodAborts`.
4. A row that leaves the calendar type and comes back gets a fresh prefill over its stale pickers — Task 5
   `testARowReturningToTheTypeIsPrefilledAfresh`.
5. A `varchar` start and end compared with each other (end before start) — Task 3 `testCastColumnsAreReadAsNumbers`
   pins the cast on both sides of `n(e) < n(s)`; the sandbox case `digits` (Task 8) proves it on MariaDB.

---

### Task 1: Sandbox fixtures and baseline (controller, before any code)

Run by the controller session, not a subagent: it needs DDEV and records the state before the change. The branch's
code equals `v0.2` (`6bf0a6f1`) at this point, so the sandbox runs today's element.

Sandbox: `~/Kunden/ericges/contao-5.3-sandbox` (see its `CLAUDE.md`; `ddev console …`, `ddev mysql …`). It runs this
checkout through its symlinked path repository. Sandbox changes stay uncommitted unless the user says otherwise.
Work files go to `~/Kunden/ericges/contao-5.3-sandbox/var/flare-calendar-check/` (ignored).

- [ ] **Step 1: Snapshot** — `ddev snapshot --name flare-calendar-columns-before`. Expected: snapshot created.
- [ ] **Step 2: Event fixtures** — insert into `tl_calendar_events` (calendar `pid` 3, published, unique alias, titles
  prefixed `CC `) the rows below as Contao writes them (`startDate`/`startTime`, `endDate`/`endTime` = end, `addTime`
  1; recurring rows `recurring` 1, `repeatEach` `a:2:{s:4:"unit";s:5:"weeks";s:5:"value";s:1:"1";}`). Timestamps from
  PHP's time zone: `ddev exec php -r 'echo strtotime("2030-03-10 10:00");'`.

  | Title | Start | End | Recurrence |
  |---|---|---|---|
  | `CC one day` | 2030-03-10 10:00 | 2030-03-10 12:00 | — |
  | `CC several days` | 2030-03-08 00:00 | 2030-03-12 23:59 | — |
  | `CC ends in March` | 2030-02-25 00:00 | 2030-03-05 23:59 | — |
  | `CC weekly limited` | 2030-01-05 10:00 | 2030-01-05 12:00 | 4, `repeatEnd` = start of the 4th repeat (2030-02-02 10:00) |
  | `CC weekly unlimited` | 2030-01-10 10:00 | 2030-01-10 12:00 | 0, `repeatEnd` 4294967295 |
  | `CC later` | 2030-04-20 10:00 | 2030-04-20 12:00 | — |

- [ ] **Step 3: Check list and page** — a FLARE list `Calendar check` (driver `flare_events`, `tl_calendar_events`)
  with exactly one published filter: `flare_calendar_current`, no control, no value, not exclusive; and a published
  page whose only FLARE element is a list view of it with no pagination limit, reachable by `curl` without login.
  Content element 430 on page 74 (a list view) can serve as a model. Note the filter's id.
- [ ] **Step 4: Driver** — `var/flare-calendar-check/run.sh <phase> <overlap-column>`: for each case below, `UPDATE`
  the filter's `elm_configureStart`/`elm_startAt`/`elm_configureStop`/`elm_stopAt` (`str` + the expression, or `''`
  for an open side) and the overlap column, `curl` the page past any HTTP cache (dev mode or a cache-busting query),
  and write the sorted entry titles to `<phase>/<case>.txt`.

  | Case | Start | Stop | Overlap |
  |---|---|---|---|
  | `march` / `march-ov` | `2030-03-01` | `2030-03-31 23:59` | off / on |
  | `narrow` / `narrow-ov` | `2030-03-09` | `2030-03-11` | off / on |
  | `turn` / `turn-ov` | `2030-02-26` | `2030-03-06` | off / on |
  | `feb` / `feb-ov` | `2030-02-10` | `2030-02-20` | off / on |
  | `until-jan` | open | `2030-01-31` | off |
  | `from-april` | `2030-04-01` | open | off |
  | `open` | open | open | off |

- [ ] **Step 5: Baseline** — `run.sh before elm_hasExtendedEvents`. Expected: 11 files; `march` lists `CC one day`,
  `CC several days`, `CC weekly unlimited` and the sandbox's four events (all unlimited recurring), not `CC weekly
  limited` (its repeat end is in February) nor `CC ends in March` (overlap off).

---

### Task 2: Column kinds in `DcaHelper`

**Files:**
- Create: `src/Util/SqlColumnKind.php`
- Modify: `src/Util/DcaHelper.php`
- Test: `tests/Util/DcaHelperTest.php` (create)

**Interfaces:**
- Produces: `enum SqlColumnKind { case Integer; case Date; case Other; }` (`@internal`);
  `DcaHelper::classifySql(array|string|null $sql): ?SqlColumnKind` (null for an empty `sql`);
  `DcaHelper::getColumnKind(string $table, string $column): ?SqlColumnKind` (loads the DCA; null when the field is
  missing or has no `sql`).

- [ ] **Step 1: Write the failing tests** — `testClassifiesSqlDefinitions` (data provider, `classifySql()`):
  `"bigint(20) NULL"`, `"int(10) unsigned NOT NULL default 0"`, `"smallint(5) unsigned NOT NULL default 0"`,
  `" INT(10) NULL"`, `"integer"`, `['type' => 'boolean', 'default' => false]`, `['type' => 'integer']`,
  `['type' => 'bigint']` → `Integer`; `"date NULL"`, `"datetime NOT NULL"`, `"timestamp NULL"`, `"time NULL"`,
  `"year(4) NULL"`, `['type' => 'date']`, `['type' => 'datetime_immutable']`, `['type' => 'datetimetz']`,
  `['type' => 'time']` → `Date`; `"varchar(10) NULL"`, `"char(1) NOT NULL default ''"`, `"decimal(10,2)"`,
  `['type' => 'string', 'length' => 10]` → `Other`; `null`, `''`, `[]` → `null`.
  `testColumnKindReadsTheTablesDca` (uses `Tests\Filter\Element\StubsContaoDcaTrait`, table `tl_test`): a field with
  `sql` `"bigint(20) NULL"` → `Integer`; a field without `sql` → `null`; an unknown field → `null`.
- [ ] **Step 2: Run** — `make test tests/Util/DcaHelperTest.php`. Expected: FAIL (class/methods missing).
- [ ] **Step 3: Implement** — string `sql`: the leading type word, lowercased (`/^\s*([a-z]+)/i`); integer words
  `tinyint smallint mediumint int integer bigint`, date words `date datetime timestamp time year`. Array `sql`: its
  `type`; `smallint integer bigint boolean` → Integer, a type starting with `date` or `time` → Date. Anything else
  with a type → Other.
- [ ] **Step 4: Run** — same command. Expected: PASS.
- [ ] **Step 5: Commit** — `feat(util): classify a DCA column by its SQL type`.

---

### Task 3: Runtime — predicate and element read the picked columns

Predicate and element change together: the element feeds the predicate's options, and either half alone leaves the
suite red.

**Files:**
- Modify: `src/Filter/Predicate/CalendarCurrentPredicate.php`,
  `src/Filter/Element/CalendarCurrentFilterElement.php` (all but `buildDca()`), `translations/flare.en.yaml`,
  `translations/flare.de.yaml`
- Test: `tests/Filter/Predicate/CalendarCurrentPredicateTest.php` (create; builder as in `DcaSelectPredicateTest`:
  mocked connection, `quoteIdentifier` → `` `x` ``, alias `main`), `tests/Filter/Element/CalendarCurrentFilterElementTest.php`

**Interfaces:**
- Consumes: Task 2 `DcaHelper::getColumnKind()`.
- Produces config keys (C1): `start_field`, `stop_field`, `recurring_field`, `recurrences_field`,
  `repeat_end_field` (`?string`, null), `overlap`, `use_recurrence` (`bool`, false); `start_at`/`stop_at` unchanged;
  `has_extended_events` removed. Model columns: `elm_periodStartField`, `elm_periodStopField`, `elm_overlap`,
  `elm_useRecurrence`, `elm_recurringField`, `elm_recurrencesField`, `elm_repeatEndField` (Task 4 adds them to the DCA).
- Produces predicate options: `start`, `stop` (`int|DateTimeInterface`, required, normalized to int — unchanged);
  `start_field` (`string`, required); `stop_field` (`?string`, null); `overlap` (`bool`, false);
  `recurring_field`, `recurrences_field`, `repeat_end_field` (`?string`, null; recurrence applies when
  `recurring_field` is set, and then the other two are required — a normalizer throws
  `InvalidOptionsException` otherwise); `cast_fields` (`list<string>`, []: the columns read as
  `CAST(col AS SIGNED)`). `has_extended_events` is removed. Parameters `start`, `stop`.

The condition is one `$builder->where()` string, with `n(x)` = `$builder->column(x)`, wrapped as
`CAST(… AS SIGNED)` when `x` is in `cast_fields`:

```
n(s) IS NOT NULL AND n(s) <> 0 AND ((<main branch>)[ OR (<recurrence branch>)])
main branch, no overlap or no stop_field:  n(s) BETWEEN :start AND :stop
main branch, overlap and stop_field:       n(s) <= :stop AND <e> >= :start
<e>:                CASE WHEN n(e) IS NULL OR n(e) = 0 OR n(e) < n(s) THEN n(s) ELSE n(e) END
recurrence branch:  n(r) IS NOT NULL AND n(r) <> 0 AND n(s) <= :stop AND (COALESCE(n(c), 0) = 0 OR n(re) >= :start)
```

`buildEffect()` first checks the columns, in this order — start; end if set; the three recurrence columns if
`use_recurrence` — against `$context->list->dc`, then computes the period as today (abort on an empty
intersection), then adds the predicate with `stop_field` and `overlap` only when an end column is set, the
recurrence fields only with `use_recurrence`, and `cast_fields` = the checked columns of kind `Other`. Failures are
`FilterException`s with `trans()` messages (new keys, `%role%` = `trans('errors.calendar.role.<role>')`):

| Key | en | de |
|---|---|---|
| `errors.calendar.no_column` | `No column is selected for the %role%.` | `Für %role% ist keine Spalte ausgewählt.` |
| `errors.calendar.unknown_column` | `The column "%column%" selected for the %role% does not exist in %table%.` | `Die für %role% ausgewählte Spalte „%column%“ gibt es in %table% nicht.` |
| `errors.calendar.date_column` | `The column "%column%" selected for the %role% is an SQL date column; the filter reads Unix timestamps only.` | `Die für %role% ausgewählte Spalte „%column%“ ist eine SQL-Datumsspalte; der Filter liest nur Unix-Zeitstempel.` |
| `errors.calendar.role.start` / `.stop` / `.recurring` / `.recurrences` / `.repeat_end` | `start` / `end` / `recurring flag` / `number of recurrences` / `repeat end` | `den Beginn` / `das Ende` / `die Wiederholung` / `die Anzahl der Wiederholungen` / `das Wiederholungsende` |

- [ ] **Step 1: Write the failing predicate tests**, each asserting `getConditions()` against the exact string above
  and `getParameters() === ['start' => 100, 'stop' => 200]`:
  - `testTheStartLiesInThePeriod` (`start_field` `startTime`):
    `` `main.startTime` IS NOT NULL AND `main.startTime` <> 0 AND ((`main.startTime` BETWEEN :start AND :stop)) ``
  - `testOverlapComparesTheEffectiveEnd` (`stop_field` `endTime`, `overlap` true)
  - `testOverlapNeedsAnEndColumn` (`overlap` true, no `stop_field`) → same string as the first test
  - `testRecurrenceAddsContaosApproximation` (`recurring`, `recurrences`, `repeatEnd`)
  - `testCastColumnsAreReadAsNumbers` (`start_field` `s`, `stop_field` `e`, both in `cast_fields`, overlap):
    every `n(s)`/`n(e)` is `` CAST(`main.s` AS SIGNED) `` — including both sides of `n(e) < n(s)`
  - `testParametersAreBoundAsIntegers`: `build(null)->getTypes() === ['start' => ParameterType::INTEGER, 'stop' =>
    ParameterType::INTEGER]`
  - `testPartialRecurrenceIsRejected`: resolving `['start' => 1, 'stop' => 2, 'start_field' => 's',
    'recurring_field' => 'r']` through an `OptionsResolver` configured by the predicate throws
    `InvalidOptionsException`
- [ ] **Step 2: Write the failing element tests** (stub `tl_test` with `StubsContaoDcaTrait`: `startTime`/`endTime`
  `"bigint(20) NULL"`, `recurring` `['type' => 'boolean']`, `recurrences` `"smallint(5) unsigned NOT NULL default 0"`,
  `repeatEnd` `"int(10) unsigned NOT NULL default 0"`, `startStr` `"varchar(10) NULL"`, `day` `"date NULL"`,
  `virtual` without `sql`); `BASE` becomes the new keys with `start_field` `startTime`:
  - `testTheTransformReadsTheColumns`: a row with all seven columns → the config keys; empty pickers → `null`.
  - `testThePredicateGetsTheColumns`: with end and recurrence → options `start_field`, `stop_field`, `overlap`,
    the three recurrence fields, `cast_fields` `[]`.
  - `testOverlapIsOnlyPassedWithAnEndColumn`: `overlap` true, no `stop_field` → options with `overlap` false and
    no `stop_field` key.
  - `testRecurrenceIsOnlyPassedWhenSwitchedOn`: pickers set, `use_recurrence` false → no recurrence options.
  - `testOtherColumnsAreCast`: `start_field` `startStr` → `cast_fields` `['startStr']`.
  - `testAnEmptyStartIsBroken` → `FilterException`, message `errors.calendar.no_column`.
  - `testAnUnknownColumnIsBroken` (`nope` and `virtual`) → `errors.calendar.unknown_column`.
  - `testADateColumnIsBroken` (`day`) → `errors.calendar.date_column`.
  - `testRecurrenceWithoutItsColumnsIsBroken` (`use_recurrence` true, fields null) → `errors.calendar.no_column`.
  - `testColumnsAreCheckedBeforeAnEmptyPeriodAborts`: unknown column and `start_at` after `stop_at` →
    `FilterException`, not `AbortFilteringException`.
  - Keep the period tests (`range()` reads `options['start']`/`['stop']`) on the new `BASE`; drop the
    `has_extended_events` data provider; `testTheTransformSatisfiesTheSchema` uses the new columns.
- [ ] **Step 3: Run** — `make test tests/Filter/Predicate/CalendarCurrentPredicateTest.php
  tests/Filter/Element/CalendarCurrentFilterElementTest.php`. Expected: FAIL.
- [ ] **Step 4: Implement** the predicate's `configureOptions()` and `buildConditions()`, then the element's
  `configureOptions()`, `transformFilterModel()` (`$model->elm_… ?: null`, `(bool)` for the checkboxes) and
  `buildEffect()` as specified; the column check in a private method; the translations.
- [ ] **Step 5: Run** — the two files, then `make test`. Expected: PASS (`buildDca()` and its tests are unchanged
  until Task 4).
- [ ] **Step 6: Commit** — `feat(filter): calendar filter reads the editor's columns, checks them, treats empty
  values`.

---

### Task 4: Backend fields, palette and options

**Files:**
- Modify: `contao/dca/tl_flare_filter.php`, `src/Filter/Element/CalendarCurrentFilterElement.php` (`buildDca()`),
  `contao/languages/en/tl_flare_filter.php`, `contao/languages/de/tl_flare_filter.php`,
  `src/Model/DocumentsFilterModelTrait.php`
- Test: `tests/Filter/Element/CalendarCurrentFilterElementTest.php`

**Interfaces:**
- Consumes: Task 2 `DcaHelper::classifySql()`, `DcaHelper::getFieldOptions()`.

DCA fields (static, `exclude` true): `elm_periodStartField` (select, `mandatory`, `includeBlankOption`, `chosen`,
`tl_class` `w50 clr`), `elm_periodStopField` (select, `includeBlankOption`, `chosen`, `submitOnChange`, `w50`),
the three recurrence pickers (as the start picker, `w50`), all `sql` `"varchar(64) NOT NULL default ''"`;
`elm_overlap` (checkbox, `w50 m12`) and `elm_useRecurrence` (checkbox, `submitOnChange`, `w50 clr`), `sql`
`['type' => 'boolean', 'default' => false]`. `elm_hasExtendedEvents` is removed from the DCA (C1, C12).

`buildDca()`: palette
`{date_start_legend},elm_configureStart;{date_stop_legend},elm_configureStop;{date_columns_legend},elm_periodStartField,elm_periodStopField,elm_useRecurrence`
with `elm_overlap` inserted after `elm_periodStopField` when `$context->filterModel?->elm_periodStopField` is not
empty; `selector('elm_useRecurrence', 'elm_recurringField,elm_recurrencesField,elm_repeatEndField')`; each of the five
pickers gets `options()` = `DcaHelper::getFieldOptions($context->listModel->dc, …)` keeping fields whose `sql`
classifies as `Integer` or `Other` (C3). The `elm_startAt`/`elm_stopAt` handling stays.

Labels (C10 and new): `elm_periodStartField` en `['Start column', 'The column holding the start as a Unix
timestamp.']` de `['Spalte Beginn', 'Die Spalte, die den Beginn als Unix-Zeitstempel enthält.']`;
`elm_periodStopField` en `['End column', 'The column holding the end as a Unix timestamp. Leave empty if entries are
points in time.']` de `['Spalte Ende', 'Die Spalte, die das Ende als Unix-Zeitstempel enthält. Leer lassen, wenn
Einträge Zeitpunkte sind.']`; `elm_overlap` en `['Overlap', 'Also match entries that end within the period or span
it, not only those that start within it.']` de `['Überlappung', 'Auch Einträge finden, die im Zeitraum enden oder
ihn überspannen, nicht nur solche, die in ihm beginnen.']`; `elm_useRecurrence` en `['Consider recurrence', 'Also
match recurring entries whose series reaches into the period, by Contao\'s recurrence model.']` de `['Rekurrenz
berücksichtigen', 'Auch wiederkehrende Einträge finden, deren Serie in den Zeitraum reicht (Wiederholungsmodell von
Contao).']`; `elm_recurringField` en `['Recurring column', 'The column marking an entry as recurring.']` de
`['Spalte Wiederholung', 'Die Spalte, die einen Eintrag als wiederkehrend kennzeichnet.']`; `elm_recurrencesField` en
`['Recurrences column', 'The column holding the number of recurrences (0 = unlimited).']` de `['Spalte Anzahl
Wiederholungen', 'Die Spalte mit der Anzahl der Wiederholungen (0 = unbegrenzt).']`; `elm_repeatEndField` en
`['Repeat end column', 'The column holding the end of the last recurrence as a Unix timestamp.']` de `['Spalte
Wiederholungsende', 'Die Spalte, die das Ende der letzten Wiederholung als Unix-Zeitstempel enthält.']`;
`date_columns_legend` en `Date columns` de `Datumsspalten`. Remove the `elm_hasExtendedEvents` labels and model
property; add the seven properties to `DocumentsFilterModelTrait`.

- [ ] **Step 1: Write the failing tests** (`applyDca()` gains the list table `tl_calendar_events` stubbed with the
  event columns plus `day` `"date NULL"` and `virtual` without `sql`):
  - `testThePaletteListsTheColumnPickers` (no end picker) → the palette above, without `elm_overlap`.
  - `testOverlapAppearsWithAnEndColumn` (`elm_periodStopField` `endTime`) → `…elm_periodStopField,elm_overlap,…`.
  - `testRecurrencePickersAreItsSubpalette` → `__selector__` contains `elm_useRecurrence`, `subpalettes` maps it to the
    three pickers.
  - `testPickersOfferColumnsWithoutDateTypes` → each picker's options contain `startTime`, `repeatEnd`, not `day`,
    not `virtual`.
  - Replace `testThePaletteHasNoLimitLegend`.
- [ ] **Step 2: Run** — `make test tests/Filter/Element/CalendarCurrentFilterElementTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement** the fields, `buildDca()`, labels and model docs as specified.
- [ ] **Step 4: Run** — `make test` and `make phpstan`. Expected: PASS, no new errors.
- [ ] **Step 5: Commit** — `feat(backend): column pickers, overlap and recurrence for the calendar filter`.

---

### Task 5: One-time prefill on a type change (C9, C14)

**Files:**
- Create: `src/Contao/Callback/FlareFilter/CalendarColumnsPrefillCallback.php`
- Modify: `tests/DependencyInjection/ServicesConfigTest.php` (add to `provideServices()`)
- Test: `tests/Contao/Callback/FlareFilter/CalendarColumnsPrefillCallbackTest.php`

**Interfaces:**
- Consumes: Task 2 `DcaHelper::getColumnKind()`; `CalendarCurrentFilterElement::TYPE`.
- Produces: `#[AsCallback(FilterContainer::TABLE_NAME, 'fields.type.save')] final readonly class
  CalendarColumnsPrefillCallback { __construct(Connection $connection); __invoke(mixed $value, DataContainer $dc):
  mixed }` (`@internal`), returns `$value` unchanged.

It reads the stored row with one query — `SELECT f.type, l.dc FROM tl_flare_filter f LEFT JOIN tl_flare_list l ON
l.id = f.pid WHERE f.id = ?` with `(int) $dc->id` — which still holds the old type when a save callback runs on both
versions (5.3's `DC_Table::save()` collects submitted values in `arrSubmit` and writes them after the callbacks; 4.13
updates each field after its callback — confirm in 4.13's `DC_Table::save()` on GitHub and note it in the commit
body). Only when the submitted type is the calendar type and the stored one is not, it writes one
`update('tl_flare_filter', …, ['id' => $id])`: each picker = its event column (`startTime`, `endTime`, `recurring`,
`recurrences`, `repeatEnd`) when `getColumnKind($table, $name)` is `Integer` or `Other`, else `''`;
`elm_useRecurrence` = 1 when all three recurrence pickers are set, else 0. This supersedes C14's `rowOf()`: the
database read is version-independent and testable.

- [ ] **Step 1: Write the failing tests** (mocked `Connection` and `DataContainer` with `__get('id')` → 7;
  `StubsContaoDcaTrait` for the list tables):
  - `testAnEventTableIsPrefilled` → one update with the five event columns and `elm_useRecurrence` 1.
  - `testATableWithoutEventColumnsGetsEmptyPickers` → all `''`, `elm_useRecurrence` 0.
  - `testRecurrenceStaysOffWithoutAllItsColumns` (only `startTime`, `recurring`) → start and recurring set,
    `elm_useRecurrence` 0.
  - `testADateTypedEventColumnIsNotPrefilled` (`startTime` `"datetime NULL"`) → start `''`.
  - `testASaveThatKeepsTheTypeWritesNothing` (stored type calendar) and `testAnotherTypeWritesNothing` → no update.
  - `testARowReturningToTheTypeIsPrefilledAfresh` (stored type `flare_published`, stale picker values in the row)
    → the update overwrites all six columns.
  - each test asserts the returned value is the submitted one.
- [ ] **Step 2: Run** — `make test tests/Contao/Callback/FlareFilter/CalendarColumnsPrefillCallbackTest.php`.
  Expected: FAIL.
- [ ] **Step 3: Implement** as specified.
- [ ] **Step 4: Run** — that file and `tests/DependencyInjection/ServicesConfigTest.php`. Expected: PASS.
- [ ] **Step 5: Commit** — `feat(backend): prefill the calendar column pickers once on a type change`.

---

### Task 6: `CalendarColumnsMigration` (C8, C12)

**Files:**
- Create: `src/Migration/CalendarColumnsMigration.php`
- Modify: `tests/Migration/FakeFilterTableTrait.php` (row defaults for the new columns and
  `elm_hasExtendedEvents`), `tests/DependencyInjection/ServicesConfigTest.php`, `AGENTS.md` (migration list)
- Test: `tests/Migration/CalendarColumnsMigrationTest.php`

**Interfaces:**
- Consumes: `RetireCalendarLimitMigration::shouldRun()`.
- Produces: `CalendarColumnsMigration extends AbstractMigration`, `__construct(Connection $connection,
  RetireCalendarLimitMigration $retire)`, marker column `calendarColumnsFilled`.

Follows `RetireCalendarLimitMigration` (read it): frozen constants (`TABLE`, `MARKER`, `TYPE`); `REQUIRED_COLUMNS` =
`type`, `elm_hasExtendedEvents`, the five pickers, `elm_overlap`, `elm_useRecurrence`; `shouldRun()` false without
the table or a required column or while `$retire->shouldRun()`, true without the marker, else pending rows exist;
`run()` adds the marker (`CHAR(1) NOT NULL DEFAULT '0'`), switches its default to `'1'`, updates each pending
calendar row in one statement with its marker — `startTime`, `endTime`, `elm_useRecurrence` 1, `recurring`,
`recurrences`, `repeatEnd`, `elm_overlap` = `(int) (bool) (int) elm_hasExtendedEvents` — and marks the rest done.
Result: `"{name} ({n} calendar filters set to the event columns)"`. The class docblock explains the guard (C12).
`AGENTS.md`: add the migration to the `src/Migration/` entry (marker, guard, waits for the retire migration).

- [ ] **Step 1: Write the failing tests** (pattern of `RetireCalendarLimitMigrationTest`, `migration()` with a mocked
  retire whose `shouldRun()` returns `$this->retirePending`):
  - `testACalendarRowGetsTheEventColumns` (`elm_hasExtendedEvents` `''`) → one update with the seven values, overlap
    0, marker `'1'`.
  - `testExtendedEventsBecomeOverlap` (`'1'`) → `elm_overlap` 1.
  - `testOtherTypesAreMarkedDone` → no per-row update, all markers `'1'`.
  - `testItWaitsForTheRetireMigration` → `shouldRun()` false while pending, true after.
  - `testItNeedsItsColumns`: without `elm_hasExtendedEvents`, or without `elm_periodStartField` → false.
  - `testShouldRunGoesQuietAfterARun` and `testRowsCreatedAfterwardsAreNeverPending`.
  - `testTheDeleteRunLeavesItQuiet`: after a run, drop the marker and `elm_hasExtendedEvents` from `$this->columns`
    → `shouldRun()` false.
  - `testAllCalendarMigrationsInOneRun`: the real `BackfillFilterValuesMigration`, `RetireCalendarLimitMigration` and
    this one on one fake table with a v0.1-shaped calendar row, run in a loop while any `shouldRun()` → the row ends
    with the retire changes and the event columns.
- [ ] **Step 2: Run** — `make test tests/Migration/CalendarColumnsMigrationTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement** as specified.
- [ ] **Step 4: Run** — `make test`, `make phpstan`, `mago lint --minimum-fail-level note`. Expected: PASS, clean.
- [ ] **Step 5: Commit** — `feat(migration): fill the calendar filters' event columns`.

---

### Task 7: Docs (docs worktree, branch `docs/main`)

**Files:**
- Modify: `docs/docs/reference/filter-elements.md` (table row of `flare_calendar_current`; section *Calendar time
  window*: columns, overlap, recurrence, empty values, Unix timestamps only, broken on a missing or date column),
  `docs/docs/migrating-from-v0.1.md` (the migration fills the event columns and turns *Extend events* into
  *Overlap*; run the deletes only after all migrations, C12)

- [ ] **Step 1: Write** the changes from the spec's sections Intent, C3–C5, C7, C9, C10, C11 in the docs' voice.
- [ ] **Step 2: Check** — `npm run build` in `docs/` if `node_modules` exists. Expected: no broken links.
- [ ] **Step 3: Commit** in `docs/` — `docs: calendar filter reads picked columns`.

---

### Task 8: Sandbox verification (controller, C16)

Run by the controller session after Tasks 2–7, on this branch, in the sandbox prepared in Task 1.

- [ ] **Step 1: Migrate** — `ddev console cache:clear`, `ddev console contao:migrate --no-interaction` (no deletes).
  Expected: the schema update adds the new columns, the migration reports the calendar filters it set; the check
  filter and filter 10 hold the event columns, recurrence on, `elm_overlap` = their old `elm_hasExtendedEvents`.
- [ ] **Step 2: Goal 6** — `run.sh after elm_overlap`, `diff -r before after`. Expected: no difference.
- [ ] **Step 3: Test table** — a sandbox DCA `contao/dca/tl_flare_period_check.php` (`id`, `tstamp`, `title`;
  `startInt`/`endInt` `"int(10) unsigned NULL"`; `startStr`/`endStr` `"varchar(10) NULL"`; `day` `"date NULL"`),
  `contao:migrate`, then rows (int columns; str columns hold the same digits, `NULL` as `NULL`):

  | Title | Start | End |
  |---|---|---|
  | `point` | 2030-03-10 12:00 | NULL |
  | `zero` | 0 (str `'0'`) | 2030-03-10 |
  | `null` | NULL | 2030-03-10 |
  | `blank` | 0 (str `''`) | 2030-03-10 |
  | `backwards` | 2030-03-20 | 2030-03-01 |
  | `endless` | 2030-02-20 | 0 (str `'0'`) |
  | `spanning` | 2030-02-20 | 2030-03-05 |
  | `digits` | 999999999 | 1000000500 |

  Two lists (`flare_generic_dc` on the table), one calendar filter each — int columns, str columns — created in the
  backend, and pages as in Task 1. Expected, the same for both lists:

  | Period | Overlap | Entries |
  |---|---|---|
  | `2030-03-01` – `2030-03-31 23:59` | off | `backwards`, `point` |
  | same | on | `backwards`, `point`, `spanning` |
  | `2030-03-02` – `2030-03-15` | on | `point`, `spanning` |
  | open – open | off | `backwards`, `digits`, `endless`, `point`, `spanning` |
  | `@1000000200` – `@1000001000` | on | `digits` |
  | same | off | none |

- [ ] **Step 4: Backend** (in a browser; ask the user for a backend login via `request_secret` if needed):
  - a new filter switched to the calendar type on the check list → pickers `startTime`, `endTime`, recurrence on with
    its three columns; on a test-table list → all pickers empty, recurrence off;
  - clear the end picker on the check filter, save, reload → stays empty, *Overlap* hidden;
  - the test table's pickers offer `startInt`, `startStr`, …, not `day`;
  - a filter on `day`, and one whose start picker is set to `nope` via SQL → the filter overview marks both broken
    with the messages of Task 3, and their list view shows `list.failed`.
- [ ] **Step 5: Reruns** — `contao:migrate` again → the migration does not run; `contao:migrate --with-deletes` →
  drops `calendarColumnsFilled` and `elm_hasExtendedEvents`, the migration stays quiet, Step 2's `after` still matches.
- [ ] **Step 6: Restore and report** — `ddev snapshot restore flare-calendar-columns-before` if the user wants the
  sandbox back; report each step's outcome, with any difference shown.
