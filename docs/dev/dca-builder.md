---
title: Backend DCA Building
sidebar_position: 5
---

# Backend DCA Building

List types and filter elements assemble their backend palettes and field tweaks at runtime by implementing
`Contract\DcaContract`:

```php
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;

public function buildDca(DcaBuilder $dca, DcaContext $context): void;
```

FLARE's `loadDataContainer` listener collects the configuration declared here (plus any changes made by
[`ElementDcaEvent` listeners](#5-modifying-another-types-dca)) and materializes it into the live
`$GLOBALS['TL_DCA']` array for `tl_flare_filter` and `tl_flare_list`.

:::info[Changed in v0.2]

This replaces the removed `palette:` parameter of `#[AsFilterElement]` / `#[AsListType]` and the removed
`#[AsFilterCallback]` / `#[AsListCallback]` DCA callback attributes. See
[Migrating from v0.1](../migrating-from-v0.1.md) for the full mapping.

:::

## 1. The `DcaBuilder`

- **`palette(?string $palette)`**: Sets the element's own palette part — a string of DCA legends and field
  names, e.g. `'{filter_legend},fieldGeneric,preselect'`. It is merged between the table's `__prefix__` and
  `__suffix__` palettes. Pass `null` if the type contributes no own fields.
- **`prefix(string|callable|null $prefix)`** / **`suffix(string|callable|null $suffix)`**: Override the
  palette prefix/suffix for this type. A string replaces the table's `__prefix__`/`__suffix__`, a callable
  `fn (string $current): string` transforms it, `null` keeps it as is.
- **`field(string $name): DcaFieldBuilder`**: Returns a builder for per-type tweaks of a DCA field definition.

The referenced fields must exist in the table's DCA (`tl_flare_filter` / `tl_flare_list`) — `field()` tweaks
definitions, it does not create columns.

## 2. The `DcaFieldBuilder`

Fluent, all methods return the builder:

- **`inputType(string $inputType)`**: Sets the field's `inputType`.
- **`eval(array $eval)`**: Merges into the field's `eval` array.
- **`merge(array $definition)`**: Merges an arbitrary partial field definition.
- **`options(callable|array $options)`**: Sets the field's options — an array, or a lazy
  `fn (): array` callable evaluated when the DCA is built.
- **`load(callable $fn)`** / **`save(callable $fn)`**: Registers `load_callback` / `save_callback` handlers.

## 3. The `DcaContext`

Read-only information about the record being edited:

| Member | Description |
|---|---|
| `$context->table` | The DCA table being built (`tl_flare_filter` or `tl_flare_list`) |
| `$context->type` | The element / list type alias |
| `$context->listModel` | The `ListModel` of the (parent) list |
| `$context->filterModel` | The `FilterModel` being edited, or `null` on `tl_flare_list` |
| `$context->getExecutionContext()` | Lazy `?ListExecutionContext` (query base, table aliases) |
| `$context->getTables()` | `array<string, string>` of table names by alias |
| `$context->getTargetTable()` | The table the filter's conditions target (configured target alias' table, falling back to the list's data container) |

## 4. Worked Example

Condensed from the built-in `BooleanFilterElement`:

```php
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;

public function buildDca(DcaBuilder $dca, DcaContext $context): void
{
    $intrinsic = (bool) $context->filterModel?->intrinsic;

    $dca->palette($intrinsic
        ? '{filter_legend},fieldGeneric,preselect'
        : '{filter_legend},fieldGeneric,label,boolMode,preselect');

    $dca->field('preselect')
        ->inputType('select')
        ->eval(['includeBlankOption' => false, 'chosen' => false])
        ->options([
            'true' => 'flare.bool_preselect.true',
            'false' => 'flare.bool_preselect.false',
        ]);

    // Lazy options against the filter's target table
    $dca->field('fieldGeneric')
        ->options(fn (): array => $this->getFieldOptions($context->getTargetTable()));
}
```

Note how the palette can react to the record's current state (`$context->filterModel`), and how expensive
option lookups stay lazy.

## 5. Modifying Another Type's DCA

To adjust the backend configuration of an element or list type you don't own, listen to `ElementDcaEvent`
(`HeimrichHannot\FlareBundle\Event\ElementDcaEvent`, readonly properties `$dca` and `$context`). It is
dispatched after the element's own `buildDca()` ran, under a name specific to the type:

- `flare.filter_element.{type}.dca` for filter elements (on `tl_flare_filter`)
- `flare.list.{type}.dca` for list types (on `tl_flare_list`)

```php
use HeimrichHannot\FlareBundle\Event\ElementDcaEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener('flare.filter_element.flare_bool.dca')]
public function __invoke(ElementDcaEvent $event): void
{
    $event->dca->field('preselect')->eval(['tl_class' => 'w50']);
}
```

## 6. Migrating from v0.1 Callbacks

| v0.1 | v0.2 |
|---|---|
| `#[AsFilterElement(palette: '...')]` / `#[AsListType(palette: '...')]` | `$dca->palette('...')` in `buildDca()` |
| `#[AsFilterCallback(TYPE, 'fields.myField.options')]` | `$dca->field('myField')->options(...)` — or `ElementDcaEvent` on `flare.filter_element.{type}.dca` for foreign types |
| `#[AsFilterCallback(TYPE, 'fields.myField.load')]` / `'fields.myField.save'` | `$dca->field('myField')->load(...)` / `->save(...)` |
| `#[AsFilterCallback(TYPE, 'config.onload')]` / list `config.onload` | `buildDca()` (own type) / `ElementDcaEvent` listener (foreign type) |
| `PaletteContract::getPalette()` / `PaletteEvent` (`flare.*.palette`) | `buildDca()` with `palette()`/`prefix()`/`suffix()` / `ElementDcaEvent` (`flare.*.dca`) |
