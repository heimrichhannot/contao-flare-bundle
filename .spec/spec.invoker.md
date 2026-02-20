# Specification: Context-Aware Filter Invocation

## 1. Overview

This document outlines the architecture of the filter invocation system.
The system replaces legacy reflection-based logic with a compiled, extensible, and context-aware mechanism.
It allows third-party developers to provide custom invocation logic for any filter element in any context (e.g., interactive forms, gallery viewers, API endpoints).

Key goals:
*   **Performance:** Compile-time discovery of invokers.
*   **Extensibility:** Decoupled logic via attributes.
*   **Clarity:** Unified DTOs and clear responsibilities.

## 2. Core Architectural Principles

1.  **Projector Responsibility:** The Projector is the authority on *what* values are used for filtering. It gathers runtime values (from Forms, Request, Config) and intrinsic values, creating a clean value map before invoking filters.
2.  **Context-Driven Logic:** Filter Elements use attributes (`#[AsFilterInvoker]`) to define context-specific behavior, removing hardcoded scope checks (e.g., ~`if ($context->isList())`~).
3.  **Indexed Collections:** Filters are stored in an associative map where the key is a unique index. This allows filters to override each other (e.g., a Database filter named 'author' can be overridden by a Manual filter named 'author').
4.  **Unified DTO:** The `FilterInvocation` DTO wraps configuration and context for the filter execution.
5.  **Specification-Driven:** `ListSpecification` is the domain object source of truth.

## 3. The `FilterInvocation` DTO

The `FilterInvocation` object wraps all necessary context and configuration for a filter's execution, *excluding* the Query Builder (which is passed separately to distinguish "Context" from "Action Target").

**Class:** `HeimrichHannot\FlareBundle\Filter\FilterInvocation`

```php
class FilterInvocation
{
    public function __construct(
        public readonly FilterDefinition $filter,
        public readonly ListSpecification $list,
        public readonly ContextConfigInterface $config,
        public readonly mixed $value = null,
    ) {}
    
    // Getter methods are also available...
}
```

## 4. The `AsFilterInvoker` Attribute

The `AsFilterInvoker` attribute is the declarative entry point for registering invocation logic.
It supports class and method targets and carries enough information for both internal and external use cases.

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
        // ... the default invoker for any context for which no specific invocation method is registered
        // ... which class method to call (instead of __invoke) can be defined with the AsFilterElement attribute 
    }

    #[AsFilterInvoker(context: 'interactive')]
    public function invokeForInteractive(FilterInvocation $invocation, FilterQueryBuilder $qb): void
    {
        // ... logic for interactive context
    }
}
```

**2. External: On a Third-Party Service's Method**
The `filterType` is mandatory.

```php
class GalleryBooleanInvoker
{
    #[AsFilterInvoker(filterType: BooleanElement::TYPE, context: 'gallery')]
    public function extendBooleanFilter(FilterInvocation $invocation, FilterQueryBuilder $qb): void
    {
        // ... custom logic for BooleanElement in the 'gallery' context
    }
}
```

**3. External: On a Third-Party Service's Class**
The `filterType` is mandatory, and the `method` defaults to `__invoke`.

```php
#[AsFilterInvoker(filterType: SearchKeywordsElement::TYPE, context: 'gallery')]
class GallerySearchInvoker
{
    public function __invoke(FilterInvocation $invocation, FilterQueryBuilder $qb): void
    {
        // ... logic
    }
}
```

## 5. System Components & Discovery

The runtime reflection mechanism is replaced by a compiled registry populated via a compiler pass.

### A. Context Configuration (`ContextConfigInterface`)
Contexts must provide a unique identity to match against the `AsFilterInvoker` attribute.

```php
interface ContextConfigInterface
{
    /**
     * Returns the unique machine name of this context type (e.g., 'interactive').
     */
    public static function getContextType(): string;
}
```

### B. Filter Collection & Indexing
Filters are managed in a `FilterDefinitionCollection` which functions as an associative array.

*   **Collection:** Supports `set(?string $key, FilterDefinition $filter)`.
*   **Indexing Strategy:** Collectors (like `ListModelFilterCollector`) determine the key.
    For example, database filters use their form field name as the key, allowing them to override or be overridden by other filters sharing the same name.

### C. Intrinsic Values (`IntrinsicValueContract`)
Allows the Projector to retrieve default values from intrinsic filters without knowing internal implementation details.

```php
interface IntrinsicValueContract
{
    public function getIntrinsicValue(FilterDefinition $definition): mixed;
}
```

### D. `RegisterFilterInvokersPass`
A Symfony Compiler Pass that discovers and registers invokers.

*   **Responsibility:**
    1.  Iterates service definitions.
    2.  Finds `#[AsFilterInvoker]` attributes.
    3.  Infers `filterType` if missing (for internal elements).
    4.  Populates the `FilterInvokerRegistry`.

### E. `FilterInvoker` Service
The primary public-facing service for executing filter invocation logic.

*   **Service:** `src/Filter/Invoker/FilterInvoker.php`
*   **Logic:**
    1.  Queries the registry for a specific custom invoker (`filterType` + `contextType`).
    2.  If found, returns that callable.
    3.  **Fallback:** Checks the base Filter Element service for a method matching the default invocation (usually `__invoke` or derived from context).
    4.  Returns `null` if no invoker is resolved.

## 6. Execution Flow

The consumer (typically `ListQueryManager`) orchestrates the process using the values provided by the Projector.

```php
// 1. Projector gathers values (Projector Responsibility)
$filterValues = [];
foreach ($spec->getFilters()->all() as $key => $filter) {
   // ... resolves values from request/form/intrinsics using the Key ...
   $filterValues[$key] = $value;
}

// 2. Manager Invokes Filters
// Inside ListQueryManager::invokeFilters(...)
$contextType = $contextConfig::getContextType();

foreach ($spec->getFilters()->all() as $key => $filter) {
    $value = $filterValues[$key] ?? null;

    // Create DTO
    $invocation = new FilterInvocation($filter, $spec, $contextConfig, $value);

    // Get Invoker
    $callback = $this->filterInvoker->get($filter->getType(), $contextType);

    // Execute
    if ($callback) {
        $callback($invocation, $qb);
    }
}
```