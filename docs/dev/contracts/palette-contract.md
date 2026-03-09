# PaletteContract

The `PaletteContract` allows both List Types and Filter Elements to provide a custom Contao DCA palette name.

**Interface:** `HeimrichHannot\FlareBundle\Contract\PaletteContract`

## Methods

### `getPalette(PaletteConfig $config): ?string`

This method returns the machine name of the palette to be used in the Contao backend configuration.

```php
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;

public function getPalette(PaletteConfig $config): ?string
{
    // Return a specific palette name for this list type or filter element
    return 'my_custom_palette';
}
```
