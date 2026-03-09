# Callbacks

Callbacks allow you to hook into the configuration and lifecycle of filter elements and list types using PHP 8 attributes. This mechanism replaces the traditional Contao DCA callbacks with a more flexible, service-oriented approach.

## 1. Registration

To register a callback, use the `#[AsFilterCallback]` or `#[AsListCallback]` attribute on a service method.

### `#[AsFilterCallback]`
Used for logic related to **Filter Elements**.

```php
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterCallback;
use HeimrichHannot\FlareBundle\Specification\FilterDefinition;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use HeimrichHannot\FlareBundle\Query\ListExecutionContext;

#[AsFilterCallback(
    element: 'my_filter_type',
    target: 'fields.myField.options',
    priority: 10
)]
public function getMyFieldOptions(
    ListSpecification $list, 
    FilterDefinition $filter, 
    ListExecutionContext $context
): array {
    return [
        'value1' => 'Label 1',
        'value2' => 'Label 2',
    ];
}
```

### `#[AsListCallback]`
Used for logic related to **List Types**.

```php
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListCallback;

#[AsListCallback(
    element: 'my_list_type',
    target: 'config.onload'
)]
public function handleOnLoad(): void
{
    // Custom logic when the list type is loaded
}
```

## 2. Common Targets

The `target` parameter corresponds to the path in the internal configuration that would normally trigger a callback.

### Filter Elements
- **`config.onload`**: Triggered when the filter configuration is initialized.
- **`fields.<fieldname>.options`**: Used to dynamically provide options for a configuration field.
- **`fields.<fieldname>.load`**: Triggered when a field value is loaded.
- **`fields.<fieldname>.save`**: Triggered before a field value is saved.

### List Types
- **`config.onload`**: Triggered when the list configuration is initialized.
- **`fields.<fieldname>.options`**: Used for list configuration fields.

## 3. Arguments

Depending on the target, different arguments are passed to the callback. Most commonly:

- **`ListSpecification $list`**: The specification of the current list.
- **`FilterDefinition $filter`**: (Filter callbacks only) The definition of the filter element.
- **`ListExecutionContext $context`**: Provides access to the query builder, table registry, and other runtime data.
