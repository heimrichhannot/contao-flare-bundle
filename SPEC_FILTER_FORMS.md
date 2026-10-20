# SPEC: Decoupling Filter Forms from Filter Elements

**Status:** Design settled (2026-10-20, §13.6-13.17). Step 0 (§12) implemented and partly
re-renamed by #48; step 1 partially implemented and red mid-cut; steps 2-6 not started. Progress
and the open checklist live in `PLAN_FILTER_FORMS.md`.
**Scope:** `src/Filter/`, `src/Form/`, `src/Query/`, `src/Event/`, `src/EventListener/NamedDispatch/`,
`src/Registry/`, `src/DependencyInjection/`, `src/DataContainer/Builder/`, `src/Util/`,
`contao/dca/tl_flare_filter.php`, `src/EventListener/Contao/ElementDcaListener.php`,
`src/Engine/Projector/InteractiveProjector.php`
**Breaking:** yes, deliberately — v0.2 is unreleased and the goal is a stable architecture, not
compatibility. `FilterElementInterface`, `Filter::$data`, `ChoicesBuilder::add()`, the
`tl_flare_filter` schema, the renames of §2.2 (service ids, event classes, the dispatch alias
`flare.filter_element.{type}.form_built`), the predicate layer of §2.3 replacing filter types,
`FilterBuilder` and `FilterExecutor`, and the removal of `FilterElementBuildingEvent` /
`FilterElementBuiltEvent` (§13.16).

**Reading note.** §1 describes the code *before* the refactor and uses the names of that time.
Everything from §2 on uses the target names.

---

## 1. Motivation

Today a filter element owns both *what it matches* and *how it is presented*:
`FilterElementInterface::buildForm()` and `::buildFilter()` live on the same class, and the
"no form" case is expressed by an `intrinsic` boolean checked at the top of nine `buildForm()`
implementations.

Three concrete problems follow.

**1.1 Presentation variants become config columns.** `BooleanFilterElement` carries `boolMode` and
`boolBinaryChoices` — two DB columns, a `__selector__` entry and a subpalette
(`contao/dca/tl_flare_filter.php`) — whose only job is to pick a rendering. Adding a toggle or a
segmented control means more enum columns. This grows combinatorially.

**1.2 `buildForm()` and `buildFilter()` share an undeclared schema.** The coupling is real, not
incidental:

- `FieldValueChoiceFilterElement::buildForm()` builds a `ChoicesBuilder`; `buildFilter()` **rebuilds
  it** in `normalizeRuntimeValue()` to map submitted choices back to scalars.
- `DateRangeFilterElement::buildForm()` adds children `from`/`to`; `buildFilter()` reads
  `$data->get('from')` by those exact names.
- The `single()` vs. compound mount decision (`FilterFormFactory`, now `FormHarnessFactory`, §2.2)
  determines whether `buildFilter()` receives `getSingleValue()` or `get($name)`.

Extracting forms without addressing this trades one coupling for a worse, invisible one.

**1.3 `intrinsic` conflates three concepts.** It is simultaneously a backend checkbox, a
"value comes from config" semantic (`$config['intrinsic'] ? $config['preselect'] : $runtime`), and a
flag set programmatically where no backend row exists — `src/Engine/Loader/ValidationLoader.php:49,98`,
`src/Engine/Mod/SimpleEquationMod.php:29`, `src/List/Driver/NewsListDriver.php:58`,
`src/Integration/ContaoCalendar/ListDriver/EventsListDriver.php:71`,
`src/Integration/Terminal42Languages/EventListener/ChangelanguageListener.php:140,152`.

The smell is visible in `BooleanFilterElement::buildDca()`:

```php
if ($intrinsic) {
    unset($preselectOptions['null']);
}
```

`null` means "nothing preselected" (valid) or "no intrinsic value" (meaningless) depending on a mode
flag. Two concepts fighting over one column.

---

## 2. Target model

A filter's **presentation** becomes a separate, registrable, per-instance-selectable concern that
owns its own config and its own slice of the backend palette. The **element owns its lifecycle**:
it alone decides, from its config and the value, which predicates apply.

```
FilterForm      — how the user supplies the value. Turns submitted form data into a value object.
Value object    — what the user said, typed: a selection, a bool, a range, a phrase.
FilterElement   — what rows match. Applies its config triple and the value object, and emits
                  predicates with bound arguments (§2.3).
Predicate       — how bound arguments become SQL conditions. Stateless, public API.
```

Registration is **one-directional**: a form binds to a *value class*, never to an element type.
Selectable forms for an element = registry lookup by the element's declared value class. A form's
value class is the narrowed return type of its `decode()` (§3.1), so a form that produces the wrong
shape fails at container build or as a native `TypeError` — it is never a silently wrong query.

**The value object stays at widget level** (§13.10). It records *what the user said* — which
options, which bool, which range, which phrase — and nothing about what that means for the query.
Interpreting it (which archive ids, which parent tables, whether an empty selection means "all") is
the element's job in `buildContext()`, the one place that also holds the config.

### 2.1 Nomenclature

`FilterForm` is the *per-filter* strategy. That name is currently occupied by the whole-form
machinery (`FilterFormFactory`, `FilterFormBuildEvent`, `flare.form.{name}.build`), which must
therefore be renamed before anything else — Phase 0 in §12.

The renaming axis is **not** filter-versus-list. A form belongs to filtering; the list is
exclusively output. The three concerns are peers:

| concern | meaning |
|---|---|
| **Filter** | what matches — the predicate, query side |
| **List** | what comes out — output |
| **Form** | what goes in — input |

The aggregate is therefore not list-scoped but simply the *plural* of the singular. Four layers,
one word each:

| term | multiplicity | what it is |
|---|---|---|
| `FormHarness` | one per list × form context | the filters, their root form, and the mount↔filter map |
| `FilterForm` | one per filter | registrable presentation strategy (`buildForm()`, `decode()`) |
| `FilterFormBuilder` | one per filter, transient | collect-only builder handed to `buildForm()` |
| **mount** | one per filter that has a form | the node mounted into the root form — flat field or compound group |

`FormHarness` is an **object**, not a bare `FormInterface` returned by a factory: it owns the
`decode()` loop (§8), which otherwise has no home but `InteractiveProjector` — where its
predecessor already landed wrongly (§8).

**"Mount"** is the codebase's own word; `FilterFormFactory` already names the variable `$mount`.
It replaces "field" throughout this spec, because in the compound case the mounted node is a
`FormType` group with children and not a field at all.

Directory split, unchanged by the rename: `src/Filter/Form/` holds FLARE `FilterForm`
implementations (peers of `src/Filter/Element/`); `src/Form/` keeps Symfony-level building blocks
(`ChoicesBuilder`, `Form/Type/DateRangeFormType`). Mixing the two is the ambiguity this section
exists to remove.

### 2.2 Renaming (Phase 0, revised by #48)

Phase 0 first named the aggregate `FilterSet`; #48 renamed it to `FormHarness` and moved it, with
its factory and `FilterMount`, from `src/Filter/` to `src/Form/`. The table gives the final names:

| before | now | scope |
|---|---|---|
| `Form\Factory\FilterFormFactory` | `Form\Factory\FormHarnessFactory` | harness |
| — | `Form\FormHarness` (new) | harness |
| — | `Form\FilterMount` (new) | harness |
| `Event\FilterFormBuildEvent` | `Event\FormHarnessBuildEvent` | harness |
| `EventListener\NamedDispatch\FilterFormListener` | `…\FormHarnessListener` | harness |
| `flare.form.{name}.build` | unchanged (was `flare.filter_set.{name}.build` in between) | harness |
| `Event\FilterElementFormBuiltEvent` | `Event\FilterFormBuiltEvent` | filter |
| `flare.filter_element.{type}.form_built` | `flare.filter_form.{type}.built` | filter |
| — | `EventListener\NamedDispatch\FilterFormListener` (new) | filter |
| `Filter\FilterFormBuilder`, `…Interface` | unchanged | filter |

The query-side renames of #48 are in §2.3.

`FormHarnessFactory` builds a `FormHarness`, not a form; the root form is `FormHarness::getForm()`.
Because the aggregate is now named after the form again, its dispatch alias went back to the
original `flare.form.{name}.build`. `FilterElementFormBuiltEvent` loses its detour over the element:
it was named after the element only because `FilterForm*` was taken — and its dispatch alias follows
the class, moving out of `FilterElementListener` into the `FilterFormListener` whose name the
`FilterFormListener` → `FormHarnessListener` rename frees. `{type}` there remains the *element* type,
which is what listeners target; the alias names the concern, not the key.

`FilterFormBuilder` keeps its name deliberately. Symfony's own
`FormTypeInterface::buildForm(FormBuilderInterface)` names the builder after what it builds, not
after who receives it, so `FilterForm::buildForm(FilterFormBuilderInterface)` reads as the familiar
pattern. `single()` is moreover a statement about the form, not about fields, which rules out
`FilterFieldsBuilder`.

Rejected names, recorded so the branches stay closed:

- **`ListForm…`** — puts the form on the output side of the model, contradicting the table above.
- **`ListFilterForm…`** — contains `FilterForm` as a substring, so every search for the per-filter
  concept also hits the aggregate.
- **`FilterFormType`** — `FilterType` was the old name of the SQL predicate (now
  `src/Filter/Predicate/`, §2.3), and the suffix falsely promises a Symfony `AbstractType`.
- **`FilterSet`** — Phase 0's name for the aggregate, replaced in #48 (§13.5).
- **`FilterBar` / `FilterPanel`** — commit to a layout no template is obliged to honour, and
  understate an object that also owns `decode()`.
- **`QueryForm`** — `src/Query/` is the SQL layer.
- **`FilterWidget` for the per-filter strategy** (leaving `FilterForm` on the aggregate) — the
  strategy has `decode()` and its own DCA slice, so "widget" promises rendering and hides half the
  contract; the term is also already occupied by Contao.

### 2.3 Two stages: `FilterScope` and `FilterContext`

A filter is applied in two stages. Their separation is the answer to §14.5.

**Stage 1 — `FilterScope`, value-free.** The config triple a filter is evaluated in:

```php
final readonly class FilterScope
{
    public function __construct(
        public ListSpec         $list,
        public Filter           $filter,         // carries the canonical config
        public ContextInterface $engineContext,  // the context the list is built / filter is executed in
    ) {}
}
```

Everything that happens before a value exists receives the scope: `FilterFormInterface::buildForm()`
and `::decode()`, `ChoiceSourceContract::createChoices()`, `FilterFormBuiltEvent`, `FilterMount`.
`FormHarnessFactory` creates scopes directly. The form-side attribute keys `ATTR_SELF` and
`ATTR_SINGLE_FIELD` move from `FilterContext` to `FilterScope`.

**Stage 2 — `FilterContext`, the element's product for one invocation.** The element applies the
triple and the value:

```
buildContext(FilterContextBuilder $builder, ?ValueInterface $value)
    → $builder->addPredicate(Predicate::class, [bound arguments])
    → FilterContext { scope, formula }
```

A formula exists **only once the value is known**. `FilterContextFactory::create(FilterScope,
?ValueInterface)` is called by `ListQueryDirector` alone; the form path never builds a formula.

Why not curry predicates instead (§13.6): the value rarely fills a predicate argument verbatim. It is
combined with config (`CalendarCurrent` clamps `start`/`stop`), it changes the formula's shape (no
value ⇒ no proposition; an empty selection ⇒ `return` or `abort()`), it is checked against
config-derived sets (`Archive`'s whitelist), and the engine context can suppress the filter
altogether (`CalendarCurrent` in a `ValidationContext`). P'(z) is therefore a function, and that
function is the element: `buildContext()` curried over the scope.

**Query-side vocabulary** (#48):

| term | visibility | what it is |
|---|---|---|
| `PredicateInterface`, `AbstractPredicate` (`src/Filter/Predicate/`) | **public API** | stateless logic class: `configureOptions()`, `buildConditions(FilterConditionsBuilder, array $options)`. Tagged `flare.filter_predicate`, registered in `FilterPredicateRegistry` keyed by **class**. Formerly "filter type". |
| `FilterContextBuilder` | **public API** | what `buildContext()` receives: `$builder->scope`, `addPredicate(class, options)`, `abort()`. The only way an element applies predicates. |
| `Proposition` | `@internal` | value object: a predicate with all arguments bound (predicate service + class, target alias, resolved options) |
| `Formula` | `@internal` | the propositions one filter produced for one invocation |
| `FormulaBuilder` | `@internal` | resolves options through the predicate's `OptionsResolver` (memoised per predicate class) and collects propositions. Formerly `FilterBuilder`. |
| `FilterConditionsBuilder` / `FilterConditions` | public | the parameterised SQL fragment builder and its product. Formerly `FilterQueryBuilder` / `FilterQuery`. |

`FilterExecutor` is gone. `ListQueryDirector::invokeFilters()` builds each filter's context from
`$values[$filter->alias] ?? $filter->value` (§8) and runs every proposition's predicate into its
own `FilterConditionsBuilder`; failures are wrapped with the filter's source via
`Util\CreatesFilterExceptionTrait`. Table-alias targeting is out of scope here (§15).

---

## 3. Contracts

### 3.1 `FilterFormInterface`

```php
interface FilterFormInterface
{
    public function buildForm(FilterFormBuilderInterface $builder, FilterScope $scope): void;

    /**
     * Turns the mount's data into this form's value object, or null to contribute nothing.
     * Implementations MUST narrow the return type to one concrete value class.
     */
    public function decode(mixed $data, FilterScope $scope): ?ValueInterface;
}

#[AsFilterForm(name: 'flare_checkbox')]
final class CheckboxFilterForm extends AbstractFilterForm
{
    public function decode(mixed $data, FilterScope $scope): ?BoolValue
    {
        return BoolValue::tryFrom($data);
    }
}
```

**The value class is the narrowed return type of `decode()`.** PHP return types are covariant, so a
form may declare `?BoolValue` against the interface's `?ValueInterface`. `RegisterFilterFormsPass`
reads it by reflection — no instantiation — and fails the container build if it is not a single
concrete class implementing `ValueInterface` (a bare `?ValueInterface`, a union, `mixed`). At run
time PHP itself enforces it: a form returning anything else is a `TypeError`. There is no
`getValueClass()` and no `@template` (§13.11). The asymmetry with elements is deliberate: an element
*cannot* narrow its `$value` parameter (contravariance, §3.2), so it declares its value class in the
attribute; a form *can* narrow its return, so it declares nothing.

**`decode()` receives the mount's data, not the `FormInterface`** (§13.13). `FormHarness::decode()`
extracts it uniformly with `getData()` — model data, never view data:

| mount | data handed to `decode()` |
|---|---|
| single field | `$mount->getData()` |
| compound, submitted | `$mount->getData()` (array keyed by child name) |
| compound, not submitted | the children's own data (defaults/preselect), because Symfony does not map them onto an unsubmitted compound parent |

The "cleared vs. never touched" distinction survives in the data itself: an unsubmitted form yields
its preselect or `null`; a submitted, unchecked checkbox yields `false`. The reason `decode()` used
to receive the `FormInterface` — letting a form pick view data over model data — disappears with
choice objects that carry their own key (§7).

A form MAY additionally implement the existing contracts, which then apply to the form's own
config slice: `OptionsContract` (`configureOptions`), `TransformerContract`
(`configureTransformers`), `DcaContract` (`buildDca`).

### 3.2 `FilterElementInterface` (revised)

```php
interface FilterElementInterface
{
    public function buildContext(FilterContextBuilder $builder, ?ValueInterface $value): void;
}
```

`buildForm()` is removed, and `buildFilter()` became `buildContext()`: the element reads its config
from `$builder->scope->filter->config` and emits predicates via `$builder->addPredicate()` (§2.3)
instead of writing into a query builder. `$value` is the decoded form value, or the filter's
programmatic value (§8), or `null` (no form, nothing submitted, nothing preset).

**An implementation cannot narrow `$value` to its value class in the signature.** PHP enforces
parameter contravariance, so `?BoolValue` against the interface's `?ValueInterface` is a fatal
"declaration must be compatible" at class load. The check runs inside the method, through a
`@template`-annotated accessor on `AbstractFilterElement` (`expectValue($value, BoolValue::class)`)
so PHPStan still narrows call sites; a mismatched value is a `FilterException`, not a silently wrong
query.

`configureOptions`, `configureTransformers`, `buildDca` stay as they are.

### 3.3 Attributes

```php
#[AsFilterElement(type: self::TYPE, value: BoolValue::class)]
#[AsFilterForm(name: 'flare_checkbox')]
```

- `AsFilterElement::$value` — the value class this element consumes. `null` means the element has no
  runtime value at all (see §5.3).
- `AsFilterForm::$name` — the identifier persisted in `tl_flare_filter.formVariant`. Defaults to
  `TypeNameFactory::createFilterFormType()` over the class (suffixes `Controller`, `FilterForm`,
  `Form` stripped). `""` (= intrinsic) and `"default"` are reserved; a duplicate name fails the
  container build, because a silent override would re-interpret stored rows. The value class is not
  a discriminator — several forms share one — so the name is needed; FQCNs are not stored because a
  class rename would orphan every row.

There is no `value:`, `requires:` or `default:` on `AsFilterForm` (§13.11, §13.12, §13.14).
`AsFilterForm` stays repeatable (one class under several names); no bundle form uses that.

### 3.4 Registration

`RegisterFilterFormsPass` follows `RegisterFilterElementsPass` exactly (§13.14):
`findAndSortTaggedServices()`, `addMethodCall('add', [Reference, attribute, name, valueClass])`
on an eager `FilterFormRegistry`, public alias `flare.filter_form.{name}`. On top of that pattern it
does two checks only: the value class (§3.1) and name uniqueness (§3.3). It has no ordering
constraint relative to the element pass.

`FilterFormRegistry::findNames(string $valueClass): list<string>` is the whole election: the forms
whose value class equals the element's declared one, in registration order. It feeds the
`formVariant` options callback (§5.2) and the consistency check in `FilterFactory` (§8). An element
whose value class has no form is legal — it is used intrinsically or programmatically, and its
`formVariant` select offers only the blank option.

### 3.5 The choice port

The only thing any form pulls from an element is the option set of a selection:

```php
interface ChoiceSourceContract
{
    /**
     * @throws FilterException On invalid filter configuration (no whitelist, no inferrable ptable, …).
     */
    public function createChoices(FilterScope $scope): ChoicesBuilder;
}
```

`ChoiceFilterForm::buildForm()` checks `$scope->filter->element instanceof ChoiceSourceContract`
and throws a `FilterException` otherwise. A unit test asserts that every bundle element declaring
`value: ChoiceValue::class` implements the contract. There is no general capability mechanism
(§13.12).

Labelling is split: `createChoices()` MAY set labels the element owns — `setLabelForTable()` fed
from element-owned whitelist rows is the real case. The form owns only global overrides
(`setLabel()`, `setModelSuffix()`, `setEmptyOption()`) and `applyFormOptions()`.

(`ChoicesBuilder::buildChoices()` keeps its name; the port method is `createChoices()` so the two no
longer collide.)

---

## 4. Ownership rules

### 4.1 Backend fields

> A field belongs to the **element** if its value changes *which rows match*.
> It belongs to the **form** if its value changes only *what the user sees or can submit*.

| field | owner | note |
|---|---|---|
| `fieldGeneric` | element | changes matching |
| `fieldPublished`, `invertPublished`, `startAt`, `stopAt` | element | changes matching |
| `targetAlias` | framework | stays in `__prefix__`; rethought in §15 |
| `label` | form | presentation only |
| `isMultiple`, `isExpanded` | form | value-shape change absorbed by `decode()` |
| `preselect` | form | initial hydration (§4.2) |
| `intrinsicValue` | element | *is* the matched value (§4.2) |
| `boolMode`, `boolBinaryChoices` | **removed** | these *are* the form choice; they become form variants |

### 4.2 The three value concepts

| concept | owner | meaning |
|---|---|---|
| `intrinsicValue` | element config | canonical value when no form is selected |
| `preselect` | form config | initial hydration → the field's native `data` option |
| `decode()` result | form | value object from submitted-or-hydrated data |

`intrinsicValue` is **not** a framework-mandated key. It is declared per element in
`configureOptions()` only where meaningful. `SimpleEquationFilterElement`,
`BelongsToRelationFilterElement` and `PublishedFilterElement` have no single value — their config
*is* the value — and declare none.

Because `FormHarness::decode()` collects field defaults for unsubmitted forms (§3.1),
preselect-as-`data` flows through the normal path and every `?? $config['preselect']` fallback
disappears.

### 4.3 Value object semantics, not structure

> Two elements share a value object only if every form registered for one is meaningful for the other.

`ChoiceValue` for the four choice-based elements (`FieldValueChoice`, `DcaSelectField`,
`CodefogTagsChoice`, `Archive`); `KeywordsValue` for `SearchKeywordsFilterElement` — even though
both are "some strings". Merging structurally identical value objects reintroduces the silent
mismatch one level up, because a choice form would be offered for a free-text filter.

The value classes are `BoolValue`, `ChoiceValue`, `DateRangeValue`, `KeywordsValue`.
`ParentRefValue` is deleted (§7.4).

---

## 5. Intrinsic filters

### 5.1 Config shape

The `intrinsic` boolean is replaced by a nullable `formVariant` slot in canonical config.
`null` = intrinsic. `$config['intrinsic']` becomes `$config['formVariant'] === null`.

The programmatic call sites in §1.3 keep working by simply not setting a form.

**Do not name the column `formAlias`** — that is taken; it is the query-parameter name
(`src/Model/FilterModel.php`). Use `formVariant`. Bare `form` was rejected as too generic for a
column in the one wide shared table (§6.3), even though `FilterForm` is now the noun it selects.

### 5.2 Backend field

`formVariant` is a `select` with `submitOnChange => true`. Its blank option *is* intrinsic.
Options come from `FilterFormRegistry::findNames()` for the element's declared value class.
`ElementDcaListener` reads it alongside `$filterModel->type` to resolve the form service before
calling the form's `buildDca()`.

### 5.3 `IntrinsicContract` is deleted

`#[AsFilterElement(value: null)]` ⇒ no value object ⇒ no forms ⇒ intrinsic-only. This is more
precise than the interface it replaces, and it removes:

- `src/Contract/FilterElement/IntrinsicContract.php`
- `FieldsLoadAndSaveCallbacks::onLoadField_intrinsic()` and `::onSaveField_intrinsic()`
  (the force-check-and-disable trickery)
- `AbstractFilterElement::isOnlyIntrinsic()` and its three overrides

---

## 6. DCA composition

### 6.1 Palette segments

`DcaBuilder::palette()` currently *replaces*, and `apply()` writes one slot:

```php
$dca['palettes'][$predicate] = Str::mergePalettes($prefix, $this->palette, $suffix);
```

A third segment is required, with fixed order:

```
__prefix__  +  element palette  +  form palette  +  __suffix__
```

`ElementDcaListener` passes a scoped sub-builder to the form (e.g. `$dca->scope('form')`) whose
`palette()` lands in the form segment. `field()` stays **shared** — it already returns a shared
`DcaFieldBuilder`, and a form occasionally needs to tweak an element field's `eval`.

`apply()` keeps keying `palettes[$type]`; the palette is recomputed per record load, so no key
change is needed.

### 6.2 Legend convention

Pair the mechanism with distinct legends so the split is visible to the editor:
element contributes `{filter_legend},fieldGeneric`, form contributes
`{form_legend},label,isMultiple,isExpanded`.

### 6.3 Known gaps

- **`__selector__` is static** in `contao/dca/tl_flare_filter.php`. A form contributing a subpalette
  needs a `DcaBuilder::selector()` API. This gap exists today.
- **Columns are shared** — `tl_flare_filter` is one wide table, so form-owned columns need globally
  unique names, exactly as `boolBinaryChoices` does now. No namespacing.


---

## 7. Choices

### 7.1 The current defect

`FieldValueChoiceFilterElement::createChoices()` does:

```php
$choices->add((string) $id, (string) $label, $id);   // alias=id, choice=label, value=id
```

so the form's **model data** is label strings, and `ChoicesBuilder::buildChoiceValueCallback()`
reverse-maps a choice to its submitted value via `array_search($choice, $this->choices, true)`.
Two rows with the same display label are the same string, so they silently collide onto the first
id, and `normalizeRuntimeValue()` must rebuild the whole `ChoicesBuilder` (SQL included) to do the
lookup at all. `DcaSelectFieldFilterElement::normalizeSubmittedValue()` hand-rolls the identical
reverse mapping.

### 7.2 Root cause and fix

The root cause is not that model data is read instead of view data — it is that **a string is a
bad choice object**: it has no identity beyond its content. `Archive` already shows the working
case: its choice objects are Contao models, which are distinct and come back as themselves.

The fix makes every choice an object that names its own key (§13.9):

```php
interface ChoiceObjectInterface
{
    /** Identity of the option: the submitted value, the preselect match, and the hash input. */
    public function getChoiceKey(): string;

    /** Placeholders for the label translation, as LabelableInterface::getLabelParameters() today. */
    public function getLabelParameters(): array;
}
```

- `ChoicesBuilder::add(ChoiceObjectInterface $choice)` is the only way in — no `$alias`, no
  `$value`, no strings. `choice_value` becomes `fn (?ChoiceObjectInterface $c) => $c?->getChoiceKey()`;
  `array_search` and the identity dependence disappear, so a preselect object equal to, but not
  identical with, a listed option is still recognised. The per-table / per-class label lookup
  (`setLabelForTable()`, format defaults) keeps working; how a choice object exposes its table or
  class for that lookup is a builder decision.
- `ContaoModelChoice` covers models:
  `::from(Model $model, ?string $key = null)` for option lists — labels from the row, model held
  internally, never hashed; `::ref(string $table, int $id)` for programmatic values without a
  database round trip. Public state is `table` and `id`. The key defaults to `"<table>.<id>"`.
- `FieldValueChoice(value, label)` covers `FieldValueChoice` and `DcaSelectField`. Equal labels now
  have different keys, which is the §7.1 fix.

Every form reads **model data** (§3.1): the selected choice objects, already validated by Symfony
against the option list — a submitted key outside it makes the form invalid, and the projector then
uses its empty loader, as today.

### 7.3 `ChoiceValue`

```php
final readonly class ChoiceValue implements ValueInterface
{
    /** @var list<ChoiceObjectInterface> Deduplicated and sorted by key. */
    public array $choices;
    /** Whether the empty option was selected; its meaning is element-defined. */
    public bool $emptySelected;
}
```

- The empty option is a flag, not a sentinel string in the list. Its meaning stays with the
  element: `Archive` reads it as "use the full whitelist" unless `use_whitelist_for_options_only`.
- Equality and the fingerprint (§9) are the sorted choice keys plus the flag.
- `ChoiceFilterForm::decode()` is `ChoiceValue::tryFrom($data)`; `ChoiceFilterForm::buildForm()`
  calls `$element->createChoices($scope)` and owns `ChoiceType`, `multiple`/`expanded`, the empty
  option, labels, preselect and `applyFormOptions()`. The write-only `flare.choices_builder`
  attribute is dropped (§13.17).

| stays on the element | moves to `ChoiceFilterForm` | disappears |
|---|---|---|
| choice provision → `createChoices()` (`getForeignValues()`, `getLocalValues()`, whitelists) | `buildPreselectData()` | `normalizeRuntimeValue()`, `normalizeSubmittedValue()` |
| interpretation of the selected objects → `buildContext()` | widget options, empty option, labels | the second `ChoicesBuilder`, the reverse lookup |

### 7.4 `ArchiveFilterElement`

Archive is the stress test, and it gets simpler:

- **Choices are `ContaoModelChoice`s.** In static-ptable mode the element passes the bare id as key
  (`ContaoModelChoice::from($model, (string) $model->id)`), so existing filter URLs stay valid; in
  dynamic-ptable mode the default `"<table>.<id>"` applies. Stored preselects use `"<table>.<id>"`
  in both modes, which collides with the static-mode key — §14.9.
- **`buildContext()` works on ids only.** It groups the selected choices' `table`/`id` into
  `array<table, list<int>>`, intersects them with the whitelist (still needed: programmatic values
  bypass the form's validation), and emits `ArchivePredicate` (static) or
  `BelongsToRelationPredicate` (dynamic) as the inferrer dictates. No model is loaded on the query
  path — today `getWhitelistedParents()` fetches models only to read their ids.
- **No `ParentRefValue`.** A selection of parent records is a `ChoiceValue` like any other; the
  table/id structure lives in the choice objects. `valueFromChoiceKeys()` and the key parsing it
  implied are gone.
- **Behaviour change:** an intrinsic Archive filter with `use_whitelist_for_options_only` set used to
  filter by the whole whitelist; it now applies no condition. The combination is meaningless — an
  intrinsic filter shows no options.

**Config re-ownership** per the §4.1 rule:

| config | owner | why |
|---|---|---|
| `whitelist_parents`, `group_whitelist_parents` | element | changes matching |
| `use_whitelist_for_options_only` | element | changes matching (empty selection ⇒ `return` vs. `abort()`) |
| `format_label`, `format_empty_option`, `has_empty_option` | form | presentation only |
| `is_mandatory`, `is_multiple`, `is_expanded` | form | presentation only |
| `preselect` | form | hydration (§4.2) |

---

## 8. The value path and `FilterData`

`Filter::$data` (`?FilterData`) becomes `Filter::$value` (`?ValueInterface`), and `Filter` gains
`?FilterFormInterface $form`. A programmatic caller constructs the value object directly — e.g.
`new ChoiceValue([ContaoModelChoice::ref('tl_news_archive', 5)])` — instead of fabricating
form-shaped data. Programmatic filter creation is a first-class use case going forward.

**The path.** `ListSpec::$filters` is a plain list and every `Filter` carries an alias
(`FilterFactory` generates `_.{type}_{random}` when none is given), so the alias is the key
throughout:

1. `FormHarnessFactory` keys mounts by alias.
2. `FormHarness::decode(): array<alias, ValueInterface>` extracts each mount's data (§3.1), calls
   `$mount->filter->form->decode($data, $mount->scope)`, keeps non-null results.
   `InteractiveProjector::collectFilterData()` is deleted; this is why the aggregate is an object
   (§2.1).
3. The map travels through `ListQueryConfig::$filterValues` and the loader/context configs.
4. `ListQueryDirector` builds each filter's context from `$values[$filter->alias] ?? $filter->value`.
   Submitted data takes precedence over the programmatic value.

`FilterFactory::createFromFilterModel()` resolves the form from `formVariant` and checks that it
is registered for the element's value class; an unknown or mismatched name degrades to intrinsic
with a logged warning — it is a data condition (a renamed or uninstalled form), not a programming
error.

**`FilterData` disappears** (introduced in #46). `hasSingle()`'s submitted-vs-untouched distinction
is carried by the data `FormHarness::decode()` extracts; `toArray()`'s hashing role moves to the
value object's fingerprint (§9). `FilterFormBuilder`'s `single()` vs. compound mount decision is
unaffected — that is genuinely a form concern and stays.

---

## 9. Value objects and hashing

**Scope: in-request caching only** (§13.15). `ListSpec::hash()` — `sha1(serialize([...]))` over
`Filter::fingerprint()` — exists to key in-request memoisation. Nothing hashes across requests, and
nothing is meant to, so cross-request stability is not a requirement.

Its single consumer today is `ArchiveFilterElement::getPtableInferrer()`, whose result depends only
on `$list->dc` and `$list->config`; that cache keys on exactly those two from now on, not on the
whole spec.

**Rule for value objects:**

> A value object is `final readonly`, implements `ValueInterface`, normalizes equal representations
> in its constructor, and expresses its identity through `ValueInterface::fingerprint(): string`.

`Filter::fingerprint()` uses `$this->value?->fingerprint()` and the form's class name beside the
element's. Per class:

| value | fingerprint |
|---|---|
| `BoolValue` | the bool |
| `ChoiceValue` | sorted choice keys + `emptySelected` |
| `DateRangeValue` | the two timestamps (it holds timezone-normalized `?\DateTimeImmutable`, which is fine under this rule) |
| `KeywordsValue` | the normalized phrase |

Because each value object states its own identity, `serialize()`'s object-graph back-references
(§14.1) and the hazards they guarded against no longer matter. `Util\Fingerprint` is deleted, and
`ValueObjectContainmentTest` shrinks to: every class in `src/Filter/Value/` and every
`#[AsFilterElement(value: …)]` is `final`, `readonly` and implements `ValueInterface`.

---

## 10. Enforcement summary

| risk | mechanism | when it fires |
|---|---|---|
| form produces the wrong shape | narrowed `decode()` return type, read by the compiler pass | container build; `TypeError` at run time |
| element receives the wrong type | `expectValue()` inside `buildContext()` (§3.2) | `FilterException`, at query build |
| stored `formVariant` does not fit the element | `findNames()` check in `FilterFactory` (§8) | degrades to intrinsic, logged |
| choice form on an element without options | `instanceof ChoiceSourceContract` in `ChoiceFilterForm`, plus a unit test over bundle elements | `FilterException` / test suite |
| two forms claim one name | uniqueness check in the compiler pass | container build |
| value object not final/readonly | reflection test | test suite |

---

## 11. Migration

Phase 0 (§2.2) is rename-only and touches no schema. Everything below belongs to steps 1-4, with
the sole exception of the data migration, which is written **last** — see "Why the data migration
comes last" below.

Two separable things, deliberately not done together:

**Schema** — tracked by the DCA, applied by Contao's schema diff. Columns in this bundle come only
from `sql` keys in `contao/dca/tl_flare_filter.php`; there is no schema listener.

- New column `formVariant` (`varchar`, landed), plus form-owned columns as they move.
- Drop `intrinsic`, `boolMode`, `boolBinaryChoices` and the `boolMode_binary` subpalette /
  `boolMode` selector entry.
- `preselect` stays a column but becomes form-owned; its semantics narrow to hydration only, and
  the `unset($preselectOptions['null'])` hack in `BooleanFilterElement::buildDca()` goes away.
  Stored preselects are matched as choice keys (§7.2). For `Archive` the stored keys are
  `"<table>.<id>"` in both ptable modes (that is what the backend options write), while the
  frontend key in static mode is the bare id — open, §14.9.

**Data** — one Contao migration: `intrinsic = 0` → a frozen `element type → form name` literal;
`intrinsic = 1` → `''`. There is no "default form" to resolve (§13.14).

### Why the data migration comes last

The mapping cannot be frozen correctly before the forms exist. Three reasons, in order of severity:

1. **The `flare_bool` arm is undecidable early.** `BooleanFilterElement::buildForm()` always mounts a
   `CheckboxType` and never reads `boolMode`; `ternary` posts a backend error as unsupported. But
   `boolBinaryChoices` *does* change matching, via `resolveRuntimeValue()` — under `NULL_TRUE` an
   unchecked box means "no opinion", under `NULL_FALSE`/`TRUE_FALSE` it means `false`. A checkbox
   form that owns no element config cannot express both, so which bool rows map to which bool form
   is only answerable once those forms are written.
2. **A migration file auto-registers the moment it exists.** `src/Migration` is not excluded from
   the PSR-4 service resource, and `autoconfigure: true` plus `AbstractMigration` earns Contao's
   `contao.migration` tag — no manual tag. So the file's mere presence means `contao:migrate` runs
   it, against form names that may not exist yet. It is also one-shot: once `shouldRun()` has been
   satisfied it goes quiet, so wrong values stay committed and need a *second* corrective migration.
3. **Form names are permanent data.** The migration must hardcode `element type → form name` as
   frozen literals rather than resolving through `FilterFormRegistry`, because a migration describes
   historical rows — resolving names through live code would let a later rename retroactively change
   what already-migrated rows meant. Freezing those literals before the names settle is the same
   mistake one step earlier.

**The ordering hazard this creates, and the constraint it imposes.** The backfill reads `intrinsic`,
which the schema step *drops*. Contao runs migrations before applying the schema diff, so a single
`contao:migrate` on a database still carrying `intrinsic` will backfill and then drop, in that order
— which is correct. But applying a schema update after the drop lands and before the migration
exists destroys the source column, and with it any chance of recovering the mapping. Therefore:

- The data migration MUST be written before any environment holding real rows applies the schema
  diff that drops `intrinsic`.
- `shouldRun()` MUST return `false` when `intrinsic` is already absent, so an environment that has
  passed that point fails closed rather than backfilling from nothing.
- `shouldRun()` MUST NOT key on `formVariant` being absent — the column arrives with the schema step
  and will already exist. Key on `intrinsic` being present plus a type-restricted count of rows
  still needing work; without the type restriction, the element types that get no form at all
  (`flare_published`, `flare_relation_belongsTo`, `flare_equation_simple`, `cfg_tags_search`) keep
  the migration pending forever.


---

## 12. Sequencing

Phase 0 clears the vocabulary so the rest can be written in the target words. After that the
contract set is the entire risk; the remaining elements are mechanical. `PLAN_FILTER_FORMS.md`
merges steps 1 and 3 into one atomic cut (no pilot, no legacy adapter) and tracks the open items.

0. **Nomenclature refactor.** ✅ **Done** (#47), then revised by #48 (§2.2). Pure rename plus one new
   object, no behaviour or schema change. Left over: two test files still carry the Phase 0 names
   (`FilterSetFactoryTest`, `FilterSetListenerTest`).
1. **Contracts + the cut.** 🟡 **In progress — tree red.** Landed: value objects, form/element
   contract signatures, `ChoiceSourceContract`, `AsFilterForm(name:)`, the registry and pass in
   their first shape, `formVariant`, the predicate layer (§2.3). To do, in the target shape of this
   spec: `FilterScope` and the two-stage split (§2.3); `decode(mixed, FilterScope)` with the value
   class from its return type (§3.1); registry/pass aligned with elements, `requires`/`default`
   removed (§3.4); choice objects, `ChoiceValue` of choice objects, `ParentRefValue` removed (§7);
   element bodies and `expectValue()`; the five forms; `Filter::$value` / `$form`; `FormHarness::decode()`
   and the value path (§8); value fingerprints, `Util\Fingerprint` removed (§9); the two element
   events removed (§13.16); `IntrinsicContract` removed.
2. **DCA composition.** `DcaBuilder` palette segments, `scope()`, `selector()`; `ElementDcaListener`
   resolves and invokes the form's `buildDca()`.
3. **Migrate the rest.** Merged into step 1 (atomic cut). `CodefogTagsSearchElement` has neither
   `buildForm()` nor `buildContext()` (only `buildDca()`) — it needs `value: null` and nothing else.
4. **Schema changes** per §11 — drop `intrinsic`, `boolMode`, `boolBinaryChoices` and their
   palette/selector entries. DCA only; no data migration yet.
5. **Fold `FilterData`** out per §8.
6. **Data migration** per §11. Written last, once the forms and their names exist and the
   `flare_bool` mapping is decided. Subject to the ordering hazard §11 records: it reads
   `intrinsic`, which step 4 removes from the DCA, so it must land before any environment with real
   rows applies the schema diff.

Targeting (§15) is a separate piece of work after this one and before the v0.2 release.

---

## 13. Resolved decisions

Recorded here because each one closes a branch the design could otherwise have taken.

1. **`ArchiveFilterElement` shares the choice port** with `FieldValueChoice`, `DcaSelectField` and
   `CodefogTagsChoice`, all served by one generic choice form. No second port is needed (§7.4).
2. **No compound case needs per-child capability negotiation.** `DateRangeFilterElement` needs
   nothing from the element at all.
3. **Form-owned field labels stay in the central language files**
   (`contao/languages/{en,de}/tl_flare_filter.php`). Forms declare fields and palette segments; they
   do not declare translations. This keeps `tl_flare_filter`'s label surface in one place, consistent
   with the shared-column reality of §6.3.
4. **`intrinsic`-branching `buildDca()` methods collapse into the §6 palette segments.**
   `CalendarCurrentFilterElement::buildDca()` already appends `{form_legend},isLimited` only when not
   intrinsic — hand-rolling exactly the split §6 formalises. Under the target model that branch
   disappears and `isLimited` becomes form-owned config. The same applies to
   `SearchKeywordsFilterElement::buildDca()`, `DcaSelectFieldFilterElement::buildDca()` and
   `BooleanFilterElement::buildDca()`.
5. **The aggregate is called `FormHarness` and is an object.** The naming axis is multiplicity within
   *Filter*, not filter-versus-list (§2.1) — the list is exclusively output, so no `List…` name can
   be right for a form. Phase 0 named it `FilterSet`; #48 renamed it `FormHarness` and moved it to
   `src/Form/`: the object holds the root form and the mounts, not the filters as such, and
   `ListSpec::$filters` stays a separate plain list (§14.4). Making it an object rather than a
   factory return value is what gives `decode()` a home (§8).
6. **Two stages, and the formula only exists once the value does** (§2.3). `FilterScope` is the
   value-free config triple for everything before the value; `FilterContext` is the element's
   product. Predicates are not curried as data: the value-to-argument mapping is element logic, so
   the curried function is the element itself.
7. **Elements describe conditions; predicates execute them** (#48). `PredicateInterface` is public
   API; `Proposition`, `Formula` and `FormulaBuilder` are `@internal`; `FilterContextBuilder::addPredicate()`
   is how an element applies a predicate. Predicates are registered by class, not by type name.
8. **Forms read model data, always** (§3.1). The view-vs-model choice was only ever needed because
   choice objects were badly chosen.
9. **Choices are objects that name their key** (§7.2): `ChoiceObjectInterface` with
   `getChoiceKey()` and `getLabelParameters()`, `ContaoModelChoice::from()` / `::ref()`,
   `FieldValueChoice`. Custom choice semantics mean a custom choice class, not a translation step.
10. **Value objects stay at widget level** (§2). Domain interpretation happens in `buildContext()`.
    `ChoiceValue` carries choice objects; `ParentRefValue` and `valueFromChoiceKeys()` are gone.
11. **A form's value class is its narrowed `decode()` return type** (§3.1), read by reflection at
    compile time. No `getValueClass()`, no `@template`, no `value:` on the attribute.
12. **No general `requires` mechanism** (§3.5). The only case is the choice form; a direct
    `instanceof` check plus a unit test replace it. A capability mechanism can be added later without
    breaking anything; removing one that third parties use could not.
13. **`decode()` takes data, not a `FormInterface`** (§3.1). Extraction lives once, in
    `FormHarness::decode()`.
14. **Form registration mirrors element registration** (§3.4): eager registry, `addMethodCall`,
    public alias. No `ServiceLocator`, no `default` / `findDefaultName()`, no "every value class has a
    form" check, no ordering constraint. The migration uses frozen literals, so nothing needs a
    default form.
15. **Hashing is for in-request caching only** (§9). Value objects state their identity through
    `fingerprint(): string`; `Util\Fingerprint` and the strict containment rule are gone.
16. **`FilterElementBuildingEvent` and `FilterElementBuiltEvent` are removed**, with their
    `flare.filter_element.{type}.building|built` aliases. An event is public API whose payload would
    pin `FilterScope`, the value and `FilterContextBuilder` while they are new; adding an event later
    breaks nothing. The use cases are covered: skip or add filters via `ListBuildEvent` /
    `flare.list.{type}.build`, preset values via programmatic `Filter::$value`, extra conditions via
    another filter or a custom element. Lost: a per-execution decision depending on the engine
    context, which needs a custom element. The 0.1 events `FilterElementInvokingEvent` /
    `FilterElementInvokedEvent` therefore have no direct successor; the migration guide says so.
17. **`flare.choices_builder` is dropped.** The attribute was write-only (set in four `buildForm()`s,
    read nowhere); `ChoiceFilterForm` owns its `ChoicesBuilder`. Custom form themes that read it
    from `form.vars` lose it — release note.

---

## 14. Remaining unknowns

1. ~~**`serialize()` stability for readonly value objects**~~ — measured in step 0 (probe commit
   06fc013 on `feat/filter-types`, not carried over): `serialize()` is not a pure value function
   over an object graph (repeated objects become back-references). **Moot** since §13.15: value
   objects state their identity through `fingerprint()`, and only in-request caching is required.
2. **`DcaBuilder::selector()`** (§6.3) has no consumer yet. Confirm whether any form in the initial
   migration actually contributes a subpalette; if none does, defer the API.
3. **`ArchiveFilterElement::buildPreselectData()`** is `ListSpec`-aware today. With preselect stored
   as choice keys it should reduce to a key lookup against `createChoices()` inside `ChoiceFilterForm`
   — confirm while porting, together with 9.
4. **Whether `ListSpec::$filters` should become a collection object.** The naming side is settled
   (§13.5); the alias is the key everywhere (§8). Open only whether the plain list deserves its own
   type. Not needed for this refactor.
5. ~~Form-time vs. query-time context~~ — resolved, §13.6.
6. ~~How the compiler pass learns a form's value class~~ — resolved, §13.11 and §13.14.
7. ~~The element events~~ — resolved, §13.16.
8. ~~`DateRangeValue` holding `\DateTimeImmutable`~~ — resolved by the relaxed rule, §9.
9. **Archive's stored preselect keys vs. its static-mode choice keys.** The backend preselect
   options write `"<table>.<id>"` in both ptable modes; the frontend submits the bare id in static
   mode. Under key-based matching the two must agree. Either the static-mode key becomes
   `"<table>.<id>"` too (one format, but existing filter URLs like `?archive=5` break), or stored
   preselects are translated (by the data migration of step 6, or by the backend options writing
   bare ids in static mode).

---

## 15. Deferred: table-alias targeting

Out of scope for this refactor; to be redesigned **before the v0.2 release**.

The v0.1 model assumed every filter applies to exactly one SQL table/join alias, chosen from outside
the element (`AsFilterElement::$isTargeted`, `Filter::$targetAlias`, `Filter::$targetingForced`,
the `targetAlias` backend field). In fact only the element knows which aliases its conditions
address, so the alias has to be coupled with `FilterConditionsBuilder`:

- **Default:** an element selects at most one target; `null` means `TableAliasRegistry::ALIAS_MAIN`.
  `$builder->column()` applies the selected alias automatically.
- **Opt-out:** an element may leave the default and address aliases itself.
- `isTargeted`, `targetingForced`, `Filter::$targetAlias` and `AddTargetAliasFieldCallback` are
  reconsidered as part of that design.

**Known breakage until then:** #48 dropped the old resolution in `FilterExecutor::invokeFilter()`
(`isTargeted || targetingForced` ⇒ `$filter->targetAlias`, else `ALIAS_MAIN`).
`FilterContextBuilder::build()` now hands `$filter->targetAlias` straight to `FormulaBuilderFactory`,
so a non-targeted element with a `targetAlias` targets it anyway, and a `null` alias reaches
`FormulaBuilder`'s `string` parameter as a `TypeError`. The cut may restore the old resolution as a
stopgap; it must not extend it.
