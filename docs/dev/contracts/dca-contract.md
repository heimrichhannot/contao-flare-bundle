# DcaContract

The `DcaContract` lets a filter element or list type assemble its backend DCA configuration — palette and
field tweaks — at runtime.

**Interface:** `HeimrichHannot\FlareBundle\Contract\DcaContract`

## Method

### `buildDca(DcaBuilder $dca, DcaContext $context): void`

Declare the type's palette and per-field adjustments on the `DcaBuilder`. The `DcaContext` provides the
record being edited (`listModel`, `filterModel`) and lazy access to the list's execution context (table
aliases, target table).

FLARE's `loadDataContainer` listener applies the collected configuration to `$GLOBALS['TL_DCA']` for
`tl_flare_filter` and `tl_flare_list`. After `buildDca()` runs, an
[`ElementDcaEvent`](../dca-builder.md#5-modifying-another-types-dca) is dispatched so third parties can
adjust the result.

`AbstractFilterElement` implements this contract with a no-op default; list types opt in by implementing the
interface.

## Example

```php
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;

public function buildDca(DcaBuilder $dca, DcaContext $context): void
{
    $dca->palette('{filter_legend},fieldGeneric,label');

    $dca->field('fieldGeneric')
        ->options(fn (): array => $this->getFieldOptions($context->getTargetTable()));
}
```

See the [Backend DCA Building guide](../dca-builder.md) for the full `DcaBuilder`, `DcaFieldBuilder`, and
`DcaContext` API.
