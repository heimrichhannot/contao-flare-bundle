# Nomenclature (Compound, Effect, Context, Intent) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rename `ListSpec`/`Filter` → `ListCompound`/`FilterCompound`, `FilterContext` → `FilterEffect`,
`ElementScope`/`ControlScope` → `ElementContext`/`ControlContext`, engine `*Context` → `*Intent`, and the form
API's `compound()` → `setMountOptions()`, without any change in behavior.

**Architecture:** A pure rename in seven tasks, one concept per task (plus glossary and docs), each ending green
and committed. The order frees words before they are reused: engine contexts become intents first (frees
`$context`), then the element's product becomes an effect (frees `$context` in `FilterInvoker` and the tests),
then scopes become contexts, then the form API gives up "compound", then list and filter become compounds.

**Tech Stack:** PHP 8.2+, Symfony, Contao, PHPUnit 9, PHPStan (level 5); `make test`, `make phpstan` (Docker).

**Spec:** `dev/superpowers/specs/2026-11-04-nomenclature-design.md` — its "Rename map" is the authority for every
name; this plan orders the work, lists the sites that need care, and names the checks. Read the map section for
your task before starting it.

## Global Constraints

- Branch `v0.2-nomenclature` (exists, holds spec and plan). Commits: **no `Co-Authored-By` trailer, never push.**
- No BC shims, no `class_alias`, no deprecated aliases.
- No behavior change: no SQL, form name, template variable or Twig function name changes; event classes and
  named-event strings stay; the `buildList()` hook (`Contract\ListDriver\BuildListContract`) stays.
- Out of scope, do not touch (see the spec's non-goals): `ListExecutionContext` (+ factory,
  `DcaContext::getExecutionContext()`), `Formula`, `Proposition`, `FormulaBuilder*`, `FilterConditions*`,
  `Sql*Struct`, `src/Integration/Terminal42Languages/`, historical files in `dev/superpowers/`, and the same words
  for other concepts: choice scope (`getChoiceScope()`, `ChoiceKey`, `ChoicesBuilder` prose), Contao's
  `ScopeMatcher`/`$scopeMatcher`, the DCA segment test `DcaBuilderTest::testControlScopeSharesFieldsWithParent`,
  `FilterInspector::checkEffect()`. **No blanket `sed` over these names** — e.g. `s/ControlScope/ControlContext/`
  would wrongly hit `testControlScopeSharesFieldsWithParent`.
- Variable/property rule: `$list`, `$filter`, `$filters` stay; names spelling an old noun change (`$spec`,
  `$listSpec`, `$scope`, `$engineContext`, `$context` holding an intent or an effect, `$interactiveConfig`
  holding an intent, …).
- Move files with `git mv` so history follows. PhpStorm's rename refactoring (`mcp__phpstorm__rename_refactoring`)
  is the preferred tool for classes/methods/properties; scripted replacements are fine for bulk occurrences —
  review the diff either way.
- PHPStan does **not** analyse `tests/` and ignores `class.notFound` under `src/Integration/` (`phpstan.neon`), so
  a stale `use …\Engine\Context\…` there plus `instanceof` silently evaluates to false. The audit greps are the
  safety net — run them exactly as given; their expected output is empty unless stated.
- `declare(strict_types=1);`, PSR-12/PER, **120-char lines**. Renames change name lengths: re-align the
  column-aligned promoted constructor parameters in touched files, and before each commit run
  `git diff --name-only HEAD | grep '\.php$' | xargs -r awk 'length > 120 {print FILENAME":"FNR}'` → no output.
- `AGENTS.md` is updated in the task that renames the concept it mentions (`CLAUDE.md` is a symlink — edit
  `AGENTS.md` only).
- Every task ends with `make test` → `OK` and `make phpstan` → `[OK] No errors`.
- Run all audit greps from the repository root. They are written for zsh/bash with GNU-compatible `grep -E`
  (the user's shell aliases `grep` to `ugrep`, which accepts them too).

## Review Focus

1. **Intent classes registered as services.** `config/services.yaml` excludes `../src/Engine/Context/*.php`; a moved
   directory without an updated glob turns `InteractiveIntent` & co. into autowired services. Pinned in Task 1
   (`ServicesConfigTest`).
2. **Error sources still saying `::buildContext`.** `FilterInvoker` builds the failing method name as a string.
   Pinned in Task 2 (`FilterInvokerTest`).
3. **Form attribute keys.** Third-party controls/listeners read `'flare.filter_scope'` and `'flare.engine_context'`
   by string; the new keys must be exactly `'flare.control_context'` and `'flare.intent'`. Pinned in Task 3
   (`FilterFormBuilderFactoryTest`) and Task 1 (`FormHarnessFactoryTest`).
4. **Twig named arguments.** `flare_project()` maps to `FlareRuntime::project()`; its parameters become `$list`,
   `$intent`, which changes Twig named-argument names. Intended — the docs (Task 7) show the new names.
5. **Stale words where no compiler looks:** tests, PHPDoc, prose, exception messages, `src/Integration/`, docs
   anchors. Covered by each task's audit greps and Task 7's anchor check.

---

### Task 1: Engine context → Intent

**Files:**
- Move: `src/Engine/Context/` → `src/Engine/Intent/` (classes renamed per the spec's map, incl. `Factory/` and
  `ReaderUrlConfigCreatorTrait`). The directory also holds ignored local files (`ruvector.db`, maybe
  `.claude-flow/`): after the `git mv`s, remove the leftover `src/Engine/Context/` by hand.
- Modify: `config/services.yaml:29` (exclude glob → `../src/Engine/Intent/*.php`); `Engine`, `EngineFactory`,
  `ProjectorInterface` (`@template TContext` → `TIntent`), `AbstractProjector`, the three projectors and
  `src/Integration/ContaoCalendar/Projector/*`, `src/Integration/ContaoCalendar/Loader/EventsInteractiveLoader.php`,
  `EventsAggregationLoader.php`, `src/Registry/ProjectorRegistry.php`, the loaders and `*LoaderConfig`,
  `ListQueryConfig`, `ListQueryDirector`, `src/EventListener/QueryStructModifier/{Order,Page}ModifierListener.php`,
  `FormHarnessFactory`, `PageParamMod`, `src/Event/ReaderRenderEvent.php`, `ListViewController`,
  `ReaderController`, `BreadcrumbHook`, `FlareRuntime`, `ElementScope`, `ControlScope`,
  `BackendElementScopeFactory`, `FilterInspector`, `CalendarCurrentFilterElement`; every test importing an engine
  context (incl. `tests/Engine/Projector/InteractiveProjectorTest.php` helper `context()` → `intent()`,
  `tests/Filter/ControlScopeTest.php` `testForElementKeepsListFilterAndContext` → `…AndIntent` with its
  `$context = new ConfigurationContext()` → `$intent`, the anonymous context in `FormHarnessFactoryTest`,
  `BuildsFilterContextsTrait` params `?ContextInterface $engineContext` → `?IntentInterface $intent`); `AGENTS.md`
- Test: `tests/DependencyInjection/ServicesConfigTest.php`, `tests/Form/FormHarnessFactoryTest.php`

**Interfaces:**
- Produces: `Engine\Intent\IntentInterface` (`getIntentType(): string`), `InteractiveIntent`, `ValidationIntent`,
  `AggregationIntent`, `ConfigurationIntent`, `FormIntentInterface`, `PaginatedIntentInterface`,
  `SortableIntentInterface`, `Engine\Intent\ReaderUrlConfigCreatorTrait`,
  `Engine\Intent\Factory\{Interactive,Validation,Aggregation}IntentFactory`;
  `Engine::getIntent()`, `Engine::with(?IntentInterface $intent = null, ?ListSpec $list = null, ?array $mods = null)`,
  `EngineFactory::createEngine(IntentInterface $intent, ListSpec $list, array $mods = [])`;
  `ProjectorInterface::supports/priority/project(ListSpec $list, IntentInterface $intent)`;
  `ElementScope::$intent`, `ControlScope::$intent` (were `$engineContext`); `*LoaderConfig::$intent`,
  `ListQueryConfig::$intent`, `ReaderRenderEvent::$intent`; `FlareRuntime::project(ListSpec $list, IntentInterface $intent)`;
  form root attribute key `'flare.intent'`.

- [ ] **Step 1: Pin service registration and the root attribute**

`ServicesConfigTest`: add to `provideNonServices()` `InteractiveIntent::class`, `ValidationIntent::class`,
`AggregationIntent::class`, `ConfigurationIntent::class`; add to `provideServices()`
`InteractiveIntentFactory::class`, `ValidationIntentFactory::class`, `AggregationIntentFactory::class` (imports
from `HeimrichHannot\FlareBundle\Engine\Intent\…`).

`FormHarnessFactoryTest`: new test

```php
public function testRootFormCarriesTheIntent(): void
{
    $root = $this->createFilterSet([])->getForm();

    $this->assertInstanceOf(IntentInterface::class, $root->getConfig()->getAttribute('flare.intent'));
}
```

- [ ] **Step 2: Run them, expect failure**

Run: `make test tests/DependencyInjection/ServicesConfigTest.php tests/Form/FormHarnessFactoryTest.php`
(or one after the other) → failures: the `Engine\Intent` classes do not exist / attribute `'flare.intent'` is null.

- [ ] **Step 3: Rename per the spec's "Engine context → Intent" table**

Classes, interfaces, files, namespace, services.yaml glob, the form attribute literal, properties, parameters and
variables holding an intent (`$context`, `$config`, `$validationContext`, `$interactiveConfig`,
`$interactiveConfigFactory`, `$aggregationConfigFactory`, … → `$intent`, `$validationIntent`,
`$interactiveIntent`, `$interactiveIntentFactory`, `$aggregationIntentFactory`), assert messages
(`'$config must be an instance of AggregationConfig'` → `'$intent must be an instance of AggregationIntent'`),
`ProjectorRegistry`'s message "supporting context configuration" → "supporting intent". `AGENTS.md`: architecture
flow, "Context (Interactive / Validation / Aggregation)", "Contexts and Projectors/Views are separate axes", every
engine-context mention.

- [ ] **Step 4: Suite and audit**

Run `make test` → `OK`; `make phpstan` → `[OK] No errors`; then each of these → no output:

```bash
# type and namespace tokens
grep -rnoE '\w*Context(Interface|Factory|Trait)?\b|Engine\\Context' src tests config contao AGENTS.md \
  | grep -vE ':(\w*[Ee]xecutionContext\w*|\w*[Ff]ilterContext\w*|buildContext|buildFixedContext|DcaContext|ContaoContext|\w*ResponseContext\w*|Context)$' \
  | grep -v Terminal42Languages
# old variable and member names
grep -rnE 'TContext|engineContext|engine_context|getContextType|getContext\(\)|\$(validation|interactive|aggregation)(Context|Config)|->(validation|interactive|aggregation)(Context|Config)|must be an instance of \w+Config|context configuration' src tests config contao AGENTS.md \
  | grep -v Terminal42Languages
# intents held under another name
grep -rnE 'Intent(Interface|Factory)?\s+\$\w+' src tests | grep -vE '\$\w*[iI]ntent\w*\b'
grep -rnE '(config|queryConfig|options|event)->context\b|\$context\s*=.*(getIntent|Intent\(|IntentFactory)' src tests
```

Then `grep -rnwE 'Contexts?|contexts?' AGENTS.md src/Engine` and fix every hit that still means the engine's
intent (prose).

- [ ] **Step 5: Commit**

```bash
git add -A src tests config AGENTS.md
git commit -m "refactor!: rename engine contexts to intents"
```

### Task 2: Element product → Effect

**Files:**
- Move: `src/Filter/FilterContext.php` → `FilterEffect.php`, `FilterContextBuilder.php` → `FilterEffectBuilder.php`,
  `FilterContextBuilderInterface.php` → `FilterEffectBuilderInterface.php`, `src/Filter/Factory/FilterContextFactory.php`
  → `FilterEffectFactory.php`, `src/Filter/Factory/FilterContextBuilderFactory.php` → `FilterEffectBuilderFactory.php`;
  tests `tests/Filter/BuildsFilterContextsTrait.php` → `BuildsFilterEffectsTrait.php`,
  `tests/Filter/FilterContextFactoryTest.php` → `FilterEffectFactoryTest.php`
- Modify: `FilterElementInterface`, `AbstractFilterElement`, every element in `src/Filter/Element/` and
  `src/Integration/*/FilterElement/` (incl. `ArchiveFilterElement::buildFixedContext()` → `buildFixedEffect()`),
  `FilterInvoker` (property `$filterContextFactory` → `$filterEffectFactory`, local `$context` → `$effect`, the
  `'::buildContext'` string, the PHPDoc "the filter's context"), `FilterEffectFactory` internals
  (`$filterContextBuilderFactory`, `$contextBuilder` → `$filterEffectBuilderFactory`, `$effectBuilder`),
  `FilterException` PHPDoc; tests: every element test reading `$context->formula` (→ `$effect->formula`), anonymous
  elements in test traits (e.g. `BuildsControlScopesTrait::buildContext`), `tests/List/FilterFailureTest.php`
  (`X::buildContext` → `X::buildEffect`); `AGENTS.md`
- Test: `tests/Query/Executor/FilterInvokerTest.php`

**Interfaces:**
- Consumes: Task 1's `IntentInterface`.
- Produces: `FilterElementInterface::buildEffect(FilterEffectBuilderInterface $builder, ElementScope $scope, ?ValueInterface $value): void`;
  `FilterEffect { public Formula $formula }`; `FilterEffectBuilder::build(): FilterEffect`;
  `FilterEffectFactory::create(ElementScope $scope, ?ValueInterface $value): FilterEffect`;
  `FilterEffectBuilderFactory::create(ElementScope $scope)`; test trait `BuildsFilterEffectsTrait` with
  `buildFilterEffect(FilterElementInterface $element, array $config = [], ?ValueInterface $value = null, ?IntentInterface $intent = null, string $dc = 'tl_test'): FilterEffect`
  and `createFilterEffectFactory(?FilterElementRegistry $elementRegistry = null): FilterEffectFactory` (it keeps
  `buildElementScope()` until Task 3).

- [ ] **Step 1: Pin the error source**

In `FilterInvokerTest::testFilterExceptionIsWrappedWithTheSource` and `::testOtherExceptionsBecomeFilterExceptions`
add inside the `catch`:

```php
self::assertStringEndsWith('::buildEffect', (string) $e->getMethod());
```

- [ ] **Step 2: Run it, expect failure**

Run: `make test tests/Query/Executor/FilterInvokerTest.php` → FAIL, the method ends with `::buildContext`.

- [ ] **Step 3: Rename per the spec's "Element product → Effect" table**

`AGENTS.md`: flow text, lifecycle taxonomy (`buildContext` → `buildEffect`), the `FilterContext` and
`FilterContextBuilderInterface` paragraphs, the parameter-order sentence.

- [ ] **Step 4: Suite and audit**

Run `make test` → `OK`; `make phpstan` → `[OK] No errors`; then → no output:

```bash
grep -rniE 'filtercontext|buildcontext|fixedcontext|contextbuilder|\$context->formula|(filter|element)'"'"'s context' src tests config contao AGENTS.md \
  | grep -v Terminal42Languages
```

- [ ] **Step 5: Commit**

```bash
git add -A src tests AGENTS.md
git commit -m "refactor!: rename FilterContext to FilterEffect, buildContext() to buildEffect()"
```

### Task 3: Hook handover Scope → Context

**Files:**
- Move: `src/Filter/ElementScope.php` → `ElementContext.php`, `src/Filter/ControlScope.php` → `ControlContext.php`,
  `src/Contao/DataContainer/BackendElementScopeFactory.php` → `BackendElementContextFactory.php`; tests
  `ElementScopeTest` → `ElementContextTest`, `ControlScopeTest` → `ControlContextTest`,
  `tests/Contao/DataContainer/BackendElementScopeFactoryTest.php` → `BackendElementContextFactoryTest.php`,
  `tests/Filter/Control/BuildsControlScopesTrait.php` → `BuildsControlContextsTrait.php`
- Modify: `src/Contao/DcaContext.php` (`getElementScope()`, `$elementScopeFactory`), `src/Contao/Hook/ElementDcaHook.php`
  (`$elementScopeFactory`), `src/Filter/ValueProvider/ChoiceValueProvider.php:85-87` (`$scope = $context->getElementScope()`
  inside `use ($context)` with a `DcaContext $context` → `$elementContext = $context->getElementContext()`),
  `src/Form/FilterFormBuilderInterface.php` (`ATTR_SCOPE`), `src/Form/Factory/FilterFormBuilderFactory.php`,
  `src/Form/Factory/FormHarnessFactory.php`, `src/Form/FilterMount.php` (public `$scope` + PHPDoc),
  `src/Form/FormHarness.php:128`, `src/Event/FilterControlBuildFormEvent.php` (public `$scope`),
  `src/EventListener/NamedDispatch/FilterControlBuildFormListener.php`, all contracts in `src/Contract/FilterElement/`
  (e.g. `ChoiceSourceContract::buildChoices(ChoicesBuilder $choices, ElementScope $scope)`), every element/control/
  value provider/integration taking a scope, `FilterInvoker`, `ListQueryDirector`, `FilterInspector` (private
  `buildScope(\Closure $buildScope, …): ?ListSpec` builds the inspected list → `buildList(\Closure $buildList, …)`);
  tests: helpers `BuildsFilterEffectsTrait::buildElementScope()` → `buildElementContext()`,
  `BuildsControlContextsTrait::scope()` → `context()`, per-test `createScope()` → `createContext()`,
  `ScopeBoundsElement` (`ControlScopeTest`) → `ContextBoundsElement`, `FilterInspectorTest` names
  (`testOnlyTheFirstOfADuplicateGroupEntersTheScope` → `…EntersTheList`,
  `testWithoutAListScopeRowIssuesAreStillReported` → `testWithoutAListRowIssuesAreStillReported`,
  `testAScopeErrorIsAListLevelProgrammingError` → `testAListBuildErrorIsAListLevelProgrammingError`,
  `testScopeErrorsEscapeInDebugMode` → `testListBuildErrorsEscapeInDebugMode`), `BackendElementContextFactoryTest`
  names (`…BackendScope…` → `…BackendContext…`, `…Scope…` → `…Context…`), `ElementDcaHookTest`
  (`$elementScopeFactory`); `AGENTS.md`
- Test: `tests/Form/FilterFormBuilderFactoryTest.php`, `tests/DependencyInjection/ServicesConfigTest.php`

**Interfaces:**
- Consumes: Task 1 (`$intent`), Task 2 (`buildEffect()`, `BuildsFilterEffectsTrait`).
- Produces: `Filter\ElementContext(ListSpec $list, Filter $filter, IntentInterface $intent)` with `get()`, `has()`;
  `Filter\ControlContext(ListSpec $list, Filter $filter, IntentInterface $intent)` with `get()`, `has()`,
  `element()`, `tryElement()`, `forElement(): ElementContext`;
  `BackendElementContextFactory::create(\Closure $getSpec, FilterModel $filterModel): ?ElementContext` (`$getSpec`
  becomes `$getList` in Task 5); `DcaContext::getElementContext(): ?ElementContext` (ctor param
  `$elementContextFactory`); `FilterFormBuilderInterface::ATTR_CONTEXT = 'flare.control_context'`;
  `FilterMount::$context`, `FilterControlBuildFormEvent::$context`; every hook's handover parameter is `$context`:
  `buildEffect($builder, ElementContext $context, $value)`, `buildForm($builder, ControlContext $context)`,
  `decode($data, ControlContext $context)`, `hydrate($value, ControlContext $context)`.

- [ ] **Step 1: Pin the attribute key and the service**

In `FilterFormBuilderFactoryTest` (the test asserting `ATTR_SCOPE` at line ~53) replace that assertion with:

```php
self::assertSame('flare.control_context', FilterFormBuilderInterface::ATTR_CONTEXT);
self::assertInstanceOf(ControlContext::class, $builder->getAttribute(FilterFormBuilderInterface::ATTR_CONTEXT));
```

In `ServicesConfigTest::provideServices()` replace `BackendElementScopeFactory::class` with
`BackendElementContextFactory::class`.

- [ ] **Step 2: Run it, expect failure**

Run: `make test tests/Form/FilterFormBuilderFactoryTest.php` → error, undefined constant `ATTR_CONTEXT` / class
`ControlContext`.

- [ ] **Step 3: Rename per the spec's "Hook handover → Context" table**

`AGENTS.md`: every `ElementScope`/`ControlScope`/`$scope` mention, "value-free, role-typed scope" wording,
`DcaContext::getElementScope()`, "Hook parameter order" sentence.

- [ ] **Step 4: Suite and audit**

Run `make test` → `OK`; `make phpstan` → `[OK] No errors`; then → no output:

```bash
grep -rnoE '\w*Scope\w*|\$scope\w*|->scope\b|\bscope\(' src tests config contao AGENTS.md \
  | grep -vE ':(\w*ChoiceScope|ScopeMatcher|\$scopeMatcher|testSingleScopeListSubmitsLocalKeys|testMixedScopesSubmitCanonicalKeys|testNamedEventIsScopedToTheFormName|testControlScopeSharesFieldsWithParent)$' \
  | grep -vE 'ChoiceKey\.php|Terminal42Languages'
```

Then `grep -rniwE 'scopes?' src tests AGENTS.md | grep -viE 'choice|ScopeMatcher'` and fix every hit that means
the hook handover or the inspected list (prose).

- [ ] **Step 5: Commit**

```bash
git add -A src tests config AGENTS.md
git commit -m "refactor!: rename ElementScope/ControlScope to ElementContext/ControlContext"
```

### Task 4: Form API gives up "compound"

**Files:**
- Modify: `src/Form/FilterFormBuilderInterface.php`, `src/Form/FilterFormBuilder.php` (`$compoundOptions` →
  `$mountOptions`), `src/Form/Factory/FormHarnessFactory.php:136`, `src/Filter/Control/DateRangeFilterControl.php`
  (call + PHPDoc "data of the compound"), PHPDoc in `src/Event/FilterControlBuildFormEvent.php:14,19` and
  `src/Filter/Control/FilterControlInterface.php:38`; tests `tests/Filter/Control/DateRangeFilterControlTest.php`,
  `tests/Form/FormHarnessFactoryTest.php` (`testMultiFieldControlMountsNestedCompound` →
  `testMultiFieldControlMountsNestedForm`, `testCompoundMountTakesTheControlsCompoundOptions` →
  `testMountTakesTheControlsMountOptions`, message "the mounted compound"); `AGENTS.md` if it mentions it
- Test: `tests/Filter/Control/DateRangeFilterControlTest.php`

**Interfaces:**
- Produces: `FilterFormBuilderInterface::setMountOptions(array $options): static`,
  `FilterFormBuilderInterface::getMountOptions(): array`.

- [ ] **Step 1: Switch the test to the new API**

`DateRangeFilterControlTest::testLabelBecomesTheCompoundMountsLabel` → `testLabelBecomesTheMountsLabel`; its
assertions read `$labelled->getMountOptions()` (`['label' => 'Period']`) and `$plain->getMountOptions()` (`[]`).

- [ ] **Step 2: Run it, expect failure**

Run: `make test tests/Filter/Control/DateRangeFilterControlTest.php` → error, undefined method `getMountOptions()`.

- [ ] **Step 3: Rename per the spec's "Compound" form-API table**

Prose: "compound mount" → "the mount of a multi-field filter"; "compound" stays only where it explicitly means
Symfony's compound form ("mounts as a nested Symfony compound form").

- [ ] **Step 4: Suite and audit**

Run `make test` → `OK`; `make phpstan` → `[OK] No errors`;
`grep -rniE 'compound' src tests AGENTS.md | grep -vE '(List|Filter)Compound'` → only lines that say "Symfony
compound form" or pass Symfony's `'compound'` option.

- [ ] **Step 5: Commit**

```bash
git add -A src tests AGENTS.md
git commit -m "refactor!: rename FilterFormBuilder compound() to setMountOptions()"
```

### Task 5: List and filter → Compound

**Files:**
- Move: `src/List/ListSpec.php` → `ListCompound.php`, `ListSpecBuilder.php` → `ListCompoundBuilder.php`,
  `ListSpecBuilderInterface.php` → `ListCompoundBuilderInterface.php`, `src/List/Factory/ListSpecFactory.php` →
  `ListCompoundFactory.php`, `src/List/Factory/ListSpecBuilderFactory.php` → `ListCompoundBuilderFactory.php`,
  `src/Contao/Factory/ListSpecBuilderFactory.php` → `ListCompoundBuilderFactory.php`, `src/Filter/Filter.php` →
  `FilterCompound.php`, `src/Filter/Factory/FilterFactory.php` → `FilterCompoundFactory.php`,
  `src/Contao/Factory/FilterFactory.php` → `FilterCompoundFactory.php`; tests `ListSpecTest`, `ListSpecBuilderTest`,
  `ListSpecFactoryTest`, the `ListSpecBuilderFactoryTest`s, `FilterTest` → `FilterCompoundTest`, the
  `FilterFactoryTest`s → `FilterCompoundFactoryTest`
- Modify: every consumer in `src/` (except Terminal42Languages), `tests/`, `config/`, `AGENTS.md`. Sites the
  compiler does not flag: `$listSpec` (`ListViewController.php:103-110`, `BreadcrumbHook.php:96-118`),
  `$this->listSpecBuilderFactory` (`FilterInspector.php:76`), `$specFactory`/`specFactory:` (`ListSpecBuilder.php`,
  `List/Factory/ListSpecBuilderFactory.php`, `ListSpecBuilderTest`, `ListDriverBuildListListenerTest`,
  `BuildsFilterInspectorTrait`, `Contao/Factory/ListSpecBuilderFactoryTest`), `$getSpec` (`ElementDcaHook`,
  `BackendElementContextFactory`), test helpers `listSpec()` (`tests/Form/BuildsFormFixturesTrait.php:31` + callers),
  `spec()` (`FilterInspectorTest`, `ListSpecTest`), `createListSpecBuilderFactory()`,
  `createContaoListSpecBuilderFactory()`, `createFilterFactory()`; test names `testFiltersDcAndSourceCarryOverToTheSpec`,
  `testCreatesSpecWithResolvedConfigAndDc`; the `Filter` type in tests and PHPDoc (`new Filter(` ~30 test sites,
  `{@see Filter}` in `ElementBinding`/`ControlBinding`, `array<int, Filter>` in `InspectedRows`,
  `list<array{Filter, …}>` in `FilterInspector`, `list<Filter>` in `BuildsFormFixturesTrait`/`ListQueryDirectorTest`,
  aligned `public Filter       $filter` in `FilterCollectedEvent`, `FilterMount`, `ElementContext`, `ControlContext`,
  `CreatesFilterExceptionTrait`, `Filter|string` in `ListSpec::withoutFilter()`); exception messages
  (`'[FLARE] ListSpec data container …'` → `'[FLARE] ListCompound data container …'`)
- Test: `tests/List/ListCompoundTest.php`

**Interfaces:**
- Consumes: Tasks 1–4.
- Produces: `List\ListCompound` (same ctor/methods as `ListSpec`), `ListCompoundBuilder`,
  `ListCompoundBuilderInterface`, `List\Factory\ListCompoundFactory`, `List\Factory\ListCompoundBuilderFactory`,
  `Contao\Factory\ListCompoundBuilderFactory::create(ListModel $listModel): ListCompoundBuilder`,
  `Filter\FilterCompound` (same ctor/methods as `Filter`), `Filter\Factory\FilterCompoundFactory`,
  `Contao\Factory\FilterCompoundFactory`; import aliases `CoreListCompoundBuilderFactory`,
  `ContaoListCompoundBuilderFactory`, `CoreFilterCompoundFactory`;
  `BuildListContract::buildList(ListCompoundBuilder $builder)`.

- [ ] **Step 1: Move the list's own test first**

`git mv tests/List/ListSpecTest.php tests/List/ListCompoundTest.php`; rename the class to `ListCompoundTest`, its
`spec()` helper to `list()` (or another clear name), and make it construct and assert `ListCompound` (and
`FilterCompound` for its filters).

- [ ] **Step 2: Run it, expect failure**

Run: `make test tests/List/ListCompoundTest.php` → error, class `ListCompound` not found.

- [ ] **Step 3: Rename per the spec's "List and filter → Compound" table**

`$spec`/`$listSpec`/`$getSpec`/`$specFactory` → `$list`/`$getList`/`$listFactory` (pick a clear name where taken);
"list spec" in prose → "list". `AGENTS.md`: every `ListSpec*` mention, "named 'Spec' only because `List` is
reserved…" → explain *Compound* (assembled from bound components, ready to run), `Filter::$value`, "`Filter`'s
constructor", "`Filter` DTO", "into `Filter`", `Factory/FilterFactory::create()`, `ListSpecBuilderFactory::create(ListModel)`,
the "Exception, until their own refactor" sentence.

- [ ] **Step 4: Suite and audit**

Run `make test` → `OK`; `make phpstan` → `[OK] No errors`; then each → no output:

```bash
grep -rniE 'listspec|specFactory|\bspec\(|\$spec\b|getSpec|list spec|TheSpec|SpecWith' src tests config contao AGENTS.md | grep -v Terminal42Languages
grep -rnE 'new Filter\(|\bclass Filter\b|<(\w+, )?Filter>|array\{Filter|@see Filter\}|`Filter`|\bFilter::|\bFilter\||[ (]Filter +\$|\?Filter\b|: Filter\b|Filter\\Filter;|FilterFactory\b' src tests config contao AGENTS.md \
  | grep -v Terminal42Languages
```

- [ ] **Step 5: Commit**

```bash
git add -A src tests config AGENTS.md
git commit -m "refactor!: rename ListSpec/Filter to ListCompound/FilterCompound"
```

### Task 6: Glossary and whole-branch audit

**Files:**
- Modify: `AGENTS.md`

- [ ] **Step 1: Add the glossary**

A short "Vocabulary" block near the architecture section, one line each: *Compound* — the assembled, runnable
list (`ListCompound`) / filter (`FilterCompound`); *Effect* — an element's abstract building blocks for the query
(`FilterEffect`, `buildEffect()`); *Context* — the pre-filled handover to a hook (`ElementContext`,
`ControlContext`, `DcaContext`); *Intent* — what the engine renders a list for (`InteractiveIntent` …).

- [ ] **Step 2: Whole-branch audit**

Run every audit command of Tasks 1–5 again. Expected: no output, except (a) Task 1's token grep now also lists
`ElementContext`/`ControlContext`/`ElementContextTest`… tokens — add them to its `grep -vE` allowlist for this
rerun —, and (b) Task 4's grep shows only the Symfony-compound lines. Then `make test` → `OK`, `make phpstan` →
`[OK] No errors`, and
`git diff --name-only v0.2 | grep '\.php$' | xargs -r awk 'length > 120 {print FILENAME":"FNR}'` → no output.

- [ ] **Step 3: Commit**

```bash
git add AGENTS.md
git commit -m "docs: add the vocabulary to AGENTS.md"
```

### Task 7: Docs worktree

**Files:** in `docs/` (the worktree on branch `docs/main` — commit there, not on `v0.2-nomenclature`):
`docs/docs/spec/{specifications,filtering,projection}.md`, `docs/docs/dev/{engine-mods,events,filter-controls,predicates}.md`,
`docs/docs/dev/contracts/{index.mdx,data-container-contract.md,build-query-contract.md}`,
`docs/docs/dev/filter-elements/index.md`, `docs/docs/dev/list-types/index.md`, `docs/docs/basic-usage.md`,
`docs/docs/templating.mdx`, `docs/docs/reference/{twig,predicates}.md`, `docs/docs/migrating-from-v0.1.md`,
`docs/docs/removed-in-v0.2.md`, and any further page the audit below finds.

- [ ] **Step 1: Rename in prose, code samples and headings**

Apply the whole map, incl. `FilterCompoundFactory` (e.g. `engine-mods.md`, `list-types/index.md`,
`filter-elements/index.md`, `spec/filtering.md`, `events.md`, `specifications.md`), `$engine->getIntent()`,
`IntentInterface`, `PaginatedIntentInterface`, `*IntentFactory`, `$context->…` (was `$scope->…`, e.g.
`filter-elements/index.md`, `filter-controls.md`), `FilterControlBuildFormEvent::$context` (`events.md`),
`setMountOptions()`/`getMountOptions()`, `ATTR_CONTEXT`, `buildEffect()`, `FilterCompound::$control`,
`flare_project(list, intent)` (`reference/twig.md` **and** `templating.mdx:528`). Headings derived from old names
change their anchors — fix every link to them, e.g. `#3-building-a-list-listspecbuilder`
(`specifications.md`), `#the-elementscope-and-the-filtercontext` (`filter-controls.md`),
`#5-query-translation-buildcontext` (`filter-elements/index.md`), `#1-the-filter-value-object` (5 links).
`spec/specifications.md`: retitle/rename only if the page is about `ListSpec` as a type; fix links to it.

- [ ] **Step 2: Migration pages**

`migrating-from-v0.1.md`: add an "Engine" section mapping each released v0.1 name to its successor —
`Engine\Context\ContextInterface` → `Engine\Intent\IntentInterface`,
`Engine\Context\Interface\{Form,Paginated,Sortable}ContextInterface` → `Engine\Intent\{Form,Paginated,Sortable}IntentInterface`,
`InteractiveContext`/`ValidationContext`/`AggregationContext` → `…Intent`,
`Engine\Context\Factory\*ContextFactory` → `Engine\Intent\Factory\*IntentFactory`,
`Engine\Context\ReaderUrlConfigCreatorTrait` → `Engine\Intent\ReaderUrlConfigCreatorTrait`, `getContextType()` →
`getIntentType()`, `Engine::getContext()` → `getIntent()`, `Engine::with(context:)` → `with(intent:)`,
`ReaderRenderEvent::getContext()` → `$intent`. Verify each v0.1 name with `git show v0.1:<path>` from the
repository root. `removed-in-v0.2.md`: matching entries; rewrite entries naming unreleased intermediates
(`ElementScope`, `ControlScope`, `ATTR_SCOPE`, `getElementScope()`, `FilterContextBuilderInterface`,
`buildContext()`, `ListSpec`, `Filter`) to the final names, without documenting the intermediate step.

- [ ] **Step 3: Audit and build**

From `docs/`, → only v0.1 names on the left-hand side of the migration/removed tables:

```bash
grep -rniE 'listspec|filtercontext|buildcontext|elementscope|controlscope|attr_scope|filter_scope|compoundoptions|->compound\(|enginecontext|getcontext\(|(interactive|validation|aggregation|configuration|form|paginated|sortable)context|contextinterface|contextfactory|\$scope|FilterFactory\b|`Filter`|Filter::\$' docs
```

If `docs/node_modules` exists, run `npm run build` in `docs/` → success, and its output contains no "broken
anchor"/"Broken link" warnings (`onBrokenAnchors` only warns, so read the output).

- [ ] **Step 4: Commit on `docs/main`**

```bash
git -C docs add -A docs
git -C docs commit -m "docs: rename to Compound, Effect, Context, Intent"
```
