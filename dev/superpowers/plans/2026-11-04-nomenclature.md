# Nomenclature (Compound, Effect, Context, Intent) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rename `ListSpec`/`Filter` → `ListCompound`/`FilterCompound`, `FilterContext` → `FilterEffect`,
`ElementScope`/`ControlScope` → `ElementContext`/`ControlContext`, engine `*Context` → `*Intent`, and the form
API's `compound()` → `setMountOptions()`, without any change in behavior.

**Architecture:** A pure rename in six slices, one concept per task, each ending green and committed. The order
frees words before they are reused: engine contexts become intents first (frees `$context`), then the element's
product becomes an effect (frees `$context` in `FilterInvoker` and the test helpers), then scopes become contexts,
then the form API gives up "compound", then list and filter become compounds. Docs follow last.

**Tech Stack:** PHP 8.2+, Symfony, Contao, PHPUnit 9, PHPStan (level 5); `make test`, `make phpstan` (Docker).

**Spec:** `dev/superpowers/specs/2026-11-04-nomenclature-design.md` — its "Rename map" is the authority for every
name; this plan only orders the work and names the checks. Read the spec's map section for your task before
starting it.

## Global Constraints

- Branch `v0.2-nomenclature` (exists, holds the spec). Commits: **no `Co-Authored-By` trailer, never push.**
- No BC shims, no `class_alias`, no deprecated aliases.
- No behavior change: no SQL, form name, template variable or Twig function name changes; event classes and
  named-event strings stay; `buildList()` stays.
- Out of scope, do not touch: `ListExecutionContext` (+ factory, `DcaContext::getExecutionContext()`),
  `Formula`, `Proposition`, `FormulaBuilder*`, `FilterConditions*`, `Sql*Struct`, `getChoiceScope()`/`ChoiceKey`
  (choice scope is a different concept), `src/Integration/Terminal42Languages/` (legacy, PHPStan-excluded),
  historical files in `dev/superpowers/`.
- Variable/property rule: `$list`, `$filter`, `$filters` stay; names spelling an old noun change (`$spec`,
  `$listSpec`, `$scope`, `$engineContext`, `$context` holding an intent or an effect).
- Move files with `git mv` so history follows. PhpStorm's rename refactoring (`mcp__phpstorm__rename_refactoring`)
  is the preferred tool for classes/methods; scripted `sed` is fine for bulk occurrences — review the diff either
  way.
- `declare(strict_types=1);`, PSR-12/PER, **120-char lines**. Renames change name lengths: re-align the
  column-aligned promoted constructor parameters in touched files, and before each commit run
  `git diff --name-only HEAD | grep '\.php$' | xargs awk 'length > 120 {print FILENAME":"FNR}'` → no output.
- `AGENTS.md` is updated in the task that renames the concept it mentions (`CLAUDE.md` is a symlink — edit
  `AGENTS.md` only).
- Every task ends with `make test` → `OK`, and `make phpstan` → `[OK] No errors`.

## Review Focus

1. **Intent classes registered as services.** `config/services.yaml` excludes `../src/Engine/Context/*.php`; a moved
   directory without an updated glob turns `InteractiveIntent` & co. into autowired services and breaks container
   compilation. Pinned in Task 1 (`ServicesConfigTest`).
2. **Error sources still saying `::buildContext`.** `FilterInvoker` builds the failing method name as a string;
   a missed string makes editors' and logs' error messages name a method that no longer exists. Pinned in Task 2
   (`FilterInvokerTest`).
3. **Form attribute keys.** `ATTR_SCOPE = 'flare.filter_scope'` and the literal `'flare.engine_context'` are
   string keys that third-party controls/listeners read; the new keys must be exactly `'flare.control_context'`
   and `'flare.intent'`. Pinned in Task 3 and Task 1.
4. **Twig named arguments.** `flare_project()` maps to `FlareRuntime::project()`; renaming its PHP parameters to
   `$list`, `$intent` changes Twig named-argument names. Intended (v0.2 is breaking) — the docs in Task 7 must show
   the new names.
5. **Stale words in prose.** Exception messages, PHPDoc and comments ("ListSpec data container …", "scope",
   "compound mount") are not checked by PHP or tests; each task's audit grep covers them.

---

### Task 1: Engine context → Intent

**Files:**
- Move: `src/Engine/Context/` → `src/Engine/Intent/` (all classes renamed per the spec's map, incl. `Factory/`)
- Modify: `config/services.yaml:29` (exclude glob → `../src/Engine/Intent/*.php`), every consumer (Controllers,
  `Engine`, `EngineFactory`, Projectors incl. `src/Integration/ContaoCalendar/Projector/`, Loaders + `*LoaderConfig`,
  `ListQueryConfig`, `ListQueryDirector`, `FormHarnessFactory`, `PageParamMod`, `BreadcrumbHook`,
  `FlareRuntime`, `ElementScope`, `ControlScope`, `BackendElementScopeFactory`, `FilterInspector`,
  `CalendarCurrentFilterElement`), tests under `tests/Engine/` and every test importing an engine context, `AGENTS.md`
- Test: `tests/DependencyInjection/ServicesConfigTest.php`

**Interfaces:**
- Produces: `Engine\Intent\IntentInterface` (`getIntentType(): string`), `InteractiveIntent`, `ValidationIntent`,
  `AggregationIntent`, `ConfigurationIntent`, `FormIntentInterface`, `PaginatedIntentInterface`,
  `SortableIntentInterface`, `Engine\Intent\Factory\{Interactive,Validation,Aggregation}IntentFactory`;
  `Engine::getIntent()`, `Engine::with(?IntentInterface $intent = null, …)`,
  `EngineFactory::createEngine(IntentInterface $intent, ListSpec $list, array $mods = [])`;
  `ElementScope::$intent` and `ControlScope::$intent` (were `$engineContext`); `*LoaderConfig::$intent`,
  `ListQueryConfig::$intent`; `FlareRuntime::project(ListSpec $list, IntentInterface $intent)`;
  form root attribute key `'flare.intent'`.

- [ ] **Step 1: Pin the service registration in `ServicesConfigTest`**

Add to `provideNonServices()`: `InteractiveIntent::class`, `ValidationIntent::class`, `AggregationIntent::class`,
`ConfigurationIntent::class` (imports from `HeimrichHannot\FlareBundle\Engine\Intent\`). Add to
`provideServices()`: `InteractiveIntentFactory::class`, `ValidationIntentFactory::class`,
`AggregationIntentFactory::class`.

- [ ] **Step 2: Run it, expect failure**

Run: `make test tests/DependencyInjection/ServicesConfigTest.php`
Expected: errors — classes `…\Engine\Intent\…` not found.

- [ ] **Step 3: Rename per the spec's "Engine context → Intent" table**

`git mv` the directory and files, rename classes/interfaces/methods/properties/params/variables, the services.yaml
glob, the form attribute literal, the assert messages. Rename tests named after engine contexts
(e.g. `testConfigurationContextHasItsOwnType` → `testConfigurationIntentHasItsOwnType`, test files under
`tests/Engine/Context/` → `tests/Engine/Intent/`). Update `AGENTS.md` (architecture flow, "Contexts and
Projectors/Views are separate axes" → Intents, every `Context (Interactive / Validation / Aggregation)` mention).

- [ ] **Step 4: Run the suite and the audit**

Run: `make test` → `OK`; `make phpstan` → `[OK] No errors`;
`grep -rnE 'Engine\\Context|\b(Interactive|Validation|Aggregation|Configuration|Form|Paginated|Sortable)?Context(Interface|Factory)?\b|getContextType|engine_context|engineContext|getContext\(\)' src tests config contao AGENTS.md | grep -vE 'ListExecutionContext|DcaContext|ContaoContext|ResponseContext|ExecutionContextInterface|Terminal42Languages'`
Expected: no output. (Remaining bare `Context` words in prose: judge each — only the engine meaning must go.)

- [ ] **Step 5: Commit**

```bash
git add -A src tests config AGENTS.md
git commit -m "refactor!: rename engine contexts to intents"
```

### Task 2: Element product → Effect

**Files:**
- Modify/move: `src/Filter/FilterContext.php` → `FilterEffect.php`, `FilterContextBuilder.php` →
  `FilterEffectBuilder.php`, `FilterContextBuilderInterface.php` → `FilterEffectBuilderInterface.php`,
  `src/Filter/Factory/FilterContextFactory.php` → `FilterEffectFactory.php`,
  `src/Filter/Factory/FilterContextBuilderFactory.php` → `FilterEffectBuilderFactory.php`;
  `FilterElementInterface`, `AbstractFilterElement` and every element (`src/Filter/Element/`,
  `src/Integration/*/FilterElement/`), `FilterInvoker`, `FilterException` PHPDoc; tests incl.
  `tests/Filter/BuildsFilterContextsTrait.php` → `BuildsFilterEffectsTrait.php`, `FilterContextFactoryTest.php` →
  `FilterEffectFactoryTest.php`, anonymous elements in test traits (e.g. `BuildsControlScopesTrait::buildContext`);
  `AGENTS.md`
- Test: `tests/Query/Executor/FilterInvokerTest.php`

**Interfaces:**
- Consumes: Task 1's `IntentInterface` (test helpers take `?IntentInterface $intent = null`).
- Produces: `FilterElementInterface::buildEffect(FilterEffectBuilderInterface $builder, ElementScope $scope, ?ValueInterface $value): void`;
  `FilterEffect { public Formula $formula }`; `FilterEffectBuilder::build(): FilterEffect`;
  `FilterEffectFactory::create(ElementScope $scope, ?ValueInterface $value): FilterEffect`;
  `FilterEffectBuilderFactory::create(ElementScope $scope)`; test trait `BuildsFilterEffectsTrait` with
  `buildFilterEffect(…): FilterEffect`, `createFilterEffectFactory(…): FilterEffectFactory` (keeps
  `buildElementScope()` until Task 3).

- [ ] **Step 1: Pin the error source**

In `testFilterExceptionIsWrappedWithTheSource` and `testOtherExceptionsBecomeFilterExceptions` add:

```php
self::assertStringEndsWith('::buildEffect', (string) $e->getMethod());
```

- [ ] **Step 2: Run it, expect failure**

Run: `make test tests/Query/Executor/FilterInvokerTest.php`
Expected: FAIL — `…::buildContext` does not end with `::buildEffect`.

- [ ] **Step 3: Rename per the spec's "Element product → Effect" table**

Includes the `'::buildContext'` string in `FilterInvoker` and the local `$context` holding the product → `$effect`.
Private helpers named after the old product follow (e.g. `ArchiveFilterElement::buildFixedContext()` →
`buildFixedEffect()`). `AGENTS.md`: flow text, lifecycle taxonomy (`buildContext` → `buildEffect`), the
`FilterContext` paragraph and parameter-order sentence. Also update test fixtures asserting the string, e.g.
`tests/List/FilterFailureTest.php` (`X::buildContext` → `X::buildEffect`).

- [ ] **Step 4: Run the suite and the audit**

Run: `make test` → `OK`; `make phpstan` → `[OK] No errors`;
`grep -rnE 'FilterContext|buildContext|buildFilterContext|FixedContext' src tests config contao AGENTS.md | grep -v Terminal42Languages`
Expected: no output.

- [ ] **Step 5: Commit**

```bash
git add -A src tests AGENTS.md
git commit -m "refactor!: rename FilterContext to FilterEffect, buildContext() to buildEffect()"
```

### Task 3: Hook handover Scope → Context

**Files:**
- Move: `src/Filter/ElementScope.php` → `ElementContext.php`, `src/Filter/ControlScope.php` → `ControlContext.php`,
  `src/Contao/DataContainer/BackendElementScopeFactory.php` → `BackendElementContextFactory.php`
- Modify: `src/Contao/DcaContext.php`, `src/Contao/Hook/ElementDcaHook.php`, `src/Form/FilterFormBuilderInterface.php`,
  `src/Form/Factory/FilterFormBuilderFactory.php`, `src/Form/Factory/FormHarnessFactory.php`, all contracts in
  `src/Contract/FilterElement/`, all elements/controls/value providers taking a scope, `FilterInvoker`,
  `ListQueryDirector`, `FilterInspector` (its private `buildScope(\Closure $buildScope, …): ?ListSpec` builds the
  list → `buildList(\Closure $buildList, …)`), `config/` if referenced; tests incl. `ElementScopeTest`,
  `ControlScopeTest`, `BackendElementScopeFactoryTest`, `tests/Filter/Control/BuildsControlScopesTrait.php` →
  `BuildsControlContextsTrait.php` (helper `scope()` → `context()`), `BuildsFilterEffectsTrait::buildElementScope()`
  → `buildElementContext()`, per-test helpers `createScope()` → `createContext()`; `AGENTS.md`
- Test: `tests/Form/FilterFormBuilderFactoryTest.php`, `tests/DependencyInjection/ServicesConfigTest.php`

**Interfaces:**
- Consumes: Task 1 (`$intent`), Task 2 (`buildEffect()`).
- Produces: `Filter\ElementContext(ListSpec $list, Filter $filter, IntentInterface $intent)` with `get()`, `has()`;
  `Filter\ControlContext(ListSpec $list, Filter $filter, IntentInterface $intent)` with `get()`, `has()`,
  `element()`, `tryElement()`, `forElement(): ElementContext`; `BackendElementContextFactory::create(\Closure $getList, FilterModel $filterModel): ?ElementContext`;
  `DcaContext::getElementContext(): ?ElementContext` (ctor param `$elementContextFactory`);
  `FilterFormBuilderInterface::ATTR_CONTEXT = 'flare.control_context'`; every hook's scope parameter is
  `$context` (`buildEffect($builder, ElementContext $context, $value)`, `buildForm($builder, ControlContext $context)`,
  `decode($data, ControlContext $context)`, `hydrate($value, ControlContext $context)`).

- [ ] **Step 1: Pin the attribute key and the service**

In `FilterFormBuilderFactoryTest` (the test asserting `ATTR_SCOPE` today) assert instead:

```php
self::assertSame('flare.control_context', FilterFormBuilderInterface::ATTR_CONTEXT);
self::assertInstanceOf(ControlContext::class, $builder->getAttribute(FilterFormBuilderInterface::ATTR_CONTEXT));
```

In `ServicesConfigTest::provideServices()` replace `BackendElementScopeFactory::class` with
`BackendElementContextFactory::class`.

- [ ] **Step 2: Run it, expect failure**

Run: `make test tests/Form/FilterFormBuilderFactoryTest.php` → errors (undefined constant / class).

- [ ] **Step 3: Rename per the spec's "Hook handover → Context" table**

`$scope` → `$context` everywhere it holds an element/control scope; the `$getSpec` closure variables may become
`$getList` now or in Task 5. `AGENTS.md`: every `ElementScope`/`ControlScope`/`$scope` mention, "value-free,
role-typed scope" wording, `DcaContext::getElementScope()`.

- [ ] **Step 4: Run the suite and the audit**

Run: `make test` → `OK`; `make phpstan` → `[OK] No errors`;
`grep -rnE 'Scope\b|Scopes|\$scope|scope\(|ATTR_SCOPE|filter_scope' src tests config contao AGENTS.md | grep -vE 'ChoiceScope|ChoiceKey|ChoicesBuilder|Terminal42Languages'`
Expected: no output. Then `grep -rniw scope src tests AGENTS.md | grep -viE 'choice'` and fix every hit that means
the hook handover (prose like "the element's scope").

- [ ] **Step 5: Commit**

```bash
git add -A src tests config AGENTS.md
git commit -m "refactor!: rename ElementScope/ControlScope to ElementContext/ControlContext"
```

### Task 4: Form API gives up "compound"

**Files:**
- Modify: `src/Form/FilterFormBuilderInterface.php`, `src/Form/FilterFormBuilder.php` (property
  `$compoundOptions` → `$mountOptions`), `src/Form/Factory/FormHarnessFactory.php:136`,
  `src/Filter/Control/DateRangeFilterControl.php`, PHPDoc in `src/Event/FilterControlBuildFormEvent.php`,
  `src/Filter/Control/FilterControlInterface.php`; tests `tests/Filter/Control/DateRangeFilterControlTest.php`,
  `tests/Form/FormHarnessFactoryTest.php`; `AGENTS.md` if it mentions it
- Test: `tests/Filter/Control/DateRangeFilterControlTest.php`

**Interfaces:**
- Produces: `FilterFormBuilderInterface::setMountOptions(array $options): static`,
  `FilterFormBuilderInterface::getMountOptions(): array`.

- [ ] **Step 1: Switch the test to the new API**

In `DateRangeFilterControlTest`: rename `testLabelBecomesTheCompoundMountsLabel` →
`testLabelBecomesTheMountsLabel`; its assertions use `$labelled->getMountOptions()` (`['label' => 'Period']`) and
`$plain->getMountOptions()` (`[]`).

- [ ] **Step 2: Run it, expect failure**

Run: `make test tests/Filter/Control/DateRangeFilterControlTest.php` → error, undefined method `getMountOptions()`.

- [ ] **Step 3: Rename per the spec's "Compound" form-API table**

`compound()` → `setMountOptions()`, `getCompoundOptions()` → `getMountOptions()`. Prose: "compound mount" → "the
mount of a multi-field filter"; "compound" stays only where it explicitly means Symfony's compound form (e.g.
"mounts as a nested Symfony compound form"); test names like `testMultiFieldControlMountsNestedCompound` →
`testMultiFieldControlMountsNestedForm`.

- [ ] **Step 4: Run the suite and the audit**

Run: `make test` → `OK`; `make phpstan` → `[OK] No errors`; `grep -rniE 'compound' src tests AGENTS.md`
Expected: only hits that say "Symfony compound form" or pass Symfony's `'compound'` option.

- [ ] **Step 5: Commit**

```bash
git add -A src tests AGENTS.md
git commit -m "refactor!: rename FilterFormBuilder compound() to setMountOptions()"
```

### Task 5: List and filter → Compound

**Files:**
- Move: `src/List/ListSpec.php` → `ListCompound.php`, `ListSpecBuilder.php` → `ListCompoundBuilder.php`,
  `ListSpecBuilderInterface.php` → `ListCompoundBuilderInterface.php`,
  `src/List/Factory/ListSpecFactory.php` → `ListCompoundFactory.php`,
  `src/List/Factory/ListSpecBuilderFactory.php` → `ListCompoundBuilderFactory.php`,
  `src/Contao/Factory/ListSpecBuilderFactory.php` → `ListCompoundBuilderFactory.php`,
  `src/Filter/Filter.php` → `FilterCompound.php`, `src/Filter/Factory/FilterFactory.php` →
  `FilterCompoundFactory.php`, `src/Contao/Factory/FilterFactory.php` → `FilterCompoundFactory.php`
- Modify: every consumer in `src/` (incl. `src/Integration/` except Terminal42Languages) and `tests/`; test files
  `ListSpecTest`, `ListSpecBuilderTest`, `ListSpecFactoryTest`, `ListSpecBuilderFactoryTest` (both, if present in
  `tests/List/Factory/` and `tests/Contao/Factory/`), `FilterTest` → `FilterCompoundTest`, `FilterFactoryTest`
  (both) → `FilterCompoundFactoryTest`; helpers `createListSpecBuilderFactory()`,
  `createContaoListSpecBuilderFactory()`, `createFilterFactory()` follow; `AGENTS.md`
- Test: `tests/List/ListCompoundTest.php`

**Interfaces:**
- Consumes: Tasks 1–4.
- Produces: `List\ListCompound` (same ctor/methods as `ListSpec`), `ListCompoundBuilder`,
  `ListCompoundBuilderInterface`, `List\Factory\ListCompoundFactory`, `List\Factory\ListCompoundBuilderFactory`,
  `Contao\Factory\ListCompoundBuilderFactory::create(ListModel $listModel): ListCompoundBuilder`,
  `Filter\FilterCompound` (same ctor/methods as `Filter`), `Filter\Factory\FilterCompoundFactory`,
  `Contao\Factory\FilterCompoundFactory`; import aliases `CoreListCompoundBuilderFactory`,
  `ContaoListCompoundBuilderFactory`, `CoreFilterCompoundFactory`; `ListDriverInterface::buildList(ListCompoundBuilder $builder)`.

- [ ] **Step 1: Move the list's own test first**

`git mv tests/List/ListSpecTest.php tests/List/ListCompoundTest.php`; rename the class to `ListCompoundTest` and
make it construct and assert `ListCompound` (and `FilterCompound` for its filters).

- [ ] **Step 2: Run it, expect failure**

Run: `make test tests/List/ListCompoundTest.php` → error, class `ListCompound` not found.

- [ ] **Step 3: Rename per the spec's "List and filter → Compound" table**

Type names, factories/builders, test names and helpers, `$spec`/`$listSpec`/`$getSpec` → `$list`/`$getList`
(pick a clear name where `$list` is taken), PHPDoc and messages (e.g. `'[FLARE] ListSpec data container cannot be
used as SQL table identifier'` → `'[FLARE] ListCompound data container …'`), "list spec" in prose → "list".
`AGENTS.md`: `ListSpec` mentions incl. "named 'Spec' only because `List` is reserved…" → explain *Compound*
(assembled from bound components, ready to run), `Filter` DTO wording, `Factory/FilterFactory::create()`,
`ListSpecBuilderFactory::create(ListModel)`, the "Exception, until their own refactor" sentence with new names.

- [ ] **Step 4: Run the suite and the audit**

Run: `make test` → `OK`; `make phpstan` → `[OK] No errors`;
`grep -rnE 'ListSpec|list spec|\$spec\b|getSpec|Filter\\Filter;|\bFilter \$|FilterFactory\b|<string, Filter>|\?Filter\b|: Filter\b|\bFilter::class' src tests config contao AGENTS.md | grep -v Terminal42Languages`
Expected: no output.

- [ ] **Step 5: Commit**

```bash
git add -A src tests config AGENTS.md
git commit -m "refactor!: rename ListSpec/Filter to ListCompound/FilterCompound"
```

### Task 6: Glossary and final audit

**Files:**
- Modify: `AGENTS.md`

- [ ] **Step 1: Add the glossary**

A short "Vocabulary" block near the architecture section, one line each: *Compound* — the assembled, runnable
list (`ListCompound`) / filter (`FilterCompound`); *Effect* — an element's abstract building blocks for the query
(`FilterEffect`, `buildEffect()`); *Context* — the pre-filled handover to a hook (`ElementContext`,
`ControlContext`, `DcaContext`); *Intent* — what the engine renders a list for (`InteractiveIntent` …).

- [ ] **Step 2: Whole-branch audit**

Run each audit grep of Tasks 1–5 again → no output, except Task 1's pattern matching the word "Context" where it
now means the hook handover (the glossary line, prose about `ElementContext`/`ControlContext`). Run `make test` → `OK`, `make phpstan` → `[OK] No errors`,
the 120-char check over `git diff --name-only v0.2 | grep '\.php$'` → no output.

- [ ] **Step 3: Commit**

```bash
git add AGENTS.md
git commit -m "docs: add the vocabulary to AGENTS.md"
```

### Task 7: Docs worktree

**Files:**
- Modify (in `docs/`, the worktree on branch `docs/main` — commit there, not on `v0.2-nomenclature`):
  `docs/docs/spec/{specifications,filtering,projection}.md`, `docs/docs/dev/{engine-mods,events,filter-controls,predicates}.md`,
  `docs/docs/dev/contracts/{index.mdx,data-container-contract.md,build-query-contract.md}`,
  `docs/docs/dev/filter-elements/index.md`, `docs/docs/dev/list-types/index.md`, `docs/docs/basic-usage.md`,
  `docs/docs/templating.mdx`, `docs/docs/reference/{twig,predicates}.md`, `docs/docs/migrating-from-v0.1.md`,
  `docs/docs/removed-in-v0.2.md`

- [ ] **Step 1: Rename in prose and code samples**

Apply the whole map, including `setMountOptions()`/`getMountOptions()`, `ATTR_CONTEXT`, `buildEffect()`,
`ElementContext $context`, `$context->intent`, and `flare_project(list, intent)` in `reference/twig.md`.
`spec/specifications.md`: retitle/rename only if the page is about `ListSpec` as a type; fix links that point to
it.

- [ ] **Step 2: Migration pages**

`migrating-from-v0.1.md`: add an "Engine" section mapping each released v0.1 name to its successor —
`Engine\Context\ContextInterface` → `Engine\Intent\IntentInterface`,
`Engine\Context\Interface\{Form,Paginated,Sortable}ContextInterface` → `Engine\Intent\{…}IntentInterface`,
`InteractiveContext`/`ValidationContext`/`AggregationContext` → `…Intent`,
`Engine\Context\Factory\*ContextFactory` → `Engine\Intent\Factory\*IntentFactory`, `getContextType()` →
`getIntentType()`, `Engine::getContext()` → `getIntent()`, `Engine::with(context:)` → `with(intent:)`; check the
v0.1 names with `git -C .. show v0.1:src/Engine/…`. `removed-in-v0.2.md`: matching entries; rewrite existing
entries that name unreleased intermediates (`ElementScope`, `ControlScope`, `ATTR_SCOPE`, `getElementScope()`,
`FilterContextBuilderInterface`, `buildContext()`, `ListSpec`) to the final names, without documenting the
intermediate step.

- [ ] **Step 3: Audit and build**

Run (in `docs/`): `grep -rnE 'ListSpec|FilterContext|buildContext|ElementScope|ControlScope|ATTR_SCOPE|getCompoundOptions|compound\(|engineContext|(Interactive|Validation|Aggregation)Context\b' docs`
Expected: only the v0.1 names on the left-hand side of the migration tables. If the docs build is set up
(`make docs-setup` done, `node_modules` present), run `npm run build` in `docs/` → success without broken links.

- [ ] **Step 4: Commit on `docs/main`**

```bash
git -C docs add -A docs
git -C docs commit -m "docs: rename to Compound, Effect, Context, Intent"
```
