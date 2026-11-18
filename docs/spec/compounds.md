---
title: Compounds
sidebar_position: 4
---

# List & Filter Compounds

## 1. Overview

The `ListCompound` is the "Source of Truth" in Flare: the assembled, ready-to-run list — driver, filters and
canonical config — independent of the intent it is rendered for. It contains no DCA or storage specifics — translating a
stored `tl_flare_list` row into config is the job of the [build lifecycle](#3-building-a-list-listcompoundbuilder).

## 2. The `ListCompound`

**Class:** `HeimrichHannot\FlareBundle\List\ListCompound` (final readonly)

### Key Properties:
- **`driver`**: The list driver instance (`ListDriverInterface`) — a registered service (e.g. the
  `flare_news` driver) or an inline instance.
- **`dc`**: The main data container table of the list. It is definitively
  resolved at creation time — via the driver's `getDataContainerName(array $config)` — and baked into the
  canonical config (`config['dc']`); a list with an unresolvable table cannot be created.
- **`filters`**: A keyed map (`array<string, FilterCompound>`) of the list's filters.
- **`config`**: The canonical config, resolved through the base schema (`BaseListOptions`) and the
  driver's own schema.
- **`source`**: Optional provenance for error messages, e.g. `tl_flare_list.5`.

`ListCompound` is immutable. Deriving methods return new instances:

```php
public function withFilter(FilterCompound $filter, ?string $key = null): self;
public function withoutFilter(string $key): self;
public function withFilters(array $filters): self;
public function withConfig(array $config): self;
public function whole(): self;
public function forOutput(Output $output): self;
public function hasFilterInstance(string $class): bool;
```

- **Adding filters:** `withFilter($filter)` keys the filter by its alias, or auto-generates a key for
  alias-less filters.
- **Overriding filters:** `withFilter($filter, 'published')` replaces an existing `'published'` entry —
  this is how a manual filter overrides a database-driven filter sharing the same key (e.g. the default
  `published` filter that the News list driver adds).
- **Checking for element classes:** `hasFilterInstance(BooleanFilterElement::class)` checks whether any
  filter's element is an instance of the given class or interface — exclusive filters count too, on the list and on
  the builder.
- **Deriving the list of an output:** `whole()` returns the list without its
  [exclusive](../reference/filter-elements.md#apply-exclusively) filters — what the reader, the breadcrumb and the
  lookup run on; `forOutput(Output::ListView)` the list without the filters exclusive to other outputs — what the
  list view runs on, its count and form included (`HeimrichHannot\FlareBundle\List\Output`). Both keep config,
  source and failures (a list with failures fails in every derivation) and the filters' keys, numeric aliases
  included; when no filter is dropped, the list itself is returned. The duplicate-alias check covers all published
  filters.

## 3. Building a List (`ListCompoundBuilder`)

Lists are built by the `ListCompoundBuilder` (`HeimrichHannot\FlareBundle\List\ListCompoundBuilder`), created
through the `ListCompoundBuilderFactory` — content elements use `createFromListModel(ListModel $model)`.
`build()` runs the lifecycle:

1. The driver's `buildList()` hook (if it implements `BuildListContract`) adds filters or config
   overrides.
2. The [`ListDriverBuildListEvent`](../dev/events.md) (`flare.list_driver.{type}.build_list`) gives third parties the same
   access.
3. The config is assembled: the framework's `BaseListOptions` translates the stored model's base columns
   (including `dc`), the driver's transformers translate its own columns, and explicit `set()` overrides
   win over both.
4. The builder delegates to the `ListCompoundFactory`, which resolves the config against the base and driver
   schemas, definitively resolves the main data container via the driver's `getDataContainerName()`
   (throwing if it comes up empty), and freezes the result into the returned `ListCompound`.

While building, the mutable builder exposes `addFilter()`, `removeFilter()`, `set(key, value)`, and read
access to the driver, the stored model, and the filters added so far.

The `ListCompoundFactory` (`HeimrichHannot\FlareBundle\List\Factory\ListCompoundFactory`) is also the programmatic
construction path: `create($driver, $filters, $config, $source)` accepts a driver instance or a registered
type alias and is the only place `ListCompound` instances are made.

## 4. Filters (`FilterCompound`)

Each entry in the list's filter map is an immutable
`HeimrichHannot\FlareBundle\Filter\FilterCompound` value object pairing a filter element with its canonical config —
see the [Filter Pipeline](./filtering.md#1-the-filtercompound-value-object) for the full property reference.

Key points at the list level:

- **`element`**: The filter element instance — a registered service (created via `FilterCompoundFactory` from a
  type alias like `flare_bool`) or an inline instance.
- **`alias`**: The filter's form name.
- **`targetAlias`**: The SQL table alias the filter should target. Defaults to `null`, which resolves to
  `main` when the filter is executed. Use `withTargetAlias(string $alias)` to pin a filter to a specific
  alias — it returns a **new** instance, since `FilterCompound` is immutable.
- **`outputs`**: `null` for a filter that bounds the whole list, else the outputs it applies to exclusively
  (`withOutputs()`); see [`whole()` / `forOutput()`](#2-the-listcompound).
