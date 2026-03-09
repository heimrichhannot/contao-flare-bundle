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

The `target` parameter corresponds to the [Contao callbacks](https://docs.contao.org/5.x/dev/reference/dca/callbacks/).

### Filter Elements
- **`config.onload`**: Executed when the DataContainer object is initialized.
- **`fields.<fieldname>.options`**: Used to dynamically provide options for a DataContainer field.
- **`fields.<fieldname>.load`**: Triggered when a DataContainer field value is loaded.
- **`fields.<fieldname>.save`**: Triggered before a DataContainer field value is saved.

### List Types
- **`config.onload`**: Triggered when the list configuration is initialized.
- **`fields.<fieldname>.options`**: Used for list configuration fields.

## 3. Arguments

Depending on the target, different arguments are passed to the callback. The arguments are resolved by their type-hint (FQCN) or by their parameter name.

### Filter Callbacks (`#[AsFilterCallback]`)

| Target | Positional Arguments (Must be first) | Optional Arguments (Resolved by type-hint or name) |
| :--- | :--- | :--- |
| `config.onload` | None | `FilterModel`, `ListModel`, `DataContainer` |
| `fields.<field>.options` | None | `FilterModel`, `ListModel`, `DataContainer`, `FilterDefinition`, `ListSpecification`, `ListExecutionContext`, `array $tables`, `string $targetTable` |
| `fields.<field>.load` | `mixed $value` | `FilterModel`, `ListModel`, `DataContainer` |
| `fields.<field>.save` | `mixed $value` | `FilterModel`, `ListModel`, `DataContainer` |

### List Callbacks (`#[AsListCallback]`)

| Target | Positional Arguments (Must be first) | Optional Arguments (Resolved by type-hint or name) |
| :--- | :--- | :--- |
| `config.onload` | None | `ListModel`, `DataContainer` |
| `fields.<field>.options` | None | `ListModel`, `DataContainer` |
| `fields.<field>.load` | `mixed $value` | `ListModel`, `DataContainer` |
| `fields.<field>.save` | `mixed $value` | `ListModel`, `DataContainer` |

### Argument Resolution Details

- **Positional Arguments**: Some targets provide a value as the first argument (e.g., `.load` and `.save` targets).
- **Type-hinted Arguments**: You can type-hint any of the following classes to have them injected:
    - `HeimrichHannot\FlareBundle\Model\FilterModel`
    - `HeimrichHannot\FlareBundle\Model\ListModel`
    - `Contao\DataContainer`
    - `HeimrichHannot\FlareBundle\Specification\FilterDefinition`
    - `HeimrichHannot\FlareBundle\Specification\ListSpecification`
    - `HeimrichHannot\FlareBundle\Query\ListExecutionContext`
- **Named Arguments**: For targets like `options`, additional data is passed that can be accessed by the parameter name:
    - `array $tables`: An array of tables associated with the current list context.
    - `string $targetTable`: The target table name for the filter element.
