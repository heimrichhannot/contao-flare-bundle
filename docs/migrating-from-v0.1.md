---
title: Migrating from v0.1
sidebar_position: 10
---

# Migrating from v0.1

v0.2 rebuilds the filter subsystem around an **element-owned architecture**: a filter element now owns its
config schema, backend DCA, frontend form, and query translation — and delegates the actual SQL to reusable
[filter types](./dev/filter-types.md). The invoker and DCA-callback subsystems are gone.

**If you only configure lists and filters in the Contao backend, nothing changes for you.** DCA fields,
palettes, backend filter labels, templates, and the content elements are unchanged. This guide is for
developers who extended FLARE in PHP.

For the exhaustive list of deleted APIs, see [Removed in v0.2](./removed-in-v0.2.md).

## Namespaces & Classes

| v0.1 | v0.2 |
|---|---|
| `HeimrichHannot\FlareBundle\FilterElement\FooElement` | `HeimrichHannot\FlareBundle\Filter\Element\FooFilterElement` — all elements moved and renamed with a `FilterElement` suffix; the `::TYPE` strings (e.g. `flare_bool`) are unchanged |
| `Specification\FilterDefinition`, `Collection\FilterDefinitionCollection` | `Filter\Filter` — a single immutable value object; see [Filter Pipeline](./spec/filtering.md) |
| `FilterCollector\*` | `Filter\Collector\*`; `collect()` now returns `array<string\|int, Filter>\|null` |
| `Form\Type\DateRangeFilterType` (Symfony form type) | `Form\Type\DateRangeFormType` — renamed; note that `Filter\Type\DateRangeFilterType` now names a *query* filter type instead |
| `ListType\ListTypeInterface`, `ListType\AbstractListType` | `List\Driver\ListDriverInterface`, `List\Driver\AbstractListDriver` — "list types" are implemented by **list drivers**; the registered type aliases (e.g. `flare_news`) are unchanged |
| `ListType\NewsListType`, `ListType\GenericDataContainerListType`, ... | `List\Driver\NewsListDriver`, `List\Driver\GenericDataContainerListDriver`, ... — built-in drivers renamed accordingly (see [built-in list types](./reference/list-types.md)) |
| `Contract\ListType\*` | `Contract\ListDriver\*`; `DataContainerContract::getDataContainerName()` is now `resolveDataContainerTable()` — the old name lives on `ListDriverInterface` and resolves the runtime table from canonical config |

## Attributes

| v0.1 | v0.2 |
|---|---|
| `#[AsFilterElement(type:, formType:, palette:, method:, isTargeted:)]` | `#[AsFilterElement(type:, isTargeted:)]` — form type moves into `buildForm()`, palette into `buildDca()`, the invoked method is always `buildFilter()`; always-intrinsic elements implement `IntrinsicContract` |
| `#[AsListType(type:, dataContainer:, palette:)]` | `#[AsListDriver(type:, dataContainer:)]` — renamed; palette moves into `buildDca()`; `dataContainer` is only a backend default for new records |
| `#[AsFilterInvoker]` | Removed — branch on `$context->engineContext` inside `buildFilter()` |
| `#[AsFilterCallback]`, `#[AsListCallback]`, `#[AsFlareCallback]` | Removed — use [`buildDca()` / `ElementDcaEvent`](./dev/dca-builder.md) |

## Filter Element API

The `__invoke(FilterInvocation $invocation, FilterQueryBuilder $qb)` entry point is replaced by three
lifecycle methods (see the [custom filter elements guide](./dev/filter-elements/index.md)):

| v0.1 | v0.2 |
|---|---|
| `formType:` attribute parameter | `buildForm(FilterFormBuilderInterface $builder, FilterContext $context): void` — declare one field via `single()` or add children under local names |
| `__invoke(FilterInvocation, FilterQueryBuilder)` | `buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $values): void` — emit filter-type calls instead of writing SQL |
| SQL written directly in the element | A [`FilterTypeInterface`](./dev/filter-types.md) service; the element calls `$builder->add(MyFilterType::class, [...])` |
| `FilterInvocation->getValue()` | `$values` (submitted form data by local child name; `single()` fields under `FilterContext::SINGLE_VALUE`) and `$context->config` (resolved config) |
| `FilterQueryBuilder::abort()` in the element | `$builder->abort()` on the `FilterBuilderInterface` (filter types may still use `FilterQueryBuilder::abort()`) |

## Contracts

| v0.1 contract | v0.2 equivalent |
|---|---|
| `FormDataContract::extractFormData()` | Read `$values` in `buildFilter()` — it contains the filter's submitted data |
| `RuntimeValueContract::processRuntimeValue()` | Normalize values inside `buildFilter()` |
| `IntrinsicValueContract::getIntrinsicValue()` | Read `$context->config` in `buildFilter()` (intrinsic values are config) |
| `HydrateFormContract::hydrateForm()` | Pass defaults via the fields' `data` option in `buildForm()` |
| `FormTypeOptionsContract::handleFormTypeOptions()` | Build options directly in `buildForm()`; third parties use `FilterElementFormBuiltEvent` |
| `PaletteContract::getPalette()` | [`DcaContract::buildDca()`](./dev/contracts/dca-contract.md) |

New interfaces: `FilterElementInterface` (required), `OptionsContract` (config schema),
`TransformerContract` (source-to-config translation), `DcaContract` (backend DCA), and
`IntrinsicContract` (always-intrinsic marker). `AbstractFilterElement` implements all of them plus
`IsSupportedContract`.

## Events

| v0.1 | v0.2 |
|---|---|
| `FilterElementInvokingEvent` (`flare.filter_element.{type}.invoking`) | `FilterElementBuildingEvent` (`flare.filter_element.{type}.building`) — cancellable via `setShouldBuild(false)` |
| `FilterElementInvokedEvent` (`flare.filter_element.{type}.invoked`) | `FilterElementBuiltEvent` (`flare.filter_element.{type}.built`) |
| `FilterFormChildOptionsEvent` (`flare.form.{parent}.child.{name}.options`) | Removed — customize in `buildForm()`, or listen to `FilterElementFormBuiltEvent` (`flare.filter_element.{type}.form_built`) |
| `FilterElementFormTypeOptionsEvent` | Removed — same replacement as above |
| `PaletteEvent` (`flare.filter_element.{alias}.palette`, `flare.list.{type}.palette`) | `ElementDcaEvent` (`flare.filter_element.{type}.dca`, `flare.list.{type}.dca`) |
| `FilterDefinitionCreatedEvent` | `FilterCollectedEvent` — dispatched per filter collected from the database; `$event->filter` is replaceable |

`flare.form.{formName}.build` (`FilterFormBuildEvent`) is unchanged.

## ListSpecification → ListSpec

`ListSpecification` is replaced by the immutable `List\ListSpec`, built by the `List\ListSpecBuilder`
(see [List Specs & Filters](./spec/specifications.md)); programmatic construction goes through the
`ListSpecFactory` service, which accepts a driver instance or a registered type alias and resolves the
list's data container definitively. The spec carries the driver instance as `$spec->driver`; the main
table is `$spec->dc`. Filters are a keyed `array<string, Filter>`
instead of a `FilterDefinitionCollection`. While a list is being built (a driver's `buildList()` hook
or a `ListBuildEvent` listener), use the mutable builder; on a finished spec, use the withers:

| v0.1 | v0.2 |
|---|---|
| `$spec->getFilters()->add($definition)` | `$builder->addFilter($filter)` while building; `$spec = $spec->withFilter($filter)` afterwards |
| `$spec->getFilters()->set('name', $definition)` | `$builder->addFilter($filter, 'name')` / `$spec->withFilter($filter, 'name')` — an existing key is replaced |
| `$spec->getFilters()->hasType('flare_bool')` | `hasFilterOfType('flare_bool')` on builder or spec |
| — | `$builder->getFilter('name')`, `$builder->removeFilter('name')`, `$spec->withoutFilter('name')` |
| `$definition->forceTargetAlias('alias')` | `$filter->withTargetAlias('alias')` — **returns a new instance** (`Filter` is immutable) |

Element `define()` factories (e.g. `PublishedFilterElement::define()`,
`SimpleEquationFilterElement::define(...)`) were removed: construct a
[`Filter`](./dev/filter-elements/index.md#9-inline-filters-without-a-service) directly with the element's
type alias and canonical config, e.g.
`new Filter(element: PublishedFilterElement::TYPE, config: ['intrinsic' => true, ...])`.

## Before / After

A minimal v0.1 element and its v0.2 equivalent:

```php
// v0.1
#[AsFilterElement(type: 'app_city', formType: TextType::class, palette: '{filter_legend},fieldGeneric')]
class CityElement extends AbstractFilterElement
{
    public function __invoke(FilterInvocation $invocation, FilterQueryBuilder $qb): void
    {
        if (!$value = $invocation->getValue()) {
            return;
        }

        $qb->where($qb->column('city') . ' = :city', ['city' => $value]);
    }
}
```

```php
// v0.2
#[AsFilterElement(type: 'app_city', isTargeted: true)]
class CityFilterElement extends AbstractFilterElement
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('field')->default('city')->allowedTypes('string');
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
    {
        $config->set('field', $model->fieldGeneric ?: 'city');
    }

    public function buildDca(DcaBuilder $dca, DcaContext $context): void
    {
        $dca->palette('{filter_legend},fieldGeneric');
    }

    public function buildForm(FilterFormBuilderInterface $builder, FilterContext $context): void
    {
        $builder->single(TextType::class, ['required' => false]);
    }

    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $values): void
    {
        if (!$value = $values[FilterContext::SINGLE_VALUE] ?? null) {
            return;
        }

        $builder->add(SimpleEquationFilterType::class, [
            'operand_left' => $context->config['field'],
            'operator' => SqlEquationOperator::EQUALS,
            'operand_right' => (string) $value,
        ]);
    }
}
```
