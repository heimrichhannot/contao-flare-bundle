---
sidebar_position: 7
---

# Events Reference

Flare provides several events to hook into the list and reader lifecycle.

## 1. Query Modification

### `ModifyListQueryStructEvent`
Dispatched after the base query is built and filters are applied, but before the SQL is executed.
- **Use Case:** Global query manipulation (multi-tenancy, custom sorting).
- **Class:** `HeimrichHannot\FlareBundle\Event\ModifyListQueryStructEvent`

### `QueryBaseInitializedEvent`
Dispatched after the `TableAliasRegistry` and base `SqlQueryStruct` are initialized.
- **Use Case:** Registering additional table joins or setting up the initial query structure.
- **Class:** `HeimrichHannot\FlareBundle\Event\QueryBaseInitializedEvent`

## 2. Specification & Filter Collection Lifecycle

### `ListSpecificationCreatedEvent`
Dispatched when a `ListSpecification` object has been created from its data source (e.g. `tl_flare_list` model).
- **Use Case:** Modifying the list configuration dynamically at runtime.
- **Class:** `HeimrichHannot\FlareBundle\Event\ListSpecificationCreatedEvent`

### `FilterCollectedEvent`
Dispatched for every filter collected from the database, before it is added to the list specification.
- **Use Case:** Replacing or reconfiguring a filter based on its `tl_flare_filter` record.
- **Class:** `HeimrichHannot\FlareBundle\Event\FilterCollectedEvent`
- **Properties:** `filter` (mutable `Filter` — assign a replacement), `model` (readonly `FilterModel`)

## 3. List View Lifecycle

### `ListViewRenderEvent`
Dispatched just before the list template is rendered.
- **Use Case:** Injecting additional data into the Twig template or switching the template.
- **Class:** `HeimrichHannot\FlareBundle\Event\ListViewRenderEvent`
- **Helper:** Uses `ModifiesTemplateTrait`.

## 4. Reader Lifecycle

### `ReaderPageMetaEvent`
Dispatched when page metadata (title, description) is generated for a reader page.
- **Use Case:** Overriding SEO tags based on the displayed entity.
- **Class:** `HeimrichHannot\FlareBundle\Event\ReaderPageMetaEvent`

### `ReaderRenderEvent`
Dispatched just before the reader template is rendered.
- **Use Case:** Modifying the reader template or injecting data.
- **Class:** `HeimrichHannot\FlareBundle\Event\ReaderRenderEvent`
- **Helper:** Uses `ModifiesTemplateTrait`.

### `ReaderSchemaOrgEvent`
Dispatched when Schema.org JSON-LD data is generated for a reader page.
- **Use Case:** Adding or modifying structured data.
- **Class:** `HeimrichHannot\FlareBundle\Event\ReaderSchemaOrgEvent`

## 5. Filter Build Lifecycle

### `FilterElementBuildingEvent`
Dispatched before a filter element's `buildFilter()` runs.
- **Use Case:** Skipping specific filters conditionally, or inspecting the filter context.
- **Class:** `HeimrichHannot\FlareBundle\Event\FilterElementBuildingEvent`
- **API:** `getContext(): FilterContext`, `getBuilder(): FilterBuilderInterface`, `getData(): array`,
  `shouldBuild(): bool` / `setShouldBuild(bool)` — set to `false` to skip the element's `buildFilter()`.

### `FilterElementBuiltEvent`
Dispatched after a filter element's `buildFilter()` ran.
- **Use Case:** Reacting to applied filters or adding further filter-type calls.
- **Class:** `HeimrichHannot\FlareBundle\Event\FilterElementBuiltEvent`
- **API:** `getContext(): FilterContext`, `getBuilder(): FilterBuilderInterface`, `getData(): array`

## 6. Filter Form Lifecycle

### `FilterFormBuildEvent`
Dispatched while the filter form is being built, exposing the `FormBuilderInterface`.
- **Use Case:** Adding, removing, or reconfiguring form children of the filter form.
- **Class:** `HeimrichHannot\FlareBundle\Event\FilterFormBuildEvent`
- **Properties:** `listSpecification`, `formName`, `formBuilder`

### `FilterElementFormBuiltEvent`
Dispatched after a filter element built its form children on the per-filter compound sub-builder, before it
is mounted onto the root form.
- **Use Case:** Adding, removing, or replacing one filter's form children (re-adding a same-named child
  overwrites it), or preventing the sub-form from being mounted at all.
- **Class:** `HeimrichHannot\FlareBundle\Event\FilterElementFormBuiltEvent`
- **API:** `getBuilder(): FormBuilderInterface`, `getContext(): FilterContext`, `cancel()` / `isCancelled()`

## 7. Backend DCA Lifecycle

### `ElementDcaEvent`
Dispatched after an element's or list type's `buildDca()` ran, before the collected configuration is applied
to the live DCA.
- **Use Case:** Modifying the backend palette or fields of a list type or filter element from outside its
  class — see [Backend DCA Building](./dca-builder.md#5-modifying-another-types-dca).
- **Class:** `HeimrichHannot\FlareBundle\Event\ElementDcaEvent`
- **Properties:** `dca` (readonly `DcaBuilder`), `context` (readonly `DcaContext`)

## 8. Other Events

### `DetailsPageUrlGeneratedEvent`
Dispatched when a URL to a details (reader) page is generated.
- **Class:** `HeimrichHannot\FlareBundle\Event\DetailsPageUrlGeneratedEvent`

## 9. Named Events (Aliased Dispatch)

Several events are re-dispatched under a dynamic name after the base event, so listeners can target
one specific filter type, form, or list type without checking inside a generic listener. The event
object is identical to the base event.

| Named event pattern | Base event |
|---|---|
| `flare.filter_element.{type}.building` | `FilterElementBuildingEvent` |
| `flare.filter_element.{type}.built` | `FilterElementBuiltEvent` |
| `flare.filter_element.{type}.form_built` | `FilterElementFormBuiltEvent` |
| `flare.form.{formName}.build` | `FilterFormBuildEvent` |
| `flare.list_type.{type}.list_specification_created` | `ListSpecificationCreatedEvent` |
| `flare.filter_element.{type}.dca` | `ElementDcaEvent` (filter elements) |
| `flare.list.{type}.dca` | `ElementDcaEvent` (list types) |

Example — listen only to the build of the form named `my_form`:

```php
use HeimrichHannot\FlareBundle\Event\FilterFormBuildEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener('flare.form.my_form.build')]
class MyFormBuildListener
{
    public function __invoke(FilterFormBuildEvent $event): void
    {
        // e.g. $event->formBuilder->remove('...');
    }
}
```

The named dispatch happens at priority `-200` on the base event, so base-event listeners with a
higher priority run before any named listeners.
