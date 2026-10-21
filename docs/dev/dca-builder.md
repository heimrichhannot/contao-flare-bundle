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

public function buildDca(DcaBuilderInterface $dca, DcaContext $context): void
{
    $dca->palette('{filter_legend},elm_fieldGeneric');

    // Lazy options against the filter's target table
    $dca->field('elm_fieldGeneric')
        ->options(fn (): array => $this->getFieldGenericOptions($context->getTargetTable()));
}
```

Note how expensive option lookups stay lazy, and that the palette can react to the record's current state
(`$context->filterModel`). A filter record's palette is the element's segment plus the segment of the selected
[control](./filter-controls.md), which is composed automatically; the field is prefixed `elm_` (element) or
`ctl_` (control).

## 5. Modifying Another Type's DCA

To adjust the backend configuration of an element or list driver you don't own, listen to `ElementDcaEvent`
(`HeimrichHannot\FlareBundle\Event\ElementDcaEvent`, readonly properties `$dca` and `$context`). It is
dispatched after the element's own `buildDca()` ran, under a name specific to the type:

- `flare.filter_element.{type}.dca` for filter elements (on `tl_flare_filter`)
- `flare.list.{type}.dca` for list drivers (on `tl_flare_list`)

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
