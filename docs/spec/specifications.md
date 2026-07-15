---
title: List Specs
sidebar_position: 4
---

# List Specs & Filters

## 1. Overview

The `ListSpec` is the "Source of Truth" in Flare: a static, context-independent description of what data
should be fetched and how it should be filtered. It contains no DCA or storage specifics — translating a
stored `tl_flare_list` row into config is the job of the [build lifecycle](#3-building-a-list-listbuilder).

## 2. The `ListSpec`

**Class:** `HeimrichHannot\FlareBundle\List\ListSpec` (final readonly)

### Key Properties:
- **`type`**: A registered list type alias (e.g., `flare_news`, `flare_generic_dc`) or an inline
  `ListTypeInterface` instance.
- **`dc`**: The main data container table of the list.
- **`filters`**: A keyed map (`array<string, Filter>`) of the list's filters.
- **`config`**: The canonical config, resolved through the base schema (`BaseListOptions`) and the type's
  own schema.
- **`source`**: Optional provenance for error messages, e.g. `tl_flare_list.5`.

`ListSpec` is immutable. Deriving methods return new instances:

```php
public function withFilter(Filter $filter, ?string $key = null): self;
public function withoutFilter(string $key): self;
public function withFilters(array $filters): self;
public function withConfig(array $config): self;
public function hasFilterOfType(string $elementType): bool;
```

- **Adding filters:** `withFilter($filter)` keys the filter by its alias, or auto-generates a key for
  alias-less filters.
- **Overriding filters:** `withFilter($filter, 'published')` replaces an existing `'published'` entry —
  this is how a manual filter overrides a database-driven filter sharing the same key (e.g. the default
  `published` filter that the News list type adds).
- **Checking for element types:** `hasFilterOfType('flare_bool')` checks whether any filter uses the given
  element type.

## 3. Building a List (`ListBuilder`)

Specs are built by the `ListBuilder` (`HeimrichHannot\FlareBundle\List\ListBuilder`), created through the
`ListBuilderFactory` — content elements use `createFromListModel(ListModel $model)`. `build()` runs the
lifecycle:

1. The list type's `buildList()` hook (if it implements `BuildListContract`) adds filters or config
   overrides.
2. The [`ListBuildEvent`](../dev/events.md) (`flare.list.{type}.build`) gives third parties the same
   access.
3. The config is assembled: the framework's `BaseListOptions` translates the stored model's base columns,
   the type's transformers translate its own columns, and explicit `set()` overrides win over both.
4. The result is resolved against the base and type schemas and frozen into the returned `ListSpec`.

While building, the mutable builder exposes `addFilter()`, `removeFilter()`, `set(key, value)`, and read
access to the stored model and the filters added so far.

## 4. Filters (`Filter`)

Each entry in the spec's filter map is an immutable
`HeimrichHannot\FlareBundle\Filter\Filter` value object pairing a filter element with its canonical config —
see the [Filter Pipeline](./filtering.md#1-the-filter-value-object) for the full property reference.

Key points at the spec level:

- **`element`**: A registered element type alias (e.g. `flare_bool`) or an inline element instance.
- **`alias`**: The filter's form name.
- **`targetAlias`**: The SQL table alias the filter should target. Defaults to `null`, which resolves to
  `main` when the filter is executed. Use `withTargetAlias(string $alias)` to pin a filter to a specific
  alias — it returns a **new** instance, since `Filter` is immutable.

## 5. Persistence & Hashing

`ListSpec::hash()` produces a stable hash from the list's type, data container, source, config, and each
filter's `Filter::fingerprint()`. The `Engine` and `Projector` use it to determine if the configuration has
changed, which is crucial for caching and identifying unique list states (e.g., for pagination parameters).
