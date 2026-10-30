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

The core execution flow is:

```
ContentElement Controller
  → ListSpecBuilderFactory::createFromListModel(...)->build() — builds the immutable ListSpec (src/List/)
    → EngineFactory → Engine
      → Context (Interactive / Validation / Aggregation) — src/Engine/Context/
        → Loader (src/Engine/Loader/) + Mods (src/Engine/Mod/)
          → Projector (Interactive / Validation / Aggregation / Export) — orchestrates query + filter execution
            → View (formats data for Twig template)
```

Filter execution inside the projector: the `FormHarness` decodes submitted form data per filter control into value
objects; `ListQueryDirector` (`src/Query/Executor/`) picks each filter's value (with a control: the form's value;
without: the fixed `Filter::$value`), creates the `FilterContext` via `FilterContextFactory::create(ElementScope, ?value)`
(the element's `buildContext($builder, $scope, $value)` adds predicates) and runs each predicate into a `FilterConditionsBuilder`. A
filter whose element cannot be built (with or without a control) — required element config is required by the
element's schema —, a stored control that cannot be resolved or whose config is broken, a control that cannot build
its form (`Form/Factory/FilterFormBuilderFactory`: a `buildForm()` failure, a `FlareException` from a `FilterControlBuildFormEvent`
listener, an alias that is no valid form name), a broken fixed value, a `FlareException` from a `FilterCollectedEvent`
listener, or two published filters sharing an alias fail the whole list (`list.failed` in the view, HTTP 500 in the
reader); an unreadable initial value only drops the value (warning). These failures win over an invalid submission,
which shows no entries and runs no COUNT. `abort()` means only "legitimately matches nothing". Failures are
recorded as `FilterFailure`s (`src/List/`) on the `ListSpec`. A controlled filter that fails with a non-null value is retried once with value null: success drops only the value
(warning); if the retry fails too (or the value was already null) the element config is broken and the list fails
closed. `\Error`s escape unwrapped. `UndefinedConfigKeyError` (an `\Error`, thrown when a component reads a config key
its schema does not define) is a programming error: never retried, never degraded into a skipped filter or a
failed list — it fails the request. The bundle catches `\Exception`, not `\Throwable`, so no `\Error` is wrapped
or swallowed; only the transformer resolvers still wrap any `\Throwable` (a transformer casting stale stored data
may throw a `\ValueError`), and the backend `FilterInspector` catches `\Error` outside debug mode to mark the filter
as a programming error (the frontend request still fails).
Who throws what: `FilterException` (and its `UnreadableValueException`) is public API — filter components, bundled
and userland, throw it to report a filter that cannot work with its config, data or value; a component translates
the message itself where an editor may see it (`AbstractFilterElement::trans()`, `flare` domain). `FlareException` and the
other exceptions in `src/Exception/` are thrown by the framework only; userland may catch them. Every filter hook
(element `buildContext()`, control `buildForm()`/`hydrate()`/`decode()`) catches any `\Exception` and normalizes it
with `FilterException::wrap()`; a non-`FilterException` is unexpected: logged as an error. Value providers are
treated alike (`FilterFactory` wraps their exceptions; a non-`FilterException` `FlareException`, a wiring error,
passes unwrapped). A control's `buildForm()` failure fails the list (error, rethrown by the failed view in debug
mode); a `hydrate()` failure mounts the filter without its initial value (warning; an unexpected exception is
rethrown in debug mode); a `decode()` failure drops the value and puts a form error on a submitted field.

Note: Contexts and Projectors/Views are separate axes — (Export Context/Projector/View are currently not implemented).

The bundle follows standard Symfony Bundle architecture with deep Contao integration.

**Lifecycle taxonomy** — elements and list types own their lifecycle through two method families:
`configure*` methods are declarative, memoizable setup (`configureOptions` = OptionsResolver schema,
`configureTransformers` = source→canonical-config mappings); `build*` methods are per-invocation construction
(`buildDca`, `buildForm`, `buildContext`, `buildList`, `buildTableRegistry`/`buildBaseQuery`).

**Notable subsystems** (beyond the flow above):
- `src/List/` — `ListSpec` (immutable list DTO: type, dc, filters, canonical config, source; named "Spec" only
  because `List` is reserved in PHP — conceptually it is the list, as `Filter` is the filter), `ListSpecBuilder`
  (build lifecycle: driver's `buildList()` hook → `ListDriverBuildListEvent` → config assembly → schema resolution),
  `BaseListOptions` (framework-owned base schema for tl_flare_list columns)
- `src/Filter/` — `Filter` DTO (`ElementBinding` = element + type + element config `elm_*`, optional
  `ControlBinding` = control + type + control config `ctl_*`, `?ValueInterface $value`); each config is an immutable
  `ComponentConfig` (`src/Config/`, `get()` throws `UndefinedConfigKeyError` for an undefined key), elements
  (`Element/`), predicates (`Predicate/`, the public SQL-fragment layer, formerly "filter types"), collector,
  resolvers (`FilterOptionsResolver`, `FilterTransformerResolver`).
  A filter is applied in two stages: a value-free, role-typed scope — `ElementScope` for element hooks and
  contracts, `ControlScope` for control hooks (list × filter × engine context; `$scope->get('key')` reads the
  calling component's own config; `ControlScope::element(Contract::class)` / `tryElement()` return the element as a
  capability port, `forElement()` hands element contracts their scope) — handed to
  everything that happens before a value exists, and `FilterContext` (the element's product for one invocation,
  collected through the public `FilterContextBuilderInterface` and built by `FilterContextFactory` from scope +
  value, only inside `ListQueryDirector`). Hook parameter order: the subject first, the scope supplementary —
  `buildContext($builder, $scope, $value)`, `buildForm($builder, $scope)`, `decode($data, $scope)`,
  `hydrate($value, $scope)`.
  `FormHarnessFactory` builds a `FormHarness` (root form + mount↔filter map of `FilterMount`s) per list ×
  form context; the harness owns the `decode()` loop
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
  inspector (`DataContainer/Inspection/FilterInspector`) finds every filter issue of a list once, as one of four
  kinds — programming error, duplicate alias, broken (cannot be created, or conditions that cannot be built with
  the default value, through the shared `Query/Executor/FilterInvoker`, or a control that cannot build its form,
  through the shared `Form/Factory/FilterFormBuilderFactory`), ineffective; `FilterIssueReporter` shows
  them on the filter row (icon), the edit form and the list's filter overview (counts of published filters only). It inspects every saved row, builds
  the list scope the way the runtime does, and catches `\Error` outside debug mode only (logged as critical)
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
  element's `buildContext()`. Each is `final readonly`, implements `ValueInterface` and expresses its identity
  through `fingerprint()` (`ValueObjectContainmentTest` enforces this by reflection). Deliberately not services
  (excluded in `config/services.yaml`)
- `src/Reader/` — reader/detail-page URL generation (`ReaderUrlGenerator`)
- `src/InferPtable/` — parent-table inference for DCAs
- `src/Integration/` — optional integrations (Codefog Tags, Terminal42 ChangeLanguage), wired via `config/integrations/*.yaml`
- `src/EventListener/NamedDispatch/` — re-dispatches base events under dynamic names (see Event system below)

**Key entry points:**
- `src/HeimrichHannotFlareBundle.php` — bundle entry, registers compiler passes
- `src/DependencyInjection/HeimrichHannotFlareExtension.php` — DI setup
- `src/Engine/Factory/EngineFactory.php` — creates Engine with appropriate Context
- `src/Controller/ContentElement/ListViewController.php` / `ReaderController.php` — frontend controllers

**Extensibility via PHP 8 attributes** (compiler passes auto-register tagged services):
- `#[AsFilterElement(type: '...', isTargeted: ..., value: SomeValue::class)]` — register a filter element and
  declare the value class it consumes (null = no runtime value, intrinsic-only)
- `#[AsFilterControl(type: '...')]` — register a filter control (repeatable); the value class is the narrowed
  return type of `decode()`, not an attribute argument. Tag `flare.filter_control`
- `#[AsListDriver(type: '...', dataContainer: '...')]` — register a list driver

Attributes are in `src/DependencyInjection/Attribute/`, compiler passes in `src/DependencyInjection/Compiler/`.
Backend palettes/fields are declared in code via `DcaContract::buildDca(DcaBuilder, DcaContext)` (both
tl_flare_filter and tl_flare_list).

**Event system** — Events, some with aliased dispatch for targeted listening
(`flare.form.{name}.build`, `flare.filter_control.{type}.build_form` (`FilterControlBuildFormEvent`),
`flare.list_driver.{type}.build_list` (`ListDriverBuildListEvent`),
`flare.filter_element.{type}.transformers` (`FilterElementConfigureTransformersEvent`),
`flare.filter_control.{type}.transformers` (`FilterControlConfigureTransformersEvent`),
`flare.list_driver.{type}.transformers` (`ListDriverConfigureTransformersEvent`),
`flare.filter_element.{type}.dca` (`FilterElementBuildDcaEvent`), `flare.filter_control.{type}.dca`
(`FilterControlBuildDcaEvent`, builder scoped to the control segment), `flare.list_driver.{type}.dca`
(`ListDriverBuildDcaEvent`), etc., implemented by the listeners in `src/EventListener/NamedDispatch/`). All events
are in `src/Event/`. Naming: a hook event is `{Component}{Hook}Event` after the hook it follows (`buildForm()` →
`FilterControlBuildFormEvent`); the named event is `flare.{component}.{type}.{suffix}`, where `{type}` is always
that component's registered type and the suffix is short (`dca`, `transformers`) unless it would be unclear
(`build_list`, `build_form`). The query events (`QueryBaseInitializedEvent`, `ModifyListQueryStructEvent`) are
left as they are until the query architecture is reworked. Elements and controls share
`FilterComponentInterface`, the type the config resolvers use.
The former per-element `FilterElementBuildingEvent`/`FilterElementBuiltEvent` (and their `.building`/`.built`
aliases) are removed; use `ListDriverBuildListEvent` or a programmatic `Filter::$value` instead.
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
