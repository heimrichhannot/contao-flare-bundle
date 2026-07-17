# DataContainerContract

The `DataContainerContract` lets a list driver determine the backend `dc` value for a `tl_flare_list` record.

**Interface:** `HeimrichHannot\FlareBundle\Contract\ListDriver\DataContainerContract`

## Method

### `resolveDataContainerTable(array $row, DataContainer $dc): string`

`$row` is the current `tl_flare_list` record. Return the table name that should be stored in its `dc` field.

## When is this called?

Flare uses this contract in the backend on the `tl_flare_list` `config.onsubmit` callback.

Behavior is:

- If the list driver implements `DataContainerContract`, the return value of `resolveDataContainerTable()`
  is stored in the record's `dc` field.
- If that return value is an empty string, Flare stops the update for that submit cycle.
- If the driver does **not** implement the contract, Flare falls back to the `dataContainer` configured on
  the `#[AsListDriver]` attribute.

This hook is about backend record configuration, not query execution: at runtime, the list's main table is
resolved from the canonical config via `ListDriverInterface::getDataContainerName(array $config)` when the
`ListSpec` is created — see
[Data Container Resolution](../list-types/index.md#3-data-container-resolution-getdatacontainername).
A driver pinned to one table typically implements both methods to return that table.

:::info[Changed in v0.2]

The method was renamed from `getDataContainerName()` to `resolveDataContainerTable()`; the freed-up name
now lives on `ListDriverInterface` and resolves the runtime table from the canonical config.

:::

## Example

```php
use Contao\DataContainer;

public function resolveDataContainerTable(array $row, DataContainer $dc): string
{
    return $row['dc'] ?? '';
}
```
