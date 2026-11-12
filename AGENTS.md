# AGENTS.md

This file provides guidance to AI coding agents, e.g., Claude Code (claude.ai/code), Gemini, and ChatGPT Codex, when working with code in this repository. `CLAUDE.md` is a symlink to this file — always edit `AGENTS.md`.

## Project

**FLARE** (Filter, List, And REader) — a Symfony bundle for Contao CMS that replaces complex module setups with a unified List/Reader content element approach.

- Namespace: `HeimrichHannot\FlareBundle\`
- Requires PHP ^8.2, Symfony ^5.4/^6.0/^7.0, Contao ^4.13/^5.0
- Bundle Name: Contao Flare Bundle (`heimrichhannot/contao-flare-bundle`)
- Type: Symfony Bundle for Contao CMS
- Status: Active development (Beta/WIP).

## Architecture

**Vocabulary** — four nouns name the core concepts; keep them distinct:
- *Compound* — the assembled, runnable list (`ListCompound`) or filter (`FilterCompound`): bound components with
  their canonical config, ready to run (a list's `$failures` change its output, not its runnability).
- *Effect* — an element's abstract building blocks for the query (`FilterEffect`, built in `buildEffect()`):
  predicates today, later joins, derivations and order hints.
- *Context* — the pre-filled handover to a hook (`ElementContext`, `ControlContext`, `DcaContext`).
- *Intent* — what the engine renders a list for, with the expected output (`InteractiveIntent`, `ValidationIntent`,
  `AggregationIntent`, `ConfigurationIntent`); its type selects the projector.

The core execution flow is:

```
ContentElement Controller
  → Contao\Factory\ListCompoundBuilderFactory::create($listModel)->build()
    — builds the immutable ListCompound (src/List/)
    → EngineFactory → Engine
      → Intent (Interactive / Validation / Aggregation) — src/Engine/Intent/
        → Loader (src/Engine/Loader/) + Mods (src/Engine/Mod/)
          → Projector (Interactive / Validation / Aggregation / Export) — orchestrates query + filter execution
            → View (entries for Twig: src/List/Entry/)
```

The reader and the breadcrumb take this path through `Contao\Reader\ReaderResolver`, once per request and auto-item;
the views hand out `Entry` objects, never Contao models (a model is made only at the Contao boundary,
`Contao\ListEntry\EntryModelFactory`).

Filter execution inside the projector: the `FormHarness` decodes submitted form data per filter control into value
objects; `ListQueryDirector` (`src/Query/Executor/`) picks each filter's value (with a control: the form's value;
without: the fixed `FilterCompound::$value`), creates the `FilterEffect` via
`FilterEffectFactory::create(ElementContext, ?value)` (the element's `buildEffect($builder, $context, $value)` adds
predicates) and runs each predicate into a `FilterConditionsBuilder`. A
filter whose element cannot be built (with or without a control) — required element config is required by the
element's schema —, a stored control that cannot be resolved or whose config is broken, a controlled filter whose
alias is no valid form name, a broken fixed value, a `FlareException` from a `FilterCollectedEvent` listener, or two
published filters sharing an alias fail the whole list: `Contao\Factory\FilterCollector` records them as
`FilterFailure`s (`src/List/`) on `ListCompound::$failures`, and a list with failures is never queried
(`list.failed` in the view, HTTP 500 in the reader). The failures only the form harness detects
(`Form/Factory/FilterFormBuilderFactory`) — a control's `buildForm()` throws, or a `FilterControlBuildFormEvent`
listener throws a `FlareException` — fail the list view only (`list.failed`); the reader builds no form, so they do
not affect it. An unreadable initial value only drops the value (warning). These failures win over an invalid
submission, which shows no entries and runs no COUNT. `abort()` means only "legitimately matches nothing".
A controlled filter that fails with a non-null value is retried once with value null: success drops only the value
(warning); if the retry fails too (or the value was already null) the element config is broken and the list fails
closed. `\Error`s escape unwrapped.
`UndefinedConfigKeyError` (an `\Error`, thrown when a component reads a config key its schema does not define) is a
programming error: never retried, never degraded into a skipped filter or a failed list — it fails the request. So is a
filter value of the wrong class (`ValueClassMismatchError`, an `\Error` thrown by `FilterCompound`'s constructor, so by
`withValue()` too): stored data cannot produce one (value providers and controls are registered by their narrowed return
type), so it always comes from code — e.g. a `ListDriverBuildListEvent` or `FilterCollectedEvent` listener — and fails
the request with HTTP 500, never as a `FilterFailure`. The bundle catches `\Exception`, not `\Throwable`, so no `\Error`
is wrapped or swallowed; only the transformer resolvers still wrap any `\Throwable` (a transformer casting stale stored
data may throw a `\ValueError`), and the backend `FilterInspector` catches `\Error` outside debug mode to mark the
filter as a programming error (the frontend request still fails).
Who throws what: `FilterException` (and its `UnreadableValueException`) is public API — filter components, bundled
and userland, throw it to report a filter that cannot work with its config, data or value; a component translates
the message itself where an editor may see it (`AbstractFilterElement::trans()`, `flare` domain). `FlareException` and the
other exceptions in `src/Exception/` are thrown by the framework only; userland may catch them. Every filter hook
(element `buildEffect()`, control `buildForm()`/`hydrate()`/`decode()`) catches any `\Exception` and normalizes it
with `FilterException::wrap()`; a non-`FilterException` is unexpected: logged as an error. Value providers are
treated alike (`Contao\Factory\FilterCompoundFactory` wraps their exceptions; a non-`FilterException` `FlareException`,
a wiring error, passes unwrapped). A control's `buildForm()` failure fails the list view (error, rethrown by the failed
view in debug mode; the reader is unaffected); a `hydrate()` failure mounts the filter without its initial value
(warning; an unexpected exception is rethrown in debug mode); a `decode()` failure drops the value and puts a form
error on a submitted field.

Note: Intents and Projectors/Views are separate axes — (Export Intent/Projector/View are currently not implemented).

The bundle follows standard Symfony Bundle architecture with deep Contao integration.

**Lifecycle taxonomy** — elements and list types own their lifecycle through two method families:
`configure*` methods are declarative, memoizable setup (`configureOptions` = OptionsResolver schema,
`configureTransformers` = source→canonical-config mappings); `build*` methods are per-invocation construction
(`buildDca`, `buildForm`, `buildEffect`, `buildList`, `buildTableRegistry`/`buildBaseQuery`).

**Notable subsystems** (beyond the flow above):
- `src/List/` — `ListCompound` (immutable list DTO: type, dc, filters, canonical config, source; a *compound*
  because it is assembled from bound components — driver and filter compounds — ready to run), `ListCompoundBuilder`
  (build lifecycle: driver's `buildList()` hook → `ListDriverBuildListEvent` → config assembly → schema resolution),
  `BaseListOptions` (framework-owned base schema for tl_flare_list columns)
- `src/List/Entry/` — the list's runtime data, Contao-free (as `Filter\Value\` is the filter's): `Entry` (one row
  as delivered, `EntryMeta $meta`, attachments; `__get()` resolves the reserved `to` (the reader URL from the
  collection's linker), then a row column, an attachment of the entry, one of its collection, else
  `UndefinedEntryKeyError`; `row` and `meta` are declared properties, so a column named `to`/`row`/`meta` stays
  reachable as `entry.row.<name>`; `__isset()` is true for a null column), `EntryMeta` (`?ModelRef $modelRef` =
  table + id, set for every DC-table row; new metadata goes here, never into the reserved names), `EntryCollection`
  (`IteratorAggregate` + `Countable`, a list, not keyed by id; `forTable()` throws `MissingEntryIdError` for a row
  without `id`; `column()`; two-phase `attach($name, $load)`: phase 1 once per collection on the first read, its
  picker per entry; `seal()` after the entries event) and the ports `EntryLookupInterface` (`lookup(mixed)`: an id →
  `?Entry`, a list of ids → `EntryCollection`) and `EntryTaggerInterface`. Collisions (reserved name, column,
  attached twice, entry vs. collection) and any `attach()` after sealing throw `\Error`s
- `src/Engine/ListEntry/` — `EntryCollector` (rows → `EntryCollection::forTable($list->dc)` with the reader linker →
  `ListDriverEntriesEvent`, only for a non-empty collection → seal) and `EntryLookup` (member entries by id by the
  reader's rules — fixed filter values, no form values — in one query without pagination, in the given order,
  duplicates collapsed, memoized per id list; each result is its own sealed collection with its own entries event;
  members are cache-tagged; `lookup([])` queries nothing; an id is a positive int or its canonical string;
  `null`/`''`/`0`/`'0'` count as no id, anything else throws `InvalidLookupIdError`). Neither is a service: `Engine/Factory/EntryCollectorFactory`
  creates both (the lookup as a `LazyEntryLookup`, built on its first call). The views use them lazily: `InteractiveView::getEntries()` (`flare_list.entries`),
  `ValidationView::getEntryByAutoItem()` (memoized, misses too) and `ValidationView::lookup()`
  (`flare_reader.lookup()`); a listener gets the lookup only as the second argument of phase 1
- `src/Filter/` — `FilterCompound` DTO (`ElementBinding` = element + type + element config `elm_*` + value class,
  optional `ControlBinding` = control + type + control config `ctl_*`, `?ValueInterface $value`); each config is an
  immutable `ComponentConfig` (`src/Config/`, `get()` throws `UndefinedConfigKeyError` for an undefined key), elements
  (`Element/`), predicates (`Predicate/`, the public SQL-fragment layer, formerly "filter types"),
  resolvers (`FilterOptionsResolver`, `FilterTransformerResolver`). `Factory/FilterCompoundFactory::create()` is the
  single construction path; it knows no model — a stored source reaches the components' transformers as `$configSource`.
  A filter is applied in two stages: a value-free, role-typed context — `ElementContext` for element hooks and
  contracts, `ControlContext` for control hooks (list × filter × intent; `$context->get('key')` reads the
  calling component's own config; `ControlContext::element(Contract::class)` / `tryElement()` return the element as a
  capability port, `forElement()` hands element contracts their context) — handed to
  everything that happens before a value exists, and `FilterEffect` (the element's product for one invocation,
  collected through the public `FilterEffectBuilderInterface` and built by `FilterEffectFactory` from context +
  value, only inside `ListQueryDirector`). Hook parameter order: the subject first, the context supplementary —
  `buildEffect($builder, $context, $value)`, `buildForm($builder, $context)`, `decode($data, $context)`,
  `hydrate($value, $context)`.
  `FormHarnessFactory` builds a `FormHarness` (root form + mount↔filter map of `FilterMount`s) per list ×
  form intent; the harness owns the `decode()` loop
- `src/Filter/Control/` — `FilterControl` implementations (peers of `src/Filter/Element/`) behind
  `FilterControlInterface` (`buildForm()`, `decode()`, `hydrate()`), registered with `#[AsFilterControl]`.
  Controls bind to a value *class* (the narrowed return type of `decode()`), never to an element type:
  `flare_choice`, `flare_checkbox`, `flare_choice_bool`, `flare_date_range`, `flare_keywords`. "Form" names only
  the Symfony level (`FormHarness`, `FilterFormBuilder`, mount). `src/Form/` — Symfony-level building blocks
  (`FormHarness`, `FilterMount`, `FilterFormBuilder` — the collect-only builder behind the public
  `FilterFormBuilderInterface` a control's `buildForm()` receives, created by `Form/Factory/FilterFormBuilderFactory` —,
  `ChoicesBuilder`, `Form/Type/DateRangeFormType`)
- `src/Contract/FilterElement/` — capability ports an element implements for controls/providers to pull from
  (`ChoiceSourceContract`, `DateBoundsContract`); controls never reach into an element otherwise.
  `IneffectiveFilterContract` lets an element explain why its filter restricts nothing as configured. One backend
  inspector (`Contao/Inspection/Inspector/FilterInspector`) finds every filter issue of a list once, as one of four
  kinds — programming error, duplicate alias, broken (cannot be created, or conditions that cannot be built with
  the default value, through the shared `Query/Executor/FilterInvoker`, or a control that cannot build its form,
  through the shared `Form/Factory/FilterFormBuilderFactory`), ineffective; `Inspection/Reporter/FilterIssueReporter` shows
  them on the filter row (icon), the edit form and the list's filter overview (counts of published filters only). It inspects every saved row, builds
  the list the way the runtime does, and catches `\Error` outside debug mode only (logged as critical)
- `src/Filter/ValueProvider/` — one `ValueProviderInterface` service per value class (`provide()` turns the
  stored `value` column into a value object; `buildDcaField()` renders the backend widget), registered via the
  `flare.value_provider` tag and `ValueProviderRegistry`
- `src/Migration/` — `RenameFilterColumnsMigration` (`elm_`/`ctl_` renames), `BackfillFilterValuesMigration`
  (run-once per row via the migration-managed `legacyBackfilled` marker column), `RenameContentListColumnMigration`.
  Migrations never delete; legacy columns stay until deletes are run manually
- `src/Config/` — `ConfigBuilder` (fluent canonical-config accumulator; no cast helpers — transformers cast
  declaratively off the typed model), `TransformerMap` (source class → transformer, what
  `TransformerContract::configureTransformers()` fills) and the two memoizing cores `SchemaResolver` (options)
  and `SourceTransformer` (transformers). The domain wrappers `FilterOptionsResolver` /
  `FilterTransformerResolver` and `ListOptionsResolver` / `ListTransformerResolver` only add the contract
  check, their event and the wrapping of any failure into `FilterException` / `FlareException` (with
  `$origin` as the exception's source)
- `src/Filter/Value/` — immutable filter value objects (`BoolValue`, `ChoiceValue`, `KeywordsValue`,
  `DateRangeValue`; choices in `Value/Choice/`), the typed channel between a control's `decode()` and an
  element's `buildEffect()`. Each is `final readonly`, implements `ValueInterface` and expresses its identity
  through `fingerprint()` (`ValueObjectContainmentTest` enforces this by reflection). Deliberately not services
  (excluded in `config/services.yaml`)
- `src/Contao/` — the Contao-specific backend code. DTOs / domain classes sit directly in `Contao/` (`BackendModule`,
  `DcaContext`, `CollectedFilters`, `ReaderResolution`; also directly in `Inspection/` and `PtableInference/`),
  Symfony services in subfolders (`config/services.yaml` has its own `Contao` block; `DataContainer/Builder` is the
  one excluded subfolder): `DataContainer/` (table classes `ContentContainer`/`FilterContainer`/`ListContainer`,
  `BackendElementContextFactory`, the DCA `Builder/`), `Inspection/` (see the inspector above), `Hook/` (Contao hooks,
  named `*Hook`), `Callback/` (DCA callbacks per table), `PtableInference/` (parent-table inference), `ListEntry/` and
  `Reader/` (both below) and `Factory/`.
  All processing of `FilterModel` into `FilterCompound` lives here: `Factory/FilterCompoundFactory::create(FilterModel)`
  (on top of the model-free core `FilterCompoundFactory`, owning value providers and the stored-value failure
  semantics), `Factory/FilterCollector` (a list's published filter rows) and
  `Factory/ListCompoundBuilderFactory::create(ListModel)`. Exception, until their own refactor: `ListCompound`,
  `ListCompoundBuilder`, `ListCompoundFactory` and `BaseListOptions` still take a `ListModel`. Hooks and callbacks of
  optional integrations stay in `src/Integration/`
- `src/Contao/ListEntry/` — entries at the Contao boundary: `EntryModelFactory::create(Entry): ?Model` (the one place
  an entry becomes a model, memoized per entry: the registry's instance for its record, else a new model from the row
  restricted to the table's columns — never registered, `save()` throws (`preventSaving()`); `null` without a model
  class; `MissingModelRefError` without `modelRef`; for Contao APIs that need
  a model, e.g. the News/Events schema.org listeners), `EntryRelationResolver` (`flare_related(entry, column)`: the
  DCA relation like `Model::getRelated()`, memoized per entry and column, no membership check;
  `UndefinedRelationError` for a column without relation) and `EntryCacheTagger` (`EntryTaggerInterface`:
  `contao.db.<table>.<id>` response tags; no-op without FOS's response tagger)
- `src/Contao/Reader/` + `Contao/ReaderResolution` — the request-scoped reader. `ReaderResolver` (`ResetInterface`)
  is the only path from a reader element and the auto-item to its entry (list → intent → engine → view → entry),
  memoized per `tl_content.id` + auto-item; a `FlareException` (also an `IntentException` for an intent that violates
  its constraints) is stored as the resolution's `failure` (anything else propagates, unmemoized); without an
  auto-item nothing is built or queried. Only the stored value itself finds the entry (`ValidationLoader` compares the
  fetched value strictly: no numeric casts, collations or trailing spaces of the database → 404). `resolve(ContentModel)` for
  `ReaderController` (failure → 5xx, no entry → 404), `resolveForPage()` for `Contao/Hook/BreadcrumbHook` (no
  resolution, a failure or no entry → items unchanged; an unexpected exception is logged, rethrown in debug mode; the
  title is escaped without insert tags). The cache lives in the service, not in request attributes (a
  fragment's sub-request never hands them back). `ReaderElementFinder` (discovery: the single visible top-level
  `flare_reader` element in the page's visible articles; the page from the main request's `pageModel`, else 4.13's
  `$GLOBALS['objPage']`; none or several → no reader; preview mode not honored) and `AutoItemReader`
  (`Input::get('auto_item', false, true)`: input-encoded, not marked as used — `ReaderController` marks it; the
  resolver reads it once per request). `ReaderResolution`: list, intent, engine, view, `?Entry`,
  `?FlareException $failure`, and `getPageMeta()` (dispatches `ReaderPageMetaEvent` once, so the breadcrumb title
  equals the `<title>`)
- `src/Reader/` — reader/detail-page URL generation (`ReaderUrlGenerator::generate(Entry)`: the auto-item field of
  `entry.row`, the stored value with Contao's input encoding reverted; only the router percent-encodes), `BackLink`,
  `ReaderPageMeta`. No request attribute any more: the reader's state lives in `Contao\Reader\ReaderResolver`
- `src/Integration/` — optional integrations (Codefog Tags, Terminal42 ChangeLanguage), wired via `config/integrations/*.yaml`
- `src/EventListener/NamedDispatch/` — re-dispatches base events under dynamic names (see Event system below)

**Key entry points:**
- `src/HeimrichHannotFlareBundle.php` — bundle entry, registers compiler passes
- `src/DependencyInjection/HeimrichHannotFlareExtension.php` — DI setup
- `src/Engine/Factory/EngineFactory.php` — creates Engine with appropriate Intent
- `src/Controller/ContentElement/ListViewController.php` / `ReaderController.php` — frontend controllers

**Extensibility via PHP 8 attributes** (compiler passes auto-register tagged services):
- `#[AsFilterElement(type: '...', isTargeted: ..., value: SomeValue::class)]` — register a filter element and
  declare the value class it consumes (null = no runtime value, intrinsic-only)
- `#[AsFilterControl(type: '...')]` — register a filter control (repeatable); the value class is the narrowed
  return type of `decode()`, not an attribute argument. Tag `flare.filter_control`
- `#[AsListDriver(type: '...', dataContainer: '...')]` — register a list driver

Attributes are in `src/DependencyInjection/Attribute/`, compiler passes in `src/DependencyInjection/Compiler/`.
Backend palettes/fields are declared in code via `DcaContract::buildDca(DcaBuilderInterface, DcaContext)` (both
tl_flare_filter and tl_flare_list). The builder interfaces (`Contao/DataContainer/Builder/`) only collect; the
concrete `DcaBuilder` is created and applied to `$GLOBALS['TL_DCA']` by the `loadDataContainer` hook
(`Contao/Hook/ElementDcaHook`), which also opens the control's palette segment (`forControl()`).

**Event system** — Events, some with aliased dispatch for targeted listening
(`flare.form.{name}.build`, `flare.filter_control.{type}.build_form` (`FilterControlBuildFormEvent`),
`flare.list_driver.{type}.build_list` (`ListDriverBuildListEvent`),
`flare.filter_element.{type}.transformers` (`FilterElementConfigureTransformersEvent`),
`flare.filter_control.{type}.transformers` (`FilterControlConfigureTransformersEvent`),
`flare.list_driver.{type}.transformers` (`ListDriverConfigureTransformersEvent`),
`flare.filter_element.{type}.dca` (`FilterElementBuildDcaEvent`), `flare.filter_control.{type}.dca`
(`FilterControlBuildDcaEvent`, builder scoped to the control segment), `flare.list_driver.{type}.dca`
(`ListDriverBuildDcaEvent`), `flare.list_driver.{type}.entries` (`ListDriverEntriesEvent`: once per non-empty entry
collection — a list page, a reader's entry, a lookup's members — before it is sealed; `{type}` is
`ListCompound::$type`; `$event->attach($name, $load)` proxies the collection's two-phase `attach()` and hands phase 1
the list's `EntryLookupInterface`), etc., implemented by the listeners in `src/EventListener/NamedDispatch/`). The
reader events (`ReaderPageMetaEvent`, `ReaderRenderEvent`, `ReaderSchemaOrgEvent`) and `ReaderUrlGeneratedEvent`
carry an `Entry $entry`, no model; the page meta changes only in `ReaderPageMetaEvent`. All events
are in `src/Event/`. Naming: a hook event is `{Component}{Hook}Event` after the hook it follows (`buildForm()` →
`FilterControlBuildFormEvent`); the named event is `flare.{component}.{type}.{suffix}`, where `{type}` is always
that component's registered type and the suffix is short (`dca`, `transformers`) unless it would be unclear
(`build_list`, `build_form`). The query events (`QueryBaseInitializedEvent`, `ModifyListQueryStructEvent`) are
left as they are until the query architecture is reworked. Elements and controls share
`FilterComponentInterface`, the type the config resolvers use.
The former per-element `FilterElementBuildingEvent`/`FilterElementBuiltEvent` (and their `.building`/`.built`
aliases) are removed; use `ListDriverBuildListEvent` or a programmatic `FilterCompound::$value` instead.
Prefer events over overriding services for customization.

**Registry pattern** — Registries in `src/Registry/` map type names to implementations: `FilterElementRegistry`, `FilterControlRegistry`, `ValueProviderRegistry`, `ListDriverRegistry`, `FilterPredicateRegistry`, `ProjectorRegistry`, `EngineModRegistry`. `FilterControlRegistry` differs from the others: it stores
`FilterControlInterface` instances together with their value class (the narrowed `decode()` return type),
registered by `RegisterFilterControlsPass` via `add($control, $type, $valueClass)`; `findTypes($valueClass)`
serves the control election (the `control` options).

**Query safety** — `FilterConditionsBuilder` (`src/Query/FilterConditionsBuilder.php`) enforces parameterized queries. `TableAliasRegistry` (`src/Query/TableAliasRegistry.php`) manages table aliases and JOINs safely.

**Contao DCA** — Backend form definitions in `contao/dca/tl_flare_*.php`. Templates in `contao/templates/`. Translations in `contao/languages/`.

## Coding Conventions

- `declare(strict_types=1);` at the top of every PHP file under `src/` and `tests/`
- PSR-12 / PER coding style, 4-space indent, 120-char line width (enforced by Mago)
- Use PHP 8 attributes over docblock annotations for registrations
- `src/Model/` is excluded from Mago linting rules

### Tooling
*   **IDE:** PHPStorm
*   **Execution:** The `php` command is likely unavailable in your shell. Use the provided `Makefile` to run commands via Docker:
    *   `make php <args>` — Runs PHP commands (e.g., `make php bin/console debug:container`)
    *   `make composer <args>` — Runs Composer commands (e.g., `make composer install`)
    *   `make phpstan` — Runs PHPStan static analysis (level 5)
    *   `make phpstan-pro` — Runs PHPStan Pro (web GUI, don't run as agent)
    *   `make semgrep-sec` — Runs the Semgrep security scan
    *   `make docs-setup` — Sets up the Docusaurus environment in the `docs/` directory
    *   `make docs-remove` — Safely removes the documentation worktree
    *   `make help` — Lists all available make commands
*   There is **no make target for Mago** (lint/format) — Mago runs in CI only.

#### Static Analysis (PHPStan)
*   **Config:** `phpstan.neon` — level 5, analyses `src/` (with a number of excludes)
*   `src/Model/` is excluded because Contao models use magic `__get`/`__set` properties
*   `src/Integration/Terminal42Languages/` is also excluded

## Testing & CI

*   **Unit tests** live in `tests/` (PHPUnit 9, configured via `phpunit.xml.dist`); run them with `make test` (optionally passing phpunit args, e.g. `make test tests/SomeTest.php`).
*   CI workflows in `.github/workflows/`:
    *   `phpunit.yaml` — PHPUnit test suite
    *   `phpstan.yaml` — PHPStan analysis
    *   `mago.yaml` — Mago lint (`--minimum-fail-level note`, PHP 8.2–8.5)
    *   `compatibility.yaml` — `composer validate` + dependency-resolution matrix (PHP 8.2–8.5 × Contao 4.13/5.x)
    *   `security.yaml` — `composer audit` + Semgrep, also on a weekly schedule

## Key Directories

*   **`src/`**: Contains the bundle's source code.
*   **`contao/`**: Contao-specific configuration.
    *   `dca/`: Data Container Arrays (Backend forms/database definition).
    *   `templates/`: Twig templates for frontend output.
    *   `config/`: `config.php` for Contao hooks/constants.
*   **`config/`**: Symfony configuration (services, routes).
*   **`translations/`**: YAML/PHP translation files for Flare messages.
*   **`dev/superpowers/`**: Design documents kept in the repo (not shipped): `specs/YYYY-MM-DD-<topic>-design.md`,
    `plans/YYYY-MM-DD-<topic>.md` and `ideas/YYYY-MM-DD-<topic>.md`. Skills that default to `docs/superpowers/`
    write here instead — `docs/` is the Docusaurus worktree (branch `docs/main`) and ignored by this repo.
