# ConfigureQueryContract

The `ConfigureQueryContract` is the heart of a List Type. It defines how the SQL query is built and how table aliases are registered.

**Interface:** `HeimrichHannot\FlareBundle\Contract\ListType\ConfigureQueryContract`

## Methods

### `configureTableRegistry(TableAliasRegistry $registry): void`
Used to register joins and additional table aliases.

```php
public function configureTableRegistry(TableAliasRegistry $registry): void
{
    $registry->registerJoin(new SqlJoinStruct(
        fromAlias: TableAliasRegistry::ALIAS_MAIN,
        joinType: JoinTypeEnum::LEFT,
        table: 'tl_member',
        joinAlias: 'member',
        condition: 'main.author = member.id'
    ));
}
```

### `configureBaseQuery(SqlQueryStruct $struct): void`
Used to define the base SELECT, FROM, and initial WHERE conditions.

```php
public function configureBaseQuery(SqlQueryStruct $struct): void
{
    // Define initial WHERE conditions
    $struct->setConditions('main.published = "1"');
}
```
