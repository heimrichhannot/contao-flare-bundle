---
title: Removed in v0.2
sidebar_position: 11
---

# Removed in v0.2

Exhaustive list of APIs deleted in v0.2. For how to replace them, see
[Migrating from v0.1](./migrating-from-v0.1.md).

## Attributes

- `#[AsFilterInvoker]` (was deprecated in v0.1)
- `#[AsFilterCallback]`
- `#[AsListCallback]`
- `#[AsFlareCallback]` (internal base attribute)
- `#[AsListType]` — renamed to `#[AsListDriver]` (see [behavioral notes](#behavioral-notes))
- Parameters removed from kept attributes: `#[AsFilterElement]` lost `palette`, `formType`, and `method`
  (it gained `value:`, the element's value class);
  `#[AsListType]` lost `palette` on its way to becoming `#[AsListDriver]`

## Classes

- `Specification\FilterDefinition`
- `Specification\Factory\FilterDefinitionFactory`
- `Specification\DataSource\FilterDataSourceInterface`
- `Collection\FilterDefinitionCollection` and `Collection\AbstractCollection` (the whole `src/Collection/` namespace)
- `Filter\FilterInvocation`
- `Filter\FilterInvokerInterface`, `Filter\ServiceMethodFilterInvoker`
- `Filter\Resolver\FilterInvokerResolver`, `Filter\Resolver\FilterValueResolver`
- `Registry\FilterInvokerRegistry`
- `Manager\FlareCallbackManager`
- `Registry\FlareCallbackRegistry`, `Registry\Descriptor\FlareCallbackDescriptor`
- `DataContainer\FlareCallbackContainerInterface`
- `Contract\Config\PaletteConfig`, `Contract\Config\ListItemProviderConfig`
- `Util\MethodInjector`
- `Filter\FilterData` and `Filter::$data` / `Filter::withData()` — programmatic runtime values are `Filter::$value`
- `Filter\FilterExecutor` and `Filter\FilterBuilder` — replaced by the `ListQueryDirector`,
  `FilterContextBuilderInterface` and `FormulaBuilder`
- `Filter\FilterScope` — replaced by the role-typed `Filter\ElementScope` (element hooks and ports) and
  `Filter\ControlScope` (control hooks); `$scope->get('key')` reads the calling component's own config, and
  `ControlScope::forElement()` hands element ports their scope. `FilterScope::ATTR_SELF` / `ATTR_SINGLE_FIELD`
  moved to `ControlScope`
- `FilterContextBuilder::$scope` and `FilterContext::$scope` — the element receives the scope as a
  `buildContext()` parameter; `FilterContextBuilder::build()` is internal, elements see only
  `FilterContextBuilderInterface`
- `Filter::$type`, `Filter::$config`, `Filter::$controlConfig`, `Filter::$controlType` — a filter now holds an
  `ElementBinding` and an optional `ControlBinding`, each with `component`, `type` and a `ComponentConfig`:

  | Removed | Replacement |
  |---|---|
  | `$filter->element` (the element) | `$filter->element->component` |
  | `$filter->type` | `$filter->element->type` |
  | `$filter->config['key']` | `$filter->element->config->get('key')` — in a hook: `$scope->get('key')` |
  | `$filter->control` (the control) | `$filter->control?->component` |
  | `$filter->controlConfig['key']` | `$filter->control?->config->get('key')` — in a control hook: `$scope->get('key')` |
  | `$filter->controlType` | `$filter->control?->type` |
  | `Filter::withControl($control, $config, $type)` | `Filter::withControl(?ControlBinding)` |

- `DcaContext::getFilterScope()` — replaced by `DcaContext::getElementScope()`
- `Util\Fingerprint` — value objects express their identity through `ValueInterface::fingerprint()`
- `Filter\Value\ParentRefValue` (use `ChoiceValue` of `ContaoModelChoice` references)
- `ChoicesBuilder::setLabelForModel()`, `::getChoice()`, `::addGroup()`, `::removeGroup()` and the `@api`
  string/model forms of `::add()` — `add()` takes a `ChoiceObjectInterface` (`ContaoModelChoice`, `ValueChoice`)
- The `flare.choices_builder` attribute on mounted filter form fields
- `BoolBinaryChoices::NULL_FALSE` — migrated to `TRUE_FALSE`

## Contracts

- `Contract\PaletteContract`
- `Contract\FilterElement\FormDataContract`
- `Contract\FilterElement\FormTypeOptionsContract`
- `Contract\FilterElement\HydrateFormContract`
- `Contract\FilterElement\IntrinsicValueContract`
- `Contract\FilterElement\RuntimeValueContract`
- `Contract\FilterElement\IntrinsicContract` — an element without a value class is intrinsic; otherwise the
  filter record's `control` field is left blank
- `FilterElementInterface::buildForm()` and `::buildFilter()` — replaced by `buildContext()` on the element and
  `buildForm()` / `decode()` / `hydrate()` on a [filter control](./dev/filter-controls.md)
- Old hook signatures: `buildContext(FilterContextBuilder, ?ValueInterface)` is now
  `buildContext(FilterContextBuilderInterface, ElementScope, ?ValueInterface)`; controls take a `ControlScope`
  first in `decode(ControlScope, mixed)` and `hydrate(ControlScope, ?ValueInterface)`; `buildForm()`,
  `ChoiceSourceContract::buildChoices()` and `DateBoundsContract::getDateBounds()` take the role-typed scope

## Events

- `FilterElementInvokingEvent`
- `FilterElementInvokedEvent`
- `FilterElementFormTypeOptionsEvent`
- `FilterFormChildOptionsEvent`
- `PaletteEvent`
- `FilterDefinitionCreatedEvent`
- `FilterElementBuildingEvent` and `FilterElementBuiltEvent` (v0.2 development only) — and with them
  `FilterElementInvokingEvent` / `FilterElementInvokedEvent` have **no successor**: use `ListDriverBuildListEvent`
  (`flare.list_driver.{type}.build_list`), a programmatic `Filter::$value`, or a custom filter element

### Named event aliases

- `flare.filter_element.{type}.invoking`, `.invoked` (and the interim `.building`, `.built`) → removed without
  replacement
- `flare.filter_element.{alias}.palette` → `flare.filter_element.{type}.dca`; `flare.list.{type}.palette` →
  `flare.list_driver.{type}.dca`
- `flare.form.{parentFormName}.child.{formName}.options` → removed without a named replacement
  (use `flare.filter_control.{type}.build_form`)

## Database

- `tl_flare_filter` columns `intrinsic`, `boolMode`, `preselect`, `prefill` (field definitions removed; the
  columns and their data remain until deletes are run manually, as does the `legacyBackfilled` marker
  column) — replaced by `control` and `value`; see [Database & Backend](./migrating-from-v0.1.md#database--backend)
- All other `tl_flare_filter` columns keep their data under new names: `elm_*` / `ctl_*` prefixes and
  `formAlias` → `alias` (`contao:migrate`)

## Twig

- `flare_enclosure_files()` — deprecated since v0.1, removed in v0.2; a replacement based on the virtual
  file system is planned for a future release

## Behavioral notes

- Bool filters with a control no longer restrict reader pages: only fixed values and the elements' own config
  bound the whole list.
- A list with a broken filter element (with or without a control), a broken fixed value, or two published
  filters sharing an alias now renders `list.failed` (HTTP 200) instead of an empty or unfiltered result; its
  reader answers 500.
- `ListSpec::withFilter()` and `ListSpecBuilder::addFilter()` throw a `FlareException` on a duplicate alias
  (`addFilter()` also on a taken key) instead of replacing the filter.

- Filter element classes moved from `FilterElement\` to `Filter\Element\` and gained a `FilterElement`
  class-name suffix — a rename rather than a removal, but any `use` statement referencing the old FQCNs
  breaks. The backend `::TYPE` strings are unchanged, so **existing database records keep working**.
- List types are now implemented by **list drivers** — again a rename rather than a removal:
  `#[AsListType]` → `#[AsListDriver]`, `ListType\ListTypeInterface` → `List\Driver\ListDriverInterface`,
  `ListType\AbstractListType` → `List\Driver\AbstractListDriver`, `Contract\ListType\*` →
  `Contract\ListDriver\*`, and the built-in classes accordingly (e.g. `NewsListType` → `NewsListDriver`).
  The registered type aliases are unchanged, so **existing database records keep working**.
- `Contract\ListDriver\DataContainerContract::getDataContainerName()` was renamed to
  `resolveDataContainerTable()`; `getDataContainerName(array $config)` now lives on
  `ListDriverInterface` and resolves the list's runtime table from the canonical config.
- Palettes for `tl_flare_filter` / `tl_flare_list` are no longer declared statically or via callback
  attributes; they are assembled at runtime by each type's `buildDca()`
  (see [Backend DCA Building](./dev/dca-builder.md)).
