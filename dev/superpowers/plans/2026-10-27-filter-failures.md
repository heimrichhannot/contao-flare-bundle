# Filter Failures Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Unresolvable controls fail the list instead of degrading, and the backend marks every filter that is
guaranteed to break its list (bone-fracture icon, overview count, edit-form error).

**Architecture:** `FilterFactory` stops dropping controls (the `Filter::$controlDropped` flag goes); collection
failures become a `FilterFailure` value object carried by `CollectedFilters`/`ListSpec`; the per-filter condition
building moves out of `ListQueryDirector` into a `FilterInvoker` service that a new backend
`BrokenFilterInspector` reuses to run each filter with its default value.

**Tech Stack:** PHP 8.2, Symfony 5.4–7, Contao 4.13/5.x, PHPUnit 9, PHPStan level 5, Mago.

**Spec:** `dev/superpowers/specs/2026-10-27-filter-failures-design.md` (approved by the user 2026-10-27).

## Global Constraints

- Catch `\Exception`, never `\Throwable` (the transformer resolvers keep `\Throwable`, as today). `\Error`s escape.
- `declare(strict_types=1);`, PER style, 120-char lines, ≤ 20 methods per class (`mago lint`).
- Tests: `make test` (all), `make test tests/Path/SomeTest.php` (one file). Static analysis: `make phpstan`.
  Lint: `mago lint` (available locally).
- Commits: conventional style, **no `Co-Authored-By` trailer, never push**. Docs (`docs/` worktree, branch
  `docs/main`) are committed separately in that worktree.
- Keep §/SPEC references in comments.
- Framework failure messages are shown to editors untranslated; only elements translate their own
  `FilterException` messages (`AbstractFilterElement::trans()`).
- The uncommitted template/CSS opacity refactor already in the working tree (`--flare-filter-control-opacity`,
  `--flare-filter-notice-opacity` in `public/backend/styles.css`) is part of this work; commit it with Task 5.

## Review Focus

1. The list's spec cannot be built at all (list driver unresolvable, schema throws) while an editor opens the
   filter list — the backend must render without marks, never crash. → Task 4 test
   `testASpecThatCannotBeBuiltMarksNothing`.
2. A controlled filter whose stored initial value breaks `buildContext()` but `null` works — not broken (the
   director retries without the value). → Task 4 test `testControlledFilterIsCheckedWithoutItsInitialValue`.
3. An element that aborts (`$builder->abort()`) with its default value — legitimately matches nothing, not broken.
   → Task 4 test `testAnAbortingFilterIsNotBroken`.
4. An element throwing a non-`FilterException` (e.g. `\RuntimeException`) — broken, with that exception's message;
   an `\Error` escapes. → Task 4 tests `testAnUnexpectedExceptionMarksTheFilter`, `testErrorsEscapeTheCheck`.
5. Two published filters sharing an alias — the later row is marked with the duplicate message (no exception to
   take a reason from). → Task 2 test `testReasonFallsBackToTheMessageWithoutException` and Task 4 test
   `testCollectionFailuresMarkTheirRows`.

---

### Task 1: Unresolvable controls fail closed; remove `Filter::$controlDropped`

**Files:**
- Modify: `src/Filter/Factory/FilterFactory.php` (`createFromFilterModel()` + its PHPDoc)
- Modify: `src/Filter/Filter.php` (remove `$controlDropped`, `withDroppedControl()`, the fingerprint key, the
  wither pass-throughs)
- Modify: `src/Filter/Element/ArchiveFilterElement.php` (`isControlled()` → `$filter->control !== null`, update its
  docblock)
- Modify: `src/List/Collector/ListModelFilterCollector.php` (docblocks only: control failures now fail the list)
- Test: `tests/Filter/FilterFactoryTest.php`, `tests/List/ListModelFilterCollectorTest.php`,
  `tests/Filter/FilterTest.php`, `tests/Filter/Element/ArchiveFilterElementTest.php`,
  `tests/EventListener/Contao/ElementDcaListenerTest.php`

**Interfaces:**
- Produces: `FilterFactory::createFromFilterModel(FilterModel $filterModel, bool $withValue = true): Filter` —
  unchanged signature; now throws `FlareException` for an unresolvable stored control, a broken control config or
  a missing value provider. `Filter` constructor loses its last parameter `bool $controlDropped`.

- [ ] **Step 1: Rewrite the failing tests in `tests/Filter/FilterFactoryTest.php`**

Replace `testUnknownControlDegradesToNoControlAndNoValue`, `testControlForAnotherValueClassDegradesLikeAnUnknownOne`,
`testUnreadableInitialValueDegradesToNoControlAndNoValue`, `testWithoutValueAnUnknownControlDegradesToNoControl`
and the `controlDropped` assertion in `testMissingValueColumnYieldsNoValue`:

```php
public function testUnknownControlThrows(): void
{
    $this->expectException(FlareException::class);
    $this->factory()->createFromFilterModel(self::model(['control' => 'gone', 'value' => '1']));
}

public function testControlForAnotherValueClassThrows(): void
{
    $this->expectException(FlareException::class);
    $this->factory()->createFromFilterModel(self::model(['control' => self::KEYWORDS_CONTROL, 'value' => '1']));
}

public function testUnreadableInitialValueKeepsTheControl(): void
{
    $filter = $this->factory()->createFromFilterModel(
        self::model(['control' => self::BOOL_CONTROL, 'value' => 'garbage']),
    );

    self::assertSame(self::BOOL_CONTROL, $filter->control?->type);
    self::assertNull($filter->value);
    $this->assertWarned();
}

public function testInitialValueFailingWithAPlainFilterExceptionKeepsTheControl(): void
// a provider throwing `new FilterException('x')` (not Unreadable…) for the stored value → control kept, value
// null, one warning. Use a dedicated provider stub registered for the bool value class in this test.

public function testWithoutValueAnUnknownControlThrows(): void
{
    $this->expectException(FlareException::class);
    $this->factory()->createFromFilterModel(self::model(['control' => 'gone']), withValue: false);
}
```

`testMissingValueColumnYieldsNoValue` keeps only `assertNull($filter->value)`.

- [ ] **Step 2: Run them, verify they fail**

Run: `make test tests/Filter/FilterFactoryTest.php`
Expected: FAIL — the unknown-control tests get a filter instead of an exception; the unreadable-value test finds
`control` null.

- [ ] **Step 3: Change `createFromFilterModel()`**

Resolve the control (`resolveControl()`), transform and resolve its config outside any `try`. Only for
`$withValue`, wrap `provideValue()` in `try … catch (FilterException $e)`: log the warning
`'[FLARE] Initial value of filter %s is ignored -- [Message] %s'` (source, message) and use `null`. Return
`$filter->withControl(new ControlBinding(...))->withValue($value)`. Update the PHPDoc: control failures throw
`FlareException`; an unreadable initial value (any `FilterException` from the provider, the public
`ValueProviderInterface::provide()` contract) only drops the value.

- [ ] **Step 4: Remove the flag**

Delete `Filter::$controlDropped`, `withDroppedControl()`, the `'controlDropped'` fingerprint key and the
pass-throughs in `withValue()`/`withControl()`/`withTargetAlias()`. `ArchiveFilterElement::isControlled()` becomes
`return $filter->control !== null;` (docblock: "Whether the filter has a control"). Remove the flag tests in
`FilterTest` (the `withDroppedControl` test and the fingerprint key in the fingerprint-keys test),
`ArchiveFilterElementTest` (the two `withDroppedControl()` uses: delete the dropped-control scenario at ~line 265
and the assertion at ~line 307), `ElementDcaListenerTest` (`assertFalse($scope->filter->controlDropped …)` line).

- [ ] **Step 5: Collector test**

In `tests/List/ListModelFilterCollectorTest.php` replace `testFailingControlIsAbsorbedAndTheFilterKeptWithoutIt`:

```php
public function testUnresolvableControlFailsTheList(): void
{
    $collected = $this->collector()->collectFromModels(
        [self::filterModel(1, 'stub', 'unknown_control')],
        self::listModel(),
    );

    self::assertSame([], $collected->filters);
    self::assertCount(1, $collected->failures);
    self::assertSame('error', $this->logs[0][0]);
}
```

(Before Task 2, `failures[0]` is a string; Task 2 adapts it.)

- [ ] **Step 6: Run the suite**

Run: `make test` → Expected: all pass. `grep -rn controlDropped src tests` → Expected: no output.

- [ ] **Step 7: Commit**

```bash
git add src tests dev/superpowers/specs/2026-10-27-filter-failures-design.md dev/superpowers/plans/2026-10-27-filter-failures.md
git commit -m "feat!: fail the list when a filter's stored control cannot be resolved"
```

---

### Task 2: `FilterFailure` value object

**Files:**
- Create: `src/List/FilterFailure.php`
- Modify: `src/List/Collector/CollectedFilters.php`, `src/List/Collector/ListModelFilterCollector.php` (`fail()`),
  `src/List/ListSpec.php` (`$failures`, `withFailure()`, `hash()`), `src/List/ListSpecBuilder.php`
  (`addFailure()`, `$failures`), `src/Query/Executor/ListQueryDirector.php` (`createQueryBuilder()` message join)
- Test: `tests/List/FilterFailureTest.php` (new), `tests/List/ListSpecTest.php`, `tests/List/ListSpecBuilderTest.php`,
  `tests/List/ListModelFilterCollectorTest.php`, `tests/Query/Executor/ListQueryDirectorTest.php`

**Interfaces:**
- Produces:
  ```php
  namespace HeimrichHannot\FlareBundle\List;
  /** @api */
  final readonly class FilterFailure {
      public function __construct(public ?string $source, public string $message, public ?\Exception $exception = null) {}
      /** The editor-facing cause: the wrapped FilterException's message for a "[FLARE] Query denied: …" wrapper
       *  (a FilterException whose previous is a FilterException), else the exception's message, else $message. */
      public function getReason(): string;
  }
  ```
  `CollectedFilters::$failures`, `ListSpec::$failures`: `list<FilterFailure>`;
  `ListSpec::withFailure(FilterFailure $failure): self`; `ListSpecBuilder::addFailure(FilterFailure $failure): self`.

- [ ] **Step 1: Write `tests/List/FilterFailureTest.php`**

```php
public function testReasonIsTheExceptionsMessage(): void
{
    $failure = new FilterFailure('tl_flare_filter.3', 'Filter … could not be created: x', new FlareException('x'));
    self::assertSame('x', $failure->getReason());
}

public function testReasonUnwrapsTheQueryDeniedWrapper(): void
{
    $cause = new FilterException('No parent archives are whitelisted.');
    $wrapper = new FilterException('[FLARE] Query denied: No parent … / Callback: X::buildContext', previous: $cause);
    self::assertSame('No parent archives are whitelisted.', (new FilterFailure('s', 'm', $wrapper))->getReason());
}

public function testReasonFallsBackToTheMessageWithoutException(): void
{
    self::assertSame('The alias "dup" is …', (new FilterFailure('s', 'The alias "dup" is …'))->getReason());
}
```

- [ ] **Step 2: Run, verify fail**

Run: `make test tests/List/FilterFailureTest.php` → Expected: FAIL, class `FilterFailure` not found.

- [ ] **Step 3: Implement `FilterFailure`, switch the carriers**

`ListModelFilterCollector::fail(FilterModel, ListModel, string $reason, ?\Exception $e = null): FilterFailure` —
same log line as today; source `"{$model::getTable()}.{$model->id}"`, message = today's string, exception = the
caught `FlareException` (null for the duplicate alias). `ListSpec::hash()` hashes failures as
`[$f->source, $f->message]`. `ListQueryDirector::createQueryBuilder()` joins
`array_map(static fn (FilterFailure $f): string => $f->message, $failures)` with `'; '` as today.

- [ ] **Step 4: Adapt existing tests**

Replace string failures with `new FilterFailure('tl_flare_filter.3', '…')` in `ListSpecTest` (~123–131),
`ListSpecBuilderTest` (~104), `ListQueryDirectorTest::testListFailuresFailTheQueryBeforeAnyFilterRuns`; in
`ListModelFilterCollectorTest` assert on `->source` (`'tl_flare_filter.1'`) and `->message`
(`assertStringContainsString`), and add to `testFailingFixedFilterIsRecordedAsListFailure`:
`self::assertInstanceOf(FlareException::class, $collected->failures[0]->exception);` and to the duplicate test
`self::assertNull($collected->failures[0]->exception); self::assertSame('tl_flare_filter.2', $collected->failures[0]->source);`

- [ ] **Step 5: Run the suite, PHPStan**

Run: `make test` → all pass. `make phpstan` → no errors.

- [ ] **Step 6: Commit**

```bash
git add src tests
git commit -m "feat!: record filter failures of a list as structured FilterFailure objects"
```

---

### Task 3: Extract `FilterInvoker`

**Files:**
- Create: `src/Query/Executor/FilterInvoker.php`
- Modify: `src/Query/Executor/ListQueryDirector.php` (constructor, `invokeFilters()`; remove
  `buildFilterConditionsBuilders()`, `wrapFilterFailure()`, the `CreatesFilterExceptionTrait` use)
- Test: `tests/Query/Executor/FilterInvokerTest.php` (new), `tests/Query/Executor/ListQueryDirectorTest.php`
  (`createDirector()` only)

**Interfaces:**
- Produces:
  ```php
  /** @internal */
  final readonly class FilterInvoker {
      public function __construct(FilterContextFactory $filterContextFactory, FilterQueryBuilderFactory $filterQueryBuilderFactory) {}
      /** @return list<FilterConditionsBuilder>
       *  @throws AbortFilteringException @throws FilterException wrapped with the filter's source @throws FlareException */
      public function invoke(ElementScope $scope, ?ValueInterface $value): array;
  }
  ```
  `ListQueryDirector::__construct(EventDispatcherInterface $eventDispatcher, FilterInvoker $filterInvoker,
  ListExecutionContextFactory $listExecutionContextFactory, QueryBuilderFactory $queryBuilderFactory,
  LoggerInterface $logger)`. Autowired (`config/services.yaml` `_defaults`), no config change expected.

- [ ] **Step 1: Write `tests/Query/Executor/FilterInvokerTest.php`**

Using `BuildsFilterContextsTrait` and stub elements like the director test:
- `testBuildsOneConditionsBuilderPerPredicateCall` — element adds two `BooleanPredicate` calls → `count(...) === 2`.
- `testFilterExceptionIsWrappedWithTheSource` — element throws `new FilterException('boom')`, filter source
  `tl_flare_filter.9` → caught `FilterException` with `getSource() === 'tl_flare_filter.9'`,
  `getPrevious()->getMessage() === 'boom'`.
- `testOtherExceptionsBecomeFilterExceptions` — `\RuntimeException('raw')` → `FilterException`, message `'raw'`.
- `testAbortPassesThrough` — element calls `$builder->abort()` → `AbortFilteringException`.

- [ ] **Step 2: Run, verify fail**

Run: `make test tests/Query/Executor/FilterInvokerTest.php` → Expected: FAIL, class not found.

- [ ] **Step 3: Move the code**

`FilterInvoker::invoke()` is today's `ListQueryDirector::buildFilterConditionsBuilders()` plus
`wrapFilterFailure()` (moved verbatim, with the trait). The director calls `$this->filterInvoker->invoke(...)`
in both places of `invokeFilters()`; its retry logic is unchanged. `ListQueryDirectorTest::createDirector()`
builds `new FilterInvoker($this->createFilterContextFactory(), new FilterQueryBuilderFactory($connection))`.

- [ ] **Step 4: Run the suite**

Run: `make test` → all pass (the director tests unchanged apart from `createDirector()`). `make phpstan` clean.

- [ ] **Step 5: Commit**

```bash
git add src tests
git commit -m "refactor: extract building a filter's conditions into FilterInvoker"
```

---

### Task 4: `BrokenFilterInspector`

**Files:**
- Create: `src/DataContainer/BrokenFilterInspector.php`, `src/DataContainer/FilterDiagnosis.php`
- Test: `tests/DataContainer/BrokenFilterInspectorTest.php`

**Interfaces:**
- Consumes: `FilterFailure` (Task 2), `FilterInvoker::invoke()` (Task 3), `FilterFactory::createFromFilterModel()`.
- Produces:
  ```php
  /** @internal */
  final readonly class FilterDiagnosis {
      /** @param array<int, FilterFailure> $rows by tl_flare_filter id @param list<FilterFailure> $listFailures */
      public function __construct(public array $rows = [], public array $listFailures = []) {}
      public function forRow(int $id): ?FilterFailure;
  }

  /** @internal */
  final class BrokenFilterInspector {   // not readonly: memo
      public function __construct(ListSpecBuilderFactory $listSpecBuilderFactory, FilterFactory $filterFactory, FilterInvoker $filterInvoker) {}
      /** Memoized per list id for the request; builds the spec and loads the unpublished rows
       *  (FilterModel::findByPid((int) $listModel->id, published: false)), then diagnose(). */
      public function inspect(ListModel $listModel): FilterDiagnosis;
      /** @param \Closure(): ListSpec $getSpec @param iterable<FilterModel> $unpublished */
      public function diagnose(\Closure $getSpec, iterable $unpublished): FilterDiagnosis;
  }
  ```

Algorithm of `diagnose()` (design Part 4):
1. `$spec = $getSpec()` inside `try … catch (\Exception)` → on failure return `new FilterDiagnosis()`.
2. Every `$spec->failures` entry goes to `listFailures`, and to `rows[id]` when its source is
   `tl_flare_filter.<id>` (parse with `FilterModel::getTable()` prefix; anything else maps to no row).
3. Every `$spec->filters` entry without a failure yet: `invoke(new ElementScope($spec, $filter, new
   ConfigurationContext()), $filter->control !== null ? null : $filter->value)`. `AbortFilteringException` → fine.
   Other `\Exception` → `new FilterFailure($filter->source, $e->getMessage(), $e)` into `listFailures` and, if the
   source maps, `rows`. First failure per row wins.
4. Every unpublished model: `createFromFilterModel($model)` (catch `\Exception` → failure with the source
   `tl_flare_filter.<id>` into `rows` only), then step 3's check into `rows` only — never `listFailures`.

- [ ] **Step 1: Write `tests/DataContainer/BrokenFilterInspectorTest.php`**

Build a real `FilterFactory` like `IneffectiveFilterInspectorTest::inspector()` (registries with stub elements:
`ok` adds a predicate, `throws` throws `FilterException('broken')`, `aborts` calls `abort()`, `raw` throws
`\RuntimeException('raw')`, `error` throws `new \Error('bug')`, `value_sensitive` throws only for a
non-null value) and a real `FilterInvoker`; specs as `new ListSpec(...)` with `Filter`s whose `source` is
`tl_flare_filter.<id>`; models via reflection like `IneffectiveFilterInspectorTest::model()`. Tests:

```php
testHealthyFiltersAreNotMarked                     // rows === [], listFailures === []
testCollectionFailuresMarkTheirRows                // spec->withFailure(new FilterFailure('tl_flare_filter.4', 'The alias "dup" …'))
                                                   //   → forRow(4)->getReason() === 'The alias "dup" …', count(listFailures) === 1
testAFilterFailingWithItsDefaultValueIsMarked      // fixed filter of `throws` → forRow(id)->getReason() === 'broken', in listFailures
testControlledFilterIsCheckedWithoutItsInitialValue // `value_sensitive` with a control and a value → not marked
testAFixedValueIsCheckedAsTheFixedValue            // `value_sensitive` without a control, with a value → marked
testAnAbortingFilterIsNotBroken                    // `aborts` → not marked
testAnUnexpectedExceptionMarksTheFilter            // `raw` → reason 'raw'
testErrorsEscapeTheCheck                           // `error` → expectException(\Error::class)
testFailuresOfInlineFiltersFailTheListWithoutARow  // source null, `throws` → rows === [], count(listFailures) === 1
testUnpublishedFiltersAreMarkedButDoNotFailTheList // model of unknown type 'gone' + model of `throws` → both rows marked, listFailures === []
testASpecThatCannotBeBuiltMarksNothing             // getSpec throws FlareException → new FilterDiagnosis() equivalent
```

- [ ] **Step 2: Run, verify fail**

Run: `make test tests/DataContainer/BrokenFilterInspectorTest.php` → Expected: FAIL, classes not found.

- [ ] **Step 3: Implement `FilterDiagnosis` and `BrokenFilterInspector` per the algorithm above**

`inspect()` passes `fn (): ListSpec => $this->listSpecBuilderFactory->createFromListModel($listModel)->build()`.
Memo key: `(int) $listModel->id`. Note: building the spec logs collection failures as errors (as in the
frontend); accepted.

- [ ] **Step 4: Run tests, PHPStan, Mago**

Run: `make test` → all pass. `make phpstan` clean. `mago lint` → no issues.

- [ ] **Step 5: Commit**

```bash
git add src/DataContainer tests/DataContainer
git commit -m "feat: find the filters that break their list for the backend"
```

---

### Task 5: Backend display

**Files:**
- Modify: `src/EventListener/DataContainer/FlareFilter/ListCallbacks.php` (pass `broken_reason`)
- Modify: `templates/backend/filter_child_record/_base.html.twig` (bone-fracture block)
- Modify: `src/EventListener/Contao/ElementDcaListener.php` (overview count, edit-form error)
- Modify: `translations/flare.en.yaml`, `translations/flare.de.yaml`
- Already modified (commit here): `public/backend/styles.css`, the template's opacity variables
- Test: none new — there is no unit-test harness for the Contao callbacks; the logic is covered by Task 4,
  the wiring is verified manually (Step 4)

**Interfaces:**
- Consumes: `BrokenFilterInspector::inspect(ListModel): FilterDiagnosis`, `FilterDiagnosis::forRow()`,
  `$listFailures`, `FilterFailure::getReason()`.

- [ ] **Step 1: Translations (exact copy)**

`flare.en.yaml`:
- `list.info.broken_filters`: `"{1} One filter breaks the list; it cannot be created until the filter is fixed. It is marked in the list below.|]1,Inf[ %count% filters break the list; it cannot be created until they are fixed. They are marked in the list below."`
- `filter.info.broken.published`: `"This filter breaks the list: %reason%"`
- `filter.info.broken.unpublished`: `"Once published, this filter would break the list: %reason%"`

`flare.de.yaml`:
- `list.info.broken_filters`: `"{1} Ein Filter verhindert, dass die Liste erstellt werden kann, bis er korrigiert ist; er ist in der Liste unten markiert.|]1,Inf[ %count% Filter verhindern, dass die Liste erstellt werden kann, bis sie korrigiert sind; sie sind in der Liste unten markiert."`
- `filter.info.broken.published`: `"Dieser Filter verhindert, dass die Liste erstellt werden kann: %reason%"`
- `filter.info.broken.unpublished`: `"Veröffentlicht würde dieser Filter verhindern, dass die Liste erstellt werden kann: %reason%"`

- [ ] **Step 2: Filter list**

`ListCallbacks` gets `BrokenFilterInspector` injected. In `listLabelLabel()`: load the list model by
`$row['pid']` (`ListModel::findByPk`), `$failure = $inspector->inspect($list)->forRow((int) $row['id'])`, pass
`'broken_reason' => $failure ? trans('filter.info.broken.' . ($isPublished ? 'published' : 'unpublished'),
['%reason%' => $failure->getReason()], 'flare') : null`. Template: after the engine block, an independent
`{% if broken_reason|default %}` with `<span style="color: #dc3545; opacity: var(--flare-filter-notice-opacity)">`
and the Lucide `bone-fracture` SVG from the design (class `lucide-bone-fracture-icon`, `width/height="1rem"`,
`<title>{{ broken_reason }}</title>`).

- [ ] **Step 3: Overview count and edit-form error in `ElementDcaListener`**

Inject `BrokenFilterInspector`. Next to `reportIneffectiveFilters()`, same request gating (child view of
`tl_flare_filter`, no `act`): `if ($n = count($inspector->inspect($listModel)->listFailures))
Message::addError(trans('list.info.broken_filters', ['%count%' => $n], 'flare'))`. In `configure()`, beside the
ineffective edit-form message (`$isEditAction && $filterModel !== null`): if
`$failure = $inspector->inspect($listModel)->forRow((int) $filterModel->id)`, `Message::addError` with the
`filter.info.broken.{published|unpublished}` text by `$filterModel->published`.

- [ ] **Step 4: Verify**

Run: `make test`, `make phpstan`, `mago lint` → clean. Manual (user's Contao instance): a list with a filter of an
unknown element type (published) shows the red overview message, the bone-fracture icon at full opacity, and the
edit-form error; unpublished, the icon is muted and there is no overview message; a healthy list shows none.

- [ ] **Step 5: Commit**

```bash
git add src templates public translations tests
git commit -m "feat: mark filters that break their list in the backend"
```

---

### Task 6: Docs

**Files:**
- Modify: `AGENTS.md` (architecture paragraph: "a control that cannot be resolved only drops the control and
  initial value (warning)" → fails the list; an unreadable initial value only drops the value; mention
  `BrokenFilterInspector` next to `IneffectiveFilterInspector` in `src/Contract/FilterElement/`'s bullet)
- Modify: `dev/superpowers/specs/2026-10-14-filter-forms-design.md` §8.1 (same rule change)
- Modify (docs worktree `docs/`): `docs/spec/filtering.md` §6 table — the control row becomes fail closed for an
  unresolvable stored control / broken control config; new row "initial value unreadable → control kept, value
  dropped (warning)"; a paragraph that the backend marks such filters (bone-fracture icon, overview error,
  edit-form error), unpublished ones only with the icon. `docs/migrating-from-v0.1.md`: a list whose stored control
  no longer exists now fails instead of losing the field. `docs/dev/filter-elements/index.md`: next to the
  ineffective marks, one sentence on broken marks and that `FilterException` messages are shown to editors.

- [ ] **Step 1: Edit the files**
- [ ] **Step 2: Build the docs** — Run: `cd docs && npm run build` → Expected: success, no broken links.
- [ ] **Step 3: Commit both**

```bash
git add AGENTS.md dev/superpowers/specs/2026-10-14-filter-forms-design.md && git commit -m "docs: unresolvable controls fail the list; broken filter marks"
git -C docs add -A && git -C docs commit -m "docs: unresolvable controls fail the list; broken filter marks"
```
