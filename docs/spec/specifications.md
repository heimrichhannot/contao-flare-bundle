---
title: Specifications
sidebar_position: 4
---

# Specifications & Filters

## 1. Overview

Specifications are the "Source of Truth" in Flare. They are static, context-independent descriptions of what data should be fetched and how it should be filtered.

## 2. List Specification (`ListSpecification`)

The `ListSpecification` defines the base configuration for a data list.

**Class:** `HeimrichHannot\FlareBundle\Specification\ListSpecification`

### Key Properties:
- **`type`**: The machine name of the list type (e.g., `flare_news`, `flare_generic_dc`).
- **`dc`**: The Data Container (usually the database table name).
- **`dataSource`**: An optional `ListDataSourceInterface` (often wrapping a Contao Model).
- **filters**: A keyed map (`array<string, Filter>`) of the list's filters.

## 3. Filters (`Filter`)

Each entry in the specification's filter map is an immutable
`HeimrichHannot\FlareBundle\Filter\Filter` value object pairing a filter element with its canonical config —
see the [Filter Pipeline](./filtering.md#1-the-filter-value-object) for the full property and factory
reference.

Key points at the specification level:

- **`element`**: A registered element type alias (e.g. `flare_bool`) or an inline element instance.
- **`alias`**: The filter's form name.
- **`targetAlias`**: The SQL table alias the filter should target. Defaults to `null`, which resolves to
  `main` when the filter is executed. Use `withTargetAlias(string $alias)` to pin a filter to a specific
  alias — it returns a **new** instance, since `Filter` is immutable.

## 4. The Filter API

Filters are indexed by a unique string key, which allows for powerful manipulation:

```php
public function getFilters(): array;                                    // array<string, Filter>
public function getFilter(string $key): ?Filter;
public function addFilter(Filter $filter, ?string $key = null): static;
public function removeFilter(string $key): static;
public function hasFilterOfType(string $elementType): bool;
```

- **Adding filters:** `addFilter($filter)` keys the filter by its alias, or auto-generates a key for
  alias-less filters.
- **Overriding filters:** `addFilter($filter, 'published')` replaces an existing `'published'` entry — this
  is how a manual filter overrides a database-driven filter sharing the same key (e.g. the default
  `published` filter that the News list type adds).
- **Checking for element types:** `hasFilterOfType('flare_bool')` checks whether any filter uses the given
  element type.

## 5. Persistence & Hashing

`ListSpecification::hash()` produces a stable hash from the list's type, data container, and each filter's
`Filter::fingerprint()`. The `Engine` and `Projector` use it to determine if the configuration has changed,
which is crucial for caching and identifying unique list states (e.g., for pagination parameters).
