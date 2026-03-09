# HydrateFormContract

The `HydrateFormContract` is used to pre-populate or "hydrate" a Symfony Form field with initial data before it is rendered.

**Interface:** `HeimrichHannot\FlareBundle\Contract\FilterElement\HydrateFormContract`

## Methods

### `hydrateForm(FormInterface $field, ListSpecification $list, FilterDefinition $filter): void`

This method allows you to set the initial data of a form field.

```php
use Symfony\Component\Form\FormInterface;
use HeimrichHannot\FlareBundle\Specification\FilterDefinition;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

public function hydrateForm(FormInterface $field, ListSpecification $list, FilterDefinition $filter): void
{
    // Pre-select a default value if the field is empty
    if ($field->getData() === null) {
        $field->setData('default_value');
    }
}
```
