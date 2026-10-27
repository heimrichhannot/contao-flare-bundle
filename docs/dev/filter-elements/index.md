# Custom Filter Elements

A filter element owns **what rows match**. It owns:

- its **config schema** — `configureOptions()` + `configureTransformers()`
- its **backend DCA** representation — `buildDca()`
- the **translation** of its config and the filter's value into query fragments — `buildContext()`

How the user *supplies* the value (checkbox, select, date inputs, ...) is not the element's concern: that is the
job of a [filter control](../filter-controls.md), which the editor picks per filter record. The two meet in a
typed **value object** (`BoolValue`, `ChoiceValue`, `DateRangeValue`, `KeywordsValue`).

Elements never write SQL themselves — that is the job of reusable [predicates](../predicates.md), which
elements add from `buildContext()`. For the filter elements that ship with FLARE, see the
[built-in filter elements reference](../../reference/filter-elements.md).

```
FilterControl   how the user supplies the value; turns submitted form data into a value object
Value object    what the user said, typed
FilterElement   what rows match: applies its config and the value object, emits predicates
Predicate       turns bound arguments into SQL conditions
```

## 1. Registration (`#[AsFilterElement]`)

Annotate your class with `#[AsFilterElement]` and extend `AbstractFilterElement`:

```php
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\Element\AbstractFilterElement;
use HeimrichHannot\FlareBundle\Filter\Value\KeywordsValue;

#[AsFilterElement(type: 'app_city', isTargeted: true, value: KeywordsValue::class)]
class CityFilterElement extends AbstractFilterElement
{
    // ...
}
```

### Attribute Parameters:
- **`type`**: Unique identifier of the element — the value stored in `tl_flare_filter.type`.
- **`isTargeted`**: (Optional) Boolean. If true, the filter respects the table alias configured on the
  filter record (see [target aliases](../../spec/filtering.md#5-target-aliases)).
- **`value`**: (Optional) The **value class** the element consumes in `buildContext()`. The editor can choose
  among all [controls](../filter-controls.md) that produce this class, and the backend shows the matching
  `value` field. Without it the element has no runtime value and is always intrinsic (e.g. `flare_published`,
  `flare_simpleEquation`). An element declaring a value class for which no
  [value provider](../filter-controls.md#4-value-providers) exists fails the container build.

:::info[Changed in v0.2]

`palette`, `formType` and `method` were removed: the palette is built in [`buildDca()`](#4-backend-dca-builddca),
and the query translation always happens in [`buildContext()`](#5-query-translation-buildcontext). There is no
`buildForm()` on elements any more, and no `IntrinsicContract`: an element without a value class is
intrinsic, and for every other element the editor leaves the *control* blank to apply it with a fixed value.

:::

## 2. The Interfaces

Every element implements `FilterElementInterface`, which has a single method — the builder (the output channel)
comes first, then the scope, then the value:

```php
public function buildContext(
    FilterContextBuilderInterface $builder,
    ElementScope                  $scope,
    ?ValueInterface               $value,
): void;
```

Elements with configuration additionally implement [`OptionsContract`](../contracts/index.mdx) and
[`TransformerContract`](../contracts/index.mdx):

```php
public function configureOptions(OptionsResolver $resolver): void;

public function configureTransformers(TransformerMap $transformers): void;
```

`AbstractFilterElement` implements all of the above, plus
[`IsSupportedContract`](../contracts/is-supported-contract.md) (returns `true`) and
[`DcaContract`](../contracts/dca-contract.md) (no-op). Only `configureOptions()` and the protected
`transformFilterModel()` hook are abstract; `buildDca()` and `buildContext()` default to no-ops. It also
provides `expectValue()` (see [below](#5-query-translation-buildcontext)).

### The `ElementScope` and the `FilterContext`

A filter is applied in two stages:

1. **`ElementScope`** (`HeimrichHannot\FlareBundle\Filter\ElementScope`, final readonly) is *value-free*: the
   situation an element hook runs in. Everything that happens before a value exists receives a scope —
   [capability ports](../filter-controls.md#3-capability-ports) get an `ElementScope`, controls a
   [`ControlScope`](../filter-controls.md#2-the-interface) — and so does `buildContext()`.

   | Member | Description |
   |---|---|
   | `$scope->get('key')` | The element's **own** resolved config value (validated against `configureOptions()`). A key the schema does not define throws `UndefinedConfigKeyError`; an option defined without default and not set reads as `null` |
   | `$scope->has('key')` | Whether the element's own config holds a value for the key |
   | `$scope->list` | The [`ListSpec`](../../spec/specifications.md) the filter belongs to, e.g. `$scope->list->dc` |
   | `$scope->filter` | The [`Filter` value object](../../spec/filtering.md#1-the-filter-value-object), e.g. `$scope->filter->control?->config->get('label')` to read the control's config |
   | `$scope->engineContext` | The engine context (`ContextInterface`) — interactive, validation, aggregation, or configuration (the backend) |

2. **`FilterContext`** is the element's *product* for one invocation: the formula of predicates the element
   produced for a given value. Elements contribute to it through the `FilterContextBuilderInterface` in
   `buildContext()`; only the query director builds one.

:::warning A misspelled key is a programming error

`UndefinedConfigKeyError` extends `\Error`, not `\Exception`, so no filter failure handling catches it: the filter
is not retried without its value, not skipped, and the list does not degrade to its "could not be created" state —
the request fails (FLARE catches `\Exception`, never `\Throwable`, so the error reaches you unwrapped), and the typo
surfaces during development instead of silently widening the list.

:::

## 3. Config Schema (`configureOptions` + `configureTransformers`)

`configureOptions()` declares the element's canonical config with Symfony's `OptionsResolver`.
`configureTransformers()` declares how stored sources translate onto that schema: it registers one
transformer per source class on a `TransformerMap`. Everything downstream — `buildDca()`,
`buildContext()` — works with the resolved config, never with raw DCA rows, which is what makes filters
equally usable from the database, PHP, and Twig.

Both methods are declarative, memoizable setup: they are run once per element class, not per filter.

If either fails — a config that violates the schema, or a transformer that throws, e.g. a `ValueError` while
parsing an enum from a broken record — FLARE wraps the error in a `FilterException` that names the element and
the record (`tl_flare_filter.42`). For an element this fails the list closed; for a control it drops the
control (see [failure handling](../../spec/filtering.md#6-failure-handling)).

`AbstractFilterElement` already registers a transformer for `FilterModel` (a stored `tl_flare_filter`
record) that delegates to the abstract `transformFilterModel()` hook — for most elements, that hook is
all you implement. All deserialization, casting, and enum parsing belongs there. **Element columns carry the
`elm_` prefix** — a column belongs to the element if its value changes *which rows match*; columns that only
change what the user sees or can submit belong to the control (`ctl_`):

```php
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Model\FilterModel;

public function configureOptions(OptionsResolver $resolver): void
{
    $resolver->define('field')->default('city')->allowedTypes('string');
}

protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
{
    $config->set('field', $model->elm_fieldGeneric ?: 'city');
}
```

Third parties can register transformers for additional source classes — or replace the `FilterModel`
one — by listening to the [`FilterElementConfigureTransformersEvent`](../events.md#filterelementconfiguretransformersevent)
(`flare.filter_element.{type}.transformers`).

## 4. Backend DCA (`buildDca`)

Declare the element's palette and field tweaks for `tl_flare_filter`. The element contributes the
`filter_legend` segment; the chosen control appends its own `control_legend` segment after it:

```php
public function buildDca(DcaBuilderInterface $builder, DcaContext $context): void
{
    $builder->palette('{filter_legend},elm_fieldGeneric');
}
```

See the [Backend DCA Building guide](../dca-builder.md) for the full API, including per-field option
callbacks and reacting to the record's state.

## 5. Query Translation (`buildContext`)

`buildContext()` turns config and the filter's value into one or more **predicate calls** — it does not
write SQL:

- `$scope->get('key')` reads the resolved element config.
- `$value` is a value object of the element's declared value class — or `null` if the filter has no value
  (nothing submitted, empty selection, no fixed value). Narrowing the parameter type is not possible in PHP;
  check it with `$this->expectValue($value, KeywordsValue::class)`, which passes `null` through and throws a
  `FilterException` for a value of another class.
- `$builder->addPredicate(SomePredicate::class, $options, ?$targetAlias)` records a call; the options are
  validated against the predicate's own schema.
- `$builder->abort()` stops filtering entirely and yields an empty result set (e.g. an empty selection that
  must not widen the list).

```php
public function buildContext(
    FilterContextBuilderInterface $builder,
    ElementScope                  $scope,
    ?ValueInterface               $value,
): void {
    if (!$value = $this->expectValue($value, KeywordsValue::class)) {
        return; // no value — this filter adds no conditions
    }

    $builder->addPredicate(SimpleEquationPredicate::class, [
        'operand_left' => $scope->get('field'),
        'operator' => SqlEquationOperator::EQUALS,
        'operand_right' => $value->keywords,
    ]);
}
```

**Where the value comes from.** A filter *with* a control receives what the control decoded from the
submitted form (or, before submission, the initial value). A filter *without* a control receives its fixed
`Filter::$value` in every context. See [Intrinsic Filters](#8-intrinsic-and-initial-values).

## 6. Complete Example

```php
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Filter\Element\AbstractFilterElement;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Predicate\SimpleEquationPredicate;
use HeimrichHannot\FlareBundle\Filter\Value\KeywordsValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AsFilterElement(type: 'app_city', isTargeted: true, value: KeywordsValue::class)]
class CityFilterElement extends AbstractFilterElement
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('field')->default('city')->allowedTypes('string');
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
    {
        $config->set('field', $model->elm_fieldGeneric ?: 'city');
    }

    public function buildDca(DcaBuilderInterface $builder, DcaContext $context): void
    {
        $builder->palette('{filter_legend},elm_fieldGeneric');
    }

    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
        if (!$value = $this->expectValue($value, KeywordsValue::class)) {
            return;
        }

        $builder->addPredicate(SimpleEquationPredicate::class, [
            'operand_left' => $scope->get('field'),
            'operator' => SqlEquationOperator::EQUALS,
            'operand_right' => $value->keywords,
        ]);
    }
}
```

The editor can now pick the built-in `flare_keywords` control for this element (both produce a
`KeywordsValue`) — no element code for the input. Because `elm_fieldGeneric` is an existing column, no
schema change is needed; a new column needs an `elm_`-prefixed `sql` entry in `tl_flare_filter`.

## 7. Choice and Date Elements: Capability Ports

Elements whose value is picked from a server-side option set implement `ChoiceSourceContract`
(`buildChoices(ChoicesBuilder, ElementScope)`, which fills a builder the caller provides, and
`choiceFromKey(string)`); date-range elements may implement `DateBoundsContract` (`getDateBounds(ElementScope)` returns a
`DateBounds`). The generic controls and the value providers use these ports instead of reaching into
the element. See [Capability Ports](../filter-controls.md#3-capability-ports).

An element that can be configured into restricting nothing implements `IneffectiveFilterContract`
(`getIneffectiveReason(Filter): ?TranslatableInterface`). It returns why the filter has no effect, or `null`; the
backend then marks the filter with an engine icon in the filter list, counts such filters in a message above that
list, and shows the reason on the filter's edit form. The runtime behavior is unchanged. `ArchiveFilterElement` uses it
for a filter with neither a control nor a value.

## 8. Intrinsic and Initial Values

There is no `intrinsic` checkbox any more. The **`control`** field of a filter record decides:

| `control` | `value` is | Initial display of the list | Whole list (readers, validation) |
|---|---|---|---|
| blank (**intrinsic**) | the **fixed value** | restricted | restricted |
| a control | the **initial value** | pre-filtered, the user can change it | **not applied** |

Elements without a value class (`value: null`), e.g. `flare_published`, `flare_belongsToRelation`,
`flare_simpleEquation`, are always intrinsic: they never have a control and `buildContext()` receives `null`.

Only fixed values and the elements' own config (e.g. a whitelist) bound the **whole list** — everything the
user can reach through controls. A bool filter with a control therefore no longer restricts reader pages.

`flare_archive` shows the difference most clearly. **Without a control** its `value` is the restriction: the
editor picks it from **all** parent records (every record of the static ptable, or of every table the dynamic
`ptable` column holds), and the filter applies `pid IN (value)`; without a value it restricts nothing. The parent
whitelist and "whitelist for options only" belong to the control: **with a control** the whitelist provides the
options and bounds the value (`value ∩ whitelist`), and "options only" decides whether no selection means the whole
whitelist or no restriction. Without a control the element ignores the whitelist settings, whatever their source —
a transformer, `FilterFactory::create()` or a listener.

## 9. Programmatic Filters Without a Backend Record

`Filter` (`HeimrichHannot\FlareBundle\Filter\Filter`) is an immutable value object holding a filter element
instance with its canonical config, an optional control with its config, and an optional value. For one-off
filters that reuse a registered element, create one through the `FilterFactory` service
(`HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory`) — it resolves registered type aliases and control
names to their services and records the alias as the filter's `type` (what the
`flare.filter_element.{type}.*` named events key on):

```php
use HeimrichHannot\FlareBundle\Filter\Element\BooleanFilterElement;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;

// Without a control the value is fixed: only featured entries.
$filter = $this->filterFactory->create(
    element: BooleanFilterElement::TYPE,
    config: ['field' => 'featured'],
    value: new BoolValue(true),
);
```

```php
// With a control the value is only the initial value of the user-changeable input.
$filter = $this->filterFactory->create(
    element: ArchiveFilterElement::TYPE,
    alias: 'archive',
    config: [/* ... */],
    control: 'flare_choice',
    value: new ChoiceValue([ContaoModelChoice::ref('tl_news_archive', 5)]),
);
```

A filter with a control **needs an `alias`**: it names the form field, which must stay the same across requests,
so `create()` throws a `FlareException` without one. A filter without a control may omit it; `create()` then
generates `_.{type}_{random}`, which is never mounted as a form field. `create()` also throws a `FlareException`
if the control is not registered for the element's value class, or the value is not of its class. It also accepts a `FilterElementInterface` instance — as does `new Filter(element: ..., type:
..., alias: ...)` directly — for elements that are not registered as a service at all (see the anonymous-class
example under [Consuming Predicates](../predicates.md#4-consuming-predicates)). Such filters fire no named
events.

The `config` array must satisfy the element's `configureOptions()` schema — no DCA row or transformer is
involved. Use the `with*()` methods (`withValue()`, `withControl()`, `withTargetAlias()`) to derive variants —
`Filter` is immutable, so they return new instances.

Add programmatic filters to a list while it is being built — from a list driver's `buildList()` hook or a
[`ListDriverBuildListEvent`](../events.md) listener via `ListSpecBuilder::addFilter()` — or derive a new spec from an
existing one via `ListSpec::withFilter()`. Both **throw a `FlareException` on a duplicate alias** (and
`addFilter()` also on a taken key); a filter is never silently replaced — remove it first with
`removeFilter()` / `withoutFilter()`. See [Custom List Drivers](../list-types/index.md) for the build lifecycle.

A broken element config — with or without a control — or a broken fixed value bounds the whole list, so it
fails the list: the list view
renders the translated `list.failed` message with HTTP 200 and the reader answers 500. A filter *with* a
control that fails at query time with a value is retried once without it: if that succeeds only the value is
dropped (warning logged); if the retry fails too, the element config is broken and the list fails closed. See
[failure handling](../../spec/filtering.md#6-failure-handling).
