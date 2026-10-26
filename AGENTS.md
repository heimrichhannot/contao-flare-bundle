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
  → ListBuilderFactory::createFromListModel(...)->build() — builds the immutable ListSpec (src/List/)
    → EngineFactory → Engine
      → Context (Interactive / Validation / Aggregation) — src/Engine/Context/
        → Loader (src/Engine/Loader/) + Mods (src/Engine/Mod/)
          → Projector (Interactive / Validation / Aggregation / Export) — orchestrates query + filter execution
            → View (formats data for Twig template)
```

Filter execution inside the projector: the `FormHarness` decodes submitted form data per filter control into value
objects; `ListQueryDirector` (`src/Query/Executor/`) picks each filter's value (with a control: the form's value;
without: the fixed `Filter::$value`), creates the `FilterContext` via `FilterContextFactory::create(FilterScope, ?value)`
(the element's `buildContext()` adds predicates) and runs each predicate into a `FilterConditionsBuilder`. A
filter whose element cannot be built (with or without a control), a broken fixed value, or two published filters
sharing an alias fail the whole list (`list.failed` in the view, HTTP 500 in the reader); a control that cannot be
resolved only drops the control and initial value (warning). A controlled filter that fails with a non-null value is retried once with value null: success drops only the value
(warning); if the retry fails too (or the value was already null) the element config is broken and the list fails
closed. `\Error`s escape unwrapped.

Note: Contexts and Projectors/Views are separate axes — (Export Context/Projector/View are currently not implemented).

The bundle follows standard Symfony Bundle architecture with deep Contao integration.

**Lifecycle taxonomy** — elements and list types own their lifecycle through two method families:
`configure*` methods are declarative, memoizable setup (`configureOptions` = OptionsResolver schema,
`configureTransformers` = source→canonical-config mappings); `build*` methods are per-invocation construction
(`buildDca`, `buildForm`, `buildContext`, `buildList`, `buildTableRegistry`/`buildBaseQuery`).

**Notable subsystems** (beyond the flow above):
- `src/List/` — `ListSpec` (immutable list DTO: type, dc, filters, canonical config, source), `ListBuilder`
  (build lifecycle: type's `buildList()` hook → `ListBuildEvent` → config assembly → schema resolution),
  `BaseListOptions` (framework-owned base schema for tl_flare_list columns)
- `src/Filter/` — `Filter` DTO (element + element config `elm_*`, optional control + control config `ctl_*`,
  `?ValueInterface $value`), elements (`Element/`), predicates (`Predicate/`, the public SQL-fragment
  layer, formerly "filter types"), collector, resolvers (`FilterOptionsResolver`, `FilterTransformerResolver`).
  A filter is applied in two stages: `FilterScope` (value-free triple list × filter × engine context; handed to
  everything that happens before a value exists) and `FilterContext` (the element's product for one
  invocation, built by `FilterContextFactory` from scope + value, only inside `ListQueryDirector`).
  `FormHarnessFactory` builds a `FormHarness` (root form + mount↔filter map of `FilterMount`s) per list ×
  form context; the harness owns the `decode()` loop
- `src/Filter/Control/` — `FilterControl` implementations (peers of `src/Filter/Element/`) behind
  `FilterControlInterface` (`buildForm()`, `decode()`, `hydrate()`), registered with `#[AsFilterControl]`.
  Controls bind to a value *class* (the narrowed return type of `decode()`), never to an element type:
  `flare_choice`, `flare_checkbox`, `flare_choice_bool`, `flare_date_range`, `flare_keywords`. "Form" names only
  the Symfony level (`FormHarness`, `FilterFormBuilder`, mount). `src/Form/` — Symfony-level building blocks
  (`FormHarness`, `FilterMount`, `ChoicesBuilder`, `Form/Type/DateRangeFormType`)
- `src/Contract/FilterElement/` — capability ports an element implements for controls/providers to pull from
  (`ChoiceSourceContract`, `DateBoundsContract`); controls never reach into an element otherwise
- `src/Filter/ValueProvider/` — one `ValueProviderInterface` service per value class (`provide()` turns the
  stored `value` column into a value object; `buildDcaField()` renders the backend widget), registered via the
  `flare.value_provider` tag and `ValueProviderRegistry`
- `src/Migration/` — `RenameFilterColumnsMigration` (`elm_`/`ctl_` renames), `BackfillFilterValuesMigration`
  (run-once per row via the migration-managed `legacyBackfilled` marker column), `RenameContentListColumnMigration`.
  Migrations never delete; legacy columns stay until deletes are run manually
- `src/Config/` — `ConfigBuilder` (fluent canonical-config accumulator; no cast helpers — transformers cast
  declaratively off the typed model) and `TransformerResolver` (source class → transformer map)
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
(`flare.form.{name}.build`, `flare.filter_control.{type}.built` (`FilterControlBuiltEvent`),
`flare.list.{type}.build`, `flare.filter_element.{type}.transformers` / `flare.filter_control.{type}.transformers`
(`FilterTransformerEvent`), `flare.list.{type}.dca` (`ListDcaEvent`), `flare.filter_element.{type}.dca`
(`FilterElementDcaEvent`), `flare.filter_control.{type}.dca` (`FilterControlDcaEvent`, control type, builder scoped to
the control segment), etc., implemented by the listeners in `src/EventListener/NamedDispatch/`). All events are in
`src/Event/`. Elements and controls share `FilterComponentInterface`, the type the config resolvers and
`FilterTransformerEvent::$owner` use.
The former per-element `FilterElementBuildingEvent`/`FilterElementBuiltEvent` (and their `.building`/`.built`
aliases) are removed; use `ListBuildEvent` or a programmatic `Filter::$value` instead.
Prefer events over overriding services for customization.

**Registry pattern** — Registries in `src/Registry/` map type names to implementations: `FilterElementRegistry`, `FilterControlRegistry`, `ValueProviderRegistry`, `ListDriverRegistry`, `FilterPredicateRegistry`, `ProjectorRegistry`, `EngineModRegistry`. `FilterControlRegistry` differs from the others: it stores
`FilterControlInterface` instances together with their value class (the narrowed `decode()` return type),
registered by `RegisterFilterControlsPass` via `add($control, $type, $valueClass)`; `findTypes($valueClass)`
serves the control election (the `control` options).

**Query safety** — `FilterConditionsBuilder` (`src/Query/FilterConditionsBuilder.php`) enforces parameterized queries. `TableAliasRegistry` (`src/Query/TableAliasRegistry.php`) manages table aliases and JOINs safely.

**Contao DCA** — Backend form definitions in `contao/dca/tl_flare_*.php`. Templates in `contao/templates/`. Translations in `contao/languages/`.

## Coding Conventions

- `declare(strict_types=1);` at the top of every PHP file
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
