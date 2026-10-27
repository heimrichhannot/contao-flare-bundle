---
title: Predicates
sidebar_position: 4
---

# Custom Predicates

Predicates are small, reusable query-fragment builders. They are the only place in FLARE where filter
conditions are written into the SQL query — [filter elements](./filter-elements/index.md) never touch the query
directly; they translate their configuration and the filter's value into *calls* to predicates.

:::info[Formerly "filter types"]

v0.2 renamed the query-side "filter type" to **predicate** (`Filter\Type\` → `Filter\Predicate\`,
`FilterTypeInterface` → `PredicateInterface`, `buildQuery()` → `buildConditions()`). The word "type" now only
means the registered alias of a filter element or list driver.

:::

Because predicates are stateless services with option-validated inputs, the same predicate can be reused by
multiple filter elements or invoked from a
[programmatic filter](./filter-elements/index.md#9-programmatic-filters-without-a-backend-record).

For the predicates that ship with FLARE, see the [built-in predicates reference](../reference/predicates.md).

## 1. The Interface

```php
namespace HeimrichHannot\FlareBundle\Filter\Predicate;

#[AutoconfigureTag(self::FLARE_FILTER_PREDICATE_TAG)]
interface PredicateInterface
{
    public const FLARE_FILTER_PREDICATE_TAG = 'flare.filter_predicate';

    public function configureOptions(OptionsResolver $resolver): void;

    /**
     * @param array<string, mixed> $options
     */
    public function buildConditions(FilterConditionsBuilder $builder, array $options): void;
}
```

- **`configureOptions()`** declares the predicate's option schema with Symfony's
  [OptionsResolver](https://symfony.com/doc/current/components/options_resolver.html). Every call to the
  predicate is resolved against this schema before `buildConditions()` runs, so invalid options fail loudly
  and early.
- **`buildConditions()`** writes the conditions into the [`FilterConditionsBuilder`](#2-the-filterconditionsbuilder),
  which is already scoped to the correct table alias.

Extend `AbstractPredicate` if your predicate has no options — it provides a no-op `configureOptions()`.

### Registration

There is no attribute to add: the interface carries `#[AutoconfigureTag('flare.filter_predicate')]`, so any
service implementing it is registered automatically when autoconfiguration is enabled (the default). If you
configure services manually, tag them with `flare.filter_predicate`. Predicates are looked up by **class
name** in the `FilterPredicateRegistry`.

## 2. The `FilterConditionsBuilder`

The `FilterConditionsBuilder` (`HeimrichHannot\FlareBundle\Query\FilterConditionsBuilder`) provides a safe and
fluent API for building filter conditions. It enforces parameterized queries and handles table aliasing
automatically.

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

## 3. Writing a Custom Predicate

```php
namespace App\Flare\Predicate;

use HeimrichHannot\FlareBundle\Filter\Predicate\AbstractPredicate;
use HeimrichHannot\FlareBundle\Query\FilterConditionsBuilder;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MinPricePredicate extends AbstractPredicate
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('field')->required()->allowedTypes('string');
        $resolver->define('min')->required()->allowedTypes('int', 'float');
    }

    public function buildConditions(FilterConditionsBuilder $builder, array $options): void
    {
        $builder->where($builder->expr()->gte($builder->column($options['field']), ':min'))
            ->setParameter('min', $options['min']);
    }
}
```

That's it — no attribute, no manual registration.

## 4. Consuming Predicates

**From a filter element**, inside `buildContext()`:

```php
public function buildContext(
    FilterContextBuilderInterface $builder,
    ElementScope                  $scope,
    ?ValueInterface               $value,
): void {
    if (!$value = $this->expectValue($value, MyPriceValue::class)) {
        return; // no value, no condition
    }

    $builder->addPredicate(MinPricePredicate::class, [
        'field' => $scope->get('field'),
        'min' => $value->min,
    ]);
}
```

`addPredicate()` takes the predicate class, its options and an optional target alias. The options are always
validated against the predicate's `configureOptions()` schema.

**Programmatically**, through a
[programmatic filter](./filter-elements/index.md#9-programmatic-filters-without-a-backend-record) whose element
emits the call — added to a list through `ListSpecBuilder::addFilter()` or `ListSpec::withFilter()`:

```php
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;

$element = new class implements FilterElementInterface {
    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
        $builder->addPredicate(MinPricePredicate::class, ['field' => 'price', 'min' => 10]);
    }
};

$filter = new Filter(
    // the type is only used for named events; free for an unregistered element
    element: new ElementBinding($element, 'app_min_price', new ComponentConfig()),
    alias: 'min_price',     // the filter's key within the list
);
```

**From Twig**, use [engine mods](./engine-mods.md) — e.g. the built-in `equation` mod — to add filters to
a rendered list.
