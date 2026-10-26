---
title: Filter Controls
sidebar_position: 3
---

# Custom Filter Controls

A **filter control** is the per-filter presentation strategy: it decides *how the user supplies the value* and
turns the submitted form data into a typed **value object**. Which rows then match is decided by the
[filter element](./filter-elements/index.md), which only ever sees that value object.

"Form" names only the Symfony level in FLARE (`FormHarness` — the root form of a list, `FilterFormBuilder` —
the collect-only builder handed to a control, and the *mount*, the node a filter occupies in the root form).
The strategy above it is a **control** ("Bedienelement" in the German backend).

Controls are registered **one-directionally**: a control binds to a *value class*, never to an element type.
The controls an editor can choose for a filter are all controls whose value class equals the element's
declared value class. Adding a new control therefore never requires touching an element.

## 1. Registration (`#[AsFilterControl]`)

Implement `FilterControlInterface` (or extend `AbstractFilterControl`) and annotate the class:

```php
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterControl;
use HeimrichHannot\FlareBundle\Filter\Control\AbstractFilterControl;

#[AsFilterControl(type: 'app_search_box')]
class SearchBoxFilterControl extends AbstractFilterControl
{
    // ...
}
```

- **`type`**: the stable identifier persisted in `tl_flare_filter.control`, like the `type` of a filter element.
  Prefix third-party types; the empty string (the *intrinsic* state) and `default` are reserved. Defaults to a
  type derived from the class (suffixes `FilterControl` / `Control` stripped). The bundled controls expose it
  as the `TYPE` constant (e.g. `ChoiceFilterControl::TYPE`).
- The attribute is repeatable and tags the service `flare.filter_control` (service alias
  `flare.filter_control.{type}`). Two controls claiming one type fail the container build.
- There is **no value class argument**: it is the narrowed return type of `decode()`, read by reflection by the
  compiler pass. A control returning a value of the wrong class fails the container build or throws a
  `TypeError` — never a silently wrong query.

The controls FLARE ships:

| Type | Class | Value class | Presentation |
|---|---|---|---|
| `flare_choice` | `ChoiceFilterControl` | `ChoiceValue` | Select, radios or checkboxes over the element's choices (single / multiple / expanded, optional empty option) |
| `flare_checkbox` | `CheckboxFilterControl` | `BoolValue` | A single checkbox (checked = true; unchecked = no filtering, or false with `ctl_boolBinaryChoices` = `true_false`) |
| `flare_choice_bool` | `ChoiceBoolFilterControl` | `BoolValue` | Yes / no / any select |
| `flare_date_range` | `DateRangeFilterControl` | `DateRangeValue` | From / to date inputs (honours `DateBoundsContract`); `ctl_label` labels the pair |
| `flare_keywords` | `KeywordsFilterControl` | `KeywordsValue` | Free-text search phrase |

## 2. The Interface

```php
public function buildForm(FilterFormBuilderInterface $builder, FilterScope $scope): void;

public function decode(mixed $data, FilterScope $scope): ?SomeValue;   // narrow the return type!

public function hydrate(?ValueInterface $value, FilterScope $scope): mixed;   // narrow it, too
```

- **`buildForm()`** declares the filter's fields on the collect-only builder. A **single-field** control calls
  `$builder->single(TextType::class, [...])`; the field is mounted flat on the root form under the filter's
  alias (`form[alias]=x`). A **multi-field** control `add()`s children under local names (`from`/`to`); they
  mount as a compound sub-form (`form[alias][from]=x`); `$builder->compound(['label' => ...])` sets form
  options of that sub-form (by default it has no label and is not required). Declaring both is not supported. A
  control that declares nothing is not mounted.
- **`decode()`** turns the mount's submitted data into the control's value object, or `null` to contribute
  nothing (an empty submission clears the filter). Implementations MUST narrow the return type to one concrete
  value class.
- **`hydrate()`** is the inverse: the widget data for a value of the control's class. It pre-fills an
  unsubmitted form from the filter's [initial value](./filter-elements/index.md#8-intrinsic-and-initial-values).
  Narrow its return type to the widget data (e.g. `?string`) and document the `$data` shape `decode()` receives
  with a `@param`, as the bundled controls do.

All three receive the value-free [`FilterScope`](./filter-elements/index.md#the-filterscope-and-the-filtercontext):
`$scope->filter->controlConfig` is the resolved control config, `$scope->filter->element` the element.

A control owns its own config and its own slice of the backend palette, through the same optional contracts as
elements — `OptionsContract` (`configureOptions()`), `TransformerContract` (`configureTransformers()`) and
`DcaContract` (`buildDca()`). `AbstractFilterControl` mirrors `AbstractFilterElement`: `configureOptions()` and
`transformFilterModel(ConfigBuilder, FilterModel)` are abstract, and its `configureTransformers()` registers the
latter for `FilterModel` records. **Control columns carry the `ctl_` prefix** (`ctl_label`, `ctl_placeholder`,
`ctl_isMandatory`, `ctl_isMultiple`, `ctl_isExpanded`, `ctl_hasEmptyOption`, `ctl_formatLabel(Custom)`,
`ctl_formatEmptyOption(Custom)`, `ctl_boolBinaryChoices`); a field belongs to the control if it changes only
*what the user sees or can submit*. The control's palette is appended after the element's, under the
`control_legend`.

### Example

```php
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterControl;
use HeimrichHannot\FlareBundle\Filter\Control\AbstractFilterControl;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterScope;
use HeimrichHannot\FlareBundle\Filter\Value\KeywordsValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AsFilterControl(type: 'app_search_box')]
class SearchBoxFilterControl extends AbstractFilterControl
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('label')->default(null)->allowedTypes('string', 'null');
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
    {
        $config->set('label', $model->ctl_label ?: null);
    }

    public function buildDca(DcaBuilderInterface $builder, DcaContext $context): void
    {
        $builder->palette('{control_legend},ctl_label');
    }

    public function buildForm(FilterFormBuilderInterface $builder, FilterScope $scope): void
    {
        $builder->single(SearchType::class, [
            'label' => $scope->filter->controlConfig['label'] ?: false,
            'required' => false,
        ]);
    }

    /**
     * @param string|null $data The submitted search phrase.
     */
    public function decode(mixed $data, FilterScope $scope): ?KeywordsValue
    {
        return KeywordsValue::tryFrom($data);
    }

    public function hydrate(?ValueInterface $value, FilterScope $scope): ?string
    {
        return $this->expectValue($value, KeywordsValue::class)?->keywords;
    }
}
```

This control is immediately selectable for every element declaring `value: KeywordsValue::class`.

## 3. Capability Ports

A control that needs something from the element does not reach into it; the element implements an explicit
**port** (`HeimrichHannot\FlareBundle\Contract\FilterElement\`), and the control checks `instanceof`:

- **`ChoiceSourceContract`** — the element's value is picked from a server-provided option set.
  `buildChoices(ChoicesBuilder $choices, FilterScope $scope): void` adds the choices to a builder the caller
  created through `ChoicesBuilderFactory` (for the frontend widget *and* the backend `value` field), `choiceFromKey(string): ?ChoiceObjectInterface` turns a canonical key back into a reference
  choice without database access. `ChoicesBuilder::add()` takes **choice objects**
  (`ContaoModelChoice`, `ValueChoice`) which carry their canonical key. Used by `flare_choice` and the
  choice value provider.
- **`DateBoundsContract`** — `getDateBounds(FilterScope): DateBounds` supplies min/max for date inputs:
  `DateBounds` carries `?DateTimeImmutable $min` and `$max` (exclusive); a null bound is open. Without it the date range control renders unbounded.

A control used with an element that lacks its port throws a `FilterException`. Elements that declare a choice
value class but do not implement the port are caught by a unit test over the bundle's elements.

## 4. Value Providers

A **value provider** (`ValueProviderInterface`, tag `flare.value_provider`, `ValueProviderRegistry`) is the
authoritative service for one value class, keyed by the narrowed return type of `provide()`:

```php
public function provide(mixed $stored, FilterElementInterface $element): ?ChoiceValue;

public function buildDcaField(DcaFieldBuilderInterface $field, DcaContext $context): void;
```

- `provide()` turns the content of the `tl_flare_filter.value` column into a value object (fixed or initial
  value — see [Intrinsic and Initial Values](./filter-elements/index.md#8-intrinsic-and-initial-values)). An
  unreadable non-empty value throws a `FilterException`.
- `buildDcaField()` renders the backend `value` field for elements of this class.

There is **exactly one provider per value class**; an element declaring a value class without a provider
fails the container build. Providers are services (inject a translator, `ChoicesBuilderFactory`, ...); value
objects stay plain values. Bundled: `BoolValueProvider`, `ChoiceValueProvider`, `DateRangeValueProvider`,
`KeywordsValueProvider`. `DateRangeValueProvider` stores two *time expressions* — a timestamp, a date such as
`2026-10-26`, or a relative `strtotime()` expression such as `today` or `+1 week` — and resolves them whenever the
value is provided, so relative bounds move with the current time. A new value class therefore needs a value object (`final readonly`, implementing
`ValueInterface` with a `fingerprint()`), a provider, and at least one control.

Stale references fail closed: a stored key whose record was deleted still yields a reference that matches
nothing, so the filter never silently widens the list.

## 5. Events

Like elements, controls have their own [backend](./events.md#filtercontrolbuilddcaevent) and
[transformer](./events.md#filtercontrolconfiguretransformersevent) events, named after the **control** type:

- `flare.filter_control.{type}.dca` (`FilterControlBuildDcaEvent`) — dispatched after the control's `buildDca()`;
  its builder is scoped to the control's palette segment.
- `flare.filter_control.{type}.transformers` (`FilterControlConfigureTransformersEvent`) — extend or replace the control's
  transformer map.

After a control declared its fields on the per-filter builder — and before they are mounted onto the root
form — a [`FilterControlBuildFormEvent`](./events.md#filtercontrolbuildformevent) is dispatched, also as
`flare.filter_control.{type}.build_form` (`{type}` is the *control* type). Listeners can add, remove or replace
children, adjust `single()`, or `cancel()` the mount.

The former element events `FilterElementBuildingEvent` / `FilterElementBuiltEvent` no longer exist; see
[Removed in v0.2](../removed-in-v0.2.md).

## 6. Failure Behaviour

- **Build time, control side.** A stored `control` that is unknown or not registered for the element's value
  class, a broken control config, or an unreadable initial value: the filter is kept **without control and
  value** (warning logged), bounded only by the element's own config. A control whose `buildForm()` or
  `hydrate()` throws a `FilterException` is skipped: it is not mounted and contributes no form field (warning).
- **Build time, element side.** An unknown element type or a broken element config fails the list
  (**fail closed**) — also for a filter *with* a control, because the element config bounds the whole list. So
  does an unreadable fixed value of a filter without a control.
- **Query time.** If a controlled filter fails with a non-null value, it is retried once with value `null`.
  Success drops only the value (warning); if the retry fails too, or the value was already `null`, the element
  config is broken and the list fails closed. `\Error`s escape unwrapped.

See [failure handling](../spec/filtering.md#6-failure-handling).
