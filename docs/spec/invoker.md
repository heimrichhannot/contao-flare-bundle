---
title: Filter Invocation
sidebar_position: 2
---

# Context-Aware Filter Invocation

## 1. Overview

This document outlines the architecture of the filter invocation system.
The system replaces legacy reflection-based logic with a compiled, extensible, and context-aware mechanism.
It allows third-party developers to provide custom invocation logic for any filter element in any context (e.g., interactive forms, aggregation).

Key goals:
*   **Performance:** Compile-time discovery of invokers.
*   **Extensibility:** Decoupled logic via attributes.
*   **Clarity:** Unified DTOs and clear responsibilities.

## 2. Core Architectural Principles

1.  **Projector Responsibility:** The Projector is the authority on *what* values are used for filtering. It gathers runtime values (from Forms, Request, Context) and intrinsic values, creating a clean value map.
2.  **Context-Driven Logic:** Filter Elements use attributes (`#[AsFilterInvoker]`) to define context-specific behavior, removing hardcoded scope checks.
3.  **Indexed Collections:** Filters are stored in an associative map where the key is a unique index.
4.  **Unified DTO:** The `FilterInvocation` DTO wraps configuration and context for the filter execution.
5.  **Targeted Query Building:** The `FilterQueryBuilder` provides a safe API to manipulate the SQL query.

## 3. The `FilterInvocation` DTO

The `FilterInvocation` object wraps all necessary context and configuration for a filter's execution.

**Class:** `HeimrichHannot\FlareBundle\Filter\FilterInvocation`

```php
readonly class FilterInvocation
{
    public function __construct(
        public FilterDefinition  $filter,
        public ListSpecification $list,
        public ContextInterface  $context,
        public mixed             $value = null,
    ) {}
    
    // Getter methods are also available: getFilterDefinition(), getListSpecification(), getContextConfig(), getValue()
}
```

## 4. The `AsFilterInvoker` Attribute

The `AsFilterInvoker` attribute is the declarative entry point for registering invocation logic.
It supports class and method targets.

**Attribute:** `HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterInvoker`

### Usage Examples

**1. Internal: On a Filter Element's Method**
The `filterType` is inferred from the class.

```php
// In: src/FilterElement/BooleanElement.php
#[AsFilterElement(...)]
class BooleanElement extends AbstractFilterElement
{
    public function __invoke(FilterInvocation $invocation, FilterQueryBuilder $qb): void
    {
        // Default invoker
    }

    #[AsFilterInvoker(context: 'interactive')]
    public function invokeForInteractive(FilterInvocation $invocation, FilterQueryBuilder $qb): void
    {
        // Logic specific to interactive context
    }
}
```

**2. External: On a Third-Party Service's Method**
The `filterType` is mandatory.

```php
class CustomInvoker
{
    #[AsFilterInvoker(filterType: BooleanElement::TYPE, context: 'interactive', priority: 10)]
    public function overrideBoolean(FilterInvocation $invocation, FilterQueryBuilder $qb): void
    {
        // Custom logic that overrides the default BooleanElement behavior in 'interactive' context
    }
}
```

## 5. System Components & Execution Flow

The invocation process is orchestrated by several specialized services.

### A. Discovery (`RegisterFilterInvokersPass`)
A Symfony Compiler Pass discovers all methods and classes tagged with `#[AsFilterInvoker]`. These are registered in the `FilterInvokerRegistry`.

### B. Resolution (`FilterInvokerResolver`)
The `FilterInvokerResolver` is responsible for finding the most appropriate invoker for a given filter type and context.
- It first looks for a specific match (`filterType` + `contextType`).
- If not found, it falls back to the default invoker for that filter type.

### C. Execution (`FilterExecutor`)
The `FilterExecutor` is the high-level service that iterates through all filters in a `ListSpecification` and executes them.

**Execution Flow:**
1. **Gather Values:** The Projector prepares a map of filter values (from request, form, or context).
2. **Loop Filters:** The `FilterExecutor` iterates over all `FilterDefinition` objects in the specification.
3. **Create Invocation:** For each filter, a `FilterInvocation` DTO is created.
4. **Resolve Invoker:** `FilterInvokerResolver` provides the callable (the invoker).
5. **Create Query Builder:** A `FilterQueryBuilder` is instantiated for the target table alias.
6. **Dispatch Event:** `FilterElementInvokingEvent` is dispatched (allows skipping or modifying the callback).
7. **Invoke:** The callback is executed: `$callback($invocation, $filterQueryBuilder)`.
8. **Dispatch Event:** `FilterElementInvokedEvent` is dispatched.

## 6. Target Aliases

Filters can target different tables within a complex query.
By default, they target `TableAliasRegistry::ALIAS_MAIN`.
If a filter is "targeted" (e.g., it belongs to a joined table), the `FilterExecutor` automatically provides a `FilterQueryBuilder` initialized with the correct table alias.
