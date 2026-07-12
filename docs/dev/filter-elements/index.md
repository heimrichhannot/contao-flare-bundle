# Custom Filter Elements

Filter Elements define how a filter value (from a form or context) is applied to the SQL query.
For the filter elements that ship with Flare, see the
[built-in filter elements reference](../../reference/filter-elements.md).

## 1. Registration (`#[AsFilterElement]`)

To create a custom filter element, annotate your class with the `#[AsFilterElement]` attribute. It is recommended to extend `AbstractFilterElement`.

```php
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\FilterElement\AbstractFilterElement;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;

#[AsFilterElement(
    type: 'my_custom_filter',
    formType: MyCustomFilterType::class
)]
class MyCustomFilterElement extends AbstractFilterElement
{
    public function __invoke(FilterInvocation $invocation, FilterQueryBuilder $qb): void
    {
        $value = $invocation->getValue();
        
        if (empty($value)) {
            return;
        }

        $qb->where($qb->column('my_field') . ' = :val')
           ->setParameter('val', $value);
    }
}
```

### Attribute Parameters:
- **`type`**: Unique identifier for the filter type.
- **`formType`**: The Symfony Form Type class used for this filter in the frontend.
- **`palette`**: (Optional) The full palette definition shown in the filter's backend configuration — a string of
  DCA legends and field names, e.g. `'{filter_legend},fieldGeneric,preselect'`. The referenced fields must exist
  in the `tl_flare_filter` DCA. This is **not** the name of a palette.
- **`isTargeted`**: (Optional) Boolean. If true, the filter expects a specific table alias.
- **`method`**: (Optional) The method to invoke on the service when the filter is applied. Defaults to `__invoke`.

## 2. The `FilterQueryBuilder`

The `FilterQueryBuilder` provides a safe and fluent API for modifying the SQL query. It automatically handles table aliasing to prevent collisions.

### Key Methods:

- **`column(string $name)`**: Returns the quoted column name prefixed with the correct table alias (e.g., `` `main`.`my_field` ``). **Always use this for column names!**
- **`where(string|CompositeExpression $query, ?array $params = null)`**: Adds a WHERE condition.
- **`setParameter(string $param, mixed $value)`**: Safely binds a value to a placeholder.
- **`whereInSerialized(mixed $find, string $column)`**: Special helper for filtering against Contao's serialized array columns.
- **`abort()`**: Static method to immediately stop filtering and return an empty result set (e.g., if a required value is missing).

### Example:
```php
public function __invoke(FilterInvocation $invocation, FilterQueryBuilder $qb): void
{
    $value = $invocation->getValue();
    
    // Simple equality
    $qb->where($qb->column('city') . ' = :city', ['city' => $value]);
    
    // Using Expression Builder
    $qb->where($qb->expr()->gt($qb->column('price'), ':min'))
       ->setParameter('min', 100);
}
```

## 3. Context-Specific Invokers (deprecated)

:::caution[Deprecated]

The `#[AsFilterInvoker]` attribute for context-specific invocation logic is deprecated and will be removed in a
future version. Branch on the context inside your `__invoke` method instead. See
[Filter Invocation](../../spec/invoker.md) for the legacy reference.

:::
