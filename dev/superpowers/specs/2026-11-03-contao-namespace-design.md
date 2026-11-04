# Design: consolidate the Contao-specific code in the `Contao` namespace

Status: agreed in conversation 2026-11-03, builder-checked, awaiting the user's review. Branch: off `v0.2` (the
unreleased major version; breaking changes are fine, no BC shims, no `class_alias`).

## Intent

`src/Contao/` holds a single class (`BackendModule`), while Contao-only code is spread over `src/DataContainer/`,
`src/EventListener/Contao/`, `src/EventListener/DataContainer/` and parts of `src/Filter/` and `src/List/`. The
translation of the stored config rows (`ListModel`, `FilterModel`) into FLARE's domain objects (`Filter`,
`ListSpecBuilder`) lives inside the core factories, so the core cannot be read or used without the Contao models.

After this refactor:

- the backend data-container code (table classes, DCA builder, inspection), the Contao hooks and the DCA callbacks
  live under `src/Contao/` — exactly the moves listed in "Target layout", nothing more;
- the processing of `FilterModel` → `Filter` lives only there; the core `FilterFactory` knows no model;
- the `Contao` namespace follows the same services layout as the other namespaces: DTOs / domain classes directly in
  `Contao/`, Symfony services in subfolders;
- the DCA builder API is collect-only for the components that receive it.

## Goals

1. Move `DataContainer/*` into `Contao/DataContainer/`, `DataContainer/Inspection/*` into `Contao/Inspection/`.
2. Move the Contao hooks into `Contao/Hook/` (renamed `*Hook`) and the DCA callbacks into `Contao/Callback/`.
3. Split `FilterFactory::createFromFilterModel()` into a Contao `FilterFactory`; give the core `FilterFactory::create()`
   a generic transformer source instead.
4. Move `ListSpecBuilderFactory::createFromListModel()` and `ListModelFilterCollector` into the Contao namespace.
5. Record the element's value class on `ElementBinding` and check every filter value against it.
6. Tighten the DCA builder API (collect-only interfaces, no stringly scope, interface-typed events).
7. Remove the redundant `CallbackFilterModelTransformer` / `CallbackListModelTransformer`.

## Non-goals

- **`ListSpec`, `ListSpecBuilder`, `ListSpecFactory` stay as they are** (user's decision): `ListSpecBuilder` keeps
  `?ListModel $model` and `getModel()`, `ListSpecFactory::createFromListModel()` and
  `BaseListOptions::transform(ListModel)` stay in `src/List/`. They will be refactored separately later; that
  refactor must make the list core model-free the way this one does for filters. No generic core
  `ListSpecBuilderFactory` with a config source is built now.
- `ReaderRequestAttribute` / `ReaderRequestAttributeFactory` (they carry and look up a `ListModel`) stay in `Reader/`.
- Models (`src/Model/`), filter elements, controls, list drivers, value providers, contracts (`src/Contract/`,
  including `DcaContract`) and events (`src/Event/`) stay where they are. Elements and drivers keep their
  `transformFilterModel()` / `transformListModel()` methods.
- All other event listeners stay in `src/EventListener/` (`NamedDispatch`, `QueryStructModifier`, `Reader`,
  `BackendMenuBuildListener`, `DataContainerRecordLabelEventListener`, `RequestListener`).
- Hooks and callbacks of the optional integrations stay in `src/Integration/` (e.g. the `changelanguageNavigation`
  hook in `Terminal42Languages/EventListener/ChangelanguageListener`, `CodefogTags/FilterCallback/TargetAliasCallback`).
- Contao entity models (`Contao\Model`, `PageModel`, `NewsModel`, …) — the records FLARE lists — are not affected.
- Other Contao-only code that is not listed in "Target layout" stays where it is (my call, following the scope the
  user set): the content-element controllers (`src/Controller/ContentElement/*`), `src/Migration/`,
  `src/Util/*` (`DcaHelper`, `BeActionsHelper`, `DcMultilingualHelper`, …), `src/ContaoManager/`. The target layout
  is the complete list of moves.
- `DcaContext` is not split into list/filter contexts, and its public `listModel` / `filterModel` stay.
- No behavior change for editors or visitors beyond the legacy-alias case and the value-class mismatch (now a
  500), both below.

## Target layout

```
src/Contao/
  BackendModule.php                       (unchanged)
  CollectedFilters.php                    ← List/Collector/CollectedFilters.php
  DcaContext.php                          ← DataContainer/Builder/DcaContext.php
  Callback/
    FlareFilter/                          ← EventListener/DataContainer/FlareFilter/* (class names unchanged)
    FlareList/                            ← EventListener/DataContainer/FlareList/*  (class names unchanged)
                                            + the config.onsubmit callback taken out of ListContainer
  DataContainer/
    ContentContainer.php                  ← DataContainer/ContentContainer.php
    FilterContainer.php                   ← DataContainer/FilterContainer.php
    ListContainer.php                     ← DataContainer/ListContainer.php (without onSubmitConfig)
    BackendElementScopeFactory.php        ← DataContainer/BackendElementScopeFactory.php
    Builder/                              ← DataContainer/Builder/* (builders only, see "DCA builder")
      DcaBuilder.php, DcaBuilderInterface.php
      DcaFieldBuilder.php, DcaFieldBuilderInterface.php
      ControlDcaBuilder.php               ← ControlScopeDcaBuilder.php
  Factory/
    FilterFactory.php                     new, from Filter/Factory/FilterFactory::createFromFilterModel()
    ListSpecBuilderFactory.php            new, from List/Factory/ListSpecBuilderFactory::createFromListModel()
    FilterCollector.php                   ← List/Collector/ListModelFilterCollector.php
  Hook/
    BreadcrumbHook.php                    ← EventListener/Contao/BreadcrumbListener.php
    ElementDcaHook.php                    ← EventListener/Contao/ElementDcaListener.php
    FormNameRegexpHook.php                ← EventListener/Contao/FormNameRegexpListener.php
  Inspection/
    FilterIssue.php, FilterIssueKind.php, InspectedRows.php, ListInspection.php   (DTOs)
    Inspector/FilterInspector.php
    Reporter/FilterIssueReporter.php
  PtableInference/                        ← InferPtable/ (renamed: a namespace is a noun, not a verb)
    PtableInferrer.php, PtableInferrable.php, PtableInferrableInterface.php   (non-services, class names unchanged)
    Factory/PtableInferrableFactory.php
```

`PtableInference` is used by models (`ListModel`/`FilterModel` implement `PtableInferrableInterface`;
`Model/PtableInferrableTrait` stays in `Model/`), elements (`ArchiveFilterElement`, `BelongsToRelationFilterElement`),
`GenericDataContainerListDriver` and the callbacks — update all imports. `Exception/InferenceException` stays in
`Exception/`.

Removed afterwards: `src/DataContainer/`, `src/InferPtable/`, `src/EventListener/Contao/`, `src/EventListener/DataContainer/`,
`src/Filter/CallbackFilterModelTransformer.php`, `src/List/CallbackListModelTransformer.php`, and
`src/List/Collector/` — its `CollectedFilters` (the collector's result DTO, used by nothing else) moves to
`src/Contao/CollectedFilters.php` (my call: a DTO, so the `Contao/` root per the services rule).

Builder's calls (not discussed, settle sensibly): the names `Factory/` and `FilterCollector`; the class name of the onsubmit callback extracted from `ListContainer` (e.g. `Contao/Callback/FlareList/
DataContainerOnSubmitCallback`). `ListContainer::getFieldOptions_sortSettings()` (referenced as a string callback from
`contao/dca/tl_flare_list.php`), `getListedTableName()` and `hasFilterConfigured()` stay in `ListContainer`;
`FilterContainer::getModelsFromDataContainer()` stays in `FilterContainer`.

`ContentContainer::TABLE_NAME` etc. are referenced from `contao/dca/*.php`, `src/Model/*`, migrations and
controllers; all references are updated (no aliases).

## `services.yaml`

`Contao` is excluded in the main resource block (it already is) and gets its own block, like `Engine`:

```yaml
HeimrichHannot\FlareBundle\:
    resource: ../src
    exclude:
        - ../src/{Config,Contao,ContaoManager,Contract,DependencyInjection,Dto,Engine,Event,Integration,Model,Trait,Util}
        - ../src/{Filter,Form,List,Paginator,Query,Sort}/*.php
        - ../src/Filter/Value
        # the two DataContainer excludes are gone

HeimrichHannot\FlareBundle\Contao\:
    resource: ../src/Contao
    exclude:
        - ../src/Contao/*.php
        - ../src/Contao/DataContainer/Builder
        - ../src/Contao/Inspection/*.php
        - ../src/Contao/PtableInference/*.php

HeimrichHannot\FlareBundle\Contao\Inspection\Inspector\FilterInspector:
    arguments:
        $debug: '%kernel.debug%'
```

Rule: DTOs / domain classes directly in `Contao/` (and directly in `Contao/Inspection/` and
`Contao/PtableInference/`, mirroring `Filter/*.php`);
Symfony services in subfolders. `DataContainer/Builder` is the one excluded subfolder (non-services, like
`Filter/Value`). `ContentContainer` (constants only) sits in the services folder `DataContainer/` as today — it is
registered and removed as unused, as it is today in the main block. Verify with `make php bin/console debug:container`
(or the test kernel) that every moved service is still registered and autowired, and that `#[AsHook]` /
`#[AsCallback]` still register (autoconfigure).

## Filter creation

### Core `FilterFactory::create()` (`src/Filter/Factory/FilterFactory.php`)

`createFromFilterModel()`, `provideInitialValue()` and `provideValue()` leave the core factory. `create()` gains a
transformer source:

```php
public function create(
    FilterElementInterface|string      $element,
    ?string                            $alias = null,
    array                              $config = [],
    FilterControlInterface|string|null $control = null,
    array                              $controlConfig = [],
    ?ValueInterface                    $value = null,
    ?string                            $targetAlias = null,
    bool                               $targetingForced = false,
    ?object                            $configSource = null,
    ?string                            $source = null,
): Filter
```

- `$configSource` (any object, e.g. a `FilterModel` — the core does not know): when given, the element's config is
  `FilterTransformerResolver::transform($element, $type, $configSource, $source) ?? []`, with `$config` merged on top
  (explicit keys win); the same for the control with `$controlConfig`. Then both go through
  `FilterOptionsResolver::resolve()` as today. Without a source, behavior is unchanged.
- The alias rules stay as they are: a controlled filter needs a non-empty alias that is a valid form name
  (`assertFormName()`), **always** — there is no switch to skip the check (user's decision, see "Legacy aliases").
- The value-class check moves out of `create()` into `Filter` (see below); `create()` passes `$value` through.
- Docblocks: `create()` is now *the* construction path; the class docblock and `@internal` note on
  `Filter::__construct` keep pointing at it.

### `ElementBinding::$valueClass` (approved)

```php
final readonly class ElementBinding
{
    /**
     * @param class-string<ValueInterface>|null $valueClass The value class the element consumes, from its
     *   registration (or its {@see AsFilterElement} attribute for inline instances); null for an element
     *   without a runtime value (intrinsic).
     */
    public function __construct(
        public FilterElementInterface $component,
        public string                 $type,
        public ComponentConfig        $config,
        public ?string                $valueClass,
    ) {}
}
```

- Required, no default: `null` means "the element takes no value", so a default would silently be wrong in the ~30
  test fixtures that build bindings by hand — update them all.
- `FilterFactory::create()` fills it from `getElementValueClass()` (stays private).

### Value check in `Filter`

`Filter` rejects a value that does not match `$this->element->valueClass`: a non-null value on an element without a
value class, or a value that is not an instance of it. `null` is always accepted.

**A mismatch is a programming error (user's decision): it must fail loudly, with HTTP 500.** Stored data can never
produce one — value providers and controls are registered by their narrowed return type — so a mismatching value
always comes from code (a `ListDriverBuildListEvent` or `FilterCollectedEvent` listener, a list driver, a programmatic
`create()` call). Therefore the check throws a new `\Error`, not a `FlareException`:

```php
namespace HeimrichHannot\FlareBundle\Exception;

/**
 * A filter was given a value its element does not consume. This is a programming error, not a filter failure: as an
 * {@see \Error} it escapes every filter failure handling unwrapped and fails the request.
 */
final class ValueClassMismatchError extends \Error { /* message as today's create() check, plus the source */ }
```

- Like `UndefinedConfigKeyError`: never caught by the bundle's `catch (\Exception)` / `catch (FlareException)`
  blocks — not by `ListModelFilterCollector`/`FilterCollector` (no `FilterFailure`, no `list.failed`), not by
  `ListQueryDirector` (no retry with null), not by `ListViewController` / `ReaderController` (no "malconfigured"
  response). The request fails with 500 through the regular error handling. The backend `FilterInspector` keeps
  catching `\Error` outside debug mode and reports the filter as a programming error.
- This replaces today's `FlareException` in `FilterFactory::create()` for a mismatching value (the check moves into
  `Filter`; the core `create()` no longer checks itself). Adjust its `@throws` and the tests asserting the
  `FlareException`.
- Name of the class is my call.

Builder's call: put the check in `Filter::__construct()` (then `withValue()`, `withControl()`, `withTargetAlias()`
and `create()` are all covered, since they construct through it) rather than only in `withValue()` — the user
approved "the check in `withValue()`"; the constructor is the superset.

AGENTS.md: add the rule next to the `UndefinedConfigKeyError` paragraph (a value-class mismatch is a programming
error; `ValueClassMismatchError` fails the request, never retried, never degraded).

### Contao `FilterFactory` (`src/Contao/Factory/FilterFactory.php`, new)

Owns everything `createFromFilterModel()` did, on top of the core factory:

```php
final readonly class FilterFactory
{
    public function __construct(
        private CoreFilterFactory     $filterFactory,     // HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory
        private ValueProviderRegistry $valueProviderRegistry,
        private LoggerInterface       $logger,
    ) {}

    /**
     * @param bool $withValue False to skip reading the stored value, e.g. for the backend scope and option
     *   lists, which must load even when the value is unreadable. The control is always resolved.
     */
    public function create(FilterModel $model, bool $withValue = true): Filter
    {
        $source = "{$model::getTable()}.{$model->id}";
        $control = (string) $model->control ?: null;

        $filter = $this->filterFactory->create(
            element: $model->getFilterElementType(),
            alias: $model->getFilterAlias(),
            control: $control,
            targetAlias: $model->getFilterTargetAlias() ?: null,
            configSource: $model,
            source: $source,
        );

        if (!$withValue) {
            return $filter;
        }

        return $filter->withValue($control === null
            ? $this->provideValue($filter, $model)           // unreadable → throws
            : $this->provideInitialValue($filter, $model));  // unreadable → warning, null
    }
}
```

- `provideValue()` / `provideInitialValue()` move over unchanged in semantics (provider lookup by
  `$filter->element->valueClass`, `FlareException` if no provider is registered, a provider's `FilterException`
  passes, a non-`FilterException` `FlareException` passes unwrapped, any other `\Exception` is logged as an error and
  wrapped into a `FilterException`; the initial value of a controlled filter is dropped with a warning). The value
  provider receives `$model->value` and `$filter->element->component`, as today.
- A provider can never return a value of the wrong class: `provide()` must narrow its return type to one concrete
  value class (`RegisterValueProvidersPass` / `ValueClassReflector` reject anything else), PHP enforces that return
  type, and the registry is keyed by it. So `withValue()` outside the warning-and-null catch of
  `provideInitialValue()` cannot change today's semantics; no extra handling is needed there.
- The failure contract in the docblock of today's `createFromFilterModel()` moves with it (minus the legacy-alias
  sentence).
- The method name is the builder's call (`create(FilterModel)` or `createFromFilterModel(FilterModel)`); keep it
  consistent with the Contao `ListSpecBuilderFactory`.

Consumers switch to the Contao factory: `FilterCollector` (ex `ListModelFilterCollector`), `BackendElementScopeFactory`,
`FilterInspector`. Consumers of the core `create()` (`Engine/Mod/SimpleEquationMod`, `Engine/Loader/ValidationLoader`,
`NewsListDriver`, `EventsListDriver`, `ChangelanguageListener`) are unaffected except for the new parameter
`$configSource` before `$source` — check that none passes `$source` positionally.

### Legacy aliases (user's decision)

Today `createFromFilterModel(withValue: false)` skips `assertFormName()` so the backend form can load to repair a
legacy alias. That exception is dropped: a controlled filter with an invalid alias cannot be created anywhere. The
editor has to rename the alias. Resulting behavior, which needs no extra code:

- frontend: the list fails (as today);
- backend inspector: the filter is reported as broken, with the reason;
- backend edit form: still loads — `BackendElementScopeFactory` catches the `FlareException` and returns `null`, so
  fields whose options depend on the element scope stay incomplete until the alias is renamed; the alias field itself
  stays editable.

Remove the "must load to repair a legacy alias" comment and adjust tests that assert the lenient behavior.

## List entry point

`ListSpecBuilderFactory::createFromListModel()` moves into its own class `src/Contao/Factory/ListSpecBuilderFactory.php`
(name clash with the core class is fine across namespaces; alias the import where both are used). The core
`List/Factory/ListSpecBuilderFactory` keeps only `create($driver, ?ListModel $model, ?string $source)` — unchanged,
since `ListSpecBuilder` keeps its model (non-goal above).

```php
namespace HeimrichHannot\FlareBundle\Contao\Factory;

final readonly class ListSpecBuilderFactory
{
    public function __construct(
        private CoreListSpecBuilderFactory $builderFactory,
        private FilterCollector            $filterCollector,
    ) {}

    /** @throws FlareException In case the list driver cannot be resolved. */
    public function create(ListModel $listModel): ListSpecBuilder
    {
        $builder = $this->builderFactory->create(
            driver: (string) $listModel->type,
            model: $listModel,
            source: $listModel::getTable() . '.' . $listModel->id,
        );
        // collect, addFilter(), addFailure() — exactly as createFromListModel() does today
    }
}
```

Consumers switch (all callers of `createFromListModel()` today): `ListViewController`, `ReaderController`,
`BreadcrumbHook`, `ElementDcaHook`, `Callback/FlareFilter/FieldsOptionsCallbacks` (two call sites).

`FilterCollector` (ex `ListModelFilterCollector`) moves unchanged in behavior, using the Contao `FilterFactory`.

## Model transformers in the abstract components

`CallbackFilterModelTransformer` and `CallbackListModelTransformer` are deleted. `TransformerMap::set()` already
accepts a callable and `TransformerMap::find()` only returns a transformer whose source class matches
(`$source instanceof $sourceClass`), so their own type check can never fire. In `AbstractFilterElement`,
`AbstractFilterControl` and `AbstractListDriver`, **keep the named arguments** (user's requirement):

```php
$transformers->set(
    sourceClass: FilterModel::class,
    transformer: $this->transformFilterModel(...),
);
```

(`ListModel::class` / `$this->transformListModel(...)` in `AbstractListDriver`.) The typed parameter of
`transformFilterModel(ConfigBuilder $config, FilterModel $model)` stays as the last guard.

## DCA builder (`Contao/DataContainer/Builder/`)

The namespace holds builders only. `DcaContext` moves to `Contao\DcaContext` (a Contao domain object handed to
components; not a service, so it belongs in the `Contao/` root; `Engine/Context/` is a different axis and would clash
with `ConfigurationContext`). Its content is unchanged.

The builder API given to components (`DcaContract::buildDca()`, `ValueProviderInterface::buildDcaField()`, the three
`*BuildDcaEvent`s) becomes collect-only, like `FilterFormBuilderInterface`:

| | Today | After |
|---|---|---|
| `DcaBuilderInterface` | `palette`, `getPalette`, `prefix`, `suffix`, `field`, `scope`, `selector`, `apply` | `palette`, `getPalette`, `prefix`, `suffix`, `field`, `selector` |
| `DcaFieldBuilderInterface` | … `applyTo` | `applyTo` removed |
| `DcaBuilder` (concrete, created by `ElementDcaHook` only) | + `composePrefix` | keeps `apply()`, `composePrefix()`; `scope('control')` becomes `forControl(): DcaBuilderInterface` |
| `DcaFieldBuilder` (concrete) | | keeps `applyTo()` (called by `DcaBuilder::apply()`; tests on the concrete class may keep using it) |
| `ControlScopeDcaBuilder` (`@internal`) | delegates `apply`, `scope` | renamed `ControlDcaBuilder`, `@internal`, implements the slimmer interface (no `apply`/`scope` delegation) |
| `FilterElementBuildDcaEvent`, `ListDriverBuildDcaEvent` | concrete `DcaBuilder $builder` | `DcaBuilderInterface $builder` |
| `FilterControlBuildDcaEvent` | `DcaBuilderInterface` | unchanged |

`DcaBuilder::field()` may keep its covariant `DcaFieldBuilder` return type internally; what matters is that the
interfaces carry no materialization. Check that no component, listener or integration calls `apply()`, `applyTo()`,
`scope()` or `composePrefix()` through the interface (as of today only `ElementDcaListener` and the value-provider
tests do).

## Tests

- Mirror the moves under `tests/`: `tests/DataContainer/**` → `tests/Contao/DataContainer/**` and
  `tests/Contao/Inspection/**` (including `Inspection/Stub/`), `tests/EventListener/Contao/*` → `tests/Contao/Hook/*`
  (renamed `*HookTest`), `tests/EventListener/DataContainer/**` → `tests/Contao/Callback/**`,
  `tests/List/ListModelFilterCollectorTest.php` → `tests/Contao/Factory/FilterCollectorTest.php`.
- Split `tests/Filter/FilterFactoryTest.php`: the model path (`createFromFilterModel`, 17 references) goes to
  `tests/Contao/Factory/FilterFactoryTest.php`; the core test gains cases for `$configSource` (transformer applied,
  explicit config wins, no source = unchanged) and keeps the alias tests.
- New: `Filter` value-class check (mismatch and value on an intrinsic element throw `ValueClassMismatchError`, null
  accepted, via `withValue()`); a mismatching value set by a `ListDriverBuildListEvent` / `FilterCollectedEvent`
  listener escapes the collector and `build()` as the `\Error` (no `FilterFailure`);
  legacy alias now fails in the Contao factory with `withValue: false` too; the Contao `ListSpecBuilderFactory`.
- Update the ~30 `new ElementBinding(...)` fixtures with the value class.
- Value-provider tests that call `applyTo()` keep working on the concrete `DcaFieldBuilder`.
- Green: `make test`, `make phpstan`, and Mago in CI (no make target; run it before committing if available —
  see project memory).

## Documentation

- `AGENTS.md`: update every moved path / class name (Architecture, notable subsystems, "Who throws what" mentions of
  `FilterFactory`, inspector paths, DCA builder, event list, key entry points); add the `Contao` namespace rule
  (DTOs in root, services in subfolders; hooks in `Contao/Hook`, callbacks in `Contao/Callback`; all model → domain
  processing in `Contao/`, with the ListSpec exception).
- Docusaurus (`docs/`, separate worktree on `docs/main`, committed separately): at least
  `docs/dev/dca-builder.md`, `docs/dev/contracts/dca-contract.md`, `docs/dev/filter-elements/index.md`,
  `docs/dev/filter-controls.md`, `docs/dev/list-types/index.md` reference the old namespaces /
  `CallbackFilterModelTransformer` / `ElementDcaListener`.

## Order (suggestion, builder may change)

1. Pure moves and renames (namespaces incl. `PtableInference`, hooks, callbacks, services.yaml, tests, contao/dca
   references) — no behavior
   change; green.
2. Delete the callback transformers.
3. DCA builder API tightening + `DcaContext` move.
4. `ElementBinding::$valueClass` + value check in `Filter`.
5. Core `create(configSource)` + Contao `FilterFactory` + collector + Contao `ListSpecBuilderFactory`; drop the
   legacy-alias leniency.
6. AGENTS.md; docs worktree.
