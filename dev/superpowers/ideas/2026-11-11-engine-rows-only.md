# Idea: the engine works with rows only, models are made at the Contao boundary

Status: superseded by the design `dev/superpowers/specs/2026-11-11-engine-entries-design.md` on branch
`v0.2-entries` (2026-11-11), which replaces the "Direction" below and answers the open questions.

Noted during the 4th review of the query refactor (`dev/superpowers/specs/2026-11-04-query-architecture-design.md`,
§2 "Projection and the model registry", D28). It goes in **its own PR on `v0.2`, before `v0.2-query`** (user's
decision). Breaking the view API is fine (user's decision; v0.2 is unreleased).

## Observation

The engine views turn rows into Contao models through `Engine/View/HandlesModelsTrait`, and both of its paths read
Contao's `Model\Registry` first:

- `fetchModel()` (used by `ValidationView::getModel()` / `getModelByAutoItem()`) returns a model that is already in the
  registry if its `published` field is set, **without running the list's lookup query at all**.
- `createModelsFromEntries()` (used by `InteractiveView::getModels()`) replaces each fetched row with a model that is
  already in the registry under the same id.

### Bug today: the reader shows entries that are not in its list

The reader's lookup is supposed to decide membership: `ReaderController` comments that a failed list means
"membership cannot be decided, so the reader answers 5xx, never 404 or the entry". The registry shortcut skips that
decision. Contao registers every model under its id and, through `Model::onRegister()`, under the values of its unique
fields (e.g. `alias`), so the shortcut also hits for an auto-item lookup.

Scenario: before the reader renders, something on the same page loads the record — a Contao news module in a side
column, an insert tag, or a FLARE list (which registers its models itself in `createModelsFromEntries()`). The reader
then shows that entry although its list's filters exclude it (another archive, a fixed filter), or although it is
outside its `start`/`stop` window (only `published` is checked). Expected: 404. `Contao/Hook/BreadcrumbHook` does the
same lookup and has the same bypass.

### Gap for the query refactor: mapped projections

The query refactor lets a driver choose a projection with mapped or partial rows (multilingual:
`IFNULL(translation.x, main.x) AS x`). The spec only says the views must not *register* such rows. But both registry
*reads* would still replace a mapped row with a cached physical model, so a translated list would show untranslated
values. The views also have no way to know whether a row comes from a non-default projection.

## Contao facts that constrain the design

Checked in `vendor/contao/core-bundle/contao/library/Contao/Model.php` and `Model/Registry.php`:

- `new $modelClass($row)` **registers the model itself** (constructor → `Registry::register()`), and `register()`
  throws if the registry already holds an instance for that primary key. So "a model without the registry" is not
  `new $class($row)`.
- A detached model is `(new $class())->setRow($row)`. Its `save()` would INSERT a new row, because `save()` only
  UPDATEs registered models. `preventSaving(false)` blocks saving (`save()` then throws) and detaches it.
- `onRegister()` registers aliases for every unique field. That is why alias lookups hit the registry.

## Direction

1. **The engine views deliver rows only.** `InteractiveView` keeps `getEntries()`; `ValidationView` offers
   `getEntry($id)` / `getEntryByAutoItem($autoItem)`. `HandlesModelsTrait` and the views' `getModel*()` leave the
   engine. There is never a lookup without a query.
2. **A Contao-side service turns a confirmed row into a model** (in `src/Contao/`, e.g. under `Contao/Factory/`; final
   name in the user's naming style). Two rules:
   - **default projection** (complete `main` rows): the registry instance if there is one, otherwise create and
     register. This is safe now, because the list's query has already confirmed membership, and it keeps Contao's
     identity map consistent (`getRelated()`, comments, other modules see the same instance).
   - **non-default projection** (mapped or partial rows): a detached model with `preventSaving`. It never enters the
     registry and is never read from it.

   Today only the default projection exists. This PR implements the default rule and a defined way for the projection
   status to reach the service (e.g. on the loader's result). The query refactor then sets it and adds the detached
   rule — or this PR already implements both rules behind the flag (builder's call).
3. **The `published` check in `fetchModel()` goes.** Membership, publication included, is decided by the list's
   filters in the query.

Rejected alternative: always detached models. That would be one rule, but each record would exist twice per request
(FLARE's instance and Contao's), and a userland `save()` on the display model would throw.

## What it touches

Model consumers that need a model from the boundary service instead of a view:

- `Controller/ContentElement/ReaderController`: the display model for `ReaderPageMetaEvent`, `ReaderRenderEvent`,
  `ReaderRequestAttributeFactory`, `EntityCacheTags::tagWith()` and the template variable `model`.
- `Contao/Hook/BreadcrumbHook`: `getModelByAutoItem()` → `ReaderPageMetaEvent`.
- The listeners on the display model: `EventListener/Reader/GenericReaderPageMetaListener`,
  `ReaderPageMetaTitleListener`, `Integration/ContaoNews/…/NewsReaderPageMetaListener`,
  `Integration/ContaoCalendar/…/EventsReaderPageMetaListener`, `Integration/ContaoComments/…/ContaoCommentsListener`
  (`getRelated('pid')`), `Integration/Terminal42Languages/…/ChangelanguageListener`.
- `Twig/Runtime/FlareRuntime`: `getTlContent(Model)`, `getEnclosure(Model|array)`, `getSchemaOrg(?Model)` →
  `ReaderSchemaOrgEvent` (News/Events listeners call `News::getSchemaOrgData()` etc., which need real models).
- `Engine/View/LinksToReaderTrait::to()` (`flare_list.to(entry.id)`): resolves a whole model only so that
  `Reader/ReaderUrlGenerator::generate(Model)` can read the auto-item field. A row is enough for that. Note that
  `ReaderUrlGeneratedEvent::$model` is public API; the bundled `ChangelanguageListener` does not read it.
- Templates: `content_element/flare_listview.html.twig` (`flare_list.model(entry.id)`) and
  `content_element/flare_reader.html.twig` (`model.row`).

Not affected: `Filter/Value/Choice/ContaoModelChoice`, `Form/ChoicesBuilder` and `ArchiveFilterElement`. They handle
models of other tables (filter choices), not list entries.

After the change, `src/Engine/` uses Contao only in the intent factories (`ContentModel`) and
`Intent/ReaderUrlConfigCreatorTrait` (`PageModel`). Moving them to `src/Contao/Factory/` (like
`ListCompoundBuilderFactory`) would be a later step.

## Open questions

- How templates get a model for an entry once the view has none: a Twig function delegating to the boundary service,
  or the Contao side wrapping the view?
- Should the reader URL be generated from a row (`generate(array $row)`), and what does `ReaderUrlGeneratedEvent`
  then carry: the row, or a lazily made model?
- Request cache for validation lookups. The breadcrumb usually renders before the reader (layout order) and builds the
  same list and lookup. Today the registry hit makes the second lookup free; without it the query runs twice. A
  request-scoped cache keyed by list × auto-item would avoid that, and a later `ChangelanguageListener` lookup could
  share it.
- Does the reader keep reading the registry instance for the default projection after the query (recommended above),
  or does it always build its own?

## Testing

- Reader and breadcrumb with an already populated registry (by id and by alias): an entry outside the list's filters
  → 404 / no breadcrumb title. An entry inside → the registry instance is returned.
- List entries with a populated registry: the default projection returns the registry instance. A row flagged as a
  non-default projection keeps its own values, is not registered, and `save()` throws.
- No test needs the registry's private state; populate it through Contao's public API.
