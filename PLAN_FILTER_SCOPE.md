# Filter Scopes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Elements and controls read their own config with `$scope->get('key')` from a role-typed scope
(`ElementScope` / `ControlScope`), `Filter` is split into element and control bindings over an immutable
`ComponentConfig`, and elements receive a narrow public `FilterContextBuilderInterface`.

**Architecture:** Bottom-up in four green steps. (1) the config value object, (2) `Filter` built from bindings
with all readers adapted mechanically, (3) the element side (`ElementScope`, context builder interface, scope as
`buildContext()` parameter), (4) the control side (`ControlScope` replaces `FilterScope`). Then (5) bundle
components switch to `$scope->get()`, and (6) the docs follow.

**Tech Stack:** PHP ^8.2, Symfony 5.4–7 (OptionsResolver, Form, DI), Contao 4.13/5, PHPUnit 9, PHPStan level 5,
Docker via `make`.

**Spec:** `SPEC_FILTER_SCOPE.md` (approved 2026-10-27). Section references (§n) point at it. Read all of it
before starting any task; §7 lists every decision.

## Global Constraints

- PHP floor **8.2**: no typed class constants, no `#[\Override]`; `readonly` classes are fine.
- `declare(strict_types=1);` in every PHP file; PSR-12/PER, 4 spaces, 120-char lines.
- The `php` binary is unavailable: run `make test <phpunit args>` and `make phpstan`. Mago runs in CI only.
- **Green after every task:** `make test` and `make phpstan` pass at the end of each task, not only the targeted
  tests.
- Breaking changes are allowed (v0.2 unreleased); no deprecation layers, no legacy adapters, no BC aliases.
- Commits on branch `v0.2-refactor-filter-api`, message prefix `refactor!:` for API breaks, `feat:`/`test:`/`docs:`
  otherwise. **No `Co-Authored-By` trailer. Never push.**
- Code comments do not reference `SPEC_FILTER_SCOPE.md` (no new `§` refs). Existing `SPEC_FILTER_FORMS.md` refs stay;
  only adjust their surrounding text where it names a removed type.
- `\Error`s escape unwrapped everywhere. `UndefinedConfigKeyError` extends `\Error`; no catch block may be widened to
  catch it.
- `docs/` is a separate git worktree on branch `docs/main`; doc changes are committed there separately (Task 6).
  `docs/versioned_docs/version-0.1/**` stays frozen.
- Attribute-bag key strings stay: `'flare.filter_scope'` (self) and `'flare.single_field'`.

## Review Focus

1. **A test or userland caller builds a `Filter` with a partial config array without going through the schema**
   (today's `new Filter(config: ['field' => 'x'])`). `get()` on a schema-defaulted key must return the default
   whenever the filter came through `FilterFactory`. → Task 2 test `testCreateResolvesSchemaDefaultsIntoComponentConfig`;
   the test traits resolve configs through the component's schema (Task 2 Step 5).
2. **Inline element without `OptionsContract`** (anonymous class implementing only `FilterElementInterface`): its
   given keys are readable, any other key throws. → Task 2 test `testComponentWithoutOptionsContractDefinesItsGivenKeys`.
3. **Option defined without default and not set** (`define('x')`): reads `null`, `has()` is false, never
   `UndefinedConfigKeyError`. → Task 1 test `testDefinedButUnsetKeyReadsNull`, Task 2 test
   `testDefinedOptionWithoutDefaultReadsAsNull`.
4. **Controlled filter whose element misspells a key only in its value branch.** The director must not retry it with
   `null` and turn the typo into a warning. → Task 5 test `testUndefinedConfigKeyEscapesWithoutRetry`.
5. **Element contract reached from a control (`forElement()`) that misspells a key.** The form harness's
   `catch (FilterException)` must not swallow it, and neither may the backend options callback. → Task 5 tests
   `testUndefinedConfigKeyInElementContractIsNotSwallowed`, `testUndefinedConfigKeyEscapesTheOptionsCallback`.

## File map

Created: `src/Config/ComponentConfig.php`, `src/Exception/UndefinedConfigKeyError.php`,
`src/Filter/ElementBinding.php`, `src/Filter/ControlBinding.php`, `src/Filter/ElementScope.php`,
`src/Filter/ControlScope.php`, `src/Filter/FilterContextBuilderInterface.php`,
`tests/Config/ComponentConfigTest.php`, `tests/Filter/ElementScopeTest.php`, `tests/Filter/ControlScopeTest.php`.

Deleted: `src/Filter/FilterScope.php` (Task 4).

---

### Task 1: `ComponentConfig` and `UndefinedConfigKeyError`

**Files:**
- Create: `src/Config/ComponentConfig.php`, `src/Exception/UndefinedConfigKeyError.php`
- Modify: `src/Config/SchemaResolver.php`
- Test: `tests/Config/ComponentConfigTest.php`, `tests/Config/SchemaResolverTest.php`

**Interfaces:**
- Produces:
  - `final readonly class ComponentConfig` (`@api`), `__construct(array $values = [], ?array $defined = null, ?string $owner = null)`.
    `$defined === null` means `array_keys($values)`. `$owner` is the component class name, used only in the error message.
  - `ComponentConfig::get(string $key): mixed`: the value; `null` when the key is defined but not in `$values`;
    throws `UndefinedConfigKeyError` when the key is not in `$defined`.
  - `ComponentConfig::has(string $key): bool` (`array_key_exists` on `$values`), `ComponentConfig::all(): array` (the `$values`).
  - `final class UndefinedConfigKeyError extends \Error`, `__construct(string $key, list<string> $defined, ?string $owner = null)`,
    public readonly `$key`, `$defined`, `$owner`. Message, exactly:
    `The config key "<key>" is not defined by <owner>. Defined keys: <k1>, <k2>.`; with a null owner: `… is not defined by the component. …`;
    with no defined keys: `Defined keys: none.`
  - `SchemaResolver::getDefinedOptions(string $key, \Closure $configure): list<string>`, which shares the memoized
    `OptionsResolver` with `resolve()` (extract a private `resolver(string $key, \Closure $configure): OptionsResolver`),
    returns `$resolver->getDefinedOptions()`.

- [ ] **Step 1: Write the failing tests** in `tests/Config/ComponentConfigTest.php`:
  - `testGetReturnsResolvedValue`: `new ComponentConfig(['a' => 1, 'b' => null])`, then `get('a') === 1` and `get('b') === null`.
  - `testDefinedButUnsetKeyReadsNull`: `new ComponentConfig(['a' => 1], ['a', 'x'])`, then `get('x') === null`, `has('x') === false`, `has('a') === true`.
  - `testUndefinedKeyThrowsError`: `new ComponentConfig(['columns' => []], null, 'App\FooElement')`, then
    `get('columnz')` throws `UndefinedConfigKeyError`, the message is
    `'The config key "columnz" is not defined by App\FooElement. Defined keys: columns.'`, and `$e->key === 'columnz'`.
  - `testErrorIsNotAnException`: `self::assertNotInstanceOf(\Exception::class, new UndefinedConfigKeyError('k', []))`.
  - `testAllReturnsValuesOnly`: `new ComponentConfig(['a' => 1], ['a', 'x'])`, then `all() === ['a' => 1]`.

  In `tests/Config/SchemaResolverTest.php`, add `testGetDefinedOptionsSharesTheMemoizedResolver`:
  the configure closure defines `a` (default 1) and `x` (no default) and counts its calls. Call `resolve('k', $c, [])`
  and `getDefinedOptions('k', $c)`. The result is `['a', 'x']` (order-insensitive, `assertEqualsCanonicalizing`) and
  the closure ran exactly once.

- [ ] **Step 2: Run them and confirm they fail**
  Run: `make test tests/Config/ComponentConfigTest.php tests/Config/SchemaResolverTest.php`. Expected: errors about the
  missing classes and method.

- [ ] **Step 3: Implement** both classes and `SchemaResolver::getDefinedOptions()` as specified above.

- [ ] **Step 4: Run them and confirm they pass**, then the full suite: `make test` and `make phpstan`. Expected: green.

- [ ] **Step 5: Commit**: `feat: add ComponentConfig with UndefinedConfigKeyError`

---

### Task 2: `Filter` from element and control bindings

Mechanical restructuring. **Semantics stay the same in this task**: component code still reads arrays (via
`->all()`) with its existing `??` fallbacks. The switch to `get()` comes in Task 5.

**Files:**
- Create: `src/Filter/ElementBinding.php`, `src/Filter/ControlBinding.php`
- Modify: `src/Filter/Filter.php`, `src/Filter/Factory/FilterFactory.php`, `src/Filter/Resolver/FilterOptionsResolver.php`,
  `src/Filter/Factory/FilterContextFactory.php`, `src/Filter/Factory/FilterContextBuilderFactory.php`,
  `src/Query/Executor/ListQueryDirector.php`, `src/Form/Factory/FormHarnessFactory.php`, `src/Form/FormHarness.php`,
  `src/EventListener/NamedDispatch/FilterControlBuildFormListener.php`, `src/List/ListSpec.php`,
  `src/List/ListSpecBuilder.php`, every element in `src/Filter/Element/`, every control in `src/Filter/Control/`,
  `src/Integration/CodefogTags/FilterElement/CodefogTagsChoiceFilterElement.php` (verify; it reads only
  `targetAlias`/`source`)
- Test: `tests/Filter/FilterTest.php`, `tests/Filter/FilterFactoryTest.php`, `tests/Filter/FilterOptionsResolverTest.php`,
  both test traits, and every test listed by `grep -rlE "new Filter\(|controlConfig|controlType" tests`

**Interfaces:**
- Consumes: `ComponentConfig`, `SchemaResolver::getDefinedOptions()` (Task 1).
- Produces:
  - `final readonly class ElementBinding(public FilterElementInterface $component, public string $type, public ComponentConfig $config)`;
    `final readonly class ControlBinding(public FilterControlInterface $component, public string $type, public ComponentConfig $config)`. Both `@api`.
  - `Filter::__construct(ElementBinding $element, string $alias, ?ControlBinding $control = null, ?ValueInterface $value = null, ?string $targetAlias = null, bool $targetingForced = false, ?string $source = null)`.
  - `Filter::withControl(?ControlBinding $control): self`; `withValue()` and `withTargetAlias()` unchanged.
  - `Filter::fingerprint()` keeps exactly the keys `element, type, alias, config, control, controlConfig, controlType, value, targetAlias, targetingForced`, with values taken from the bindings (`config->all()`, `component::class`).
  - `FilterOptionsResolver::resolve(FilterComponentInterface $owner, array $config, ?string $origin = null): ComponentConfig`.
    With `OptionsContract`, the result is `new ComponentConfig($resolved, $schemaResolver->getDefinedOptions(...), $owner::class)`.
    Without it: `new ComponentConfig($config, null, $owner::class)`.
  - `FilterFactory::create()` keeps its public signature exactly (§2.4). For an inline control, the factory defaults the
    control type to the class name.
  - Old → new property mapping: spec §2.3 table.

- [ ] **Step 1: Write the failing tests**
  - `FilterTest::testFingerprintKeysAreUnchanged`: assert the key list above and `fingerprint()['config'] === ['a' => 1]`.
  - `FilterTest::testControlBindingCarriesTypeAndConfig`: `$filter->withControl(new ControlBinding($control, 'app_control', new ComponentConfig(['b' => 2])))`, then `control->type === 'app_control'` and `control->config->get('b') === 2`. Replace the old `controlType` assertions (FilterTest lines ~109–116) with this.
  - `FilterFactoryTest::testCreateResolvesSchemaDefaultsIntoComponentConfig`: `create(element: <element whose schema defines 'columns' default []>, config: [])`, then `$filter->element->config->get('columns') === []`.
  - `FilterFactoryTest::testCreateDefaultsControlTypeToClassNameForInlineControl`: `$filter->control->type === $control::class`.
  - `FilterOptionsResolverTest::testComponentWithoutOptionsContractDefinesItsGivenKeys`: the result's `get('a') === 1`, and `get('b')` throws `UndefinedConfigKeyError`.
  - `FilterOptionsResolverTest::testDefinedOptionWithoutDefaultReadsAsNull`: the schema does `define('x')`; then `get('x') === null` and `has('x') === false`.

- [ ] **Step 2: Run them and confirm they fail**: `make test tests/Filter/FilterTest.php tests/Filter/FilterFactoryTest.php tests/Filter/FilterOptionsResolverTest.php`.

- [ ] **Step 3: Implement** the bindings, `Filter`, `FilterOptionsResolver` and `FilterFactory`.

- [ ] **Step 4: Adapt every reader** according to the §2.3 mapping. In component code, map
  `$x->filter->config` → `$x->filter->element->config->all()`, `$x->filter->controlConfig` → `$x->filter->control->config->all()`,
  and `$x->filter->element` → `$x->filter->element->component`. Keep every `?? fallback` as it is.
  `ListQueryDirector::wrapFilterFailure()` uses `$filter->element->component::class`. `FilterContextBuilderFactory`
  reads `$filter->element->type`. Check that nothing is left:
  `grep -rnE "filter->(config|controlConfig|controlType|type)\b" src` returns nothing.

- [ ] **Step 5: Adapt the test traits.** `BuildsFilterContextsTrait::buildFilterContext()` and
  `BuildsControlScopesTrait::scope()` build bindings. They resolve the given config arrays through the component's
  schema with a `FilterOptionsResolver(new SchemaResolver())` (handles components with and without `OptionsContract`),
  so partial test configs get their defaults. Adapt the remaining tests that construct `Filter` directly.

- [ ] **Step 6: Run** `make test` and `make phpstan`. Expected: green.

- [ ] **Step 7: Commit**: `refactor!: split Filter into element and control bindings over ComponentConfig`

---

### Task 3: Element side: `ElementScope` and `FilterContextBuilderInterface`

**Files:**
- Create: `src/Filter/ElementScope.php`, `src/Filter/FilterContextBuilderInterface.php`
- Modify: `src/Filter/FilterContextBuilder.php`, `src/Filter/FilterContext.php`, `src/Filter/FilterScope.php`
  (temporary `forElement()`), `src/Filter/Factory/FilterContextBuilderFactory.php`, `src/Filter/Factory/FilterContextFactory.php`,
  `src/Filter/Element/FilterElementInterface.php`, `src/Filter/Element/AbstractFilterElement.php`, every element's
  `buildContext()`, `src/Contract/FilterElement/ChoiceSourceContract.php`, `src/Contract/FilterElement/DateBoundsContract.php`
  and their implementers (`Archive`, `CalendarCurrent`, `DcaSelectField`, `FieldValueChoice`, `CodefogTagsChoice`),
  `src/Filter/Control/ChoiceFilterControl.php` and `DateRangeFilterControl.php` (hand-over call),
  `src/DataContainer/Builder/DcaContext.php`, `src/EventListener/Contao/ElementDcaListener.php`,
  `src/Filter/ValueProvider/ChoiceValueProvider.php`, `src/Query/Executor/ListQueryDirector.php`
- Test: `tests/Filter/ElementScopeTest.php`, `tests/Filter/FilterContextFactoryTest.php`, `tests/Filter/BuildsFilterContextsTrait.php`,
  element tests, stubs (`tests/List/StubFilterElement.php`, `tests/Filter/ValueProvider/ProviderStub*Element.php`),
  anonymous elements in other tests

**Interfaces:**
- Consumes: `Filter` and bindings (Task 2).
- Produces:
  - `final readonly class ElementScope(public ListSpec $list, public Filter $filter, public ContextInterface $engineContext)`
    with `get(string $key): mixed` and `has(string $key): bool`, both delegating to `$this->filter->element->config`.
  - `interface FilterContextBuilderInterface` (`@api`): `addPredicate(string $predicateClass, array $options = [], ?string $targetAlias = null): static`, `abort(): never`.
  - `FilterContextBuilder` (`@internal`) `implements FilterContextBuilderInterface`, `__construct(FormulaBuilderInterface $formulaBuilder)`, `build(): FilterContext`. No scope property.
  - `final readonly class FilterContext(public Formula $formula)`, without `$scope`.
  - `FilterContextBuilderFactory::create(ElementScope $scope): FilterContextBuilder`;
    `FilterContextFactory::create(ElementScope $scope, ?ValueInterface $value): FilterContext`.
  - `FilterElementInterface::buildContext(FilterContextBuilderInterface $builder, ElementScope $scope, ?ValueInterface $value): void`.
  - `ChoiceSourceContract::buildChoices(ChoicesBuilder $choices, ElementScope $scope): void`; `DateBoundsContract::getDateBounds(ElementScope $scope): DateBounds`.
  - `DcaContext::getElementScope(): ?ElementScope` replaces `getFilterScope()`. The constructor parameter becomes `?\Closure $elementScopeFactory`.
    `ElementDcaListener::createElementScope()` replaces `createFilterScope()`.
  - Temporary: `FilterScope::forElement(): ElementScope` (moves to `ControlScope` in Task 4).

- [ ] **Step 1: Write the failing tests**
  - `ElementScopeTest::testGetReadsTheElementConfig` (a filter that has a control whose config has the same key with a different value: `get()` returns the element's value).
  - `ElementScopeTest::testGetThrowsForUndefinedKey` (`UndefinedConfigKeyError`).
  - `ElementScopeTest::testHasMirrorsTheElementConfig`.
  - `FilterContextFactoryTest::testElementReceivesTheScope`: a capturing element asserts `assertSame($scope, $captured)`. Replace `testBuildsContextOverTheScope` with it, since the context no longer carries a scope.
  - `FilterContextFactoryTest::testBuildIsNotExposedToElements`: `(new \ReflectionClass(FilterContextBuilderInterface::class))->hasMethod('build') === false`.

- [ ] **Step 2: Run them and confirm they fail**: `make test tests/Filter/ElementScopeTest.php tests/Filter/FilterContextFactoryTest.php`.

- [ ] **Step 3: Implement** the types above. In each element, the body's `$builder->scope` becomes `$scope`. The config
  reads stay array reads, now `$scope->filter->element->config->all()[...]`. The controls hand over with
  `$element->buildChoices($choices, $scope->forElement())` and `$element->getDateBounds($scope->forElement())`.
  The director builds `ElementScope`.

- [ ] **Step 4: Adapt the tests.** `BuildsFilterContextsTrait` builds an `ElementScope`. The stubs and anonymous
  elements get the new signature (`grep -rn "function buildContext" tests src`).

- [ ] **Step 5: Run** `make test` and `make phpstan`. Expected: green.

- [ ] **Step 6: Commit**: `refactor!: pass ElementScope to element hooks and narrow the context builder to an interface`

---

### Task 4: Control side: `ControlScope` replaces `FilterScope`

**Files:**
- Create: `src/Filter/ControlScope.php`
- Delete: `src/Filter/FilterScope.php`
- Modify: `src/Filter/Control/FilterControlInterface.php`, `AbstractFilterControl.php`, all five controls,
  `src/Form/Factory/FormHarnessFactory.php`, `src/Form/FormHarness.php`, `src/Form/FilterMount.php`,
  `src/Event/FilterControlBuildFormEvent.php`, `src/EventListener/NamedDispatch/FilterControlBuildFormListener.php`
- Test: `tests/Filter/ControlScopeTest.php`, `tests/Filter/Control/BuildsControlScopesTrait.php`, control tests,
  `tests/Form/FormHarnessFactoryTest.php`, `tests/Form/FormHarnessTest.php`,
  `tests/EventListener/NamedDispatch/FilterControlBuildFormListenerTest.php`, registry/compiler-pass tests with
  anonymous controls

**Interfaces:**
- Consumes: `ElementScope` (Task 3), bindings (Task 2).
- Produces:
  - `final readonly class ControlScope(public ListSpec $list, public Filter $filter, public ContextInterface $engineContext)`.
    The constructor throws `\InvalidArgumentException` when `$filter->control === null`.
    It has `get(string $key): mixed`, `has(string $key): bool` (delegating to `$this->filter->control->config`) and
    `forElement(): ElementScope` (same list, filter and engine context).
  - `ControlScope::ATTR_SELF = 'flare.filter_scope'` (stores the `ControlScope`) and
    `ControlScope::ATTR_SINGLE_FIELD = 'flare.single_field'`.
  - `FilterControlInterface::buildForm(FilterFormBuilderInterface $builder, ControlScope $scope): void`,
    `decode(ControlScope $scope, mixed $data): ?ValueInterface`, `hydrate(ControlScope $scope, ?ValueInterface $value): mixed`.
  - `FilterMount::$scope` and `FilterControlBuildFormEvent::$scope` are typed `ControlScope`.

- [ ] **Step 1: Write the failing tests** in `tests/Filter/ControlScopeTest.php`:
  - `testRejectsAFilterWithoutControl` (`\InvalidArgumentException`).
  - `testGetReadsTheControlConfig` (the element config has the same key with a different value; the control's value is returned).
  - `testForElementKeepsListFilterAndContext` (`assertSame` on all three; `forElement()->get()` reads the element config).

  In `FormHarnessFactoryTest`, line ~196 asserts `ControlScope::class` for `ATTR_SELF`.

- [ ] **Step 2: Run them and confirm they fail**: `make test tests/Filter/ControlScopeTest.php`.

- [ ] **Step 3: Implement** `ControlScope`, delete `FilterScope`, and apply the new signatures and parameter order to
  all controls and call sites (`FormHarnessFactory`: `hydrate($scope, $filter->value)`; `FormHarness`:
  `decode($mount->scope, $child->getData())`). Afterwards `grep -rn "FilterScope" src tests` returns nothing.

- [ ] **Step 4: Adapt the tests.** The trait returns a `ControlScope`, and control tests and anonymous controls use the
  new parameter order.

- [ ] **Step 5: Run** `make test` and `make phpstan`. Expected: green.

- [ ] **Step 6: Commit**: `refactor!: replace FilterScope with ControlScope and order control hook parameters scope-first`

---

### Task 5: Components read their own config via `$scope->get()`

**Files:**
- Modify: all elements in `src/Filter/Element/`, all controls in `src/Filter/Control/`,
  `src/Integration/CodefogTags/FilterElement/CodefogTagsChoiceFilterElement.php`
- Test: `tests/Query/Executor/ListQueryDirectorTest.php`, `tests/Form/FormHarnessFactoryTest.php`,
  `tests/Filter/ValueProvider/ChoiceValueProviderTest.php`

**Interfaces:**
- Consumes: `ElementScope::get()`, `ControlScope::get()`, `UndefinedConfigKeyError`.
- Produces: no new API.

- [ ] **Step 1: Write the failing-path tests** (they pin the fact that `UndefinedConfigKeyError` escapes; they may
  already pass, since no catch block catches `\Error`):
  - `ListQueryDirectorTest::testUndefinedConfigKeyEscapesWithoutRetry`: a controlled filter whose element calls
    `$scope->get('typo')` only when `$value !== null` and counts its invocations. With a value in the form value map,
    `invokeFilters()` throws `UndefinedConfigKeyError` and the element ran exactly **once**.
  - `FormHarnessFactoryTest::testUndefinedConfigKeyInBuildFormIsNotSwallowed`: the control calls `$scope->get('typo')` in `buildForm()`, and creating the harness throws `UndefinedConfigKeyError`.
  - `FormHarnessFactoryTest::testUndefinedConfigKeyInElementContractIsNotSwallowed`: the control calls `buildChoices($choices, $scope->forElement())`, the element reads `get('typo')`, and the result is `UndefinedConfigKeyError`.
  - `ChoiceValueProviderTest::testUndefinedConfigKeyEscapesTheOptionsCallback`: call `buildDcaField()` with a
    `DcaFieldBuilderInterface` mock that captures the `options()` callable and a `DcaContext` whose element-scope
    factory returns a scope over a `ChoiceSourceContract` element reading `get('typo')`. Invoking the captured
    callable throws `UndefinedConfigKeyError`.

- [ ] **Step 2: Run them**: `make test tests/Query/Executor/ListQueryDirectorTest.php tests/Form/FormHarnessFactoryTest.php tests/Filter/ValueProvider/ChoiceValueProviderTest.php`.
  Expected: the new tests pass. If one fails, a catch block swallows `\Error`. Fix that catch, do not widen any other.

- [ ] **Step 3: Rewrite the component reads.** Every own-config read becomes `$scope->get('<key>')` and the `?? fallback`
  goes away (§3.2: no fallback differs from its schema default). Use per-key `get()` instead of local `$config` arrays.
  `all()` is acceptable only where a helper takes the whole array (`CalendarCurrentFilterElement::resolveFormLimits()`).
  Check: `grep -rnE "config->all\(\)\[" src` returns nothing.

- [ ] **Step 4: Run** `make test` and `make phpstan`. Expected: green.

- [ ] **Step 5: Commit**: `refactor: read own component config via $scope->get() in bundle elements and controls`

---

### Task 6: Documentation

**Files:**
- Modify: `AGENTS.md` (the architecture paragraphs naming `FilterScope`, `FilterContextFactory::create(FilterScope, ?value)`
  and the two-stage model; the `CLAUDE.md` symlink follows)
- Modify (docs worktree, branch `docs/main`): `docs/docs/dev/filter-elements/index.md`, `docs/docs/dev/filter-controls.md`,
  `docs/docs/dev/contracts/index.mdx`, `docs/docs/dev/events.md`, `docs/docs/spec/filtering.md`,
  `docs/docs/migrating-from-v0.1.md`, `docs/docs/removed-in-v0.2.md`

- [ ] **Step 1: Update `AGENTS.md`.** The two stages become `ElementScope`/`ControlScope` (value-free, role-typed,
  `get()` for the own config) and `FilterContext` (the element's product, built via `FilterContextBuilderInterface`).
  `Filter` = `ElementBinding` + `?ControlBinding` over `ComponentConfig`. An undefined key throws `UndefinedConfigKeyError` (`\Error`).
- [ ] **Step 2: Update the docs pages.** Examples use the §3 signatures and `$scope->get()`. `removed-in-v0.2.md`
  lists `FilterScope`, `Filter::$config`/`$controlConfig`/`$controlType`/`$type` and `DcaContext::getFilterScope()`,
  with the old → new mapping from spec §2.3 and §3.
- [ ] **Step 3: Verify** with `grep -rnE "FilterScope|controlConfig|builder->scope" AGENTS.md docs/docs`. Expected: no
  matches except intentional "removed" entries.
- [ ] **Step 4: Commit both.** Repo: `docs: describe role-bound scopes and component configs in AGENTS.md`.
  Docs worktree (`git -C docs`): `docs: document ElementScope/ControlScope and $scope->get()`.
