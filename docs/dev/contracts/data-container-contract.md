# DataContainerContract

The `DataContainerContract` is used by List Types to dynamically determine which Contao Data Container (table) should be used.

**Interface:** `HeimrichHannot\FlareBundle\Contract\ListType\DataContainerContract`

## Methods

### `getDataContainerName(array $row, DataContainer $dc): string`

This method returns the name of the database table (Data Container) for the given record.

```php
use Contao\DataContainer;

public function getDataContainerName(array $row, DataContainer $dc): string
{
    // Return a different table name based on record data
    return $row['use_alternate_table'] ? 'tl_alternate_table' : 'tl_default_table';
}
```
