# IntrinsicValueContract

The `IntrinsicValueContract` allows a filter element to provide a default value when it is marked as **intrinsic** (automatic) and no value was provided by the user or context.

**Interface:** `HeimrichHannot\FlareBundle\Contract\FilterElement\IntrinsicValueContract`

## Implementation

Implement this contract to fetch default values from the backend configuration (e.g., a "Preselect" field in the DCA).

```php
use HeimrichHannot\FlareBundle\Contract\FilterElement\IntrinsicValueContract;
use HeimrichHannot\FlareBundle\Specification\FilterDefinition;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

class MyFilterElement extends AbstractFilterElement implements IntrinsicValueContract
{
    public function getIntrinsicValue(ListSpecification $list, FilterDefinition $filter): mixed
    {
        // Fetch value from the filter's data source (e.g., a Contao Model)
        return $filter->getDataSource()?->getFilterProperty('preselect_value');
    }
}
```

### When is this called?
*   The filter is marked as **intrinsic** in the list configuration.
*   No runtime value was provided (e.g. from a Form or Request parameter).

:::note
Intrinsic values are **NOT** passed through `processRuntimeValue()`.
:::
