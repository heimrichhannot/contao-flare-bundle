# Design: exclusive filters — a filter that narrows an output, not the whole list

Status: approved by the user 2026-11-13. Checked against `v0.2` at `680d4add` (PR #55 merged) on 2026-11-16 and updated
where the code had moved; E1–E12 unchanged. Extended on 2026-11-17 after the review with E13–E24 (the inspection event
and warnings, §3; how the calendar filter reads its period and its backend fields, §4; migration row 3, §6;
`ListCompound::hash()`, §2, and `ConfigurationIntent`, §5, removed), and after a second review (Opus, Astra) with
E25–E28 (the migration's baseline, order and marker, empty and inverted periods, unreadable text in the migration).
After a third review (2026-11-18), §6 was simplified (E29: nothing moves into the stored value; E21 superseded; amended
by E31 during implementation: a row with `is_limited` off becomes exclusive to the list view) and legends without fields
are dropped when a palette is applied (E30). A fourth review (2026-11-18) tied the migration to `elm_isLimited` (E26)
and reads leftovers as v0.1 did (E28). Its own PR on `v0.2` (branch `v0.2-exclusive-filters`): after #55, before the
query architecture refactor (`2026-11-04-query-architecture-design.md`, on branch `v0.2-query`, not yet on `v0.2`),
which assumes it (its D40).

## Intent

A list view and its reader do not always show the same entries. The common case is a list of events: the list view
shows what is coming, the reader still finds an event that has passed (as Contao's own event reader does). Formally
these are **two lists** (user): the entries of the one are bounded by a period, the entries of the other are not.

Today exactly one filter element produces that difference, and it does so by asking for the intent:
`Filter/Element/CalendarCurrentFilterElement::buildEffect()` drops its configured window when
`$context->intent instanceof ValidationIntent` and `is_limited` is off ("unlimited defaults only shape the initial
display"). It is the only reader of an intent among all elements and controls. The user's judgement: that is an error
in the element's setup, and it contradicts the rule agreed for the query design — **the list defines its entries, the
intent only order, section and embedding.** The element also mixes two purposes behind its `is_limited` checkbox: a
hard bound of the period entries must lie in, and a preselection the visitor can move with a control.

What the user wants instead:

- The difference between the two lists is **configured on the filter**, for every filter type, as an expert setting:
  a filter applies either to the whole list, or exclusively to the outputs the editor ticks.
- No element and no control sees the intent.
- New outputs must be right without touching existing filters: a sitemap (a later PR) is generated from the whole
  list — what the reader sees —, not from what the list view shows.

## Goals

1. An expert setting on every filter row: **apply exclusively** (off by default), and with it a list of **outputs**
   to tick. Today's only output is the list view; sitemap and export follow in later PRs.
2. Two lists from one configuration: the **whole list** (the filters that are not exclusive) and the list of an
   output (those plus the filters exclusive to it). The reader and the lookup run on the whole list, the list view on
   its output's list, its count with it.
3. `flare_calendar_current` with one rule and without `is_limited`: the configured period is always a bound of that
   filter, the stored value is the preselection.
4. `ElementContext` and `ControlContext` without an intent.
5. No existing list becomes wider; the exceptions, and the lists that become narrower, are named under Migration.

## Non-goals

- The sitemap and export outputs themselves. Only the storage and the semantics are prepared: a new output is a new
  option, existing rows need no migration.
- Outputs registered by userland.
- Per-content-element filters, derived lists, or any other way to have several list views of one list differ (the
  approaches considered below).
- Changing how a control's value is treated: it stays a matter of the list view (the visitor's values and the
  initial value never reach the reader).
- The query assembly (`ListQueryDirector`, loaders): the query refactor's business. This PR only changes which list
  they are handed.

## Anti-goals

- An output that can show an entry the reader does not find. The reader is the whole list; every output is a subset.
  So "reader" is no option to tick, and neither is the lookup (it follows the reader) nor the count (it follows the
  list view).
- An element, control or framework step that decides by the intent's type which filters or conditions apply.
- A new output that silently inherits list-view filters (the reason for "exclusive" instead of "do not apply in").

## Constraints

- Project conventions (`AGENTS.md`): vocabulary *Compound / Effect / Context / Intent*; the user's naming taste (short,
  sober nouns); the bundle catches `\Exception`, not `\Throwable`; programming errors are `\Error`s.
- Migrations never delete or drop; they update values only; SQL for MySQL and MariaDB.
- Testable through public API. `v0.2` is unreleased: breaking changes are fine, no BC shims.
- Backend fields via `mvo/contao-group-widget` are already in use, but a plain checkbox list suffices here.

## Approaches

Every list-view entry must be findable in the reader, so the list view's list is always the reader's list plus
narrowing filters. The approaches differ in where those filters live.

| Approach | Trade-off |
|---|---|
| **Filter with exclusive outputs** (chosen) — one list configuration, each filter row says whether it bounds the whole list or only the ticked outputs | Smallest change, works for every filter type. Two list views of one list that differ still need two configurations or a control |
| A derived list — "current events" refers to "all events" as its base and adds filters | Literally two lists, several views possible. Largest change: inherited filters in the backend, the inspector, two records to maintain |
| The content element narrows — the list describes the whole set, the list-view element adds its own filters | One list serves several views. The rich filter backend of `tl_flare_filter` would have to exist on `tl_content` |

Rejected early: two independent lists (nothing guarantees the reader finds what the view shows; shared filters
drift), a reader that hides filters (the list view's lookup does not know the reader element's setting), a per-filter
switch per intent (of four intents one choice is meaningful, two break "count = entries" and "list ⊆ reader"), and
"do not apply in: reader" (a later sitemap would inherit every list-view filter).

The user chose the first, in the form "apply exclusively" + outputs.

## Design

### 1. The setting

Two fields on `tl_flare_filter`, in the palette suffix (`__suffix__`, `{expert_legend:hide},alias`) under the expert
legend, for every filter type. A type can change the suffix with `DcaBuilderInterface::suffix()` — from an element's
`buildDca()`, or a control's, which `ControlDcaBuilder` forwards to the same builder: a string replaces it, and so
hides the two fields together with `alias` (and the rest of the suffix); a callable transforms it and keeps whatever
it does not remove. No bundled filter element or control calls it (the one bundled caller, `EventsListDriver`, works on
`tl_flare_list`). Hiding a field does not clear it: the factory reads the stored columns whatever the palette shows,
as `FilterModel::generateFormName()` reads `alias`. A filter of such a type keeps a setting stored earlier (e.g.
before a change of its type) without the editor seeing it; that is the type author's choice and needs no guard
(user). Prefixes and suffixes are reworked later anyway. A legend left without fields, such as a bare
`{expert_legend:hide}`, is dropped when `DcaBuilder::apply()` writes the palette into the DCA, and only there (user,
2026-11-18): `Str::mergePalettes()` keeps it, so a palette manipulator that runs before the hook can still add fields
under a legend a palette declares empty (e.g. `{advanced_legend:hide}` in `tl_flare_list`'s suffix).

- a checkbox (working name `exclusive`; label "Apply exclusively" / „Exklusiv anwenden“), off by default, opening
- a checkbox list (working name `outputs`) of the outputs. Today one option: the list view. The field stores the
  ticked keys as a list, so later options need no migration.

Meaning:

| Stored | The filter applies |
|---|---|
| not exclusive | everywhere: it bounds the whole list |
| exclusive, outputs ticked | only in those outputs; not in the reader, the lookup or any other output |
| exclusive, nothing ticked | nowhere |

The outputs are a closed set in code (working name `Output`, a backed enum; today `ListView`). Reader, lookup and
count are no outputs.

### 2. Two lists from one compound

`FilterCompound` carries the setting (working name `$outputs`: `null` = not exclusive, else the list of outputs).
`Contao/Factory/FilterCompoundFactory::create(FilterModel)` reads it; a filter created in
code (`Filter/Factory/FilterCompoundFactory`, engine mods' `withFilter()`) is not exclusive unless it says so — through
a parameter of the core factory's `create()` or a `withOutputs()` (builder's call; the pipeline tests need it). Stored
output keys the enum does not know are ignored: an exclusive filter left without a known output applies nowhere and
is reported as ineffective (§3).

`ListCompound` keeps every published filter, as today, and derives (working names):

- `whole()` — the list without its exclusive filters;
- `forOutput(Output $output)` — the list without the filters exclusive to other outputs.

Both return a `ListCompound` and keep everything else: config, `$failures` (and the catalog, once the query design
adds it). A list with
failures is not queried in any output, also when the failed filter is exclusive to another one (Claude's call: fail
closed, one rule). The duplicate-alias check stays over all published filters.

`ListCompound::hash()` goes, and with it `FilterCompound::fingerprint()`, which exists only for it (user). Their last
consumer, an in-request memo in `ArchiveFilterElement`, went with #49; since then only `ListCompoundTest` calls
`hash()`, and the docs still claim the engine and the projector use it. So `$outputs` needs no place in a hash.
`ValueInterface::fingerprint()` stays: it is how a value object expresses its identity (`ValueObjectContainmentTest`).
The query design on `v0.2-query` mentions both (its `fingerprint()`/`hash()` sentence on the effective target) and
drops them when it is rebased onto this PR.

Who asks for which list — the entry points, not the intent:

| Entry point | List |
|---|---|
| `ListViewController` (entries, count, form) | `forOutput(Output::ListView)` |
| the list view's lookup (`InteractiveProjector::project()` → `EntryCollectorFactory::createLookup()`) | `whole()` of the list it runs on, engine mods included |
| `Contao/Reader/ReaderResolver` (reader, breadcrumb, `Terminal42Languages/EventListener/ChangelanguageListener`; `ReaderResolution::$list`) | `whole()` |
| backend: `FilterInspector`, `FieldsOptionsCallbacks`, `ElementDcaHook` | the full compound, as today |
| a list handed to `FlareRuntime::project()` from Twig or code | as given |

Where the lists are taken: `ListViewController` and `ReaderResolver` each build the compound
(`ListCompoundBuilderFactory::create()->build()`) and hand the derived list to `EngineFactory::createEngine()`.
`InteractiveProjector::project()` uses its list for the form harness, the count (`createAggregationView()`) and the
entries, and today hands the same list to `createLookup()`; that call gets `$list->whole()` instead.
`ValidationProjector` already runs on the whole list and keeps handing it to both loader and lookup. `whole()` of
`forOutput(Output::ListView)` equals `whole()` of the full compound, so the lookup sees the same filters as the reader,
except those an engine mod of the list view adds (the reader's engine runs without them; not new here); filters an
engine mod adds (`SimpleEquationMod` via `withFilter()`) and the auto-item filter `ValidationLoader` adds
are not exclusive and stay in both.

The reader path since #55: `ValidationLoader::fetchEntryByAutoItem()` reads every row of the whole list the database
matches (no LIMIT, ordered behind any driver or listener order by `main.id`) and takes the first strictly equal one;
`fetchEntriesByIds()` serves the lookup on the same list. Neither changes here; they are handed the whole list.

A control of a filter that is not part of the list view's list is not mounted (the form harness works on that list).
Events and hooks see the list of their entry point; none of them changes shape:

| Event | List it carries |
|---|---|
| `ListDriverEntriesEvent::$list` | the list view's list on a list page; the whole list for the reader's entry and a lookup's members |
| `ReaderPageMetaEvent::$list`, `ReaderRenderEvent::$list` (both `ReaderResolution::$list`) | the whole list |
| `ReaderSchemaOrgEvent::$list` | in a reader template without arguments the reader engine's list, so the whole list; a list passed to `flare_schema_org()` as given (`flare.list` in a list template: the list view's list) |
| `ListViewRenderEvent` (carries the `Engine`, no list) | `$engine->getList()`: the list view's list |
| `FormHarnessBuildEvent::$list` | the list view's list |
| `QueryBaseInitializedEvent::$list`, `ModifyListQueryStructEvent` (`ListQueryConfig::$list`) | the list being queried: the list view's list for entries and count, the whole list for the reader and a lookup; in the backend (`ListExecutionContextFactory` from `ElementDcaHook`, `FieldsOptionsCallbacks`) the full compound; the Codefog tags element (`buildChoices()`) fires it with the list its context carries — the full compound in the backend, the list view's list in the frontend form |
| `FilterControlBuildFormEvent` (`ControlContext::$list`) | the list view's list (the form harness's) |
| `ListDriverBuildListEvent` | the builder before any derivation: every published filter, exclusive ones included |

`FilterCompound`'s `withValue()`, `withControl()` and `withTargetAlias()` build a new instance field by field; each
carries `$outputs` over, so a filter keeps its setting when the factory or a mod adds a value or a target.

### 3. The inspector

`Contao/Inspection/Inspector/FilterInspector` reports a filter that is exclusive without an output as *ineffective*
(`FilterIssueKind::Ineffective`), with its own translated reason — a framework check next to the element's
`IneffectiveFilterContract`. Everything else is inspected as today, for every saved row.

**Warnings, from an event.** Some setups are valid and meant, but worth a hint. The case that raised it: the News and
Events drivers add a default `PublishedFilterElement` filter in `buildList()` only when the list has none
(`ListCompoundBuilder::hasFilterInstance()`, which counts exclusive filters too). A published filter exclusive to the
list view therefore leaves the reader and the lookup without one: they find unpublished entries. That is intended
(user): the default filter is a setup aid against showing what one would rather not, and an editor who overrules it,
exclusivity included, may. The editor is told, not stopped. The points below are the user's or confirmed by the
user (2026-11-17).

- A fifth kind `FilterIssueKind::Warning` (the name is the user's). It never fails a list and never counts as broken.
- An event dispatched by `FilterInspector` once per inspected filter row (working name `FilterInspectionEvent`),
  after the framework's own checks, for every saved row, also one whose compound could not be built. It carries the
  filter row, the list (`null` when the list cannot be built), the filter compound (`null` when it could not be
  built; the one in the built list when the list has the row's filter, so that a `ListDriverBuildListEvent` listener's
  replacement is what listeners see, else the one built for the row alone), the issues found so far, and
  `addWarning(string $message, ?string $icon = null)`. Filters without a row (added by a driver or a listener) get no
  event: there is no row to show a warning on. Listeners add warnings for any reason; the bundle ships the one above:
  a `PublishedFilterElement` filter exclusive to an output (exclusive to none, it is already reported as ineffective
  and gets no warning), with no non-exclusive `PublishedFilterElement` filter in the list, for any list type
  (without the default, the reader has none either); icon `opacity`. The message is translated by the listener
  (`flare` domain).
- **An issue names its icon.** `FilterIssue` gets an optional lucide icon name. Without one, or with a name the bundle
  has no icon for, the kind's default applies: `badge-exclamation-point` for warnings, and the icons the row template
  has today for the other kinds (bug, copy-x, bone-fracture, engine). Whether those defaults change is discussed
  before the PR merges (open questions). The icons become one Twig partial per name instead of inline per kind.
- **A row keeps all its issues.** Today one issue per row and kind survives in three places: `FilterInspector::
  checkFilters()` skips a kind already found, `InspectedRows::addIssue()` keeps the first of a kind (and
  `toInspection()` iterates per kind), and `FilterIssueReporter::getRowNotices()` keys its notices by kind. All three
  keep a list per row instead; the row shows every issue, each with its own icon and reason as tooltip.
  `FilterIssue::describe()` returns a warning's reason as given (already translated, as for `Ineffective`);
  `reportOverview()`'s `match` and `addMessage()` handle `Warning`. The overview counts, as today, the filters with at
  least one issue of a kind, not the issues. Warnings are drawn yellow at the notice opacity,
  like the engine icon. In the edit form a warning is a `Message::addInfo()`-level hint; ineffective stays
  `Message::addNew()` and the other kinds `addError()`, as today (user). In the list's filter overview warnings are a
  count of their own (user).

### 4. `flare_calendar_current`

One rule, for every list the filter is part of (user's decision):

- **The configured period is a bound.** `start_at` applies where `configure_start` is set, `stop_at` where
  `configure_stop` is set; an unconfigured side is open. Today `buildEffect()` ignores `configure_*` and applies any
  non-empty `start_at`/`stop_at` — also a leftover of a side the editor switched off (the save callback does not
  clear it, the subpalette hides it); only `getDateBounds()` honors the switch. The migration (§6) keeps such
  leftovers bounding where they are readable expressions.
- **The sides are dates, read once.** The options `start_at` and `stop_at` become `?\DateTimeImmutable` (user). The
  transformer (`transformFilterModel()`) converts the stored text and is the only place that reads `configure_start` and
  `configure_stop`: an unconfigured side becomes `null`. `configure_*` leaves the options schema; the DCA fields stay.
  For a configured side it reads `elm_startAt`/`elm_stopAt` alone, in every mode: in `date` mode the timestamp Contao
  stored, in `str` mode the editor's expression, in a time-span mode the expression the save callback wrote there
  (`DateTimeHelper::spanToTimeString()`, unchanged). One reader serves all modes (user), so that a value left from
  another mode still means what it meant — e.g. a timestamp kept after switching `date` → `str`. The conversion goes
  through `DateTimeHelper`: a variant of `toTimestamp()` returning `\DateTimeImmutable` in Contao's time zone, which
  reads, like it, a time-span key, else a number as a timestamp, else the text through `strtotime()`. Empty text (or
  only whitespace) on a configured side is an open side, `null`, as today (user): the fields are not mandatory, and a
  new row keeps the default mode `today` with an empty text until its calendar palette is saved. Today both
  `buildEffect()` and `getDateBounds()` use `strtotime()`, which misreads the bare timestamp the `date` mode stores
  (`rgxp => datim`): `1600432000` becomes 16:00:43 on today's month and day in the year 2000, a bound that moves daily;
  other timestamps are not read at all. That is fixed here (Claude's call after the builder check). Relative
  expressions resolve when the compound is built, so once per build: each content element builds its own compound
  (`SourceTransformer` memoizes the transformer map, not its results). A request-wide reference time is not required.
- **Unreadable text fails closed** (user). A configured side whose text `DateTimeHelper` cannot read makes the
  transformer throw a `FilterException`, so the list fails (§2: in every output) and the inspector shows the filter
  as broken. A save callback on `elm_startAt`/`elm_stopAt` rejects such text with a translated message through the
  same helper, as `DateRangeValueProvider::normalizeExpressions()` does for the value, so it rarely gets stored; it
  accepts empty text.
- **The value narrows within it.** A `from` earlier than the start or a `to` later than the stop is ignored on that
  side — today's `is_limited` behavior, always: the effective range is the intersection of bound and value.
- **An empty intersection matches nothing** (user). When the effective start lies after the effective stop (e.g. a
  bound up to March and a value from April), the element calls `abort()` ("legitimately matches nothing") instead of
  adding the predicate. Today the inverted range goes into `CalendarCurrentPredicate`, whose extended-events branch
  `startTime <= :start AND endTime >= :end` then matches events that lie in neither range (and the same holds in v0.1).
- **The filter's stored value** (the `value` column, a `DateRangeValue`, relative expressions such as `today` allowed,
  `DateRangeValueProvider`) has two roles, as for every filter (user):
  - with a control it is the preselection: the form's initial value (`hydrate()`); the visitor moves it freely within
    the bound, and the reader never sees it;
  - without a control it is a fixed narrowing within the bound, in every list the filter is part of — the reader too,
    unless the filter is exclusive.
- **A side is a point in time, not a day.** `today` is today 00:00; as a stop it excludes an event starting later
  today (`CalendarCurrentPredicate`: `startTime <= stop`). That is intended (user) and stays: an editor who wants
  today included writes `tomorrow` or `today 23:59`.
- `getDateBounds()` returns the configured period, so the date inputs are limited whenever a bound is configured.
- `is_limited` goes: from the schema, the transformer, the palette and the translations. The column stays in the
  database.

**The backend fields keep their three modes** (user): `elm_configureStart`/`elm_configureStop` offer `date` (a date
picker; Contao stores a timestamp), `str` (a text input for a `strtotime()` expression such as "last week thursday")
and the time-span keys (the save callback writes the span's expression into the side, no input shown). What changes is
how the date picker comes about:

- `elm_startAt` and `elm_stopAt` are plain text inputs in `contao/dca/tl_flare_filter.php` (no `rgxp`, no
  `datepicker`, no `wizard` class). The calendar element is their only user.
- `DcaFieldBuilderInterface` gets `makeDatim()` (user): it sets `rgxp => datim`, `datepicker` and the `wizard` class,
  and adds a load callback that shows a stored expression as its timestamp (through `DateTimeHelper`), so any element
  can turn a text field into Contao's date-time input. The calendar element calls it in `buildDca()` for a side whose
  stored mode is `date` (`DcaContext::$filterModel`); the mode field submits on change, so the stored mode is the one
  shown. Switching a side from `str` to `date` therefore shows the expression as the timestamp it means at that
  moment, and the next save stores that fixed date — accepted (user), as today.
- `FieldsLoadAndSaveCallbacks::onLoadField_startStopAt()` goes: its `date` branch is `makeDatim()`'s load callback, its
  other branch (removing `rgxp` and `datepicker`) is no longer needed.
- `DcaFieldBuilder` can remove keys (user: their absence is a bug). `eval()` and `merge()` only merge, so today a key
  can be set to `null`, never removed; `applyTo()` then writes the merged array. The method name and whether it covers
  `eval` keys only or any path are the builder's call. The calendar element does not need it any more; it is fixed
  here because it was found here.

The two purposes are then two settings, and where both are wanted with different periods, two filter rows of the same
type:

| Wanted | Filter rows |
|---|---|
| a hard bound | one, not exclusive |
| the list shows what is coming, the reader finds everything | one, exclusive to the list view |
| a hard bound and a narrower section in the list view | two: one not exclusive, one exclusive |
| a preselection the visitor can move | one with a control; its stored value is the preselection |

### 5. Contexts without an intent

`ElementContext` and `ControlContext` lose `$intent` (list × filter remains). Call sites: `ListQueryDirector`
(`invokeFilters()`), `Form/Factory/FormHarnessFactory`, `FilterInspector` (twice), `Contao/DataContainer/
BackendElementContextFactory`, `ControlContext::forElement()`. The only reader of `$context->intent` in `src/` is
`CalendarCurrentFilterElement::buildEffect()` (§4); in `tests/`, `ArchiveFilterElementTest`, `FilterInvokerTest` and
`ControlContextTest` read it. In `src/`, `ConfigurationIntent` is constructed only for these contexts
(`FilterInspector`, `BackendElementContextFactory`), so it goes with them (user). It came with #49 as
`ConfigurationContext` (`2026-10-14-filter-forms-design.md` §6.4, its decision 21) to fill the then mandatory third
member of the triple for the backend, which evaluates filters without an engine run; no projector serves it and
nothing branches on it, so without `$intent` it has no purpose. The tests that use it: as
the contexts' intent, `BuildsFilterEffectsTrait`, `Control/BuildsControlContextsTrait`, `ControlContextTest`,
`ElementContextTest`, `FilterEffectFactoryTest` (also `testConfigurationIntentHasItsOwnType()`),
`Element/CalendarCurrentFilterElementTest`, `Element/DcaSelectFieldFilterElementTest`,
`ValueProvider/ChoiceValueProviderTest`, `Form/FilterFormBuilderFactoryTest`, `Form/FormHarnessTest`; as an `Engine`'s
intent, `Twig/FlareRuntimeTest` (needs another intent then); and `DependencyInjection/ServicesConfigTest` lists the
class. The docblock of `DcaContext::getElementContext()` ("with a configuration intent") follows. The form harness
itself keeps its intent (form name, action), and so do `ListQueryConfig` and the loaders (paging, filter values).

### 6. Migration

A run-once migration for the rows of type `flare_calendar_current`, so that no list becomes wider than it was and no
reader narrower. v0.2 is unreleased, so the rows it meets come from v0.1 (user): the baseline is v0.1's element. Its
period bounds the list view; the reader it bounds only where `is_limited` is on. With `is_limited` off, v0.1's
`onInvoking()` listener skipped the filter for a `ValidationContext`, so the reader and the breadcrumb ignored it
(E31: the earlier premise that v0.1's period bounded the reader in every case was wrong, found while the docs were
written). The palette offered the checkbox only without `intrinsic`, so every intrinsic calendar filter of v0.1 has it
off. The `v0.2` development state behaves the same (the `ValidationIntent` branch of `buildEffect()` drops the window
in the reader when `is_limited` is off).

**A row with `is_limited` off becomes exclusive to the list view; no control or value is touched** (user, E29 after
the third review, amended by E31, 2026-11-18). §4's one rule is v0.1's `is_limited` behavior made permanent: the
configured period bounds every list the filter applies to, a value only narrows within it. Where `is_limited` was off,
the exclusive setting keeps the filter out of the whole list, so the reader, the breadcrumb and the lookup stay as
v0.1's reader was; the list view gets the one rule, which never widens it but can narrow it, and the editor checks
those filters after the update:

| `is_limited` | Control / stored value | Today (v0.1 and v0.2-dev) | After |
|---|---|---|---|
| on | with or without | the period bounds list view and reader; a value narrows within | unchanged |
| off | none, no stored value | the period bounds the list view; the reader ignores the filter | unchanged: exclusive to the list view |
| off | with a control | list view: the period is a default the visitor's value replaces side by side; the reader ignores the filter | exclusive to the list view; there the period bounds, the visitor narrows within it, and the date inputs are limited to it. What the list shows before the visitor changes anything stays the same; the reader is unchanged |
| off | none, with a stored value | list view: the value replaces the period side by side; the reader ignores the filter | exclusive to the list view; there the value narrows within the period, and the list view can become narrower, or empty where they do not overlap (`abort()`, §4); the reader is unchanged |

An editor who wants the old list-view behavior of the last two rows back configures it with §1 and §4 (the notes give
the recipe): the period as a movable preselection is a filter with a control, its sides switched off and the period
as its stored value, still exclusive to the list view.

The migration's changes, for every row of the type: a row with `is_limited` off gets `exclusive` on and `outputs` set
to the list view (`serialize(['list_view'])`, E31); and a side whose `configure_*` is blank but whose
`start_at`/`stop_at` holds a non-numeric expression `strtotime()` can read gets `configure_*` switched on (`str`),
so the backend shows the bound that applies today and the new rule keeps applying it. The check reads the text as v0.1
did, with plain `strtotime()`: a time-span key such as `this_week` it cannot read, so that side is open today and
stays switched off, though `DateTimeHelper` would read it. A leftover it cannot read stays
switched off (user): today `strtotime()` returns false for it and the side is open, so switching it on would fail a
list that works (§4, fail closed). A numeric leftover stays switched off too and so stops applying (§4: the
transformer ignores a switched-off side). Most numbers are timestamps `strtotime()` does not read or misreads as a
date in the year 2000 on the current day (as a stop: an empty list), but some it reads as a working date or time —
`20260101` as 1 January 2026, `0900` as today 09:00 — and those bounds go; the list can become wider (accepted,
below).

Nothing in the backend marks the filters whose meaning changed, so the migration's result message counts them: the
rows of the type with `is_limited` off and a control or a stored value with a set side.

Accepted changes, named in the migration notes (confirmed by the user, 2026-11-17 and 2026-11-18):

- `is_limited` off, with a control: in the list view the period becomes a bound the visitor cannot leave, and it
  limits the control's date inputs; the initial display is unchanged;
- `is_limited` off, no control, with a stored value: in the list view the value narrows within the period instead of
  replacing it;
- `is_limited` off: the filter is exclusive to the list view, so a filter added to the list later applies there too,
  while the reader, the breadcrumb and the lookup keep ignoring it (as v0.1's reader did); the editor sees the setting
  in the expert legend;
- a side in `date` mode (an absolute date) starts to apply as configured — the fix of §4. Where the date was ignored,
  or misread as a start in the year 2000, the lists that have it become narrower; where it was misread as a stop in
  the year 2000 and emptied the list, the list shows entries again;
- a numeric leftover of a switched-off side stops applying (see above): where it emptied the list as a misread stop,
  the list shows entries again; where `strtotime()` read it as a working date or time, that bound goes;
- a configured side with text `DateTimeHelper` cannot read fails the list instead of being ignored (§4); the
  inspector shows such filters as broken after the update;
- `is_limited` on, with a control: a side the pre-step switches on now also limits the control's date inputs
  (`getDateBounds()` honors `configure_*`); it already bounded the entries, so only the form changes.

**Order.** On an update from v0.1, the rows get their v0.2 meaning from other migrations first:
`RenameFilterColumnsMigration` renames the element columns (`elm_isLimited`, `elm_configureStart`, `elm_configureStop`,
`elm_startAt`, `elm_stopAt`), and `BackfillFilterValuesMigration` assigns the control (`flare_date_range` for a calendar
row that was not intrinsic) and the value. The switch-on step needs only the renamed columns; the count needs the
control and the value; the exclusive step needs `exclusive` and `outputs`, which the schema update adds. Contao orders
migrations of equal priority by class name, so the order is stated, not left to the names: the migration runs only once
the renamed period columns, `control`, `value`, `exclusive` and `outputs` exist, and while
`BackfillFilterValuesMigration::shouldRun()` is true it waits (`contao:migrate` checks again after each pass). It also
requires `elm_isLimited`, which only a database that had the v0.1 calendar element has: a fresh install never runs it,
and once the manual deletes drop the column it stays quiet (Marker, below).

**Marker.** The migration must handle each row exactly once and never touch a row created or saved after the update:
run again, it would switch on a side an editor has switched off since (the save callback does not clear the text).
`is_limited` stays in the database and cannot serve as the marker. It follows `BackfillFilterValuesMigration`'s pattern
(Claude's call after the second review, confirmed by the user): its own migration-managed column, not part of the
DCA; the rows existing when it is added start pending (`'0'`), the column default then becomes `'1'`, so rows created
afterwards are never pending; a row's changes and its marker are written in one statement, so an interrupted run never
processes a row twice. Rows of other types are marked done in the same run. An editor does not save filters between
the schema update and the migration (user), so the window in which a row could be saved while still pending is not
guarded. The marker is not part of the DCA, so the schema diff offers to drop it among the manual deletes, together
with `elm_isLimited`; `contao:migrate --with-deletes` runs the migrations again after the schema diff, and without a
guard the migration would start over then and switch on the sides editors have switched off since. Requiring
`elm_isLimited` is that guard (fourth review, user, 2026-11-18), as `intrinsic` is the backfill's. The deletes run
only after all migrations have run (user), so dropping `elm_isLimited` first never skips the switch-on. The query
design's D31 pattern (a DCA field defaulting to `'0'`, set on backend saves) is not used: a row created outside a
backend save would start pending and be rewritten. Like `BackfillFilterValuesMigration`, the migration works through
the DBAL `Connection`, not `Contao\Database`, and its SQL runs on MySQL and MariaDB; it never drops or deletes.

### 7. Documentation

`AGENTS.md`: the filter subsystem (`FilterCompound`, `ElementContext`/`ControlContext` "list × filter" instead of
"list × filter × intent"), the vocabulary's intents without `ConfigurationIntent` (:24), the passages #55 rewrote —
`src/Engine/ListEntry/` (`EntryLookup`: "by the reader's rules") and `src/Contao/Reader/` (`ReaderResolver`,
`ValidationLoader`) — now on "the whole list", the list view's list from `forOutput()`, the inspector's issue kinds and
its event, and the migration list.

The docs worktree (`docs/`, branch `docs/main`, committed separately), page by page:

| Page | What changes |
|---|---|
| `reference/filter-elements.md` | the expert setting; the calendar filter's one rule (the page never named `is_limited`) |
| `dev/filter-elements/index.md` | the context table lists `$context->intent` (:105); the table "fixed value / initial display / whole list" (:296–304) and the fail-closed passage (:365): an exclusive filter bounds only its outputs; the inspector's warning and event |
| `dev/filter-controls.md` | says the same about `$context->intent` (:92); "the element config bounds the whole list" (:254), unless exclusive |
| `spec/filtering.md` | the two-stage context "list × filter × intent" (:23); `fingerprint()` / `hash()` gone (:72–73); the role table (:77) and "whole list" (:150) as above; the issue-kind table (:190–195) gets `Warning` |
| `spec/compounds.md` | `whole()` / `forOutput()`; `hasFilterInstance()` (:36, :44) counts exclusive filters too; §5 "Persistence & Hashing" (:86–90) goes with `hash()` |
| `dev/list-types/index.md` | the default published filter (:102–126): skipped also for an exclusive one, with the warning of §3 |
| `dev/events.md` | which list each event carries (§2); the inspection event |
| `dev/dca-builder.md` | `makeDatim()` and the key removal |
| `templating.mdx` | the lookup's membership "by the reader's rules" (:313): the whole list; `flare_schema_org()` (:620): which list it gets |
| `reference/twig.md` | `flare_reader.lookup()` "by the reader's rules" (:17); `flare_schema_org()` (:11) as above |
| `dev/entries.md` | the lookup's membership (:163, :195) |
| `integrations/index.md` | the calendar filter's description (:29) |
| `removed-in-v0.2.md` | `is_limited`, the contexts' `$intent`, `ConfigurationIntent`, `ListCompound::hash()`, `FilterCompound::fingerprint()`; "by the reader's rules" (:77); "bound the whole list" (:167), unless exclusive |
| `migrating-from-v0.1.md` | `#[AsFilterInvoker]` (:41) says to branch on `$context->intent`: now the exclusive setting; "only fixed values … bound the whole list" (:160), unless exclusive; the issue kinds (:175–176) get the warning; the accepted changes of §6, the narrower lists of `is_limited` off first, with the recipe for the old behavior |

Translations in `contao/languages/{de,en}/tl_flare_filter.php` and the `flare` domain (the expert fields of §1, the
warning of §3, the save callback's message of §4).

## Decisions

| # | Decision | By |
|---|---|---|
| E1 | The list view and the reader are two lists; the difference is configured per filter, not decided by the intent | user |
| E2 | Expert setting "apply exclusively" + ticked outputs in the palette suffix of every filter; off by default | user |
| E3 | Outputs are subsets of the whole list: list view now, sitemap and export later. Reader, lookup and count are no options | user (proposed by Claude) |
| E4 | Exclusive instead of "do not apply in", so a new output gets the whole list without touching existing filters | user |
| E5 | The setting is stored as a list of output keys; exclusive without an output applies nowhere and is reported as ineffective | Claude |
| E6 | The entry points ask the compound for their list (`whole()`, `forOutput()`); no step looks at the intent | Claude |
| E7 | One element type `flare_calendar_current`; configured period = bound, stored value = preselection; `is_limited` goes | user |
| E8 | `ElementContext` and `ControlContext` lose the intent | user |
| E9 | Failures fail the list in every output | Claude |
| E10 | The migration widens no list; lists change only by the accepted changes of §6 | Claude (reworded after the second and the third review) |
| E11 | Own PR on `v0.2` before the query refactor | Claude |
| E12 | Absolute dates of the configured period are read (today they are not applied); readable leftover bounds of switched-off sides are made visible by the migration instead of dropped | Claude (after the builder check) |
| E13 | A default filter a driver skips because an exclusive filter of its type exists is intended; the editor gets a warning, not an error | user |
| E14 | A fifth issue kind `Warning`, added by listeners of one inspection event per filter; the bundle's own warning is one such listener | user |
| E15 | An issue names its lucide icon; unknown or none falls back to the kind's default (`badge-exclamation-point` for warnings); a row shows all its issues | user |
| E16 | `start_at`/`stop_at` are `?\DateTimeImmutable` options; the transformer reads only the value fields, with one reader for every mode, and alone honors `configure_*`, which leaves the schema | user |
| E17 | Unreadable period text fails closed; a save callback rejects it; both through `DateTimeHelper` | user |
| E18 | The backend keeps the `date` / `str` / time-span modes; the fields are plain text, `makeDatim()` on the field builder turns the `date` side into Contao's date-time input | user |
| E19 | `DcaFieldBuilder` can remove keys | user |
| E20 | A side is a point in time (a stop of `today` excludes later today), and switching `str` → `date` fixes the expression's date: both intended, as today | user |
| E21 | ~~Migration row 3 moves only the sides whose `configure_*` is set after the pre-step; a side the stored value sets wins~~ Superseded by E29 | user (after the review) |
| E22 | Events keep their shape and carry the list of their entry point (§2 table); `with*()` keep `$outputs`; a type that replaces the palette suffix hides the setting (stored values still apply), unguarded | Claude (after the review), confirmed by the user |
| E23 | `ListCompound::hash()` and `FilterCompound::fingerprint()` go (dead since #49); `ValueInterface::fingerprint()` stays | user |
| E24 | `ConfigurationIntent` goes with the contexts' `$intent` | user |
| E25 | The migration's baseline is v0.1 (v0.2 is unreleased) | user |
| E26 | The migration waits for the column renames and `BackfillFilterValuesMigration`; its marker is its own migration-managed column (the backfill's pattern), not D31; it requires `elm_isLimited`, so the delete run that drops the marker leaves it quiet; saves between schema update and migration are not guarded | Claude (after the second review), confirmed by the user; the window: user; `elm_isLimited` required: user, after the fourth review |
| E27 | An empty configured side is open; an empty intersection of bound and value matches nothing (`abort()`) | user |
| E28 | The migration switches on only leftovers v0.1 could read (plain `strtotime()`, no time-span keys); numeric leftovers go, also the ones read as dates | user |
| E29 | (Amended by E31.) The migration touches no control or value: §4's rule applied to every row widens no list; where `is_limited` was off it can narrow one, the result message counts those filters, and the notes give the recipe for the old behavior | user (proposed after the third review), 2026-11-18 |
| E30 | Legends without fields are dropped only when `DcaBuilder::apply()` writes a palette; `Str::mergePalettes()` keeps them | user, 2026-11-18 |
| E31 | v0.1's reader ignored a calendar filter with `is_limited` off (its `onInvoking()` listener skipped it for a `ValidationContext`; intrinsic rows always had it off), so the migration makes every such row exclusive to the list view: the reader, breadcrumb and lookup stay as in v0.1, the list view gets the one rule | user, 2026-11-18 (found in the docs review during implementation) |

## Left to the builder

Final names (`exclusive`, `outputs`, `Output`, `whole()`, `forOutput()` are working names), the storage format of
the output list, where the enum lives, the name of the migration's marker column, how code marks a filter exclusive
(§2), how the inspector words the new reason, the order of commits. From E13–E30: the event's name, the name of the
`DateTimeHelper` variant, the key-removal method.

## Testing

- `FilterCompound`: the setting, `withValue()`/`withControl()`/`withTargetAlias()`
  keeping it; `ListCompound::whole()` / `forOutput()` (exclusive filters dropped or kept, failures and config kept,
  filters added by `withFilter()` not exclusive).
- `Contao/Factory/FilterCompoundFactory` and `FilterCollector`: the stored fields, exclusive without outputs.
- Through the SQLite pipeline of PR #54 (`tests/Engine/EnginePipelineTrait.php`; `createReaderResolver()` builds
  the stored list with filters injected by a `ListDriverBuildListEvent` listener, so those filters carry the
  setting): a list with a filter exclusive to the list view — entries and count apply it, the lookup and the reader
  (by auto-item, through #55's strict match) find an entry it excludes, the breadcrumb (`BreadcrumbHookTest`) too; a
  control of a filter outside the list view's list is not mounted.
- `ListViewController` (no test today): it hands `forOutput(Output::ListView)` to the engine.
- `CalendarCurrentFilterElement`: bound and value under the one rule, unconfigured sides open, the same conditions
  whatever the caller; an empty intersection aborts (with extended and recurring events, which the inverted range
  matches today); `getDateBounds()`. Its transformer: a side holding a time-span's expression, an editor's expression
  and a bare timestamp (`1600432000` among them) gives the expected `\DateTimeImmutable`, a switched-off side and an
  empty or blank text `null`, unreadable text a `FilterException`. The save callback rejects unreadable text and
  accepts empty text; `buildDca()` calls `makeDatim()` only for a `date` side.
- `DcaFieldBuilder`: `makeDatim()` (eval keys and load callback) and the key removal, through `applyTo()`.
- `FilterInspector` / `FilterIssueReporter`: the event is dispatched per saved row, also for a row whose compound
  could not be built and when the list cannot be built (with `null` members), not for row-less filters; its warnings
  land on the row, the form and the overview; the bundled listener warns for an exclusive `PublishedFilterElement`
  without a non-exclusive one and not otherwise; a row keeps several issues of one kind (two warnings), the overview
  counts the filter once; icon fallback for an unknown name; exclusive without a (known) output is ineffective.
- The migration: a readable leftover switched on (on every row of the type, `is_limited` on or off), an unreadable
  one and a numeric one left off; `exclusive` on and `outputs` the list view exactly for the rows with `is_limited`
off; controls and values untouched; the count of the result
  message (a control, a stored value with a set side, `is_limited` on not counted); quiet without `elm_isLimited`
  (after the manual deletes); waiting while the backfill would run (an update from v0.1 in one `contao:migrate`), run
  once, rows created afterwards never pending.
- `DcaBuilder::apply()` drops a legend left without fields; `Str::mergePalettes()` keeps it.
- The migration's tests mock the DBAL `Connection`, as `BackfillFilterValuesMigrationTest` does.
- Types are pinned with `identicalTo()` / `assertSame()` where a test checks what a list or value is.
- Tests of the removed code go: `ListCompoundTest`'s `hash()` tests, `FilterCompoundTest`'s `fingerprint()` tests,
  `FilterEffectFactoryTest::testConfigurationIntentHasItsOwnType()`; `FlareRuntimeTest` takes another intent.
- Existing tests follow (the contexts' constructors in 26 places: 6 in `src/`, 20 in `tests/`); `make test` (923
  tests at `680d4add`) and `make phpstan` green; Mago runs in CI only.

## Open questions

The check against `680d4add` raised none: #55 changed how the reader finds its row and how models are loaded, not
which list the reader and the lookup are handed, so E1–E12 hold as decided.

- The default icons per issue kind (§3): kept as they are for now; whether they change is decided before the PR merges
  (user).
