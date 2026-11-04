---
title: Projection
sidebar_position: 1
---

# Projection Pattern

## Architecture Overview

Flare uses a specialized design pattern for generating and rendering data lists, inspired by **CQRS** (Command Query Responsibility Segregation).

The system is based on a strict separation of responsibilities:

1. **List (What?)**: The assembled list — data source, filters and config (`ListCompound`).
2. **Intent (What for?)**: What the list is rendered for, with its parameters (e.g., interactive list, aggregation).
3. **Engine (Orchestrator)**: Stateful container that binds List and Intent.
4. **Projector (Who?)**: Stateless service that executes the projection logic.
5. **View (Result)**: Stateful data object containing the results (entries, pagination, etc.).

## The 5 Core Components

### 1. List (`ListCompound`)
- **Type:** Value Object / Domain Object.
- **Responsibility:** Contains the static configuration of a list (e.g., table name, base query, filters —
  a keyed map of immutable [`FilterCompound` value objects](./filtering.md#1-the-filtercompound-value-object)).
- **Function:** Abstractly defines the data source without knowing the intent.

### 2. Intent (`IntentInterface`)
- **Type:** Value Object / Configuration DTO.
- **Responsibility:** Defines *what for* the list is projected, and with it the expected output.
- **Examples:**
    - `InteractiveIntent`: For HTML lists with pagination and forms.
    - `AggregationIntent`: For counting or statistical calculations.
    - `ValidationIntent`: For checking if specific IDs are valid within the filter scope.
    - `ExportIntent`: (Planned) For CSV/Excel downloads. An `ExportProjector` and `ExportView` already exist, but no export intent is wired up yet.

### 3. Engine (`Engine`)
- **Type:** Stateful Orchestrator.
- **Responsibility:** Acts as a bridge between the List and the Intent.
- **Function:** Provides a unified entry point (`createView()`) to trigger the projection process. It uses the `ProjectorRegistry` to find the appropriate projector.

### 4. Projector (`ProjectorInterface`)
- **Type:** Stateless Symfony Service.
- **Responsibility:** Executes the actual business logic to transform a List and an Intent into a View.
- **Function:** Interacts with the database (via `SqlQueryStruct` and `FilterConditionsBuilder`) to fetch or aggregate data.

### 5. View (`ViewInterface`)
- **Type:** Stateful Data Object (Result).
- **Responsibility:** Holds the results of the projection.
- **Usage:** Passed to Twig templates or returned by an API.
- **API:** Provides access to the processed data. `ViewInterface` itself is an empty marker interface — the actual API lives on the concrete views and differs per context: `InteractiveView` offers `getEntries()`, `getPaginator()`, `getCount()`, `getForm()`, and more, while `AggregationView` only exposes `getCount()`.

## The Flow

1. You create or load a **List**.
2. You define an **Intent** (e.g., `InteractiveIntent` with pagination settings).
3. You create an **Engine** from both via the factory service: `$engine = $engineFactory->createEngine($intent, $list);`.
4. You call `$view = $engine->createView();`.
5. The Engine delegates the work to a **Projector**, which returns the **View**.

## Standard Intents

### Interactive Intent (`InteractiveIntent`)
- **Goal:** Standard frontend/backend list rendering.
- **Features:** Supports pagination, sorting, and form-based filtering.
- **View:** `InteractiveView` (provides entries and pagination).

### Aggregation Intent (`AggregationIntent`)
- **Goal:** Fast counting or calculations.
- **Features:** Ignores pagination and sorting for performance.
- **View:** `AggregationView` (provides the count).

### Validation Intent (`ValidationIntent`)
- **Goal:** Check if specific entities exist within the current filter scope.
- **Features:** Used for internal validation or access control.
- **View:** `ValidationView`.
