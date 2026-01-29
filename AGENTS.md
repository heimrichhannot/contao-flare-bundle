# Contao Flare Bundle

You are a senior software architect and developer, an expert in Symfony 7 and PHP 8 in the context of the Contao CMS.

We are developing a Contao extension called FLARE (acronym for “Filter, List, and Reader”).

## Project Overview

- **Name:** Contao Flare Bundle (`heimrichhannot/contao-flare-bundle`)
- **Type:** Symfony Bundle for Contao CMS
- **Purpose:** A powerful and flexible extension for filtering, listing, and reading entities (such as News, Events, or generic data containers) in the Contao frontend. It aims to replace complex module setups with a unified "List" and "Reader" approach configured via the backend.
- **Status:** Active development (Beta/WIP).

## Key Technologies

*   **Language:** PHP ^8.2
*   **Framework:** Symfony (extensively uses Forms, Dependency Injection, EventDispatcher)
*   **CMS:** Contao Open Source CMS (^4.13 || ^5.0)
*   **Templating:** Twig
*   **Database:** Doctrine DBAL
*   **Quality Assurance:** Mago (Linter/Formatter), PHPStan

## Architecture

The bundle follows standard Symfony Bundle architecture with deep Contao integration.

*   **Bundle Entry Point:** `src/HeimrichHannotFlareBundle.php`. Registers several Compiler Passes to handle the extensible plugin architecture (`RegisterFilterElementsPass`, `RegisterListTypesPass`, etc.).
*   **Dependency Injection:** Services are defined in `config/services.yaml` and loaded via `src/DependencyInjection/HeimrichHannotFlareExtension.php`.
*   **Extensibility:** The bundle is designed to be extended.
    *   **Custom Filters:** specific classes marked with `#[AsFilterElement]`.
    *   **Custom Lists:** specific classes marked with `#[AsListType]`.
*   **Events:** A comprehensive event system allows hooking into almost every stage of the process (Form Generation, Item Retrieval, Filter Invocation, Rendering).
    *   Namespace: `HeimrichHannot\FlareBundle\Event`

## Development & Conventions

### Build & Install
*   **Installation:** `composer require heimrichhannot/contao-flare-bundle`
*   **Tests:** none
*   **Linting/Formatting:** The project uses `mago`. Configuration is in `mago.toml`.

### Coding Standards
*   Follow PSR-12/PER Coding Style.
*   **Attributes over Annotations:** Use PHP 8 attributes for registration (e.g., `#[AsFilterElement]`).
*   **Strict Typing:** `declare(strict_types=1);` is recommended.

### Key Directories

*   **`src/`**: Contains the bundle's source code.
    *   `Filter/`: Core logic for filter context, query building, and invocation.
    *   `FilterElement/`: Concrete implementations of filter elements (e.g., `BooleanElement`, `DateRangeElement`).
    *   `ListType/`: Concrete implementations of list sources (e.g., `NewsListType`, `EventsListType`).
    *   `Event/`: Event classes.
    *   `Controller/`: Frontend controllers.
*   **`contao/`**: Contao-specific configuration.
    *   `dca/`: Data Container Arrays (Backend forms/database definition).
    *   `templates/`: Twig templates for frontend output.
    *   `config/`: `config.php` for Contao hooks/constants.
*   **`config/`**: Symfony configuration (services, routes).
*   **`translations/`**: XLF/YAML translation files.

## Usage by Contao

1.  **Backend:** Create a "List" configuration under **Layout > Lists FLARE**.
2.  **Filters:** Add children to the List configuration to define filters.
3.  **Frontend:**
    *   Use the **Flare List** content element to display the list.
    *   Use the **Flare Reader** content element to display details.
