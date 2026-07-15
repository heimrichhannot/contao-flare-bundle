---
title: Filter Types
sidebar_position: 4
---

# Custom Filter Types

Filter types are small, reusable query-fragment builders. They are the only place in FLARE where filter
conditions are written into the SQL query — [filter elements](./filter-elements/index.md) never touch the query
directly; they translate their configuration and form data into *calls* to filter types.

Because filter types are stateless services with option-validated inputs, the same type can be reused by
multiple filter elements or invoked from an
[inline filter](./filter-elements/index.md#9-inline-filters-without-a-service) added programmatically.

For the filter types that ship with FLARE, see the [built-in filter types reference](../reference/filter-types.md).

## 1. The Interface

```php
namespace HeimrichHannot\FlareBundle\Filter\Type;

#[AutoconfigureTag(self::FLARE_FILTER_TYPE_TAG)]
interface FilterTypeInterface
{
    public const FLARE_FILTER_TYPE_TAG = 'huh.flare.filter_type';

    public function configureOptions(OptionsResolver $resolver): void;

    /**
     * @param array<string, mixed> $options
     */
    public function buildQuery(FilterQueryBuilder $builder, array $options): void;
}
```

- **`configureOptions()`** declares the type's option schema with Symfony's
  [OptionsResolver](https://symfony.com/doc/current/components/options_resolver.html). Every call to the type
  is resolved against this schema before `buildQuery()` runs, so invalid options fail loudly and early.
- **`buildQuery()`** writes the conditions into the [`FilterQueryBuilder`](#2-the-filterquerybuilder),
  which is already scoped to the correct table alias.

Extend `AbstractFilterType` if your type has no options — it provides a no-op `configureOptions()`.

### Registration

There is no attribute to add: the interface carries `#[AutoconfigureTag('huh.flare.filter_type')]`, so any
service implementing it is registered automatically when autoconfiguration is enabled (the default). If you
configure services manually, tag them with `huh.flare.filter_type`.

## 2. The `FilterQueryBuilder`

The `FilterQueryBuilder` (`HeimrichHannot\FlareBundle\Query\FilterQueryBuilder`) provides a safe and fluent
API for building filter conditions. It enforces parameterized queries and handles table aliasing automatically.

### Key Methods:

- **`column(string $column)`**: Returns the quoted column name prefixed with the correct table alias
  (e.g., `` `main`.`my_field` ``). **Always use this for column names!**
- **`where(string|CompositeExpression $query, ?array $params = null)`**: Adds a WHERE condition.
- **`whereAnd(...)` / `whereOr(...)`**: Combine multiple conditions.
- **`expr()`**: Returns Doctrine's `ExpressionBuilder` for composing conditions (`eq()`, `gt()`, `like()`, ...).
- **`setParameter(string $param, mixed $value)`**: Safely binds a value to a placeholder.
- **`whereInSerialized(array|int|string $find, string $column)`**: Special helper for filtering against
  Contao's serialized array columns.
- **`abort()`**: Static method to immediately stop filtering and return an empty result set
  (e.g., if a required value is missing).

## 3. Writing a Custom Filter Type

```php
namespace App\Flare\FilterType;

use HeimrichHannot\FlareBundle\Filter\Type\AbstractFilterType;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MinPriceFilterType extends AbstractFilterType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('field')->required()->allowedTypes('string');
        $resolver->define('min')->required()->allowedTypes('int', 'float');
    }

    public function buildQuery(FilterQueryBuilder $builder, array $options): void
    {
        $builder->where($builder->expr()->gte($builder->column($options['field']), ':min'))
            ->setParameter('min', $options['min']);
    }
}
```

That's it — no attribute, no manual registration.

## 4. Consuming Filter Types

**From a filter element**, inside `buildFilter()`:

```php
public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $values): void
{
    $builder->add(MinPriceFilterType::class, [
        'field' => $context->config['field'],
        'min' => (int) ($values[FilterContext::SINGLE_VALUE] ?? 0),
    ]);
}
```

**Programmatically**, via an [inline filter](./filter-elements/index.md#9-inline-filters-without-a-service)
whose element emits the call — added to a list through `ListBuilder::addFilter()` or
`ListSpec::withFilter()`:

```php
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;

$filter = new Filter(
    type: new class implements FilterElementInterface {
        public function buildForm(FilterFormBuilderInterface $builder, FilterContext $context): void {}

        public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $values): void
        {
            $builder->add(MinPriceFilterType::class, ['field' => 'price', 'min' => 10]);
        }
    },
);
```

**From Twig**, use [engine mods](./engine-mods.md) — e.g. the built-in `equation` mod — to add filters to
a rendered list.

The options passed to `add()` are always validated against the type's `configureOptions()` schema.
