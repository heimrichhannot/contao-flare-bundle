# Custom List Drivers

List drivers define the source of data for a list and how the base query should be structured. A driver
class is registered under a **type** alias — the value stored in `tl_flare_list.type` when the list is
configured in the backend.

## 1. Registration (`#[AsListDriver]`)

To create a custom list driver, annotate your class with the `#[AsListDriver]` attribute. Your class should ideally extend `AbstractListDriver` to inherit default behavior.

```php
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListDriver;
use HeimrichHannot\FlareBundle\List\Driver\AbstractListDriver;

#[AsListDriver(
    type: 'my_custom_list',
    dataContainer: 'tl_my_table'
)]
class MyCustomListDriver extends AbstractListDriver
{
    // ...
}
```

### Attribute Parameters:
- **`type`**: Unique identifier for the list type.
- **`dataContainer`**: (Optional) Backend default for the record's `dc` field — the main database table
  (e.g., `tl_news`). See [Data Container Resolution](#3-data-container-resolution-getdatacontainername).

The attribute is repeatable: a driver class annotated multiple times is registered once per type alias.

:::info[Changed in v0.2]

`#[AsListType]` was renamed to `#[AsListDriver]`, and `AbstractListType` to `AbstractListDriver`
(`HeimrichHannot\FlareBundle\List\Driver\`). The registered type aliases are unchanged, so existing
database records keep working. The `palette` parameter was removed — backend palettes are now built in
[`buildDca()`](#6-backend-configuration-builddca).

:::

## 2. Config Schema (`configureOptions` + `configureTransformers`)

A list's canonical config is resolved against two schemas: the framework-owned **base schema**
(`HeimrichHannot\FlareBundle\List\BaseListOptions`, covering the `tl_flare_list` columns — data
container (`dc`), title, jump-to pages, sorting, parent-table settings, meta formats, ...) and the
**driver schema** your driver declares on top in `configureOptions()`:

```php
use Symfony\Component\OptionsResolver\OptionsResolver;

public function configureOptions(OptionsResolver $resolver): void
{
    $resolver->define('archives')->default([])->allowedTypes('int[]');
}
```

`configureTransformers()` declares how stored sources translate onto that schema. `AbstractListDriver`
already registers a transformer for `ListModel` (a stored `tl_flare_list` record) that delegates to the
protected `transformListModel()` hook — the base columns are translated by `BaseListOptions` beforehand,
so the hook only maps the driver's own fields. All deserialization, casting, and enum parsing belongs
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

Both methods are declarative, memoizable setup — they run once per driver class, not per list.
Downstream, the resolved config is available as `ListSpec::$config`.

## 3. Data Container Resolution (`getDataContainerName`)

Every `ListSpec` has a definitive main table. When the spec is created, the driver's
`getDataContainerName(array $config): string` (declared on `ListDriverInterface`) is called with the
resolved canonical config; an empty return value aborts the spec creation with an exception.
`AbstractListDriver` returns `$config['dc']` — the record's stored `dc` column, translated by the base
schema — which is right for drivers whose table is user-selected.

A driver pinned to one table overrides it (and typically also implements the backend-side
[`DataContainerContract`](../contracts/data-container-contract.md), which keeps the stored `dc` column
in sync while the record is edited):

```php
public function getDataContainerName(array $config): string
{
    return 'tl_news';
}
```

The attribute's `dataContainer` parameter is **only** a backend default for new records — it plays no
part in runtime resolution.

## 4. Build Lifecycle (`buildList`)

A list is built by the `ListSpecBuilder` into an immutable
[`ListSpec`](../../spec/specifications.md). Drivers that implement
`Contract\ListDriver\BuildListContract` take part in that lifecycle: `buildList()` runs first and may add
filters or set config overrides. Afterwards, the [`ListBuildEvent`](../events.md)
(`flare.list.{type}.build`) gives third parties the same access, then the config is assembled and
resolved.

The built-in news list driver uses this to guarantee a published-state filter:

```php
use HeimrichHannot\FlareBundle\Filter\Element\PublishedFilterElement;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory;
use HeimrichHannot\FlareBundle\List\ListSpecBuilder;

class MyCustomListDriver extends AbstractListDriver
{
    public function __construct(
        private readonly FilterFactory $filterFactory,
    ) {}

    public function buildList(ListSpecBuilder $builder): void
    {
        if ($builder->hasFilterInstance(PublishedFilterElement::class)) {
            return;
        }

        $builder->addFilter($this->filterFactory->create(
            element: PublishedFilterElement::TYPE,
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
overrides (they win over the base translation and the driver's transformers) and read access to the
driver (`getDriver()`), the stored model (`getModel()`), the pre-resolution data container hint
(`getDc()`), and the already-added filters.

## 5. Building the Query (`BuildQueryContract`)

To customize the SQL query, implement `Contract\ListDriver\BuildQueryContract` (included in
`AbstractListDriver` with no-op defaults).

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

## 6. Backend Configuration (`buildDca`)

If your list driver requires specific configuration fields in the Contao backend, implement
`Contract\DcaContract` and declare the palette — the legends and fields to display — in `buildDca()`:

```php
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;

class MyCustomListDriver extends AbstractListDriver
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
