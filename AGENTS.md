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
  → ListBuilderFactory::createFromListModel(...)->build() — builds the immutable ListSpec (src/Lists/)
    → EngineFactory → Engine
      → Context (Interactive / Validation / Aggregation) — src/Engine/Context/
        → Loader (src/Engine/Loader/) + Mods (src/Engine/Mod/)
          → Projector (Interactive / Validation / Aggregation / Export) — orchestrates query + filter execution
            → View (formats data for Twig template)
```

Note: Contexts and Projectors/Views are separate axes — (Export Context/Projector/View are currently not implemented).

The bundle follows standard Symfony Bundle architecture with deep Contao integration.

**Lifecycle taxonomy** — elements and list types own their lifecycle through two method families:
`configure*` methods are declarative, memoizable setup (`configureOptions` = OptionsResolver schema,
`configureTransformers` = source→canonical-config mappings); `build*` methods are per-invocation construction
(`buildDca`, `buildForm`, `buildFilter`, `buildList`, `buildTableRegistry`/`buildBaseQuery`).

**Notable subsystems** (beyond the flow above):
- `src/Lists/` — `ListSpec` (immutable list DTO: type, dc, filters, canonical config, source), `ListBuilder`
  (build lifecycle: type's `buildList()` hook → `ListBuildEvent` → config assembly → schema resolution),
  `BaseListOptions` (framework-owned base schema for tl_flare_list columns)
- `src/Filter/` — `Filter` DTO, elements (`Element/`), types (`Type/`), collector, resolvers
  (`FilterOptionsResolver`, `FilterTransformerResolver`, `FilterElementResolver`), `FilterContextFactory`
- `src/Config/` — `ConfigBuilder` (fluent canonical-config accumulator; no cast helpers — transformers cast
  declaratively off the typed model) and `TransformerBuilder` (source class → transformer map)
- `src/Form/` — filter form building (FilterFormFactory etc.)
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
- `#[AsFilterElement(type: '...', intrinsicOnly: ..., isTargeted: ...)]` — register a filter element
- `#[AsListType(type: '...', dataContainer: '...')]` — register a list type

Attributes are in `src/DependencyInjection/Attribute/`, compiler passes in `src/DependencyInjection/Compiler/`.
Backend palettes/fields are declared in code via `DcaContract::buildDca(DcaBuilder, DcaContext)` (both
tl_flare_filter and tl_flare_list).

**Event system** — Events, some with aliased dispatch for targeted listening (`flare.form.{name}.build`,
`flare.list.{type}.build`, `flare.filter_element.{type}.transformers`, `flare.filter_element.{type}.dca` /
`flare.list.{type}.dca`, etc., implemented by the listeners in `src/EventListener/NamedDispatch/`). All events
are in `src/Event/`. Prefer events over overriding services for customization.

**Registry pattern** — Registries in `src/Registry/` map type names to implementations: `FilterElementRegistry`, `ListTypeRegistry`, `FilterTypeRegistry`, `ProjectorRegistry`, `EngineModRegistry`.

**Query safety** — `FilterQueryBuilder` (`src/Query/FilterQueryBuilder.php`) enforces parameterized queries. `TableAliasRegistry` (`src/Query/TableAliasRegistry.php`) manages table aliases and JOINs safely.

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

*   **Unit tests** live in `tests/` (PHPUnit 9); run them with `make php vendor/bin/phpunit tests`. There is no `phpunit.xml` and no test CI workflow yet, and no `make test` target.
*   CI workflows in `.github/workflows/`:
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
