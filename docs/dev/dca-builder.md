---
title: Backend DCA Building
sidebar_position: 5
---

# Backend DCA Building

List drivers and filter elements assemble their backend palettes and field tweaks at runtime by implementing
`Contract\DcaContract`:

```php
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;

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

## 5. Modifying Another Type's DCA

To adjust the backend configuration of a type you don't own, listen to its DCA event (readonly properties
`$builder` and `$context`). Each is dispatched after the type's own `buildDca()` ran, under a name specific to
the type:

- `FilterElementDcaEvent` as `flare.filter_element.{type}.dca` for filter elements (on `tl_flare_filter`)
- `FilterControlDcaEvent` as `flare.filter_control.{type}.dca` for filter controls (on `tl_flare_filter`); its
  builder is scoped to the control's palette segment, and `$type` holds the control type
- `ListDcaEvent` as `flare.list.{type}.dca` for list drivers (on `tl_flare_list`)

```php
use HeimrichHannot\FlareBundle\Event\FilterElementDcaEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener('flare.filter_element.flare_bool.dca')]
public function __invoke(FilterElementDcaEvent $event): void
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
| `#[AsFilterCallback(TYPE, 'fields.myField.options')]` | `$builder->field('myField')->options(...)` — or `FilterElementDcaEvent` on `flare.filter_element.{type}.dca` for foreign types |
| `#[AsFilterCallback(TYPE, 'fields.myField.load')]` / `'fields.myField.save'` | `$builder->field('myField')->load(...)` / `->save(...)` |
| `#[AsFilterCallback(TYPE, 'config.onload')]` / list `config.onload` | `buildDca()` (own type) / `FilterElementDcaEvent` or `ListDcaEvent` listener (foreign type) |
| `PaletteContract::getPalette()` / `PaletteEvent` (`flare.*.palette`) | `buildDca()` with `palette()`/`prefix()`/`suffix()` / `FilterElementDcaEvent`, `FilterControlDcaEvent`, `ListDcaEvent` (`flare.*.dca`) |
