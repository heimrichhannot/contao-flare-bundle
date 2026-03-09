# FormTypeOptionsContract

The `FormTypeOptionsContract` allows filter elements to dynamically modify the options passed to their Symfony Form Type.

**Interface:** `HeimrichHannot\FlareBundle\Contract\FilterElement\FormTypeOptionsContract`

## Method

### `handleFormTypeOptions(FilterElementFormTypeOptionsEvent $event): void`

This method is called while the filter form is being built.

The event exposes public properties:

- `$event->list`: the current `ListSpecification`
- `$event->filter`: the current `FilterDefinition`
- `$event->options`: the Symfony form options array being built
- `$event->choicesBuilder`: a `ChoicesBuilder` instance for choice-like fields

Update `$event->options` directly. There are no `getOptions()` or `setOptions()` methods on this event.

Enable and populate `$event->choicesBuilder` when your form type needs generated `choices`, `choice_label`, and
`choice_value` callbacks.

## Lifecycle

1. `FilterFormFactory` creates a fresh `FilterElementFormTypeOptionsEvent` with empty options.
2. If the filter element implements `FormTypeOptionsContract`, `handleFormTypeOptions()` is called.
3. The same event is dispatched so listeners can continue modifying it.
4. If the `ChoicesBuilder` was enabled, the factory converts it into Symfony choice options.
5. The final field options are merged with Flare's defaults.

```php
use HeimrichHannot\FlareBundle\Event\FilterElementFormTypeOptionsEvent;

public function handleFormTypeOptions(FilterElementFormTypeOptionsEvent $event): void
{
    $event->options['required'] = false;
    $event->options['attr']['class'] = 'my-custom-filter-class';

    $event->choicesBuilder
        ->enable()
        ->setEmptyOption(true)
        ->add('draft', 'label.draft')
        ->add('published', 'label.published');
}
```

:::note
`AbstractFilterElement` already implements this contract with a no-op method.
:::
