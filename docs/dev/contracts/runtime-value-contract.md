# RuntimeValueContract

The `RuntimeValueContract` is used to normalize or preprocess values that come from user interaction (Forms, GET parameters, Twig variables).

**Interface:** `HeimrichHannot\FlareBundle\Contract\FilterElement\RuntimeValueContract`

## Implementation

Implement this if you need to transform the raw input before it reaches your `__invoke` method.

```php
use HeimrichHannot\FlareBundle\Contract\FilterElement\RuntimeValueContract;
use HeimrichHannot\FlareBundle\Specification\FilterDefinition;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

class MyFilterElement extends AbstractFilterElement implements RuntimeValueContract
{
    public function processRuntimeValue(mixed $value, ListSpecification $list, FilterDefinition $filter): mixed
    {
        // Example: Convert a comma-separated string from a GET parameter into an array
        if (is_string($value)) {
            return explode(',', $value);
        }

        return $value;
    }
}
```

:::info
`AbstractFilterElement` implements this contract by default (returning the value unchanged), so you only need to override `processRuntimeValue` when needed.
:::
