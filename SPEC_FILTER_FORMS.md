# SPEC: Decoupling Filter Controls from Filter Elements

**Status:** Design settled (2026-10-20/21, §13.6-13.31) and **implemented** on `v0.2-refactor-filter-api`
(all of §12 steps 0-6, documentation included). Where the implementation deliberately differs from the
text below, a *Implementation note* marks the place (R3, R5, R7-R10). Manual verification on a real Contao
install and real MySQL/MariaDB is still pending. The plan and its checklist live in `PLAN_FILTER_FORMS.md`.
**Scope:** `src/Filter/` (incl. the new `src/Filter/Control/`), `src/Form/`, `src/Query/`, `src/Event/`, `src/EventListener/NamedDispatch/`,
`src/Registry/`, `src/DependencyInjection/`, `src/DataContainer/Builder/`, `src/Util/`,
`contao/dca/tl_flare_filter.php`, `src/EventListener/Contao/ElementDcaListener.php`,
`src/Engine/Projector/InteractiveProjector.php`
**Breaking:** yes, deliberately — v0.2 is unreleased and the goal is a stable architecture, not
compatibility. `FilterElementInterface`, `Filter::$data`, `ChoicesBuilder::add()`, the
`tl_flare_filter` schema, the renames of §2.2 (service ids, event classes, the dispatch alias
`flare.filter_element.{type}.form_built`, and the per-filter strategy `FilterForm` → `FilterControl`), the predicate layer of §2.3 replacing filter types,
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

A filter's **presentation** becomes a separate, registrable, per-instance-selectable concern — the
**filter control** — that owns its own config and its own slice of the backend palette. The
**element owns its lifecycle**: it alone decides, from its config and the value, which predicates
apply.

```
FilterControl   — how the user supplies the value. Turns submitted form data into a value object.
Value object    — what the user said, typed: a selection, a bool, a range, a phrase.
FilterElement   — what rows match. Applies its config triple and the value object, and emits
                  predicates with bound arguments (§2.3).
Predicate       — how bound arguments become SQL conditions. Stateless, public API.
```

Registration is **one-directional**: a control binds to a *value class*, never to an element type.
Selectable controls for an element = registry lookup by the element's declared value class. A
control's value class is the narrowed return type of its `decode()` (§3.1), so a control that
produces the wrong shape fails at container build or as a native `TypeError` — it is never a
silently wrong query.

**The value object stays at widget level** (§13.10). It records *what the user said* — which
options, which bool, which range, which phrase — and nothing about what that means for the query.
Interpreting it (which archive ids, which parent tables, whether an empty selection means "all") is
the element's job in `buildContext()`, the one place that also holds the config.

### 2.1 Nomenclature

The renaming axis is **not** filter-versus-list. Input belongs to filtering; the list is
exclusively output. The three concerns are peers:

| concern | meaning |
|---|---|
| **Filter** | what matches — the predicate, query side |
| **List** | what comes out — output |
| **Form** | what goes in — input, rendered as one Symfony form per list |

Two levels must not share a word: the **Symfony level** (a real form: root form, builder, mounted
fields) and the **strategy level** above it (which kind of input a filter offers, and how its data
becomes a value). "Form" names only the Symfony level; the strategy is a **control** (§13.27):

| term | multiplicity | level | what it is |
|---|---|---|---|
| `FormHarness` | one per list × form context | Symfony | the root form, and the mount↔filter map |
| `FilterControl` | one per filter | strategy | registrable presentation strategy (`buildForm()`, `decode()`, `hydrate()`) — checkbox, choice, date range, keywords |
| `FilterFormBuilder` | one per filter, transient | Symfony | collect-only builder handed to `FilterControl::buildForm()` |
| **mount** | one per filter that has a control | Symfony | the node mounted into the root form — flat field or compound group |

"Control" is the technology-neutral UI word for an input element ("Bedienelement" in the German
backend). How a control renders — Symfony fields, HTML — is its means, not its identity.

`FormHarness` is an **object**, not a bare `FormInterface` returned by a factory: it owns the
`decode()` loop (§8), which otherwise has no home but `InteractiveProjector` — where its
predecessor already landed wrongly.

**"Mount"** is the codebase's own word; `FilterFormFactory` already named the variable `$mount`.
It replaces "field" throughout this spec, because in the compound case the mounted node is a
`FormType` group with children and not a field at all.

Directory split: `src/Filter/Control/` holds the `FilterControl` implementations (peers of
`src/Filter/Element/`); `src/Form/` keeps Symfony-level building blocks (`FormHarness`,
`FilterMount`, `ChoicesBuilder`, `Form/Type/DateRangeFormType`).

### 2.2 Renaming

Phase 0 freed the name `FilterForm` from the whole-form machinery and first named the aggregate
`FilterSet`; #48 renamed the aggregate to `FormHarness` and moved it to `src/Form/`; this design
renames the per-filter strategy from `FilterForm` to `FilterControl`. Final names:

| before | now | scope |
|---|---|---|
| `Form\Factory\FilterFormFactory` | `Form\Factory\FormHarnessFactory` | harness |
| — | `Form\FormHarness`, `Form\FilterMount` (new) | harness |
| `Event\FilterFormBuildEvent` | `Event\FormHarnessBuildEvent` | harness |
| `EventListener\NamedDispatch\FilterFormListener` | `…\FormHarnessListener` | harness |
| `flare.form.{name}.build` | unchanged (was `flare.filter_set.{name}.build` in between) | harness |
| `Event\FilterElementFormBuiltEvent` | `Event\FilterControlBuildFormEvent` (was `FilterFormBuiltEvent`, then `FilterControlBuiltEvent` in between) | control |
| `flare.filter_element.{type}.form_built` | `flare.filter_control.{type}.build_form` (was `flare.filter_form.{type}.built`, then `.built`) | control |
| — | `EventListener\NamedDispatch\FilterControlBuildFormListener` (new) | control |
| `Filter\Form\FilterFormInterface`, `AbstractFilterForm` (v0.2-only) | `Filter\Control\FilterControlInterface`, `AbstractFilterControl` | control |
| `#[AsFilterForm]`, tag `flare.filter_form`, alias `flare.filter_form.{name}` (v0.2-only) | `#[AsFilterControl]`, tag `flare.filter_control`, alias `flare.filter_control.{type}` | control |
| `FilterFormRegistry`, `RegisterFilterFormsPass`, `TypeNameFactory::createFilterFormType()` (v0.2-only) | `FilterControlRegistry`, `RegisterFilterControlsPass`, `createFilterControlType()` (suffixes `FilterControl`, `Control`) | control |
| `Filter\FilterFormBuilder`, `…Interface` | unchanged | Symfony |
| `Event\ElementDcaEvent` (fired for list drivers and filter elements) | `Event\ListDriverBuildDcaEvent` (`flare.list_driver.{type}.dca`), `Event\FilterElementBuildDcaEvent` (`flare.filter_element.{type}.dca`) | element / list driver |
| `Event\ListTransformerEvent`, `flare.list.{type}.transformers` | `Event\ListDriverConfigureTransformersEvent`, `flare.list_driver.{type}.transformers` | list driver |
| — | `Event\FilterControlBuildDcaEvent`, `flare.filter_control.{type}.dca` with the *control* type (new) | control |
| `EventListener\NamedDispatch\ElementDcaEventListener` | `…\DcaEventListener` | element / list driver / control |
| `FilterTransformerEvent` (element-only) | `Event\FilterElementConfigureTransformersEvent` (`$element`, `flare.filter_element.{type}.transformers`) and `Event\FilterControlConfigureTransformersEvent` (`$control`, `flare.filter_control.{type}.transformers`) (new) | element / control |
| `FilterTransformerListener`, `ListTransformerListener` | `…\TransformersEventListener` (one handler per event) | element / control / list driver |
| `Event\DetailsPageUrlGeneratedEvent` | `Event\ReaderUrlGeneratedEvent` | reader |

The query-side renames of #48 are in §2.3.

`FormHarnessFactory` builds a `FormHarness`, not a form; the root form is `FormHarness::getForm()`.
Because the aggregate is named after the form, its dispatch alias went back to the original
`flare.form.{name}.build`. `FilterElementFormBuiltEvent` loses its detour over the element: it was
named after the element only because `FilterForm*` was taken; it is dispatched after a control built
its fields, so it is named after the control, and its alias moves out of `FilterElementListener`
into `FilterControlBuildFormListener`. `{type}` there is the *control* type (`Filter::$controlType`), like in every
other `flare.filter_control.{type}.*` event (§13.32); it carried the element type until the review of PR #49.

`FilterFormBuilder` keeps its name deliberately: it is a Symfony-level builder for one filter's
part of the form. Symfony's own `FormTypeInterface::buildForm(FormBuilderInterface)` names the
builder after what it builds, so `FilterControl::buildForm(FilterFormBuilderInterface)` reads as
"the control builds its part of the form". `single()` is a statement about the form, not about
fields, which rules out `FilterFieldsBuilder`.

Rejected names, recorded so the branches stay closed:

- **`FilterForm`** for the per-filter strategy — the name this spec used until 2026-10-21. It evokes
  HTML and Symfony forms, while the strategy is a layer above them, and it blurred the line to
  `FormHarness` / `FilterFormBuilder`, which *are* Symfony forms.
- **`FilterInput`** — collides with `Contao\Input`, used in the same code, and evokes `<input>`.
- **`FilterPresenter`** — accurate as a pattern, opaque to editors, and sounds like output.
- **`FilterWidget`** — promises rendering and hides `decode()`/`hydrate()` and the DCA slice; the
  term is also already occupied by Contao.
- **`ListForm…`** — puts input on the output side of the model, contradicting the table above.
- **`ListFilterForm…`** — contains `FilterForm` as a substring, so every search for one concept
  also hits the other.
- **`FilterFormType`** — `FilterType` was the old name of the SQL predicate (now
  `src/Filter/Predicate/`, §2.3), and the suffix falsely promises a Symfony `AbstractType`.
- **`FilterSet`** — Phase 0's name for the aggregate, replaced in #48 (§13.5).
- **`FilterBar` / `FilterPanel`** — commit to a layout no template is obliged to honour.
- **`QueryForm`** — `src/Query/` is the SQL layer.

### 2.3 Two stages: `FilterScope` and `FilterContext`

A filter is applied in two stages (§13.6).

**Stage 1 — `FilterScope`, value-free.** The config triple a filter is evaluated in:

```php
final readonly class FilterScope
{
    public function __construct(
        public ListSpec         $list,
        public Filter           $filter,         // carries element config and control config (§8)
        public ContextInterface $engineContext,  // the context the list is built / filter is executed in
    ) {}
}
```

Everything that happens before a value exists receives the scope: `FilterControlInterface::buildForm()`,
`::decode()` and `::hydrate()`, the element ports (§3.5), value providers (§3.6),
`FilterControlBuildFormEvent`, `FilterMount`. `FormHarnessFactory` creates scopes directly. The form-side
attribute keys `ATTR_SELF` and `ATTR_SINGLE_FIELD` move from `FilterContext` to `FilterScope`.

**Engine contexts** are `InteractiveContext`, `ValidationContext`, `AggregationContext` and, new,
**`ConfigurationContext`** — the list being configured in the backend, where filters are evaluated
for their options but never queried (§6.4). `ContextInterface` only requires `getContextType()`.

**Stage 2 — `FilterContext`, the element's product for one invocation.** The element applies the
triple and the value:

```
buildContext(FilterContextBuilder $builder, ?ValueInterface $value)
    → $builder->addPredicate(Predicate::class, [bound arguments])
    → FilterContext { scope, formula }
```

A formula exists **only once the value is known**. `FilterContextFactory::create(FilterScope,
?ValueInterface)` is called by `ListQueryDirector` alone; the form path never builds a formula.

Why not curry predicates instead: the value rarely fills a predicate argument verbatim. It is
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

`FilterExecutor` is gone. `ListQueryDirector::invokeFilters()` builds each filter's context from the
value of §8 and runs every proposition's predicate into its own `FilterConditionsBuilder`; failures
are wrapped with the filter's source via `Util\CreatesFilterExceptionTrait`. Table-alias targeting
is out of scope here (§15).

> **Implementation note (R3).** `ListSpec::withFilter()` throws a `FlareException` when the filter's alias is
> already present in the list; it never silently replaces. Aliases are the key of the value path (§8), so a
> duplicate would make two filters share one form value.

---

## 3. Contracts

### 3.1 `FilterControlInterface`

```php
interface FilterControlInterface
{
    public function buildForm(FilterFormBuilderInterface $builder, FilterScope $scope): void;

    /**
     * Turns the mount's data into this control's value object, or null to contribute nothing.
     * Implementations MUST narrow the return type to one concrete value class.
     */
    public function decode(mixed $data, FilterScope $scope): ?ValueInterface;

    /**
     * The inverse of decode(): the widget data for a value of this control's value class.
     * Used to hydrate an unsubmitted form from Filter::$value (§8).
     */
    public function hydrate(?ValueInterface $value, FilterScope $scope): mixed;
}

#[AsFilterControl(type: 'flare_choice')]
final class ChoiceFilterControl extends AbstractFilterControl
{
    public function decode(mixed $data, FilterScope $scope): ?ChoiceValue
    {
        return ChoiceValue::tryFrom($data);
    }

    // hydrate(): ChoiceValue → its choice objects (a list, or the single one for single-select)
}
```

**The value class is the narrowed return type of `decode()`.** PHP return types are covariant, so a
control may declare `?ChoiceValue` against the interface's `?ValueInterface`. `RegisterFilterControlsPass`
reads it by reflection — no instantiation — and fails the container build if it is not a single
concrete class implementing `ValueInterface` (a bare `?ValueInterface`, a union, `mixed`). At run
time PHP itself enforces it: a control returning anything else is a `TypeError`. There is no
`getValueClass()` and no `@template` (§13.11). The asymmetry with elements is deliberate: an element
*cannot* narrow its `$value` parameter (contravariance, §3.2), so it declares its value class in the
attribute; a control *can* narrow its return, so it declares nothing. `hydrate()` cannot narrow its
parameter for the same reason as elements, and checks its argument the same way.

**`decode()` receives the mount's data, not the `FormInterface`** (§13.13). `FormHarness::decode()`
extracts it uniformly with `getData()` — model data, never view data:

| mount | data handed to `decode()` |
|---|---|
| single field | `$mount->getData()` |
| compound, submitted | `$mount->getData()` (array keyed by child name) |
| compound, not submitted | `$mount->getData()` — the array `hydrate()` set as the mount's `data` (§8 step 1) |

The "cleared vs. never touched" distinction survives in the data itself: an unsubmitted form yields
its hydrated value or `null`; a submitted, unchecked checkbox yields `false`. The reason `decode()`
used to receive the `FormInterface` — letting a control pick view data over model data — disappears with
choice objects (§7).

**Control config.** A control MAY implement `OptionsContract` (`configureOptions`), `TransformerContract`
(`configureTransformers`) and `DcaContract` (`buildDca`). They apply to the control's own config,
`Filter::$controlConfig`, which is resolved and stored separately from the element's (§8, §13.20). A
control never reads element config; what it needs from the element it gets through a port (§3.5).
`AbstractFilterControl` mirrors `AbstractFilterElement`: `configureOptions()` and a protected
`transformFilterModel(ConfigBuilder, FilterModel)` are abstract, the default `configureTransformers()`
registers the latter for `FilterModel`, `buildDca()` is a no-op, and the interface methods are redeclared
abstract. Concrete controls document the `$data` shape of `decode()` and narrow the return type of
`hydrate()`.

### 3.2 `FilterElementInterface` (revised)

```php
interface FilterElementInterface
{
    public function buildContext(FilterContextBuilder $builder, ?ValueInterface $value): void;
}
```

`buildForm()` is removed, and `buildFilter()` became `buildContext()`: the element reads its config
from `$builder->scope->filter->config` and emits predicates via `$builder->addPredicate()` (§2.3)
instead of writing into a query builder. `$value` is `Filter::$value` or the submitted value (§8),
or `null`. The element no longer knows where the value came from — backend, form or code — and no
longer distinguishes "intrinsic": every `$config['intrinsic'] ? $config['preselect'] : …` branch
disappears.

**An implementation cannot narrow `$value` to its value class in the signature.** PHP enforces
parameter contravariance, so `?BoolValue` against the interface's `?ValueInterface` is a fatal
"declaration must be compatible" at class load. The check runs inside the method, through a
`@template`-annotated accessor on `AbstractFilterElement` (`expectValue($value, BoolValue::class)`)
so PHPStan still narrows call sites; a mismatched value is a `FilterException`, not a silently wrong
query.

`configureOptions`, `configureTransformers`, `buildDca` stay as they are and apply to the element's
own config only.

### 3.3 Attributes

```php
#[AsFilterElement(type: self::TYPE, value: BoolValue::class)]
#[AsFilterControl(type: 'flare_checkbox')]
```

- `AsFilterElement::$value` — the value class this element consumes. `null` means the element has no
  runtime value at all: its config *is* the value (§5.3). A non-null value class requires a value
  provider (§3.6).
- `AsFilterControl::$type` — the identifier persisted in `tl_flare_filter.control`. Defaults to
  `TypeNameFactory::createFilterControlType()` over the class (suffixes `FilterControl`,
  `Control` stripped). `""` (= intrinsic) and `"default"` are reserved; a duplicate type fails the
  container build, because a silent override would re-interpret stored rows. The value class is not
  a discriminator — several controls share one — so the name is needed; FQCNs are not stored because a
  class rename would orphan every row.

There is no `value:`, `requires:` or `default:` on `AsFilterControl` (§13.11, §13.12, §13.14).
`AsFilterControl` stays repeatable (one class under several types); no bundle control uses that.

### 3.4 Control registration

`RegisterFilterControlsPass` follows `RegisterFilterElementsPass` exactly (§13.14):
`findAndSortTaggedServices()`, `addMethodCall('add', [Reference, name, valueClass])`
on an eager `FilterControlRegistry`, public alias `flare.filter_control.{type}`. On top of that pattern it
does two checks only: the value class (§3.1) and name uniqueness (§3.3). It has no ordering
constraint relative to the element pass.

`FilterControlRegistry::findTypes(string $valueClass): list<string>` is the whole election: the controls
whose value class equals the element's declared one, in registration order. It feeds the
`control` options callback (§5.2) and the consistency check in `FilterFactory` (§8). An element
whose value class has no control is legal — it is used intrinsically or programmatically, and its
`control` select offers only the blank option.

### 3.5 Ports

A control reads nothing from the element but what the element offers through a **port**: a plain
interface the element implements, checked with `instanceof` where it is used (§13.12). There is no
declarative capability mechanism.

```php
/** Required by ChoiceFilterControl and ChoiceValueProvider. */
interface ChoiceSourceContract
{
    /**
     * @throws FilterException On invalid filter configuration (no whitelist, no inferrable ptable, …).
     */
    public function buildChoices(ChoicesBuilder $choices, FilterScope $scope): void;

    /**
     * A reference choice for a canonical key — no database access; null for an unreadable key.
     * Only the element knows which choice class a key belongs to ("tl_news_archive.5" is table + id
     * for Archive, but may be a plain field value containing a dot for FieldValueChoice).
     */
    public function choiceFromKey(string $key): ?ChoiceObjectInterface;
}

/** Optional for DateRangeFilterControl: bounds for the widget's min/max attributes. */
interface DateBoundsContract
{
    public function getDateBounds(FilterScope $scope): DateBounds;
}
```

- `ChoiceSourceContract` is **required**: `ChoiceFilterControl::buildForm()` and
  `ChoiceValueProvider` throw a `FilterException` if the element does not implement it, and a unit
  test asserts that every bundle element declaring `value: ChoiceValue::class` does. It is the one
  source of options for the frontend widget *and* the backend value field (§6.4).
- `DateBoundsContract` is **optional**: `DateRangeFilterControl` sets `min`/`max` only when the element
  implements it (`CalendarCurrent` with `elm_isLimited`), and otherwise renders unbounded
  (`DateRange`).

`DateBounds` is a `final readonly` DTO with `?\DateTimeImmutable $min` and `$max` (exclusive); a null
bound is open.

Following the lifecycle taxonomy (`build*` methods fill a builder the caller hands in), the caller
creates the `ChoicesBuilder` through `ChoicesBuilderFactory` and the element fills it; elements no
longer depend on the factory.

Labelling is split: `buildChoices()` MAY set labels the element owns — `setLabelForTable()` fed
from element-owned whitelist rows is the real case. The control owns only global overrides
(`setLabel()`, `setModelSuffix()`, `setEmptyOption()`) and `applyFormOptions()`.

(`ChoicesBuilder::buildChoices()` is renamed to `ChoicesBuilder::build()`, so the port method that fills
the builder and the builder method that returns the choices no longer share a name.)

### 3.6 Value providers

A value class that an element consumes has exactly one **value provider**: the authoritative
service for turning the stored `value` column into a value object, and for rendering that column in
the backend (§13.18).

```php
#[AutoconfigureTag('flare.value_provider')]
interface ValueProviderInterface
{
    /**
     * The value object for the stored column content, or null for "no value".
     * Implementations MUST narrow the return type to one concrete value class.
     */
    public function provide(mixed $stored, FilterElementInterface $element): ?ValueInterface;

    /** Configures the backend widget of the `value` column for an element of this value class. */
    public function buildDcaField(DcaFieldBuilder $field, DcaContext $context): void;
}

final class BoolValueProvider implements ValueProviderInterface
{
    public function provide(mixed $stored, FilterElementInterface $element): ?BoolValue { … }
    public function buildDcaField(DcaFieldBuilder $field, DcaContext $context): void { … }
}
```

- **Registration** — a compiler pass reads the narrowed return type of `provide()` by reflection,
  exactly as for controls. One provider per value class; a duplicate, an un-narrowed return type, or an
  element declaring a value class that has no provider fails the container build.
- **Providers are services**, so they inject what they need (translator, `ChoicesBuilderFactory`, …);
  value objects stay plain values without Contao or DI dependencies.
- **`provide()` needs no scope** (§13.29). A `Filter` must not carry stored (DCA) data, so the stored
  value is turned into a value object while the filter is created (`FilterFactory`, §8) — before any
  `ListSpec` or engine context exists. Choices can be resolved without loading options because they
  match by key, never by identity (§7.2): `ChoiceValueProvider` asks the element's
  `choiceFromKey()` for reference choices (`ContaoModelChoice::ref()`, `ValueChoice`), which serve
  the query (table/id, value), hydration (`choice_value` = key) and the fingerprint (keys) alike.
- **Bundle providers:** `BoolValueProvider` (select yes/no; stores `'1'`/`'0'`), `ChoiceValueProvider`
  (backend options from `ChoiceSourceContract::buildChoices()` → `buildContaoOptions()` keyed by
  canonical choice key, via the backend scope of §6.4; stores a serialized list of canonical keys;
  `provide()` via `choiceFromKey()`), `DateRangeValueProvider` (two text inputs without `rgxp`;
  stores a serialized `[from, to]` of time expressions — timestamps, time span keys or `strtotime()`
  strings such as `today` — validated on save and resolved in `provide()`, so relative bounds move
  with the request time), `KeywordsValueProvider`.
- **Stale references fail closed.** A stored key whose record was deleted still yields a reference
  that matches nothing in the query (and, for Archive, drops out of the whitelist intersection); if
  nothing remains, the filter aborts and the list is empty rather than unfiltered.

---

## 4. Ownership rules

### 4.1 Backend fields

> A field belongs to the **element** if its value changes *which rows match*.
> It belongs to the **control** if its value changes only *what the user sees or can submit*.

The ownership is visible in the column name (§13.19): element columns carry `elm_`, control columns
`ctl_`, framework columns no prefix. Element and control config can therefore never collide, at the
price of occasional duplicates (an element and a control may each have a column of the same base name).

| prefix | columns |
|---|---|
| none (framework) | `id`, `pid`, `sorting`, `tstamp`, `title`, `type`, `published`, **`control`** (formerly `formVariant`), **`alias`** (formerly `formAlias`, the query-parameter name), `targetAlias` (rethought in §15), **`value`** (new, §4.2) |
| `elm_` | `fieldGeneric`, `columnsGeneric`, `usePublished`, `fieldPublished`, `invertPublished`, `useStart`, `fieldStart`, `useStop`, `fieldStop`, `whichPtable`, `fieldPid`, `fieldPtable`, `tablePtable`, `whitelistParents`, `groupWhitelistParents`, `useWhitelistForOptionsOnly`, `equationLeft`, `equationOperator`, `equationRight`, `configureStart`, `startAt`, `configureStop`, `stopAt`, `hasExtendedEvents`, `isLimited` |
| `ctl_` | `label`, `placeholder`, `isMandatory`, `isMultiple`, `isExpanded`, `hasEmptyOption`, `formatLabel`, `formatLabelCustom`, `formatEmptyOption`, `formatEmptyOptionCustom`, `boolBinaryChoices` |
| removed | `intrinsic` (→ blank `control`), `preselect` and `prefill` (→ `value`), `boolMode` (→ `control`, §7.5) |

Columns of the Codefog integration are classified by the same rule. `elm_groupWhitelistParents` is a
`group` widget whose stored blob uses its sub-fields' names as keys (`tablePtable`,
`whitelistParents`, `formatLabel`, `formatLabelCustom`); those inner keys are **not** renamed — they
are not columns — and the group defines its sub-fields itself instead of referencing the renamed
columns. The per-group label format is element-owned labelling (§3.5).

### 4.2 One value, one column

The `value` column holds a value of the element's value class, stored in the format of its value
provider (§3.6). Its meaning depends on `control`:

| `control` | `value` is | initial display of the list | whole list (`ValidationContext`) |
|---|---|---|---|
| blank (intrinsic) | the **fixed value** | restricted | restricted |
| a control | the **initial value** (what `prefill` meant) | pre-filtered: the control is hydrated with it, and an unsubmitted form decodes to it | **not applied** — the user can change it |

**The whole list** is everything the user can reach through the controls; only fixed values and the
elements' own config (e.g. a whitelist) bound it. The `ValidationContext` decides membership in
exactly that, so a reader may render whatever belongs to the whole list. **The initial display** is
a pre-filtered view of it (§13.30).

The old `preselect` conflated three things — widget hydration, the intrinsic value, and (for bools)
a fallback that also restricted validation. The first two map to the two rows above; the fallback is
gone. `prefill` only ever meant the initial form output. Fixed and initial value are mutually
exclusive per row, so they share the column; switching `control` keeps the stored value as long as
the value class stays the same. The same rule holds for programmatic filters (§8): with a control,
`Filter::$value` is an initial value; without one, it is fixed.

Elements without a value class (`SimpleEquation`, `Published`, `BelongsToRelation`) have no
`value` field: their config *is* the value.

### 4.3 Value object semantics, not structure

> Two elements share a value object only if every control registered for one is meaningful for the other.

`ChoiceValue` for the four choice-based elements (`FieldValueChoice`, `DcaSelectField`,
`CodefogTagsChoice`, `Archive`); `KeywordsValue` for `SearchKeywordsFilterElement` — even though
both are "some strings". Merging structurally identical value objects reintroduces the silent
mismatch one level up, because a choice control would be offered for a free-text filter.

The value classes are `BoolValue`, `ChoiceValue`, `DateRangeValue`, `KeywordsValue`.
`ParentRefValue` is deleted (§7.4).

---

## 5. Intrinsic filters

### 5.1 Config shape

The `intrinsic` boolean is replaced by `control`: blank = intrinsic. It is framework data, not
part of either config. An intrinsic filter with a value class takes its value from `value` (§4.2);
one without a value class needs nothing.

The programmatic call sites in §1.3 keep working by simply not setting a control.

**Column names** (§13.26, §13.27). The framework columns that select or name the filter's parts
are `type` (the `FilterElement`), `control` (the `FilterControl`, by its registered name) and `alias`
(the filter's name in the URL query and the form, formerly `formAlias`; Contao uses `alias` for
"name in the URL" throughout). `formVariant` — the column's name while v0.2 was in development —
implied a variant of the element's widget, which the model no longer has. `FilterModel::getFilterFormName()`,
which returns the alias (or the id as fallback), becomes `getFilterAlias()`.

### 5.2 Backend fields

`control` is a `select` with `submitOnChange => true`. Its blank option *is* intrinsic.
Options come from `FilterControlRegistry::findTypes()` for the element's declared value class.
`ElementDcaListener` reads it alongside `$filterModel->type` to resolve the control service before
calling the control's `buildDca()`.

`value` is configured by the value provider of the element's value class
(`ValueProviderInterface::buildDcaField()`) and shown in the framework part of the palette whenever
the element has a value class — as the fixed value or as the initial value of the control.

### 5.3 `IntrinsicContract` is deleted

`#[AsFilterElement(value: null)]` ⇒ no value object ⇒ no controls ⇒ intrinsic-only. This is more
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
__prefix__  +  element palette  +  control palette  +  __suffix__
```

`ElementDcaListener` passes a scoped sub-builder to the control (e.g. `$dca->scope('control')`) whose
`palette()` lands in the control segment, then dispatches `FilterControlBuildDcaEvent` with that sub-builder
and the control type, so listeners adjust only the control's part. `FilterElementBuildDcaEvent` follows once
the framework fields are in place. `field()` stays **shared** — it already returns a shared
`DcaFieldBuilder`, and a control occasionally needs to tweak an element field's `eval`.

`apply()` keeps keying `palettes[$type]`; the palette is recomputed per record load, so no key
change is needed.

### 6.2 Legend convention

Pair the mechanism with distinct legends so the split is visible to the editor:
element contributes `{filter_legend},elm_fieldGeneric`, control contributes
`{control_legend},ctl_label,ctl_isMultiple,ctl_isExpanded`. Element fields never sit in a control legend
and vice versa; CalendarCurrent's `elm_isLimited` therefore moved from `{form_legend}` to its own
`{date_limits_legend}`. The DCA fields, the language files and the model docblock list the framework
fields first, then `elm_*`, then `ctl_*`; the language files keep one section per legend.

> **Implementation note (R7).** The `control` backend field is labelled "Control" (German
> "Bedienelement"); its blank option is the intrinsic (fixed value) mode.

### 6.3 Known gaps

- **`__selector__` is static** in `contao/dca/tl_flare_filter.php`. `DcaBuilder::selector()` is built
  in step 2: the choice control's `ctl_formatLabel` / `ctl_formatEmptyOption` already have `_custom`
  subpalettes, and the renamed element columns (`elm_usePublished`, `elm_whichPtable`, …) need their
  selector entries rewritten anyway.
- **Columns are shared** — `tl_flare_filter` is one wide table. Element and control columns are kept
  apart by the `elm_` / `ctl_` prefixes (§4.1); within each side, names are global across all
  elements resp. controls.


### 6.4 Backend scope

Choice options in the backend (`value` field of a choice element) come from the same port as in the
frontend, so the backend needs a `FilterScope` (§13.21):

- `DcaContext` gains the element and a lazy `getFilterScope(): ?FilterScope`, built from the
  `ListSpec` that `ElementDcaListener::createExecutionContext()` already builds, the edited filter
  (`FilterFactory::createFromFilterModel()` with its control but without its value — the control decides an
  element's options, e.g. the archive whitelist only applies with one, while an unreadable value must not block
  the form that repairs it), and a
  `ConfigurationContext`. It is `null` when the
  list cannot be built — the same "backend must not fail on broken list configs" rule as
  `getExecutionContext()`.
- `ChoiceValueProvider::buildDcaField()` fills a fresh builder via `buildChoices($choices, $scope)` and calls `buildContaoOptions()`. A
  `FilterException` (empty whitelist, no inferrable ptable, …) or a `null` scope yields a backend
  message and an empty option list, never an error page.
- Element-specific backend option builders such as `ArchiveFilterElement::getPreselectOptions()`
  disappear.
- The scope reflects the **saved** record, as `getPreselectOptions()` does today: options follow a
  config change after the reload (`submitOnChange`) or the save.

### 6.5 Outlook: Contao 5

One column per config value is a Contao 4.13 constraint. With Contao 5 as the minimum, element and
control config can become virtual fields stored in one JSON column each, which removes the prefixes and
the duplicates. Not part of this refactor.

---

## 7. Choices and bools

### 7.1 The current defect

`FieldValueChoiceFilterElement::buildChoices()` does:

```php
$choices->add((string) $id, (string) $label, $id);   // alias=id, choice=label, value=id
```

so the form's **model data** is label strings, and `ChoicesBuilder::buildChoiceValueCallback()`
reverse-maps a choice to its submitted value via `array_search($choice, $this->choices, true)`.
Two rows with the same display label are the same string, so they silently collide onto the first
id, and `normalizeRuntimeValue()` must rebuild the whole `ChoicesBuilder` (SQL included) to do the
lookup at all. `DcaSelectFieldFilterElement::normalizeSubmittedValue()` hand-rolls the identical
reverse mapping.

### 7.2 Root cause and fix: choice objects

The root cause is not that model data is read instead of view data — it is that **a string is a
bad choice object**: it has no identity beyond its content. `Archive` already shows the working
case: its choice objects are Contao models, which are distinct and come back as themselves.

The fix makes every choice an object that names its own key (§13.9):

```php
interface ChoiceObjectInterface
{
    /** Namespace of the key, e.g. the table "tl_news_archive"; null if the key has none. */
    public function getChoiceScope(): ?string;

    /** Key within the namespace, e.g. "5". */
    public function getChoiceLocalKey(): string;

    /** Placeholders for the label translation, as LabelableInterface::getLabelParameters() today. */
    public function getLabelParameters(): array;
}
```

- **Canonical key** = `"<scope>.<local>"`, or `"<local>"` without a scope. It is what is stored
  (`value`), hashed (§9), and used as the key of backend options. In the database a model choice is
  always `"<table>.<id>"`.
- **Submitted key (URL)** — chosen per option list by `ChoicesBuilder` (§13.22): if all options of
  the list share one scope, `choice_value` is the local key (`?archive=5`); otherwise the canonical
  key (`?archive=tl_news_archive.5`). Contao internals reach the URL only when they are needed to
  disambiguate, with no element code. Trade-off: a dynamic-ptable list whose whitelist has one
  table gets short URLs, and they turn long once a second table is added.
- `ChoicesBuilder::add(ChoiceObjectInterface $choice)` is the only way in — no `$alias`, no
  `$value`, no strings. `array_search` and the identity dependence disappear, so a hydrated choice
  equal to, but not identical with, a listed option is still recognised. The per-table / per-class
  label lookup (`setLabelForTable()`, format defaults) keeps working off the choice's scope/class.
- `ContaoModelChoice` covers models: `::from(Model $model)` for option lists — labels from the row,
  model held internally, never hashed; `::ref(string $table, int $id)` for programmatic values
  without a database round trip. Scope = table, local key = id.
- `ValueChoice(value, label)` covers `FieldValueChoice`, `DcaSelectField` and `CodefogTagsChoice`:
  no scope, local key = the field value (resp. the tag id). Equal labels now have different keys,
  which is the §7.1 fix. Tags are deliberately not `ContaoModelChoice`s: their stored keys are bare
  ids today and stay valid. *(Builder's call recorded: §13.28.)*

Every control reads **model data** (§3.1): the selected choice objects, already validated by Symfony
against the option list — a submitted key outside it makes the form invalid, and the projector then
uses its empty loader, as today.

### 7.3 `ChoiceValue` and `ChoiceFilterControl`

```php
final readonly class ChoiceValue implements ValueInterface
{
    /** @var non-empty-list<ChoiceObjectInterface> Deduplicated and sorted by canonical key. */
    public array $choices;
}
```

- **The `value` field is always multiple** (§13.28): a stored choice value is a set. A single-select
  control's `hydrate()` uses the first choice in key order; `ChoiceValueProvider` never reads control
  config.
- **The empty option is "no selection".** `ChoiceValue::tryFrom()` returns `null` when nothing or
  only the empty option is selected. No element gives the empty option its own meaning —
  `Archive::processRuntimeValue()` already treats "empty option" and "nothing" identically — so
  there is no flag for it.
- `ChoiceFilterControl::buildForm()` creates the builder, calls `$element->buildChoices($choices, $scope)` and owns `ChoiceType`,
  the empty option, labels and `applyFormOptions()`. The write-only `flare.choices_builder`
  attribute is dropped (§13.17).
- **One palette for all four choice elements** (§13.23): `ctl_label`, `ctl_formatLabel`(+custom),
  `ctl_isMandatory`, `ctl_isMultiple`, `ctl_isExpanded`, `ctl_hasEmptyOption`,
  `ctl_formatEmptyOption`(+custom), `ctl_placeholder`.
- **Empty option vs. placeholder.** The empty option is always a real option via `ChoicesBuilder`
  (works for expanded radios too), labelled by `ctl_formatEmptyOption`. Symfony's `placeholder`
  option is no longer used. `ctl_placeholder` means the HTML `placeholder` attribute everywhere; the
  choice control sets it as `attr.placeholder` **only when the widget has no empty option** (multiple
  select, e.g. for a JS tag widget, or empty option off). `KeywordsFilterControl` sets it as today.
- Existing rows keep their look through the migration (§11).

| stays on the element | moves to `ChoiceFilterControl` | disappears |
|---|---|---|
| choice provision → `buildChoices()` (`getForeignValues()`, `getLocalValues()`, whitelists) | widget options, empty option, labels, placeholder | `normalizeRuntimeValue()`, `normalizeSubmittedValue()` |
| interpretation of the selected objects → `buildContext()` | hydration from `ChoiceValue` | the second `ChoicesBuilder`, the reverse lookup, `buildPreselectData()`, `getPreselectOptions()` |

### 7.4 `ArchiveFilterElement`

Archive is the stress test, and it gets simpler:

- **Choices are `ContaoModelChoice`s.** In static-ptable mode all options share one table, so URLs
  keep the bare id (§7.2); in dynamic-ptable mode with several tables they carry `"<table>.<id>"`.
  Stored values are canonical `"<table>.<id>"` in both modes — exactly what the backend writes today.
- **`buildContext()` works on ids only.** It groups the selected choices' table/id into
  `array<table, list<int>>`, intersects them with the whitelist (still needed: programmatic values
  bypass the form's validation), and emits `ArchivePredicate` (static) or
  `BelongsToRelationPredicate` (dynamic) as the inferrer dictates. No model is loaded on the query
  path — today `getWhitelistedParents()` fetches models only to read their ids.
- **No `ParentRefValue`.** A selection of parent records is a `ChoiceValue` like any other; the
  table/id structure lives in the choice objects.
- **Behaviour change:** an intrinsic Archive filter with `elm_useWhitelistForOptionsOnly` set used to
  filter by the whole whitelist; it now applies no condition. The combination is meaningless — an
  intrinsic filter shows no options.

**Config ownership** per §4.1: `whitelistParents`, `groupWhitelistParents`,
`useWhitelistForOptionsOnly` are `elm_`; `formatLabel`, `formatEmptyOption`, `hasEmptyOption`,
`isMandatory`, `isMultiple`, `isExpanded` are `ctl_`; the old `preselect` is `value`.

### 7.5 Bools

`boolMode` picked the widget; `boolBinaryChoices` picked which two of the three logical values
(`null` = no filter, `true`, `false`) the checkbox's two states mean. Under the new model that
mapping is widget → logical value, i.e. `decode()` (§13.24):

- **`BoolValue`** carries `true` or `false`. "No filter" is no value object.
- **`CheckboxFilterControl`** (`flare_checkbox`) owns `ctl_boolBinaryChoices`:

  | setting | unchecked | checked |
  |---|---|---|
  | `NULL_TRUE` (default) | no filter | `true` |
  | `TRUE_FALSE` | `false` | `true` |

  `hydrate()` is the inverse; a value the checkbox cannot show (`false` under `NULL_TRUE`) shows
  unchecked. **`NULL_FALSE` is dropped**: it was never implemented (`normalizeValue()` treated it as
  `TRUE_FALSE`), so existing rows migrate to `TRUE_FALSE` without a behaviour change.
- **`ChoiceBoolFilterControl`** (`flare_choice_bool`) is the former `ternary` mode: a choice of
  "any / yes / no", no config.
- **`boolMode` becomes `control`:** `binary` → `flare_checkbox`, `ternary` → `flare_choice_bool`.
- **Intrinsic bool filters** store `true`/`false` in `value` via `BoolValueProvider`.

---

## 8. The value path and `FilterData`

`Filter` changes shape (§13.20):

```php
final readonly class Filter
{
    public function __construct(
        public FilterElementInterface $element,
        public string                 $type,
        public string                 $alias,
        public array                  $config = [],      // element-owned, resolved by the element's schema
        public ?FilterControlInterface $control = null,
        public array                  $controlConfig = [],  // control-owned, resolved by the control's schema
        public ?ValueInterface        $value = null,     // fixed (intrinsic) or initial value
        /* target alias, source, … unchanged until §15 */
    ) {}
}
```

**Where `Filter::$value` comes from.**
- `FilterFactory::createFromFilterModel()` resolves the element config from the `elm_` columns, the
  control from the `control` column, the control config from the `ctl_` columns, and — if the element has a value
  class — the value via its provider's `provide($model->value, $element)`. An unknown `control`, or
  one not registered for the element's value class, is a data condition (a renamed or uninstalled
  control), not a programming error; it fails the list (§8.1). Each config has its
  own resolver cache key, so the composite-key problem of the old plan does not arise.
- `FilterFactory::create()` takes `?FilterControlInterface $control`, `array $controlConfig` and
  `?ValueInterface $value` for programmatic filters, e.g.
  `value: new ChoiceValue([ContaoModelChoice::ref('tl_news_archive', 5)])`. Programmatic filter
  creation is a first-class use case going forward.

**The path.** `ListSpec::$filters` is a plain list and every `Filter` carries an alias
(`FilterFactory` generates `_.{type}_{random}` when none is given — for a filter with a control a form-valid
`{type}_{random}` instead, so it mounts), so the alias is the key
throughout:

1. `FormHarnessFactory` keys mounts by alias and hydrates each mount with
   `$filter->control->hydrate($filter->value, $scope)` as the field's `data`.
2. `FormHarness::decode()` extracts each mount's data (§3.1) and calls
   `$mount->filter->control->decode($data, $mount->scope)`. For a **submitted** form it returns an entry
   for every mount, `null` included; for an unsubmitted one, the non-null results.
   `InteractiveProjector::collectFilterData()` is deleted; this is why the aggregate is an object
   (§2.1).
3. The map travels through `ListQueryConfig::$filterValues` and the loader/context configs.
4. `ListQueryDirector` takes the value by the filter's role (§4.2):
   - **with a control:** `$values[$alias] ?? null` — only the form's values. In the interactive list
     and its aggregation the map holds the submission or, unsubmitted, the decoded initial value; in
     a `ValidationContext` or a standalone aggregation there is no map, so the initial value does not
     apply. An empty submission clears the filter.
   - **without a control:** `$filter->value`, in every context.

**`FilterData` disappears** (introduced in #46). `hasSingle()`'s submitted-vs-untouched distinction
is carried by step 2; `toArray()`'s hashing role moves to the value object's fingerprint (§9).
`FilterFormBuilder`'s `single()` vs. compound mount decision is unaffected.


### 8.1 Failure handling

The rule follows the two scopes of §4.2 (§13.31):

| what fails | bounds | behaviour |
|---|---|---|
| a filter's **element** cannot be built, **with or without a control** — its type is unknown or its config is broken (`createFromFilterModel()` throws); without a control also a non-empty stored (fixed) value that yields no readable key; two published filters of a list sharing an alias | the whole list | **fail closed**: the list fails as a whole; error logged |
| a filter's stored **control** cannot be built — its `control` names no registered control for the element's value class, or its control config is broken | the whole list | **fail closed**: the editor chose the control, and the filter must not apply without it (e.g. a "hide secret items" filter of an uninstalled bundle); error logged |
| a controlled filter's **initial value** is unreadable (any `FilterException` from its value provider) | only the initial display | **fail open**: the control is kept, the value dropped; warning logged |

The backend reports every filter issue through one inspector (`FilterInspector`), on the filter row (icon), the edit
form and the list's filter overview: a **programming error** (an `\Error` while building or checking the filter,
caught outside debug mode only, logged as critical; bug icon), a **duplicate alias** (copy-x icon; two or more
published rows sharing an alias block the list and are all marked, with one published row only the unpublished ones
are, with none no row is), a **broken** filter — one that cannot be created, or whose conditions cannot be built with
its default value (the fixed value without a control, none with one) — (bone-fracture icon) and an **ineffective**
one (engine icon). Every saved row is inspected; unpublished rows are marked muted and never count toward the
overview messages, which count published filters and filters added by the list driver.

Stale keys of deleted records are not a failure: they resolve to references that match nothing
(§3.6).

**A failed list is not an empty list.** `ListModelFilterCollector` no longer drops a broken filter
without a control; it records the failure, and the engine treats the list as failed. This is a
separate state from `AbortFilteringException`, which keeps meaning "this filter legitimately matches
nothing" (e.g. an empty selection without `elm_useWhitelistForOptionsOnly`):

- **List view:** the view exposes the failed state (e.g. `flare_list.failed`); the template renders
  a translated "the list could not be created" message instead of the entries, the empty-result
  text and the pagination. The response status stays **200** — no HTTP error code. The filter form
  may still render; it cannot repair a broken fixed filter. In `flare_listview.html.twig` the `body`
  block chooses between the `failure` and `list` blocks, and `pagination_wrapper` renders the
  `pagination` block only for a list that did not fail, so overrides of `list` and `pagination` keep
  working.
- **Reader (`ValidationContext`):** membership cannot be decided, so the entry is not rendered and
  the reader answers with a **5xx status** — today's `ReaderController::getErrorResponse()` with 500.
  A broken server config must not end up as indexed content, and a 404 would wrongly claim the entry
  is absent.
- **Debug mode** keeps today's behaviour of the controllers' `getErrorResponse()`: the exception is
  rethrown so developers see it. *(Builder's call.)*
- The asymmetry is deliberate: the list view is one content element among others on a page, so the
  page stays a 200; the reader *is* the page's content, so its failure is the page's failure.

> **Implementation note (R12/R13).** An element-level failure fails the list **regardless of `control`**: the
> element config bounds the whole list (§4.2), so dropping a controlled filter whose element is broken would
> widen it. A stored control that cannot be built fails the list too (it used to be absorbed, keeping the filter
> without control); only an unreadable initial value is absorbed in `FilterFactory::createFromFilterModel()`, which
> keeps the control and drops the value. Aliases are unique per list: `ListModelFilterCollector` records a list
> failure for a second published row with an alias already taken instead of overwriting the first, and
> `ListSpecBuilder::addFilter()` throws a `FlareException` on a taken key or alias, like `ListSpec::withFilter()`.

> **Implementation note (R5).** A **controlled** filter whose `buildContext()` fails while it carries a
> non-null value is retried **once with value `null`**. If the retry succeeds, only the value is dropped
> (the submitted or initial value was the problem) and a warning is logged. If the retry fails, or the value
> was already `null`, the element config itself is broken; because the filter's config bounds the whole list
> (§4.2) this fails **closed** like a fixed filter. `\Error`s (programming errors) are not caught and escape
> unwrapped.
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

`Filter::fingerprint()` covers element, type, alias, both configs, the control's class name and
`$this->value?->fingerprint()`. Per class:

| value | fingerprint |
|---|---|
| `BoolValue` | the bool |
| `ChoiceValue` | the sorted canonical choice keys |
| `DateRangeValue` | the two timestamps (it holds timezone-normalized `?\DateTimeImmutable`, which is fine under this rule) |
| `KeywordsValue` | the normalized phrase |

`Util\Fingerprint` is deleted, and `ValueObjectContainmentTest` shrinks to: every class in
`src/Filter/Value/` and every `#[AsFilterElement(value: …)]` is `final`, `readonly` and implements
`ValueInterface`.

---

## 10. Enforcement summary

| risk | mechanism | when it fires |
|---|---|---|
| control produces the wrong shape | narrowed `decode()` return type, read by the compiler pass | container build; `TypeError` at run time |
| value class without a (unique) provider | narrowed `provide()` return type, read by the compiler pass | container build |
| element receives the wrong type | `expectValue()` inside `buildContext()` (§3.2) | `FilterException`, at query build |
| stored `control` does not fit the element | `findTypes()` check in `FilterFactory` (§8) | degrades to intrinsic, logged |
| choice control/provider on an element without options | `instanceof ChoiceSourceContract`, plus a unit test over bundle elements | `FilterException` / test suite |
| two controls claim one name | uniqueness check in the compiler pass | container build |
| element and control config collide | `elm_` / `ctl_` column prefixes, separate configs | by construction |
| value object not final/readonly | reflection test | test suite |

---

## 11. Migration

Three kinds of change, with different constraints. Columns come only from `sql` keys in
`contao/dca/tl_flare_filter.php`; there is no schema listener. Contao runs migrations **before** the
schema diff in one `contao:migrate`.

**A. Renames** (`elm_` / `ctl_`, `formAlias` → `alias`, §4.1). `formVariant` → `control` needs no
migration: the column is v0.2-only and still empty everywhere, so dropping and re-adding it loses
nothing. A rename migration (`ALTER TABLE … CHANGE`, guarded by
`SHOW COLUMNS`) MUST land in the same commit as the DCA rename: a schema diff that sees the DCA
renamed but the column not yet renamed drops the column and adds an empty one. Renames are
mechanical and do not depend on control types, so they need not wait for step 6.

**B. Data migration** (step 6) — written last, see below. It fills the new framework columns and
the new control columns from the legacy ones, per row:

| target | source |
|---|---|
| `control` | `intrinsic = 1` → `''`; otherwise a frozen literal per element type: `flare_archive`, `flare_fieldValueChoice`, `flare_dcaSelectField`, `cfg_tags_choice` → `flare_choice`; `flare_dateRange`, `flare_calendar_current` → `flare_date_range`; `flare_search_keywords` → `flare_keywords`; `flare_bool` → `boolMode`: `binary` → `flare_checkbox`, `ternary` → `flare_choice_bool`; element types without a value class → `''` |
| `value` | copied **only where the old code read it**, so no leftover becomes a restriction: `preselect` for bool, `flare_fieldValueChoice`, `flare_dcaSelectField`, `cfg_tags_choice` (intrinsic and not); `preselect` for `flare_archive` only when `intrinsic = 0` (intrinsic Archive filters ignored it); `prefill` for `flare_search_keywords` only when `intrinsic = 1` (the form never read it). Converted to the provider's storage format: bool `true`/`false`/`null` → `1`/`0`/empty; choices → a serialized list of keys, which are already canonical (`"<table>.<id>"` for Archive, bare values/ids for the others) whether single or multiple; date types have no stored value |
| `ctl_boolBinaryChoices` | `null_false` → `true_false` (§7.5) |
| `ctl_hasEmptyOption`, `ctl_formatEmptyOption(Custom)`, `ctl_placeholder` | per element, so existing choice widgets look the same: `FieldValueChoice` single-select → empty option on; `DcaSelect`/`CodefogTagsChoice` single-select and not expanded → empty option on, the old placeholder text becomes its custom label; otherwise the placeholder stays `ctl_placeholder`; `Archive` 1:1 |

> **Implementation note (R9/R10, B table, empty-option row).** Single-selects get the empty option
> (`ctl_hasEmptyOption`) unless they are **expanded and mandatory**. This applies to `DcaSelect` and the
> Codefog tag single-selects (the earlier "not expanded" condition is widened), and to `FieldValueChoice`
> single-selects. The label: the old placeholder becomes the custom label (`ctl_formatEmptyOption` =
> custom); without a placeholder, the translation default is used, `empty_option.prompt` when
> mandatory and `empty_option.no_selection` otherwise. `FieldValueChoice` single-selects get the default
> empty-option label (`ctl_formatEmptyOption` = `''`).

**C. Retirement** (step 4): the field definitions of `intrinsic`, `boolMode`, `preselect`, `prefill`
and the `boolMode` selector / subpalette are removed. Every retired column is a source of B; its data
stays until deletes are run manually.

### Why the data migration comes last

The mapping can be frozen only once the controls exist with their final types:

1. **A migration file auto-registers the moment it exists.** `src/Migration` is not excluded from
   the PSR-4 service resource, and `autoconfigure: true` plus `AbstractMigration` earns Contao's
   `contao.migration` tag. So the file's mere presence means `contao:migrate` runs it, against control
   names that may not exist yet. It is one-shot: once `shouldRun()` has been satisfied it goes quiet,
   so wrong values stay committed and need a *second* corrective migration.
2. **Control types are permanent data.** The migration hardcodes `element type → control type` as frozen
   literals rather than resolving through `FilterControlRegistry`: a migration describes historical
   rows, and resolving through live code would let a later rename retroactively change what
   already-migrated rows meant.

(The former third reason — the undecidable `flare_bool` arm — is resolved by §7.5.)

> **Implementation note (R8).** The backfill is **run once per row** through a migration-managed marker
> column `legacyBackfilled`, not through a content-based "pending" predicate. The migration adds the column
> (default switched to `'1'` right after adding it — idempotently, on every run, so an interrupted run cannot
> leave it at `'0'` — so rows created afterwards count as done), it is not part of the
> DCA, and the schema diff's DROP of it is, like every legacy column, only run manually with the deletes.
> It replaces the type-restricted pending predicate demanded by the third `shouldRun()` rule below.

**Operating rule: migrations never delete.** `contao:migrate` is run without deletes; DROPs
proposed by the schema diff are executed manually (`--with-deletes`) only once everything is tested.
Migrations may rename (A) and fill (B) columns but never drop columns or delete rows; C only removes
the field definitions, so the old columns and their data stay until that manual step.

**The ordering hazard**, reduced by that rule. B reads the columns C retires. Under the rule they are
still present when B runs: the schema diff adds `control`/`value` without dropping anything, and
`contao:migrate` re-checks migrations afterwards, so B runs in the same command. Running deletes
before B has run would destroy the sources unrecoverably, hence:

- B MUST ship together with A and C (the v0.2 release), and deletes MUST NOT be run before it has run.
- `shouldRun()` MUST return `false` when its source columns are already absent (fail closed), and
  MUST wait for its target columns rather than create them.
- `shouldRun()` MUST key on a type-restricted predicate of rows still needing work; without the type
  restriction, element types that get no control keep the migration pending forever.

**Database compatibility.** MySQL 5.7/8 and MariaDB (InnoDB): renames use `ALTER TABLE … CHANGE`
(not `RENAME COLUMN`, which needs MySQL 8 / MariaDB 10.5.2+), with the column definition rendered
by Doctrine DBAL's platform from the introspected column, never hand-built from `SHOW COLUMNS`.
---

## 12. Sequencing

Phase 0 clears the vocabulary so the rest can be written in the target words. After that the
contract set is the entire risk; the remaining elements are mechanical. `PLAN_FILTER_FORMS.md`
merges steps 1 and 3 into one atomic cut (no pilot, no legacy adapter) and tracks the open items.

0. **Nomenclature refactor.** ✅ **Done** (#47), then revised by #48 (§2.2). Pure rename plus one new
   object, no behaviour or schema change.
1. **Contracts + the cut.** ✅ **Done** (atomic cut, tree green). Everything of the former to-do list landed in the
   target shape of this spec.
2. **DCA composition.** ✅ Done. `DcaBuilder` palette segments, `scope()`, `selector()`; `ElementDcaListener`
   resolves and invokes the control's `buildDca()`.
3. **Migrate the rest.** Merged into step 1 (atomic cut). `CodefogTagsSearchElement` has neither
   `buildForm()` nor `buildContext()` (only `buildDca()`) — it needs `value: null` and nothing else.
4. **Schema changes** ✅ Done: per §11: the `elm_`/`ctl_` renames with their rename migration (§11 A), the
   new `value` column, and the drops of §11 C. No data migration yet.
5. **Fold `FilterData`** ✅ Done: out per §8.
6. **Data migration** ✅ Done: per §11 B. Written last, once the controls and their types exist. Subject to
   the ordering hazard §11 records: it reads the columns step 4 drops, so it must land before any
   environment with real rows applies the schema diff.

Targeting (§15) is a separate piece of work after this one and before the v0.2 release.

---

## 13. Resolved decisions

Recorded here because each one closes a branch the design could otherwise have taken.

1. **`ArchiveFilterElement` shares the choice port** with `FieldValueChoice`, `DcaSelectField` and
   `CodefogTagsChoice`, all served by one generic choice control. No second port is needed (§7.4).
2. **No compound case needs per-child capability negotiation.** `DateRangeFilterElement` needs
   nothing from the element at all.
3. **Control-owned field labels stay in the central language files**
   (`contao/languages/{en,de}/tl_flare_filter.php`). Controls declare fields and palette segments; they
   do not declare translations. This keeps `tl_flare_filter`'s label surface in one place, consistent
   with the shared-column reality of §6.3.
4. **`intrinsic`-branching `buildDca()` methods collapse into the §6 palette segments.**
   `CalendarCurrentFilterElement::buildDca()` already appends `{form_legend},isLimited` only when not
   intrinsic — hand-rolling exactly the split §6 formalises. Under the target model that branch
   disappears. (`isLimited` is **element**-owned, `elm_isLimited`: it clamps the range and skips the
   filter in a `ValidationContext`, i.e. it changes matching; the date-range control learns the bounds through
   `DateBoundsContract`, §3.5.) The same applies to
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
8. **Controls read model data, always** (§3.1). The view-vs-model choice was only ever needed because
   choice objects were badly chosen.
9. **Choices are objects that name their key** (§7.2): `ChoiceObjectInterface` with
   `getChoiceScope()`, `getChoiceLocalKey()` and `getLabelParameters()`, `ContaoModelChoice::from()` /
   `::ref()`, `ValueChoice`. Custom choice semantics mean a custom choice class, not a
   translation step.
10. **Value objects stay at widget level** (§2). Domain interpretation happens in `buildContext()`.
    `ChoiceValue` carries choice objects; `ParentRefValue` and `valueFromChoiceKeys()` are gone.
11. **A control's value class is its narrowed `decode()` return type** (§3.1), read by reflection at
    compile time. No `getValueClass()`, no `@template`, no `value:` on the attribute.
12. **No declarative `requires` mechanism** (§3.5). Ports are plain interfaces checked with
    `instanceof` where used: `ChoiceSourceContract` required, `DateBoundsContract` optional. A
    declarative capability mechanism can be added later without breaking anything; removing one that
    third parties use could not.
13. **`decode()` takes data, not a `FormInterface`** (§3.1). Extraction lives once, in
    `FormHarness::decode()`.
14. **Control registration mirrors element registration** (§3.4): eager registry, `addMethodCall`,
    public alias. No `ServiceLocator`, no `default` / `findDefaultName()`, no "every value class has a
    control" check, no ordering constraint. The migration uses frozen literals, so nothing needs a
    default control.
15. **Hashing is for in-request caching only** (§9). Value objects state their identity through
    `fingerprint(): string`; `Util\Fingerprint` and the strict containment rule are gone.
16. **`FilterElementBuildingEvent` and `FilterElementBuiltEvent` are removed**, with their
    `flare.filter_element.{type}.building|built` aliases. An event is public API whose payload would
    pin `FilterScope`, the value and `FilterContextBuilder` while they are new; adding an event later
    breaks nothing. The use cases are covered: skip or add filters via `ListDriverBuildListEvent` /
    `flare.list_driver.{type}.build_list` (formerly `ListBuildEvent` / `flare.list.{type}.build`), preset values via programmatic `Filter::$value`, extra conditions via
    another filter or a custom element. Lost: a per-execution decision depending on the engine
    context, which needs a custom element. The 0.1 events `FilterElementInvokingEvent` /
    `FilterElementInvokedEvent` therefore have no direct successor; the migration guide says so.
17. **`flare.choices_builder` is dropped.** The attribute was write-only (set in four `buildForm()`s,
    read nowhere); `ChoiceFilterControl` owns its `ChoicesBuilder`. Custom form themes that read it
    from `form.vars` lose it — release note.
18. **Each value class has one authoritative `ValueProvider` service** (§3.6): `provide()` turns the
    stored column into the value object (its narrowed return type registers the provider),
    `buildDcaField()` renders the column in the backend. Not static methods on the value class:
    providers need services, and value objects stay free of Contao and DI. Named *provider*, not
    *builder*, because builders in this codebase are mutable accumulators.
19. **Element and control config are separated down to the column**: `elm_` / `ctl_` prefixes, no prefix
    for framework columns (§4.1). Duplicated base names are accepted. JSON storage waits for Contao 5
    (§6.5).
20. **`Filter` carries two configs**, `$config` (element) and `$controlConfig` (control), each with its own
    schema and transformers. A control never reads element config; it uses ports.
21. **The backend evaluates filters in a `ConfigurationContext`** (§6.4), so the choice port serves
    frontend and backend alike.
22. **The URL carries a choice's scope only when the option list needs it** (§7.2), decided per list
    by `ChoicesBuilder`; storage always uses the canonical key.
23. **One `ChoiceFilterControl` palette for all four choice elements**, with a migration that keeps
    existing widgets looking the same; the empty option is a real option with its own label, and
    `ctl_placeholder` is always the HTML attribute (§7.3).
24. **Bools: the checkbox mapping belongs to the control** (§7.5). `NULL_FALSE` is dropped;
    `boolMode` becomes `control`.
25. **One value column; the control decides its role** (§4.2, §8): without a control the value is
    fixed and applies everywhere; with a control it is the initial value and applies only through the
    form.

26. **Framework columns are `type`, `control`, `alias`** (§5.1): `formVariant` → `control`,
    `formAlias` → `alias`, `FilterModel::getFilterFormName()` → `getFilterAlias()`. A `filter`
    prefix was considered and rejected; within `tl_flare_filter` it is redundant, as it is for
    `type`.
27. **The per-filter presentation strategy is a `FilterControl`** (§2.1), not a `FilterForm`.
    "Form" names only the Symfony level (`FormHarness`, `FilterFormBuilder`); the strategy above it
    is a control. Column `control`, prefix `ctl_`, directory `src/Filter/Control/`, attribute
    `#[AsFilterControl]`. The SPEC/PLAN file names keep "FILTER_FORMS" to avoid churn in links.
28. **Builder's calls from the second review**: Codefog tags use `ValueChoice` (bare ids, as stored);
    the choice `value` field is always multiple; the data migration copies `preselect`/`prefill` only
    where the old code read them; `elm_groupWhitelistParents` keeps its inner blob keys;
    `DcaBuilder::selector()` is built in step 2.
29. **Stored values are resolved without a scope** (§3.6): `provide(mixed $stored,
    FilterElementInterface $element)` in `FilterFactory`, with `ChoiceSourceContract::choiceFromKey()`
    building reference choices. A `Filter` carries no DCA data, the backend scope has no cycle, and
    intrinsic choice filters cost no option load.
30. **Initial values never restrict the whole list** (§4.2). The `ValidationContext` checks
    membership in everything the controls can reach; initial values only pre-filter the initial
    display. Bool filters with a control and a preselect therefore stop restricting reader pages —
    a correction by this definition, noted in the release notes.
31. **Fail closed for what bounds the whole list, fail open for what only shapes the initial
    display** (§8.1). A stored control the editor chose bounds the whole list: if it cannot be resolved, the list
    fails. A list that fails closed shows an explicit "could not be created" message —
    never "no entries found" — and keeps HTTP status 200; a reader that cannot validate its entry
    answers 5xx, so broken config is never indexed.
32. **Event names state whose type they carry** (review of PR #49): `flare.filter_element.{type}.*` always
    carries the registered element type, `flare.filter_control.{type}.*` the registered control type
    (`Filter::$controlType`; `build_form` switched from the element type) and `flare.list_driver.{type}.*`
    the list driver type (`ListDriverBuildDcaEvent`, `ListDriverConfigureTransformersEvent`,
    `ListDriverBuildListEvent` — `build_list`, because `ListSpec` is the list), so further list components
    can get their own prefix without another rename. Hook event classes are `{Component}{Hook}Event` after
    the hook they follow (`FilterControlBuildFormEvent`, `FilterElementBuildDcaEvent`,
    `FilterElementConfigureTransformersEvent`); the named event keeps a short suffix (`dca`,
    `transformers`) unless it would be unclear (`build_list`, `build_form`). The query events are left
    alone until the query architecture is reworked.
33. **Elements and controls share `FilterComponentInterface`** (review of PR #49). Both are registered
    by type and own their config, so `FilterOptionsResolver` and `FilterTransformerResolver` type against
    the common interface instead of a union. Their events are separate classes per component (the DCA
    events, because the control's builder is scoped to its palette segment; the transformer events, so a
    listener receives a typed `$element` or `$control`).
34. **Config resolution has two generic cores and thin domain wrappers** (review of PR #49).
    `Config\SchemaResolver` (options) and `Config\SourceTransformer` (transformers) memoize per
    `"{type}@{class}"` key; `FilterOptionsResolver`/`FilterTransformerResolver` and
    `ListOptionsResolver`/`ListTransformerResolver` add only the contract check, their event and the
    error wrapping. Every failure — schema violation or a throwing transformer, `\Error`s included,
    since stored data typically fails with a `ValueError`/`TypeError` — becomes a `FilterException`
    (filters) or `FlareException` (lists) with the record as source (`$origin`), so a broken record
    fails the list closed instead of escaping the collector. The source-class → transformer map is
    `Config\TransformerMap` (formerly `TransformerResolver`) with `set(sourceClass, transformer)`, which replaces, and
    `find(source)`, which matches by exact class first, then by inheritance (formerly `for()` and `resolve()`).

---

## 14. Remaining unknowns

1. ~~**`serialize()` stability for readonly value objects**~~ — measured in step 0 (probe commit
   06fc013 on `feat/filter-types`, not carried over): `serialize()` is not a pure value function
   over an object graph (repeated objects become back-references). **Moot** since §13.15: value
   objects state their identity through `fingerprint()`, and only in-request caching is required.
2. ~~`DcaBuilder::selector()`~~ — resolved: it has consumers and is built in step 2 (§6.3).
3. ~~`ArchiveFilterElement::buildPreselectData()`~~ — resolved: hydration goes through
   `ChoiceFilterControl::hydrate()` from `Filter::$value` (§8), backend options through the port (§6.4).
4. **Whether `ListSpec::$filters` should become a collection object.** The naming side is settled
   (§13.5); the alias is the key everywhere (§8). Open only whether the plain list deserves its own
   type. Not needed for this refactor.
5. ~~Form-time vs. query-time context~~ — resolved, §13.6.
6. ~~How the compiler pass learns a control's value class~~ — resolved, §13.11 and §13.14.
7. ~~The element events~~ — resolved, §13.16.
8. ~~`DateRangeValue` holding `\DateTimeImmutable`~~ — resolved by the relaxed rule, §9.
9. ~~Archive's stored preselect keys vs. its static-mode choice keys~~ — resolved by canonical keys
   for storage and per-list short keys for URLs, §13.22.
10. **The exact frontend label of the empty option and of the bool choice control** (`flare_form`
    translation keys) — builder's call; keep today's keys where they exist.
11. ~~Unresolvable or unknown at run time~~ — resolved, §8.1 and §13.31.

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
