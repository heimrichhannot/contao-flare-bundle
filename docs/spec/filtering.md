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
tl_flare_filter rows ──(collector + transformers)──▶ Filter[] on the ListSpec
                                                          │
                             form building: buildForm() per filter ──▶ Symfony filter form
                                                          │
              query building: buildFilter() per filter ──▶ FilterCall[] ──▶ FilterType::buildQuery()
                                                          │
                                                 FilterQueryBuilder[] ──▶ SQL conditions
```

## 1. The `Filter` Value Object

`HeimrichHannot\FlareBundle\Filter\Filter` is the immutable runtime representation of a single filter within
a list. It pairs a filter element instance with its canonical, element-defined configuration — no DCA or
storage specifics.

Constructor properties:

| Property | Description |
|---|---|
| `element` | The `FilterElementInterface` instance — a registered element service or an inline instance |
| `type` | The registered element type alias (e.g. `flare_bool`), if the filter was created from one; used for the `flare.filter_element.{type}.*` named events and targeting lookups |
| `config` | Canonical config following the element's schema; scalars, arrays, and enums only |
| `data` | Optional runtime data bag, same shape `buildFilter()` receives; submitted form data takes precedence |
| `alias` | Form name of the filter; an invalid Symfony form name never mounts form children |
| `targetAlias` | Table alias the filter's conditions apply to |
| `targetingForced` | Whether the target alias applies even if the element is not marked as targeted |
| `source` | Provenance for error messages, e.g. `tl_flare_filter.42` |

Because `Filter` is immutable, all modification happens through withers that return a new instance:
`withConfig()`, `withData()`, `withAlias()`, `withTargetAlias(?string, bool $forced = true)`, `withSource()`.

Filters are created without a database row through the `FilterFactory` service — which resolves a
registered element type alias to its service — or by constructing a `Filter` directly around an inline
element instance; see
[Inline Filters](../dev/filter-elements/index.md#9-inline-filters-without-a-service).
`Filter::fingerprint()` returns a stable representation (including the element's class) used by
`ListSpec::hash()` for caching and pagination identity.

## 2. Collection

The `ListModelFilterCollector` (`Filter\Collector\`) reads the published `tl_flare_filter` rows of a
`tl_flare_list` record; each row is translated into canonical config by its element's transformers
(the `configureTransformers()` map, usually the `transformFilterModel()` hook — see
[Config Schema](../dev/filter-elements/index.md#3-config-schema-configureoptions--configuretransformers)).
For every collected filter, a `FilterCollectedEvent` is dispatched before it is added to the list —
listeners may replace the (mutable) `$event->filter`, e.g. to change its config or target alias.

## 3. Form Building

For each non-intrinsic filter whose alias is a valid Symfony form name, the `FilterFormFactory` hands a
collect-only per-filter builder (`FilterFormBuilderInterface`) to the element's `buildForm()`.
**Single-field elements** declare their one control via `single()` — it is mounted flat on the root form
under the filter's alias (`form[alias]=x`). **Multi-field elements** `add()` children under local names,
which mount as a compound sub-form (`form[alias][from]=x`).

The per-filter builder carries the `FilterContext` in its attribute bag under
`FilterContext::ATTR_SELF`. After the element built its fields, a `FilterElementFormBuiltEvent` is
dispatched — listeners can add, remove, or replace children, adjust the `single()` declaration, or
`cancel()` the filter's form entirely. Filters that declare no fields are not mounted onto the root form.

## 4. Query Building

When a projector executes the list query, the `FilterExecutor` processes each filter on the spec:

1. The filter's `config` is resolved against the element's `configureOptions()` schema
   (via `FilterOptionsResolver`; elements without `OptionsContract` get their config verbatim).
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
