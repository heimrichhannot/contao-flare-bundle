# FLARE Architecture Overview: Projection Pattern for Contao CMS

---

## Architecture Overview

We use a specialized design pattern for generating and rendering data lists that adapts concepts from **CQRS** (Command Query Responsibility Segregation) and **ADR** (Action-Domain-Responder).

The system is based on a strict separation of responsibilities, optimized for testability, Symfony 7 autowiring, and cognitive clarity (code discovery). The architecture is divided into four logical layers:

1. **Definition (What?)**: Static description of the data source and filters (`Specification`).
2. **Context (How?)**: Specific configuration of the use case (`Context`).
3. **Processing (Who?)**: Stateless services that perform the projection (`Projector`).
4. **Result (Data)**: Stateful data objects for output (`View`).

This separation is directly reflected in the namespace structure (Specification, Context, Projector, View) to ensure clear orientation within the bundle (`HeimrichHannot\FlareBundle`).

---

## The 4 Core Components

### ListSpecification
- **Type:** Value Object / Definition Class
- **Responsibility:** Contains the static base configuration of a list and a collection of filter elements.
- **Function:** Abstractly defines query manipulations and the data source, without knowing how the data will later be rendered.

### ContextConfig
- **Type:** Value Object / DTO
- **Responsibility:** Defines the specific use case (context).
- **Examples:** `InteractiveContext` (HTML lists with pagination/forms), `ExportConfig` (CSV downloads), `ValidationConfig`.
- **Function:** Controls parameters such as pagination size, formatting, or lazy-loading behavior for a specific invocation.

### The Projector (Service / Factory)
- **Type:** Stateless Symfony Service
- **Responsibility:** Accepts a `ListSpecification` and a `ContextInterface`.
- **Function:** “Projects” the specification onto the context. Executes (optionally lazy) database queries or prepares query builders and instantiates the result object.
- **Example:** `InteractiveProjector` creates an `InteractiveView`.

### The View (Data Object / Result)
- **Type:** Data Object (stateful) — **not a service**
- **Responsibility:** Holds the final data or iterators for output.
- **Usage:** Passed to Twig templates or export functions.
- **API:** Provides methods such as `getEntries()`, `getTotal()`, etc.  
  **Important:** This is not a finished HTML string, but a data container (“view data”) consumed by templates.

---

## The Flow

- A controller or Twig function creates/loads a `ListSpecification`.
- A suitable `Context` object is created (e.g. for an interactive list).
- The corresponding projector service is called: `$projector->project($spec, $config)`.
- A `View` is returned.
- The view is used in Twig templates to render the list, or in controllers to generate content/files/reports.

---

## Standard Contexts & Use Cases

The system provides four specialized standard implementations of `ContextConfig` and their corresponding projections.

### 1. Interactive (Frontend Lists)
- **Config:** `InteractiveContext`
- **Goal:** Classic rendering of data in the browser (HTML).
- **Specialty:** Automatically processes request data (GET).
- **Features:**
  - Manages pagination (page X of Y) and pagination links (with `{list}_page` parameter in the URL).
  - Builds the Symfony filter form based on the specification.
  - Handles sorting via user input.
- **Result (`InteractiveView`):** Provides methods for `getEntries()` (iterable entities), `getPagination()` (metadata), and `getFormComponent()` (for Twig).

### 2. Aggregation (Counting & Statistics)
- **Config:** `AggregationContext`
- **Goal:** Efficient determination of total counts without loading actual data.
- **Specialty:** Performance-optimized (executes `COUNT` queries, no entity hydration).
- **Features:** Ignores pagination and sorting, but applies all filter criteria from the specification.
- **Result (`AggregationView`):** Primarily provides `getCount()`.

### 3. Validation (Checking & Selection)
- **Config:** `ValidationContext`
- **Goal:** Verify whether specific IDs or values are “visible” or valid under the current filter conditions.
- **Specialty:** Often used internally, e.g. to check foreign-key constraints or generate whitelists.
- **Features:** Accepts a list of IDs (“candidates”) to restrict the result set.
- **Result (`ValidationView`):** Provides `isValid(id)` (boolean) or `getModel(id)` for valid candidates.

### 4. Export (Data Download)
- **Config:** `ExportContext`
- **Goal:** Output all data (or a large subset) for external processing (CSV, XML, JSON).
- **Specialty:** Pagination is disabled by default (limit = 0).
- **Features:** Can use memory-optimized iterators (unbuffered queries) to avoid memory limits with large lists.
- **Result (`ExportView`):** Provides a pure data iterator optimized for `fputcsv` or serializers.

---

## Namespace Architecture

The architecture follows a layer-based structure to cleanly separate technical responsibilities (definition vs. processing vs. result). This supports Symfony autowiring and improves cognitive readability of the code.

**Root Namespace (Bundle):** `HeimrichHannot\FlareBundle`

### 1. Definition

Contains the core definition objects describing the “what”, independent of context.

- **Namespace:** `HeimrichHannot\FlareBundle\Specification`
- **Core Class:** `ListSpecification`

### 2. Context

Holds the configuration DTOs (contexts) that control the use case.

- **Namespace:** `HeimrichHannot\FlareBundle\Engine\Context`
- **Classes:**
  - `InteractiveContext`
  - `AggregationContext`
  - `ValidationContext`
  - `ExportContext`
  - *Interface:* `ContextInterface`

### 3. Processing

Contains the stateless services (projectors) that execute the logic.

- **Namespace:** `HeimrichHannot\FlareBundle\Engine\Projector`
- **Classes:**
  - `InteractiveProjector`
  - `AggregationProjector`
  - `ValidationProjector`
  - `ExportProjector`
  - *Interface:* `ProjectorInterface`

### 4. Result

Contains the stateful objects (views) that hold the processing results.

- **Namespace:** `HeimrichHannot\FlareBundle\Engine\View`
- **Classes:**
  - `InteractiveView`
  - `AggregationView`
  - `ValidationView`
  - `ExportView`
  - *Interface:* `ViewInterface`
