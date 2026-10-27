# SPEC: Role-bound filter scopes and component configs

**Status:** Design agreed 2026-10-27, not implemented. Extends PR #49 (`v0.2-refactor-filter-api`).
**Scope:** `src/Filter/` (`Filter`, `FilterScope`, `FilterContext*`, `Element/`, `Control/`, `Factory/`),
`src/Contract/FilterElement/`, `src/Config/`, `src/Form/`, `src/Query/Executor/ListQueryDirector.php`,
`src/DataContainer/Builder/DcaContext.php`, `src/EventListener/Contao/ElementDcaListener.php`,
`src/Event/FilterControlBuildFormEvent.php`, `src/EventListener/NamedDispatch/FilterControlBuildFormListener.php`,
`src/Filter/ValueProvider/ChoiceValueProvider.php`, `src/Integration/` (CodefogTags, Terminal42Languages),
`src/List/ListSpec.php`, `src/List/ListSpecBuilder.php`, `tests/`, `AGENTS.md`, the docs worktree.
**Breaking:** yes, deliberately. v0.2 is unreleased; no BC layer, no deprecations (atomic cut, as in
`SPEC_FILTER_FORMS.md`).

---

## 1. Intent

Filter elements and filter controls are **public API**: their purpose is that userland code writes its own.
Inside a hook, the one thing an author needs almost every time is **their own config**. Today that reads

```php
$builder->scope->filter->config['columns'] ?? []      // element, in buildContext()
$scope->filter->controlConfig['is_multiple'] ?? false // control, in buildForm()/decode()/hydrate()
```

The chain is long, but length is not the core problem. The API is not self-describing:

- Element and control receive **the same** `FilterScope`. Which config is "mine" (`config` or
  `controlConfig`) is knowledge the author must bring along, not something the type tells them.
- The element's scope hides behind the builder (`$builder->scope`), while every other hook receives the
  scope as a parameter.
- `FilterContextBuilder::build()` is public, so an element *can* call it although only the framework may.
- `?? []` / `?? null` fallbacks hide typos in keys: a misspelled key silently becomes the fallback.

**Target:** in every hook, `$scope->get('columns')` returns the calling component's own resolved config
value, the parameter types say which role the scope belongs to, and a key the component never declared
throws.

### 1.1 Goals

- One obvious way to read the own config: `$scope->get($key)`, identical for elements and controls.
- Role is visible in the signature (`ElementScope` vs. `ControlScope`) and checked by PHPStan.
- `Filter` stops being a flat bag of eleven properties: element and control each become one cohesive part.
- One parameter-order rule across all hooks.
- The element's per-invocation builder is a public, narrow port; the framework-only parts are not visible.
- `FilterContext` stays the name for what an element produces per invocation and is ready to carry more than
  predicates later (see §8).

### 1.2 Non-goals

- Implementing derivations, sort hints or any other new `FilterContext` channel (§8 is an outlook only).
- Changing the query pipeline, `FilterConditionsBuilder`, predicates, or the targeting stopgap (§15 of
  `SPEC_FILTER_FORMS.md`); targeting logic moves along unchanged.
- Changing `ListSpec::$config` (stays an array) or list-driver hooks.
- Shortcut accessors for anything other than the own config. `$scope->list->dc`, `$scope->engineContext`,
  `$scope->filter->alias` etc. stay as they are.
- Restricting cross-reads: a control **may** read the element's config and vice versa (via `$scope->filter`).

### 1.3 Anti-goals

- **Inheritance between `Filter`, `FilterScope`, `FilterContext`.** Rejected: a context is the *product* of a
  scope, not a kind of scope; a scope extending `Filter` would carry both configs side by side again, which is
  exactly the ambiguity being removed. All three stay composed, `final readonly` where they are today.
- **A runtime role pointer** (one `FilterScope` class with an element/control enum deciding what `get()`
  returns). Rejected: the wrong scope handed to an element contract would only fail at runtime.
- **A `$default` parameter on `get()`.** Defaults belong in `configureOptions()`.
- Any change in observable list/reader/form behavior. This is a pure API restructuring; the existing test
  suite (rewritten to the new API) must stay green. Sole exception: reading an undefined config key now
  throws `UndefinedConfigKeyError` (§2.1) instead of silently using a `??` fallback.

---

## 2. Target model

```
Filter (final readonly)
├── alias, value, targetAlias, targetingForced, source       (unchanged)
├── element: ElementBinding  { component, type, config: ComponentConfig }
└── control: ?ControlBinding { component, type, config: ComponentConfig }

ElementScope (final readonly) { list, filter, engineContext }  get()/has() → filter->element->config
ControlScope (final readonly) { list, filter, engineContext }  get()/has() → filter->control->config
                                                               forElement(): ElementScope

FilterContextBuilderInterface (@api)  addPredicate(), abort()
FilterContextBuilder (@internal)      implements it + build(): FilterContext
FilterContext (final readonly)        { formula }   — no scope
```

### 2.1 `ComponentConfig` — `src/Config/ComponentConfig.php`

An immutable value object holding the resolved, canonical config of one component (element or control).

```php
final readonly class ComponentConfig
{
    /**
     * @param array<string, mixed> $values  Resolved values (scalars, arrays, enums only).
     * @param list<string>         $defined Option names the component's schema defines.
     */
    public function __construct(array $values = [], ?array $defined = null) {}  // $defined null ⇒ array_keys($values)

    /** @throws UndefinedConfigKeyError If the schema does not define the key. */
    public function get(string $key): mixed;

    /** Whether the key holds a value (false for an option defined without default and not set). */
    public function has(string $key): bool;

    /** @return array<string, mixed> */
    public function all(): array;
}
```

Semantics:

| Key is …                                            | `get()`                    | `has()` |
|-----------------------------------------------------|----------------------------|---------|
| defined and resolved (incl. via default)            | the value                  | true    |
| defined without default and not set (`define('x')`) | `null`                     | false   |
| not defined by the schema                           | throws `UndefinedConfigKeyError` | false   |

- **An undefined key is a programming error, not a filter failure.** `UndefinedConfigKeyError`
  (`src/Exception/UndefinedConfigKeyError.php`) extends **`\Error`**, not `\Exception`. The bundle's existing
  rule "`\Error`s escape unwrapped" therefore applies everywhere, with no catch block changed:
  - `ListQueryDirector` neither wraps it nor retries the filter with value `null`. A misspelled key in the
    value branch of `buildContext()` cannot be masked by the retry.
  - `FormHarnessFactory` (`catch (FilterException)` around `buildForm()`/`hydrate()`, including element contracts
    reached via `forElement()`), `FormHarness` (decode) and `ChoiceValueProvider::buildDcaField()` do not swallow
    it: the frontend request fails (HTTP 500), the backend shows the error.
  - This is the one deliberate behavior change of this spec (see §1.3): a typo that used to fall back to
    `?? x` silently now fails loudly. Bundle code has no such typos (verified: every key read today is defined).
- The message names the key, the component class and the defined keys (typo aid). `ComponentConfig` receives the
  component class name for this (constructor argument or named constructor; builder's choice).
- `$defined` comes from the memoized `OptionsResolver` (`getDefinedOptions()`). `SchemaResolver` /
  `FilterOptionsResolver` therefore return a `ComponentConfig` (or expose the defined names) instead of a bare
  array; the exact split between the two is left to the builder. Callers that need an array use `all()`.
- No `ArrayAccess`, no setters, no `with()`. Equality/fingerprint through `all()`.

### 2.2 Bindings — `src/Filter/ElementBinding.php`, `src/Filter/ControlBinding.php`

```php
final readonly class ElementBinding
{
    public function __construct(
        public FilterElementInterface $component, // registered service or inline instance
        public string                 $type,      // registered type alias (or class name of an inline element)
        public ComponentConfig        $config,
    ) {}
}

final readonly class ControlBinding
{
    public function __construct(
        public FilterControlInterface $component,
        public string                 $type,      // registered control type, or class name of an inline control
        public ComponentConfig        $config,
    ) {}
}
```

Two classes, not one generic `Binding`: the component type stays precise for PHPStan without generics. Both
carry `@api` (read-only for userland), constructed only by `FilterFactory` and tests.

### 2.3 `Filter`

```php
final readonly class Filter
{
    public function __construct(
        public ElementBinding  $element,
        public string          $alias,
        public ?ControlBinding $control = null,
        public ?ValueInterface $value = null,
        public ?string         $targetAlias = null,
        public bool            $targetingForced = false,
        public ?string         $source = null,
    ) {}

    public function withValue(?ValueInterface $value): self;
    public function withControl(?ControlBinding $control): self;
    public function withTargetAlias(?string $targetAlias, bool $forced = true): self;
    public function fingerprint(): array;
}
```

Property mapping (old → new):

| old                       | new                                |
|---------------------------|------------------------------------|
| `$filter->element`        | `$filter->element->component`      |
| `$filter->type`           | `$filter->element->type`           |
| `$filter->config`         | `$filter->element->config`         |
| `$filter->control`        | `$filter->control?->component`     |
| `$filter->controlConfig`  | `$filter->control?->config`        |
| `$filter->controlType`    | `$filter->control?->type`          |
| `$filter->control === null` | unchanged (`$filter->control === null`) |

- The invariant "no control ⇒ no control config/type" now holds by construction (it used to be enforced by
  `withControl()` and `FilterFactory::create()`).
- `fingerprint()` keeps its keys and meaning; values come from the bindings (`config->all()`).
- `withControl()` takes a whole binding. Its old defaulting of the control type to the class name moves into
  `FilterFactory`, which is the only place that builds bindings from loose parts.

### 2.4 `FilterFactory`

The public signature of `FilterFactory::create()` **does not change**: callers still pass the element (instance
or type), `array $config`, the control (instance or type), `array $controlConfig`, etc. The factory resolves the
configs into `ComponentConfig`s and assembles the bindings. `createFromFilterModel()` likewise only changes
internally. Programmatic filter creation in userland (e.g. in a `ListDriverBuildListEvent` listener) is
therefore unaffected.

### 2.5 Scopes — `src/Filter/ElementScope.php`, `src/Filter/ControlScope.php`

```php
/** The value-free situation an element hook runs in. */
final readonly class ElementScope
{
    public function __construct(
        public ListSpec         $list,
        public Filter           $filter,
        public ContextInterface $engineContext,
    ) {}

    /** The element's own config value. @throws UndefinedConfigKeyError */
    public function get(string $key): mixed { return $this->filter->element->config->get($key); }

    public function has(string $key): bool;
}

/** The value-free situation a control hook runs in. */
final readonly class ControlScope
{
    public function __construct(
        public ListSpec         $list,
        public Filter           $filter,   // guaranteed to have a control
        public ContextInterface $engineContext,
    ) {}

    /** The control's own config value. @throws UndefinedConfigKeyError */
    public function get(string $key): mixed;

    public function has(string $key): bool;

    /** The element's scope, to hand to element contracts (ChoiceSourceContract, DateBoundsContract). */
    public function forElement(): ElementScope;
}
```

- `ControlScope`'s constructor throws `\InvalidArgumentException` if `$filter->control` is null, so `get()` needs
  no null handling.
- `FilterScope` is **deleted**. There is no shared base class or interface: nothing in the codebase needs to
  accept "either scope". (Builder may add an interface if a real consumer appears during implementation; flag
  it in the PR.)
- The constants `FilterScope::ATTR_SELF` and `FilterScope::ATTR_SINGLE_FIELD` move to `ControlScope`
  (`ATTR_SELF` stores the `ControlScope` on the per-filter form builder; `ATTR_SINGLE_FIELD` is a form-mount
  flag and may instead move to `FilterMount`/`FormHarness`; builder's choice). Attribute key strings stay.
- Cross-reads stay possible and are allowed: a control reads `$scope->filter->element->config->get('field')`,
  an element reads `$scope->filter->control?->config->get('label')`.

### 2.6 `FilterContext` and its builder

```php
/** @api What an element sees in buildContext(). */
interface FilterContextBuilderInterface
{
    /**
     * @param class-string<PredicateInterface> $predicateClass
     * @param array<string, mixed>             $options
     */
    public function addPredicate(string $predicateClass, array $options = [], ?string $targetAlias = null): static;

    public function abort(): never;
}

/** @internal */
final class FilterContextBuilder implements FilterContextBuilderInterface
{
    public function build(): FilterContext;   // not on the interface: invisible to elements
}

/** The element's product for one invocation. */
final readonly class FilterContext
{
    public function __construct(public Formula $formula) {}
}
```

- The builder **no longer holds the scope**. Its only scope-dependent input today is the default target alias
  (targeting stopgap), which `FilterContextBuilderFactory::create(ElementScope)` resolves up front, as it does
  now.
- `FilterContext::$scope` is removed: nothing reads it (the only consumer, `ListQueryDirector`, already holds
  the scope).
- Whether `FilterContextBuilder` keeps delegating to the internal `FormulaBuilder` or absorbs it is left to the
  builder. `Formula`, `Proposition`, `FormulaBuilder(Interface)` stay `@internal`; if `FormulaBuilderInterface`
  becomes redundant it may be removed.
- `FilterContextFactory::create(ElementScope $scope, ?ValueInterface $value): FilterContext` keeps its job
  (SQL-name check of `list->dc`, invoking the element, building the context).

---

## 3. Hook signatures

**Parameter-order rule:** the method's subject comes first, the scope is supplementary. For builder hooks the
subject is the output channel (builder, then scope, then the value); for `decode()`/`hydrate()` it is the data or
value being converted, followed by the scope (revised 2026-10-27, see D5).

| Hook                                   | Old                                                         | New                                                                                |
|----------------------------------------|-------------------------------------------------------------|------------------------------------------------------------------------------------|
| `FilterElementInterface::buildContext` | `(FilterContextBuilder $builder, ?ValueInterface $value)`   | `(FilterContextBuilderInterface $builder, ElementScope $scope, ?ValueInterface $value)` |
| `FilterControlInterface::buildForm`    | `(FilterFormBuilderInterface $builder, FilterScope $scope)` | `(FilterFormBuilderInterface $builder, ControlScope $scope)`                        |
| `FilterControlInterface::decode`       | `(mixed $data, FilterScope $scope)`                         | `(mixed $data, ControlScope $scope)`                                                |
| `FilterControlInterface::hydrate`      | `(?ValueInterface $value, FilterScope $scope)`              | `(?ValueInterface $value, ControlScope $scope)`                                     |
| `ChoiceSourceContract::buildChoices`   | `(ChoicesBuilder $choices, FilterScope $scope)`             | `(ChoicesBuilder $choices, ElementScope $scope)`                                    |
| `DateBoundsContract::getDateBounds`    | `(FilterScope $scope)`                                      | `(ElementScope $scope)`                                                             |

Unchanged: `configureOptions`, `configureTransformers`, `buildDca(DcaBuilderInterface, DcaContext)`,
`ChoiceSourceContract::choiceFromKey(string)`, `ValueProviderInterface::provide()` /
`buildDcaField()` (no scope), predicates.

`decode()`'s narrowed return type still defines the control's value class (`ValueClassReflector` reads the
return type, not parameter positions; verify nothing reads parameter positions).

### 3.1 Before / after

Element:

```php
// before
public function buildContext(FilterContextBuilder $builder, ?ValueInterface $value): void
{
    if (!$value = $this->expectValue($value, KeywordsValue::class)) {
        return;
    }

    if (!$columns = $builder->scope->filter->config['columns'] ?? []) {
        $builder->abort();
    }
    // …
}

// after
public function buildContext(FilterContextBuilderInterface $builder, ElementScope $scope, ?ValueInterface $value): void
{
    if (!$value = $this->expectValue($value, KeywordsValue::class)) {
        return;
    }

    if (!$columns = $scope->get('columns')) {
        $builder->abort();
    }
    // …
}
```

Control handing over to an element contract:

```php
// before
$element = $scope->filter->element;
$config = $scope->filter->controlConfig;
$element->buildChoices($choices, $scope);
if ($config['format_label']) { … }

// after
$element = $scope->filter->element->component;
$element->buildChoices($choices, $scope->forElement());
if ($scope->get('format_label')) { … }
```

### 3.2 `?? fallback` cleanup

Every `config[...] ?? x` read in bundle elements/controls becomes `$scope->get(...)`. All keys read today are
defined with defaults (the only option without one, `SimpleEquationFilterElement`'s `right`, already resolves
to `null`), and no fallback differs from its schema default, so the fallbacks were dead code (verified during
the spec review).

---

## 4. Call sites to change

Framework (scope construction and plumbing):

- `ListQueryDirector::invokeFilters()` → builds `ElementScope`; `buildFilterConditionsBuilders(ElementScope, …)`;
  `wrapFilterFailure()` uses `$filter->element->component::class`.
- `FilterContextFactory`, `FilterContextBuilderFactory` (targeting reads `$filter->element->type`,
  `targetingForced`, `targetAlias`), `FilterContextBuilder`, `FilterContext`.
- `FormHarnessFactory` → builds `ControlScope`, `$filter->control->component->buildForm(...)`.
- `FormHarness::decode loop` → `$mount->filter->control->component->decode($data, $mount->scope)`;
  hydrate call site likewise.
- `FilterMount::$scope` → `ControlScope`.
- `FilterControlBuildFormEvent::$scope` → `ControlScope`; `FilterControlBuildFormListener` reads
  `$event->scope->filter->control->type`.
- `DcaContext::getFilterScope(): ?FilterScope` → `getElementScope(): ?ElementScope` (the backend scope is built
  without control and value, `SPEC_FILTER_FORMS.md` §6.4); `ElementDcaListener::createFilterScope()` →
  `createElementScope()`; consumer `ChoiceValueProvider`.
- `FilterFactory` (§2.4), `FilterOptionsResolver`/`SchemaResolver` (§2.1).
- `ListSpec::withoutFilter()` / `ListSpecBuilder` (`instanceof $class` on `$filter->element->component`).
- `CreatesFilterExceptionTrait` (only `source`, verify).

Bundle components (config reads → `$scope->get()`, signatures per §3):

- Elements: `Archive`, `BelongsToRelation`, `Boolean`, `CalendarCurrent`, `DateRange`, `DcaSelectField`,
  `FieldValueChoice`, `Published`, `SearchKeywords`, `SimpleEquation`, `AbstractFilterElement`.
- Controls: `Checkbox`, `ChoiceBool`, `Choice`, `DateRange`, `Keywords`, `AbstractFilterControl`.
- Integrations: `CodefogTagsChoiceFilterElement`, `Terminal42Languages/ChangelanguageListener`
  (`withTargetAlias()` only; this directory is excluded from PHPStan, so check it by hand).

Where an element passes its whole config on (e.g. `CalendarCurrentFilterElement::resolveFormLimits($config)`,
`ArchiveFilterElement` local `$config = …`), prefer `$scope->get()` per key; `all()` is acceptable where a
helper genuinely takes the whole array.

Tests: `tests/Filter/BuildsFilterContextsTrait.php` and `tests/Filter/Control/BuildsControlScopesTrait.php`
become the factories for `ElementScope`/`ControlScope`; `FilterTest`, `FilterContextFactoryTest`,
`FormulaBuilderTest`, control/element tests, `FormHarness*Test`, `ListQueryDirectorTest`, stubs
(`tests/List/StubFilterElement.php`, `tests/Filter/ValueProvider/ProviderStubElement.php`) follow the new
signatures. New unit tests: `ComponentConfigTest` (the three rows of the §2.1 table, message lists keys),
`ControlScope` rejects a filter without control, `forElement()` returns a scope over the same filter/list/context.
Failure-path tests for `UndefinedConfigKeyError` escaping unwrapped: `ListQueryDirector` (no wrap, **no retry**
with `null` for a controlled filter), `FormHarnessFactory` (not swallowed by the `FilterException` catch, also
when thrown from an element contract via `forElement()`), `ChoiceValueProvider::buildDcaField()`.

---

## 5. Documentation

- `AGENTS.md` (source of `CLAUDE.md`): the architecture paragraphs naming `FilterScope`,
  `FilterContextFactory::create(FilterScope, ?value)` and the two-stage model → `ElementScope`/`ControlScope`,
  `FilterContext` without scope, bindings.
- Docs worktree (`docs/`, branch `docs/main`, separate commit): `docs/docs/dev/filter-elements/index.md`,
  `dev/filter-controls.md`, `dev/contracts/index.mdx`, `dev/events.md`, `spec/filtering.md`,
  `migrating-from-v0.1.md`, `removed-in-v0.2.md` (FilterScope removed; old→new mapping table from §2.3 and §3).
- Code comments do not reference this spec (no `§`-refs to `SPEC_FILTER_SCOPE.md`). Existing
  `SPEC_FILTER_FORMS.md` refs stay untouched until the final PR #49 review loop; adjust their surrounding text
  only where it names removed types.

---

## 6. Sequencing

One atomic cut is acceptable; a sensible order that keeps the suite runnable between steps:

1. `ComponentConfig` + resolver returning it; `ElementBinding`/`ControlBinding`; `Filter` + `FilterFactory`.
2. `ElementScope`/`ControlScope` replace `FilterScope` everywhere, signatures per §3.
3. `FilterContextBuilderInterface`, builder without scope, `FilterContext` without scope.
4. Bundle elements/controls/integrations to `$scope->get()`; drop dead `??` fallbacks.
5. Tests, `make test`, `make phpstan`, `AGENTS.md`, docs worktree.

Commits follow the repo's `refactor!:` convention.

---

## 7. Decisions recorded

| # | Decision | By |
|---|----------|----|
| D1 | Own config via `$scope->get($key)`; no default parameter; undefined key throws | user |
| D2 | Decompose `Filter` into element/control bindings with a config value object (option C) | user |
| D3 | Two role-typed scopes (option B) on top of D2; no role pointer, no inheritance | user |
| D4 | Element receives the scope as a parameter, not via the builder | user |
| D5 | Parameter order: builder, scope, value for builder hooks; `decode`/`hydrate` keep data/value first, scope last — the converted data is the subject, the scope supplementary (revised 2026-10-27) | user |
| D6 | `FilterContext` and `buildContext()` keep their names ("Formula" is not public vocabulary) | user |
| D7 | Element ↔ control cross-reads of config are allowed | user |
| D8 | Future context channels (§8) are out of scope; the builder interface is where they will go | user |
| D9 | No BC layer, `refactor!` on v0.2 | user |
| D10 | `$scope->list->dc` & co. get no shortcuts | user |
| D11 | Value object named `ComponentConfig`, in `src/Config/`; bindings named `ElementBinding`/`ControlBinding` with property `component` | Claude |
| D12 | `get()` returns `null` for a defined-but-unset option; `has()` added; `all()` for fingerprint/helpers | Claude |
| D13 | Undefined key is a programming error: `UndefinedConfigKeyError extends \Error`, escapes unwrapped everywhere | user |
| D14 | `FilterScope` deleted without a shared scope interface | Claude |
| D15 | `FilterContext` drops `$scope`; builder interface `@api`, `build()` only on the `@internal` class | Claude |
| D16 | `DcaContext::getElementScope()` replaces `getFilterScope()` | Claude |
| D17 | `FilterFactory::create()` keeps its array-based public signature | Claude |

---

## 8. Outlook (not part of this work): more than predicates

The reason `FilterContext` stays a named product instead of collapsing into a formula: an element may later
contribute more than WHERE fragments. Example, a *tag-overlap score* element ("related articles"):

```sql
SELECT main.*, ovl.score
FROM tl_news main
JOIN (
    SELECT r.news_id AS id, COUNT(*) AS score
    FROM tl_news_tags r
    WHERE r.tag_id IN (:tags)
    GROUP BY r.news_id
) ovl ON ovl.id = main.id
WHERE ovl.score >= :min                 -- restriction
ORDER BY ovl.score DESC, main.date DESC -- order *hint* + the list's own sorting
```

With further channels on `FilterContextBuilderInterface`, the element would read:

```php
public function buildContext(FilterContextBuilderInterface $builder, ElementScope $scope, ?ValueInterface $value): void
{
    if (!$value = $this->expectValue($value, ChoiceValue::class)) {
        return;
    }

    $ovl = $builder->derive('ovl', $overlapSubquery, ['tags' => $value->keys()]); // returns the alias
    $builder->addPredicate(GreaterOrEqualPredicate::class, ['column' => "{$ovl}.score", 'value' => $scope->get('min_overlap')]);
    $builder->suggestOrder("{$ovl}.score", 'DESC');
}
```

producing `FilterContext {formula, derivations, orderHints}`. Order hints are suggestions: sort ownership stays
with the list. How derivations and hints enter the SQL is decided by the planned query-pipeline rework, in which
each list driver owns its query generation instead of event-driven struct modification. Nothing in this spec
may preclude adding such methods to the interface without changing the `buildContext()` signature.
