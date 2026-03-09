# PaletteContract

The `PaletteContract` lets a filter element or list type return a Contao DCA palette string, or adjust palette
prefix/suffix state while the final palette is being built.

**Interface:** `HeimrichHannot\FlareBundle\Contract\PaletteContract`

`AbstractFilterElement` and `AbstractListType` both implement this contract by default and return `null`.

## Method

### `getPalette(PaletteConfig $config): ?string`

This method does **not** return a palette machine name. It returns the palette string that should be merged into the
Contao DCA, or `null` to leave palette resolution to later steps.

`PaletteConfig` contains:

- the current type
- the `DataContainer`
- mutable prefix and suffix strings
- the current `ListModel`
- the current `FilterModel`, if any

## Lifecycle

When Flare builds a backend palette:

1. If the service implements `PaletteContract`, `getPalette()` is called first.
2. If that returns `null`, Flare can still use the palette declared on `#[AsFilterElement]` or `#[AsListType]`.
3. A `PaletteEvent` is dispatched afterwards.
4. The final palette is merged with the current prefix and suffix.

Because `PaletteConfig` is mutable, your implementation can change prefix and suffix even when returning `null`.

## Example

```php
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;

public function getPalette(PaletteConfig $config): ?string
{
    $config->setSuffix(str_replace('sortSettings', '', $config->getSuffix()));

    return '{filter_legend},fieldGeneric,label';
}
```
