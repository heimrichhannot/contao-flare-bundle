# DataContainerContract

The `DataContainerContract` lets a list type determine the backend `dc` value for a `tl_flare_list` record.

**Interface:** `HeimrichHannot\FlareBundle\Contract\ListType\DataContainerContract`

## Method

### `getDataContainerName(array $row, DataContainer $dc): string`

`$row` is the current `tl_flare_list` record. Return the table name that should be stored in its `dc` field.

## When is this called?

Flare uses this contract in the backend on the `tl_flare_list` `config.onsubmit` callback.

Behavior is:

- If the list type implements `DataContainerContract`, the return value of `getDataContainerName()` is used.
- If that return value is an empty string, Flare stops the update for that submit cycle.
- If the list type does **not** implement the contract, Flare falls back to the `dataContainer` configured on the
  `#[AsListType]` descriptor.

This hook is therefore about backend record configuration, not query execution.

## Example

```php
use Contao\DataContainer;

public function getDataContainerName(array $row, DataContainer $dc): string
{
    return $row['dc'] ?? '';
}
```
