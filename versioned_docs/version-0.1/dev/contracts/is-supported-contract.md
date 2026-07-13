# IsSupportedContract

The `IsSupportedContract` lets a service declare whether it should currently be offered by Flare.

**Interface:** `HeimrichHannot\FlareBundle\Contract\IsSupportedContract`

## Method

### `isSupported(): bool`

Return `true` when the service is available in the current environment and `false` when it should be hidden.

## Current runtime usage

In the current codebase, this contract is checked when Flare builds the backend options for the `tl_flare_filter.type`
field.

If a filter element implements `IsSupportedContract` and returns `false`, that filter type is omitted from the backend
selection list.

`AbstractFilterElement` implements this contract by default and returns `true`.

## Example

```php
public function isSupported(): bool
{
    return class_exists(\Codefog\TagsBundle\CodefogTagsBundle::class);
}
```
