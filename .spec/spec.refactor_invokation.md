# Architecture Refactoring Specification: Context-Aware Filter Invocation

## 1. Overview
This specification details the architectural changes required to refactor the Filter Invocation system within the `contao-flare-bundle`. The goal is to decouple filter logic from the calling context, enable dynamic context registration, and standardize filter identification via an indexed collection strategy.

## 2. Core Architectural Principles
1.  **Projector Responsibility:** The Projector is the ultimate authority on *what* values are used for filtering. It gathers runtime values (e.g., from Forms, Request, Config) and intrinsic values and passes them to the `ListQueryManager`.
2.  **Indexed Collections:** Filters are stored in an associative map (`FilterDefinitionCollection`) where the key is the unique index. This allows filters to override each other (e.g., a DB filter named 'author' is overridden by a manual filter named 'author' added later).
3.  **Context-Driven Logic:** Filter Elements use Attributes (`#[AsFilterInvoker]`) to define context-specific behavior, removing hardcoded scope checks (like `if ($context->isList())`).
4.  **Unified DTO:** `FilterInvocation` DTO wraps configuration and context for the filter execution, replacing legacy loose arguments and `FilterContext`. **Note:** The `FilterQueryBuilder` is passed separately to maintain a clear distinction between "Context Data" and "Action Target".
5.  **Specification-Driven:** `ListSpecification` is the domain object source of truth.

## 3. Component Updates

### A. FilterDefinitionCollection (The Map)
Must function as an associative array to support overriding.

```php
class FilterDefinitionCollection implements \IteratorAggregate, \Countable
{
    private array $items = []; // [string $key => FilterDefinition]

    /**
     * Adds or overwrites a filter.
     * @param string|null $key If null, a unique ID is generated.
     */
    public function set(?string $key, FilterDefinition $filter): void;
    
    public function get(string $key): ?FilterDefinition;
    
    public function all(): array; // Returns ['key' => Definition]
}
```

### B. Filter Collectors (The Index Strategy)
Collectors define the indexing strategy.

*   **ListModelFilterCollector:**
    *   **Index:** Uses `filterFormFieldName` if available.
    *   **Fallback:** Uses an automatically generated ID if name is missing.
    *   **Result:** DB filters with the same form name automatically override earlier ones.

### C. The Invocation DTO (`FilterInvocation`)
Wraps configuration and context dependencies. **Does not include the Query Builder.**

Implemented in `\HeimrichHannot\FlareBundle\Filter\FilterInvocation`;

```php
namespace HeimrichHannot\FlareBundle\Filter;

class FilterInvocation
{
    public function __construct(
        public readonly FilterDefinition $definition,
        public readonly ListSpecification $spec,
        public readonly ContextConfigInterface $contextConfig,
        public readonly mixed $value = null,
    ) {}
}
```

### D. The Invoker Attribute (`AsFilterInvoker`)
Allows methods on a `FilterElement` service to claim responsibility for specific contexts.

Implemented in `\HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterInvoker`;

### E. Intrinsic Value Interface (`IntrinsicValueContract`)
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

### F. Context Configuration Updates
`ContextConfigInterface` must provide a unique identity to match against the `AsFilterInvoker` attribute.

Implemented in `\HeimrichHannot\FlareBundle\Context\ContextConfigInterface`;

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
The Projector builds a value map using the **Collection Keys**.

```php
$filterValues = []; // [string $key => mixed $value]
$formData = $form->isSubmitted() ? $form->getData() : [];

/** @var \HeimrichHannot\FlareBundle\Filter\FilterDefinition $filter */
foreach ($spec->getFilters()->all() as $key => $filter)
{
    if ($filter->isIntrinsic())
    {
        $element = $this->filterElementRegistry->get($filter->getType());
        
        if ($element instanceof IntrinsicValueContract) {
            $filterValues[$key] = $element->getIntrinsicValue($filter);
        }
        
        continue;
    }
    
    $formFieldName = $filter->getFilterFormFieldName();
    if ($formFieldName && \array_key_exists($formFieldName, $formData))
    {
        $filterValues[$key] = $formData[$formFieldName];
        continue;
    }
}

$this->listQueryManager->populate($builder, $spec, $contextConfig, $filterValues);
```

### Step 2: ListQueryManager Dispatches
The Manager iterates using the keys.

```php
// Inside ListQueryManager::invokeFilters(..., array $filterValues)
$contextType = $contextConfig::getContextType();

foreach ($spec->getFilters()->all() as $key => $filter) {
    // Value lookup by Collection Key
    $value = $filterValues[$key] ?? null;
    
    // Note: QueryBuilder is NOT in DTO
    $invocation = new FilterInvocation($filter, $spec, $contextConfig, $value);

    // Resolution & Execution...
    // Signature: method(FilterInvocation $invocation, FilterQueryBuilder $qb)
    $method = $this->resolver->resolveInvoker($element, $contextType);
    $element->{$method}($invocation, $qb);
}
```

## 5. Migration Strategy

1.  **Collection Refactoring:**
    *   [x] Update `FilterDefinitionCollection` to support string keys and `set()`.
    *   [x] Update `ListModelFilterCollector` to implement the Name/ID indexing strategy.

2.  **Infrastructure:**
    *   [x] Create DTO, Attribute, and Interface.
    *   [x] Update `ContextConfigInterface`.

3.  **Manager & Projectors:**
    *   [x] Update `ListQueryManager` to accept keyed array `$filterValues`.
    *   [ ] Update Projectors to build the array using collection keys.

4.  **Element Refactoring:**
    *   [ ] Apply `#[AsFilterInvoker]` to elements.
    *   [ ] Update method signatures to `(FilterInvocation $inv, FilterQueryBuilder $qb)`.
    *   [ ] Remove legacy scope logic.
