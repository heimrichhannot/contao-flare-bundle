# IntrinsicValueContract

The `IntrinsicValueContract` lets a filter element provide a value for an intrinsic filter when no runtime value is
available.

**Interface:** `HeimrichHannot\FlareBundle\Contract\FilterElement\IntrinsicValueContract`

## Method

### `getIntrinsicValue(ListSpecification $list, FilterDefinition $filter): mixed`

Return any value that the filter's invoker knows how to interpret. That value becomes the one available through
`$invocation->getValue()`.

## When is this called?

Flare uses this contract in `AbstractProjector::gatherFilterValues()`.

The method is called only when:

- The filter element implements `IntrinsicValueContract`.
- The filter is marked as intrinsic.
- No runtime value exists for that filter key.

The "no runtime value" check is based on the presence of the key in the runtime values array. If a runtime key exists,
even with a `null` value, runtime handling wins and intrinsic fallback is skipped.

Intrinsic values are **not** passed through `RuntimeValueContract::processRuntimeValue()`.

## Example

```php
use HeimrichHannot\FlareBundle\Contract\FilterElement\IntrinsicValueContract;
use HeimrichHannot\FlareBundle\Specification\FilterDefinition;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

class MyFilterElement extends AbstractFilterElement implements IntrinsicValueContract
{
    public function getIntrinsicValue(ListSpecification $list, FilterDefinition $filter): mixed
    {
        return $filter->preselect ?: null;
    }
}
```
