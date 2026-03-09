---
title: SQL Query Struct
sidebar_position: 3
---

# SQL Query Struct & Aliasing

## 1. Overview

Flare uses a structured approach to SQL query building to handle complex joins and avoid naming collisions. Instead of raw SQL strings, the system uses the `SqlQueryStruct` to represent a query's components (Select, From, Join, Where, etc.) and a `TableAliasRegistry` to manage table aliases.

## 2. The `SqlQueryStruct`

The `SqlQueryStruct` is a DTO that holds the individual parts of a SQL query. It is primarily used by the `Projector` and can be modified by events.

**Class:** `HeimrichHannot\FlareBundle\Query\SqlQueryStruct`

### Properties
- **`select`**: Array of columns to select (e.g., `['main.id', 'main.title']`).
- **`from`**: The main table name.
- **`fromAlias`**: The alias for the main table (usually `TableAliasRegistry::ALIAS_MAIN`).
- **`joins`**: Array of `SqlJoinStruct` objects.
- **`conditions`**: The WHERE clause string.
- **`groupBy` / `having` / `orderBy`**: Arrays for the respective SQL clauses.
- **`limit` / `offset`**: Integer values for pagination.
- **`params`**: Array of query parameters.

## 3. Table Aliasing (`TableAliasRegistry`)

To prevent ambiguous column errors during joins, all tables in Flare MUST be aliased.

### `ALIAS_MAIN`
The primary table of any list is always aliased as `main`. This is defined in `TableAliasRegistry::ALIAS_MAIN`.

### Quoting Columns
When writing query parts (e.g., in a List Type or Filter Element), you should use the `column()` helper to ensure correct aliasing:

```php
// In a service using QueryHelperTrait
$qb->where($this->column('published'), '1');
// Results in: WHERE main.published = '1'
```

### Automatic Join Resolution
The `TableAliasRegistry` keeps track of all available joins. When a filter or list type "activates" an alias, the registry automatically resolves all required joins (including nested joins) to ensure the final SQL is valid.

## 4. `ModifyListQueryStructEvent`

This event is the primary hook for global query manipulation. It is dispatched after the base query is built and filters are invoked, but before the final SQL is executed.

**Event:** `HeimrichHannot\FlareBundle\Event\ModifyListQueryStructEvent`

### Use Cases:
- Adding global `WHERE` conditions (e.g., multi-tenant restrictions).
- Forcing specific `ORDER BY` clauses.
- Modifying `SELECT` fields for custom views.
- Activating additional joins based on external state.

```php
public function onModifyQuery(ModifyListQueryStructEvent $event): void
{
    $struct = $event->queryStruct;
    
    // Add a global condition
    $currentConditions = $struct->getConditions();
    $struct->setConditions($currentConditions . ' AND main.pid = :pid');
    
    $params = $struct->getParams();
    $params['pid'] = 123;
    $struct->setParams($params);
}
```

## 5. `SqlJoinStruct`

Used to define joins within the registry.

```php
new SqlJoinStruct(
    fromAlias: 'main',
    joinType: JoinTypeEnum::LEFT,
    table: 'tl_member',
    joinAlias: 'member',
    condition: 'main.author = member.id'
);
```
