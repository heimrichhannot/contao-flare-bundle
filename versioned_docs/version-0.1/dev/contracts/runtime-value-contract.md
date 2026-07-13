# RuntimeValueContract

The `RuntimeValueContract` is used to normalize or preprocess values that were determined at runtime.

**Interface:** `HeimrichHannot\FlareBundle\Contract\FilterElement\RuntimeValueContract`

## Method

### `processRuntimeValue(mixed $value, ListSpecification $list, FilterDefinition $filter): mixed`

Return the processed value that should be passed to the filter invoker. The returned value becomes the one available
through `$invocation->getValue()`.

## When is this called?

Flare uses this contract in `AbstractProjector::gatherFilterValues()`.

The method is called only when a runtime value exists for the filter key. In interactive lists that runtime value comes
from the form view data; in other execution contexts it can come from context-provided filter values.

If the key is absent, `processRuntimeValue()` is not called. If the key is present with `null`, the method is still
called with `null`.

This hook is not used for intrinsic fallback values returned by `IntrinsicValueContract`.

## Example

```php
use HeimrichHannot\FlareBundle\Contract\FilterElement\RuntimeValueContract;
use HeimrichHannot\FlareBundle\Specification\FilterDefinition;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

class MyFilterElement extends AbstractFilterElement implements RuntimeValueContract
{
    public function processRuntimeValue(mixed $value, ListSpecification $list, FilterDefinition $filter): mixed
    {
        if (is_string($value)) {
            return explode(',', $value);
        }

        return $value;
    }
}
```

:::info
`AbstractFilterElement` implements this contract by default and returns the runtime value unchanged.
:::
