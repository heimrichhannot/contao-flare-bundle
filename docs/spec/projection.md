---
title: Projection
sidebar_position: 1
---

# Projection Pattern

## Architecture Overview

Flare uses a specialized design pattern for generating and rendering data lists, inspired by **CQRS** (Command Query Responsibility Segregation).

The system is based on a strict separation of responsibilities:

1. **Specification (What?)**: Static description of the data source and filters.
2. **Context (How?)**: Use-case specific configuration (e.g., interactive list, aggregation).
3. **Engine (Orchestrator)**: Stateful container that binds Specification and Context.
4. **Projector (Who?)**: Stateless service that executes the projection logic.
5. **View (Result)**: Stateful data object containing the results (entries, pagination, etc.).

## The 5 Core Components

### 1. Specification (`ListSpecification`)
- **Type:** Value Object / Domain Object.
- **Responsibility:** Contains the static configuration of a list (e.g., table name, base query, filters).
- **Function:** Abstractly defines the data source without knowing the execution context.

### 2. Context (`ContextInterface`)
- **Type:** Value Object / Configuration DTO.
- **Responsibility:** Defines *how* the specification should be projected.
- **Examples:**
    - `InteractiveContext`: For HTML lists with pagination and forms.
    - `AggregationContext`: For counting or statistical calculations.
    - `ValidationContext`: For checking if specific IDs are valid within the filter scope.
    - `ExportContext`: (Future) For CSV/Excel downloads.

### 3. Engine (`Engine`)
- **Type:** Stateful Orchestrator.
- **Responsibility:** Acts as a bridge between the Specification and the Context.
- **Function:** Provides a unified entry point (`createView()`) to trigger the projection process. It uses the `ProjectorRegistry` to find the appropriate projector.

### 4. Projector (`ProjectorInterface`)
- **Type:** Stateless Symfony Service.
- **Responsibility:** Executes the actual business logic to transform a Specification and Context into a View.
- **Function:** Interacts with the database (via `SqlQueryStruct` and `FilterQueryBuilder`) to fetch or aggregate data.

### 5. View (`ViewInterface`)
- **Type:** Stateful Data Object (Result).
- **Responsibility:** Holds the results of the projection.
- **Usage:** Passed to Twig templates or returned by an API.
- **API:** Provides access to the processed data (e.g., `getEntries()`, `getPagination()`, `getCount()`).

## The Flow

1. You create or load a **Specification**.
2. You define a **Context** (e.g., `InteractiveContext` with pagination settings).
3. You instantiate an **Engine** with both: `$engine = new Engine($context, $spec, $registry);`.
4. You call `$view = $engine->createView();`.
5. The Engine delegates the work to a **Projector**, which returns the **View**.

## Standard Contexts

### Interactive Context (`InteractiveContext`)
- **Goal:** Standard frontend/backend list rendering.
- **Features:** Supports pagination, sorting, and form-based filtering.
- **View:** `InteractiveView` (provides entries and pagination).

### Aggregation Context (`AggregationContext`)
- **Goal:** Fast counting or calculations.
- **Features:** Ignores pagination and sorting for performance.
- **View:** `AggregationView` (provides the count).

### Validation Context (`ValidationContext`)
- **Goal:** Check if specific entities exist within the current filter scope.
- **Features:** Used for internal validation or access control.
- **View:** `ValidationView`.
