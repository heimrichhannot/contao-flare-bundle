# FormDataContract

The `FormDataContract` lets a filter element control how the runtime value is extracted from its submitted Symfony
form field.

**Interface:** `HeimrichHannot\FlareBundle\Contract\FilterElement\FormDataContract`

## Method

### `extractFormData(FormInterface $form): mixed`

Return the value that the filter invoker should receive for this filter. The default implementation in
`AbstractFilterElement` simply returns `$form->getData()`.

## When is this called?

`FormDataContract` is only used by the interactive projector. When it gathers the filter values from the submitted
filter form, it calls `extractFormData()` on every filter element that implements the contract; elements without it
fall back to `$field->getData()`.

Override this when the form field's model data is not what your invoker needs — for example, to work with the view
data instead, or to unwrap a compound field.

## Example

`DcaSelectFieldElement` uses the view data instead of the (transformed) model data:

```php
use Symfony\Component\Form\FormInterface;

public function extractFormData(FormInterface $form): mixed
{
    return $form->getViewData();
}
```

If you need to normalize the extracted value further before invocation, combine this with
[`RuntimeValueContract`](./runtime-value-contract.md).
