# Specification: Extensible Filter Invocation

## 1. Overview

This document outlines the architectural refactoring of the filter invocation system. The primary goal was to replace the former reflection-based resolver with a compiled, extensible system that allows third-party developers to provide custom invocation logic for any filter element in any context.

This is achieved by:
1.  Implementation of a powerful and flexible `AsFilterInvoker` attribute.
2.  Introduction of a Symfony Compiler Pass to discover and register all invokers at compile time.
3.  Replacement of the runtime resolver with one that queries a pre-populated registry.

This new architecture is more performant, significantly more extensible, and provides a clearer, more unified developer experience.

## 2. The `AsFilterInvoker` Attribute

The `AsFilterInvoker` attribute is the sole declarative entry point for registering invocation logic. It was updated to support class and method targets, and to carry enough information for both internal and external use cases.

Implemented in `src/DependencyInjection/Attribute/AsFilterInvoker.php`.

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

The runtime reflection mechanism was replaced by a compiled registry populated via a compiler pass.
This registry is used by the `FilterInvoker` service that provides consumers with a ready-to-use `callable`.

### A. `FilterInvokerRegistry`

A simple service that acts as a collection for invoker configurations, populated by the compiler pass.

- **Responsibility:** Hold a structured, in-memory map of all discovered invokers.
- Implemented in `src/Registry/FilterInvokerRegistry.php`.

### B. `RegisterFilterInvokersPass`

This is the core of the novel discovery mechanism. Registered in the bundle's `build()` method.

Implemented in `src/DependencyInjection/RegisterFilterInvokersPass.php`.

- **Responsibility:**
    1.  Iterates over all service definitions in the container.
    2.  Use Reflection to find `#[AsFilterInvoker]` attributes on classes and methods.
    3.  For each attribute instance, performs validation and collects the invoker configuration (`serviceId`, `method`, `filterType`, `context`, `priority`).
    4.  **Inference Logic:**
        - If given, uses the `filterType` to infer the service ID.
        - If `filterType` is `null`, verifies that the service is a filter element and infers the type.
          (Note: Filter element services do not necessarily extend `AbstractFilterElement`. It must instead be decorated with the `#[AsFilterElement]` attribute.)
        - If `filterType` is not given and cannot be inferred, it throws a compile-time `InvalidArgumentException`.
    5.  Populates the `FilterInvokerRegistry` service definition with the collected invoker configurations.
    6.  Collects all invoker service IDs and passes them to the `FilterInvoker` service constructor via a `ServiceLocator`.

### C. `FilterInvoker`

This is the novel primary public-facing service for executing filter invocation logic.
It encapsulates all the resolution and fallback logic.

Implemented in `src/Filter/Invoker/FilterInvoker.php`

- **Primary Method:** `get(string $filterType, string $contextType): ?callable`
- **Logic of `get()`:**
    1.  Calls the `invokerResolver` to find a custom invoker config (`['serviceId', 'method']`).
    2.  **If a config is found:**
        - Gets the invoker service from the `$invokerLocator` using the `serviceId`.
        - Returns the `callable` `[$service, $method]`.
    3.  **If no config is found (fallback):**
        - Gets the base filter element service from the `FilterElementRegistry` using the `$filterType`.
        - Retrieves the invoker `$method` from the base element service (default is `__invoke`).
        - If `method_exists($elementService, $method)`, returns the `callable` `[$elementService, $method]`.
    4.  If no invoker can be resolved, returns `null`.

## 4. Execution Flow

With the new `FilterInvoker` service, consumer services like `ListQueryManager` no longer need to know about the
underlying resolution mechanism. The execution flow is greatly simplified.

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
    // No invoker found, handle appropriately (e.g., log a warning, throw an exception, or skip)
}
```
