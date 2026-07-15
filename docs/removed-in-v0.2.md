---
title: Removed in v0.2
sidebar_position: 11
---

# Removed in v0.2

Exhaustive list of APIs deleted in v0.2. For how to replace them, see
[Migrating from v0.1](./migrating-from-v0.1.md).

## Attributes

- `#[AsFilterInvoker]` (was deprecated in v0.1)
- `#[AsFilterCallback]`
- `#[AsListCallback]`
- `#[AsFlareCallback]` (internal base attribute)
- Parameters removed from kept attributes: `#[AsFilterElement]` lost `palette`, `formType`, and `method`
  (always-intrinsic elements now implement `Contract\FilterElement\IntrinsicContract` instead);
  `#[AsListType]` lost `palette`

## Classes

- `Specification\FilterDefinition`
- `Specification\Factory\FilterDefinitionFactory`
- `Specification\DataSource\FilterDataSourceInterface`
- `Collection\FilterDefinitionCollection` and `Collection\AbstractCollection` (the whole `src/Collection/` namespace)
- `Filter\FilterInvocation`
- `Filter\FilterInvokerInterface`, `Filter\ServiceMethodFilterInvoker`
- `Filter\Resolver\FilterInvokerResolver`, `Filter\Resolver\FilterValueResolver`
- `Registry\FilterInvokerRegistry`
- `Manager\FlareCallbackManager`
- `Registry\FlareCallbackRegistry`, `Registry\Descriptor\FlareCallbackDescriptor`
- `DataContainer\FlareCallbackContainerInterface`
- `Contract\Config\PaletteConfig`, `Contract\Config\ListItemProviderConfig`
- `Util\MethodInjector`

## Contracts

- `Contract\PaletteContract`
- `Contract\FilterElement\FormDataContract`
- `Contract\FilterElement\FormTypeOptionsContract`
- `Contract\FilterElement\HydrateFormContract`
- `Contract\FilterElement\IntrinsicValueContract`
- `Contract\FilterElement\RuntimeValueContract`

## Events

- `FilterElementInvokingEvent`
- `FilterElementInvokedEvent`
- `FilterElementFormTypeOptionsEvent`
- `FilterFormChildOptionsEvent`
- `PaletteEvent`
- `FilterDefinitionCreatedEvent`

### Named event aliases

- `flare.filter_element.{type}.invoking` → now `.building`
- `flare.filter_element.{type}.invoked` → now `.built`
- `flare.filter_element.{alias}.palette`, `flare.list.{type}.palette` → now `.dca`
- `flare.form.{parentFormName}.child.{formName}.options` → removed without a named replacement
  (use `flare.filter_element.{type}.form_built`)

## Twig

- `flare_enclosure_files()` — deprecated since v0.1, removed in v0.2; a replacement based on the virtual
  file system is planned for a future release

## Behavioral notes

- Filter element classes moved from `FilterElement\` to `Filter\Element\` and gained a `FilterElement`
  class-name suffix — a rename rather than a removal, but any `use` statement referencing the old FQCNs
  breaks. The backend `::TYPE` strings are unchanged, so **existing database records keep working**.
- Palettes for `tl_flare_filter` / `tl_flare_list` are no longer declared statically or via callback
  attributes; they are assembled at runtime by each type's `buildDca()`
  (see [Backend DCA Building](./dev/dca-builder.md)).
