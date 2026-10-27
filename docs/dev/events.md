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

## 2. List Build & Filter Collection Lifecycle

### `ListDriverBuildListEvent`
Dispatched while a list is being built into its [`ListSpec`](../spec/specifications.md) — after the
driver's `buildList()` hook and before the config is resolved.
- **Use Case:** Adding or removing filters and setting canonical config overrides at runtime.
- **Class:** `HeimrichHannot\FlareBundle\Event\ListDriverBuildListEvent`
- **Properties:** `builder` (readonly `ListSpecBuilder` — `addFilter()`, `removeFilter()`, `set()`, ...)

### `FilterCollectedEvent`
Dispatched for every filter collected from the database, before it is added to the list.
- **Use Case:** Replacing or reconfiguring a filter based on its `tl_flare_filter` record.
- **Class:** `HeimrichHannot\FlareBundle\Event\FilterCollectedEvent`
- **Properties:** `filter` (mutable `Filter` — assign a replacement), `model` (readonly `FilterModel`)

### `FilterElementConfigureTransformersEvent`
Dispatched once per filter element class and type when its transformer map is configured, after the
element's `configureTransformers()`.
- **Use Case:** Setting transformers for additional source classes, or replacing the element's `FilterModel`
  transformer (setting a source class again replaces the default).
- **Class:** `HeimrichHannot\FlareBundle\Event\FilterElementConfigureTransformersEvent`
- **Properties:** `transformers` (readonly `TransformerMap`), `element` (readonly `FilterElementInterface`),
  `type` (readonly `string`)

### `FilterControlConfigureTransformersEvent`
The same for a [filter control](./filter-controls.md), after the control's `configureTransformers()`.
- **Class:** `HeimrichHannot\FlareBundle\Event\FilterControlConfigureTransformersEvent`
- **Properties:** `transformers` (readonly `TransformerMap`), `control` (readonly `FilterControlInterface`),
  `type` (readonly `string`)

### `ListDriverConfigureTransformersEvent`
The same for a list driver, after the driver's `configureTransformers()`.
- **Use Case:** Setting transformers for additional source classes, or replacing the driver's
  `ListModel` transformer.
- **Class:** `HeimrichHannot\FlareBundle\Event\ListDriverConfigureTransformersEvent`
- **Properties:** `transformers` (readonly `TransformerMap`), `driver` (readonly
  `ListDriverInterface`), `type` (readonly `string`)

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

## 5. Filter Form Lifecycle

### `FormHarnessBuildEvent`
Dispatched after every filter mounted onto the root form, before the form is built, exposing the
`FormBuilderInterface`.
- **Use Case:** Adding, removing, or reconfiguring form children of the filter form.
- **Class:** `HeimrichHannot\FlareBundle\Event\FormHarnessBuildEvent`
- **Properties:** `list` (readonly `ListSpec`), `formName` (readonly), `formBuilder`

### `FilterControlBuildFormEvent`
Dispatched after a filter's [control](./filter-controls.md) `buildForm()` declared its fields on the collect-only per-filter
builder, before the factory mounts them onto the root form (flat for `single()` fields without companions,
nested compound otherwise).
- **Use Case:** Adding, removing, or replacing one filter's form children (re-adding a same-named child
  overwrites it), adjusting the single-field declaration via `single()`/`getSingle()`, or preventing the
  filter from being mounted at all. Adding a child alongside a `single()` declaration switches the filter
  to the nested compound layout.
- **Class:** `HeimrichHannot\FlareBundle\Event\FilterControlBuildFormEvent`
- **Properties:** `builder` (readonly `FilterFormBuilderInterface`), `scope` (readonly `ControlScope`)
- **API:** `cancel()` / `isCancelled()`

:::info[No per-element events]

`FilterElementBuildingEvent` and `FilterElementBuiltEvent` were removed together with the `buildFilter()`
element lifecycle they surrounded. To add or remove filters, use
[`ListDriverBuildListEvent`](#listdriverbuildlistevent); to influence a filter's value, set `Filter::$value` programmatically; for
bespoke matching, write a custom filter element.

:::

## 6. Backend DCA Lifecycle

All three are dispatched before the collected configuration is applied to the live DCA. Use them to modify the
backend palette or fields of a type from outside its class — see
[Backend DCA Building](./dca-builder.md#5-modifying-another-types-dca).

### `ListDriverBuildDcaEvent`
Dispatched after a list driver's `buildDca()` ran.
- **Class:** `HeimrichHannot\FlareBundle\Event\ListDriverBuildDcaEvent`
- **Properties:** `builder` (readonly `DcaBuilder`), `context` (readonly `DcaContext`)

### `FilterElementBuildDcaEvent`
Dispatched after a filter element's `buildDca()` and the framework fields (the control segment, `value`) ran.
- **Class:** `HeimrichHannot\FlareBundle\Event\FilterElementBuildDcaEvent`
- **Properties:** `builder` (readonly `DcaBuilder`), `context` (readonly `DcaContext`)

### `FilterControlBuildDcaEvent`
Dispatched after the selected filter control's `buildDca()` ran, before `FilterElementBuildDcaEvent`.
- **Class:** `HeimrichHannot\FlareBundle\Event\FilterControlBuildDcaEvent`
- **Properties:** `builder` (readonly `DcaBuilderInterface`, scoped to the control's palette segment),
  `context` (readonly `DcaContext`; `context->type` is the *element* type), `type` (readonly `string`, the
  control type)

## 7. Other Events

### `ReaderUrlGeneratedEvent`
Dispatched when a URL to a reader (details) page is generated; `url` can be replaced.
- **Class:** `HeimrichHannot\FlareBundle\Event\ReaderUrlGeneratedEvent`

## 8. Named Events (Aliased Dispatch)

Several events are re-dispatched under a dynamic name after the base event, so listeners can target
one specific filter element type, form, or list type without checking inside a generic listener. The event
object is identical to the base event.

| Named event pattern | Base event |
|---|---|
| `flare.filter_element.{type}.transformers` | `FilterElementConfigureTransformersEvent` |
| `flare.filter_control.{type}.transformers` | `FilterControlConfigureTransformersEvent` |
| `flare.filter_control.{type}.build_form` | `FilterControlBuildFormEvent` |
| `flare.form.{formName}.build` | `FormHarnessBuildEvent` |
| `flare.list_driver.{type}.build_list` | `ListDriverBuildListEvent` |
| `flare.list_driver.{type}.transformers` | `ListDriverConfigureTransformersEvent` |
| `flare.filter_element.{type}.dca` | `FilterElementBuildDcaEvent` |
| `flare.filter_control.{type}.dca` | `FilterControlBuildDcaEvent` |
| `flare.list_driver.{type}.dca` | `ListDriverBuildDcaEvent` |

Naming: an event following a hook is named `{Component}{Hook}Event` after that hook (`buildForm()` →
`FilterControlBuildFormEvent`). The named event is `flare.{component}.{type}.{suffix}`: the prefix states whose
type `{type}` is — `filter_element` always a registered filter element type, `filter_control` a registered
filter control type, `list_driver` a list driver type — and the suffix stays short (`dca`, `transformers`)
unless that would be unclear (`build_list`, `build_form`).

How `{type}` is derived:

- **`flare.list_driver.{type}.build_list` / `.transformers`** look the driver instance up in the
  `ListDriverRegistry` and fire once per type alias it is registered under (a driver registered under
  several aliases dispatches once per alias). An inline driver instance that is not registered fires no
  named events — the base event still fires.
- **`flare.filter_element.{type}.transformers`** uses the element's type (`Filter::$element->type`) — the registered *element* type
  captured when the filter was created from one (e.g. via `FilterFactory`).
- **`flare.filter_control.{type}.transformers` and `.build_form`** use the registered *control* type the control
  was resolved from (`Filter::$control->type`). For a control passed to `FilterFactory` as an instance, the type
  is its class name.
- **`.dca`** events run in the backend and use the type stored on the record being edited — the element type
  (`type`), the control type (`control`) or the list driver type.

Example — listen only to the build of the form named `my_form`:

```php
use HeimrichHannot\FlareBundle\Event\FormHarnessBuildEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener('flare.form.my_form.build')]
class MyFormBuildListener
{
    public function __invoke(FormHarnessBuildEvent $event): void
    {
        // e.g. $event->formBuilder->remove('...');
    }
}
```

The named dispatch happens at priority `-200` on the base event, so base-event listeners with a
higher priority run before any named listeners.
