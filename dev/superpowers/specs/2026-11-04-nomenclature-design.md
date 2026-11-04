# Design: nomenclature — Compound, Effect, Context, Intent

Status: agreed in conversation 2026-11-04, builder-checked, awaiting the user's review. Branch: `v0.2-nomenclature`
off `v0.2` (the unreleased major version; breaking changes are fine).

## Intent

v0.2 introduced several nouns that do not say what their classes are. Four concepts are renamed so each name
describes its role, and no two concepts share a word:

| Concept (in the user's words) | Today | New |
|---|---|---|
| All components assembled to process and render a list, ready to run (failures are a runtime error bag; the list is still runnable) | `ListSpec` | `ListCompound` |
| The same one level down: element and control bound with config and value, ready to be applied | `Filter` | `FilterCompound` |
| What an element hands the list driver: abstract query building blocks — predicates, and later joins, derivations (inner SELECTs) and order hints | `FilterContext`, `buildContext()` | `FilterEffect`, `buildEffect()` |
| The pre-filled situation handed to a hook | `ElementScope`, `ControlScope`, `DcaContext` | `ElementContext`, `ControlContext`, `DcaContext` |
| The order to the engine: what a list is rendered for, with an expected form of output | `Engine\Context\*Context` | `Engine\Intent\*Intent` |

Why each old name is wrong:

- **`ListSpec`** describes something not yet built; the object is built and runnable. "Spec" exists only because
  `List` is reserved in PHP. **`Filter`** names the act or the thing itself, not the bound composition. A
  *compound* is composed of bound elements — which is literally what `FilterCompound` is (`ElementBinding`,
  `ControlBinding`), and `ListCompound` binds a driver with its filter compounds.
- **`FilterContext`** is an *output* of the element, while "context" means an input. It also collided with the
  engine contexts. *Effect* is the filter's effect on the query and is not limited to WHERE (order hints and
  derivations restrict nothing).
- **Scope** means extent or access range (as in OIDC scopes); these objects are a pre-filled handover. Once the
  engine contexts are renamed, "context" is free for exactly the meaning in which it is right: an input that
  influences a hook without being its subject. `DcaContext` already is such an object and keeps its name.
- **Engine contexts** are a context only from a filter element's point of view. For the engine — their main
  user — they are the order: their type selects the projector (`supports()` by `instanceof`), they carry the
  run's parameters and even input data (`filterValues`). An *intent* says what the list is rendered for and is
  routed to the component that fulfils it (like an Android intent). `ConfigurationIntent` is never handed to an
  engine, but still answers "what for" (the backend configures the list).

The hook signature after the rename:

```php
public function buildEffect(FilterEffectBuilderInterface $builder, ElementContext $context, ?ValueInterface $value): void
// $context->list: ListCompound, $context->filter: FilterCompound, $context->intent, $context->get('key')
```

## Goals

1. Rename the classes, interfaces, methods, properties, parameters, variables, files, namespaces and tests listed
   under "Rename map", so that the old nouns (`ListSpec`, `Filter` as a type, `FilterContext`, `Scope`, engine
   `Context`) no longer occur for these concepts anywhere in `src/`, `tests/`, `config/`, `contao/`, `AGENTS.md`
   and the docs worktree.
2. Keep the observable behavior identical: the test suite (renamed) stays green, PHPStan stays green.

## Non-goals

- **`ListExecutionContext`** (and its factory, `DcaContext::getExecutionContext()`): left as is; it is rebuilt by
  the upcoming query refactor, in which the list driver builds the whole query (the way an element builds its
  effect today) and the query-building events disappear.
- The **ListModel exception** ([`2026-11-03-contao-namespace-design.md`](2026-11-03-contao-namespace-design.md),
  non-goals): `ListCompound`, `ListCompoundBuilder`, `ListCompoundFactory` and `BaseListOptions` keep taking a
  `ListModel`. This is a pure rename, not the planned list-layer refactor.
- Internal query vocabulary: `Formula`, `Proposition`, `FormulaBuilder*`, `FilterConditions`,
  `FilterConditionsBuilder`, `SqlQueryStruct`, `SqlJoinStruct` stay.
- Domain words that are not the renamed types stay: `FilterElement*`, `FilterControl*`, `FilterFailure`,
  `FilterInvoker`, `FilterCollector`, `CollectedFilters`, `FilterMount`, `FilterFormBuilder*`, `FilterInspector`,
  `FilterException`, `ListDriver*`, `ListQueryDirector`, `ListQueryConfig`, `ResolvedListDriver`, the event
  classes and all named-event strings (`flare.list_driver.{type}.build_list` etc.), the hook `buildList()`.
- Historical design documents in `dev/superpowers/` (specs, plans, ideas) are not rewritten.
- No new features, no unused-code removal (e.g. whether `getIntentType()` is still needed is a separate question).

## Anti-goals

- Any change in observable behavior, SQL, form names, template variables or Twig function names.
- BC shims, `class_alias`, deprecated aliases — v0.2 is unreleased (as in the earlier v0.2 refactors).
- A half-renamed state on the branch's final commit: old and new name side by side in code, `AGENTS.md` or docs.
- Renaming beyond the map "because it is nearby" — anything not listed is out of scope; a builder who finds a
  further candidate notes it instead of renaming it.

## Rename map

Rule behind the map: a name that denotes one of the five renamed concepts as a *type* follows the new noun
(including factories, builders and tests named after their product). Variable and property names that denote the
*domain thing* stay: `$list`, `$filter`, `$filters` remain, because a `ListCompound` is still "the list" when
held. Only names that spell the old noun change (`$spec`, `$listSpec`, `$scope`, `$engineContext`, `$context`
where it holds an engine intent).

### List and filter → Compound

| Today | New |
|---|---|
| `List\ListSpec` (`src/List/ListSpec.php`) | `List\ListCompound` (`src/List/ListCompound.php`) |
| `List\ListSpecBuilder`, `ListSpecBuilderInterface` | `ListCompoundBuilder`, `ListCompoundBuilderInterface` |
| `List\Factory\ListSpecFactory` | `List\Factory\ListCompoundFactory` |
| `List\Factory\ListSpecBuilderFactory` (core) and `Contao\Factory\ListSpecBuilderFactory` | `ListCompoundBuilderFactory` (both; import aliases `CoreListSpecBuilderFactory` → `CoreListCompoundBuilderFactory`, `ContaoListSpecBuilderFactory` → `ContaoListCompoundBuilderFactory`) |
| `Filter\Filter` (`src/Filter/Filter.php`) | `Filter\FilterCompound` (`src/Filter/FilterCompound.php`) |
| `Filter\Factory\FilterFactory` (core) and `Contao\Factory\FilterFactory` | `FilterCompoundFactory` (both; alias `CoreFilterFactory` → `CoreFilterCompoundFactory`) |
| variables/params `$spec`, `$listSpec`, `$listSpecs` | `$list`, `$lists` (where a `$list` already exists in the scope, the builder picks a clear name) |
| PHPDoc, comments, exception messages mentioning "ListSpec", "list spec" or `Filter` as the type (e.g. `'[FLARE] ListSpec data container cannot be used …'`) | the new name |

"Compound" is then taken for this concept, so its existing use in the filter-form API goes (user's decision):

| Today | New |
|---|---|
| `Form\FilterFormBuilderInterface::compound(array $options)` (`@api`) and `FilterFormBuilder::compound()` | `setMountOptions(array $options)` |
| `getCompoundOptions()` | `getMountOptions()` |
| PHPDoc/tests speaking of a filter's "compound mount" or "compound filters" (e.g. `testMountMapRecordsSingleAndCompoundFiltersUnderTheirAlias`) | "the mount of a multi-field filter", "multi-field filters" |

Symfony's own `compound` form option (and any `'compound' => …` passed to Symfony) is Symfony vocabulary and stays;
prose may keep "compound" only where it explicitly means a Symfony compound form (e.g. "mounts as a nested
Symfony compound form"), never as a bare noun for a filter's mount. Docs: `docs/docs/dev/filter-controls.md`,
`docs/docs/spec/filtering.md`.

`ListCompound::$filters`, `withFilter()`, `withoutFilter()`, `withFilters()`, `hasFilterInstance()`,
`FilterCompound::withValue()` etc. keep their names. `ElementBinding`, `ControlBinding`, `ComponentConfig` stay.

### Element product → Effect

| Today | New |
|---|---|
| `Filter\FilterContext` | `Filter\FilterEffect` |
| `Filter\FilterContextBuilderInterface` (`@api`) | `Filter\FilterEffectBuilderInterface` |
| `Filter\FilterContextBuilder` (`@internal`) | `Filter\FilterEffectBuilder` |
| `Filter\Factory\FilterContextFactory` | `Filter\Factory\FilterEffectFactory` |
| `Filter\Factory\FilterContextBuilderFactory` | `Filter\Factory\FilterEffectBuilderFactory` |
| `FilterElementInterface::buildContext()` and all implementations | `buildEffect()` |
| method-name strings built for error sources (`… . '::buildContext'` in `FilterInvoker`) | `'::buildEffect'` |
| local variables holding the product (`$context = $this->filterContextFactory->create(…)`) | `$effect` |

### Hook handover → Context

| Today | New |
|---|---|
| `Filter\ElementScope` | `Filter\ElementContext` |
| `Filter\ControlScope` | `Filter\ControlContext` |
| `ControlScope::forElement(): ElementScope` | `ControlContext::forElement(): ElementContext` (name kept) |
| `ControlScope::element()`, `tryElement()` | unchanged |
| property `$engineContext` on both | `$intent` |
| `Contao\DcaContext` | unchanged name; `getElementScope()` → `getElementContext()`, constructor param `$elementScopeFactory` → `$elementContextFactory` |
| `Contao\DataContainer\BackendElementScopeFactory` | `BackendElementContextFactory` |
| parameters/variables `$scope` (all hooks, contracts, factories) | `$context` |
| `Form\FilterFormBuilderInterface::ATTR_SCOPE = 'flare.filter_scope'` (`@api`, holds the `ControlScope`) | `ATTR_CONTEXT = 'flare.control_context'` (Claude's call) |

Parameter order stays as documented ("the subject first, the context supplementary"):
`buildEffect($builder, $context, $value)`, `buildForm($builder, $context)`, `decode($data, $context)`,
`hydrate($value, $context)`.

### Engine context → Intent

| Today | New |
|---|---|
| namespace `Engine\Context\` (`src/Engine/Context/`) | `Engine\Intent\` (`src/Engine/Intent/`) |
| `ContextInterface` | `IntentInterface` |
| `InteractiveContext`, `ValidationContext`, `AggregationContext`, `ConfigurationContext` | `InteractiveIntent`, `ValidationIntent`, `AggregationIntent`, `ConfigurationIntent` |
| `FormContextInterface`, `PaginatedContextInterface`, `SortableContextInterface` | `FormIntentInterface`, `PaginatedIntentInterface`, `SortableIntentInterface` |
| `ReaderUrlConfigCreatorTrait` | unchanged (moves with the namespace) |
| `Engine\Context\Factory\{Interactive,Validation,Aggregation}ContextFactory` | `Engine\Intent\Factory\{Interactive,Validation,Aggregation}IntentFactory` |
| `ContextInterface::getContextType()` | `IntentInterface::getIntentType()` (return values `'interactive'` etc. unchanged) |
| `Engine::getContext()`, `Engine::with(context: …)`, constructor/property `$context` | `getIntent()`, `with(intent: …)`, `$intent` |
| `EngineFactory::createEngine(ContextInterface $context, …)` | `createEngine(IntentInterface $intent, …)` |
| `ProjectorInterface`/`AbstractProjector` `supports()`, `priority()`, `project()` param `$context` (and the `@template TContext`) | `$intent` (`TIntent`) |
| `{Interactive,Validation,Aggregation}LoaderConfig::$context`, `ListQueryConfig::$context` | `$intent` |
| `FlareRuntime::project(ListSpec $spec, ContextInterface $config)` | `project(ListCompound $list, IntentInterface $intent)` (Twig function `flare_project` unchanged) |
| form root attribute `'flare.engine_context'` (`FormHarnessFactory`) | `'flare.intent'` |
| variables/params holding an engine context (`$context`, `$validationContext`, `$interactiveConfig`, `$aggregationConfigFactory`, `$config` typed as a context …) | `$intent`, `$validationIntent`, `$interactiveIntent`, `$aggregationIntentFactory` … |
| assert messages like `'$config must be an instance of AggregationConfig'` | `'$intent must be an instance of AggregationIntent'` |

### Tests

Test classes, traits and helper methods follow their subjects, e.g. `ListSpecTest` → `ListCompoundTest`,
`ListSpecBuilderTest`, `ListSpecFactoryTest`, `ListSpecBuilderFactoryTest`, `FilterTest` → `FilterCompoundTest`,
`FilterFactoryTest`, `FilterContextFactoryTest` → `FilterEffectFactoryTest`, `ElementScopeTest` →
`ElementContextTest`, `ControlScopeTest` → `ControlContextTest`, `BackendElementScopeFactoryTest`,
`BuildsFilterContextsTrait` → `BuildsFilterEffectsTrait` (it builds `FilterContext`s, i.e. effects),
`BuildsControlScopesTrait` → `BuildsControlContextsTrait`, helpers `buildFilterContext()` → `buildFilterEffect()`,
`buildElementScope()` → `buildElementContext()`, `createFilterContextFactory()` → `createFilterEffectFactory()`,
`createListSpecBuilderFactory()` → `createListCompoundBuilderFactory()`,
`testConfigurationContextHasItsOwnType` → `testConfigurationIntentHasItsOwnType`, and so on for any test or
fixture named after an old noun.

### Documentation

- `AGENTS.md` (`CLAUDE.md` is a symlink): every mention, including the architecture flow, the paragraph on filter
  execution, "Notable subsystems", the lifecycle taxonomy (`buildContext` → `buildEffect`) and the note that
  `ListSpec` is "named 'Spec' only because `List` is reserved" (replace with the reason for *Compound*). Add one
  short glossary line per concept so the vocabulary is anchored: Compound = assembled, runnable list/filter;
  Effect = an element's building blocks for the query; Context = the pre-filled handover to a hook; Intent =
  what the engine renders a list for.
- The docs worktree (`docs/`, branch `docs/main`, committed separately there): the ~18 pages that mention the
  old names (`docs/docs/spec/*`, `docs/docs/dev/*`, `basic-usage.md`, `templating.mdx`, `reference/twig.md`,
  `migrating-from-v0.1.md`, `removed-in-v0.2.md`). Doc files whose path or title is an old noun (e.g. a page
  titled "Specifications" about `ListSpec`) are renamed only if the page is about the renamed type; links follow.
  The docs describe the step from v0.1 to the final v0.2 (Claude's call):
  - **Engine contexts are released v0.1 API** (`Engine\Context\ContextInterface`,
    `Engine\Context\Interface\{Form,Paginated,Sortable}ContextInterface`, `InteractiveContext` etc., the
    `*ContextFactory` classes, `Engine::getContext()`, `Engine::with(context: …)`, `getContextType()`). Add an
    engine section to `migrating-from-v0.1.md` and entries to `removed-in-v0.2.md` mapping each v0.1 name to its
    `Intent` successor — for custom projectors, mods and `flare_project` callers.
  - `ListSpec`, `Filter`, `FilterContext`, `ElementScope`/`ControlScope` never existed in a release. Existing
    history entries that point at them (e.g. "`Filter\FilterScope` — replaced by `ElementScope` …",
    "`DcaContext::getFilterScope()` — replaced by `getElementScope()`", "`FilterScope::ATTR_SELF` became
    `ATTR_SCOPE`") are rewritten to point at the final names; no v0.2-internal rename steps are documented.

## Constraints

- PHP ^8.2, `declare(strict_types=1)`, PSR-12/PER, 120-char lines (Mago runs in CI only, no make target).
- Run tests with `make test`, static analysis with `make phpstan`.
- Service IDs are class names (autowiring); renaming a class renames its service. Check `config/services.yaml`
  and `config/integrations/*.yaml` for explicit references (excludes, aliases, arguments) and for the `Context`
  directory under `Engine` in resource/exclude globs.
- Commits on this repo carry no `Co-Authored-By` trailer and are never pushed by the agent.

## Left to the builder

- Order and slicing of the work (e.g. one commit per concept), and whether to use PhpStorm's rename refactoring
  or scripted replacements — as long as the final state matches the map and every intermediate commit is green.
- Names for local variables where the map's rule leads to a clash in one scope.
- Wording of the `AGENTS.md` glossary lines and doc prose.
