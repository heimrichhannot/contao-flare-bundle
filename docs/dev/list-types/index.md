# Custom List Types

List Types define the source of data for a list and how the base query should be structured.

## 1. Registration (`#[AsListType]`)

To create a custom list type, annotate your class with the `#[AsListType]` attribute. Your class should ideally extend `AbstractListType` to inherit default behavior.

```php
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListDriver;
use HeimrichHannot\FlareBundle\List\Type\AbstractListDriver;

#[AsListDriver(
    type: 'my_custom_list',
    dataContainer: 'tl_my_table'
)]
class MyCustomListType extends AbstractListDriver
{
    // ...
}
```

### Attribute Parameters:
- **`type`**: Unique identifier for the list type.
- **`dataContainer`**: The main database table (e.g., `tl_news`).

:::info[Changed in v0.2]

The `palette` parameter was removed — backend palettes are now built in
[`buildDca()`](#5-backend-configuration-builddca).

:::

## 2. Config Schema (`configureOptions` + `configureTransformers`)

A list's canonical config is resolved against two schemas: the framework-owned **base schema**
(`HeimrichHannot\FlareBundle\List\BaseListOptions`, covering the `tl_flare_list` columns — title,
jump-to pages, sorting, parent-table settings, meta formats, ...) and the **type schema** your list
type declares on top in `configureOptions()`:

```php
use Symfony\Component\OptionsResolver\OptionsResolver;

public function configureOptions(OptionsResolver $resolver): void
{
    $resolver->define('archives')->default([])->allowedTypes('int[]');
}
```

`configureTransformers()` declares how stored sources translate onto that schema. `AbstractListType`
already registers a transformer for `ListModel` (a stored `tl_flare_list` record) that delegates to the
protected `transformListModel()` hook — the base columns are translated by `BaseListOptions` beforehand,
so the hook only maps the type's own fields. All deserialization, casting, and enum parsing belongs
there:

```php
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Model\ListModel;

protected function transformListModel(ConfigBuilder $config, ListModel $model): void
{
    $config->set('archives', \array_map('\intval', StringUtil::deserialize($model->archives, true)));
}
```

Both methods are declarative, memoizable setup — they run once per type class, not per list.
Downstream, the resolved config is available as `ListSpec::$config`.

## 3. Build Lifecycle (`buildList`)

A list is built by the `ListBuilder` into an immutable
[`ListSpec`](../../spec/specifications.md). List types that implement
`Contract\ListType\BuildListContract` take part in that lifecycle: `buildList()` runs first and may add
filters or set config overrides. Afterwards, the [`ListBuildEvent`](../events.md)
(`flare.list.{type}.build`) gives third parties the same access, then the config is assembled and
resolved.

The built-in news list type uses this to guarantee a published-state filter:

```php
use HeimrichHannot\FlareBundle\Contract\ListType\BuildListContract;
use HeimrichHannot\FlareBundle\Filter\Element\PublishedFilterElement;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\List\ListSpecBuilder;

class MyCustomListType extends AbstractListType implements BuildListContract
{
    public function buildList(ListSpecBuilder $builder): void
    {
        if ($builder->hasFilterOfType(PublishedFilterElement::TYPE)) {
            return;
        }

        $builder->addFilter(new Filter(
            type: PublishedFilterElement::TYPE,
            config: [
                'intrinsic' => true,
                'published_field' => 'published',
                'start_field' => 'start',
                'stop_field' => 'stop',
                'invert' => false,
            ],
        ));
    }
}
```

Besides `addFilter()`/`removeFilter()`, the builder exposes `set(key, value)` for canonical config
overrides (they win over the base translation and the type's transformers) and read access to the
stored model (`getModel()`), the data container (`getDc()`), and the already-added filters.

## 4. Building the Query (`BuildQueryContract`)

To customize the SQL query, implement `Contract\ListType\BuildQueryContract` (included in
`AbstractListType` with no-op defaults).

### `buildTableRegistry`
Use this method to register joins.

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
        joinAlias: 'author',
        condition: $registry->makeJoinOn('author', 'id', TableAliasRegistry::ALIAS_MAIN, 'author')
    ));
}
```

### `buildBaseQuery`
Use this method to define base `WHERE` conditions, `SELECT` fields, or `ORDER BY` defaults.

```php
use HeimrichHannot\FlareBundle\Query\SqlQueryStruct;

public function buildBaseQuery(SqlQueryStruct $struct): void
{
    // Ensure only published items are shown by default
    $struct->setConditions('main.published = "1"');

    // Add custom select fields from the joined table
    $select = $struct->getSelect() ?? [];
    $select[] = 'author.username AS author_name';
    $struct->setSelect($select);
}
```

## 5. Backend Configuration (`buildDca`)

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
