# Custom Filter Elements

A filter element owns everything specific to one kind of filter:

- its **config schema** — `configureOptions()` + `configureTransformers()`
- its **backend DCA** representation — `buildDca()`
- its **frontend form** controls — `buildForm()`
- the **translation** of config and submitted data into query fragments — `buildFilter()`

Elements never write SQL themselves — that is the job of reusable [filter types](../filter-types.md), which
elements invoke from `buildFilter()`. For the filter elements that ship with FLARE, see the
[built-in filter elements reference](../../reference/filter-elements.md).

## 1. Registration (`#[AsFilterElement]`)

Annotate your class with `#[AsFilterElement]` and extend `AbstractFilterElement`:

```php
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\Element\AbstractFilterElement;

#[AsFilterElement(type: 'app_city', isTargeted: true)]
class CityFilterElement extends AbstractFilterElement
{
    // ...
}
```

### Attribute Parameters:
- **`type`**: Unique identifier for the filter type — this is the value stored in `tl_flare_filter.type`.
- **`isTargeted`**: (Optional) Boolean. If true, the filter respects the table alias configured on the
  filter record (see [target aliases](../../spec/filtering.md#5-target-aliases)).

:::info[Changed in v0.2]

The `palette`, `formType`, and `method` parameters were removed: the palette is now built in
[`buildDca()`](#4-backend-dca-builddca), form controls in [`buildForm()`](#5-form-building-buildform), and
the query translation always happens in [`buildFilter()`](#6-query-translation-buildfilter). Elements that
are always intrinsic implement [`IntrinsicContract`](#8-intrinsic-filters).

:::

## 2. The Interfaces

Every element implements `FilterElementInterface`:

```php
public function buildForm(FilterFormBuilderInterface $builder, FilterContext $context): void;

public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $values): void;
```

Elements with configuration additionally implement [`OptionsContract`](../contracts/index.mdx) and
[`TransformerContract`](../contracts/index.mdx):

```php
public function configureOptions(OptionsResolver $resolver): void;

public function configureTransformers(TransformerResolver $resolver): void;
```

`AbstractFilterElement` implements all of the above, plus
[`IsSupportedContract`](../contracts/is-supported-contract.md) (returns `true`),
[`DcaContract`](../contracts/dca-contract.md) (no-op), and `IntrinsicContract`
(`isOnlyIntrinsic()` returns `false`). Only `configureOptions()` and the protected
`transformFilterModel()` hook are abstract; `buildDca()`, `buildForm()`, and `buildFilter()` default
to no-ops.

### The `FilterContext`

Both `buildForm()` and `buildFilter()` receive a `FilterContext`
(`HeimrichHannot\FlareBundle\Filter\FilterContext`, final readonly):

| Member | Description |
|---|---|
| `$context->list` | The [`ListSpec`](../../spec/specifications.md) the filter belongs to |
| `$context->filter` | The [`Filter` value object](../../spec/filtering.md#1-the-filter-value-object) |
| `$context->config` | The **resolved** canonical config (validated against `configureOptions()`) |
| `$context->engineContext` | The engine context (`ContextInterface`) — interactive, validation, ... |
| `$context->key` | Key of the filter within `ListSpec::$filters` |
| `FilterContext::SINGLE_VALUE` | Constant `'0'` — canonical `$values` key under which a `single()` field's value reaches `buildFilter()` |
| `FilterContext::ATTR_SELF` | Attribute-bag key under which the context is stored on the per-filter form builder |

## 3. Config Schema (`configureOptions` + `configureTransformers`)

`configureOptions()` declares the element's canonical config with Symfony's `OptionsResolver`.
`configureTransformers()` declares how stored sources translate onto that schema: it registers one
transformer per source class on a `TransformerResolver`. Everything downstream — `buildDca()`,
`buildForm()`, `buildFilter()` — works with the resolved config, never with raw DCA rows, which is
what makes filters equally usable from the database, PHP, and Twig.

Both methods are declarative, memoizable setup: they are run once per element class, not per filter.

`AbstractFilterElement` already registers a transformer for `FilterModel` (a stored `tl_flare_filter`
record) that delegates to the abstract `transformFilterModel()` hook — for most elements, that hook is
all you implement. All deserialization, casting, and enum parsing belongs there:

```php
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Model\FilterModel;

public function configureOptions(OptionsResolver $resolver): void
{
    $resolver->define('field')->default('city')->allowedTypes('string');
    $resolver->define('label')->default(null)->allowedTypes('string', 'null');
}

protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
{
    $config
        ->set('field', $model->fieldGeneric ?: 'city')
        ->set('label', $model->label ?: null);
}
```

Third parties can register transformers for additional source classes — or replace the `FilterModel`
one — by listening to the [`FilterTransformerEvent`](../events.md)
(`flare.filter_element.{type}.transformers`).

## 4. Backend DCA (`buildDca`)

Declare the element's palette and field tweaks for `tl_flare_filter`:

```php
public function buildDca(DcaBuilder $dca, DcaContext $context): void
{
    $dca->palette('{filter_legend},fieldGeneric,label');
}
```

See the [Backend DCA Building guide](../dca-builder.md) for the full API, including per-field option
callbacks and reacting to the record's state.

## 5. Form Building (`buildForm`)

`buildForm()` receives a Flare-owned `FilterFormBuilderInterface`. **Single-field elements** declare their
one control via `single()` — the field is then mounted flat on the root form under the filter's form name,
so the query parameter reads `form[alias]=x`:

```php
use Symfony\Component\Form\Extension\Core\Type\TextType;

public function buildForm(FilterFormBuilderInterface $builder, FilterContext $context): void
{
    if ($context->config['intrinsic'] ?? false) {
        return; // intrinsic filters render no controls
    }

    $builder->single(TextType::class, [
        'label' => $context->config['label'] ?? false,
        'required' => false,
    ]);
}
```

**Multi-field elements** `add()` children under local names instead (e.g. `'from'`/`'to'`); they mount as
a compound sub-form named after the filter's form name (`form[alias][from]=x`). Declare no fields and the
filter simply has no form representation.

Pre-submission defaults go into the fields' native `data` option. Event listeners registered on the builder
are replayed onto the mounted form; event subscribers are not supported and throw. After your element
declared its fields, a [`FilterElementFormBuiltEvent`](../events.md) is dispatched so third parties can
adjust or cancel the filter's form — adding a child alongside a `single()` declaration switches the filter
to the compound layout, with the single field materialized under `FilterContext::SINGLE_VALUE`.

## 6. Query Translation (`buildFilter`)

`buildFilter()` turns config and data into one or more **filter-type calls** — it does not write SQL:

- `$context->config` is the resolved canonical config.
- `$values` holds the submitted form data, keyed by the local field names from `buildForm()` —
  `single()` fields always arrive under `FilterContext::SINGLE_VALUE`, regardless of how they
  were mounted (or the filter's programmatic data bag; an empty array otherwise).
- `$builder->add(SomeFilterType::class, $options, ?$targetAlias)` records a call; the options are validated
  against the filter type's own schema.
- `$builder->abort()` stops filtering entirely and yields an empty result set.

```php
public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $values): void
{
    if (!$value = $values[FilterContext::SINGLE_VALUE] ?? null) {
        return; // nothing submitted — this filter adds no conditions
    }

    $builder->add(SimpleEquationFilterType::class, [
        'operand_left' => $context->config['field'],
        'operator' => SqlEquationOperator::EQUALS,
        'operand_right' => (string) $value,
    ]);
}
```

## 7. Complete Example

```php
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Filter\Element\AbstractFilterElement;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\Type\SimpleEquationFilterType;
use HeimrichHannot\FlareBundle\Form\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AsFilterElement(type: 'app_city', isTargeted: true)]
class CityFilterElement extends AbstractFilterElement
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('field')->default('city')->allowedTypes('string');
        $resolver->define('label')->default(null)->allowedTypes('string', 'null');
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
    {
        $config
            ->set('field', $model->fieldGeneric ?: 'city')
            ->set('label', $model->label ?: null);
    }

    public function buildDca(DcaBuilder $dca, DcaContext $context): void
    {
        $dca->palette('{filter_legend},fieldGeneric,label');
    }

    public function buildForm(FilterFormBuilderInterface $builder, FilterContext $context): void
    {
        $builder->single(TextType::class, [
            'label' => $context->config['label'] ?? false,
            'required' => false,
        ]);
    }

    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $values): void
    {
        if (!$value = $values[FilterContext::SINGLE_VALUE] ?? null) {
            return;
        }

        $builder->add(SimpleEquationFilterType::class, [
            'operand_left' => $context->config['field'],
            'operator' => SqlEquationOperator::EQUALS,
            'operand_right' => (string) $value,
        ]);
    }
}
```

## 8. Intrinsic Filters

An **intrinsic** filter contributes conditions without user input — its values come from config, not from
a submitted form. Two flavors exist:

- **Optionally intrinsic** elements expose an `intrinsic` config option (backed by the
  `tl_flare_filter.intrinsic` checkbox) and skip their form controls when it is set — see the
  `buildForm()` example above. Most built-in elements work this way.
- **Always intrinsic** elements implement `IntrinsicContract`
  (`HeimrichHannot\FlareBundle\Contract\FilterElement\IntrinsicContract`) and return `true` from
  `isOnlyIntrinsic()`. They never render form controls, and the backend forces (and hides) the
  intrinsic flag on their filter records. Built-in examples: `flare_published`,
  `flare_belongsToRelation`, `flare_simpleEquation`.

`AbstractFilterElement` implements the contract with `isOnlyIntrinsic(): false`, so an always-intrinsic
element just overrides it:

```php
public function isOnlyIntrinsic(): bool
{
    return true;
}
```

## 9. Inline Filters Without a Service

`Filter` (`HeimrichHannot\FlareBundle\Filter\Filter`) is an immutable value object pairing a filter
element — a registered type alias or an inline instance — with its canonical config. For one-off filters
that don't warrant a registered element, construct one directly:

```php
use HeimrichHannot\FlareBundle\Filter\Element\BooleanFilterElement;
use HeimrichHannot\FlareBundle\Filter\Filter;

$filter = new Filter(
    element: BooleanFilterElement::TYPE,
    config: [
        'intrinsic' => true,
        'field' => 'featured',
        'preselect' => true,
    ],
);
```

The `config` array must satisfy the element's `configureOptions()` schema — no DCA row or transformer is
involved. An optional `data` bag supplies runtime values in the same shape `buildFilter()` receives
(single-field elements read `FilterContext::SINGLE_VALUE`); submitted form data takes precedence over it.
Use the `with*()` methods (`withConfig()`, `withData()`, `withAlias()`, `withTargetAlias()`, ...) to derive
variants — `Filter` is immutable, so they return new instances.

Add programmatic filters to a list while it is being built — from a list type's `buildList()` hook or a
[`ListBuildEvent`](../events.md) listener via `ListBuilder::addFilter()` — or derive a new spec from an
existing one via `ListSpec::withFilter()`. See [Custom List Types](../list-types/index.md) for the build
lifecycle.
