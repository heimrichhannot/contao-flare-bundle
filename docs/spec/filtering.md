---
title: Filter Pipeline
sidebar_position: 2
---

# The Filter Pipeline

As of v0.2, FLARE's filtering follows an **element-owned architecture** built on two decoupled concepts:

- **Filter Elements** (`HeimrichHannot\FlareBundle\Filter\Element\`) own everything specific to one filter
  kind: its canonical config schema, its backend DCA representation, its frontend form controls, and the
  translation of config + submitted data into query fragments.
- **Filter Types** (`HeimrichHannot\FlareBundle\Filter\Type\`) are small, reusable, stateless query-fragment
  builders. They are the only components that write SQL conditions.

The pipeline, end to end:

```
tl_flare_filter rows ──(collector + configFromRow)──▶ Filter[] on the ListSpecification
                                                          │
                             form building: buildForm() per filter ──▶ Symfony filter form
                                                          │
              query building: buildFilter() per filter ──▶ FilterCall[] ──▶ FilterType::buildQuery()
                                                          │
                                                 FilterQueryBuilder[] ──▶ SQL conditions
```

## 1. The `Filter` Value Object

`HeimrichHannot\FlareBundle\Filter\Filter` is the immutable runtime representation of a single filter within
a list. It pairs an element (a registered type alias string, or an inline element instance) with its
canonical, element-defined configuration — no DCA or storage specifics.

Constructor properties:

| Property | Description |
|---|---|
| `element` | Registered element type alias (e.g. `flare_bool`) or an inline `FilterElementInterface` instance |
| `config` | Canonical config following the element's schema; scalars, arrays, and enums only |
| `data` | Optional runtime data bag, same shape `buildFilter()` receives; submitted form data takes precedence |
| `alias` | Form name of the filter; an invalid Symfony form name never mounts form children |
| `targetAlias` | Table alias the filter's conditions apply to |
| `targetingForced` | Whether the target alias applies even if the element is not marked as targeted |
| `source` | Provenance for error messages, e.g. `tl_flare_filter.42` |

Because `Filter` is immutable, all modification happens through withers that return a new instance:
`withConfig()`, `withData()`, `withAlias()`, `withTargetAlias(?string, bool $forced = true)`, `withSource()`.

Two static factories create filters without a database row:

```php
use HeimrichHannot\FlareBundle\Filter\Filter;

// Apply a single filter type with options — no registered element needed:
Filter::fromType(MinPriceFilterType::class, ['field' => 'price', 'min' => 10]);

// Full control via closures (query building + optional form building):
Filter::fromCallback(
    buildFilter: function (FilterBuilderInterface $builder, FilterContext $context, array $data): void { /* ... */ },
    buildForm: function (FormBuilderInterface $builder, FilterContext $context): void { /* ... */ },
);
```

Both are backed by the internal `CallbackFilterElement`. `Filter::fingerprint()` returns a stable
representation used by `ListSpecification::hash()` for caching and pagination identity.

## 2. Collection

Filter collectors turn a list's data source into filters. They implement
`Filter\Collector\FilterCollectorInterface` (auto-tagged `flare.filter_collector`):

```php
public function supports(ListDataSourceInterface $dataSource): bool;

/** @return array<string|int, Filter>|null */
public function collect(ListDataSourceInterface $dataSource): ?array;
```

The built-in `ListModelFilterCollector` reads the published `tl_flare_filter` rows of a list; each row is
translated into canonical config by its element's `configFromRow()`. For every collected filter, a
`FilterCollectedEvent` is dispatched before it is added to the specification — listeners may replace the
(mutable) `$event->filter`, e.g. to change its config or target alias.

## 3. Form Building

For each non-intrinsic filter whose alias is a valid Symfony form name, the `FilterFormFactory` creates a
compound child form (named after the filter's alias) and hands its builder to the element's `buildForm()`.
The element adds its own children under local names — by convention `FilterContext::FIELD_VALUE` (`'v'`)
for single-control elements.

The per-filter builder carries the `FilterContext` in its attribute bag under
`FilterContext::FORM_ATTRIBUTE`. After the element built its children, a `FilterElementFormBuiltEvent` is
dispatched — listeners can add, remove, or replace children, or `cancel()` the child entirely. Children
without any fields are not mounted onto the root form.

## 4. Query Building

When a projector executes the list query, the `FilterExecutor` processes each filter on the specification:

1. The filter's `config` is resolved against the element's `configureOptions()` schema
   (via `FilterOptionsResolver`; elements without `FilterElementOptionsInterface` get their config verbatim).
2. A `FilterElementBuildingEvent` is dispatched — listeners may inspect the `FilterContext` and skip the
   filter via `setShouldBuild(false)`.
3. The element's `buildFilter()` runs. It translates config + data into one or more **filter-type calls**
   on the `FilterBuilder`:

   ```php
   $builder->add(BooleanFilterType::class, ['field' => 'featured', 'value' => true]);
   ```

   Each call's options are resolved against the filter type's own `configureOptions()` schema and recorded
   as a `FilterCall` (type, resolved options, target alias).
4. A `FilterElementBuiltEvent` is dispatched.
5. Each `FilterCall` is executed: the filter type's `buildQuery(FilterQueryBuilder $builder, array $options)`
   writes parameterized conditions into a `FilterQueryBuilder` scoped to the call's target alias.

Calling `$builder->abort()` (which throws `AbortFilteringException`) anywhere in the pipeline short-circuits
the list to an empty result set — useful when a required value is missing or invalid.

## 5. Target Aliases

Every filter-type call runs against a table alias — by default `main`, the list's primary table (see
[SQL Query Struct & Aliasing](./query-struct.md)). Elements registered with
`#[AsFilterElement(isTargeted: true)]` respect the target alias configured on the filter record; a
programmatic filter can force one with `Filter::withTargetAlias('my_alias')`. In the backend,
`DcaContext::getTargetTable()` resolves the same aliasing so DCA option lookups match the queried table.
