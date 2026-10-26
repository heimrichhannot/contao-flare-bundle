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

### `ListBuildEvent`
Dispatched while a list is being built into its [`ListSpec`](../spec/specifications.md) — after the
driver's `buildList()` hook and before the config is resolved.
- **Use Case:** Adding or removing filters and setting canonical config overrides at runtime.
- **Class:** `HeimrichHannot\FlareBundle\Event\ListBuildEvent`
- **Properties:** `builder` (readonly `ListSpecBuilder` — `addFilter()`, `removeFilter()`, `set()`, ...)

### `FilterCollectedEvent`
Dispatched for every filter collected from the database, before it is added to the list.
- **Use Case:** Replacing or reconfiguring a filter based on its `tl_flare_filter` record.
- **Class:** `HeimrichHannot\FlareBundle\Event\FilterCollectedEvent`
- **Properties:** `filter` (mutable `Filter` — assign a replacement), `model` (readonly `FilterModel`)

### `FilterTransformerEvent`
Dispatched once per filter element or filter control class and type when its transformer map is configured.
- **Use Case:** Registering transformers for additional source classes, or replacing the element's or
  control's `FilterModel` transformer (re-registering a source class overrides the default).
- **Class:** `HeimrichHannot\FlareBundle\Event\FilterTransformerEvent`
- **Properties:** `transformers` (readonly `TransformerResolver`), `owner` (readonly
  `FilterComponentInterface` — the element or the control), `type` (readonly `string`)

### `ListTransformerEvent`
Dispatched once per list driver class when its transformer map is configured.
- **Use Case:** Registering transformers for additional source classes, or replacing the driver's
  `ListModel` transformer.
- **Class:** `HeimrichHannot\FlareBundle\Event\ListTransformerEvent`
- **Properties:** `transformers` (readonly `TransformerResolver`), `driver` (readonly
  `ListDriverInterface`)

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

### `FilterControlBuiltEvent`
Dispatched after a filter's [control](./filter-controls.md) declared its fields on the collect-only per-filter
builder, before the factory mounts them onto the root form (flat for `single()` fields without companions,
nested compound otherwise).
- **Use Case:** Adding, removing, or replacing one filter's form children (re-adding a same-named child
  overwrites it), adjusting the single-field declaration via `single()`/`getSingle()`, or preventing the
  filter from being mounted at all. Adding a child alongside a `single()` declaration switches the filter
  to the nested compound layout.
- **Class:** `HeimrichHannot\FlareBundle\Event\FilterControlBuiltEvent`
- **Properties:** `builder` (readonly `FilterFormBuilderInterface`), `scope` (readonly `FilterScope`)
- **API:** `cancel()` / `isCancelled()`

:::info[No per-element events]

`FilterElementBuildingEvent` and `FilterElementBuiltEvent` were removed together with the `buildFilter()`
element lifecycle they surrounded. To add or remove filters, use
[`ListBuildEvent`](#listbuildevent); to influence a filter's value, set `Filter::$value` programmatically; for
bespoke matching, write a custom filter element.

:::

## 6. Backend DCA Lifecycle

All three are dispatched before the collected configuration is applied to the live DCA. Use them to modify the
backend palette or fields of a type from outside its class — see
[Backend DCA Building](./dca-builder.md#5-modifying-another-types-dca).

### `ListDcaEvent`
Dispatched after a list driver's `buildDca()` ran.
- **Class:** `HeimrichHannot\FlareBundle\Event\ListDcaEvent`
- **Properties:** `builder` (readonly `DcaBuilder`), `context` (readonly `DcaContext`)

### `FilterElementDcaEvent`
Dispatched after a filter element's `buildDca()` and the framework fields (the control segment, `value`) ran.
- **Class:** `HeimrichHannot\FlareBundle\Event\FilterElementDcaEvent`
- **Properties:** `builder` (readonly `DcaBuilder`), `context` (readonly `DcaContext`)

### `FilterControlDcaEvent`
Dispatched after the selected filter control's `buildDca()` ran, before `FilterElementDcaEvent`.
- **Class:** `HeimrichHannot\FlareBundle\Event\FilterControlDcaEvent`
- **Properties:** `builder` (readonly `DcaBuilderInterface`, scoped to the control's palette segment),
  `context` (readonly `DcaContext`; `context->type` is the *element* type), `type` (readonly `string`, the
  control type)

## 7. Other Events

### `DetailsPageUrlGeneratedEvent`
Dispatched when a URL to a details (reader) page is generated.
- **Class:** `HeimrichHannot\FlareBundle\Event\DetailsPageUrlGeneratedEvent`

## 8. Named Events (Aliased Dispatch)

Several events are re-dispatched under a dynamic name after the base event, so listeners can target
one specific filter element type, form, or list type without checking inside a generic listener. The event
object is identical to the base event.

| Named event pattern | Base event |
|---|---|
| `flare.filter_element.{type}.transformers` | `FilterTransformerEvent` (filter elements) |
| `flare.filter_control.{type}.transformers` | `FilterTransformerEvent` (filter controls) |
| `flare.filter_control.{type}.built` | `FilterControlBuiltEvent` |
| `flare.form.{formName}.build` | `FormHarnessBuildEvent` |
| `flare.list.{type}.build` | `ListBuildEvent` |
| `flare.list.{type}.transformers` | `ListTransformerEvent` |
| `flare.filter_element.{type}.dca` | `FilterElementDcaEvent` |
| `flare.filter_control.{type}.dca` | `FilterControlDcaEvent` |
| `flare.list.{type}.dca` | `ListDcaEvent` |

How `{type}` is derived:

- **`flare.list.{type}.build` / `.transformers`** look the driver instance up in the
  `ListDriverRegistry` and fire once per type alias it is registered under (a driver registered under
  several aliases dispatches once per alias). An inline driver instance that is not registered fires no
  named events — the base event still fires.
- **`flare.filter_element.{type}.transformers` and `flare.filter_control.{type}.built`** use the filter's
  `type` — the registered *element* alias captured when the filter was created from one (e.g. via
  `FilterFactory`). Filters constructed around a plain element
  instance have no `type` and fire no named events.
- **`flare.filter_control.{type}.transformers`** uses the registered *control* type the control was resolved
  from.
- **`.dca`** events run in the backend and use the type stored on the record being edited — the element type
  (`type`), the control type (`control`) or the list type.

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
