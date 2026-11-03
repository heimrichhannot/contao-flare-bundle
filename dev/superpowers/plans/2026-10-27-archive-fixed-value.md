# Archive Filter Without Control: The Value Is the Restriction — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** An archive filter without a control works like the other choice filters: its `value` (chosen from all parent
archives) is the restriction; the parent whitelist and "whitelist for options only" only exist for controlled filters.

**Architecture:** `ArchiveFilterElement` branches on `$scope->filter->control === null` in every place that reads the
whitelist, so whitelist config from any source (transformer, `FilterFactory::create()`, listeners) is ignored without a
control. The backend element scope resolves the control (not the value) so the `value` widget knows which mode it is
in. The backfill migration turns legacy intrinsic whitelists into values, so no existing list widens.

**Tech Stack:** PHP 8.2, Contao 4.13/5.x, Doctrine DBAL, PHPUnit 9 (`make test`), PHPStan (`make phpstan`), Docusaurus
docs in the `docs/` worktree (branch `docs/main`, `npm run build`).

**Spec:** the "Decisions" section below (agreed in conversation on 2026-10-27; there is no separate spec file).

## Decisions

1. **Without a control** the archive filter's `value` is its fixed restriction: `pid IN (value)`, not intersected with
   any whitelist. Without a value it restricts nothing (like `DcaSelectFieldFilterElement`) and is reported as
   ineffective (`IneffectiveFilterContract`).
2. **Without a control** the `value` widget offers **all** parents:
   - static ptable: every record of the list DCA's `config.ptable`;
   - dynamic ptable: every record of every table in `SELECT DISTINCT ptable FROM {list dc}` (option (a)).
3. **Without a control** `whitelist_parents`, `group_whitelist_parents` and `use_whitelist_for_options_only` are
   **ignored by the element**, whatever their source, and hidden in the DCA.
4. **With a control** nothing changes: the whitelist provides the options and bounds the value
   (`value ∩ whitelist`), "options only" decides whether no selection means "whole whitelist" or "unrestricted".
5. The backend `ElementScope` resolves the control but not the value (an unreadable value must not block the form
   that repairs it).
6. Ineffective = no control **and** no value (fixes the bug that flagged filters with a fixed value).
7. Migration: a legacy intrinsic archive row (`intrinsic = 1`) gets `value := whitelist keys` — the old element always
   restricted an intrinsic archive filter to its whitelist, "options only" included. Static ptable: `{ptable}.{id}` from
   `elm_whitelistParents`; otherwise `{table}.{id}` from `elm_groupWhitelistParents`. Rows created on the unreleased
   v0.2 branch after the backfill are not covered (dev databases only, accepted).

## Global Constraints

- Migrations never drop or delete; SQL must run on MySQL 5.7/8 and MariaDB.
- `declare(strict_types=1);`, 4-space indent, 120-char lines; keep existing `§`/SPEC references in comments, add none.
- Commits: no `Co-Authored-By` trailer, never push. Docs commit separately in `docs/` (branch `docs/main`).
- Catch `\Exception`, never `\Throwable` (except the transformer resolvers).

## Review Focus

1. A controlled filter after its control was **removed** in the backend still carries a whitelist-only value: without
   a control its value now applies unbounded — expected, the editor sees the value field with all archives.
2. A value containing keys of a table that is not the static ptable (stale, or from a former dynamic config): those
   keys match nothing; if no key remains, `abort()` (empty list), never "no restriction".
3. Dynamic ptable whose entity table has **no rows yet**: the value widget offers nothing and shows no error.
4. A table name from `SELECT DISTINCT ptable` without a model class (custom table, no `TL_MODELS` entry): skipped, not
   an exception.
5. Backend scope with a control that cannot be resolved (unknown type): the scope falls back to "no control" (warning
   logged) and the widget offers all archives, instead of failing.

---

### Task 1: Backend scope resolves the control, not the value

**Files:**
- Modify: `src/Filter/Factory/FilterFactory.php:122-180` (`createFromFilterModel()`)
- Modify: `src/EventListener/Contao/ElementDcaListener.php` (`createElementScope()`)
- Test: `tests/Filter/FilterFactoryTest.php`

**Interfaces:**
- Produces: `FilterFactory::createFromFilterModel(FilterModel $filterModel, bool $withValue = true): Filter` — the
  control is always resolved (failing open as today); with `$withValue = false` the stored value is never read, so an
  unreadable value cannot throw. Replaces the parameter `withControlAndValue`.

- [ ] **Step 1: Rewrite the two tests that use `withControlAndValue: false`** (`testWithoutControlAndValueSkipsBoth`,
  around `tests/Filter/FilterFactoryTest.php:306-320`) into:
  - `testWithoutValueStillResolvesTheControl`: model with `control => self::BOOL_CONTROL`, `value => '1'`;
    `createFromFilterModel($model, withValue: false)` → `control?->type === self::BOOL_CONTROL`, `value === null`.
  - `testWithoutValueNeverReadsAnUnreadableValue`: model without control, `value => 'garbage'`;
    `createFromFilterModel(..., withValue: false)` does not throw, `value === null`.
  - `testWithoutValueAnUnknownControlDegradesToNoControl`: `control => 'gone'`, `withValue: false` → `control === null`,
    one warning logged (Review Focus 5).
- [ ] **Step 2: Run** `make test tests/Filter/FilterFactoryTest.php` — expect failures (unknown named parameter).
- [ ] **Step 3: Implement** the new signature: resolve the control in both cases; call `provideValue()` only when
  `$withValue`. In `ElementDcaListener::createElementScope()` pass `withValue: false` and fix its docblock (the backend
  scope knows the control, which decides the element's options, but resolves no value). Update the `@param` docblock
  ("False to skip reading the stored value, e.g. for backend option lists; the control is always resolved").
- [ ] **Step 4: Run** `make test` — expect all green.
- [ ] **Step 5: Commit** `refactor!: resolve the control in the backend filter scope, skip only the value`

### Task 2: Runtime — without a control the value is the restriction

**Files:**
- Modify: `src/Filter/Element/ArchiveFilterElement.php` (`buildContext()`, `getIneffectiveReason()`, docblocks)
- Modify: `translations/flare.en.yaml`, `translations/flare.de.yaml`
- Test: `tests/Filter/Element/ArchiveFilterElementTest.php`, `tests/DataContainer/IneffectiveFilterInspectorTest.php`

**Interfaces:**
- Consumes: `ElementScope::$filter->control` (null = no control).
- Produces: translation key `filter.info.ineffective.archive_without_value` (replaces
  `filter.info.ineffective.whitelist_for_options_only`, which is removed).

- [ ] **Step 1: Make the existing selection tests controlled.** Add a test helper
  `controlled(ElementScope $scope): ElementScope` that returns a new `ElementScope` whose filter is
  `$scope->filter->withControl(new ControlBinding($this->createMock(FilterControlInterface::class), 'flare_choice', new ComponentConfig()))`.
  `buildStatic()` / `buildDynamic()` get `bool $controlled = true` and build the context through
  `createFilterContextFactory()->create($scope, $value)` with the controlled scope. All existing whitelist tests keep
  their assertions (they describe the controlled mode).
- [ ] **Step 2: Write the uncontrolled tests** (static ptable `tl_news` → `tl_news_archive`, dynamic `tl_content`):
  - `testWithoutControlTheValueAppliesUnboundedByTheWhitelist`: config whitelist `[3]`, options-only `false`,
    value refs `tl_news_archive.5`, `tl_news_archive.9` → one `ArchivePredicate` with `parent_ids === [5, 9]`.
  - `testWithoutControlAndValueNothingIsRestricted`: whitelist `[3, 5]`, options-only `false`, no value →
    `propositions === []`.
  - `testWithoutControlForeignTableKeysAbort`: value `tl_calendar.5` on `tl_news` → `AbortFilteringException`.
  - `testDynamicWithoutControlGroupsTheValueByTable`: groups config present, value `tl_news.1`, `tl_calendar.7`,
    `tl_calendar.8` → one `BelongsToRelationPredicate` with `parent_groups === [['table' => 'tl_news', 'ids' => [1]],
    ['table' => 'tl_calendar', 'ids' => [7, 8]]]` and `submitted_data === null`.
  - Replace `testWhitelistForOptionsOnlyWithoutAControlIsIneffective` by
    `testOnlyAFilterWithoutControlAndValueIsIneffective`: reason key `filter.info.ineffective.archive_without_value`
    for no control + no value; `null` for no control + value; `null` for control + no value (options-only `true`).
  - In `IneffectiveFilterInspectorTest`, the fixture value provider returns a `ChoiceValue` for stored `'5'`; add
    `self::assertNull($inspector->describe(self::model(['value' => '5'])))` to `testEffectiveFiltersAreNotDescribed`
    and change the expected key in `testDescribesAnArchiveWhitelistForOptionsOnlyWithoutAControl` (rename it
    `testDescribesAnArchiveWithoutControlAndValue`) and in `testCountsTheIneffectiveFilters`.
- [ ] **Step 3: Run** `make test` — expect the new tests to fail.
- [ ] **Step 4: Implement** in `buildContext()`: when `$scope->filter->control === null`, never call
  `getAllowedParentIds()`; no value → `return`; otherwise group the value's `ContaoModelChoice`s by table
  (`array<string, list<int>>`) and emit, for a static ptable, `ArchivePredicate` with
  `parent_ids => $grouped[$ptable] ?? $builder->abort()`, for a dynamic ptable `BelongsToRelationPredicate` with
  `parent_groups` = the grouped value as `list<array{table, ids}>` and no `submitted_data`. Keep the controlled path
  unchanged. `getIneffectiveReason()`: `control === null && value === null` →
  `TranslatableMessage('filter.info.ineffective.archive_without_value', domain: 'flare')`.
- [ ] **Step 5: Translations** (`filter.info.ineffective.archive_without_value`):
  - en: `This filter has no control and no value, so it restricts nothing. Select the archives to show as its value, or select a control.`
  - de: `Dieser Filter hat weder Bedienelement noch Wert und schränkt daher nichts ein. Wählen Sie die anzuzeigenden Archive als Wert oder wählen Sie ein Bedienelement.`
- [ ] **Step 6: Run** `make test` and `make phpstan` — expect green.
- [ ] **Step 7: Commit** `fix!: an archive filter without control is restricted by its value, not its whitelist`

### Task 3: Backend — all parents as choices, whitelist fields only with a control

**Files:**
- Modify: `src/Filter/Element/ArchiveFilterElement.php` (`buildChoices()`, `buildDca()`, two new protected fetchers)
- Test: `tests/Filter/Element/ArchiveFilterElementTest.php`

**Interfaces:**
- Consumes: backend scope with resolved control (Task 1); `AbstractFilterElement::getConnection()`.
- Produces (protected, overridable in tests):
  - `fetchAllParents(string $table): ?Collection` — all records of `$table` via its model class
    (`Model::getClassFromTable()`, `findAll()`), null without a model class.
  - `fetchDynamicParentTables(string $table): list<string>` —
    `SELECT DISTINCT ptable FROM {$table} WHERE ptable <> ''` (identifier quoted), the column name `ptable` as in
    `buildContext()`.

- [ ] **Step 1: Write the tests** with an anonymous subclass of `ArchiveFilterElement` overriding both fetchers
  (parents as `ContaoModelChoice`-compatible model stubs, or assert on the tables/ids requested):
  - `testWithoutControlChoicesAreAllRecordsOfThePtable`: uncontrolled scope on `tl_news` with whitelist `[3]` →
    `fetchAllParents('tl_news_archive')` is used, `fetchParents()` is not; the whitelist does not limit the choices.
  - `testWithoutControlDynamicChoicesComeFromTheDistinctPtables`: `fetchDynamicParentTables('tl_content')` returns
    `['tl_news', 'tl_unknown']`; `tl_unknown` has no model class (fetcher returns null) → skipped, no exception; the
    group whitelist is not read.
  - `testWithoutControlAndNoParentsTheChoicesAreEmpty`: both fetchers return nothing → empty builder, no exception
    (Review Focus 3).
  - `testWithControlChoicesStayTheWhitelist`: controlled scope → `fetchParents('tl_news_archive', [3])` as today.
- [ ] **Step 2: Run** `make test` — expect failures.
- [ ] **Step 3: Implement** the `control === null` branch at the top of `buildChoices()` (static: `fetchAllParents($ptable)`;
  dynamic: for each table of `fetchDynamicParentTables($scope->list->dc)` add `fetchAllParents($table)`'s models,
  label `null`; keep `setModelSuffix('(%@name%)')` for dynamic). No exception for an empty result without a control.
  In `buildDca()` return before adding the archive palette when `(string) $filterModel->control === ''`.
- [ ] **Step 4: Run** `make test` and `make phpstan` — expect green.
- [ ] **Step 5: Commit** `feat: offer all parent archives as the value of an archive filter without control`

### Task 4: Migration — legacy intrinsic whitelists become values

**Files:**
- Modify: `src/Migration/BackfillFilterValuesMigration.php` (`REQUIRED_COLUMNS`, `valueFor()`, new private method)
- Test: `tests/Migration/BackfillFilterValuesMigrationTest.php`

**Interfaces:**
- Consumes: `findPtable(int $listId): ?string` (exists).
- Produces: `private function convertIntrinsicArchive(array $row): string` — serialized `{table}.{id}` list or `''`.

- [ ] **Step 1: Write the tests** (fixture list 1 = generic `tl_news`, ptable `tl_news_archive`; add the columns
  `elm_whitelistParents`, `elm_groupWhitelistParents` to `COLUMNS` and `addRow()` defaults):
  - `testIntrinsicArchiveWhitelistBecomesItsValue`: `intrinsic = 1`, `elm_whitelistParents = serialize(['3', '5'])`,
    `elm_useWhitelistForOptionsOnly = '1'` → `value === serialize(['tl_news_archive.3', 'tl_news_archive.5'])`,
    `control` stays `''`.
  - `testIntrinsicArchiveOfADynamicListUsesItsGroups`: list without static ptable, `elm_groupWhitelistParents =
    serialize([['tablePtable' => 'tl_news', 'whitelistParents' => serialize(['1'])], ['tablePtable' => 'tl_calendar',
    'whitelistParents' => serialize(['7'])]])` → `value === serialize(['tl_news.1', 'tl_calendar.7'])`.
  - `testIntrinsicArchiveWithoutWhitelistGetsNoValue`: empty whitelist → `value` untouched.
  - `testShouldRunWaitsForTheRenamedElementColumns`: without `elm_whitelistParents` → `shouldRun() === false`.
  - Replace the expectations of `testIntrinsicArchiveIgnoresLeftoverPreselect` (whitelist now drives the value; the
    leftover `preselect` still does not).
- [ ] **Step 2: Run** `make test tests/Migration` — expect failures.
- [ ] **Step 3: Implement**: add `elm_whitelistParents` and `elm_groupWhitelistParents` to `REQUIRED_COLUMNS` (fail
  closed until the rename ran); `valueFor()` → `'flare_archive' => $intrinsic ? $this->convertIntrinsicArchive($row) : …`.
  `convertIntrinsicArchive()`: `findPtable(pid)` non-null → ids of `elm_whitelistParents`; otherwise the groups
  (`tablePtable` + nested serialized `whitelistParents`); ids cast to int, `< 1` dropped, deduplicated. Update the class
  docblock (intrinsic archive rows now get a value; why: the old element always restricted to the whitelist).
- [ ] **Step 4: Run** `make test` and `make phpstan` — expect green.
- [ ] **Step 5: Commit** `fix: turn intrinsic archive whitelists into values in the backfill migration`

### Task 5: Documentation

**Files:**
- Modify: `AGENTS.md` (only if a sentence describes the archive whitelist without control — check, else leave)
- Modify (docs worktree): `docs/dev/filter-elements/index.md` (IneffectiveFilterContract paragraph: "no control and no
  value"), `docs/reference/filter-elements.md` (`flare_archive` row: without a control the value restricts; the
  whitelist and "options only" apply to controlled filters), `docs/migrating-from-v0.1.md` (intrinsic archive filters:
  whitelist migrates into the value), `docs/removed-in-v0.2.md` (breaking: `whitelist_parents` /
  `group_whitelist_parents` / `use_whitelist_for_options_only` are ignored on filters without a control, also when set
  through `FilterFactory::create()`)

- [ ] **Step 1: Edit** the pages above.
- [ ] **Step 2: Run** `npm run build` in `docs/` — expect `[SUCCESS]`.
- [ ] **Step 3: Commit** in `docs/`: `docs: an archive filter without control is restricted by its value`
