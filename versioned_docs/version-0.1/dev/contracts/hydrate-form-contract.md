# HydrateFormContract

The `HydrateFormContract` lets a filter element write initial data into its Symfony form field before the form is
rendered.

**Interface:** `HeimrichHannot\FlareBundle\Contract\FilterElement\HydrateFormContract`

## Method

### `hydrateForm(FormInterface $field, ListSpecification $list, FilterDefinition $filter): void`

Use this method to call `$field->setData(...)` or otherwise adjust the field before first render.

## When is this called?

`HydrateFormContract` is only used by the interactive projector.

The method is called only when all of the following are true:

- The filter element implements `HydrateFormContract`.
- The current form has not been submitted yet.
- The filter is **not** intrinsic.
- The filter has a field alias and that child exists in the built form.

The form request is already handled before hydration runs, so this hook is for initial field state, not for submitted
data processing.

If you need a value for an intrinsic filter, use `IntrinsicValueContract` instead.

## Example

```php
use HeimrichHannot\FlareBundle\Specification\FilterDefinition;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Component\Form\FormInterface;

public function hydrateForm(FormInterface $field, ListSpecification $list, FilterDefinition $filter): void
{
    if ($field->getData() !== null) {
        return;
    }

    $field->setData($filter->preselect ?: null);
}
```
