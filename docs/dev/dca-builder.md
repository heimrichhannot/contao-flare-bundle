---
title: Backend DCA Building
sidebar_position: 5
---

# Backend DCA Building

List drivers and filter elements assemble their backend palettes and field tweaks at runtime by implementing
`Contract\DcaContract`:

```php
use HeimrichHannot\FlareBundle\Contao\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\Contao\DcaContext;

public function buildDca(DcaBuilderInterface $builder, DcaContext $context): void
{
    $builder->palette('{filter_legend},elm_fieldGeneric');

    // Lazy options against the filter's target table
    $builder->field('elm_fieldGeneric')
        ->options(fn (): array => $this->getFieldGenericOptions($context->getTargetTable()));
}
```

Note how expensive option lookups stay lazy, and that the palette can react to the record's current state
(`$context->filterModel`). A filter record's palette is the element's segment plus the segment of the selected
[control](./filter-controls.md), which is composed automatically; the field is prefixed `elm_` (element) or
`ctl_` (control).

## 4. Fields and Palettes

`$builder->field('name')` returns a `DcaFieldBuilderInterface` for per-type tweaks of one field, applied only while
a record of that type is edited:

| Method | Effect |
|---|---|
| `eval(array)`, `merge(array)` | Merge keys into the field's `eval` / definition. Merging cannot remove a key — one set to `null` stays, as `null`; use `remove()` |
| `remove(string ...$paths)` | Removes keys of the definition, as dot paths (`'eval.rgxp'`, `'wizard'`), before the builder's own definition is merged in; a later `eval()` or `merge()` sets the key again |
| `makeDatim()` | Turns a text field into Contao's date-time input (`rgxp` datim, date picker, `wizard` class) and adds a load callback that shows a stored expression such as `today` as its timestamp |
| `inputType()`, `label()`, `options()`, `load()`, `save()` | Set the input type, replace the label, set (lazy) options, add load and save callbacks |

`remove()` covers the keys of the definition and of `eval()`/`merge()` only — not the label, the options or the
callbacks: their setters are applied after the removal, and `remove()` does not touch what they set.

```php
// a date picker only in the mode that stores a timestamp
if ($context->filterModel?->elm_configureStart === 'date') {
    $builder->field('elm_startAt')->makeDatim();
}

// a plain select instead of Contao's chosen widget
$builder->field('elm_fieldGeneric')->remove('eval.chosen');
```

**Empty legends.** When the hook writes a type's palette into the DCA (`DcaBuilder::apply()`, only while a record
is edited), it drops every legend left without fields — e.g. `{advanced_legend:hide}` of `tl_flare_list`'s
`__suffix__`, which no bundled list type fills, or `{expert_legend:hide}` of a filter type whose suffix override
removes its fields. `Str::mergePalettes()` keeps empty legends, and so do the table's own palettes (`__prefix__`,
`__suffix__`, `default`).

For extension authors who change palettes with Contao's `PaletteManipulator`: `apply()` drops a legend that has no
fields at that moment, so a field you add under such a legend — e.g. `advanced_legend` of `tl_flare_list` — must be
there before `apply()` runs. FLARE's `loadDataContainer` hook runs at priority `-100` (Contao runs higher priorities
first, after the DCA files): target `__suffix__` (or `__prefix__`) from a DCA file or a `loadDataContainer` hook with
a higher priority, and the merged palette carries your field under its legend. Applied to a type's palette after the
hook, the manipulator no longer finds the dropped legend and, without a fallback legend, appends the field to the
last legend (`publish_legend`).

## 5. Modifying Another Type's DCA

To adjust the backend configuration of a type you don't own, listen to its DCA event (readonly properties
`$builder` and `$context`). Each is dispatched after the type's own `buildDca()` ran, under a name specific to
the type:

- `FilterElementBuildDcaEvent` as `flare.filter_element.{type}.dca` for filter elements (on `tl_flare_filter`)
- `FilterControlBuildDcaEvent` as `flare.filter_control.{type}.dca` for filter controls (on `tl_flare_filter`); its
  builder is scoped to the control's palette segment, and `$type` holds the control type
- `ListDriverBuildDcaEvent` as `flare.list_driver.{type}.dca` for list drivers (on `tl_flare_list`)

```php
use HeimrichHannot\FlareBundle\Event\FilterElementBuildDcaEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener('flare.filter_element.flare_bool.dca')]
public function __invoke(FilterElementBuildDcaEvent $event): void
{
    $event->builder->field('elm_fieldGeneric')->eval(['tl_class' => 'w50']);
}
```

A field whose meaning depends on the type can get its own label with
`$builder->field('value')->label('Date range', 'Start and end ...')`; it replaces Contao's default label
instead of merging into it.

## 6. Migrating from v0.1 Callbacks

| v0.1 | v0.2 |
|---|---|
| `#[AsFilterElement(palette: '...')]` / `#[AsListType(palette: '...')]` | `$builder->palette('...')` in `buildDca()` |
| `#[AsFilterCallback(TYPE, 'fields.myField.options')]` | `$builder->field('myField')->options(...)` — or `FilterElementBuildDcaEvent` on `flare.filter_element.{type}.dca` for foreign types |
| `#[AsFilterCallback(TYPE, 'fields.myField.load')]` / `'fields.myField.save'` | `$builder->field('myField')->load(...)` / `->save(...)` |
| `#[AsFilterCallback(TYPE, 'config.onload')]` / list `config.onload` | `buildDca()` (own type) / `FilterElementBuildDcaEvent` or `ListDriverBuildDcaEvent` listener (foreign type) |
| `PaletteContract::getPalette()` / `PaletteEvent` (`flare.*.palette`) | `buildDca()` with `palette()`/`prefix()`/`suffix()` / `FilterElementBuildDcaEvent`, `FilterControlBuildDcaEvent`, `ListDriverBuildDcaEvent` (`flare.*.dca`) |
