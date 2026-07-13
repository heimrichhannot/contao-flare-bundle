# Custom Filter Elements

A filter element owns everything specific to one kind of filter:

- its **config schema** — `configureOptions()` + `configFromRow()`
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
- **`intrinsicOnly`**: (Optional) Boolean. If true, the element never renders a form control and must be
  configured intrinsically.
- **`isTargeted`**: (Optional) Boolean. If true, the filter respects the table alias configured on the
  filter record (see [target aliases](../../spec/filtering.md#5-target-aliases)).

:::info[Changed in v0.2]

The `palette`, `formType`, and `method` parameters were removed: the palette is now built in
[`buildDca()`](#4-backend-dca-builddca), form controls in [`buildForm()`](#5-form-building-buildform), and
the query translation always happens in [`buildFilter()`](#6-query-translation-buildfilter).

:::

## 2. The Interfaces

Every element implements `FilterElementInterface`:

```php
public function buildForm(FormBuilderInterface $builder, FilterContext $context): void;

public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $data): void;
```

Elements with configuration additionally implement `FilterElementOptionsInterface`:

```php
public function configureOptions(OptionsResolver $resolver): void;

public function configFromRow(array $row): array;
```

`AbstractFilterElement` implements both, plus [`IsSupportedContract`](../contracts/is-supported-contract.md)
(returns `true`) and [`DcaContract`](../contracts/dca-contract.md) (no-op). Only `configureOptions()` and
`configFromRow()` are abstract; `buildDca()`, `buildForm()`, and `buildFilter()` default to no-ops.

### The `FilterContext`

Both `buildForm()` and `buildFilter()` receive a `FilterContext`
(`HeimrichHannot\FlareBundle\Filter\FilterContext`, final readonly):

| Member | Description |
|---|---|
| `$context->list` | The `ListSpecification` the filter belongs to |
| `$context->filter` | The [`Filter` value object](../../spec/filtering.md#1-the-filter-value-object) |
| `$context->config` | The **resolved** canonical config (validated against `configureOptions()`) |
| `$context->engineContext` | The engine context (`ContextInterface`) — interactive, validation, ... |
| `$context->key` | Key of the filter within `ListSpecification::getFilters()` |
| `FilterContext::FIELD_VALUE` | Constant `'v'` — conventional local child name for single-field elements |
| `FilterContext::FORM_ATTRIBUTE` | Attribute-bag key under which the context is stored on the per-filter form builder |

## 3. Config Schema (`configureOptions` + `configFromRow`)

`configureOptions()` declares the element's canonical config with Symfony's `OptionsResolver`;
`configFromRow()` maps a stored `tl_flare_filter` row onto that schema. Everything downstream —
`buildDca()`, `buildForm()`, `buildFilter()` — works with the resolved config, never with raw DCA rows,
which is what makes filters equally usable from the database, PHP, and Twig.

```php
public function configureOptions(OptionsResolver $resolver): void
{
    $resolver->define('field')->default('city')->allowedTypes('string');
    $resolver->define('label')->default(null)->allowedTypes('string', 'null');
}

public function configFromRow(array $row): array
{
    return [
        'field' => ($row['fieldGeneric'] ?? null) ?: 'city',
        'label' => ($row['label'] ?? null) ?: null,
    ];
}
```

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

Each non-intrinsic filter gets its own compound sub-form named after the filter's form name. Your element
adds children to it under **local names** — use `FilterContext::FIELD_VALUE` (`'v'`) for single-control
elements. Add no children and the filter simply has no form representation.

```php
use Symfony\Component\Form\Extension\Core\Type\TextType;

public function buildForm(FormBuilderInterface $builder, FilterContext $context): void
{
    if ($context->config['intrinsic'] ?? false) {
        return; // intrinsic filters render no controls
    }

    $builder->add(FilterContext::FIELD_VALUE, TextType::class, [
        'label' => $context->config['label'] ?? false,
        'required' => false,
    ]);
}
```

Pre-submission defaults go into the children's native `data` option. After your element built its children,
a [`FilterElementFormBuiltEvent`](../events.md) is dispatched so third parties can adjust or cancel the
sub-form.

## 6. Query Translation (`buildFilter`)

`buildFilter()` turns config and data into one or more **filter-type calls** — it does not write SQL:

- `$context->config` is the resolved canonical config.
- `$data` holds the submitted form data, keyed by the local child names from `buildForm()`
  (or the filter's programmatic data bag; an empty array otherwise).
- `$builder->add(SomeFilterType::class, $options, ?$targetAlias)` records a call; the options are validated
  against the filter type's own schema.
- `$builder->abort()` stops filtering entirely and yields an empty result set.

```php
public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $data): void
{
    if (!$value = $data[FilterContext::FIELD_VALUE] ?? null) {
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
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Filter\Element\AbstractFilterElement;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\Type\SimpleEquationFilterType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AsFilterElement(type: 'app_city', isTargeted: true)]
class CityFilterElement extends AbstractFilterElement
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('field')->default('city')->allowedTypes('string');
        $resolver->define('label')->default(null)->allowedTypes('string', 'null');
    }

    public function configFromRow(array $row): array
    {
        return [
            'field' => ($row['fieldGeneric'] ?? null) ?: 'city',
            'label' => ($row['label'] ?? null) ?: null,
        ];
    }

    public function buildDca(DcaBuilder $dca, DcaContext $context): void
    {
        $dca->palette('{filter_legend},fieldGeneric,label');
    }

    public function buildForm(FormBuilderInterface $builder, FilterContext $context): void
    {
        $builder->add(FilterContext::FIELD_VALUE, TextType::class, [
            'label' => $context->config['label'] ?? false,
            'required' => false,
        ]);
    }

    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $data): void
    {
        if (!$value = $data[FilterContext::FIELD_VALUE] ?? null) {
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

## 8. Intrinsic Filters & `define()` Factories

Built-in elements expose static `define()` factories that create ready-made intrinsic
[`Filter`](../../spec/filtering.md#1-the-filter-value-object) objects for programmatic use:

```php
use HeimrichHannot\FlareBundle\Filter\Element\BooleanFilterElement;

$listSpecification->addFilter(
    BooleanFilterElement::define(targetField: 'featured', expectedValue: true)
);
```

Consider providing one on your own elements. Since `Filter` is immutable, use
`->withTargetAlias('alias')` (not the removed `forceTargetAlias()`) to retarget the returned filter.

## 9. Inline Filters Without a Service

For one-off filters that don't warrant a registered element, create a `Filter` directly:

```php
use HeimrichHannot\FlareBundle\Filter\Filter;

// A single filter-type call with options:
Filter::fromType(SimpleEquationFilterType::class, [
    'operand_left' => 'featured',
    'operator' => SqlEquationOperator::EQUALS,
    'operand_right' => '1',
]);

// Full control via closures:
Filter::fromCallback(
    buildFilter: static function (FilterBuilderInterface $builder, FilterContext $context, array $data): void {
        // ...
    },
);
```

Both are backed by the internal `CallbackFilterElement` and can be added to any list via
`ListSpecification::addFilter()` — or created from Twig with
[`flare_make_filter()`](../../templating.mdx#twig-helpers).
