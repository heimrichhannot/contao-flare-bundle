# Architecture Refactoring Specification: Context-Aware Filter Invocation

## 1. Overview
This specification details the architectural changes required to refactor the Filter Invocation system within the `contao-flare-bundle`. The goal is to decouple filter logic from the calling context, enable dynamic context registration (e.g., third-party Projectors), and centralize responsibility for value retrieval within the Projector.

## 2. Core Architectural Principles
1.  **Projector Responsibility:** The Projector is the ultimate authority on *what* values are used for filtering. It is responsible for gathering runtime values (from Forms, Request, Config) and Intrinsic values (Defaults) and passing them to the `ListQueryManager`.
2.  **Context-Driven Logic:** Filter Elements are stateless services that can define specific behaviors for different contexts (e.g., "Interactive" vs "Export") using Attributes (`#[AsFilterInvoker]`), avoiding hardcoded `if ($context->isList())` checks.
3.  **Unified DTO:** All necessary data for filtering is encapsulated in a single, typed DTO (`FilterInvocation`), replacing legacy loose arguments and `FilterContext`.
4.  **Specification-Driven:** The `ListSpecification` (domain object) is the source of truth for filter definitions, replacing direct dependencies on Contao Models (`ListModel`) within the filter logic.

## 3. New Components

### A. The Invocation DTO (`FilterInvocation`)
Replaces `FilterContext` and loose arguments. Wraps all dependencies required by a filter to modify the query.

```php
namespace HeimrichHannot\FlareBundle\Dto;

class FilterInvocation
{
    public function __construct(
        public readonly FilterDefinition $definition, // Configuration (field, operator)
        public readonly FilterQueryBuilder $qb,       // Target SQL builder
        public readonly ListSpecification $spec,      // Global list context (tables)
        public readonly ContextConfigInterface $contextConfig, // Context identity & options
        public readonly mixed $value = null,          // The value to filter by
    ) {}
}
```

### B. The Invoker Attribute (`AsFilterInvoker`)
Allows methods on a `FilterElement` service to claim responsibility for specific contexts.

```php
namespace HeimrichHannot\FlareBundle\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
class AsFilterInvoker
{
    public function __construct(
        public ?string $context = null, // e.g., 'interactive', 'export'. Null = Default.
        public int $priority = 0        // Higher priority wins.
    ) {}
}
```

### C. Intrinsic Value Interface (`IntrinsicValueContract`)
Allows the Projector to standardly retrieve default values from intrinsic filters without knowing internal implementation details.

```php
namespace HeimrichHannot\FlareBundle\Contract\FilterElement;

interface IntrinsicValueContract
{
    /**
     * Returns the intrinsic value (e.g. preselect) from the definition.
     */
    public function getIntrinsicValue(FilterDefinition $definition): mixed;
}
```

### D. Context Configuration Updates
`ContextConfigInterface` must provide a unique identity to match against the `AsFilterInvoker` attribute.

```php
interface ContextConfigInterface
{
    /**
     * Returns the unique machine name of this context type (e.g., 'interactive').
     */
    public static function getContextType(): string;
}
```

## 4. Execution Flow

### Step 1: Projector Gathers Values
The Projector (e.g., `InteractiveProjector`) prepares the filter values map before invoking the manager.

```php
$filterValues = [];

// 1. Get Runtime Values (e.g. from Form)
if ($form->isSubmitted()) {
    $filterValues = $form->getData();
}

// 2. Get Intrinsic Values
foreach ($spec->getFilters() as $filter) {
    if ($filter->isIntrinsic()) {
        $element = $this->registry->get($filter->getType());
        if ($element instanceof IntrinsicValueContract) {
            // Projector actively retrieves the default value
            $filterValues[$filter->getFilterFormFieldName()] = $element->getIntrinsicValue($filter);
        }
    }
}

// 3. Call Manager with values
$this->listQueryManager->populate($builder, $spec, $contextConfig, $filterValues);
```

### Step 2: ListQueryManager Dispatches
The `ListQueryManager` resolves the correct method to execute on the Filter Element.

```php
// Inside ListQueryManager::invokeFilters
$contextType = $contextConfig::getContextType(); // e.g. 'interactive'

foreach ($spec->getFilters() as $filter) {
    // ... Scope/Applicability Checks (from DCA config) ...

    $element = $this->registry->get($filter->getType());
    
    // Resolve Value from the map provided by Projector
    $value = $filterValues[$filter->getFilterFormFieldName()] ?? null;
    
    $invocation = new FilterInvocation($filter, $qb, $spec, $contextConfig, $value);

    // Find best method via Reflection/Metadata matching $contextType
    $method = $this->resolver->resolveInvoker($element, $contextType);
    
    $element->$method($invocation);
}
```

## 5. Migration Strategy

1.  **Infrastructure:**
    *   Create `FilterInvocation` DTO.
    *   Create `AsFilterInvoker` Attribute.
    *   Create `IntrinsicValueContract` Interface.
    *   Update `ContextConfigInterface` to include `getContextType()`.

2.  **Manager Update:**
    *   Update `ListQueryManager::populate` to accept `array $filterValues`.
    *   Implement method resolution logic in `ListQueryManager` (using `AsFilterInvoker`).

3.  **Filter Element Refactoring:**
    *   Update `AbstractFilterElement` to remove `InScopeContract` logic.
    *   Refactor `BooleanElement`, `DateRangeElement`, etc., to use `#[AsFilterInvoker]` and `FilterInvocation`.
    *   Implement `IntrinsicValueContract` where applicable.

4.  **Projector Update:**
    *   Update `InteractiveProjector` (and others) to gather `$filterValues` and pass them to `populate`.

5.  **Cleanup:**
    *   Delete `src/Filter/FilterContext.php`.
    *   Remove `ListItemProvider` classes (part of broader refactoring).
