# Contao Namespace Consolidation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move the Contao-specific backend code into `src/Contao/`, split the `FilterModel` → `Filter` translation
out of the core `FilterFactory`, record the value class on `ElementBinding` and make the DCA builder API
collect-only.

**Architecture:** Mechanical moves first (no behavior change), then the small API changes one by one: callback
transformers removed, DCA builder tightened, value-class check in `Filter` throwing an `\Error`, a generic
`$configSource` on the core `FilterFactory::create()`, a Contao `FilterFactory` on top of it, and the collector plus
`createFromListModel()` moved into `Contao/Factory/`.

**Tech Stack:** PHP 8.2, Symfony 5.4–7, Contao 4.13/5.x, PHPUnit 9, PHPStan level 5, Mago.

**Spec:** `dev/superpowers/specs/2026-11-03-contao-namespace-design.md` (approved by the user 2026-11-03). Read it
before every task — the plan does not repeat its reasoning.

## Global Constraints

- Branch `v0.2-contao-namespace`. Unreleased major: no BC shims, no `class_alias`, no deprecation layers.
- `ListSpec`, `ListSpecBuilder`, `ListSpecFactory`, `BaseListOptions::transform()` stay untouched (they keep
  `ListModel`); `ReaderRequestAttribute*`, controllers, `Migration/`, `Util/*`, `ContaoManager/`, models, elements,
  controls, drivers, value providers, contracts and events stay in place (imports are updated, nothing else).
- Catch `\Exception`, never `\Throwable`; `\Error`s escape (the inspector's `\Error` catch outside debug mode stays).
- `declare(strict_types=1);`, PER style, 120-char lines; `mago lint --minimum-fail-level note` (binary installed
  locally) must pass before each commit.
- Tests: `make test` (all) / `make test tests/Path/SomeTest.php`; static analysis: `make phpstan`. Both green at the
  end of every task.
- Production code testable through its public API; no reflection on private members in tests.
- Commits: conventional style, **no `Co-Authored-By` trailer**. Do not push without the user's go (the draft PR
  exists; the user decides when implementation commits go up).
- Moving a file: `git mv` (keeps history), then fix namespace, imports and every reference
  (`grep -rn 'Old\\Namespace' src tests contao config`).
- Docs in the `docs/` worktree (branch `docs/main`) are committed separately there; `npm run build` in `docs/` must
  pass.

## Review Focus

- A filter whose value is set to `null` (or never set) on an element without a value class must keep working through
  every `with*()` — the new constructor check must not reject `null` (test in Task 5).
- A `FilterCollectedEvent` listener that sets a value of the wrong class must escape the collector as
  `ValueClassMismatchError`, not be recorded as a `FilterFailure` (test in Task 8).
- Opening the backend edit form of a controlled filter with a legacy alias (`price.min`) must not throw: the element
  scope is `null` (test in Task 7).
- A config source plus explicit `$config`: explicit keys win, untouched source keys survive, and the control's
  transformer runs on the same source (test in Task 6).
- An unreadable initial value of a controlled filter must still only warn and keep the control — the value check
  must not turn stored data into an `\Error` (moved test in Task 7).

---

### Task 1: Move DataContainer, Inspection and InferPtable into `Contao/`; services.yaml

**Files:**
- Move (`git mv`): `src/DataContainer/{ContentContainer,FilterContainer,ListContainer,BackendElementScopeFactory}.php`
  → `src/Contao/DataContainer/`; `src/DataContainer/Builder/*` → `src/Contao/DataContainer/Builder/` (unchanged for
  now, incl. `DcaContext`); `src/DataContainer/Inspection/**` → `src/Contao/Inspection/**`;
  `src/InferPtable/{PtableInferrer,PtableInferrable,PtableInferrableInterface}.php` → `src/Contao/PtableInference/`,
  `src/InferPtable/Factory/PtableInferrableFactory.php` → `src/Contao/PtableInference/Factory/`
- Modify: `config/services.yaml`; every importer (models, `Model/PtableInferrableTrait`, elements, drivers, callbacks,
  hooks, controllers, migrations, integrations, `contao/dca/*.php`, `contao/**` templates/config if they reference
  them)
- Move tests: `tests/DataContainer/**` → `tests/Contao/DataContainer/**` and `tests/Contao/Inspection/**`
  (incl. `Inspection/Stub/`), namespaces `HeimrichHannot\FlareBundle\Tests\Contao\…`
- Test: `tests/DependencyInjection/ServicesConfigTest.php` (new)

**Interfaces:**
- Produces: namespaces `HeimrichHannot\FlareBundle\Contao\DataContainer\…`,
  `…\Contao\DataContainer\Builder\…`, `…\Contao\Inspection\…`, `…\Contao\PtableInference\…`. Class names unchanged.

- [ ] **Step 1: Write the failing test** `ServicesConfigTest`: load `config/services.yaml` into a `ContainerBuilder`
  via `YamlFileLoader(new FileLocator(__DIR__ . '/../../config'))`. Tests:
  - `testContaoServicesAreRegistered`: `hasDefinition()` is true for `Contao\Inspection\Inspector\FilterInspector`,
    `Contao\Inspection\Reporter\FilterIssueReporter`, `Contao\DataContainer\BackendElementScopeFactory`,
    `Contao\DataContainer\ListContainer`, `Contao\DataContainer\FilterContainer`; and
    `getDefinition(FilterInspector::class)->getArgument('$debug') === '%kernel.debug%'`.
  - `testContaoNonServicesAreNotRegistered`: `hasDefinition()` is false for `Contao\BackendModule`,
    `Contao\DataContainer\Builder\DcaBuilder`, `Contao\DataContainer\Builder\DcaContext`,
    `Contao\Inspection\FilterIssue`, `Contao\Inspection\ListInspection`, `Contao\PtableInference\PtableInferrer`,
    `Contao\PtableInference\PtableInferrable`.
  Later tasks extend both lists.
- [ ] **Step 2: Run** `make test tests/DependencyInjection/ServicesConfigTest.php` — expected FAIL (classes not found /
  definitions missing).
- [ ] **Step 3: Move the files and fix all references.** `services.yaml` exactly as in the spec's "services.yaml"
  section (main block without the two `DataContainer` excludes and without `InferPtable`; new
  `HeimrichHannot\FlareBundle\Contao\:` block excluding `../src/Contao/*.php`, `../src/Contao/DataContainer/Builder`,
  `../src/Contao/Inspection/*.php`, `../src/Contao/PtableInference/*.php`; the `FilterInspector` `$debug` entry under
  its new FQCN). `src/DataContainer/` and `src/InferPtable/` are gone afterwards.
- [ ] **Step 4: Verify no stale references:** `grep -rnE 'FlareBundle\\\\(DataContainer|InferPtable)\\\\' src tests
  contao config` → no output. `make test` and `make phpstan` green; `mago lint --minimum-fail-level note` clean.
- [ ] **Step 5: Commit** `refactor: move DataContainer, Inspection and InferPtable into the Contao namespace`

### Task 2: Hooks into `Contao/Hook/`, callbacks into `Contao/Callback/`

**Files:**
- Move + rename: `src/EventListener/Contao/BreadcrumbListener.php` → `src/Contao/Hook/BreadcrumbHook.php`,
  `ElementDcaListener.php` → `src/Contao/Hook/ElementDcaHook.php`, `FormNameRegexpListener.php` →
  `src/Contao/Hook/FormNameRegexpHook.php`
- Move: `src/EventListener/DataContainer/FlareFilter/*` → `src/Contao/Callback/FlareFilter/*`,
  `src/EventListener/DataContainer/FlareList/*` → `src/Contao/Callback/FlareList/*` (class names unchanged)
- Create: `src/Contao/Callback/FlareList/ConfigOnSubmitCallback.php` (from `ListContainer::onSubmitConfig()`)
- Modify: `src/Contao/DataContainer/ListContainer.php` (remove `onSubmitConfig()` and the now unused
  `ListDriverRegistry` dependency), `contao/config/config.php` or other places naming the hook classes (grep)
- Move tests: `tests/EventListener/Contao/ElementDcaListenerTest.php` → `tests/Contao/Hook/ElementDcaHookTest.php`,
  `FormNameRegexpListenerTest.php` → `tests/Contao/Hook/FormNameRegexpHookTest.php`,
  `tests/EventListener/DataContainer/**` → `tests/Contao/Callback/**`
- Test: `tests/DependencyInjection/ServicesConfigTest.php`

**Interfaces:**
- Produces: `Contao\Hook\{BreadcrumbHook,ElementDcaHook,FormNameRegexpHook}`,
  `Contao\Callback\FlareFilter\*`, `Contao\Callback\FlareList\*`,
  `Contao\Callback\FlareList\ConfigOnSubmitCallback` with
  `#[AsCallback(ListContainer::TABLE_NAME, 'config.onsubmit')] public function __invoke(DataContainer $dc): void`
  (body = today's `onSubmitConfig()`; constructor `Connection $connection, ListDriverRegistry $listDriverRegistry`).

- [ ] **Step 1: Extend `testContaoServicesAreRegistered`** with `Contao\Hook\ElementDcaHook`,
  `Contao\Hook\BreadcrumbHook`, `Contao\Hook\FormNameRegexpHook`, `Contao\Callback\FlareList\ConfigOnSubmitCallback`,
  `Contao\Callback\FlareFilter\FieldsOptionsCallbacks`; add
  `testHooksAndCallbacksKeepTheirAttributes`: `(new \ReflectionClass(ElementDcaHook::class))->getAttributes(AsHook::class)[0]->getArguments()`
  equals `['loadDataContainer', 'priority' => -100]`; `ConfigOnSubmitCallback::__invoke` carries one `AsCallback`
  with arguments `[ListContainer::TABLE_NAME, 'config.onsubmit']`.
- [ ] **Step 2: Run** the test — expected FAIL.
- [ ] **Step 3: Move, rename, extract** as listed; rename the test classes accordingly.
- [ ] **Step 4: Verify:** `grep -rnE 'EventListener\\\\(Contao|DataContainer)\\\\|ElementDcaListener|BreadcrumbListener|FormNameRegexpListener|onSubmitConfig' src tests contao config`
  → no output; `src/EventListener/Contao/` and `src/EventListener/DataContainer/` are gone. `make test`,
  `make phpstan`, mago green.
- [ ] **Step 5: Commit** `refactor: move Contao hooks and DCA callbacks into the Contao namespace`

### Task 3: Remove the callback model transformers

**Files:**
- Delete: `src/Filter/CallbackFilterModelTransformer.php`, `src/List/CallbackListModelTransformer.php`
- Modify: `src/Filter/Element/AbstractFilterElement.php`, `src/Filter/Control/AbstractFilterControl.php`,
  `src/List/Driver/AbstractListDriver.php` (`configureTransformers()`)
- Test: `tests/Filter/FilterTransformerResolverTest.php`, `tests/List/ListTransformerResolverTest.php` (extend)

**Interfaces:** none new. Call form, keeping the named arguments (user's requirement):
```php
$transformers->set(
    sourceClass: FilterModel::class,
    transformer: $this->transformFilterModel(...),
);
```
(`ListModel::class` / `$this->transformListModel(...)` in `AbstractListDriver`.)

- [ ] **Step 1: Write the tests** `testAbstractElementTransformsAFilterModel` (an anonymous `AbstractFilterElement`
  whose `transformFilterModel()` sets `label` from `$model->title`; `FilterTransformerResolver::transform()` with a
  `FilterModel` row `['title' => 'T']` returns `['label' => 'T']`) and `testAbstractElementIgnoresOtherSources`
  (an `\stdClass` source returns `null`); the same pair for `AbstractListDriver` in `ListTransformerResolverTest`.
  Build models like `FilterFactoryTest::model()` does. These pass today — they pin the behavior across the change.
- [ ] **Step 2: Run** both test files — expected PASS.
- [ ] **Step 3: Replace the transformer wrappers** with the call form above; delete both classes.
- [ ] **Step 4: Run** `make test`, `make phpstan`, mago — green; `grep -rn 'Callback\(Filter\|List\)ModelTransformer' src tests`
  → no output.
- [ ] **Step 5: Commit** `refactor: pass the model transformers to the TransformerMap directly`

### Task 4: Collect-only DCA builder API; `DcaContext` into the `Contao` root

**Files:**
- Move: `src/Contao/DataContainer/Builder/DcaContext.php` → `src/Contao/DcaContext.php`
- Rename: `src/Contao/DataContainer/Builder/ControlScopeDcaBuilder.php` → `ControlDcaBuilder.php`
- Modify: `DcaBuilderInterface.php`, `DcaFieldBuilderInterface.php`, `DcaBuilder.php`, `ControlDcaBuilder.php`,
  `src/Event/FilterElementBuildDcaEvent.php`, `src/Event/ListDriverBuildDcaEvent.php`,
  `src/Contao/Hook/ElementDcaHook.php`, every `DcaContext` importer (≈32 files: `Contract/DcaContract`, elements,
  controls, drivers, value providers, events, integrations)
- Test: `tests/Contao/DataContainer/Builder/DcaBuilderTest.php`, `tests/EventListener/NamedDispatch/DcaEventListenerTest.php`,
  `tests/DependencyInjection/ServicesConfigTest.php`

**Interfaces:**
- Produces:
  - `DcaBuilderInterface`: exactly `palette(?string): self`, `getPalette(): ?string`,
    `prefix(string|callable|null): self`, `suffix(string|callable|null): self`, `field(string): DcaFieldBuilderInterface`,
    `selector(string $field, string $palette, ?string $value = null): self`.
  - `DcaFieldBuilderInterface`: today's methods without `applyTo()`.
  - `DcaBuilder` (concrete): additionally `composePrefix(callable): self`, `forControl(): DcaBuilderInterface`
    (replaces `scope('control')`), `apply(string $table, string $type, bool $applyPalette = true): void`.
  - `DcaFieldBuilder` (concrete): keeps `applyTo(array &$definition): void`.
  - `ControlDcaBuilder` (`@internal`, created by `DcaBuilder::forControl()`): implements the slim interface.
  - `FilterElementBuildDcaEvent::$builder` and `ListDriverBuildDcaEvent::$builder` typed `DcaBuilderInterface`.
  - `HeimrichHannot\FlareBundle\Contao\DcaContext` (content unchanged).

- [ ] **Step 1: Write the failing tests** in `DcaBuilderTest`:
  - `testPublicInterfacesAreCollectOnly`: method names of `DcaBuilderInterface` (via `ReflectionClass::getMethods()`,
    sorted) equal `['field', 'getPalette', 'palette', 'prefix', 'selector', 'suffix']`; those of
    `DcaFieldBuilderInterface` do not contain `applyTo`.
  - Replace `scope('control')` with `forControl()` in `testSegmentsMergeInPrefixElementControlSuffixOrder`,
    `testMissingSegmentsAreDropped`, `testControlScopeSharesFieldsWithParent`; delete `testUnknownScopeIsRejected`.
  - In `ServicesConfigTest::testContaoNonServicesAreNotRegistered` replace the `DcaContext` FQCN with
    `Contao\DcaContext` and add `Contao\DataContainer\Builder\ControlDcaBuilder`.
- [ ] **Step 2: Run** `make test tests/Contao/DataContainer/Builder/DcaBuilderTest.php` — expected FAIL.
- [ ] **Step 3: Implement** the interface changes, `forControl()`, the rename, the event types and the `DcaContext`
  move; `ElementDcaHook` uses `forControl()`. Update `DcaEventListenerTest` (`(new DcaBuilder())->forControl()`).
- [ ] **Step 4: Run** `make test`, `make phpstan`, mago — green;
  `grep -rn "scope('control')\|Builder\\\\DcaContext\|ControlScopeDcaBuilder" src tests` → no output.
- [ ] **Step 5: Commit** `refactor: make the DCA builder API collect-only and move DcaContext to the Contao root`

### Task 5: `ElementBinding::$valueClass` and `ValueClassMismatchError`

**Files:**
- Create: `src/Exception/ValueClassMismatchError.php`
- Modify: `src/Filter/ElementBinding.php`, `src/Filter/Filter.php` (constructor check), `src/Filter/Factory/FilterFactory.php`
  (fill `valueClass`, remove its own value check, `@throws`), all `new ElementBinding(` fixtures (≈30, list via
  `grep -rln 'new ElementBinding' tests`)
- Test: `tests/Filter/FilterTest.php`, `tests/Filter/FilterFactoryTest.php`, `tests/List/ListSpecBuilderTest.php`

**Interfaces:**
- Produces:
  - `ElementBinding::__construct(FilterElementInterface $component, string $type, ComponentConfig $config, ?string $valueClass)`
    — `$valueClass` is `class-string<ValueInterface>|null`, required, no default (docblock from the spec).
  - `final class ValueClassMismatchError extends \Error` with
    `__construct(ValueInterface $value, ?string $valueClass, string $type, ?string $source = null)`; message:
    `The value of class "%s" does not match the value class "%s" of filter element type "%s"%s.` with
    `$value::class`, `$valueClass ?? 'none'`, `$type`, `$source ? " ({$source})" : ''`. Docblock as in the spec.
  - `Filter::__construct()` throws it when `$value !== null` and (`$element->valueClass === null` or
    `!$value instanceof $element->valueClass`), passing `$element->type` and `$source`.

- [ ] **Step 1: Write the failing tests**
  - `FilterTest`: `testValueOfTheWrongClassIsRejected` (binding with `BoolValue::class`;
    `->withValue(new KeywordsValue('x'))` throws `ValueClassMismatchError`, message contains `"BoolValue"` class
    name and the filter's source); `testValueOnAnElementWithoutValueClassIsRejected` (binding `valueClass: null`,
    `withValue(new BoolValue(true))` throws); `testNullValueIsAlwaysAccepted` (both bindings: `withValue(null)`,
    `withControl(null)`, `withTargetAlias('t')` do not throw, `value` stays `null`);
    `testConstructorRejectsAMismatchingValue` (`new Filter(element: <BoolValue binding>, alias: 'a', value: new KeywordsValue('x'))` throws).
  - `FilterFactoryTest`: in `testProgrammaticValueOfTheWrongClassIsRejected`,
    `testValueClassIsReadFromTheAttributeOfAnElementInstance`, `testElementWithoutValueClassRejectsAnyValue` expect
    `ValueClassMismatchError` instead of `FlareException`; new `testCreateRecordsTheValueClassOnTheBinding`
    (`create(element: self::BOOL_ELEMENT)->element->valueClass === BoolValue::class`;
    `create(element: new FactoryStubPlainElement())->element->valueClass === null`).
  - `ListSpecBuilderTest`: `testListenerSettingAWrongValueFailsWithAnError` — a `ListDriverBuildListEvent` listener
    replacing a filter with `$filter->withValue(<wrong class>)`; `build()` throws `ValueClassMismatchError`.
- [ ] **Step 2: Run** these files — expected FAIL (unknown class / unknown argument).
- [ ] **Step 3: Implement** as listed; update every `ElementBinding` fixture with the value class its element
  consumes (`null` for intrinsic stubs).
- [ ] **Step 4: Run** `make test`, `make phpstan`, mago — green.
- [ ] **Step 5: Commit** `feat: record the value class on ElementBinding and reject mismatching values with an Error`

### Task 6: `$configSource` on the core `FilterFactory::create()`

**Files:**
- Modify: `src/Filter/Factory/FilterFactory.php` (`create()`, docblocks)
- Test: `tests/Filter/FilterFactoryTest.php`

**Interfaces:**
- Consumes: Task 5's `ElementBinding`.
- Produces: `FilterFactory::create(FilterElementInterface|string $element, ?string $alias = null, array $config = [],
  FilterControlInterface|string|null $control = null, array $controlConfig = [], ?ValueInterface $value = null,
  ?string $targetAlias = null, bool $targetingForced = false, ?object $configSource = null, ?string $source = null): Filter`.
  With a source: element config = `[...($this->filterTransformerResolver->transform($element, $type, $configSource, $source) ?? []), ...$config]`,
  the same for the control with its type and `$controlConfig`; then `FilterOptionsResolver::resolve()` as today.
  `createFromFilterModel()` stays until Task 7.

- [ ] **Step 1: Write the failing tests** with a new stub `FactoryStubSourcedElement` (registered under
  `sourced_element`, value `BoolValue::class`; option `label` default `''`; transformer
  `\ArrayObject::class => fn (ConfigBuilder $c, \ArrayObject $s) => $c->set('label', $s['label'])`) and
  `FactoryStubSourcedControl` (same pattern, option `placeholder`, registered for `BoolValue::class`):
  - `testConfigSourceIsTransformedForElementAndControl`: `create(element: 'sourced_element', alias: 'a', control: 'sourced_control', configSource: new \ArrayObject(['label' => 'L', 'placeholder' => 'P']))`
    → `element->config->get('label') === 'L'`, `control->config->get('placeholder') === 'P'`.
  - `testExplicitConfigWinsOverTheConfigSource`: same plus `config: ['label' => 'X']` → `'X'`; `placeholder` still `'P'`.
  - `testWithoutConfigSourceNoTransformerRuns`: `create(element: 'sourced_element')` → `label === ''`.
  - `testUnmatchedConfigSourceLeavesTheConfigAlone`: `configSource: new \stdClass()` → `label === ''`.
- [ ] **Step 2: Run** `make test tests/Filter/FilterFactoryTest.php` — expected FAIL (unknown named parameter).
- [ ] **Step 3: Implement** `$configSource` in `create()`.
- [ ] **Step 4: Run** `make test`, `make phpstan`, mago — green; check no caller passes `$source` positionally
  (`grep -rn 'filterFactory->create(' src` and read each call).
- [ ] **Step 5: Commit** `feat: let FilterFactory::create() read component config from a transformer source`

### Task 7: Contao `FilterFactory`; drop the legacy-alias leniency

**Files:**
- Create: `src/Contao/Factory/FilterFactory.php`
- Modify: `src/Filter/Factory/FilterFactory.php` (remove `createFromFilterModel()`, `provideInitialValue()`,
  `provideValue()`, the `ValueProviderRegistry`/`LoggerInterface` dependencies if unused afterwards, the
  `FilterModel` import), `src/Contao/DataContainer/BackendElementScopeFactory.php`,
  `src/Contao/Inspection/Inspector/FilterInspector.php`, `src/List/Collector/ListModelFilterCollector.php`
  (switch to the Contao factory; it moves in Task 8)
- Create test: `tests/Contao/Factory/FilterFactoryTest.php` (model tests moved out of `tests/Filter/FilterFactoryTest.php`
  with their stubs, or the stubs shared via a trait/fixture file)
- Modify tests: `tests/Filter/FilterFactoryTest.php`, `tests/Contao/DataContainer/BackendElementScopeFactoryTest.php`,
  `tests/Contao/Inspection/BuildsFilterInspectorTrait.php`, `tests/List/ListModelFilterCollectorTest.php`,
  `tests/DependencyInjection/ServicesConfigTest.php`

**Interfaces:**
- Consumes: Task 6's `create(..., configSource:, source:)`, Task 5's `$filter->element->valueClass`.
- Produces: `HeimrichHannot\FlareBundle\Contao\Factory\FilterFactory` —
  `__construct(\HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory $filterFactory, ValueProviderRegistry $valueProviderRegistry, LoggerInterface $logger)`,
  `public function create(FilterModel $model, bool $withValue = true): Filter` (body as in the spec's sketch;
  `provideValue()`/`provideInitialValue()` private, same semantics and log messages as today, provider looked up by
  `$filter->element->valueClass`, called with `$model->value` and `$filter->element->component`). Docblock: today's
  failure contract of `createFromFilterModel()` without the legacy-alias sentence.

- [ ] **Step 1: Move the tests.** Every test of `tests/Filter/FilterFactoryTest.php` that calls
  `createFromFilterModel()` moves to `tests/Contao/Factory/FilterFactoryTest.php` and calls
  `(new ContaoFilterFactory($core, $providers, $logger))->create(...)` instead; the core test keeps the `create()`
  tests. Change `testWithoutValueAControlledFilterWithAnInvalidAliasStillResolves` into
  `testWithoutValueAControlledFilterWithAnInvalidAliasIsRejected` (`expectException(FlareException::class)`,
  model `['alias' => 'price.min', 'control' => self::BOOL_CONTROL]`, `withValue: false`). Add to
  `BackendElementScopeFactoryTest`: `testLegacyAliasYieldsNoScope` (same model → `create($getSpec, $model) === null`,
  no exception). Add `Contao\Factory\FilterFactory` to `testContaoServicesAreRegistered`.
- [ ] **Step 2: Run** `make test tests/Contao/Factory/FilterFactoryTest.php` — expected FAIL (class missing).
- [ ] **Step 3: Implement** the Contao factory; remove the model path from the core factory; switch
  `BackendElementScopeFactory`, `FilterInspector`, `ListModelFilterCollector` and their test wiring. Delete the
  "must load to repair a legacy alias" comment.
- [ ] **Step 4: Run** `make test`, `make phpstan`, mago — green; `grep -rn 'createFromFilterModel' src tests` → only
  doc references that Task 9 updates (none in code).
- [ ] **Step 5: Commit** `feat: create filters from FilterModels in a Contao FilterFactory`

### Task 8: Collector and list entry point into `Contao/Factory/`

**Files:**
- Move: `src/List/Collector/ListModelFilterCollector.php` → `src/Contao/Factory/FilterCollector.php` (class renamed),
  `src/List/Collector/CollectedFilters.php` → `src/Contao/CollectedFilters.php`; `src/List/Collector/` is gone
- Create: `src/Contao/Factory/ListSpecBuilderFactory.php`
- Modify: `src/List/Factory/ListSpecBuilderFactory.php` (remove `createFromListModel()` and the collector dependency),
  consumers `src/Controller/ContentElement/ListViewController.php`, `ReaderController.php`,
  `src/Contao/Hook/BreadcrumbHook.php`, `src/Contao/Hook/ElementDcaHook.php`,
  `src/Contao/Callback/FlareFilter/FieldsOptionsCallbacks.php` (two call sites)
- Move test: `tests/List/ListModelFilterCollectorTest.php` → `tests/Contao/Factory/FilterCollectorTest.php`
- Create test: `tests/Contao/Factory/ListSpecBuilderFactoryTest.php`
- Modify tests: `tests/Contao/Hook/ElementDcaHookTest.php`, `tests/Contao/Callback/FlareFilter/FieldsOptionsCallbacksTest.php`,
  `tests/Contao/Inspection/BuildsFilterInspectorTrait.php`, `tests/DependencyInjection/ServicesConfigTest.php`

**Interfaces:**
- Consumes: Task 7's Contao `FilterFactory`.
- Produces:
  - `Contao\Factory\FilterCollector` — today's `collect(ListModel): ?CollectedFilters` and
    `collectFromModels(iterable $models, ListModel $listModel): CollectedFilters`, unchanged in behavior.
  - `Contao\CollectedFilters` (unchanged DTO).
  - `Contao\Factory\ListSpecBuilderFactory` — `__construct(\HeimrichHannot\FlareBundle\List\Factory\ListSpecBuilderFactory $builderFactory, FilterCollector $filterCollector)`,
    `public function create(ListModel $listModel): ListSpecBuilder` (body = today's `createFromListModel()`,
    `@throws FlareException` if the driver cannot be resolved).
  - Core `List\Factory\ListSpecBuilderFactory` keeps only `create(ListDriverInterface|string $driver, ?ListModel $model = null, ?string $source = null)`.

- [ ] **Step 1: Write the failing tests**
  - `FilterCollectorTest` (moved, class renamed): new `testListenerSettingAWrongValueEscapesAsError` — a
    `FilterCollectedEvent` listener doing `$event->filter = $event->filter->withValue(<wrong class>)`;
    `collectFromModels()` throws `ValueClassMismatchError` (no `FilterFailure` returned).
  - `ListSpecBuilderFactoryTest`: `testCreateCarriesModelSourceFiltersAndFailures` — a list model
    (`id` 3, a registered driver type) whose collector yields one filter and one failure: the builder's `getModel()`
    is the model, `getSource() === 'tl_flare_list.3'`, `getFilters()` holds the filter under its alias, and the
    built spec carries the failure; `testUnknownDriverThrows` → `FlareException`.
  - Add `Contao\Factory\FilterCollector` and `Contao\Factory\ListSpecBuilderFactory` to
    `testContaoServicesAreRegistered`; `Contao\CollectedFilters` to the non-services list.
- [ ] **Step 2: Run** both new test files — expected FAIL.
- [ ] **Step 3: Implement** the moves and the new factory; switch all consumers (import the core class under an
  alias where both are used).
- [ ] **Step 4: Run** `make test`, `make phpstan`, mago — green;
  `grep -rn 'createFromListModel\|ListModelFilterCollector\|List\\\\Collector' src tests` → only
  `ListSpecBuilder::build()`'s call to `ListSpecFactory::createFromListModel()` remains (stays by design).
- [ ] **Step 5: Commit** `refactor: move the filter collector and the ListModel entry point into Contao/Factory`

### Task 9: AGENTS.md and the docs worktree

**Files:**
- Modify: `AGENTS.md`
- Modify (docs worktree `docs/`, branch `docs/main`): `docs/docs/dev/dca-builder.md`,
  `docs/docs/dev/contracts/dca-contract.md`, `docs/docs/dev/filter-elements/index.md`,
  `docs/docs/dev/filter-controls.md`, `docs/docs/dev/list-types/index.md`, plus any other hit of
  `grep -rlE 'DataContainer\\\\|InferPtable|CallbackFilterModelTransformer|CallbackListModelTransformer|createFromFilterModel|ElementDcaListener|scope\(.control.\)|ListModelFilterCollector' docs/docs`

**Interfaces:** none.

- [ ] **Step 1: Update AGENTS.md:** every moved path/class (architecture flow, failure paragraph mentioning
  `FilterFactory`/`FilterInspector`/`FilterFormBuilderFactory` paths, notable subsystems, key entry points, event
  list, DCA builder description), and add: the `Contao` namespace rule (DTOs in the root, services in subfolders;
  hooks in `Contao/Hook` named `*Hook`, DCA callbacks in `Contao/Callback`; all `FilterModel` → `Filter` processing
  in `Contao/`; `ListSpec`/builder/factory still model-aware until their refactor); the `ValueClassMismatchError`
  rule next to the `UndefinedConfigKeyError` paragraph (a value-class mismatch is a programming error, fails the
  request, never retried, never degraded); the collect-only DCA builder interfaces.
- [ ] **Step 2: Verify** `grep -nE 'src/DataContainer|EventListener/Contao|EventListener/DataContainer|InferPtable|createFromFilterModel|ListModelFilterCollector|ControlScopeDcaBuilder' AGENTS.md`
  → no output. Commit `docs: describe the Contao namespace in AGENTS.md`.
- [ ] **Step 3: Update the docs pages** (namespaces, `forControl()` instead of `scope('control')` if shown, the
  `->set(sourceClass:, transformer: $this->transformFilterModel(...))` form, `Contao\DcaContext`).
- [ ] **Step 4: Run** `npm run build` in `docs/` — succeeds.
- [ ] **Step 5: Commit in the docs worktree** (`git -C docs commit`) `docs: follow the Contao namespace refactor`.
