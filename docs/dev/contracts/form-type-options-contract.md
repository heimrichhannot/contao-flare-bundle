# FormTypeOptionsContract

The `FormTypeOptionsContract` allows filter elements to dynamically modify the options passed to their Symfony Form Type.

**Interface:** `HeimrichHannot\FlareBundle\Contract\FilterElement\FormTypeOptionsContract`

## Methods

### `handleFormTypeOptions(FilterElementFormTypeOptionsEvent $event): void`

This method is called when the filter form is being built. You can use the event to add or modify options.

```php
public function handleFormTypeOptions(FilterElementFormTypeOptionsEvent $event): void
{
    $options = $event->getOptions();
    
    // Example: Add a custom CSS class to the form field
    $options['attr']['class'] = 'my-custom-filter-class';
    
    $event->setOptions($options);
}
```
