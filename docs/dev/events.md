---
sidebar_position: 5
---

# Events Reference

Flare provides several events to hook into the list and reader lifecycle.

## 1. Query Modification

### `ModifyListQueryStructEvent`
Dispatched after the base query is built and filters are invoked, but before the SQL is executed.
- **Use Case:** Global query manipulation (multi-tenancy, custom sorting).
- **Class:** `HeimrichHannot\FlareBundle\Event\ModifyListQueryStructEvent`

### `QueryBaseInitializedEvent`
Dispatched after the `TableAliasRegistry` and base `SqlQueryStruct` are initialized.
- **Use Case:** Registering additional table joins or setting up the initial query structure.
- **Class:** `HeimrichHannot\FlareBundle\Event\QueryBaseInitializedEvent`

## 2. Specification & Definition Lifecycle

### `ListSpecificationCreatedEvent`
Dispatched when a `ListSpecification` object has been created from its data source (e.g. `tl_flare_list` model).
- **Use Case:** Modifying the list configuration dynamically at runtime.
- **Class:** `HeimrichHannot\FlareBundle\Event\ListSpecificationCreatedEvent`

### `FilterDefinitionCreatedEvent`
Dispatched when a `FilterDefinition` has been created.
- **Use Case:** Modifying filter element configuration.
- **Class:** `HeimrichHannot\FlareBundle\Event\FilterDefinitionCreatedEvent`

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

## 5. Filter Invocation Lifecycle

### `FilterElementInvokingEvent`
Dispatched before a filter is executed.
- **Use Case:** Skipping specific filters or swapping the invoker callback.
- **Class:** `HeimrichHannot\FlareBundle\Event\FilterElementInvokingEvent`

### `FilterElementInvokedEvent`
Dispatched after a filter has been executed.
- **Use Case:** Reacting to applied filters or inspecting the modified query builder.
- **Class:** `HeimrichHannot\FlareBundle\Event\FilterElementInvokedEvent`

## 6. Filter Form Lifecycle

### `FilterFormBuildEvent`
Dispatched while the filter form is being built, exposing the `FormBuilderInterface`.
- **Use Case:** Adding, removing, or reconfiguring form children of the filter form.
- **Class:** `HeimrichHannot\FlareBundle\Event\FilterFormBuildEvent`
- **Properties:** `listSpecification`, `formName`, `formBuilder`

### `FilterFormChildOptionsEvent`
Dispatched when the form type options for a single filter's child form are being gathered.
- **Use Case:** Modifying the options of one filter's form field (attributes, label, choices).
- **Class:** `HeimrichHannot\FlareBundle\Event\FilterFormChildOptionsEvent`
- **Properties:** `listSpecification`, `filterDefinition`, `parentFormName`, `formName`, `options`

## 7. Other Events

### `DetailsPageUrlGeneratedEvent`
Dispatched when a URL to a details (reader) page is generated.
- **Class:** `HeimrichHannot\FlareBundle\Event\DetailsPageUrlGeneratedEvent`

### `FilterElementFormTypeOptionsEvent`
Dispatched when the options for a filter's Symfony Form Type are being gathered.
- **Use Case:** Dynamically adding attributes or choices to the frontend filter form.
- **Class:** `HeimrichHannot\FlareBundle\Event\FilterElementFormTypeOptionsEvent`

### `PaletteEvent`
Dispatched when the backend palette for a list or filter configuration is being resolved.
- **Use Case:** Modifying the DCA palette of a list type or filter element from outside its class.
- **Class:** `HeimrichHannot\FlareBundle\Event\PaletteEvent`
- **API:** `getPaletteContainer()` (list or filter), `getPaletteConfig()`/`setPaletteConfig()`, `getPalette()`/`setPalette()`

## 8. Named Events (Aliased Dispatch)

Several events are re-dispatched under a dynamic name after the base event, so listeners can target
one specific filter type, form, or list type without checking inside a generic listener. The event
object is identical to the base event.

| Named event pattern | Base event |
|---|---|
| `flare.filter_element.{type}.invoking` | `FilterElementInvokingEvent` |
| `flare.filter_element.{type}.invoked` | `FilterElementInvokedEvent` |
| `flare.form.{formName}.build` | `FilterFormBuildEvent` |
| `flare.form.{parentFormName}.child.{formName}.options` | `FilterFormChildOptionsEvent` |
| `flare.list_type.{type}.list_specification_created` | `ListSpecificationCreatedEvent` |
| `flare.filter_element.{type}.palette` | `PaletteEvent` (filter palettes) |
| `flare.list.{type}.palette` | `PaletteEvent` (list palettes) |

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
