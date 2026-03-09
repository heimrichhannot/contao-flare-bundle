---
title: Specifications
sidebar_position: 4
---

# Specifications & Filter Collections

## 1. Overview

Specifications are the "Source of Truth" in Flare. They are static, context-independent descriptions of what data should be fetched and how it should be filtered.

## 2. List Specification (`ListSpecification`)

The `ListSpecification` defines the base configuration for a data list.

**Class:** `HeimrichHannot\FlareBundle\Specification\ListSpecification`

### Key Properties:
- **`type`**: The machine name of the list type (e.g., `news`, `member`).
- **`dc`**: The Data Container (usually the database table name).
- **`dataSource`**: An optional `ListDataSourceInterface` (often wrapping a Contao Model).
- **`filters`**: A `FilterDefinitionCollection` containing all associated filters.

## 3. Filter Definition (`FilterDefinition`)

A `FilterDefinition` describes a single filter criteria.

**Class:** `HeimrichHannot\FlareBundle\Specification\FilterDefinition`

### Key Properties:
- **`type`**: The filter element type (e.g., `flare_bool`, `flare_select`).
- **`intrinsic`**: Boolean indicating if the filter is "hidden" (applied automatically without user interaction).
- **`alias`**: The unique identifier for this filter within the collection.
- **`targetAlias`**: The SQL table alias this filter should target (defaults to `main`).

## 4. Filter Definition Collection (`FilterDefinitionCollection`)

The `FilterDefinitionCollection` is an associative container for `FilterDefinition` objects.

### Indexing and Overriding
Filters in the collection are indexed by a unique key (string). This allows for powerful manipulation:

- **Adding Filters:** Use `add(FilterDefinition ...$item)` to add filters with auto-generated keys.
- **Overriding Filters:** Use `set(string $key, FilterDefinition $filter)` to replace a filter at a specific index. This is useful when you want a database-driven filter to be replaced by a more specific manual filter sharing the same name.
- **Retrieving Filters:** Use `get(string $key)` or `all()` to access the definitions.

### Filtering the Filters
You can check for specific filter types using `hasType(string $type)`.

## 5. Persistence & Hashing

Both `ListSpecification` and `FilterDefinition` implement a `hash()` method. This is used by the `Engine` and `Projector` to determine if the configuration has changed, which is crucial for caching and identifying unique list states (e.g., for pagination parameters).
