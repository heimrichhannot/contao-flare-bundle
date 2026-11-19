# Time-Window Rename Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or
> superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The filter element `flare_calendar_current` becomes `flare_time_window` — key, classes, messages, stored
rows, docs — without breaking v0.1 or v0.2-dev databases.

**Architecture:** Classes are moved and renamed; the element's `TYPE` constant carries the new key into the registry,
the named events, the service alias and the label catalogues. `RenameTimeWindowTypeMigration` rewrites stored rows
byte-exactly; the three older migrations that match the type accept both keys, so the migrations' order does not
matter.

**Tech Stack:** PHP 8.2+, Symfony, Contao 4.13/5.x, Doctrine DBAL, PHPUnit 9, Mago; the Contao 5.3 sandbox.

**Spec:** `dev/superpowers/specs/2026-11-19-time-window-rename-design.md`. Read it first.

## Global Constraints

- New key `flare_time_window`; classes `TimeWindowFilterElement`, `TimeWindowPredicate`, `TimeWindowCallbacks`;
  message keys `errors.time_window.*` (wording unchanged); service alias `flare.filter_element.flare_time_window`.
- Unchanged: the config keys, DCA field names and behavior of the element; the names of `RetireCalendarLimitMigration`
  and `CalendarColumnsMigration`; historical documents under `dev/superpowers/`; the archived v0.1 doc pages.
- Each older migration keeps its own frozen key list `['flare_calendar_current', 'flare_time_window']` (no shared
  constant).
- Byte-exact type comparison in SQL: `CAST(type AS BINARY) = ?` (MySQL 5.7/8, MariaDB).
- `declare(strict_types=1);`, PER style, 120 chars; `make test <one path>` (one path per run), `make test`,
  `make phpstan`, `mago lint --minimum-fail-level note` before each commit; no `Co-Authored-By` trailer; never
  push. Docs commit in the `docs/` worktree (branch `docs/main`).
- Branch: `v0.2-calendar-columns`.

## Review Focus

1. A custom type that differs only in case (`Flare_Calendar_Current`) is never converted — Task 3
   `testTheComparisonIsByteExact`; sandbox Task 5.
2. A row the newer migrations already processed and an editor changed keeps everything but `type` — Task 3
   `testOnlyTheTypeChanges`.
3. The rename running first on a v0.1 row leaves the other migrations' results as without it — Task 3
   `testTheRenameFirstEndsLikeTheOtherMigrationsAlone`.
4. A version restored from before the rename gets its key renamed by the next `contao:migrate` — sandbox Task 5.
5. The backend type selector still shows *Zeitfenster* / *Time window* — Task 1 `testTheLabelCataloguesUseTheNewKey`.

---

### Task 1: Rename the element, predicate and callbacks

**Files:**
- Move (`git mv`): `src/Filter/Element/CalendarCurrentFilterElement.php` → `TimeWindowFilterElement.php`,
  `src/Filter/Predicate/CalendarCurrentPredicate.php` → `TimeWindowPredicate.php`,
  `src/Contao/Callback/FlareFilter/CalendarColumnsCallbacks.php` → `TimeWindowCallbacks.php`, and their tests
  `tests/Filter/Element/CalendarCurrentFilterElementTest.php`, `tests/Filter/Predicate/CalendarCurrentPredicateTest.php`,
  `tests/Contao/Callback/FlareFilter/CalendarColumnsCallbacksTest.php` → `TimeWindow…Test.php`
- Modify: every reference (grep `CalendarCurrent`, `CalendarColumnsCallbacks`, `errors.calendar` in `src/`,
  `tests/`, `config/`, `contao/`), `translations/flare.en.yaml`, `translations/flare.de.yaml`,
  `translations/flare_filter_element.en.php`, `translations/flare_filter_element.de.php`,
  `tests/DependencyInjection/ServicesConfigTest.php`, the comment in `tests/Filter/Value/DateRangeValueTest.php:72`,
  `AGENTS.md` (current-code mentions of the old names and key; not the migration descriptions of historical rows)
- Test: `tests/Translation/FilterElementLabelsTest.php` (create)

**Interfaces:**
- Produces: `TimeWindowFilterElement::TYPE = 'flare_time_window'`; `TimeWindowPredicate`; `TimeWindowCallbacks`
  (same methods and attributes as before); message keys `errors.time_window.no_column`, `.unknown_column`,
  `.date_column`, `.inverted_period`, `.role.start|stop|recurring|recurrences|repeat_end`.

- [ ] **Step 1: Write the failing tests**
  - In the renamed element test: `testTheTypeIsTheTimeWindow` → `TimeWindowFilterElement::TYPE ===
    'flare_time_window'`; the existing message assertions expect `errors.time_window.*`.
  - `FilterElementLabelsTest::testTheLabelCataloguesUseTheNewKey`: `require` each of
    `translations/flare_filter_element.{en,de}.php` → its array has `'flare_time_window' => 'Time window'` /
    `'Zeitfenster'` and no key `flare_calendar_current`.
- [ ] **Step 2: Run** — `make test tests/Translation/FilterElementLabelsTest.php`. Expected: FAIL.
- [ ] **Step 3: Rename** the files with `git mv`, the classes, the constant and all references; rename the YAML
  message group `calendar` to `time_window` in both languages.
- [ ] **Step 4: Run** — `make test`, `make phpstan`, `mago lint --minimum-fail-level note`; then
  `grep -rn "CalendarCurrent\|CalendarColumnsCallbacks\|errors\.calendar" src tests config contao translations`.
  Expected: PASS, clean, no grep hits.
- [ ] **Step 5: Commit** — `refactor: rename the calendar filter element to flare_time_window`.

---

### Task 2: The older migrations accept both keys

**Files:**
- Modify: `src/Migration/BackfillFilterValuesMigration.php` (`controlFor()`: `'flare_time_window'` beside
  `'flare_calendar_current'`), `src/Migration/RetireCalendarLimitMigration.php`,
  `src/Migration/CalendarColumnsMigration.php` (`TYPE` → `TYPES = ['flare_calendar_current', 'flare_time_window']`,
  matched with `in_array(…, true)`; docblocks say why: the rename migration may run first)
- Test: `tests/Migration/BackfillFilterValuesMigrationTest.php`, `tests/Migration/RetireCalendarLimitMigrationTest.php`,
  `tests/Migration/CalendarColumnsMigrationTest.php`

**Interfaces:**
- Produces: the three migrations treat a `flare_time_window` row exactly like a `flare_calendar_current` row.

- [ ] **Step 1: Write the failing tests** — one per migration, each a copy of an existing old-key case with the new
  key, asserting the same result:
  - Backfill: `testATimeWindowRowGetsTheDateRangeControl` (as the existing calendar case, type
    `flare_time_window`).
  - Retire: `testATimeWindowRowIsRetiredToo` (as `testAReadableLeftoverIsSwitchedOn`).
  - Columns: `testATimeWindowRowGetsTheEventColumns` (as `testACalendarRowGetsTheEventColumns`).
  The old-key tests stay.
- [ ] **Step 2: Run** — the three files one by one. Expected: the new tests FAIL.
- [ ] **Step 3: Implement** as specified.
- [ ] **Step 4: Run** — the three files, then `make test`. Expected: PASS.
- [ ] **Step 5: Commit** — `fix(migration): recognize the calendar filter under both keys`.

---

### Task 3: `RenameTimeWindowTypeMigration`

**Files:**
- Create: `src/Migration/RenameTimeWindowTypeMigration.php`
- Modify: `tests/Migration/FakeFilterTableTrait.php`, `tests/DependencyInjection/ServicesConfigTest.php`, `AGENTS.md`
  (the `src/Migration/` entry)
- Test: `tests/Migration/RenameTimeWindowTypeMigrationTest.php`

**Interfaces:**
- Produces: `RenameTimeWindowTypeMigration extends AbstractMigration`, `__construct(Connection $connection)`; frozen
  constants `TABLE = 'tl_flare_filter'`, `OLD = 'flare_calendar_current'`, `NEW = 'flare_time_window'`.
  `shouldRun()`: false without the table or a `type` column; else `fetchOne('SELECT 1 FROM tl_flare_filter WHERE
  CAST(type AS BINARY) = ? LIMIT 1', [OLD]) !== false`. `run()`: `executeStatement('UPDATE tl_flare_filter SET type
  = ? WHERE CAST(type AS BINARY) = ?', [NEW, OLD])`; result `"{name} ({n} filters renamed)"`.

The fake serves both statements: `fetchOne` with that SQL returns 1 when a row's `type` is strictly `===` the
parameter; `executeStatement` with that SQL sets matching rows' `type` (strict `===`), records the statement and
returns the count. Existing statements keep working.

- [ ] **Step 1: Write the failing tests**
  - `testOldRowsAreRenamed`: rows of `flare_calendar_current`, `flare_published` → first renamed, second untouched;
    `shouldRun()` false afterwards.
  - `testTheComparisonIsByteExact`: a row of `Flare_Calendar_Current` → `shouldRun()` false, untouched; the recorded
    statement contains `CAST(type AS BINARY)`.
  - `testOnlyTheTypeChanges`: an old-key row with `elm_periodStartField` `startStr`, `elm_periodStopField` `''`,
    `elm_useRecurrence` 0, no `elm_hasExtendedEvents` and no marker columns → after `run()` the row equals its
    former self except `type`.
  - `testQuietWithoutTableOrTypeColumn`: `$this->columns = null` → false; columns without `type` → false.
  - `testTheRenameFirstEndsLikeTheOtherMigrationsAlone`: a v0.1-shaped calendar row (columns as in
    `RetireCalendarLimitMigrationTest::COLUMNS` plus the seven picker columns and `elm_hasExtendedEvents`); run the
    real `BackfillFilterValuesMigration`, `RetireCalendarLimitMigration` and `CalendarColumnsMigration` in a loop
    while any `shouldRun()`, keep the row; reset the fake with the same row, run the rename first, then the same
    loop → the two rows are equal except `type` (`flare_calendar_current` vs. `flare_time_window`). Wire the real
    backfill as `RetireCalendarLimitMigrationTest::testAV01RowIsBackfilledFirstAndThenChecked` does.
- [ ] **Step 2: Run** — `make test tests/Migration/RenameTimeWindowTypeMigrationTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement** the migration and the fake's two statements.
- [ ] **Step 4: Run** — `make test`, `make phpstan`, `mago lint --minimum-fail-level note`. Expected: PASS, clean.
- [ ] **Step 5: Commit** — `feat(migration): rename stored calendar filters to flare_time_window`.

---

### Task 4: Docs (docs worktree, branch `docs/main`)

**Files:**
- Modify: `docs/docs/reference/filter-elements.md` (table row key), `docs/docs/reference/predicates.md`
  (`TimeWindowPredicate`, its anchor, and every inbound link to the old anchor, e.g. `migrating-from-v0.1.md:305`),
  `docs/docs/integrations/index.md` (key), `docs/docs/migrating-from-v0.1.md` and `docs/docs/removed-in-v0.2.md`

`migrating-from-v0.1.md` / `removed-in-v0.2.md`: the statements that every element's `::TYPE` string is unchanged
(`migrating-from-v0.1.md:26`, `removed-in-v0.2.md:201`) get the one exception `flare_calendar_current` →
`flare_time_window`, renamed in the database by `RenameTimeWindowTypeMigration`; code naming the type or the service
id (`huh.flare.filter_element.flare_calendar_current` → `flare.filter_element.flare_time_window`) uses the new key; a
v0.1 listener of the element's named events needs the event migration table as well, e.g.
`flare.filter_element.flare_calendar_current.palette` → `flare.filter_element.flare_time_window.dca`; restoring a
version or undo record from before the upgrade brings the old key back — don't; if it happened, `contao:migrate`
renames it again, but v0.1 settings are not recovered; troubleshooting: a backend filter panel with the old type
selected (kept in the user session) shows no filters — clear and reselect **Type**. Archived v0.1 pages stay.

- [ ] **Step 1: Write** the changes.
- [ ] **Step 2: Check** — `grep -rn "flare_calendar_current\|CalendarCurrent" docs/docs` shows only the historical
  mentions this task keeps; `npm run build` in `docs/` passes without broken links.
- [ ] **Step 3: Commit** in `docs/` — `docs: the calendar filter element is flare_time_window`.

---

### Task 5: Sandbox verification (controller)

Run by the controller session after Tasks 1–4 in `~/Kunden/ericges/contao-5.3-sandbox` (fixtures and scripts of the
calendar-columns plan in `var/flare-calendar-check/`).

- [ ] **Step 1: Custom type** — insert an unpublished filter in list 1 with `type` `Flare_Calendar_Current`.
- [ ] **Step 2: Migrate** — `ddev console cache:clear`, `ddev console contao:migrate --no-interaction`. Expected: the
  rename reports 10 filters (10–19); `Flare_Calendar_Current` untouched; no other migration runs.
- [ ] **Step 3: Same output** — run the driver on `CC driver` (phase `renamed`, `elm_overlap`) and render `CC fixed`,
  `CC fixed overlap`, the test-table lists and the broken lists; `diff -r` against `after/` and `table-*` → no
  difference; the broken lists still fail with their messages.
- [ ] **Step 4: Backend** (login as in the calendar-columns Task 8: password file + `curl` session cookie): the type
  selector shows *Zeitfenster*; restore a version of filter 11 from before the rename → its row has
  `flare_calendar_current` again; `contao:migrate` renames it, the list renders as before.
- [ ] **Step 5: Report**, and remove the custom-type row.
