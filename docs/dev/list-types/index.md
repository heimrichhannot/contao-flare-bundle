# Custom List Types

List Types define the source of data for a list and how the base query should be structured.

## 1. Registration (`#[AsListType]`)

To create a custom list type, annotate your class with the `#[AsListType]` attribute. Your class should ideally extend `AbstractListType` to inherit default behavior.

```php
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;
use HeimrichHannot\FlareBundle\ListType\AbstractListType;

#[AsListType(
    type: 'my_custom_list',
    dataContainer: 'tl_my_table'
)]
class MyCustomListType extends AbstractListType
{
    // ...
}
```

### Attribute Parameters:
- **`type`**: Unique identifier for the list type.
- **`dataContainer`**: The main database table (e.g., `tl_news`).
- **`palette`**: (Optional) Specific DCA palette to use.

## 2. Configuring the Query (`ConfigureQueryContract`)

To customize the SQL query, implement the `ConfigureQueryContract` (included in `AbstractListType`).

### `configureTableRegistry`
Use this method to register joins.

```php
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use HeimrichHannot\FlareBundle\Query\SqlJoinStruct;
use HeimrichHannot\FlareBundle\Query\JoinTypeEnum;

public function configureTableRegistry(TableAliasRegistry $registry): void
{
    $registry->registerJoin(new SqlJoinStruct(
        fromAlias: TableAliasRegistry::ALIAS_MAIN,
        joinType: JoinTypeEnum::LEFT,
        table: 'tl_member',
        joinAlias: 'author',
        condition: 'main.author = author.id'
    ));
}
```

### `configureBaseQuery`
Use this method to define base `WHERE` conditions, `SELECT` fields, or `ORDER BY` defaults.

```php
use HeimrichHannot\FlareBundle\Query\SqlQueryStruct;

public function configureBaseQuery(SqlQueryStruct $struct): void
{
    // Ensure only published items are shown by default
    $struct->setConditions('main.published = "1"');
    
    // Add custom select fields from the joined table
    $select = $struct->getSelect();
    $select[] = 'author.username AS author_name';
    $struct->setSelect($select);
}
```

## 3. Custom Palettes

If your list type requires specific configuration fields in the Contao backend, you can specify a palette.

```php
#[AsListType(
    type: 'my_custom_list',
    dataContainer: 'tl_my_table',
    palette: 'my_custom_palette'
)]
```

You can then define this palette in your `tl_flare_list.php` DCA file.
