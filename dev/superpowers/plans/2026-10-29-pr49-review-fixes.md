# PR #49 Review Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the ten validated findings of the PR #49 code review as decided with the user.

**Architecture:** Small, independent fixes (alias keys, alias rule, migration backfill and guard, required config)
plus one connected change to failure handling: value providers and collector listeners fail the list like other
filter hooks, a control's form is built by one shared `FilterFormInvoker` (used by `FormHarnessFactory` and the
backend `FilterInspector`), whose failures the harness records so the `InteractiveProjector` renders the failed
view — checked before the validity of a submission, which no longer triggers a COUNT.

**Tech Stack:** PHP 8.2, Symfony 5.4–7, Contao 4.13/5.x, PHPUnit 9, PHPStan level 5, Mago.

**Spec:** `dev/superpowers/specs/2026-10-29-pr49-review-fixes-design.md` (approved by the user 2026-10-29). Task
headings name the spec's finding numbers (#1–#10).

## Global Constraints

- Catch `\Exception`, never `\Throwable`; `\Error`s escape (the inspector's existing `\Error` catch outside debug mode
  stays the only exception). The transformer resolvers keep wrapping `\Throwable`.
- `declare(strict_types=1);`, PER style, 120-char lines (`mago lint`, CI only).
- Tests: `make test` (all), `make test tests/Path/SomeTest.php`. Static analysis: `make phpstan`.
- Production code testable through its public API; no reflection on private members in tests.
- Migrations never drop or delete; SQL valid on MySQL 5.7/8 and MariaDB.
- Commits: conventional style, **no `Co-Authored-By` trailer, never push**. Docs in the `docs/` worktree (branch
  `docs/main`) are committed separately there; `npm run build` in `docs/` must pass.
- Do not touch `§`/`SPEC_*` references in comments, even stale ones.

## Review Focus

- An alias with non-ASCII letters (`größe`) passes Contao's `isAlias` but is not a valid form name — the DCA must
  reject it (test in Task 2).
- A purely numeric alias (`12`, `0`) is a valid form name and must be accepted, and keep its key in the list
  (tests in Tasks 1 and 2).
- An invalid submission on a list that has a collection or `buildForm()` failure must render the failed view, not an
  empty list (test in Task 8).
- A cancelled `FilterControlBuildFormEvent` next to a failing control: only the failing one fails the list; a list
  whose only "problem" is a cancellation renders normally (test in Task 7).
- A bool filter that already has a `ctl_label`, or stays without a control, must not get the title written over it
  (test in Task 3).

---

### Task 1: `ListSpec::withFilter()` keeps alias keys (#1)

**Files:**
- Modify: `src/List/ListSpec.php` (`withFilter()`)
- Test: `tests/List/ListSpecTest.php`

**Interfaces:** none new.

- [ ] **Step 1: Write the failing test** `testWithFilterKeepsNumericAliasKeys`: a spec built with filters aliased
  `'12'` and `'15'` (use the existing `filter()` / `spec()` helpers; pass them keyed by alias), then
  `->withFilter(self::filter(..., '0'))`. Assert `array_keys($result->filters) === [12, 15, 0]` and that no exception
  is thrown.
- [ ] **Step 2: Run** `make test tests/List/ListSpecTest.php` — expected FAIL (keys `[0, 1, 2]` or a duplicate-alias
  `FlareException` for `'0'`).
- [ ] **Step 3: Implement** — replace the spread with a copy and keyed assignment:
  `$filters = $this->filters; $filters[$filter->alias] = $filter; return $this->withFilters($filters);`
- [ ] **Step 4: Run** the test file — expected PASS.
- [ ] **Step 5: Commit** `fix: keep alias keys when a filter is added to a list`

### Task 2: Filter aliases must be valid form names (#2, parts 1–2)

**Files:**
- Create: `src/EventListener/Contao/FormNameRegexpListener.php`
- Modify: `contao/dca/tl_flare_filter.php` (`alias.eval.rgxp`), `contao/languages/{de,en}/default.php`,
  `src/Filter/Factory/FilterFactory.php` (`create()` and `createFromFilterModel()`)
- Test: `tests/EventListener/Contao/FormNameRegexpListenerTest.php`, `tests/Filter/FilterFactoryTest.php`

**Interfaces:**
- Produces: `FormNameRegexpListener::RGXP = 'flare_form_name'`;
  `#[AsHook('addCustomRegexp')] public function __invoke(string $regexp, mixed $input, Widget $widget): bool`.
- Produces: `FilterFactory` throws `FlareException` for a filter **with a control** whose alias fails
  `Str::isValidFormName()`. Task 7 relies on the harness never seeing such a filter from the factory.

- [ ] **Step 1: Write the failing listener tests** (`FormNameRegexpListenerTest`, a `Widget` mock recording
  `addError()`):
  - `testOtherRegexpsAreNotHandled`: `__invoke('alias', 'x', $widget)` returns `false`, no error.
  - `testValidFormNamesPass` (data provider: `'price_min'`, `'12'`, `'0'`, `'a-b'`, `'a:b'`): returns `true`, no error.
  - `testInvalidFormNamesAreRejected` (data provider: `'price.min'`, `'-sort'`, `'größe'`, `'a b'`): returns `true`,
    exactly one `addError()` call, message is the translated `ERR.flare.form_name` (stub the translation in
    `$GLOBALS['TL_LANG']` in the test).
- [ ] **Step 2: Write the failing factory tests** in `FilterFactoryTest`:
  - `testControlledFilterWithAnInvalidFormNameIsRejected`: `create(..., alias: 'price.min', control: <control>)`
    throws `FlareException` whose message names the alias.
  - `testControlledFilterModelWithAnInvalidFormNameIsRejected`: same through `createFromFilterModel()` with a model
    row `alias = 'price.min'`, `control` set.
  - `testFilterWithoutControlKeepsAnInvalidAlias`: `create(..., alias: 'price.min')` without control succeeds.
- [ ] **Step 3: Run** both test files — expected FAIL.
- [ ] **Step 4: Implement the listener** — handle only `RGXP`; when `!Str::isValidFormName((string) $input)`, call
  `$widget->addError($GLOBALS['TL_LANG']['ERR']['flare']['form_name'])` (use `\sprintf` with the widget label if the
  text has a `%s`). Return `true` whenever `$regexp === RGXP`.
- [ ] **Step 5: Wire it up** — `'rgxp' => 'flare_form_name'` on the `alias` field (replacing `'alias'`);
  translations: en `'The alias may only contain letters a–z, digits, underscores, hyphens and colons, and must not start with a hyphen or colon.'`,
  de `'Der Alias darf nur Buchstaben a–z, Ziffern, Unterstriche, Bindestriche und Doppelpunkte enthalten und nicht mit einem Bindestrich oder Doppelpunkt beginnen.'`
  (builder may polish wording; keep both languages in sync).
- [ ] **Step 6: Implement the factory check** next to the existing "control needs an alias" check in `create()`, and
  in `createFromFilterModel()` before `withControl()`: `FlareException` "The alias "%s" of a filter with a control
  is not a valid form name%s." with the `$source` suffix, `method: __METHOD__`.
- [ ] **Step 7: Run** both test files and `make test tests/List/ListModelFilterCollectorTest.php` — expected PASS.
- [ ] **Step 8: Commit** `fix: reject filter aliases that are not valid form names`

### Task 3: Backfill migration fills `ctl_label` (#3)

**Files:**
- Modify: `src/Migration/BackfillFilterValuesMigration.php` (`REQUIRED_COLUMNS`, `computeChanges()`, class docblock)
- Test: `tests/Migration/BackfillFilterValuesMigrationTest.php` (add `'title' => ''` and `'ctl_label' => ''` to the
  `addRow()` defaults and `'ctl_label'` to the fixture's column list)

**Interfaces:** none new.

- [ ] **Step 1: Write the failing tests:**
  - `testBoolGetsItsTitleAsLabel` (data provider: `boolMode` `''` → `flare_checkbox`, `'ternary'` →
    `flare_choice_bool`): row `title => 'Only open'`, empty `ctl_label`; the row update contains
    `'ctl_label' => 'Only open'`.
  - `testKeywordsGetTheTranslatedDefaultLabel`: `flare_search_keywords` row with empty `ctl_label`; update contains
    `'ctl_label' => 'label.text'`.
  - `testExistingLabelIsKept`: bool row with `ctl_label => 'Mine'`; no `ctl_label` key in its update.
  - `testRowsWithoutAControlGetNoLabel`: intrinsic bool row (`intrinsic => '1'`) and an intrinsic keywords row; no
    `ctl_label` in their updates.
  - `testShouldRunWaitsForTheLabelColumn`: without `ctl_label` in the columns, `shouldRun()` is false.
- [ ] **Step 2: Run** the test file — expected FAIL.
- [ ] **Step 3: Implement** — add `'ctl_label'` to `REQUIRED_COLUMNS`; in `computeChanges()`, inside the
  `$assignsControl` branch, when `(string) ($row['ctl_label'] ?? '') === ''`: `flare_bool` →
  `(string) ($row['title'] ?? '')` (only if non-empty), `flare_search_keywords` → `'label.text'`. Extend the class
  docblock with one sentence on the label backfill (old element fallbacks: title / translated `label.text`).
- [ ] **Step 4: Run** the test file — expected PASS (all old tests too).
- [ ] **Step 5: Commit** `fix: backfill the labels the old bool and search elements fell back to`

### Task 4: Backfill migration never runs twice (#8)

**Files:**
- Modify: `src/Migration/BackfillFilterValuesMigration.php` (`shouldRun()`, class docblock)
- Modify: `docs/docs/migrating-from-v0.1.md` (docs worktree, section "Database & Backend", next to the paragraph on
  `legacyBackfilled` and `--with-deletes`)
- Test: `tests/Migration/BackfillFilterValuesMigrationTest.php` (mock `fetchOne` in `migration()`)

**Interfaces:** none new.

- [ ] **Step 1: Write the failing tests:**
  - `testShouldRunStaysOffWhenTheMarkerIsGoneButControlsAreInUse`: columns without the marker, one row with
    `control => 'flare_choice'` → `shouldRun()` false.
  - `testShouldRunStaysOffWhenTheMarkerIsGoneButValuesAreInUse`: one row with `value => 'a:1:{...}'`, empty
    control → false.
  - Existing `testShouldRunWhileTheMarkerIsAbsent` must keep passing (rows with empty control and null value).
  - The `fetchOne` mock asserts the exact SQL
    `"SELECT 1 FROM tl_flare_filter WHERE control <> '' OR value <> ''"` and returns `1` if any fixture row matches,
    else `false`.
- [ ] **Step 2: Run** — expected FAIL.
- [ ] **Step 3: Implement** — in `shouldRun()`, when the marker column is absent, return
  `$this->connection->fetchOne(<the SQL above>) === false`. Docblock: explain the guard and its blind spot (a table
  where every filter ended up with neither control nor value; a rerun recomputes from the old columns and only
  differs where an editor changed something).
- [ ] **Step 4: Run** — expected PASS.
- [ ] **Step 5: Commit** `fix: never re-run the filter backfill once its columns are in use`
- [ ] **Step 6: Docs** — add to the drop guidance: "Drop `legacyBackfilled` together with the other retired columns,
  never on its own: the backfill uses it to remember that it has run." Run `npm run build` in `docs/`; commit in the
  docs worktree: `docs: drop the backfill marker only with the retired columns`.

### Task 5: Required config in the schema; `abort()` means "matches nothing" (#4)

**Files:**
- Modify: every element under `src/Filter/Element/` and `src/Integration/*/FilterElement/` whose
  `configureOptions()` or `buildContext()` the audit touches; known: `BooleanFilterElement`,
  `DcaSelectFieldFilterElement`, `FieldValueChoiceFilterElement`, `DateRangeFilterElement`,
  `SearchKeywordsFilterElement`
- Test: the matching `tests/Filter/Element/*Test.php` and `tests/Integration/CodefogTags/FilterElement/*Test.php`

**Interfaces:**
- Produces: required keys resolved as `->define($key)->required()->allowedTypes('string')->allowedValues(static fn
  (string $v): bool => $v !== '')` (arrays: `allowedTypes('array')` and a non-empty `allowedValues` closure). The
  resolver's failure surfaces as `FilterException` via `FilterOptionsResolver`.

- [ ] **Step 1: Audit** — for each element, list every config key `buildContext()`, `buildChoices()` or
  `getDateBounds()` reads and classify: required in every case → schema; required only in some cases (depends on
  another key or on `$filter->control`) → `FilterException` at the top of `buildContext()`, before the value check;
  data-dependent emptiness → stays `abort()`. Write the result as a short table into the commit message body.
- [ ] **Step 2: Write the failing tests**, one per changed key, e.g. `BooleanFilterElementTest::
  testMissingFieldIsRejectedBySchema`: resolving the element's options with `['field' => null]` (through the
  `FilterOptionsResolver` the test suite already uses) throws `FilterException`; and
  `testNoValueRestrictsNothing`: `buildContext()` with a configured field and `null` value adds no predicate and does
  not abort. For keywords: `columns => []` rejected. For date range: the old "Set elm_fieldGeneric" test moves to the
  schema.
- [ ] **Step 3: Run** the element tests — expected FAIL.
- [ ] **Step 4: Implement** per the audit; remove the bool element's early `abort()` and the now-unreachable
  `if (!$field)` checks; keep `abort()` only for data-dependent emptiness.
- [ ] **Step 5: Update existing tests** that build these elements without the required keys (search
  `tests/` for the element classes; e.g. `ChoicePortCoverageTest`, `FilterInspectorTest` stubs, control tests) so they
  pass the keys.
- [ ] **Step 6: Run** `make test` and `make phpstan` — expected PASS.
- [ ] **Step 7: Commit** `fix: required element config fails the filter instead of emptying the list`

### Task 6: Value providers and collector listeners fail the list; inspector agrees (#6, #7 provider)

**Files:**
- Modify: `src/List/Collector/ListModelFilterCollector.php` (`collectFromModels()`),
  `src/Filter/Factory/FilterFactory.php` (`provideValue()`), `src/DataContainer/Inspection/FilterInspector.php`
  (`build()` and its docblock), `src/Filter/ValueProvider/ChoiceValueProvider.php` (`provide()`)
- Test: `tests/List/ListModelFilterCollectorTest.php`, `tests/Filter/FilterFactoryTest.php`,
  `tests/DataContainer/Inspection/FilterInspectorTest.php`, `tests/Filter/ValueProvider/ChoiceValueProviderTest.php`

**Interfaces:**
- Produces: `FilterFactory::provideValue()` throws `FilterException` for any `\Exception` of a provider except a
  `FlareException` that is not a `FilterException`, which passes through unchanged. `ChoiceValueProvider::provide()`
  throws `FlareException` (not `FilterException`) for an element without `ChoiceSourceContract`.

- [ ] **Step 1: Write the failing tests:**
  - Collector `testListenerFlareExceptionFailsTheList`: a `FilterCollectedEvent` listener throws `FlareException`;
    the result has one failure for that model and no filter; no exception escapes.
  - Collector `testListenerOtherExceptionEscapes`: a listener throws `\RuntimeException` → it escapes.
  - Factory `testProviderExceptionIsWrappedForAFixedValue`: a provider throwing `\InvalidArgumentException` for a
    filter without control → `FilterException` with the original as previous; an error is logged.
  - Factory `testProviderExceptionDropsTheInitialValue`: same provider, filter with control → filter returned with
    `value === null`, warning logged.
  - Factory `testProviderFlareExceptionPassesThrough`: provider throws a plain `FlareException`, with and without a
    control → that exact exception escapes `createFromFilterModel()`.
  - ChoiceValueProvider `testElementWithoutChoiceSourceIsAWiringError`: throws `FlareException`, and
    `assertNotInstanceOf(FilterException::class, ...)`; also for an empty stored value.
  - Inspector `testListenerFlareExceptionIsBroken` / `testListenerOtherExceptionIsAProgrammingError`: a
    `FilterCollectedEvent` listener throwing each → the row's issue kind is `Broken` / `ProgrammingError`.
- [ ] **Step 2: Run** those files — expected FAIL.
- [ ] **Step 3: Implement** — collector: move the dispatch into the `try` (still `catch (FlareException $e)`).
  Factory: wrap `$provider->provide()` in `try`, rethrow `FilterException` unchanged, rethrow a non-`FilterException`
  `FlareException` unchanged, otherwise log an error and throw a `FilterException` built like
  `FilterException::wrap()` does (the factory has no `Filter` yet — construct it directly with `$source`).
  Inspector `build()`: `catch (FlareException)` → `Broken`; `catch (\Exception)` → `ProgrammingError` with
  `self::reason($e)`; docblock states it judges like the collector. ChoiceValueProvider: throw `FlareException`.
- [ ] **Step 4: Run** the files plus `make test` — expected PASS.
- [ ] **Step 5: Commit** `fix: provider and collector-listener failures fail the list; the inspector agrees`

### Task 7: `FilterFormInvoker`; the harness records `buildForm()` failures (#7, #2 part 3)

**Files:**
- Create: `src/Form/FilterFormInvoker.php`
- Modify: `src/Form/Factory/FormHarnessFactory.php`, `src/Form/FormHarness.php`, `config/services.yaml` if needed
- Test: create `tests/Form/FilterFormInvokerTest.php`; modify `tests/Form/FormHarnessFactoryTest.php` (the tests
  `testInvalidAliasIsSkipped`, `testControlFailingToBuildIsSkipped`,
  `testUnexpectedExceptionInBuildFormIsSkippedAndLoggedAsError`, `testSingleWithCompanionFieldIsRejected` change
  meaning), `tests/Form/FormHarnessTest.php`

**Interfaces:**
- Produces: `final readonly class FilterFormInvoker` (`@internal`), constructor
  `(EventDispatcherInterface $eventDispatcher, FormFactoryInterface $formFactory, LoggerInterface $logger)`;
  `public function build(ControlScope $scope): ?FilterFormBuilder` — runs the control's `buildForm()` and dispatches
  `FilterControlBuildFormEvent`. Returns `null` when the event is cancelled or the control declares no field.
  Throws `FilterException` (source set, via `FilterException::wrap()`) when: the alias is not a valid form name; the
  control's `buildForm()` throws any `\Exception` (a non-`FilterException` is also logged as error); a listener throws
  a `FlareException`; the control declares a single field and children at once. A listener's non-`FlareException`
  escapes unchanged.
- Produces: `FormHarness::__construct(..., array $failures = [])` as the **last** parameter (`list<FilterFailure>`)
  and `FormHarness::getFailures(): array`.
- Consumes: Task 2 (factory never creates a controlled filter with an invalid alias).

- [ ] **Step 1: Write the failing invoker tests** (`FilterFormInvokerTest`, reuse the control/filter helpers of
  `FormHarnessFactoryTest` by moving them into a `tests/Form/BuildsFormFixturesTrait.php` if they are needed twice):
  - `testReturnsTheBuilderOfASingleField`, `testReturnsNullWithoutFields`, `testReturnsNullWhenCancelled`.
  - `testFilterExceptionFromBuildFormIsRethrownWithSource`, `testUnexpectedExceptionFromBuildFormIsWrappedAndLogged`
    (error level).
  - `testListenerFlareExceptionIsWrapped`, `testListenerOtherExceptionEscapes`.
  - `testSingleFieldWithChildrenIsAFilterException`, `testInvalidAliasIsAFilterException`.
- [ ] **Step 2: Write the failing harness tests** in `FormHarnessFactoryTest`:
  - `testFailingControlIsRecordedAndTheOthersMount`: two filters, one control throws `FilterException` → harness has
    one `FilterFailure` (source = that filter's source, exception set), the other filter is mounted; an error is
    logged.
  - `testCancelledFilterIsNoFailure`: one cancelled filter, one failing → exactly one failure, for the failing one.
  - `testHydrateFailureKeepsTheMountWithoutData` (data provider: single field, compound): `hydrate()` throws → the
    mount exists, its data is `null`, a warning is logged (error for a non-`FilterException`), no failure recorded.
  - Rewrite the four tests named under **Files** to the new behaviour (failures instead of skips).
- [ ] **Step 3: Run** `make test tests/Form/` — expected FAIL.
- [ ] **Step 4: Implement `FilterFormInvoker::build()`** by moving the per-filter build steps out of
  `FormHarnessFactory::create()` (collect-only `FilterFormBuilder`, `ATTR_SELF`, `buildForm()`, event, cancel,
  single-vs-children check).
- [ ] **Step 5: Rework `FormHarnessFactory::create()`** — inject `FilterFormInvoker`; for each filter with a control
  call `build()` inside `try { } catch (FilterException $e)`: record `new FilterFailure($filter->source,
  $e->getMessage(), $e)`, log an error, `continue`. `hydrate()` failure: keep mounting with `$initial = null`, log a
  warning (error for a non-`FilterException`). Drop `skip()` and the `$debug` rethrow (the failed view rethrows in
  debug mode, Task 8). Filters without a control stay skipped silently. Pass the failures to `FormHarness`.
- [ ] **Step 6: Run** `make test tests/Form/` and `make phpstan` — expected PASS.
- [ ] **Step 7: Commit** `feat: a control that cannot build its form fails the list`

### Task 8: Projector order — failures, then validity, then COUNT (#5, #7)

**Files:**
- Modify: `src/Engine/Projector/InteractiveProjector.php` (`project()`), `src/List/FilterFailure.php`,
  `src/Query/Executor/ListQueryDirector.php` (`createQueryBuilder()`)
- Test: `tests/Engine/Projector/InteractiveProjectorTest.php`, `tests/List/FilterFailureTest.php`

**Interfaces:**
- Consumes: `FormHarness::getFailures()` (Task 7).
- Produces: `public static function FilterFailure::toListException(list<FilterFailure> $failures, string $source,
  string $method): FilterException` — message `'[FLARE] The list has failed: ' . implode('; ', messages)`, the
  director's current text; the director uses it instead of its inline construction.

- [ ] **Step 1: Write the failing tests:**
  - `FilterFailureTest::testToListExceptionJoinsTheMessages`.
  - Projector `testInvalidSubmissionIsNotCounted`: a control whose `decode()` rejects the submitted value (query
    `?<form>[alias]=bad`) → view has `totalItems === 0`, an empty paginator, no entries, `failed === false`, and the
    recording projector saw **no** aggregation (count) call.
  - `testHarnessFailureYieldsTheFailedView`: a control whose `buildForm()` throws → failed view, form still has the
    other filters' fields.
  - `testFailureWinsOverAnInvalidSubmission` (data provider: list failure recorded at build time / harness failure)
    combined with an invalid submission → failed view.
  - `testHarnessFailureIsRethrownInDebugMode`.
- [ ] **Step 2: Run** the test files — expected FAIL.
- [ ] **Step 3: Implement** `project()` order: build harness and decode as today; if `$list->failures` or
  `$harness->getFailures()` → `createFailedView(FilterFailure::toListException([...both], $list->source ?? 'list
  inlined', __METHOD__), ...)`; else if `$form->isSubmitted() && !$form->isValid()` → view with
  `InteractiveEmptyLoader`, `paginatorFactory->createEmpty(pageParam: $this->getPageParam($form, $context))`,
  `totalItems: 0`; else count, paginate and load as today. `createForm()` stays as is (returns the form; failures are
  already logged by the factory).
- [ ] **Step 4: Run** the test files and `make test tests/Query/` — expected PASS.
- [ ] **Step 5: Commit** `fix: an invalid submission is not counted; a failed list wins over it`

### Task 9: Inspector checks the control's form (#7)

**Files:**
- Modify: `src/DataContainer/Inspection/FilterInspector.php` (constructor, `checkFilters()`, new private
  `checkForm()`), `config/services.yaml` if needed
- Test: `tests/DataContainer/Inspection/FilterInspectorTest.php`, `tests/DataContainer/Inspection/BuildsFilterInspectorTrait.php`

**Interfaces:**
- Consumes: `FilterFormInvoker::build(ControlScope): ?FilterFormBuilder` (Task 7).

- [ ] **Step 1: Write the failing tests:**
  - `testControlFailingToBuildIsBroken`: a controlled row whose control's `buildForm()` throws `FilterException` →
    issue `Broken` with that message.
  - `testBuildFormListenerOtherExceptionIsAProgrammingError`: a `FilterControlBuildFormEvent` listener throws
    `\RuntimeException` → `ProgrammingError`.
  - `testFormIsNotCheckedForFiltersWithoutAControl`: the invoker is never reached (a control-less row with an
    element whose conditions are fine has no issue).
- [ ] **Step 2: Run** — expected FAIL.
- [ ] **Step 3: Implement** — inject `FilterFormInvoker`; in `checkFilters()`, for a filter with a control and a
  `$spec`, add `checkForm($spec, $filter)` to the issue sources: `build(new ControlScope($spec, $filter, new
  ConfigurationContext()))`; `FilterException` → `Broken` (`self::reason()`); other `\Exception` →
  `ProgrammingError`; `\Error` → as `checkConditions()` does. Update `createFilterInspector()` in the trait.
- [ ] **Step 4: Run** `make test tests/DataContainer/` and `make phpstan` — expected PASS.
- [ ] **Step 5: Commit** `feat: the inspector reports controls that cannot build their form`

### Task 10: Documentation (#7, #9, #10)

**Files:**
- Modify: `AGENTS.md`, `dev/superpowers/specs/2026-10-14-filter-forms-design.md` (§8.1 table),
  `src/Filter/Element/ArchiveFilterElement.php` (comment), `docs/docs/reference/filter-elements.md` (docs worktree)

- [ ] **Step 1: AGENTS.md** — (a) Coding Conventions: "`declare(strict_types=1);` at the top of every PHP file under
  `src/` and `tests/`". (b) Failure paragraph: replace "A control's `buildForm()`/`hydrate()` failure drops its mount
  (warning)" with: a control's `buildForm()` failure (incl. a `FlareException` from a `FilterControlBuildFormEvent`
  listener or an invalid form-name alias) fails the list; a `hydrate()` failure keeps the mount without initial data
  (warning); value-provider exceptions are normalized like hook exceptions; a `FlareException` from a
  `FilterCollectedEvent` listener fails the list. (c) Mention `Form/FilterFormInvoker` (shared by `FormHarnessFactory`
  and the inspector) next to `Query/Executor/FilterInvoker`.
- [ ] **Step 2: §8.1** — add a row: "a controlled filter's **control cannot build its form** (`buildForm()`, a
  build-form listener's `FlareException`, an alias that is no valid form name) | the whole list | **fail closed**;
  error logged", and a row for `hydrate()`: "only the initial display | **fail open**: the mount is kept without
  initial data; warning logged". Add a sentence that an invalid submission skips the COUNT after the failures known
  without SQL are checked, linking the idea file.
- [ ] **Step 3: Archive comment** — one sentence on `buildAllChoices()`: for a dynamic ptable, every record of every
  parent table is loaded; a known limitation, see the design of 2026-10-29 (#9).
- [ ] **Step 4: Commit** `docs: record the new failure rules and the strict_types scope`
- [ ] **Step 5: Docs worktree** — in `filter-elements.md`, after the `flare_archive` row's table, add a note: "Known
  limitation: without a control, on a list whose data container has a dynamic parent table (e.g. `tl_content`), the
  value field lists every record of every parent table." Run `npm run build` in `docs/`; commit there
  `docs: note the archive value field's limitation for dynamic parent tables`.
