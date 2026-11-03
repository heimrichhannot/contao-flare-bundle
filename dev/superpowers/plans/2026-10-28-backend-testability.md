# Backend Testability Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The backend presentation of filter issues lives in a `FilterIssueReporter` with a public API, and
`ElementDcaListener` / `ListCallbacks` delegate to it and reach Contao only through framework adapters, so all of it is
tested through public methods.

**Architecture:** Shared test fixtures (model subclasses, a real-inspector trait, an echo translator, a message
recorder) come first. Then `FilterIssueReporter` (when and how to report), `BackendElementScopeFactory` (the
listener's element scope, public), the listener rewired to both, and `ListCallbacks` rewired to the reporter.

**Tech Stack:** PHP 8.2, Contao 4.13/5.x (`ContaoFramework`, `contao/test-case`), Symfony 5.4–7, PHPUnit 9,
PHPStan level 5, Mago.

**Spec:** `dev/superpowers/specs/2026-10-28-backend-testability-design.md` (approved 2026-10-28).

## Global Constraints

- No change in behaviour, messages, translations or the row template.
- `FilterInspector` stays `final`, no interface. New production classes are `@internal`, `final readonly`, services
  (auto-registered: `src/DataContainer/` and `src/DataContainer/Inspection/FilterIssueReporter.php` are not excluded).
- No test of this work reaches a private method or property of the code under test (no bound closure, no reflection
  for that). Existing fixtures in other tests stay as they are.
- Model fixtures are `FilterModelFixture` / `ListModelFixture` (constructor assigns `$this->arrData`); never
  `Model::setRow()` (needs Contao's container).
- Catch `\Exception`, never `\Throwable`. `declare(strict_types=1);`, PER style, 120-char lines, ≤ 20 methods per
  class. Keep §/SPEC references.
- Tests: `make test`, `make test tests/Path/SomeTest.php`; `make phpstan`; `mago lint`.
- Commits: conventional style, **no `Co-Authored-By` trailer, never push**.

## Review Focus

1. A record id that is missing, non-numeric, `0` or negative — the listener loads nothing and reports nothing.
   → Task 4 test `testAnInvalidRecordIdLoadsNothing`.
2. An alias with HTML characters (renamed by a `FilterCollectedEvent` listener) — escaped in the overview's duplicate
   message and in the edit form's message. → Task 2 tests `testTheOverviewReportsEachIssueKindWithItsCount`,
   `testTheEditFormReportsEachIssueOfTheFilter`.
3. Saving a form (POST) — nothing reported, and the list not inspected. → Task 2 tests
   `testNothingIsInspectedOrReportedOutsideTheFilterChildView`, `testTheEditFormIsReportedOnGetWithActEditOnly`.
4. A filter row or edit form whose list no longer exists — no notices, no messages, no error. → Task 2 test
   `testARowWithoutItsListHasNoNotices`, Task 4 test `testAFilterWithoutItsListReportsNothing`.
5. A list whose only issues are on unpublished filters — no overview message. → Task 2 test
   `testUnpublishedIssuesAddNoOverviewMessage`.

---

### Task 1: Shared test fixtures

**Files:**
- Create: `tests/Fixture/FilterModelFixture.php`, `tests/Fixture/ListModelFixture.php`,
  `tests/DataContainer/Inspection/BuildsFilterInspectorTrait.php`,
  `tests/DataContainer/Inspection/Stub/{OkElement,ThrowingElement,AbortingElement,RawElement,ErroringElement,ValueSensitiveElement,IneffectiveElement}.php`
- Modify: `tests/DataContainer/Inspection/FilterInspectorTest.php` (uses the trait and fixtures; its
  `InspectorStub*` classes and its `inspector()`, `createListSpecBuilderFactory()`, `model()`, `listModel()` helpers
  move out — the `arrData` closures go with them)

**Interfaces:**
- Produces:
  ```php
  namespace HeimrichHannot\FlareBundle\Tests\Fixture;
  final class FilterModelFixture extends FilterModel { /** @param array<string, mixed> $row */ public function __construct(array $row) }
  final class ListModelFixture extends ListModel { /** @param array<string, mixed> $row */ public function __construct(array $row) }

  namespace HeimrichHannot\FlareBundle\Tests\DataContainer\Inspection;
  /** For ContaoTestCase subclasses. Elements 'ok', 'throws', 'aborts', 'raw', 'errors', 'sensitive' (BoolValue),
   *  'ineffective' (BoolValue); control 'flare_checkbox' (BoolValue); list driver 'test_list' on tl_test. */
  trait BuildsFilterInspectorTrait {
      private function createFilterInspector(ContaoFramework $framework, ?EventDispatcherInterface $dispatcher = null,
          bool $debug = false, ?LoggerInterface $logger = null): FilterInspector;   // translator: echoTranslator()
      private function createFilterElementRegistry(): FilterElementRegistry;
      private function createFilterFactory(): FilterFactory;
      private function createListDriverRegistry(): ListDriverRegistry;
      private function createListSpecBuilderFactory(EventDispatcherInterface $dispatcher): ListSpecBuilderFactory;
      /** findBy() → $rows (null when empty); findByPk($id) → $byId[$id] ?? null.
       *  @param list<FilterModel> $rows @param array<int, FilterModel> $byId */
      private function mockFilterModelAdapter(array $rows, array $byId = []): Adapter;
      /** A Message adapter whose addError()/addNew() append [method, message] to $recorded.
       *  @param list<array{string, string}> $recorded */
      private function recordMessages(array &$recorded): Adapter;
      /** @param array<string, mixed> $row extra columns */
      private static function filterModel(int $id, string $type, string $alias, bool $published = true, array $row = []): FilterModelFixture; // pid 5, control '', value ''
      private static function listModel(int $id): ListModelFixture;   // type 'test_list'
      /** "key" without parameters, else "key k1=v1 k2=v2" in the given order. */
      private static function echoTranslator(): TranslatorInterface;
  }
  ```
  Stubs (namespace `...\Inspection\Stub`): today's `InspectorStub*Element` classes without the prefix, otherwise
  unchanged (`ThrowingElement` throws `FilterException('broken')`, `ErroringElement` `\Error('bug')`,
  `IneffectiveElement` reports `stub.ineffective`).

- [ ] **Step 1: Move** helpers, stubs and model construction as listed. `FilterInspectorTest` keeps its `$dispatcher`
  property and `inspect()` helper; `model()` calls become `filterModel()`; `inspect()` passes
  `mockContaoFramework([FilterModel::class => mockFilterModelAdapter([])])` where it needs no rows.
- [ ] **Step 2: Run** — `make test tests/DataContainer/Inspection` → OK, 38 tests, as before. `make test`,
  `make phpstan`, `mago lint` clean. (A pure test refactor: no failing step.)
- [ ] **Step 3: Commit** — `git commit -m "test: share fixtures for the filter inspection tests"`

---

### Task 2: `FilterIssueReporter`

**Files:**
- Create: `src/DataContainer/Inspection/FilterIssueReporter.php`
- Test: `tests/DataContainer/Inspection/FilterIssueReporterTest.php` (`ContaoTestCase` + the trait)

**Interfaces:**
- Consumes: Task 1; `FilterInspector::inspect()`, `ListInspection::count()/forRow()/$blockingAliases`,
  `FilterIssue::describe()`.
- Produces:
  ```php
  final readonly class FilterIssueReporter {
      public function __construct(FilterInspector $filterInspector, ContaoFramework $framework,
          RequestStack $requestStack, TranslatorInterface $translator) {}
      /** On a GET with table=tl_flare_filter and no act only; the list is inspected only then. */
      public function reportOverview(ListModel $listModel): void;
      /** On a GET with act=edit only. */
      public function reportFilter(ListModel $listModel, FilterModel $filterModel): void;
      /** @param array<string, mixed> $row @return array<string, string> by FilterIssueKind value; unescaped */
      public function getRowNotices(array $row): array;
  }
  ```
  The message composition is today's `ElementDcaListener::reportFilterStates()` / `reportFilterState()` /
  `addMessage()` (keys `list.info.{programming_errors,duplicate_filter_alias,broken_filters,ineffective_filters}`,
  `StringUtil::specialchars`), with `Message` through `getAdapter(Message::class)`. `getRowNotices()` is today's
  `ListCallbacks::describeIssues()` with the list loaded through `getAdapter(ListModel::class)->findByPk()`.

Test setup: one framework per test, `mockContaoFramework([FilterModel::class => mockFilterModelAdapter($rows),
Message::class => recordMessages($recorded), ListModel::class => …])`, shared by the inspector and the reporter;
the list is `listModel(5)`. Requests: overview `Request::create('/contao?do=flare&table=tl_flare_filter&id=5')`,
edit `…?do=flare&table=tl_flare_filter&act=edit&id=1`.

- [ ] **Step 1: Write the tests**

```php
testTheOverviewReportsEachIssueKindWithItsCount
    // rows: 1 'ok' 'x' published, a FilterCollectedEvent listener throws \Error('bug') for row 1;
    //       2 & 3 'ok' published, the listener renames both to '<dup>'; 4 'gone' published; 5 'ineffective' published
    // $recorded === [
    //   ['addError', 'list.info.programming_errors %count%=1'],
    //   ['addError', 'list.info.duplicate_filter_alias %alias%="&lt;dup&gt;"'],
    //   ['addError', 'list.info.broken_filters %count%=1'],
    //   ['addNew', 'list.info.ineffective_filters %count%=1'],
    // ]
testUnpublishedIssuesAddNoOverviewMessage
    // rows: 'gone' and 'ineffective', both unpublished → $recorded === []
testNothingIsInspectedOrReportedOutsideTheFilterChildView
    // a published 'gone' row; FilterModel adapter findBy() expects never();
    // requests …&act=edit, ?table=tl_other, the overview URL as POST → $recorded === [] each
testTheEditFormReportsEachIssueOfTheFilter
    // rows: 1 'throws' published, 2 'ok' published; listener renames both to '<dup>'; reportFilter(list, row 1)
    // $recorded === [
    //   ['addError', 'filter.info.duplicate_alias.published %alias%=&lt;dup&gt;'],
    //   ['addError', 'filter.info.broken.published %reason%=broken'],
    // ]
testIneffectiveFiltersAreReportedAsNew
    // row 1 'ineffective' unpublished → $recorded === [['addNew', 'stub.ineffective']]
testTheEditFormIsReportedOnGetWithActEditOnly
    // as testTheEditFormReports… with the edit URL as POST, and as GET without act → $recorded === [] each
testRowNoticesAreKeyedByIssueKind
    // row 1 'throws' published; ListModel adapter findByPk(5) → listModel(5)
    // getRowNotices(['id' => 1, 'pid' => 5]) === ['broken' => 'filter.info.broken.published %reason%=broken']
testARowWithoutItsListHasNoNotices
    // ListModel adapter findByPk() → null → getRowNotices(['id' => 1, 'pid' => 5]) === []
```

- [ ] **Step 2: Run, verify fail** — `make test tests/DataContainer/Inspection/FilterIssueReporterTest.php` → FAIL,
  class not found.
- [ ] **Step 3: Implement** the reporter as specified.
- [ ] **Step 4: Run** — the file passes; `make test`, `make phpstan`, `mago lint` clean.
- [ ] **Step 5: Commit** — `git commit -m "feat: present filter issues in the backend through one reporter"`

---

### Task 3: `BackendElementScopeFactory`

**Files:**
- Create: `src/DataContainer/BackendElementScopeFactory.php`
- Modify: `src/EventListener/Contao/ElementDcaListener.php` (uses the factory; `createElementScope()` and the
  `FilterFactory` dependency go)
- Test: `tests/DataContainer/BackendElementScopeFactoryTest.php`; remove
  `testTheBackendScopeKnowsTheControlButReadsNoValue` and its helpers from `ElementDcaListenerTest`

**Interfaces:**
- Produces:
  ```php
  final readonly class BackendElementScopeFactory {
      public function __construct(FilterFactory $filterFactory) {}
      /** The backend scope (§6.4): knows the control, reads no value; null on any \Exception.
       *  @param \Closure(): ListSpec $getSpec */
      public function create(\Closure $getSpec, FilterModel $filterModel): ?ElementScope;
  }
  ```
  The listener's `DcaContext` gets `elementScopeFactory: fn (): ?ElementScope => $filterModel ?
  $this->elementScopeFactory->create($getSpec, $filterModel) : null`.

- [ ] **Step 1: Write the test** — `testTheBackendScopeKnowsTheControlButReadsNoValue`, ported from
  `ElementDcaListenerTest` (same `ArchiveFilterElement` / `flare_choice` / unreadable-value setup, model as
  `FilterModelFixture`): scope not null, `$scope->filter->control?->type === 'flare_choice'`,
  `$scope->filter->value === null`. Plus `testAScopeThatCannotBeBuiltIsNull`: `$getSpec` throws
  `FlareException` → `null`.
- [ ] **Step 2: Run, verify fail** — `make test tests/DataContainer/BackendElementScopeFactoryTest.php` → FAIL, class
  not found.
- [ ] **Step 3: Implement** the factory (today's `createElementScope()` body); rewire the listener; remove the old
  test.
- [ ] **Step 4: Run** — `make test`, `make phpstan`, `mago lint` clean.
- [ ] **Step 5: Commit** — `git commit -m "refactor: build the backend element scope in its own factory"`

---

### Task 4: `ElementDcaListener` delegates and loads through the framework

**Files:**
- Modify: `src/EventListener/Contao/ElementDcaListener.php`
- Test: `tests/EventListener/Contao/ElementDcaListenerTest.php` (rewritten: `ContaoTestCase` + the trait)

**Interfaces:**
- Consumes: Task 2's reporter, Task 3's factory, Task 1's trait.
- Produces: constructor `(EventDispatcherInterface $eventDispatcher, FilterControlRegistry $filterControlRegistry,
  FilterElementRegistry $filterElementRegistry, BackendElementScopeFactory $elementScopeFactory,
  FilterIssueReporter $filterIssueReporter, ContaoFramework $framework, ListExecutionContextFactory
  $listExecutionContextFactory, ListSpecBuilderFactory $listFactory, ListDriverRegistry $listDriverRegistry,
  RequestStack $requestStack, ValueProviderRegistry $valueProviderRegistry)`.

Changes: the record id comes from `$requestStack->getCurrentRequest()?->query->get('id')`, validated as today
(`Input` goes); the filter loads through `getAdapter(FilterModel::class)->findByPk($id)` and its list through
`getAdapter(ListModel::class)->findByPk((int) $filterModel->pid)` (not `getRelated()`); the list table's list through
`getAdapter(ListModel::class)->findByPk($id)`. The list table calls `$this->filterIssueReporter->reportOverview()`
where `reportFilterStates()` is called today; the filter table calls `reportFilter()` after `apply()` whenever there
is a filter model — the listener no longer checks the method or `act` for reporting (the reporter does);
`$isEditAction` stays for `applyPalette`; `reportFilterStates()`, `reportFilterState()`,
`reportsFilterStates()`, `addMessage()` and the `FilterInspector` / `TranslatorInterface` dependencies go.

Test setup: the listener built with real collaborators — the trait's registries and factories, a real
`FilterIssueReporter` and `BackendElementScopeFactory`, `new ListExecutionContextFactory($dispatcher)`, an empty
`FilterControlRegistry` / `ValueProviderRegistry` — and one framework mock (`FilterModel`, `ListModel`, `Message`
adapters). `setUp()` sets `$GLOBALS['TL_DCA']['tl_flare_list']` and `['tl_flare_filter']` to
`['fields' => [], 'palettes' => []]`; `tearDown()` unsets them.

- [ ] **Step 1: Write the tests**

```php
testTheFilterChildViewReportsTheOverview
    // GET ?do=flare&table=tl_flare_filter&id=5; ListModel findByPk(5) → listModel(5); rows: 'gone' published
    // ($listener)('tl_flare_list') → $recorded === [['addError', 'list.info.broken_filters %count%=1']]
testTheEditFormReportsTheFilter
    // GET ?do=flare&table=tl_flare_filter&act=edit&id=1; FilterModel findByPk(1) → filterModel(1, 'throws', 'a');
    // ListModel findByPk(5) → listModel(5); rows: the same row
    // ($listener)('tl_flare_filter') → $recorded === [['addError', 'filter.info.broken.published %reason%=broken']]
testAFilterWithoutItsListReportsNothing
    // as testTheEditFormReportsTheFilter with ListModel findByPk() → null → $recorded === [], no error
testAnInvalidRecordIdLoadsNothing
    // ids: none, 'abc', '0', '-3' → FilterModel and ListModel findByPk() expect never(); $recorded === []
```

The existing `testFilterStatesAreReportedOnGetRequestsOnly` goes (Task 2 tests the GET rule on the reporter).

- [ ] **Step 2: Run, verify fail** — `make test tests/EventListener/Contao/ElementDcaListenerTest.php` → FAIL
  (unknown named parameters `$filterIssueReporter`, `$framework`).
- [ ] **Step 3: Implement** the changes listed above.
- [ ] **Step 4: Run** — `make test`, `make phpstan`, `mago lint` clean; `grep -n "Input::\|Message::\|::findByPk\|getRelated" src/EventListener/Contao/ElementDcaListener.php` → no output.
- [ ] **Step 5: Commit** — `git commit -m "refactor: delegate filter issue reporting and load through the framework in the element DCA listener"`

---

### Task 5: `ListCallbacks` delegates to the reporter

**Files:**
- Modify: `src/EventListener/DataContainer/FlareFilter/ListCallbacks.php`
- Test: `tests/EventListener/DataContainer/FlareFilter/ListCallbacksTest.php` (new; `ContaoTestCase` + the trait)

**Interfaces:**
- Consumes: Task 2's `getRowNotices()`.
- Produces: constructor `(FilterIssueReporter $filterIssueReporter, TranslatorInterface $translator, TwigEnvironment
  $twig)`; `describeIssues()` and the `FilterInspector` dependency go.

- [ ] **Step 1: Write the test** — `testTheRowReceivesItsNotices`: a real reporter (row 1 'throws' published, ListModel
  findByPk(5) → listModel(5)); a Twig `Environment` mock whose `render()` stores its context and returns `''`;
  `listLabelLabel(['id' => 1, 'pid' => 5, 'type' => 'throws', 'alias' => 'a', 'published' => '1', 'control' => ''])`
  → `$context['notices'] === ['broken' => 'filter.info.broken.published %reason%=broken']`.
- [ ] **Step 2: Run, verify fail** — `make test tests/EventListener/DataContainer/FlareFilter/ListCallbacksTest.php`
  → FAIL (unknown named parameter `$filterIssueReporter`).
- [ ] **Step 3: Implement** — pass `'notices' => $this->filterIssueReporter->getRowNotices($row)`.
- [ ] **Step 4: Run** — `make test`, `make phpstan`, `mago lint` clean.
- [ ] **Step 5: Commit** — `git commit -m "refactor: take the filter row notices from the issue reporter"`
