# Calendar Columns Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or
> superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `flare_calendar_current` reads the date columns the editor picks (start, optional end, opt-in Contao
recurrence), treats empty values and an end before the start one way on every table, and fails as a broken filter —
never as an SQL error — on a bad column or a configured stop before the start.

**Architecture:** `DcaHelper` resolves a picked physical column name against the list table's DCA `sql` into its
kind (integer, other → read through `CAST(… AS SIGNED)`, date → rejected). The element checks its columns and its
period in `buildEffect()` and hands the predicate column names, the cast list, overlap and recurrence; the predicate
writes one condition on the main table. Backend options and the prefill use the table the list driver resolves; the
prefill is written with the type change (5.x: `onbeforesubmit`; 4.13: the type's save callback). A run-once
migration fills the pickers of stored rows.

**Tech Stack:** PHP 8.2+, Symfony, Contao 4.13/5.x, Doctrine DBAL, PHPUnit 9, Mago; the Contao 5.3 sandbox (DDEV,
MariaDB 10.11) for the database and backend test.

**Spec:** `dev/superpowers/specs/2026-11-19-calendar-columns-design.md` (C1–C17, Goal 6's exceptions). Read it first.

## Global Constraints

- `declare(strict_types=1);` in every PHP file under `src/` and `tests/`; PER style, 4-space indent, 120 chars.
- SQL runs on MySQL 5.7/8 and MariaDB; migrations never delete. `contao:migrate --with-deletes` runs only after all
  migrations and schema updates (documented upgrade requirement).
- Unix timestamps only (C11). `FilterConditionsBuilder::setParameter()` already binds an `int` as
  `ParameterType::INTEGER`; the predicate passes ints and states no type.
- Element type `flare_calendar_current` keeps its name; targeting is ignored — the predicate always runs on
  `TableAliasRegistry::ALIAS_MAIN`.
- A picker stores the **physical column name** (C15) everywhere: options, prefill, migration, runtime.
- Contao 5 vs. 4.13: `HeimrichHannot\FlareBundle\Util\Env::isContao5()`.
- Messages an editor may see go through `AbstractFilterElement::trans()` (`flare` domain), en and de.
- Tests: `make test <one path>` (PHPUnit 9 takes only the first path: run files one by one, or `make test`);
  `make phpstan`; before every commit `mago lint --minimum-fail-level note` (local binary, as CI).
- Commits: conventional prefix, no `Co-Authored-By` trailer, never push. Docs commit in the `docs/` worktree (branch
  `docs/main`), separately.

## Review Focus

1. A filter with *Overlap* stored on whose end picker was cleared later: the start must lie in the period, no SQL on
   an empty column — Task 3 `testOverlapNeedsAnEndColumn`, `testOverlapIsOnlyPassedWithAnEndColumn`.
2. *Consider recurrence* on with one of its pickers empty (a type change on a non-event table): broken with a
   translated message, never an SQL error — Task 3 `testRecurrenceWithAMissingColumnIsBroken`.
3. A broken column or an inverted period together with a value that empties the period: the checks run before
   `abort()` — Task 3 `testChecksRunBeforeAnEmptyIntersectionAborts`.
4. A type change submitted with an invalid other field on 5.x writes neither type nor prefill; an unchanged blank
   picker submitted with the type change stays blank — Task 4 (`alwaysSave`), Task 5 `testSubmittedValuesWin`;
   sandbox Task 8.
5. A DCA field whose array `sql` names another physical column (`begins` → `startTime`): migrated filters, options
   and prefill all use `startTime` — Task 2 `testGetColumnFindsThePhysicalName`, Task 4
   `testOptionsArePhysicalNames`.

---

### Task 1: Sandbox fixtures and baseline (controller, before any code)

Run by the controller session, not a subagent: it needs DDEV and records the state before the change. The branch's
code equals `v0.2` (`6bf0a6f1`) at this point, so the sandbox runs today's element.

Sandbox: `~/Kunden/ericges/contao-5.3-sandbox` (see its `CLAUDE.md`; `ddev console …`, `ddev mysql …`; PHP's time
zone is Europe/Berlin). It runs this checkout through its symlinked path repository. Sandbox changes stay
uncommitted unless the user says otherwise. Work files go to `var/flare-calendar-check/` in the sandbox (ignored).

- [ ] **Step 1: Snapshot** — `ddev snapshot --name flare-calendar-columns-before`. Expected: snapshot created.
- [ ] **Step 2: Event fixtures** — insert into `tl_calendar_events` (calendar `pid` 3, published, unique alias,
  titles prefixed `CC `) the rows below as `adjustTime()` writes them (`startDate`/`endDate` the days, `startTime`/
  `endTime` as listed, `addTime` 1; recurring rows `recurring` 1, `repeatEach`
  `a:2:{s:4:"unit";s:5:"weeks";s:5:"value";s:1:"1";}`). Timestamps from `ddev exec php -r 'echo
  strtotime("2030-03-10 10:00");'`.

  | Title | Start | End | Recurrence |
  |---|---|---|---|
  | `CC one day` | 2030-03-10 10:00 | 2030-03-10 12:00 | — |
  | `CC several days` | 2030-03-08 00:00 | 2030-03-12 23:59 | — |
  | `CC ends in March` | 2030-02-25 00:00 | 2030-03-05 23:59 | — |
  | `CC reversed` | 2030-03-10 18:00 | 2030-03-10 10:00 | — (end time before start time) |
  | `CC reversed weekly` | 2030-01-12 18:00 | 2030-01-12 10:00 | 0, `repeatEnd` 4294967295 |
  | `CC weekly limited` | 2030-01-05 10:00 | 2030-01-05 12:00 | 4, `repeatEnd` 2030-02-02 12:00 (end + 4 weeks) |
  | `CC weekly unlimited` | 2030-01-10 10:00 | 2030-01-10 12:00 | 0, `repeatEnd` 4294967295 |
  | `CC later` | 2030-04-20 10:00 | 2030-04-20 12:00 | — |

- [ ] **Step 3: Template** — `templates/content_element/flare_listview/calendar_check.html.twig` in the sandbox,
  extending `@Contao/content_element/flare_listview.html.twig`: empty `filter` block; `list` block rendering
  `<ul class="cc-entries">` (also for zero entries) with one `<li data-entry="{{ entry.row.id }}">{{ entry.row.title
  }}</li>` per `flare_list.entries`; the `failure` block stays. `ddev console cache:clear`.
- [ ] **Step 4: Lists and pages** — FLARE lists with the generic driver (`flare_generic_dc`, data container
  `tl_calendar_events`; the events driver would expand occurrences), each with exactly one published
  `flare_calendar_current` filter (no control, no value, not exclusive), each on its own published page with only a
  list-view content element (template variant `calendar_check`, items per page 0), reachable by `curl` without
  login: `CC fixed` (period `2030-03-01` – `2030-03-31 23:59`, *Extend events* off), `CC fixed overlap` (same,
  *Extend events* on), `CC driver`. Note the filter ids and page URLs in `var/flare-calendar-check/README`.
- [ ] **Step 5: Driver** — `var/flare-calendar-check/run.sh <phase> <filter-id> <page-url> <overlap-column>
  [cases-file]`: for each case, `UPDATE` the filter's `elm_configureStart`/`elm_startAt`/`elm_configureStop`/
  `elm_stopAt` (`str` + the expression, or `''` for an open side) and the overlap column, `curl` the page past any
  HTTP cache, assert HTTP 200 and `cc-entries` (or, where a case expects failure, `flare-list__failed`), and write
  the distinct `data-entry` ids with their titles, sorted, to `<phase>/<case>.txt`. Default cases:

  | Case | Start | Stop | Overlap |
  |---|---|---|---|
  | `march` / `march-ov` | `2030-03-01` | `2030-03-31 23:59` | off / on |
  | `narrow` / `narrow-ov` | `2030-03-09` | `2030-03-11` | off / on |
  | `turn` / `turn-ov` | `2030-02-26` | `2030-03-06` | off / on |
  | `reversed-ov` | `2030-03-10 09:00` | `2030-03-10 11:00` | on |
  | `series-inside` | `2030-01-20` | `2030-01-21` | off |
  | `series-end` | `2030-02-02 12:00` | `2030-02-02 13:00` | off |
  | `series-beyond` | `2030-02-02 12:01` | `2030-02-03` | off |
  | `until-jan` | open | `2030-01-31` | off |
  | `from-april` | `2030-04-01` | open | off |
  | `open` | open | open | off |

- [ ] **Step 6: Baseline** — render `CC fixed` and `CC fixed overlap` into `before/fixed.txt` and
  `before/fixed-overlap.txt`, then run the driver on `CC driver` (phase `before`, `elm_hasExtendedEvents`).
  Expected: `march` lists `CC one day`, `CC several days`, `CC reversed`, `CC reversed weekly`, `CC weekly unlimited`
  and the sandbox's four (unlimited recurring) events; `reversed-ov` lists `CC reversed` (by its end); `series-end`
  lists `CC weekly limited` (repeat end exactly at the window's start), `series-beyond` does not.

---

### Task 2: Physical column and kind in `DcaHelper`

**Files:**
- Create: `src/Util/SqlColumnKind.php`, `src/Util/SqlColumn.php`
- Modify: `src/Util/DcaHelper.php`
- Test: `tests/Util/DcaHelperTest.php` (create)

**Interfaces:**
- Produces (`@internal`): `enum SqlColumnKind { case Integer; case Date; case Other; }`;
  `final readonly class SqlColumn { __construct(public string $name, public string $field, public SqlColumnKind
  $kind) }` (`name` physical, `field` the DCA key);
  `DcaHelper::classifySql(array|string|null $sql): ?SqlColumnKind` (null for an empty `sql`);
  `DcaHelper::getColumns(?string $table): list<SqlColumn>` — the table's DCA fields with an `sql` whose physical
  name matches `/^[a-zA-Z0-9_]+$/`; `[]` for a null or empty table, without loading a DCA;
  `DcaHelper::getColumn(?string $table, string $name): ?SqlColumn` — the one whose physical name equals `$name`
  case-insensitively, else null.

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
  With `Tests\Filter\Element\StubsContaoDcaTrait` stubbing `tl_test` (`start` `"bigint(20) NULL"`, `begins`
  `['type' => 'integer', 'name' => 'startTime']`, `virtual` without `sql`, `bad` `['type' => 'integer', 'name' =>
  'event-start']`): `testGetColumnsListsColumnsWithSql` → `start` and `startTime` (field `begins`), not `virtual`,
  not `event-start`; `testGetColumnFindsThePhysicalName` → `getColumn('tl_test', 'STARTTIME')` is
  `SqlColumn('startTime', 'begins', Integer)`, `getColumn('tl_test', 'begins')` is null;
  `testNoTableHasNoColumns` → `getColumns('')`, `getColumns(null)` are `[]`, `getColumn('', 'start')` is null.
- [ ] **Step 2: Run** — `make test tests/Util/DcaHelperTest.php`. Expected: FAIL (classes/methods missing).
- [ ] **Step 3: Implement** — a declaration word is the leading `/^\s*([a-z]+)/i`, lowercased; integer words
  `tinyint smallint mediumint int integer bigint`, date words `date datetime timestamp time year`. A string `sql` and
  an array `sql`'s `columnDefinition` are read by that word; otherwise the array's `type`: `smallint integer bigint
  boolean` → Integer, `date date_immutable datetime datetime_immutable datetimetz datetimetz_immutable time
  time_immutable` → Date, any other type → Other. The physical name is an array `sql`'s `name` or the field key.
- [ ] **Step 4: Run** — same command. Expected: PASS.
- [ ] **Step 5: Commit** — `feat(util): resolve a DCA table's physical columns and their SQL kind`.

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
  `repeat_end_field` (`?string`, null; physical names), `overlap`, `use_recurrence` (`bool`, false);
  `start_at`/`stop_at` unchanged; `has_extended_events` removed. Model columns: `elm_periodStartField`,
  `elm_periodStopField`, `elm_overlap`, `elm_useRecurrence`, `elm_recurringField`, `elm_recurrencesField`,
  `elm_repeatEndField` (Task 4 adds them).
- Produces predicate options: `start`, `stop` (`int|DateTimeInterface`, required, normalized to int — unchanged);
  `start_field` (`string`, required); `stop_field` (`?string`, null); `overlap` (`bool`, false);
  `recurring_field`, `recurrences_field`, `repeat_end_field` (`?string`, null; recurrence applies when
  `recurring_field` is set, and then the other two are required — a normalizer throws `InvalidOptionsException`
  otherwise); `cast_fields` (`list<string>`, []: the columns read as `CAST(col AS SIGNED)`).
  `has_extended_events` is removed. Parameters `start`, `stop`.

The condition is one `$builder->where()` string, with `n(x)` = `$builder->column(x)`, wrapped as
`CAST(… AS SIGNED)` when `x` is in `cast_fields`:

```
n(s) IS NOT NULL AND n(s) <> 0[ AND <end_ok>] AND ((<main branch>)[ OR (<recurrence branch>)])
<end_ok>, with stop_field:                 (n(e) IS NULL OR n(e) = 0 OR n(e) >= n(s))
main branch, no overlap or no stop_field:  n(s) BETWEEN :start AND :stop
main branch, overlap and stop_field:       n(s) <= :stop AND <e> >= :start
<e>:                CASE WHEN n(e) IS NULL OR n(e) = 0 THEN n(s) ELSE n(e) END
recurrence branch:  n(r) IS NOT NULL AND n(r) <> 0 AND n(s) <= :stop AND (COALESCE(n(c), 0) = 0 OR (n(c) > 0 AND n(re) >= :start))
```

`buildEffect()` order: (1) the column checks — start; end if set; the three recurrence columns if `use_recurrence`
(and only then) — via `DcaHelper::getColumn($context->list->dc, …)`; (2) C17: `start_at` and `stop_at` both set
and `start_at > stop_at` → `FilterException`; (3) the intersection with the value as today (`abort()` when empty);
(4) `addPredicate(CalendarCurrentPredicate::class, [...], TableAliasRegistry::ALIAS_MAIN)` with the physical names,
`stop_field`/`overlap` only when an end column is set, the recurrence fields only with `use_recurrence`,
`cast_fields` = the checked columns of kind `Other`. Messages (new keys; `%role%` =
`trans('errors.calendar.role.<role>')`, dates as `Y-m-d H:i`):

| Key | en | de |
|---|---|---|
| `errors.calendar.no_column` | `No column is selected for the %role%.` | `Für %role% ist keine Spalte ausgewählt.` |
| `errors.calendar.unknown_column` | `The column "%column%" selected for the %role% does not exist in %table%.` | `Die für %role% ausgewählte Spalte „%column%“ gibt es in %table% nicht.` |
| `errors.calendar.date_column` | `The column "%column%" selected for the %role% is an SQL date column; the filter reads Unix timestamps only.` | `Die für %role% ausgewählte Spalte „%column%“ ist eine SQL-Datumsspalte; der Filter liest nur Unix-Zeitstempel.` |
| `errors.calendar.inverted_period` | `The configured end of the period (%stop%) lies before its start (%start%).` | `Das konfigurierte Ende des Zeitraums (%stop%) liegt vor seinem Beginn (%start%).` |
| `errors.calendar.role.start` / `.stop` / `.recurring` / `.recurrences` / `.repeat_end` | `start` / `end` / `recurring flag` / `number of recurrences` / `repeat end` | `den Beginn` / `das Ende` / `die Wiederholung` / `die Anzahl der Wiederholungen` / `das Wiederholungsende` |

- [ ] **Step 1: Write the failing predicate tests**, each asserting `getConditions() === [$expectedSql]` with the
  exact one-line string of the shape above and `getParameters() === ['start' => 100, 'stop' => 200]`:
  - `testTheStartLiesInThePeriod` (`start_field` `startTime`):
    `` `main.startTime` IS NOT NULL AND `main.startTime` <> 0 AND ((`main.startTime` BETWEEN :start AND :stop)) ``
  - `testAnEndBeforeTheStartNeverMatches` (`stop_field` `endTime`, no overlap) → `<end_ok>` present
  - `testOverlapComparesTheEffectiveEnd` (`stop_field` `endTime`, `overlap` true)
  - `testOverlapNeedsAnEndColumn` (`overlap` true, no `stop_field`) → same string as the first test
  - `testRecurrenceAddsContaosApproximation` (`recurring`, `recurrences`, `repeatEnd`): the string ends
    `` OR (`main.recurring` IS NOT NULL AND `main.recurring` <> 0 AND `main.startTime` <= :stop AND
    (COALESCE(`main.recurrences`, 0) = 0 OR (`main.recurrences` > 0 AND `main.repeatEnd` >= :start)))) `` (one line)
  - `testOverlapAndRecurrenceTogether` (end, overlap, recurrence) → `<end_ok>` outside both branches
  - `testCastColumnsAreReadAsNumbers` (`start_field` `s`, `stop_field` `e`, both in `cast_fields`, overlap): every
    `n(s)` is `` CAST(`main.s` AS SIGNED) ``, every `n(e)` `` CAST(`main.e` AS SIGNED) ``, also in `n(e) >= n(s)`
  - `testParametersAreBoundAsIntegers`: `build(null)->getTypes() === ['start' => ParameterType::INTEGER, 'stop' =>
    ParameterType::INTEGER]`
  - `testPartialRecurrenceIsRejected`: resolving `['start' => 1, 'stop' => 2, 'start_field' => 's',
    'recurring_field' => 'r']` through an `OptionsResolver` configured by the predicate throws
    `InvalidOptionsException`
- [ ] **Step 2: Write the failing element tests** — `setUp()` calls `stubDca('tl_test', …)` (`StubsContaoDcaTrait`):
  `startTime`/`endTime` `"bigint(20) NULL"`, `recurring` `['type' => 'boolean']`, `recurrences` `"smallint(5)
  unsigned NOT NULL default 0"`, `repeatEnd` `"int(10) unsigned NOT NULL default 0"`, `startStr` `"varchar(10)
  NULL"`, `day` `"date NULL"`, `begins` `['type' => 'integer', 'name' => 'physical']`, `virtual` without `sql`.
  `BASE` becomes the new keys with `start_field` `startTime`. Options are read from the resolved proposition (unused
  ones are `null`):
  - `testTheTransformReadsTheColumns`: a row with all seven columns → the config keys; empty pickers → `null`.
  - `testThePredicateGetsTheColumns`: with end and recurrence → `start_field`, `stop_field`, `overlap`, the three
    recurrence fields, `cast_fields` `[]`, proposition `targetAlias` `main`.
  - `testThePredicateRunsOnTheMainTable`: a context whose filter is `withTargetAlias('archive')` (forced) →
    `targetAlias` `main`.
  - `testThePhysicalNameIsChecked`: `start_field` `physical` passes; `start_field` `begins` →
    `errors.calendar.unknown_column`.
  - `testOverlapIsOnlyPassedWithAnEndColumn`: `overlap` true, no `stop_field` → `overlap` false, `stop_field` null.
  - `testRecurrenceIsOnlyPassedWhenSwitchedOn`: `use_recurrence` false with stale pickers `nope` → no failure,
    recurrence options null.
  - `testOtherColumnsAreCast`: `start_field` `startStr` → `cast_fields` `['startStr']`.
  - `testAnEmptyStartIsBroken` → `FilterException`, message `errors.calendar.no_column`.
  - `testAnUnknownColumnIsBroken` (`nope` and `virtual`) → `errors.calendar.unknown_column`.
  - `testADateColumnIsBroken` (`day`) → `errors.calendar.date_column`.
  - `testRecurrenceWithAMissingColumnIsBroken` (`use_recurrence` true, `recurring` and `recurrences` set,
    `repeat_end_field` null) → `errors.calendar.no_column`.
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
- Create: `src/Contao/DataContainer/FilterListTableResolver.php`,
  `src/Contao/Callback/FlareFilter/CalendarColumnsCallbacks.php`
- Modify: `contao/dca/tl_flare_filter.php`, `src/Filter/Element/CalendarCurrentFilterElement.php` (`buildDca()`),
  `contao/languages/en/tl_flare_filter.php`, `contao/languages/de/tl_flare_filter.php`,
  `src/Model/DocumentsFilterModelTrait.php`, `tests/DependencyInjection/ServicesConfigTest.php`
- Test: `tests/Filter/Element/CalendarCurrentFilterElementTest.php`,
  `tests/Contao/DataContainer/FilterListTableResolverTest.php`,
  `tests/Contao/Callback/FlareFilter/CalendarColumnsCallbacksTest.php`

**Interfaces:**
- Consumes: Task 2 `DcaHelper::getColumns()`.
- Produces: `final readonly class FilterListTableResolver { __construct(Connection $connection, ListDriverRegistry
  $drivers); resolve(int $filterId): ?string }` (`@internal`) — `SELECT l.type, l.dc FROM tl_flare_filter f INNER
  JOIN tl_flare_list l ON l.id = f.pid WHERE f.id = ?`, then the list driver's `resolveDcTable($type, ['dc' =>
  $dc], $drivers->getAttribute($type)?->attributes ?? [])`, as `ListCompoundFactory` does; null for a missing row,
  list or driver, or an empty table.
  `final class CalendarColumnsCallbacks` (`@internal`), constructor `(Connection $connection,
  FilterListTableResolver $tables, ?bool $contao5 = null)` (null → `Env::isContao5()`), method
  `getColumnOptions(DataContainer $dc): array` registered as `#[AsCallback(FilterContainer::TABLE_NAME,
  'fields.<picker>.options')]` for each of the five pickers: `[physical name => 'table.field']` for the columns of
  kind `Integer` or `Other` of `resolve((int) $dc->id)`, `[]` without a table. Task 5 adds the prefill methods.

DCA fields (static, `exclude` true): `elm_periodStartField` (select, `mandatory`, `includeBlankOption`, `chosen`,
`alwaysSave`, `tl_class` `w50 clr`), `elm_periodStopField` (select, `includeBlankOption`, `chosen`, `alwaysSave`,
`submitOnChange`, `w50`), the three recurrence pickers (as the start picker, `w50`), all `sql`
`"varchar(64) NOT NULL default ''"`; `elm_overlap` (checkbox, `alwaysSave`, `cbx w50`) and `elm_useRecurrence`
(checkbox, `alwaysSave`, `submitOnChange`, `cbx w50 clr`), `sql` `['type' => 'boolean', 'default' => false]`.
`elm_hasExtendedEvents` is removed from the DCA (C1, C12).

`buildDca()`: palette
`{date_start_legend},elm_configureStart;{date_stop_legend},elm_configureStop;{date_columns_legend},elm_periodStartField,elm_periodStopField,elm_useRecurrence`
with `elm_overlap` after `elm_periodStopField` when `$context->filterModel?->elm_periodStopField` is not empty;
`selector('elm_useRecurrence', 'elm_recurringField,elm_recurrencesField,elm_repeatEndField')`. The
`elm_startAt`/`elm_stopAt` handling stays.

Labels: `elm_periodStartField` en `['Start column', 'The column holding the start as a Unix timestamp.']` de
`['Spalte Beginn', 'Die Spalte, die den Beginn als Unix-Zeitstempel enthält.']`; `elm_periodStopField` en
`['End column', 'The column holding the end as a Unix timestamp. Leave empty if entries are points in time; an entry
ending before it starts is never shown.']` de `['Spalte Ende', 'Die Spalte, die das Ende als Unix-Zeitstempel
enthält. Leer lassen, wenn Einträge Zeitpunkte sind; ein Eintrag, der vor seinem Beginn endet, wird nie
angezeigt.']`; `elm_overlap` en `['Overlap', 'Also match entries that end within the period or span it, not only
those that start within it.']` de `['Überlappung', 'Auch Einträge finden, die im Zeitraum enden oder ihn
überspannen, nicht nur solche, die in ihm beginnen.']`; `elm_useRecurrence` en `['Consider recurrence', 'Also match
recurring entries whose series reaches into the period, by Contao\'s recurrence model.']` de `['Wiederholungen
berücksichtigen', 'Auch wiederkehrende Einträge finden, deren Serie in den Zeitraum reicht (Wiederholungsmodell von
Contao).']`; `elm_recurringField` en `['Recurring column', 'The column marking an entry as recurring.']` de
`['Spalte Wiederholung', 'Die Spalte, die einen Eintrag als wiederkehrend kennzeichnet.']`; `elm_recurrencesField`
en `['Recurrences column', 'The column holding the number of recurrences (0 = unlimited).']` de `['Spalte Anzahl
Wiederholungen', 'Die Spalte mit der Anzahl der Wiederholungen (0 = unbegrenzt).']`; `elm_repeatEndField` en
`['Repeat end column', 'The column holding the end of the last recurrence as a Unix timestamp.']` de `['Spalte
Wiederholungsende', 'Die Spalte, die das Ende der letzten Wiederholung als Unix-Zeitstempel enthält.']`;
`date_columns_legend` en `Date columns` de `Datumsspalten`. Remove the `elm_hasExtendedEvents` labels and model
property; add the seven properties to `DocumentsFilterModelTrait`.

- [ ] **Step 1: Write the failing tests**
  - Element (`applyDca()` as today): `testThePaletteListsTheColumnPickers` (no end picker) → the palette above,
    without `elm_overlap`; `testOverlapAppearsWithAnEndColumn` (`elm_periodStopField` `endTime`) →
    `…elm_periodStopField,elm_overlap,…`; `testRecurrencePickersAreItsSubpalette` → `__selector__` contains
    `elm_useRecurrence`, `subpalettes` maps it to the three pickers; replace `testThePaletteHasNoLimitLegend`.
  - Resolver (mocked `Connection`, a `ListDriverRegistry` with a driver whose `resolveDcTable()` returns a fixed
    table and one returning `$config['dc']`): `testAFixedTableDriverWinsOverAnEmptyDc` → the fixed table;
    `testTheStoredDcIsUsed`; `testNoRowNoListOrNoDriverIsNull`.
  - Callbacks (`StubsContaoDcaTrait` for `tl_test` with `startTime` `"bigint(20) NULL"`, `begins` `['type' =>
    'integer', 'name' => 'physical']`, `day` `"date NULL"`, `virtual` without `sql`):
    `testOptionsArePhysicalNames` → `['physical' => 'tl_test.begins', 'startTime' => 'tl_test.startTime']`, no
    `day`, no `virtual`; `testNoTableNoOptions` → `[]`.
- [ ] **Step 2: Run** — the three test files one by one. Expected: FAIL.
- [ ] **Step 3: Implement** the fields, `buildDca()`, the resolver, the options callback, labels and model docs.
- [ ] **Step 4: Run** — `make test`, `make phpstan`. Expected: PASS, no new errors.
- [ ] **Step 5: Commit** — `feat(backend): column pickers, overlap and recurrence for the calendar filter`.

---

### Task 5: One-time prefill, written with the type (C9, C14)

**Files:**
- Modify: `src/Contao/Callback/FlareFilter/CalendarColumnsCallbacks.php`
- Test: `tests/Contao/Callback/FlareFilter/CalendarColumnsCallbacksTest.php`

**Interfaces:**
- Consumes: Task 4 `CalendarColumnsCallbacks`, `FilterListTableResolver::resolve()`; Task 2 `DcaHelper::getColumn()`;
  `CalendarCurrentFilterElement::TYPE`.
- Produces: `#[AsCallback(FilterContainer::TABLE_NAME, 'config.onbeforesubmit')] onBeforeSubmit(array $values,
  DataContainer $dc): array`; `#[AsCallback(FilterContainer::TABLE_NAME, 'fields.type.save', priority: -1000)]
  onSaveType(mixed $value, DataContainer $dc): mixed` (returns `$value`).

The prefill of a row: each picker = its event column (`startTime`, `endTime`, `recurring`, `recurrences`,
`repeatEnd`) when `DcaHelper::getColumn($table, $name)` exists and is not `Date`, else `''`; `elm_useRecurrence` 1
when all three recurrence pickers are set, else 0; `elm_overlap` 0. A row needs it when the new type is
`flare_calendar_current` and the stored one (`SELECT type FROM tl_flare_filter WHERE id = ?`) is not.
`onBeforeSubmit()` (Contao 5): when `$values['type']` makes the row need it, returns `$values + $prefill` (submitted
values win), else `$values`. `onSaveType()` acts only without Contao 5: when the row needs it,
`update('tl_flare_filter', $prefill, ['id' => $id])`. Before Step 3, confirm in Contao 4.13.55's `DC_Table::save()`
(GitHub) that each field is written right after its save callbacks and that 4.13 has no `onbeforesubmit_callback`;
note it in the commit body.

- [ ] **Step 1: Write the failing tests** (mocked `Connection`, a resolver over a mocked connection, a
  `DataContainer` mock with `__get('id')` → 7; `StubsContaoDcaTrait` for the list tables):
  - `testAnEventTableIsPrefilledOnSubmit` (Contao 5): `onBeforeSubmit(['type' => 'flare_calendar_current'], $dc)`
    returns the type, the five event columns, `elm_useRecurrence` 1, `elm_overlap` 0; no update.
  - `testSubmittedValuesWin`: `elm_periodStopField` `''` and `elm_overlap` 1 submitted with the type are kept.
  - `testNoTypeChangeLeavesTheValues`: `$values` without `type`, or stored type already calendar → unchanged.
  - `testOnContao5TheSaveCallbackWritesNothing` → `onSaveType()` returns the value, no update.
  - `testOnContao413TheSaveCallbackWrites` (`contao5` false) → one update with the seven columns, `['id' => 7]`.
  - `testATableWithoutEventColumnsGetsEmptyPickers` → all `''`, `elm_useRecurrence` 0.
  - `testRecurrenceStaysOffWithoutAllItsColumns` (only `startTime`, `recurring`) → those two, recurrence 0.
  - `testADateTypedEventColumnIsNotPrefilled` (`startTime` `"datetime NULL"`) → start `''`.
  - `testAListWithoutTableGetsEmptyPickers` (resolver null) → all `''`, no DCA loaded.
  - `testARowReturningToTheTypeIsPrefilledAfresh` (stored `flare_published`, stale pickers) → all seven columns.
- [ ] **Step 2: Run** — `make test tests/Contao/Callback/FlareFilter/CalendarColumnsCallbacksTest.php`.
  Expected: FAIL.
- [ ] **Step 3: Implement** as specified.
- [ ] **Step 4: Run** — that file, then `make test`. Expected: PASS.
- [ ] **Step 5: Commit** — `feat(backend): prefill the calendar column pickers once, with the type change`.

---

### Task 6: `CalendarColumnsMigration` (C8, C12)

**Files:**
- Create: `src/Migration/CalendarColumnsMigration.php`
- Modify: `tests/Migration/FakeFilterTableTrait.php` (`addRow()` defaults: `elm_hasExtendedEvents` `''`, the
  pickers `''`, `elm_overlap`/`elm_useRecurrence` `0`), `tests/DependencyInjection/ServicesConfigTest.php`,
  `AGENTS.md` (the `src/Migration/` entry)
- Test: `tests/Migration/CalendarColumnsMigrationTest.php`

**Interfaces:**
- Produces: `CalendarColumnsMigration extends AbstractMigration`, `__construct(Connection $connection)`, marker
  column `calendarColumnsFilled`.

Pattern of `RetireCalendarLimitMigration` (read it): frozen constants `TABLE`, `MARKER`, `TYPE`; `REQUIRED_COLUMNS`
= `type`, `elm_hasExtendedEvents`, the five pickers, `elm_overlap`, `elm_useRecurrence`. `shouldRun()`: false
without the table or a required column; true without the marker; else whether pending rows exist. No dependency on
the backfill or the retire migration. `run()`: adds the marker (`CHAR(1) NOT NULL DEFAULT '0'`), switches its
default to `'1'`, and per pending calendar row writes in one statement with its marker — unless its
`elm_periodStartField` is already set (then only the marker) — `startTime`, `endTime`, `elm_useRecurrence` 1,
`recurring`, `recurrences`, `repeatEnd`, `elm_overlap` = `(int) (bool) (int) elm_hasExtendedEvents`; marks the rest
done. Result: `"{name} ({n} calendar filters set to the event columns)"`. The class docblock states the guard and
that the deletes run only after all migrations and schema updates (C12). `AGENTS.md`: add the migration (marker,
waits for its columns, guard).

- [ ] **Step 1: Write the failing tests** (pattern of `RetireCalendarLimitMigrationTest`):
  - `testACalendarRowGetsTheEventColumns` (`elm_hasExtendedEvents` `''`) → one update with the seven values, overlap
    0, marker `'1'`.
  - `testExtendedEventsBecomeOverlap` (`'1'`) → `elm_overlap` 1.
  - `testAPresetStartPickerIsOnlyMarked` (`elm_periodStartField` `begins`) → update with the marker only.
  - `testAMarkedRowBesideAPendingOneIsUntouched`: row 1 marked with edited pickers, row 2 pending → only row 2
    updated.
  - `testOtherTypesAreMarkedDone` → no per-row update, all markers `'1'`.
  - `testItWaitsForItsColumns`: without `elm_hasExtendedEvents` (only `hasExtendedEvents`, before the rename), or
    without `elm_periodStartField` (before the schema update) → false.
  - `testItDoesNotWaitForTheOtherMigrations`: backfill pending, no `exclusive`/`outputs` → still true.
  - `testShouldRunGoesQuietAfterARun`, `testRowsCreatedAfterwardsAreNeverPending`.
  - `testTheDeleteRunLeavesItQuiet`: after a run, drop the marker and `elm_hasExtendedEvents` → `shouldRun()` false.
- [ ] **Step 2: Run** — `make test tests/Migration/CalendarColumnsMigrationTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement** as specified.
- [ ] **Step 4: Run** — `make test`, `make phpstan`, `mago lint --minimum-fail-level note`. Expected: PASS, clean.
- [ ] **Step 5: Commit** — `feat(migration): fill the calendar filters' column pickers`.

---

### Task 7: Docs (docs worktree, branch `docs/main`)

**Files:**
- Modify: `docs/docs/reference/filter-elements.md` (table row; *Calendar time window*: columns, overlap,
  recurrence, empty values, an end before the start, Unix timestamps only, broken on a missing or date column or an
  inverted period), `docs/docs/reference/predicates.md` (`CalendarCurrentPredicate`'s options with an old-to-new
  example: `['start' => …, 'stop' => …, 'has_extended_events' => true]` → `['start' => …, 'stop' => …,
  'start_field' => 'startTime', 'stop_field' => 'endTime', 'overlap' => true, 'recurring_field' => 'recurring',
  'recurrences_field' => 'recurrences', 'repeat_end_field' => 'repeatEnd']`), `docs/docs/migrating-from-v0.1.md`
  (the migration fills the event columns and turns *Extend events* into *Overlap*; Goal 6's behavior changes, the
  custom-table examples included; a general note: after the upgrade, admins check the per-field permissions of
  non-admin user groups — new fields are granted to no one)

- [ ] **Step 1: Write** the changes from the spec (Intent, Goal 6, C3–C5, C7, C9–C11, C17, Docs) in the docs' voice.
- [ ] **Step 2: Check** — `npm run build` in `docs/` if `node_modules` exists. Expected: no broken links.
- [ ] **Step 3: Commit** in `docs/` — `docs: calendar filter reads picked columns`.

---

### Task 8: Sandbox verification (controller, C16)

Run by the controller session after Tasks 2–7, on this branch, in the sandbox prepared in Task 1.

- [ ] **Step 1: Migrate** — `ddev console cache:clear`, `ddev console contao:migrate --no-interaction` (no deletes).
  Expected: the migration reports its calendar filters; `CC fixed` has `elm_overlap` 0, `CC fixed overlap` 1, all
  three and filter 10 hold the event columns with recurrence on.
- [ ] **Step 2: Untouched filters first** — render `CC fixed` and `CC fixed overlap` into `after/`, before the
  driver changes anything. Expected: as `before/`, without `CC reversed` and `CC reversed weekly` (Goal 6).
- [ ] **Step 3: Driver** — run it on `CC driver` (phase `after`, `elm_overlap`); `diff -r before after`. Expected:
  the only differences are missing `CC reversed` / `CC reversed weekly` lines.
- [ ] **Step 4: Test table** — a sandbox DCA `contao/dca/tl_flare_period_check.php`: `config` with `dataContainer`
  `DC_Table` and `sql.keys.id` `primary`; fields `id` `"int(10) unsigned NOT NULL auto_increment"`, `tstamp`,
  `title` `"varchar(255) NOT NULL default ''"`, `startInt`/`endInt` `"int(10) unsigned NULL"`, `startStr`/`endStr`
  `"varchar(10) NULL"`, `recFlag` `"char(1) NULL"`, `recCount` `"int(10) NULL"`, `recEnd` `"int(10) unsigned
  NULL"`, `day` `"date NULL"`. `ddev console cache:clear`, `contao:migrate`, then rows (str columns hold the same
  digits; `NULL` stays `NULL`; recurrence columns `NULL` unless given):

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

  Lists (`flare_generic_dc` on the table), their filters created in the backend (this also exercises the pickers),
  each on its own page as in Task 1: `int` (startInt/endInt), `str` (startStr/endStr), `rec` (startInt/endInt,
  recurrence on with the three `rec*` columns). Run the driver on each with a cases file of the periods below.
  Expected, for `int` and `str` alike (the `rec-*` rows' own date lies outside every period but the open one):

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
- [ ] **Step 5: Backend** (in a browser; ask the user for a backend login via `request_secret` if needed):
  - a new filter switched to the calendar type on `CC driver`'s list → pickers `startTime`, `endTime`, recurrence on
    with its three columns, *Overlap* off; on the `int` list → all pickers empty, recurrence off;
  - rejected submit: on a new filter, open the expert legend, enter an invalid alias (e.g. with spaces), **then**
    switch the type → the error shows; the database row keeps its old type and empty pickers;
  - clear the end picker on a calendar filter, save, reload → stays empty, *Overlap* hidden;
  - the test table's pickers offer `startInt`, `startStr`, …, not `day`;
  - three lists of their own on the test table, each with one filter set via SQL — start picker `day`, start
    picker `nope`, stop `2030-03-01` before start `2030-03-31` — and a page each → the filter overview marks each
    broken with Task 3's messages.
- [ ] **Step 6: Failed view without debug** — set `APP_ENV=prod` in the sandbox's `.env.local`,
  `ddev console cache:clear --env=prod`, `curl` the three broken pages → `flare-list__failed`; restore `.env.local`
  and clear the cache.
- [ ] **Step 7: Reruns** — `contao:migrate` → the migration does not run. In the backend, clear `CC fixed`'s end
  picker and switch recurrence off; render it → `CC one day`, `CC reversed`, `CC several days` (without an end
  column C5's end check does not apply), saved as `after/fixed-edited.txt`. `contao:migrate --with-deletes
  --no-interaction` → drops `calendarColumnsFilled` and `elm_hasExtendedEvents`, the migration stays quiet;
  `SELECT` the filter → end picker `''`, recurrence 0; render it → as `after/fixed-edited.txt`.
- [ ] **Step 8: Report and clean up** — report each step's outcome, with any difference shown. Offer to restore:
  the snapshot `flare-calendar-columns-before` holds the database only and pairs with `v0.2`'s code (on this branch
  it needs `contao:migrate` again); the sandbox's `contao/dca/tl_flare_period_check.php`, the `calendar_check`
  template and `var/flare-calendar-check/` stay until removed.
