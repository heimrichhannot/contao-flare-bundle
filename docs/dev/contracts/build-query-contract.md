# BuildQueryContract

The `BuildQueryContract` defines how a list driver registers joins and customizes its base SQL query structure.

**Interface:** `HeimrichHannot\FlareBundle\Contract\ListDriver\BuildQueryContract`

`AbstractListDriver` already implements this contract with no-op methods, so most list drivers only override the parts
they need.

## Lifecycle

Before your methods run, Flare creates the query context with:

- The list's main table (`ListSpec::$dc`, definitively resolved when the spec was created)
- `main` as the default table alias
- `SELECT main.*`
- `GROUP BY main.id`

Then Flare:

1. Calls `buildTableRegistry()`
2. Calls `buildBaseQuery()`
3. Dispatches `QueryBaseInitializedEvent`
4. Re-adds `main.id AS id` to the select list for internal processing

## Methods

### `buildTableRegistry(TableAliasRegistry $registry): void`

Use this method to register joins and additional aliases on the `TableAliasRegistry`.

```php
use HeimrichHannot\FlareBundle\Query\JoinTypeEnum;
use HeimrichHannot\FlareBundle\Query\SqlJoinStruct;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;

public function buildTableRegistry(TableAliasRegistry $registry): void
{
    $registry->registerJoin(new SqlJoinStruct(
        fromAlias: TableAliasRegistry::ALIAS_MAIN,
        joinType: JoinTypeEnum::LEFT,
        table: 'tl_member',
        joinAlias: 'member',
        condition: $registry->makeJoinOn('member', 'id', TableAliasRegistry::ALIAS_MAIN, 'author')
    ));
}
```

### `buildBaseQuery(SqlQueryStruct $struct): void`

Use this method to modify the pre-seeded `SqlQueryStruct`, for example by adding select fields, conditions, sorting, or
custom grouping.

```php
use HeimrichHannot\FlareBundle\Query\SqlQueryStruct;

public function buildBaseQuery(SqlQueryStruct $struct): void
{
    $select = $struct->getSelect() ?? [];
    $select[] = 'member.username AS author_name';
    $struct->setSelect($select);

    $struct->setConditions('main.published = "1"');
}
```
