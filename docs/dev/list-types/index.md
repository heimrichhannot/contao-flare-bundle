# Custom List Types

List Types define the source of data for a list and how the base query should be structured.

## 1. Registration (`#[AsListType]`)

To create a custom list type, annotate your class with the `#[AsListType]` attribute. Your class should ideally extend `AbstractListType` to inherit default behavior.

```php
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;
use HeimrichHannot\FlareBundle\List\Type\AbstractListType;

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

:::info[Changed in v0.2]

The `palette` parameter was removed — backend palettes are now built in
[`buildDca()`](#3-backend-configuration-builddca).

:::

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
    $select = $struct->getSelect() ?? [];
    $select[] = 'author.username AS author_name';
    $struct->setSelect($select);
}
```

## 3. Backend Configuration (`buildDca`)

If your list type requires specific configuration fields in the Contao backend, implement
`Contract\DcaContract` and declare the palette — the legends and fields to display — in `buildDca()`:

```php
use HeimrichHannot\FlareBundle\Contract\DcaContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;

class MyCustomListType extends AbstractListType implements DcaContract
{
    public function buildDca(DcaBuilder $dca, DcaContext $context): void
    {
        $dca->palette('{my_legend},myCustomField,anotherField');
    }
}
```

The referenced fields must be defined in the `tl_flare_list` DCA (e.g., in your extension's
`contao/dca/tl_flare_list.php`). See the [Backend DCA Building guide](../dca-builder.md) for the full
`DcaBuilder` API, including per-field tweaks and palette prefixes/suffixes.
