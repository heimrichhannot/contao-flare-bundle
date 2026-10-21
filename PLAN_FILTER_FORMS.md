# Filter Controls Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Finish the v0.2 filter refactor: elements own matching, `FilterControl`s own presentation, value objects carry what the user said, and the backend, DB schema and migration follow.

**Architecture:** A filter is applied in two stages — a value-free `FilterScope` for everything before the value exists (controls, ports, providers, backend), and a `FilterContext` built by the element's `buildContext()` only once the value is known. Controls are registered per value class (read from `decode()`'s return type); stored values are turned into value objects by one `ValueProvider` per value class; choices are key-identified objects. Element and control config are separated down to the column (`elm_` / `ctl_`).

**Tech Stack:** PHP ^8.2, Symfony 5.4–7 (DI, Form, OptionsResolver), Contao 4.13/5, PHPUnit 9, PHPStan level 5, Docker via `make`.

**Spec:** `SPEC_FILTER_FORMS.md` (approved 2026-10-21). Section references (§n) point at it. Read §2–§11 and §13 before starting any task.

## Global Constraints

- PHP floor **8.2**: no typed class constants, no `#[\Override]`; `readonly` classes are fine.
- Contao **4.13** compatible: one DB column per config value (no JSON/virtual fields); MySQL 5.7 — column renames use `ALTER TABLE … CHANGE`, never `RENAME COLUMN`.
- `declare(strict_types=1);` in every PHP file; PSR-12/PER, 4 spaces, 120-char lines; attributes over annotations.
- The `php` binary is unavailable: run `make test <phpunit args>`, `make phpstan`, `make semgrep-sec`. Mago runs in CI only.
- Breaking changes are allowed (v0.2 unreleased); no deprecation layers, no legacy adapters.
- The tree is **red until Task 11**: until then verify each task with the targeted `make test tests/…` commands it names. From Task 11 on, `make test` and `make phpstan` must be fully green after every task.
- Commits: on branch `v0.2-refactor-filter-api`, **no `Co-Authored-By` trailer**, never push without the user's explicit OK.
- `docs/` is a separate git worktree on branch `docs/main`; doc changes are committed there separately (Task 15). `docs/versioned_docs/version-0.1/**` stays frozen.
- Names (§2.1, §2.2, §13.26–27): per-filter strategy = `FilterControl` (`src/Filter/Control/`, `#[AsFilterControl]`, tag `flare.filter_control`, alias `flare.filter_control.{name}`); "form" names only the Symfony level (`FormHarness`, `FilterFormBuilder`, mount). Control names: `flare_choice`, `flare_checkbox`, `flare_choice_bool`, `flare_date_range`, `flare_keywords`.
- Framework columns `type`, `control`, `alias`, `value`; element columns `elm_*`, control columns `ctl_*` (§4.1). Until Task 13 the code still reads the old column names (`formVariant`, `fieldGeneric`, …).

## Review Focus

1. **Two options with the same display label** (FieldValueChoice on a `foreignKey` field): both must stay selectable and filter by their own value (§7.1). → Task 2 test `testEqualLabelsKeepDistinctSubmittedValues`, Task 7 test `testDuplicateLabelsResolveToDistinctValues`.
2. **Old Archive URLs `?archive=5` in static-ptable mode** must keep matching, while stored keys are `tl_news_archive.5` (§7.2, §13.22). → Task 2 test `testSingleScopeListSubmitsLocalKeys`, Task 9 test `testHydratedCanonicalReferenceMatchesShortSubmittedKey`.
3. **A controlled filter's initial value on a reader page** must not restrict validation; a fixed value must (§4.2, §13.30). → Task 11 tests `testInitialValueIsIgnoredWithoutAFormValueMap`, `testFixedValueAppliesInValidation`.
4. **TRUE_FALSE checkbox, form never submitted, no initial value** must not filter `false` (untouched ≠ unchecked). → Task 9 test `testUntouchedCheckboxContributesNothingEvenUnderTrueFalse`.
5. **Broken fixed filter vs. broken control** (§8.1): fixed → list fails (message, HTTP 200; reader 500); control → filter skipped with a warning. → Task 6 tests `testUnknownControlDegradesToNoControlAndNoValue`, `testFailingFixedFilterIsRecordedAsListFailure`, Task 10 test `testControlFailingToBuildIsSkipped`, Task 11 tests `testFailingControlledFilterIsSkipped`, `testFailingFixedFilterFailsTheList`, `testProjectorReturnsFailedViewWhenAFixedFilterFails`.

## File map

Created:
- `src/Filter/FilterScope.php`, `src/Engine/Context/ConfigurationContext.php`
- `src/Filter/Value/Choice/{ChoiceObjectInterface,ChoiceKey,ContaoModelChoice,ValueChoice}.php`
- `src/Filter/Control/{FilterControlInterface,AbstractFilterControl,ChoiceFilterControl,CheckboxFilterControl,ChoiceBoolFilterControl,DateRangeFilterControl,KeywordsFilterControl}.php` (replaces `src/Filter/Form/`)
- `src/Filter/ValueProvider/{ValueProviderInterface,BoolValueProvider,ChoiceValueProvider,DateRangeValueProvider,KeywordsValueProvider}.php`
- `src/Filter/ExpectsValueTrait.php`
- `src/Contract/FilterElement/DateBoundsContract.php`
- `src/DependencyInjection/Attribute/AsFilterControl.php`, `src/DependencyInjection/Compiler/{RegisterFilterControlsPass,RegisterValueProvidersPass,ValueClassReflector}.php`
- `src/Registry/{FilterControlRegistry,ValueProviderRegistry}.php`
- `src/Event/FilterControlBuiltEvent.php`, `src/EventListener/NamedDispatch/FilterControlListener.php`
- `src/Exception/UnreadableValueException.php`, `src/List/Collector/CollectedFilters.php`
- `src/Migration/{RenameFilterColumnsMigration,BackfillFilterValuesMigration}.php`

Deleted: `src/Filter/Form/*`, `AsFilterForm`, `RegisterFilterFormsPass`, `FilterFormRegistry`, `FilterFormBuiltEvent`, NamedDispatch `FilterFormListener` and `FilterElementListener`, `FilterElementBuildingEvent`, `FilterElementBuiltEvent`, `IntrinsicContract`, `FilterData`, `Value/ParentRefValue`, `Util/Fingerprint`, `Enum/BoolMode`, and their tests.

---

### Task 1: Choice objects and value objects

**Files:**
- Create: `src/Filter/Value/Choice/ChoiceObjectInterface.php`, `ChoiceKey.php`, `ContaoModelChoice.php`, `ValueChoice.php`
- Modify: `src/Filter/Value/{ValueInterface,BoolValue,ChoiceValue,DateRangeValue,KeywordsValue}.php`
- Delete: `src/Filter/Value/ParentRefValue.php`, `src/Util/Fingerprint.php`, `tests/Filter/Value/ParentRefValueTest.php`, `tests/Util/FingerprintTest.php`
- Test: `tests/Filter/Value/{ChoiceValueTest,BoolValueTest,DateRangeValueTest,KeywordsValueTest,ValueObjectContainmentTest}.php`, new `tests/Filter/Value/Choice/ChoiceObjectsTest.php`

**Interfaces:**
- Produces:
  - `interface ValueInterface { public function fingerprint(): string; }`
  - `interface ChoiceObjectInterface { public function getChoiceScope(): ?string; public function getChoiceLocalKey(): string; public function getLabelParameters(): array; }`
  - `final class ChoiceKey { public static function of(ChoiceObjectInterface $choice): string }` — `"scope.local"`, or `"local"` when scope is null.
  - `final readonly class ContaoModelChoice implements ChoiceObjectInterface { public string $table; public int $id; public static function from(Model $model): self; public static function ref(string $table, int $id): self; public function getModel(): ?Model }` — scope = table, local key = `(string) $id`; label parameters from the model row when held (`%field%` per column), else `[]`.
  - `final readonly class ValueChoice implements ChoiceObjectInterface { public function __construct(public string $value, public ?string $label = null) }` — scope `null`, local key = `$value`, label parameters `['%value%' => $value]`.
  - `ChoiceValue`: `/** @var non-empty-list<ChoiceObjectInterface> */ public array $choices`; ctor deduplicates by `ChoiceKey::of()` and sorts by it; `public static function tryFrom(mixed $data): ?self` accepts one choice object or an iterable of them, ignores `ChoicesBuilder::EMPTY_CHOICE`, `null` and non-choice entries, returns `null` when nothing remains.
  - `BoolValue`: `public bool $state`; `tryFrom(mixed $value): ?self` — **no** `BoolBinaryChoices` parameter any more (the mapping moves to the checkbox control, §7.5).
  - Fingerprints (§9): `BoolValue` → `'1'|'0'`; `ChoiceValue` → `\json_encode` of the sorted canonical keys; `DateRangeValue` → `"{fromTs}|{toTs}"` with `''` for null; `KeywordsValue` → the normalized phrase.

- [ ] **Step 1: Write the failing tests**

```php
// tests/Filter/Value/Choice/ChoiceObjectsTest.php
public function testModelReferenceHasCanonicalTableKey(): void
{
    $ref = ContaoModelChoice::ref('tl_news_archive', 5);
    self::assertSame('tl_news_archive', $ref->getChoiceScope());
    self::assertSame('5', $ref->getChoiceLocalKey());
    self::assertSame('tl_news_archive.5', ChoiceKey::of($ref));
    self::assertNull($ref->getModel());
}

public function testValueChoiceWithADotIsUnscoped(): void
{
    $choice = new ValueChoice('1.5', 'One and a half');
    self::assertNull($choice->getChoiceScope());
    self::assertSame('1.5', ChoiceKey::of($choice));
}

// tests/Filter/Value/ChoiceValueTest.php (rewrite)
public function testDeduplicatesAndSortsByCanonicalKey(): void
{
    $value = new ChoiceValue([new ValueChoice('b'), new ValueChoice('a'), new ValueChoice('b', 'dup')]);
    self::assertSame(['a', 'b'], \array_map(ChoiceKey::of(...), $value->choices));
}

public function testTryFromIgnoresTheEmptySentinelAndYieldsNullWhenNothingRemains(): void
{
    self::assertNull(ChoiceValue::tryFrom([ChoicesBuilder::EMPTY_CHOICE, null]));
    self::assertNull(ChoiceValue::tryFrom(null));
    self::assertCount(1, ChoiceValue::tryFrom(new ValueChoice('x'))->choices);
}

public function testFingerprintIsOrderIndependentAndKeyBased(): void
{
    $a = new ChoiceValue([ContaoModelChoice::ref('t', 2), ContaoModelChoice::ref('t', 1)]);
    $b = new ChoiceValue([ContaoModelChoice::ref('t', 1), ContaoModelChoice::ref('t', 2)]);
    self::assertSame($a->fingerprint(), $b->fingerprint());
    self::assertSame('["t.1","t.2"]', $a->fingerprint());
}
```

Add one fingerprint assertion each to `BoolValueTest` (`'1'`), `DateRangeValueTest` (`'0|'` for `tryFrom(0, null)`), `KeywordsValueTest` (`'foo bar'` for `"  foo   bar "`). In `BoolValueTest` delete `testTryFromMatchesTheElementItReplaces` and `testBinaryChoicesDecideWhetherFalsyCollapses` (the `BoolBinaryChoices` parameter is gone; Task 9 tests the mapping). Reduce `ValueObjectContainmentTest` to one test: every concrete class in `src/Filter/Value/` (non-recursive; `ValueInterface` excluded) and every value class *named* in an `#[AsFilterElement(value: …)]` is `final`, `readonly` and implements `ValueInterface`; delete its property-type walk and samples.

- [ ] **Step 2: Run tests to verify they fail**

Run: `make test tests/Filter/Value`
Expected: FAIL — `ChoiceObjectInterface`/`ChoiceKey` not found, `fingerprint()` undefined.

- [ ] **Step 3: Implement the interfaces above**

`ContaoModelChoice` holds the model in a private readonly `?Model` property; `from()` takes table from `$model::getTable()` and id from `$model->id`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `make test tests/Filter/Value`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add -A src/Filter/Value src/Util tests/Filter/Value tests/Util
git commit -m "feat!: key-identified choice objects and value fingerprints"
```

---

### Task 2: ChoicesBuilder on choice objects

**Files:**
- Modify: `src/Form/ChoicesBuilder.php`
- Test: `tests/Form/ChoicesBuilderTest.php`

**Interfaces:**
- Consumes: Task 1 choice objects, `ChoiceKey::of()`.
- Produces:
  - `add(ChoiceObjectInterface $choice): static` — keyed by canonical key; re-adding a key overwrites in place. The `$alias`/`$value`/`$group` parameters, `$choiceValues`, `addGroup()`, `removeGroup()`, `$groups`, `$choiceGroupMap`, `getChoice()` and `setLabelForModel()` are removed (release note: `@api` removals).
  - `buildChoices(): array<string, ChoiceObjectInterface|string>` — canonical key ⇒ choice, `EMPTY_CHOICE` first when enabled.
  - `usesLocalKeys(): bool` — true iff the choices have at most one distinct scope (§7.2).
  - `buildChoiceValueCallback(): \Closure(mixed): string` — `EMPTY_CHOICE` ⇒ empty option value; a `ChoiceObjectInterface` ⇒ its local key if `usesLocalKeys()`, else its canonical key; anything else ⇒ `''`. No lookup in the list: a reference equal to a listed option yields the same value.
  - `buildContaoOptions(): array<string, string>` — keyed by **canonical** key (`''` for the empty option).
  - `buildChoiceLabel()`: a `ContaoModelChoice` with a model resolves labels as models do today (table from scope); without a model, the label is `"{table}.{id}"`; a `ValueChoice` uses its label (translated as strings are today) or its value.

- [ ] **Step 1: Rewrite the characterisation tests for the new contract**

Replace every string/alias-based test. Keep the empty-option, count and label-placeholder tests, ported to `ValueChoice`. New tests:

```php
public function testEqualLabelsKeepDistinctSubmittedValues(): void
{
    $builder = self::builder()->add(new ValueChoice('1', 'Same'))->add(new ValueChoice('2', 'Same'));
    $toValue = $builder->buildChoiceValueCallback();
    self::assertSame(['1', '2'], \array_map($toValue, \array_values($builder->buildChoices())));
}

public function testSingleScopeListSubmitsLocalKeys(): void
{
    $builder = self::builder()->add(ContaoModelChoice::ref('tl_news_archive', 5));
    self::assertTrue($builder->usesLocalKeys());
    self::assertSame('5', ($builder->buildChoiceValueCallback())(ContaoModelChoice::ref('tl_news_archive', 5)));
}

public function testMixedScopesSubmitCanonicalKeys(): void
{
    $builder = self::builder()->add(ContaoModelChoice::ref('tl_a', 1))->add(ContaoModelChoice::ref('tl_b', 1));
    self::assertFalse($builder->usesLocalKeys());
    self::assertSame('tl_b.1', ($builder->buildChoiceValueCallback())(ContaoModelChoice::ref('tl_b', 1)));
}

public function testContaoOptionsAreKeyedByCanonicalKey(): void
{
    $options = self::builder()->add(ContaoModelChoice::ref('tl_a', 1))->buildContaoOptions();
    self::assertSame(['tl_a.1'], \array_keys($options));
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `make test tests/Form/ChoicesBuilderTest.php`
Expected: FAIL — `add()` signature mismatch.

- [ ] **Step 3: Implement the interface above in `src/Form/ChoicesBuilder.php`**

- [ ] **Step 4: Run tests to verify they pass**

Run: `make test tests/Form/ChoicesBuilderTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Form/ChoicesBuilder.php tests/Form/ChoicesBuilderTest.php
git commit -m "feat!: ChoicesBuilder takes choice objects and shortens keys per list"
```

---

### Task 3: FilterScope, ConfigurationContext and the two-stage context

**Files:**
- Create: `src/Filter/FilterScope.php`, `src/Engine/Context/ConfigurationContext.php`, `tests/Filter/BuildsFilterContextsTrait.php`, `tests/Filter/FilterContextFactoryTest.php`
- Modify: `src/Filter/{FilterContext,FilterContextBuilder,Formula,FormulaBuilder,FormulaBuilderInterface,Proposition}.php`, `src/Filter/Factory/{FilterContextFactory,FilterContextBuilderFactory,FormulaBuilderFactory}.php`
- Rename: `tests/Filter/FilterBuilderTest.php` → `tests/Filter/FormulaBuilderTest.php` (class `FormulaBuilderTest`)

**Interfaces:**
- Produces:
  - `final readonly class FilterScope { public const ATTR_SELF = 'flare.filter_scope'; public const ATTR_SINGLE_FIELD = 'flare.single_field'; public function __construct(public ListSpec $list, public Filter $filter, public ContextInterface $engineContext) }`
  - `final readonly class ConfigurationContext implements ContextInterface` — `getContextType(): string` returns `'configuration'`.
  - `final readonly class FilterContext { public function __construct(public FilterScope $scope, public Formula $formula) }` — the `ATTR_*` constants move to `FilterScope`.
  - `FilterContextBuilder`: `public readonly FilterScope $scope`; `addPredicate(string $predicateClass, array $options = [], ?string $targetAlias = null): self`; `abort(): never`; `build(): FilterContext`. `setPredicates()` is removed.
  - `FilterContextBuilderFactory::create(FilterScope $scope): FilterContextBuilder` — computes the formula's default target alias with the **v0.1 stopgap** (§15): `isTargeted` (from `FilterElementRegistry::getAttribute($filter->type)`) or `targetingForced` ⇒ `$filter->targetAlias ?: TableAliasRegistry::ALIAS_MAIN`; otherwise `ALIAS_MAIN`.
  - `FilterContextFactory::create(FilterScope $scope, ?ValueInterface $value): FilterContext` — keeps the SQL-name check on `$scope->list->dc`, calls `$scope->filter->element->buildContext($builder, $value)`.
  - `@internal` docblock tag on `Proposition`, `Formula`, `FormulaBuilder`, `FormulaBuilderInterface`, `FormulaBuilderFactory`.
  - Test helper `trait BuildsFilterContextsTrait { protected function buildFilterContext(FilterElementInterface $element, array $config = [], ?ValueInterface $value = null, ?ContextInterface $engineContext = null, string $dc = 'tl_test'): FilterContext }` — real `FormulaBuilderFactory` over a `FilterPredicateRegistry` holding every predicate in `src/Filter/Predicate/` (`SearchKeywordsPredicate` takes a `ConfigProvider`: pass a test double); default engine context `new ConfigurationContext()`.
  - `FormulaBuilderTest` (renamed) reads `$builder->build()->propositions`; the old `$builder->all()` no longer exists.

- [ ] **Step 1: Write the failing tests**

```php
// tests/Filter/FilterContextFactoryTest.php
public function testHandsTheValueToTheElement(): void { /* spy element records the ?ValueInterface it got */ }

/** @dataProvider targetAliasCases */
public function testResolvesTheDefaultTargetAliasLikeV01(bool $isTargeted, bool $forced, ?string $alias, string $expected): void
{
    // element adds one predicate without explicit alias; assert $context->formula->propositions[0]->targetAlias
}

public static function targetAliasCases(): iterable
{
    yield 'not targeted ignores alias' => [false, false, 'join', TableAliasRegistry::ALIAS_MAIN];
    yield 'targeted uses alias' => [true, false, 'join', 'join'];
    yield 'targeted without alias' => [true, false, null, TableAliasRegistry::ALIAS_MAIN];
    yield 'forced' => [false, true, 'join', 'join'];
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `make test tests/Filter/FilterContextFactoryTest.php tests/Filter/FormulaBuilderTest.php`
Expected: FAIL — `FilterScope` not found.

- [ ] **Step 3: Implement the interfaces above**

- [ ] **Step 4: Run tests to verify they pass**

Run: `make test tests/Filter/FilterContextFactoryTest.php tests/Filter/FormulaBuilderTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add -A src/Filter src/Engine/Context/ConfigurationContext.php tests/Filter
git commit -m "feat!: value-free FilterScope; FilterContext is built only with the value"
```

---

### Task 4: FilterControl contracts and registration

**Files:**
- Delete: `src/Filter/Form/`, `src/DependencyInjection/Attribute/AsFilterForm.php`, `src/DependencyInjection/Compiler/RegisterFilterFormsPass.php`, `src/Registry/FilterFormRegistry.php`, `src/Event/FilterFormBuiltEvent.php`, `src/EventListener/NamedDispatch/FilterFormListener.php`, their tests
- Create: `src/Filter/Control/{FilterControlInterface,AbstractFilterControl}.php`, `src/Filter/ExpectsValueTrait.php`, `src/DependencyInjection/Attribute/AsFilterControl.php`, `src/DependencyInjection/Compiler/{RegisterFilterControlsPass,ValueClassReflector}.php`, `src/Registry/FilterControlRegistry.php`, `src/Event/FilterControlBuiltEvent.php`, `src/EventListener/NamedDispatch/FilterControlListener.php`
- Modify: `src/DependencyInjection/Factory/TypeNameFactory.php`, `src/DependencyInjection/HeimrichHannotFlareExtension.php`, `src/HeimrichHannotFlareBundle.php`, `config/services.yaml` (drop the `FilterFormRegistry` argument block)
- Test: `tests/DependencyInjection/Compiler/{RegisterFilterControlsPassTest,ValueClassReflectorTest}.php`, `tests/Registry/FilterControlRegistryTest.php`, `tests/EventListener/NamedDispatch/FilterControlListenerTest.php`, `tests/DependencyInjection/Factory/TypeNameFactoryTest.php`

**Interfaces:**
- Consumes: `FilterScope` (Task 3), `ValueInterface` (Task 1).
- Produces:
  - `interface FilterControlInterface { public function buildForm(FilterFormBuilderInterface $builder, FilterScope $scope): void; public function decode(mixed $data, FilterScope $scope): ?ValueInterface; public function hydrate(?ValueInterface $value, FilterScope $scope): mixed; }`
  - `abstract class AbstractFilterControl implements FilterControlInterface, OptionsContract, TransformerContract, DcaContract` — no-op defaults for `configureOptions()`, `configureTransformers()`, `buildDca()`; uses `ExpectsValueTrait`.
  - `trait ExpectsValueTrait { /** @template T of ValueInterface @param class-string<T> $class @return T|null @throws FilterException */ protected function expectValue(?ValueInterface $value, string $class): ?ValueInterface }` — `null` passes, a wrong class throws `FilterException`.
  - `#[\Attribute(TARGET_CLASS | IS_REPEATABLE)] class AsFilterControl { public const TAG = 'flare.filter_control'; public function __construct(?string $name = null, mixed ...$attributes) }`
  - `final class ValueClassReflector { /** @return class-string<ValueInterface> @throws \InvalidArgumentException */ public static function fromReturnType(string $class, string $method): string }` — a single named, non-builtin type that implements `ValueInterface` and is not `ValueInterface` itself; nullability allowed.
  - `RegisterFilterControlsPass` — mirrors `RegisterFilterElementsPass`: `findAndSortTaggedServices`, `clearTag`, name from tag or `TypeNameFactory::createFilterControlType()`; `''`/`'default'` reserved; duplicate name ⇒ `\InvalidArgumentException`; class must implement `FilterControlInterface`; `addMethodCall('add', [$reference, $name, $valueClass])`; public alias `flare.filter_control.{name}`.
  - `final class FilterControlRegistry { add(FilterControlInterface $control, string $name, string $valueClass): self; has(string $name): bool; get(?string $name): ?FilterControlInterface; getValueClass(?string $name): ?string; findNames(string $valueClass): list<string>; keys(): list<string> }`
  - `TypeNameFactory::createFilterControlType(string $className): string` — suffixes `['FilterControl', 'Control']`.
  - `class FilterControlBuiltEvent extends Event { __construct(public readonly FilterFormBuilderInterface $builder, public readonly FilterScope $scope); cancel(): void; isCancelled(): bool }`, re-dispatched by `FilterControlListener` as `flare.filter_control.{$event->scope->filter->type}.built`.

- [ ] **Step 1: Write the failing tests**

```php
// RegisterFilterControlsPassTest
public function testRegistersNameValueClassAndPublicAlias(): void
{
    // tag a PassBoolControl (decode(): ?BoolValue) as ['name' => 'flare_checkbox']
    // assert registry method call ['add', [Reference('test.ctl'), 'flare_checkbox', BoolValue::class]]
    // assert alias 'flare.filter_control.flare_checkbox' is public; tag cleared
}
public function testDerivesTheNameFromTheClass(): void { /* PassDerivedFilterControl → 'pass_derived' */ }
public function testUnnarrowedDecodeReturnTypeFailsTheBuild(): void { /* decode(): ?ValueInterface → InvalidArgumentException */ }
public function testDuplicateNameFailsTheBuild(): void {}
public function testReservedNamesFailTheBuild(): void { /* '' via class named FilterControl, and 'default' */ }
public function testNonControlServiceFailsTheBuild(): void {}

// FilterControlRegistryTest
public function testFindNamesFiltersByValueClassInRegistrationOrder(): void
{
    $r = (new FilterControlRegistry())->add($a, 'a', BoolValue::class)->add($b, 'b', ChoiceValue::class)->add($c, 'c', BoolValue::class);
    self::assertSame(['a', 'c'], $r->findNames(BoolValue::class));
    self::assertNull($r->get('nope'));
}
```

Port `FilterFormListenerTest` to `FilterControlListenerTest` (alias name only changes). Port the four `createFilterFormType` tests in `TypeNameFactoryTest` to `createFilterControlType` (e.g. `ChoiceFilterControl` → `choice`).

- [ ] **Step 2: Run tests to verify they fail**

Run: `make test tests/DependencyInjection tests/Registry/FilterControlRegistryTest.php tests/EventListener/NamedDispatch/FilterControlListenerTest.php`
Expected: FAIL — classes not found.

- [ ] **Step 3: Implement the interfaces above; register the pass and the attribute's autoconfiguration (replace the `AsFilterForm` entries); remove the forms pass**

- [ ] **Step 4: Run tests to verify they pass**

Run: same as Step 2. Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add -A src tests config/services.yaml
git commit -m "feat!: FilterControl contracts, eager registry, value class from decode()"
```

---

### Task 5: Ports, value providers and the backend scope

**Files:**
- Modify: `src/Contract/FilterElement/ChoiceSourceContract.php`, `src/DataContainer/Builder/DcaContext.php`, `src/EventListener/Contao/ElementDcaListener.php`, `src/DependencyInjection/Compiler/RegisterFilterElementsPass.php`, `src/HeimrichHannotFlareBundle.php`
- Create: `src/Contract/FilterElement/DateBoundsContract.php`, `src/Filter/ValueProvider/{ValueProviderInterface,BoolValueProvider,ChoiceValueProvider,DateRangeValueProvider,KeywordsValueProvider}.php`, `src/Registry/ValueProviderRegistry.php`, `src/DependencyInjection/Compiler/RegisterValueProvidersPass.php`, `src/Exception/UnreadableValueException.php`
- Test: `tests/Filter/ValueProvider/{BoolValueProviderTest,ChoiceValueProviderTest,KeywordsValueProviderTest,DateRangeValueProviderTest}.php`, `tests/DependencyInjection/Compiler/{RegisterValueProvidersPassTest,RegisterFilterElementsPassTest}.php`

**Interfaces:**
- Consumes: `ValueClassReflector` (Task 4), choice objects (Task 1), `ChoicesBuilder::buildContaoOptions()` (Task 2), `FilterScope`/`ConfigurationContext` (Task 3).
- Produces:
  - `interface ChoiceSourceContract { public function createChoices(FilterScope $scope): ChoicesBuilder; public function choiceFromKey(string $key): ?ChoiceObjectInterface; }` — `valueFromChoiceKeys()` and `buildChoices()` are gone.
  - `interface DateBoundsContract { /** @return array{0: ?\DateTimeInterface, 1: ?\DateTimeInterface} */ public function getDateBounds(FilterScope $scope): array; }`
  - `#[AutoconfigureTag(ValueProviderInterface::TAG)] interface ValueProviderInterface { public const TAG = 'flare.value_provider'; public function provide(mixed $stored, FilterElementInterface $element): ?ValueInterface; public function buildDcaField(DcaFieldBuilderInterface $field, DcaContext $context): void; }`
  - `final class ValueProviderRegistry { add(ValueProviderInterface $provider, string $valueClass): self; get(?string $valueClass): ?ValueProviderInterface }`
  - `RegisterValueProvidersPass` — value class from `provide()`'s return type via `ValueClassReflector`; one provider per value class (duplicate ⇒ `\InvalidArgumentException`); `addMethodCall('add', [$reference, $valueClass])`. **Registered before `RegisterFilterElementsPass`**, which then fails the build when an element's `value:` has no provider (it reads the provider registry definition's `add` method calls).
  - `class UnreadableValueException extends FilterException` — a non-empty stored value yielded no readable key (§8.1, fail closed).
  - Providers (`provide()` / storage format / `buildDcaField()`):
    - `BoolValueProvider::provide(): ?BoolValue` — `'1'` ⇒ true, `'0'` ⇒ false, `null`/`''` ⇒ null, anything else ⇒ `UnreadableValueException` (fail closed); field: `select`, options `['1' => …yes, '0' => …no]`, `includeBlankOption`.
    - `KeywordsValueProvider::provide(): ?KeywordsValue` — `KeywordsValue::tryFrom()`; field: `text`.
    - `DateRangeValueProvider::provide(): ?DateRangeValue` — stored = serialized `[fromTs|null, toTs|null]` **timestamps**; non-numeric non-empty entries ⇒ `UnreadableValueException`. Field: `text`, `eval: ['multiple' => true, 'size' => 2, 'rgxp' => 'date']` with a `load()` callback (timestamps → `Date::parse(Date::getNumericDateFormat(), $ts)`) and a `save()` callback (date strings → `(new Date($s, Date::getNumericDateFormat()))->tstamp`), because Contao does not convert `multiple` text values itself.
    - `ChoiceValueProvider::provide(): ?ChoiceValue` — stored = serialized list of canonical keys; element must implement `ChoiceSourceContract` (else `FilterException`); maps `choiceFromKey()`; non-empty stored but no readable key ⇒ `UnreadableValueException`; empty ⇒ null. Field: `select`, `eval: ['multiple' => true, 'chosen' => true]`, options = `$element->createChoices($scope)->buildContaoOptions()` from `$context->getFilterScope()`; a `FilterException` or a `null` scope ⇒ `Message::addError()` and `[]`.
  - `DcaContext`: new ctor params `public readonly ?FilterElementInterface $element = null` and `private readonly ?\Closure $filterScopeFactory = null`; `getFilterScope(): ?FilterScope` (lazy, memoised, `null` on failure).
  - `ElementDcaListener` builds the scope from the `ListSpec` it already builds in `createExecutionContext()` (memoise the spec), `FilterFactory::createFromFilterModel($filterModel, withControlAndValue: false)` (Task 6 — options need neither, and a stale control right after a `type` change must not empty them) and `new ConfigurationContext()`; any `\Throwable` ⇒ `null`.

- [ ] **Step 1: Write the failing tests**

```php
// ChoiceValueProviderTest (stub element implementing ChoiceSourceContract: choiceFromKey('k1') → new ValueChoice('k1'), else null)
public function testResolvesStoredKeysToReferenceChoices(): void
{
    $value = $provider->provide(\serialize(['k1']), $element);
    self::assertSame(['k1'], \array_map(ChoiceKey::of(...), $value->choices));
}
public function testEmptyStoredValueIsNoValue(): void { self::assertNull($provider->provide('', $element)); self::assertNull($provider->provide(null, $element)); }
public function testUnreadableNonEmptyValueFailsClosed(): void
{
    $this->expectException(UnreadableValueException::class);
    $provider->provide(\serialize(['garbage']), $element);
}
public function testElementWithoutChoiceSourceIsRejected(): void { $this->expectException(FilterException::class); /* … */ }

// BoolValueProviderTest
public function testMapsStoredFlags(): void { self::assertTrue($p->provide('1', $el)->state); self::assertFalse($p->provide('0', $el)->state); self::assertNull($p->provide('', $el)); }
public function testUnreadableFlagFailsClosed(): void { $this->expectException(UnreadableValueException::class); $p->provide('yes', $el); }
// DateRangeValueProviderTest: serialize([0, null]) ⇒ from = epoch, to = null; serialize(['x', null]) ⇒ UnreadableValueException

// RegisterValueProvidersPassTest: registers by return type; duplicate fails; unnarrowed fails
// RegisterFilterElementsPassTest
public function testElementValueClassWithoutProviderFailsTheBuild(): void {}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `make test tests/Filter/ValueProvider tests/DependencyInjection/Compiler`
Expected: FAIL — classes not found.

- [ ] **Step 3: Implement the interfaces above**

- [ ] **Step 4: Run tests to verify they pass**

Run: same as Step 2. Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add -A src tests
git commit -m "feat!: element ports, per-value-class value providers, backend FilterScope"
```

---

### Task 6: Filter DTO, resolvers, FilterFactory and the collector

**Files:**
- Modify: `src/Filter/Filter.php`, `src/Filter/Element/FilterElementInterface.php`, `src/Filter/Factory/FilterFactory.php`, `src/Filter/Resolver/{FilterOptionsResolver,FilterTransformerResolver}.php`, `src/List/Collector/ListModelFilterCollector.php`, `src/List/Factory/ListSpecBuilderFactory.php`, `src/List/{ListSpec,ListSpecBuilder}.php`
- Create: `src/List/Collector/CollectedFilters.php`
- Test: `tests/Filter/{FilterTest,FilterFactoryTest,FilterOptionsResolverTest,FilterTransformerResolverTest}.php`, `tests/List/{ListSpecTest,ListSpecBuilderTest,StubFilterElement,ListModelFilterCollectorTest}.php`

**Interfaces:**
- Consumes: `FilterControlRegistry` (Task 4), `ValueProviderRegistry` (Task 5).
- Produces:
  - `FilterElementInterface` declares only `buildContext(FilterContextBuilder $builder, ?ValueInterface $value): void` (`buildForm()` leaves the interface here so `StubFilterElement` loads; elements drop their bodies in Tasks 7/8).
  - `Filter::__construct(FilterElementInterface $element, string $type, string $alias, array $config = [], ?FilterControlInterface $control = null, array $controlConfig = [], ?ValueInterface $value = null, ?string $targetAlias = null, bool $targetingForced = false, ?string $source = null)`; withers `withValue(?ValueInterface)`, `withControl(?FilterControlInterface, array $controlConfig = [])`, `withTargetAlias(?string, bool $forced = true)` (each enumerates every ctor arg); `withData()` and `withAlias()` are deleted.
  - `Filter::fingerprint(): array` keys: `element`, `type`, `alias`, `config`, `control` (class or null), `controlConfig`, `value` (`$value?->fingerprint()`), `targetAlias`, `targetingForced`.
  - `FilterOptionsResolver::resolve(FilterElementInterface|FilterControlInterface $owner, array $config): array` — schema key `$owner::class`.
  - `FilterTransformerResolver::transform(FilterElementInterface|FilterControlInterface $owner, string $type, object $source): ?array` — `FilterTransformerEvent` is dispatched for elements only.
  - Element value class: `FilterElementRegistry::getAttribute($type)?->value`, falling back to reading `#[AsFilterElement]` by reflection on the element class (elements passed as instances have no registry attribute).
  - `FilterFactory::create(FilterElementInterface|string $element, ?string $alias = null, array $config = [], FilterControlInterface|string|null $control = null, array $controlConfig = [], ?ValueInterface $value = null, ?string $targetAlias = null, bool $targetingForced = false, ?string $source = null): Filter` — a control name resolves via the registry; a control or value whose class does not match the element's value class ⇒ `FlareException` (programming error).
  - `FilterFactory::createFromFilterModel(FilterModel $model, bool $withControlAndValue = true): Filter` — element config as today (its failure throws). With `$withControlAndValue`: control name `(string) $model->formVariant` (renamed `control` in Task 13). **Fail open (§8.1):** an unknown control, a control registered for another value class, or an `UnreadableValueException` for a controlled filter ⇒ warning logged, filter kept with `control: null, value: null` (it keeps the element's own bounds, e.g. Archive's whitelist). Without a control, the value comes from `ValueProviderRegistry::get($class)->provide($model->value, $element)`; an `UnreadableValueException` there propagates (fail closed). (`value` column arrives in Task 12; until then `null`.)
  - `final readonly class CollectedFilters { public function __construct(public array $filters = [], public array $failures = []) }` — `array<string, Filter>` and `list<string>` (failure messages with source).
  - `ListModelFilterCollector::collect(ListModel $listModel): ?CollectedFilters` — a `FlareException` from `createFromFilterModel()`: `(string) $model->formVariant !== ''` ⇒ warning, skip (fail open, only element config can fail here); `''` ⇒ error logged and the message added to `failures` (fail closed). It never throws for a broken filter, so backend callbacks building the ListSpec keep working. Extract the per-model loop as `collectFromModels(iterable $models, ListModel $listModel): CollectedFilters` for tests.
  - `ListSpecBuilder::addFailure(string $failure): self`; `ListSpec` gains `public array $failures = []` (`list<string>`), carried over by every `with*()`; `ListSpecBuilderFactory` adds the collected filters and failures.

- [ ] **Step 1: Write the failing tests**

```php
// FilterTest
public function testWithersPreserveControlConfigAndValue(): void {}
public function testFingerprintChangesWithControlAndValue(): void
{
    $base = self::filter();
    self::assertNotSame($base->fingerprint(), $base->withControl($control)->fingerprint());
    self::assertNotSame($base->fingerprint(), $base->withValue(new BoolValue(true))->fingerprint());
}

// FilterFactoryTest
public function testUnknownControlDegradesToNoControlAndNoValue(): void { /* formVariant 'gone' ⇒ control null, value null, warning logged */ }
public function testControlForAnotherValueClassDegradesLikeAnUnknownOne(): void {}
public function testUnreadableFixedValueThrows(): void { $this->expectException(UnreadableValueException::class); }
public function testValueComesFromTheProviderOfTheElementsValueClass(): void {}
public function testWithoutControlAndValueSkipsBoth(): void {}
public function testProgrammaticValueOfTheWrongClassIsRejected(): void { $this->expectException(FlareException::class); }
public function testValueClassIsReadFromTheAttributeOfAnElementInstance(): void {}

// ListModelFilterCollectorTest (FilterModel instances created without constructor, properties set directly)
public function testFailingFixedFilterIsRecordedAsListFailure(): void {}
public function testFailingControlledFilterIsSkippedWithAWarning(): void {}

// ListSpecTest
public function testFailuresSurviveWithers(): void {}
```

Update `StubFilterElement` (`buildContext()` only) and drop `FilterData` from `ListSpecTest`/`ListSpecBuilderTest`.

- [ ] **Step 2: Run tests to verify they fail**

Run: `make test tests/Filter/FilterTest.php tests/Filter/FilterFactoryTest.php tests/Filter/FilterOptionsResolverTest.php tests/Filter/FilterTransformerResolverTest.php tests/List`
Expected: FAIL

- [ ] **Step 3: Implement the interfaces above**

- [ ] **Step 4: Run tests to verify they pass**

Run: same as Step 2. Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add -A src tests
git commit -m "feat!: Filter carries control, control config and value; list records filter failures"
```

---

### Task 7: Element base and the four choice elements

**Files:**
- Modify: `src/Filter/Element/{AbstractFilterElement,FieldValueChoiceFilterElement,DcaSelectFieldFilterElement,ArchiveFilterElement}.php`, `src/Integration/CodefogTags/FilterElement/CodefogTagsChoiceFilterElement.php`, `src/EventListener/DataContainer/FlareFilter/FieldsLoadAndSaveCallbacks.php` (delete `onLoadField_intrinsic()`/`onSaveField_intrinsic()`, the only `IntrinsicContract` users)
- Delete: `src/Contract/FilterElement/IntrinsicContract.php`
- Test: `tests/Filter/Element/{ArchiveFilterElementTest,FieldValueChoiceFilterElementTest,DcaSelectFieldFilterElementTest,ChoicePortCoverageTest}.php`

**Interfaces:**
- Consumes: `BuildsFilterContextsTrait` (Task 3), `ChoiceSourceContract` (Task 5), `ExpectsValueTrait` (Task 4).
- Produces:
  - `AbstractFilterElement` drops `buildForm()`, `isOnlyIntrinsic()`, `IntrinsicContract`; uses `ExpectsValueTrait`.
  - **`buildDca()` of these four elements** drops every control/value field and every `intrinsic` branch (§13.4): remaining palettes `{filter_legend},fieldGeneric` (FieldValueChoice, DcaSelectField), Archive's whitelist/ptable fields, nothing for CodefogTagsChoice; no call to a removed method (`getPreselectOptions()`, the old private `createChoices($table, $field)`).
  - All four: `#[AsFilterElement(…, value: ChoiceValue::class)]`, `implements ChoiceSourceContract`; no `buildForm()`; no `'intrinsic'`, `'preselect'`, `'multiple'`, `'expanded'`, `'is_*'`, `'label'`, `'placeholder'`, `'format_*'`, `'has_empty_option'` options (those are control config or `value` now); config read from `$builder->scope->filter->config`.
  - `FieldValueChoiceFilterElement`: options `field`; `createChoices()` → `ValueChoice((string) $id, $label)` (foreign key) / `ValueChoice($v, $v)` (local); `choiceFromKey($k)` → `new ValueChoice($k)`; `buildContext()` → `FieldValueChoicePredicate(field, values: [ValueChoice::$value …])`. **Remove the `ValidationContext` early return** — a fixed value must restrict validation (§4.2). Delete `normalizeRuntimeValue`, `buildPreselectData`, `extractSubmittedData`, `normalizePreselect`.
  - `DcaSelectFieldFilterElement`: options `field`; `createChoices()` → `ValueChoice((string) $key, (string) $label)` from `getOptions()`; `choiceFromKey()` → `ValueChoice`; `buildContext()` → `DcaSelectPredicate(field, selected: values, valid_options, is_multiple_dca_field)`. Delete `normalizeSubmittedValue`, `buildPreselectData`.
  - `CodefogTagsChoiceFilterElement`: no options; `createChoices()` → `ValueChoice((string) $tagId, $label)` (options from `getOptions()` with an execution context for `$scope->list`); `choiceFromKey()` → `ValueChoice` only for digit strings; `buildContext()` → `IntegerIdChoicePredicate(field: 'id', ids: int values)`.
  - `ArchiveFilterElement`: options `whitelist_parents`, `group_whitelist_parents`, `use_whitelist_for_options_only`; `createChoices()` = the choice part of today's `buildForm()` with `ContaoModelChoice::from($parent)` (keep its four `FilterException`s; dynamic mode keeps `setLabelForTable()` per group and `setModelSuffix('(%@name%)')`); `choiceFromKey()` parses `"<table>.<id>"` with a positive int id into `ContaoModelChoice::ref()`, else `null`. `buildContext()` (§3.6, §7.4) works on ids grouped `array<table, list<int>>`, allowed = the whitelist (static: main ptable ⇒ `whitelist_parents`; dynamic: `group_whitelist_parents`):

    | value | `use_whitelist_for_options_only` | result |
    |---|---|---|
    | none | false | the whole allowed set |
    | none | true | no condition (`return`) |
    | present | any | selected ∩ allowed; **empty ⇒ `abort()`** (stale or foreign keys never widen the list) |

    then `ArchivePredicate(field: 'pid', parent_ids)` (static) or `BelongsToRelationPredicate(field_pid: 'pid', field_dynamic_ptable: 'ptable', parent_groups, submitted_data: grouped)` (dynamic); no model loading on the query path. `getPtableInferrer()` caches on `\sha1(\serialize([$list->dc, $list->config]))`. Delete `processRuntimeValue`, `normalizeFilterValue`, `buildPreselectData`, `getPreselectOptions`, `getWhitelistedParents`.

- [ ] **Step 1: Write the failing tests**

```php
// FieldValueChoiceFilterElementTest (uses BuildsFilterContextsTrait)
public function testSelectedValuesBecomeTheFieldValueChoicePredicate(): void
{
    $context = $this->buildFilterContext(new FieldValueChoiceFilterElement(), ['field' => 'category'],
        new ChoiceValue([new ValueChoice('a'), new ValueChoice('b')]));
    $p = $context->formula->propositions[0];
    self::assertSame(FieldValueChoicePredicate::class, $p->predicateClass);
    self::assertSame(['a', 'b'], $p->options['values']);
}
public function testDuplicateLabelsResolveToDistinctValues(): void { /* ValueChoice('1','Same'), ValueChoice('2','Same') → values ['1','2'] */ }
public function testFixedValueAppliesInValidation(): void { /* engineContext new ValidationContext() → one proposition */ }
public function testNoValueAddsNothing(): void {}
public function testChoiceFromKeyKeepsDotsInTheValue(): void { self::assertSame('1.5', $el->choiceFromKey('1.5')->value); }

// ArchiveFilterElementTest (keep transformer test, drop intrinsic/preselect keys)
public function testStaleSelectionAbortsInsteadOfWidening(): void { /* value ref to an id outside the whitelist ⇒ AbortFilteringException */ }

// ChoicePortCoverageTest (§3.5): every element class under src/Filter/Element and src/Integration/*/FilterElement
// whose #[AsFilterElement(value:)] is ChoiceValue::class implements ChoiceSourceContract
public function testEveryChoiceElementImplementsTheChoicePort(): void {}

public function testChoiceFromKeyParsesTableAndId(): void
{
    $ref = $el->choiceFromKey('tl_news_archive.5');
    self::assertSame(['tl_news_archive', 5], [$ref->table, $ref->id]);
    self::assertNull($el->choiceFromKey('5'));
    self::assertNull($el->choiceFromKey('tl_x.abc'));
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `make test tests/Filter/Element`
Expected: FAIL

- [ ] **Step 3: Implement the interfaces above**

- [ ] **Step 4: Run tests to verify they pass**

Run: `make test tests/Filter/Element`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add -A src tests
git commit -m "feat!: choice elements implement the choice port and work on choice objects"
```

---

### Task 8: The remaining elements and programmatic call sites

**Files:**
- Modify: `src/Filter/Element/{BooleanFilterElement,SearchKeywordsFilterElement,DateRangeFilterElement,CalendarCurrentFilterElement,PublishedFilterElement,BelongsToRelationFilterElement,SimpleEquationFilterElement}.php`, `src/Integration/CodefogTags/FilterElement/CodefogTagsSearchElement.php`, `src/Engine/Loader/ValidationLoader.php`, `src/Engine/Mod/SimpleEquationMod.php`, `src/List/Driver/NewsListDriver.php`, `src/Integration/ContaoCalendar/ListDriver/EventsListDriver.php`, `src/Integration/Terminal42Languages/EventListener/ChangelanguageListener.php`
- Test: `tests/Filter/Element/{BooleanFilterElementTest,SearchKeywordsFilterElementTest,DateRangeFilterElementTest,CalendarCurrentFilterElementTest,SimpleEquationFilterElementTest}.php`

**Interfaces:**
- Produces (all: no `buildForm()`, no `'intrinsic'` option, no `isOnlyIntrinsic()`, `addPredicate()` instead of `add()`):
  - `BooleanFilterElement`: `value: BoolValue::class`; options `field`; no field ⇒ `abort()`; no value ⇒ nothing; else `BooleanPredicate(field, value: $v->state)`. Delete `normalizeValue`, `resolveRuntimeValue`, the `boolMode` error; `buildDca()` palette `{filter_legend},fieldGeneric`.
  - `SearchKeywordsFilterElement`: `value: KeywordsValue::class`; options `columns`; no value or no columns ⇒ nothing; else `SearchKeywordsPredicate(value: $v->keywords, columns)`.
  - `DateRangeFilterElement`: `value: DateRangeValue::class`; options `field` (no field ⇒ `FilterException`); no value ⇒ nothing; else `DateRangePredicate(field, from, to)`. Delete `validateRange`, `from_enabled`/`to_enabled`.
  - `CalendarCurrentFilterElement`: `value: DateRangeValue::class`, `implements DateBoundsContract`; options `is_limited`, `configure_start`, `configure_stop`, `start_at`, `stop_at`, `has_extended_events`; `getDateBounds()` = today's `resolveFormLimits()` when `is_limited`, else `[null, null]`; `buildContext()` as today but reading `$v->from`/`$v->to`. Delete `buildForm`, `validateRange`, the positional `processRuntimeValue` fallback.
  - `PublishedFilterElement`, `BelongsToRelationFilterElement`, `SimpleEquationFilterElement`, `CodefogTagsSearchElement`: `value` stays `null`; bodies read `$builder->scope->filter->config` / `$builder->scope->list`.
  - **`buildDca()` of these elements** drops every control/value field (`preselect`, `prefill`, `label`, `placeholder`, `boolMode`, …) and every `intrinsic` branch (§13.4); `CalendarCurrent` keeps `isLimited` as element config.
  - Remove `'intrinsic' => true` from the programmatic configs in `ValidationLoader` (×2), `SimpleEquationMod`, `NewsListDriver`, `EventsListDriver`, `ChangelanguageListener` (×2) — the option no longer exists and would be rejected.

- [ ] **Step 1: Write the failing tests** (via `BuildsFilterContextsTrait`)

```php
public function testBooleanWithoutValueAddsNothingAndWithValueMatchesIt(): void
{
    self::assertSame([], $this->buildFilterContext($bool, ['field' => 'featured'])->formula->propositions);
    $p = $this->buildFilterContext($bool, ['field' => 'featured'], new BoolValue(false))->formula->propositions[0];
    self::assertSame([BooleanPredicate::class, false], [$p->predicateClass, $p->options['value']]);
}
public function testKeywordsNeedColumns(): void {}
public function testDateRangeRequiresAField(): void { $this->expectException(FilterException::class); }
public function testCalendarBoundsOnlyWhenLimited(): void { self::assertSame([null, null], $el->getDateBounds($scope)); }
public function testWrongValueClassIsRejected(): void { $this->expectException(FilterException::class); /* BoolValue into DateRange */ }
```

Update `SimpleEquationFilterElementTest` (no `intrinsic`).

- [ ] **Step 2: Run tests to verify they fail**

Run: `make test tests/Filter/Element`
Expected: FAIL

- [ ] **Step 3: Implement the interfaces above**

- [ ] **Step 4: Run tests to verify they pass**

Run: `make test tests/Filter/Element`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add -A src tests
git commit -m "feat!: remaining elements build contexts from typed values"
```

---

### Task 9: The five filter controls

**Files:**
- Create: `src/Filter/Control/{ChoiceFilterControl,CheckboxFilterControl,ChoiceBoolFilterControl,DateRangeFilterControl,KeywordsFilterControl}.php`
- Test: `tests/Filter/Control/{ChoiceFilterControlTest,CheckboxFilterControlTest,ChoiceBoolFilterControlTest,DateRangeFilterControlTest,KeywordsFilterControlTest}.php`

**Interfaces:**
- Consumes: `AbstractFilterControl`, `#[AsFilterControl]` (Task 4); ports (Task 5); `ChoicesBuilder` (Task 2).
- Produces (each `#[AsFilterControl(name: …)]`, `extends AbstractFilterControl`, narrowed `decode()` return; transformers read today's column names, renamed in Task 13; `buildDca()` sets only the control's palette segment `{control_legend},…`):

  | control | name | options (control config) | `buildForm()` | `decode()` / `hydrate()` |
  |---|---|---|---|---|
  | `ChoiceFilterControl` | `flare_choice` | `label`, `format_label`, `is_mandatory`, `is_multiple`, `is_expanded`, `has_empty_option`, `format_empty_option`, `placeholder` (from `label`, `formatLabel(+Custom)`, `isMandatory`, `isMultiple`, `isExpanded`, `hasEmptyOption`, `formatEmptyOption(+Custom)`, `placeholder`) | element must implement `ChoiceSourceContract` (else `FilterException`); `$choices = $element->createChoices($scope)`; `setLabel(format_label)`; empty option when `has_empty_option` (value `EMPTY_CHOICE_VALUE_ALTERNATIVE` if expanded+multiple); `single(ChoiceType::class, [label ?: false, required, multiple, expanded])`; `attr.placeholder` only when no empty option is rendered; `applyFormOptions()`; no `flare.choices_builder` attribute | `?ChoiceValue` via `ChoiceValue::tryFrom($data)` / multiple ⇒ the value's choices (or `[]`), single ⇒ the first choice (or `null`) |
  | `CheckboxFilterControl` | `flare_checkbox` | `label`, `binary_choices` (`BoolBinaryChoices`, default `NULL_TRUE`; stored `null_false` reads as `TRUE_FALSE`) | `single(CheckboxType::class, [label, required: false])` | `?BoolValue`: `true` ⇒ true; `false` ⇒ `TRUE_FALSE` ? false : null; `null` ⇒ null / `true`⇒true, `false`⇒false, null⇒null |
  | `ChoiceBoolFilterControl` | `flare_choice_bool` | `label` | `single(ChoiceType::class)` with choices `'1'`/`'0'` and an "any" placeholder option | `'1'`/`'0'` ⇒ `BoolValue`, else null / inverse |
  | `DateRangeFilterControl` | `flare_date_range` | `label` | `add('from'|'to', DateType::class, single_text, html5, required false)`; `min`/`max` attrs from `DateBoundsContract` if the element implements it (format as today's `CalendarCurrent::buildForm`); `POST_SUBMIT` listener with today's `validateRange()` body | `?DateRangeValue` via `tryFrom($data['from'] ?? null, $data['to'] ?? null)` / `['from' => $v?->from, 'to' => $v?->to]` |
  | `KeywordsFilterControl` | `flare_keywords` | `label`, `placeholder` | `single(TextType::class)`, `attr.placeholder` | `?KeywordsValue` via `tryFrom()` / `$v?->keywords` |

- [ ] **Step 1: Write the failing tests**

```php
// CheckboxFilterControlTest
public function testUntouchedCheckboxContributesNothingEvenUnderTrueFalse(): void
{
    self::assertNull($ctl->decode(null, $this->scope(['binary_choices' => BoolBinaryChoices::TRUE_FALSE])));
}
public function testUncheckedMeansFalseOnlyUnderTrueFalse(): void
{
    self::assertFalse($ctl->decode(false, $this->scope(['binary_choices' => BoolBinaryChoices::TRUE_FALSE]))->state);
    self::assertNull($ctl->decode(false, $this->scope(['binary_choices' => BoolBinaryChoices::NULL_TRUE])));
}
public function testStoredNullFalseReadsAsTrueFalse(): void { /* transformer: model boolBinaryChoices 'null_false' → TRUE_FALSE */ }

// ChoiceFilterControlTest (Forms::createFormFactoryBuilder() + FilterFormBuilder, as in today's FilterSetFactoryTest)
public function testHydratedCanonicalReferenceMatchesShortSubmittedKey(): void
{
    // element createChoices: one ContaoModelChoice::ref('tl_news_archive', 5) ⇒ choice_value '5'
    // hydrate(new ChoiceValue([ContaoModelChoice::ref('tl_news_archive', 5)])) as data, build form,
    // assert $form->createView()->vars['value'] === '5'
}
public function testElementWithoutChoiceSourceFails(): void { $this->expectException(FilterException::class); }
public function testPlaceholderAttributeOnlyWithoutEmptyOption(): void {}
public function testSingleSelectHydratesTheFirstChoice(): void {}

// DateRangeFilterControlTest: bounds applied only with DateBoundsContract; decode/hydrate round trip
// KeywordsFilterControlTest, ChoiceBoolFilterControlTest: decode/hydrate tables
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `make test tests/Filter/Control`
Expected: FAIL

- [ ] **Step 3: Implement the controls per the table**

- [ ] **Step 4: Run tests to verify they pass**

Run: `make test tests/Filter/Control`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add -A src/Filter/Control tests/Filter/Control
git commit -m "feat: choice, checkbox, choice-bool, date-range and keywords controls"
```

---

### Task 10: FormHarness on controls

**Files:**
- Modify: `src/Form/Factory/FormHarnessFactory.php`, `src/Form/{FormHarness,FilterMount}.php`
- Delete/move: `tests/Filter/FilterSetFactoryTest.php` → `tests/Form/FormHarnessFactoryTest.php`; `tests/EventListener/NamedDispatch/FilterSetListenerTest.php` → `FormHarnessListenerTest.php`
- Test: `tests/Form/{FormHarnessFactoryTest,FormHarnessTest}.php`

**Interfaces:**
- Consumes: `FilterControlInterface`, `FilterControlBuiltEvent` (Task 4); `Filter::$control`, `$value` (Task 6).
- Produces:
  - `FormHarnessFactory::__construct(EventDispatcherInterface $eventDispatcher, FormFactoryInterface $formFactory, LoggerInterface $logger)`; `create(ListSpec $list, FormContextInterface $context): FormHarness` — per filter with a control and a valid alias: `$scope = new FilterScope($list, $filter, $context)`; collector builder carries `FilterScope::ATTR_SELF`; `$filter->control->buildForm($builder, $scope)`; dispatch `FilterControlBuiltEvent`; mount as today; the mount's `data` option = `$filter->control->hydrate($filter->value, $scope)` when not `null`; `$mounts[$alias] = new FilterMount($filter, $alias, $scope)`. A `FilterException` from `buildForm()` or `hydrate()` ⇒ warning logged, filter not mounted (fail open, §8.1). No `FilterContextFactory`.
  - `final readonly class FilterMount { __construct(public Filter $filter, public string $alias, public FilterScope $scope) }`
  - `FormHarness::decode(): array<string, ?ValueInterface>` — per mount: `$data = $child->getData()`; `$value = $filter->control->decode($data, $scope)`; submitted root form ⇒ an entry for every mounted alias (null included); unsubmitted ⇒ non-null entries only. Docblocks say "keyed by alias".

- [ ] **Step 1: Write the failing tests**

Port the mounting tests of `FilterSetFactoryTest` (single/compound mount, invalid alias, cancelled event, no fields, `getForm()`, mount map keyed by alias, lazy `getChild()`), swapping elements for stub controls. New:

```php
public function testUnsubmittedFormDecodesToTheHydratedInitialValue(): void
{
    // filter with a keywords stub control and value KeywordsValue('foo'); handle no request
    self::assertSame('foo', $harness->decode()['q']->keywords);
}
public function testSubmittedEmptyValueIsKeptAsNull(): void
{
    // submit ['list' => ['q' => '']] ⇒ array_key_exists('q', $values) && $values['q'] === null
}
public function testFiltersWithoutAControlAreNotMounted(): void {}
public function testControlFailingToBuildIsSkipped(): void { /* control throws FilterException in buildForm ⇒ no mount, warning logged, other filters mounted */ }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `make test tests/Form tests/EventListener`
Expected: FAIL

- [ ] **Step 3: Implement the interfaces above**

- [ ] **Step 4: Run tests to verify they pass**

Run: `make test tests/Form tests/EventListener`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add -A src/Form tests/Form tests/Filter tests/EventListener
git commit -m "feat!: FormHarness mounts controls, hydrates initial values and decodes"
```

---

### Task 11: Value path, failure state, event and FilterData removal — tree green

**Files:**
- Modify: `src/Engine/Projector/{AbstractProjector,InteractiveProjector}.php`, `src/Integration/ContaoCalendar/Projector/EventsInteractiveProjector.php`, `src/Engine/View/InteractiveView.php` (and `InteractiveEventsView` if it has its own ctor), `contao/templates/content_element/flare_listview/*.html.twig`, `src/Query/Executor/ListQueryDirector.php`, `src/Query/ListQueryConfig.php`, `src/Engine/Loader/{InteractiveLoaderConfig,AggregationLoaderConfig}.php`, `src/Engine/Context/{AggregationContext,ValidationContext}.php`, `src/Filter/FilterFormBuilderInterface.php` (docblock), `contao/templates/content_element/flare_listview.html.twig`, `translations/flare.en.yaml`, `translations/flare.de.yaml`
- Delete: `src/Filter/FilterData.php`, `src/Event/{FilterElementBuildingEvent,FilterElementBuiltEvent}.php`, `src/EventListener/NamedDispatch/FilterElementListener.php`, `tests/Filter/FilterDataTest.php`, `tests/Query/Executor/FilterExecutorTest.php`
- Test: `tests/Query/Executor/ListQueryDirectorTest.php`, `tests/Engine/Projector/InteractiveProjectorTest.php`

**Interfaces:**
- Consumes: `FormHarness::decode()` (Task 10), `FilterContextFactory::create(FilterScope, ?ValueInterface)` (Task 3).
- Produces:
  - Filter value maps everywhere are `array<string, ?ValueInterface>` keyed by alias (docblocks only).
  - `ListQueryDirector::createQueryBuilder()` — a `ListSpec` with `failures` ⇒ `FilterException` naming them, before any filter runs (fail closed for every context: the interactive projector turns it into the failed view, the reader into a 500).
  - `ListQueryDirector::invokeFilters()` — per filter: `$value = $filter->control !== null ? ($values[$filter->alias] ?? null) : $filter->value` (§8 step 4); `FilterFactory`-built scope `new FilterScope($list, $filter, $options->context)`; a `FilterException` from `create()` or a predicate: control ⇒ log warning and skip the filter (fail open); no control ⇒ rethrow wrapped via `CreatesFilterExceptionTrait` (fail closed). `AbortFilteringException` keeps returning `null`.
  - `AbstractProjector::getSubscribedServices()` adds `LoggerInterface` and the `kernel.debug` parameter (via `ParameterBagInterface`); accessors `getLogger()`, `isDebug()`.
  - `InteractiveProjector`: `createFormHarness()` (renamed from `createFilterSet()`), `project()` uses `$harness->decode()`; `collectFilterData()` deleted; a `FilterException` while computing the aggregation count ⇒ error logged; in debug mode rethrown (§8.1); otherwise `createView(…, failed: true)` with `InteractiveEmptyLoader`, `paginatorFactory->createEmpty()` and `totalItems: 0`.
  - `createView(…, bool $failed = false)` gains the flag in `InteractiveProjector` **and** its override in `EventsInteractiveProjector` (which returns `InteractiveEventsView`); `InteractiveView` ctor param `bool $failed = false`, `isFailed(): bool` (Twig: `flare_list.failed`).
  - Templates: inside the `list` block `{% if flare_list.failed %}<p class="flare-list__failed">{{ 'list.failed'|trans({}, 'flare') }}</p>{% else %}…{% endif %}`, and the `pagination` block renders nothing when failed — so the partials `list_only`/`pagination_only` (which extend the main template) inherit it. Keys `list.failed`: en `The list could not be created.`, de `Die Liste konnte nicht erstellt werden.`
  - Reader: no code change — a `FlareException` in `ReaderController` already becomes `InternalServerErrorHttpException` (500). Add a test or a note asserting it stays.

- [ ] **Step 1: Write the failing tests**

```php
// ListQueryDirectorTest (spy element records the value it receives)
public function testInitialValueIsIgnoredWithoutAFormValueMap(): void
{
    // filter with control + value BoolValue(true), ListQueryConfig(context: new ValidationContext(), filterValues: [])
    self::assertNull($spy->received);
}
public function testFormValueMapWinsForControlledFilters(): void {}
public function testFixedValueAppliesInValidation(): void { /* no control, value set ⇒ received */ }
public function testFailingControlledFilterIsSkipped(): void { /* element throws FilterException; control set ⇒ no throw, warning logged */ }
public function testFailingFixedFilterFailsTheList(): void { $this->expectException(FilterException::class); }

// InteractiveProjectorTest
public function testProjectorReturnsFailedViewWhenAFixedFilterFails(): void { self::assertTrue($view->isFailed()); self::assertSame(0, $view->getCount()); }
public function testListFailuresRecordedAtBuildTimeYieldTheFailedView(): void { /* ListSpec(failures: ['…']) ⇒ isFailed() */ }
public function testFailuresAreRethrownInDebugMode(): void { $this->expectException(FilterException::class); }
public function testValuesComeFromTheHarness(): void {}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `make test tests/Query tests/Engine`
Expected: FAIL

- [ ] **Step 3: Implement the interfaces above; delete the listed files**

- [ ] **Step 4: Run the whole suite and static analysis**

Run: `make test` — Expected: all PASS
Run: `make phpstan` — Expected: `[OK] No errors`
Run: `grep -rnw "FilterData\|FilterFormInterface\|AsFilterForm\|FilterElementBuildingEvent\|IntrinsicContract\|ParentRefValue" src tests` — Expected: no output (`-w`, so `FilterModel::getFilterData()` does not match)

- [ ] **Step 5: Commit**

```bash
git add -A src tests contao/templates translations
git commit -m "feat!: role-based value path, failed list state; drop FilterData and element events"
```

---

### Task 12: DCA composition and the backend value field

**Files:**
- Modify: `src/DataContainer/Builder/{DcaBuilder,DcaBuilderInterface}.php`, `src/Util/Str.php`, `src/EventListener/Contao/ElementDcaListener.php`, `src/EventListener/DataContainer/FlareFilter/{FieldsOptionsCallbacks,FieldsLoadAndSaveCallbacks}.php`, `contao/dca/tl_flare_filter.php`, `contao/languages/{en,de}/tl_flare_filter.php`, every element's `buildDca()`
- Test: `tests/DataContainer/Builder/DcaBuilderTest.php`, `tests/Util/StrTest.php`

**Interfaces:**
- Consumes: controls' `buildDca()` (Task 9), `ValueProviderInterface::buildDcaField()` (Task 5), `FilterControlRegistry::findNames()` (Task 4).
- Produces:
  - `DcaBuilderInterface::scope(string $segment): DcaBuilderInterface` — `'control'` returns a sub-builder whose `palette()` fills the control segment; `field()` is shared with the parent.
  - `DcaBuilderInterface::selector(string $field, string $palette, ?string $value = null): self` — adds `$field` to `__selector__` and sets `subpalettes[$value === null ? $field : "{$field}_{$value}"]` (named `selector()` as in spec §6.3).
  - `apply()`: `palettes[$type] = Str::mergePalettes($prefix, $elementPalette, $controlPalette, $suffix)`.
  - `Str::mergePalettes(?string ...$palettes): string` — drops `null`/empty segments **before** trimming.
  - `ElementDcaListener::configure()`: after the element's `buildDca()`, if the edited row's `formVariant` names a registered control implementing `DcaContract` ⇒ `$control->buildDca($dca->scope('control'), $context)`; if the element declares a value class ⇒ `ValueProviderRegistry::get($class)->buildDcaField($dca->field('value'), $context)` and the prefix gains `,value` (via `DcaBuilder::prefix()` callable).
  - DCA: new field `value` (`exclude`, `sql` `['type' => 'blob', 'notnull' => false]`); `formVariant` gets `submitOnChange` and `options_callback` `FieldsOptionsCallbacks::getFieldOptions_formVariant(DataContainer $dc): array` (names from `findNames($elementValueClass)`, labels from `$GLOBALS['TL_LANG']['tl_flare_filter']['formVariant_options'][$name] ?? $name`); `__prefix__` = `{title_legend},title,type,formVariant`.
  - `__prefix__` loses `intrinsic`, so `AddTargetAliasFieldCallback` re-anchors `targetAlias` on `formVariant` here (Task 13 renames it to `control`).
  - Language keys: `value`, `formVariant_options` (`flare_choice`, `flare_checkbox`, `flare_choice_bool`, `flare_date_range`, `flare_keywords`), `control_legend` in en/de.

- [ ] **Step 1: Write the failing tests**

```php
// DcaBuilderTest ($GLOBALS['TL_DCA']['tl_test'] set up by hand)
public function testSegmentsMergeInPrefixElementControlSuffixOrder(): void
{
    $GLOBALS['TL_DCA']['tl_test']['palettes'] = ['__prefix__' => '{a},p', '__suffix__' => '{z},s'];
    $dca = new DcaBuilder();
    $dca->palette('{e},el');
    $dca->scope('control')->palette('{control_legend},ctl');
    $dca->apply('tl_test', 'x');
    self::assertSame('{a},p;{e},el;{control_legend},ctl;{z},s', $GLOBALS['TL_DCA']['tl_test']['palettes']['x']);
}
public function testMissingSegmentsAreDropped(): void {}
public function testSelectorRegistersFieldAndSubpaletteKey(): void
{
    // selector('formatLabel', 'formatLabelCustom', 'custom') ⇒ __selector__ contains it; subpalettes['formatLabel_custom']
}

// StrTest
public function testMergePalettesAcceptsNullSegments(): void { self::assertSame('{a},b', Str::mergePalettes(null, '{a},b', null)); }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `make test tests/DataContainer tests/Util`
Expected: FAIL

- [ ] **Step 3: Implement the interfaces above**

- [ ] **Step 4: Run the whole suite and static analysis**

Run: `make test && make phpstan`
Expected: PASS / `[OK] No errors`

- [ ] **Step 5: Commit**

```bash
git add -A src contao tests
git commit -m "feat!: control palette segment, subpalettes and the backend value field"
```

---

### Task 13: Column renames and the rename migration

**Files:**
- Create: `src/Migration/RenameFilterColumnsMigration.php`, `tests/Migration/RenameFilterColumnsMigrationTest.php`
- Modify: `contao/dca/tl_flare_filter.php`, `contao/languages/{en,de}/tl_flare_filter.php`, `src/Model/{FilterModel,DocumentsFilterModelTrait}.php`, all element/control transformers and `buildDca()`s, `src/EventListener/DataContainer/FlareFilter/*`, `src/EventListener/Contao/ElementDcaListener.php`, `src/Filter/Factory/FilterFactory.php`, `src/List/Collector/ListModelFilterCollector.php`, Codefog integration DCA/elements, `templates/backend/filter_child_record/*`

**Interfaces:**
- Produces:
  - Column map (§4.1), frozen as a constant in the migration: `elm_` for `fieldGeneric`, `columnsGeneric`, `usePublished`, `fieldPublished`, `invertPublished`, `useStart`, `fieldStart`, `useStop`, `fieldStop`, `whichPtable`, `fieldPid`, `fieldPtable`, `tablePtable`, `whitelistParents`, `groupWhitelistParents`, `useWhitelistForOptionsOnly`, `equationLeft`, `equationOperator`, `equationRight`, `configureStart`, `startAt`, `configureStop`, `stopAt`, `hasExtendedEvents`, `isLimited`; `ctl_` for `label`, `placeholder`, `isMandatory`, `isMultiple`, `isExpanded`, `hasEmptyOption`, `formatLabel`, `formatLabelCustom`, `formatEmptyOption`, `formatEmptyOptionCustom`, `boolBinaryChoices`; `formAlias` → `alias`. `formVariant` → `control` is **not** migrated (v0.2-only and empty; the schema diff drops/adds it). (All columns, including those the Codefog elements use, live in `contao/dca/tl_flare_filter.php`.)
  - `RenameFilterColumnsMigration extends AbstractMigration` (pattern: `RenameContentListColumnMigration`): `shouldRun()` = table exists and at least one old column exists whose new column does not; `run()` = per pending pair `ALTER TABLE tl_flare_filter CHANGE {old} {new} {Type} {NULL|NOT NULL} {DEFAULT …}` composed from the old column's `SHOW COLUMNS` row.
  - `elm_groupWhitelistParents` keeps its inner blob keys (`tablePtable`, `whitelistParents`, `formatLabel`, `formatLabelCustom`); the group widget defines its sub-fields itself instead of referencing the renamed columns (§4.1).
  - `ElementDcaListener::checkDuplicateFilterAliases()` queries `alias`.
  - `FilterModel::getFilterAlias(): string` replaces `getFilterFormName()`; `generateFormName()` reads `alias`. `FilterFactory`/collector read `$model->control`. `AddTargetAliasFieldCallback` anchors on `control`. `ListCallbacks`: `is_intrinsic` = `($row['control'] ?? '') === ''`. All `#[AsCallback]` targets use the new field names; `__selector__`/subpalette keys use the new names (`elm_usePublished`, `elm_whichPtable_*`, `ctl_formatLabel_custom`, …).

- [ ] **Step 1: Write the failing test** (mocked `Doctrine\DBAL\Connection`)

```php
public function testShouldRunOnlyWhileOldColumnsRemain(): void {}
public function testRunChangesColumnsKeepingTheirDefinition(): void
{
    // SHOW COLUMNS row for fieldGeneric: Type varchar(255), Null NO, Default ''
    // expect executeStatement("ALTER TABLE tl_flare_filter CHANGE fieldGeneric elm_fieldGeneric varchar(255) NOT NULL DEFAULT ''")
}
public function testFormVariantIsNotRenamed(): void {}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `make test tests/Migration`
Expected: FAIL

- [ ] **Step 3: Implement the migration and rename every reader in the same commit**

- [ ] **Step 4: Verify**

Run: `make test && make phpstan` — Expected: PASS / `[OK] No errors`
Run: `grep -rnE "\->(fieldGeneric|columnsGeneric|isMultiple|formatLabel|formAlias|formVariant)\b|'(formAlias|formVariant)'|\`formAlias\`" src contao templates --exclude-dir=Migration` — Expected: no output (`ElementDcaListener::checkDuplicateFilterAliases()` uses the backticked column in SQL)

- [ ] **Step 5: Commit**

```bash
git add -A src contao templates tests
git commit -m "feat!: elm_/ctl_ column prefixes, control and alias columns, rename migration"
```

---

### Task 14: Data migration and dropping the legacy columns

**Files:**
- Create: `src/Migration/BackfillFilterValuesMigration.php`, `tests/Migration/BackfillFilterValuesMigrationTest.php`
- Modify: `contao/dca/tl_flare_filter.php` (drop `intrinsic`, `boolMode`, `preselect`, `prefill`, the `boolMode` selector and `boolMode_binary` subpalette), `src/Enum/BoolBinaryChoices.php` (drop `NULL_FALSE`), `src/Model/{FilterModel,DocumentsFilterModelTrait}.php` (drop `isFilterIntrinsic()`, the `intrinsic` arm of `__get()`, the properties), `src/Filter/Control/CheckboxFilterControl.php` (drop the `null_false` read), language files, `translations/flare.{en,de}.yaml` (`filter.info.intrinsic.*` stay)
- Delete: `src/Enum/BoolMode.php`

**Interfaces:**
- Produces: `BackfillFilterValuesMigration extends AbstractMigration` per §11 B with **frozen string literals** (no class constants, no registry):
  - `shouldRun()`: table exists, `intrinsic` present, the renamed `ctl_*` columns present (so it waits for the rename migration — `contao:migrate` re-runs pending migrations until none remain), and at least one row matches the **pending predicate**: type in the mapped set AND ( (`intrinsic = 0` AND `control = ''`) OR (copy source non-empty per the `value` rules below AND `value` empty) OR `ctl_boolBinaryChoices = 'null_false'` ); when `control`/`value` do not exist yet, every mapped row counts as pending. `false` when `intrinsic` is absent.
  - `run()` first adds `control` (`varchar(128) NOT NULL DEFAULT ''`) and `value` (`blob NULL`) with `ALTER TABLE … ADD` when absent — on an upgrading install they would otherwise only arrive with the schema diff, which runs after migrations and also drops the sources.
  - `run()`, per row:
    - `control`: `intrinsic = 1` ⇒ `''`; else `flare_archive|flare_fieldValueChoice|flare_dcaSelectField|cfg_tags_choice` ⇒ `flare_choice`; `flare_dateRange|flare_calendar_current` ⇒ `flare_date_range`; `flare_search_keywords` ⇒ `flare_keywords`; `flare_bool` ⇒ `boolMode = 'ternary'` ? `flare_choice_bool` : `flare_checkbox`; others ⇒ `''`.
    - `value`: `preselect` for `flare_bool`, `flare_fieldValueChoice`, `flare_dcaSelectField`, `cfg_tags_choice`; for `flare_archive` only when `intrinsic = 0`; `prefill` for `flare_search_keywords` only when `intrinsic = 1`. Bool `true|false|null` ⇒ `'1'|'0'|''`; choices ⇒ `serialize(list of keys)` whether stored single or multiple.
    - `ctl_boolBinaryChoices`: `null_false` ⇒ `true_false`.
    - Empty option / placeholder: `flare_fieldValueChoice` single-select ⇒ `ctl_hasEmptyOption = 1`; `flare_dcaSelectField|cfg_tags_choice` single-select and not expanded ⇒ `ctl_hasEmptyOption = 1`, `ctl_formatEmptyOption = 'custom'`, `ctl_formatEmptyOptionCustom = ctl_placeholder`, `ctl_placeholder = ''`; otherwise unchanged.

- [ ] **Step 1: Write the failing tests** (mocked `Connection`, rows in, `update` calls out)

```php
public function testIntrinsicArchiveIgnoresLeftoverPreselect(): void { /* intrinsic=1, preselect set ⇒ control '' and value '' */ }
public function testControlledArchiveKeepsPreselectAsInitialValue(): void { /* value = serialize(['tl_news_archive.5']) */ }
public function testKeywordsPrefillOnlyWhenIntrinsic(): void {}
public function testTernaryBoolBecomesChoiceBoolAndNullFalseBecomesTrueFalse(): void {}
public function testSingleDcaSelectPlaceholderBecomesEmptyOptionLabel(): void {}
public function testShouldRunIsFalseOnceIntrinsicIsGone(): void {}
public function testShouldRunWaitsForTheRenamedColumns(): void {}
public function testShouldRunGoesQuietAfterARun(): void { /* rows after run() match no pending predicate, intrinsic-only databases included */ }
public function testRunAddsMissingTargetColumnsFirst(): void {}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `make test tests/Migration`
Expected: FAIL

- [ ] **Step 3: Implement the migration and the drops**

- [ ] **Step 4: Verify**

Run: `make test && make phpstan && make semgrep-sec` — Expected: PASS / `[OK] No errors` / no findings
Run: `grep -rnE "intrinsic|boolMode|preselect|prefill|NULL_FALSE" src contao` — Expected: only the migration, `translations` keys and `ListCallbacks`' `is_intrinsic` flag.

- [ ] **Step 5: Commit**

```bash
git add -A src contao translations tests
git commit -m "feat!: backfill control and value, drop intrinsic, boolMode, preselect and prefill"
```

---

### Task 15: Documentation and end-to-end verification

**Files:**
- Modify (repo): `AGENTS.md` (architecture: `FilterControl`, `FilterScope`/`FilterContext`, value providers, ports, `src/Filter/Control/`, registries incl. `FilterControlRegistry`/`ValueProviderRegistry`, events list without the element events, `flare.filter_control.{type}.built`)
- Modify (worktree `docs/`, branch `docs/main`): `docs/docs/dev/events.md`, `docs/docs/spec/filtering.md`, `docs/docs/dev/filter-elements/index.md`, `docs/docs/migrating-from-v0.1.md`, `docs/docs/removed-in-v0.2.md`; new `docs/docs/dev/filter-controls.md`

- [ ] **Step 1: Update `AGENTS.md`; commit on `v0.2-refactor-filter-api`**

- [ ] **Step 2: Update the docs worktree.** `migrating-from-v0.1.md`/`removed-in-v0.2.md` must list: `FilterElementInvokingEvent`/`InvokedEvent` have no successor (use `ListBuildEvent`/`flare.list.{type}.build`, programmatic `Filter::$value`, or a custom element); column renames (`elm_`/`ctl_`, `formAlias` → `alias`) and `contao:migrate`; `NULL_FALSE` removed; bool filters with a control no longer restrict reader pages; `flare.choices_builder` form attribute removed; `ChoicesBuilder::add()` takes choice objects and the removed `@api` methods; failed lists render `list.failed` instead of an empty result. Commit inside `docs/` (`git -C docs commit`).

- [ ] **Step 3: Manual verification on a Contao install** (not possible in this repo — no `bin/console`; report as pending if unavailable): `contao:migrate --dry-run`, then migrate; a `flare_bool` filter offers both bool controls and swaps the palette on change; the blank option hides control fields and shows `value` as fixed value; an Archive filter in static and dynamic ptable mode filters by submitted values and by an initial value; old `?archive=5` links still work; a FieldValueChoice filter with duplicate labels distinguishes them; a reader page of an entry outside the initial display but inside the whole list renders; a fixed filter with a broken config renders the "could not be created" message with HTTP 200, and its reader answers 500.

- [ ] **Step 4: Final gate**

Run: `make test && make phpstan && make semgrep-sec`
Expected: all green.
