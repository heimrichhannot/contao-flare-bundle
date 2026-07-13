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

## Attributes

| v0.1 | v0.2 |
|---|---|
| `#[AsFilterElement(type:, formType:, palette:, method:, isTargeted:)]` | `#[AsFilterElement(type:, intrinsicOnly:, isTargeted:)]` — form type moves into `buildForm()`, palette into `buildDca()`, the invoked method is always `buildFilter()` |
| `#[AsListType(type:, dataContainer:, palette:)]` | `#[AsListType(type:, dataContainer:)]` — palette moves into `buildDca()` |
| `#[AsFilterInvoker]` | Removed — branch on `$context->engineContext` inside `buildFilter()` |
| `#[AsFilterCallback]`, `#[AsListCallback]`, `#[AsFlareCallback]` | Removed — use [`buildDca()` / `ElementDcaEvent`](./dev/dca-builder.md) |

## Filter Element API

The `__invoke(FilterInvocation $invocation, FilterQueryBuilder $qb)` entry point is replaced by three
lifecycle methods (see the [custom filter elements guide](./dev/filter-elements/index.md)):

| v0.1 | v0.2 |
|---|---|
| `formType:` attribute parameter | `buildForm(FormBuilderInterface $builder, FilterContext $context): void` — add form children under local names |
| `__invoke(FilterInvocation, FilterQueryBuilder)` | `buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $data): void` — emit filter-type calls instead of writing SQL |
| SQL written directly in the element | A [`FilterTypeInterface`](./dev/filter-types.md) service; the element calls `$builder->add(MyFilterType::class, [...])` |
| `FilterInvocation->getValue()` | `$data` (submitted form data by local child name) and `$context->config` (resolved config) |
| `FilterQueryBuilder::abort()` in the element | `$builder->abort()` on the `FilterBuilderInterface` (filter types may still use `FilterQueryBuilder::abort()`) |

## Contracts

| v0.1 contract | v0.2 equivalent |
|---|---|
| `FormDataContract::extractFormData()` | Read `$data` in `buildFilter()` — it contains the compound child's submitted data |
| `RuntimeValueContract::processRuntimeValue()` | Normalize values inside `buildFilter()` |
| `IntrinsicValueContract::getIntrinsicValue()` | Read `$context->config` in `buildFilter()` (intrinsic values are config) |
| `HydrateFormContract::hydrateForm()` | Pass defaults via the children's `data` option in `buildForm()` |
| `FormTypeOptionsContract::handleFormTypeOptions()` | Build options directly in `buildForm()`; third parties use `FilterElementFormBuiltEvent` |
| `PaletteContract::getPalette()` | [`DcaContract::buildDca()`](./dev/contracts/dca-contract.md) |

New interfaces: `FilterElementInterface` (required), `FilterElementOptionsInterface` (config schema),
`DcaContract` (backend DCA). `AbstractFilterElement` implements all of them plus `IsSupportedContract`.

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

## ListSpecification

Filters are now a keyed `array<string, Filter>` instead of a `FilterDefinitionCollection`:

| v0.1 | v0.2 |
|---|---|
| `$spec->getFilters()->add($definition)` | `$spec->addFilter($filter)` |
| `$spec->getFilters()->set('name', $definition)` | `$spec->addFilter($filter, 'name')` — an existing key is replaced |
| `$spec->getFilters()->hasType('flare_bool')` | `$spec->hasFilterOfType('flare_bool')` |
| — | `$spec->getFilter('name')`, `$spec->removeFilter('name')` |
| `$definition->forceTargetAlias('alias')` | `$filter->withTargetAlias('alias')` — **returns a new instance** (`Filter` is immutable) |

Element `define()` factories (e.g. `PublishedFilterElement::define()`,
`SimpleEquationFilterElement::define(...)`) now return `Filter` instead of `FilterDefinition`; their
signatures are unchanged.

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

    public function configFromRow(array $row): array
    {
        return ['field' => ($row['fieldGeneric'] ?? null) ?: 'city'];
    }

    public function buildDca(DcaBuilder $dca, DcaContext $context): void
    {
        $dca->palette('{filter_legend},fieldGeneric');
    }

    public function buildForm(FormBuilderInterface $builder, FilterContext $context): void
    {
        $builder->add(FilterContext::FIELD_VALUE, TextType::class, ['required' => false]);
    }

    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $data): void
    {
        if (!$value = $data[FilterContext::FIELD_VALUE] ?? null) {
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
