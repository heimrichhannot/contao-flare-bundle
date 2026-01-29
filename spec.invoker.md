# Specification: Extensible Filter Invocation

## 1. Overview

This document outlines the architectural refactoring of the filter invocation system. The primary goal is to replace the current runtime Reflection-based resolver with a compiled, extensible system that allows third-party developers to provide custom invocation logic for any filter element in any context.

This will be achieved by:
1.  Enhancing the `AsFilterInvoker` attribute to be more powerful and flexible.
2.  Introducing a Symfony Compiler Pass to discover and register all invokers at compile time.
3.  Replacing the runtime resolver with one that queries a pre-populated registry.

This new architecture is more performant, significantly more extensible, and provides a clearer, more unified developer experience.

## 2. The `AsFilterInvoker` Attribute (Refactored)

The `AsFilterInvoker` attribute is the sole declarative entry point for registering invocation logic. It will be updated to support class and method targets, and to carry enough information for both internal and external use cases.

### Definition

File: `src/DependencyInjection/Attribute/AsFilterInvoker.php`

```php
<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class AsFilterInvoker
{
    /**
     * @param string|null $filterType The type of the filter element (e.g., 'flare_bool').
     *                                If null, it is inferred from the class, which must be a filter element service.
     * @param string|null $context    The context this invoker applies to (e.g., 'interactive').
     *                                If null, it's the default invoker for contexts without a specific one.
     * @param string|null $method     The public method to be called on the service.
     *                                Required when used on a class, unless the method is `__invoke`.
     * @param int         $priority   Higher priority invokers are chosen over lower priority ones for the same
     *                                filter type and context.
     */
    public function __construct(
        public ?string $filterType = null,
        public ?string $context = null,
        public ?string $method = null,
        public int $priority = 0,
    ) {}
}
```

### Usage Examples

**1. Internal: On a Filter Element's Method**
The `filterType` is inferred from the class.

```php
// In: src/FilterElement/BooleanElement.php
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterInvoker;

#[AsFilterElement(...)]
class BooleanElement extends AbstractFilterElement
{
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
// In: src/Acme/Gallery/Filter/GalleryBooleanInvoker.php
use HeimrichHannot\FlareBundle\FilterElement\BooleanElement;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterInvoker;

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
// In: src/Acme/Gallery/Filter/GallerySearchInvoker.php
use HeimrichHannot\FlareBundle\FilterElement\SearchKeywordsElement;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterInvoker;

#[AsFilterInvoker(filterType: SearchKeywordsElement::TYPE, context: 'gallery')]
class GallerySearchInvoker
{
    public function __invoke(FilterInvocation $invocation, FilterQueryBuilder $qb): void
    {
        // ... custom logic for SearchKeywordsElement in the 'gallery' context
    }
}
```

## 3. System Architecture Changes

The current runtime reflection mechanism will be replaced by a compiled registry populated via a compiler pass. This registry is then used by a new `FilterInvoker` service that provides consumers with a ready-to-use `callable`.

### A. `FilterInvokerRegistry` (New Service)

A simple service that acts as a collection for invoker configurations, populated by the compiler pass.

- **Responsibility:** Hold a structured, in-memory map of all discovered invokers.
- **Methods:**
    - `add(string $filterType, ?string $context, string $serviceId, string $method, int $priority)`: Adds an invoker to the registry.
    - `find(string $filterType, string $contextType): ?array`: Finds the highest-priority invoker configuration (`['serviceId' => string, 'method' => string]`) for a given filter type and context.

### B. `RegisterFilterInvokersPass` (New Compiler Pass)

This is the core of the new discovery mechanism. It will be registered in the bundle's `build()` method.

- **Responsibility:**
    1.  Iterate over all service definitions in the container.
    2.  Use Reflection to find `#[AsFilterInvoker]` attributes on classes and methods.
    3.  For each attribute instance, perform validation and collect the invoker configuration (`serviceId`, `method`, `filterType`, `context`, `priority`).
    4.  **Inference Logic:** If `filterType` is `null`, it must verify that the service is a filter element (e.g., `is_subclass_of($serviceClass, AbstractFilterElement::class)`) and infer the type. If not, it throws a compile-time `InvalidArgumentException`.
    5.  **Validation Logic:**
        - Throw an error if `filterType` is specified on an attribute decorating a filter element class/method (as it's redundant).
        - Throw an error if `method` is specified on an attribute decorating a method.
    6.  Populate the `FilterInvokerRegistry` service definition with the collected invoker configurations.
    7.  Collect all invoker service IDs and pass them to the `FilterInvoker` service constructor via a `ServiceLocator`.

### C. `FilterInvokerResolver` (Refactored, Internal Service)

The resolver is a simple, internal service that queries the registry.

- **Dependencies:** `FilterInvokerRegistry`.
- **Signature:** `resolve(string $filterType, string $contextType): ?array`.
- **Return Value:** A tuple `['serviceId' => string, 'method' => string]` or `null`.
- **Logic:**
    1.  Query the registry for an invoker matching the specific `$filterType` and `$contextType`.
    2.  If none is found, query again for a default invoker matching the `$filterType` and a `null` context.
    3.  Return the highest-priority result or `null`.

### D. `FilterInvoker` (New Service)

This is the new primary public-facing service for executing filter logic. It encapsulates all the resolution and fallback logic.

- **Dependencies:**
    - `FilterInvokerResolver`
    - `FilterElementRegistry` (to get the base filter element for fallbacks)
    - `ServiceLocator $invokerLocator` (containing all invoker services, provided by the compiler pass)
- **Primary Method:** `get(string $filterType, string $contextType): ?callable`
- **Logic of `get()`:**
    1.  Call the `invokerResolver` to find a custom invoker config (`['serviceId', 'method']`).
    2.  **If a config is found:**
        - Get the invoker service from the `$invokerLocator` using the `serviceId`.
        - Return the `callable` `[$service, $method]`.
    3.  **If no config is found (fallback):**
        - Get the base filter element service from the `FilterElementRegistry` using the `$filterType`.
        - If `method_exists($elementService, '__invoke')`, return the `callable` `[$elementService, '__invoke']`.
    4.  If no invoker can be resolved, return `null`.

## 4. Execution Flow

With the new `FilterInvoker` service, consumer services like `ListQueryManager` no longer need to know about the underlying resolution mechanism. The execution flow is greatly simplified.

```php
// Inside a service like ListQueryManager...

// Dependency: private FilterInvoker $filterInvoker;

$filterType = $filter->getType();
$contextType = $contextConfig::getContextType();

// 1. Get the callable invoker
$invoker = $this->filterInvoker->get($filterType, $contextType);

// 2. Execute if found
if ($invoker) {
    $invoker($invocation, $qb);
} else {
    // No invoker found, handle appropriately (e.g., log a warning or skip)
}
```
