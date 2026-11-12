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
Dispatched while a list is being built into its [`ListCompound`](../spec/compounds.md) — after the
driver's `buildList()` hook and before the config is resolved.
- **Use Case:** Adding or removing filters and setting canonical config overrides at runtime.
- **Class:** `HeimrichHannot\FlareBundle\Event\ListDriverBuildListEvent`
- **Properties:** `builder` (readonly `ListCompoundBuilder` — `addFilter()`, `removeFilter()`, `set()`, ...)

### `FilterCollectedEvent`
Dispatched for every filter collected from the database, before it is added to the list.
- **Use Case:** Replacing or reconfiguring a filter based on its `tl_flare_filter` record.
- **Class:** `HeimrichHannot\FlareBundle\Event\FilterCollectedEvent`
- **Properties:** `filter` (mutable `FilterCompound` — assign a replacement), `model` (readonly `FilterModel`)

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

## 3. Entries

### `ListDriverEntriesEvent`
Dispatched once per non-empty entry collection, before the collection is sealed: the entries of a list page (on the
first access to `flare_list.entries`), a reader's entry (as a collection of one, when the reader is resolved) and the
members of a lookup (`flare_reader.lookup()`, or a lookup inside a listener). Never for an empty collection — a failed
list, an invalid submission, an empty page, a lookup without members — and not for the count. The same listener
therefore serves list and reader.
- **Use Case:** Attaching values to the entries — related records, computed values — that templates read as
  `entry.<name>`. See [Enriching Entries](./entries.md).
- **Class:** `HeimrichHannot\FlareBundle\Event\ListDriverEntriesEvent`
- **Named event:** `flare.list_driver.{type}.entries`
- **Properties:** `list` (readonly `ListCompound` — e.g. target a single list by its `source`), `intent` (readonly
  `IntentInterface` — `InteractiveIntent` for a list page, `ValidationIntent` for the reader and lookups), `entries`
  (readonly `EntryCollection`)
- **API:** `attach(string $name, \Closure $load)` proxies `$event->entries->attach()`: phase 1,
  `$load(EntryCollection $entries, EntryLookupInterface $lookup)`, runs once per collection on the first read of
  `$name` on any of its entries and returns the picker, a `\Closure(Entry): mixed` whose result is memoized per entry.
  There is no `$event->lookup()`: the list's lookup exists only as phase 1's second argument. A value per entry is
  attached on the entry itself: `foreach ($event->entries as $entry) { $entry->attach($name, $value); }`.

## 4. List View Lifecycle

### `ListViewRenderEvent`
Dispatched just before the list template is rendered.
- **Use Case:** Injecting additional data into the Twig template or switching the template.
- **Class:** `HeimrichHannot\FlareBundle\Event\ListViewRenderEvent`
- **Helper:** Uses `ModifiesTemplateTrait`.

## 5. Reader Lifecycle

The reader events carry the reader's `Entry` as `entry` — no Contao model; if a Contao API needs one, see
[Enriching Entries](./entries.md#8-contao-apis-that-need-a-model).

### `ReaderPageMetaEvent`
Dispatched once per reader element and auto-item, when the page metadata of its entry is first needed: by the
breadcrumb, which renders before the reader on most pages, else by the reader. Breadcrumb title and `<title>` are
therefore the same.
- **Use Case:** Overriding SEO tags (title, description, canonical URL, robots) based on the displayed entry.
- **Class:** `HeimrichHannot\FlareBundle\Event\ReaderPageMetaEvent`
- **Properties:** `contentModel` (readonly `ContentModel`, the reader element), `entry` (readonly `Entry`), `list`
  (readonly `ListCompound`), `pageMeta` (`ReaderPageMeta` — change it or assign another)

### `ReaderRenderEvent`
Dispatched just before the reader template is rendered.
- **Use Case:** Modifying the reader template or injecting data.
- **Class:** `HeimrichHannot\FlareBundle\Event\ReaderRenderEvent`
- **Properties:** `contentModel` (readonly `ContentModel`), `intent` (readonly `IntentInterface`), `entry` (readonly
  `Entry`), `list` (readonly `ListCompound`); the template via `getTemplate()` / `setTemplate()`
- **Helper:** Uses `ModifiesTemplateTrait`.

The event has no `pageMeta`: change the page metadata in `ReaderPageMetaEvent`. It runs once and possibly earlier,
for the breadcrumb — a change at render time would make the breadcrumb and the `<title>` differ.

### `ReaderSchemaOrgEvent`
Dispatched when `flare_schema_org()` generates Schema.org JSON-LD data for an entry.
- **Use Case:** Adding or modifying structured data.
- **Class:** `HeimrichHannot\FlareBundle\Event\ReaderSchemaOrgEvent`
- **Properties:** `list` (readonly `ListCompound`), `entry` (readonly `Entry`), `data` (`array`, writable)

## 6. Filter Form Lifecycle

### `FormHarnessBuildEvent`
Dispatched after every filter mounted onto the root form, before the form is built, exposing the
`FormBuilderInterface`.
- **Use Case:** Adding, removing, or reconfiguring form children of the filter form.
- **Class:** `HeimrichHannot\FlareBundle\Event\FormHarnessBuildEvent`
- **Properties:** `list` (readonly `ListCompound`), `formName` (readonly), `formBuilder`

### `FilterControlBuildFormEvent`
Dispatched after a filter's [control](./filter-controls.md) `buildForm()` declared its fields on the collect-only per-filter
builder, before the factory mounts them onto the root form (flat for a `single()` field, a nested Symfony
compound form for children).
- **Use Case:** Adding, removing, or replacing one filter's form children (re-adding a same-named child
  overwrites it), adjusting the single-field declaration via `single()`/`getSingle()`, or preventing the
  filter from being mounted at all. Declaring a single field and children at once is not supported: the
  filter cannot build its form, and the list view fails (fail closed).
- **Class:** `HeimrichHannot\FlareBundle\Event\FilterControlBuildFormEvent`
- **Properties:** `builder` (readonly `FilterFormBuilderInterface`), `context` (readonly `ControlContext`)
- **API:** `cancel()` / `isCancelled()`

:::info[No per-element events]

`FilterElementBuildingEvent` and `FilterElementBuiltEvent` were removed together with the `buildFilter()`
element lifecycle they surrounded. To add or remove filters, use
[`ListDriverBuildListEvent`](#listdriverbuildlistevent); to influence a filter's value, set `FilterCompound::$value` programmatically; for
bespoke matching, write a custom filter element.

:::

## 7. Backend DCA Lifecycle

All three are dispatched before the collected configuration is applied to the live DCA. Use them to modify the
backend palette or fields of a type from outside its class — see
[Backend DCA Building](./dca-builder.md#5-modifying-another-types-dca).

### `ListDriverBuildDcaEvent`
Dispatched after a list driver's `buildDca()` ran.
- **Class:** `HeimrichHannot\FlareBundle\Event\ListDriverBuildDcaEvent`
- **Properties:** `builder` (readonly `DcaBuilderInterface`), `context` (readonly `DcaContext`)

### `FilterElementBuildDcaEvent`
Dispatched after a filter element's `buildDca()` and the framework fields (the control segment, `value`) ran.
- **Class:** `HeimrichHannot\FlareBundle\Event\FilterElementBuildDcaEvent`
- **Properties:** `builder` (readonly `DcaBuilderInterface`), `context` (readonly `DcaContext`)

### `FilterControlBuildDcaEvent`
Dispatched after the selected filter control's `buildDca()` ran, before `FilterElementBuildDcaEvent`.
- **Class:** `HeimrichHannot\FlareBundle\Event\FilterControlBuildDcaEvent`
- **Properties:** `builder` (readonly `DcaBuilderInterface`, scoped to the control's palette segment),
  `context` (readonly `DcaContext`; `context->type` is the *element* type), `type` (readonly `string`, the
  control type)

## 8. Other Events

### `ReaderUrlGeneratedEvent`
Dispatched when the URL to a reader (details) page is generated for an entry — once per entry, on the first read of
`entry.to`; `url` can be replaced.
- **Class:** `HeimrichHannot\FlareBundle\Event\ReaderUrlGeneratedEvent`
- **Properties:** `entry` (readonly `Entry`), `autoItem` (`string`), `page` (`PageModel`, the reader page), `url`
  (`string`)

`autoItem` is the URL text of the entry's auto-item value: the stored value with Contao's input encoding reverted,
decoded and **never percent-encoded** — percent-encoding is the router's job. A listener that builds another URL from
it passes it to the router as is; encoding it again would break the round trip back to the entry.

## 9. Named Events (Aliased Dispatch)

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
| `flare.list_driver.{type}.entries` | `ListDriverEntriesEvent` |

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
- **`flare.list_driver.{type}.entries`** uses the list's type (`ListCompound::$type`); a list with an empty type fires
  no named event.
- **`flare.filter_element.{type}.transformers`** uses the element's type (`FilterCompound::$element->type`) — the registered *element* type
  captured when the filter was created from one (e.g. via `FilterCompoundFactory`).
- **`flare.filter_control.{type}.transformers` and `.build_form`** use the registered *control* type the control
  was resolved from (`FilterCompound::$control->type`). For a control passed to `FilterCompoundFactory` as an instance, the type
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
