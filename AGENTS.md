# AGENTS.md

This file provides guidance AI coding agents, e.g., Claude Code (claude.ai/code), Gemini, and ChatGPT Codex, when working with code in this repository.

## Project

**FLARE** (Filter, List, And REader) — a Symfony bundle for Contao CMS that replaces complex module setups with a unified List/Reader content element approach.

- Namespace: `HeimrichHannot\FlareBundle\`
- Requires PHP ^8.2, Symfony ^5.4/^6.0, Contao ^4.13/^5.0
- Bundle Name: Contao Flare Bundle (`heimrichhannot/contao-flare-bundle`)
- Type: Symfony Bundle for Contao CMS
- Status: Active development (Beta/WIP).

## Architecture

The core execution flow is:

```
ContentElement Controller
  → EngineFactory → Engine
    → Context (Interactive / Validation / Aggregation / Export)
      → Projector (orchestrates query + filter execution)
        → View (formats data for Twig template)
```

The bundle follows standard Symfony Bundle architecture with deep Contao integration.

**Key entry points:**
- `src/HeimrichHannotFlareBundle.php` — bundle entry, registers compiler passes
- `src/DependencyInjection/HeimrichHannotFlareExtension.php` — DI setup
- `src/Engine/Factory/EngineFactory.php` — creates Engine with appropriate Context
- `src/Controller/ContentElement/ListViewController.php` / `ReaderController.php` — frontend controllers

**Extensibility via PHP 8 attributes** (compiler passes auto-register tagged services):
- `#[AsFilterElement(type: '...', palette: '...', formType: ...)]` — register a filter element
- `#[AsListType(type: '...', dataContainer: '...', palette: '...')]` — register a list type
- `#[AsFilterCallback(type, 'path.to.callback')]` — register a Contao DCA callback on a filter type
- `#[AsFilterInvoker]` — register a custom filter invocation handler

Attributes are in `src/DependencyInjection/Attribute/`, compiler passes in `src/DependencyInjection/Compiler/`.

**Event system** — Events, some with aliased dispatch for targeted listening (`flare.form.{name}.build`, etc.). All events are in `src/Event/`. Prefer events over overriding services for customization.

**Registry pattern** — `FilterElementRegistry`, `ListTypeRegistry`, `FilterInvokerRegistry`, `ProjectorRegistry` map type names to implementations.

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
    *   `make docs-setup` — Sets up the Docusaurus environment in the `docs/` directory
    *   `make docs-remove` — Safely removes the documentation worktree
    *   `make help` — Lists all available make commands

#### Static Analysis (PHPStan)
*   **Config:** `phpstan.neon` — level 5, analyses `src/` (with a number of excludes)
*   `src/Model/` is excluded because Contao models use magic `__get`/`__set` properties

## Key Directories

*   **`src/`**: Contains the bundle's source code.
*   **`contao/`**: Contao-specific configuration.
    *   `dca/`: Data Container Arrays (Backend forms/database definition).
    *   `templates/`: Twig templates for frontend output.
    *   `config/`: `config.php` for Contao hooks/constants.
*   **`config/`**: Symfony configuration (services, routes).
*   **`translations/`**: YAML/PHP translation files for Flare messages.
