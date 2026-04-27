<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Form;

use Contao\Model;
use HeimrichHannot\FlareBundle\Contract\LabelableInterface;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds Symfony form choice configurations from Contao Models, {@see LabelableInterface}
 * instances, or strings.
 *
 * Aggregates choices with optional values (and groups ffu), then produces the artifacts a Symfony
 * ChoiceType field needs: a choices array via {@see buildChoices()}, a value callback via
 * {@see buildChoiceValueCallback()}, and a label callback via {@see buildChoiceLabelCallback()}.
 * For Contao-style option arrays, use {@see buildOptions()} instead.
 *
 * Labels are resolved against the `flare_form` translation domain. For a given choice, the first
 * available source wins:
 *   1. a per-class/per-table label set via {@see setLabelForClass()}, {@see setLabelForTable()},
 *      or {@see setLabelForModel()};
 *   2. the global label from {@see setLabel()};
 *   3. a default from the `huh_flare.format_label_defaults` parameter, keyed by class or table;
 *   4. the model's id (Model instances only) or a dash.
 *
 * String choices are translated directly. Null choices resolve to the `empty_option.ndash` key.
 *
 * Label translations receive the placeholders `%@choice%`, `%@key%`, `%@value%`, `%@type%`, and
 * `%@class%`. Model choices additionally receive `%@table%`, `%@name%` (the translated
 * `table.<table>` key), and one placeholder per row field as `%<field>%`. Choices implementing
 * {@see LabelableInterface} may contribute further parameters via
 * {@see LabelableInterface::getLabelParameters()}.
 *
 * An optional empty option is enabled via {@see setEmptyOption()}. It is rendered under the
 * sentinel key {@see self::EMPTY_CHOICE} in {@see buildChoices()} (and under an empty-string key
 * in {@see buildOptions()}), with its submitted value controlled by {@see setEmptyOptionValue()}.
 * Use {@see self::EMPTY_CHOICE_VALUE_DEFAULT} for no value or
 * {@see self::EMPTY_CHOICE_VALUE_ALTERNATIVE} when a non-empty URL parameter value is required.
 *
 * Group support ({@see addGroup()}, {@see removeGroup()}) is reserved and not yet implemented.
 *
 * @mago-expect lint:too-many-properties
 */
class ChoicesBuilder
{
    /**
     * @api This is the 'choice' property of the empty option. Use as a placeholder for a empty choice option.
     */
    public const EMPTY_CHOICE = '__flare_empty__';
    /**
     * @api Use when no choice value shall be submitted when the empty option is selected.
     */
    public const EMPTY_CHOICE_VALUE_DEFAULT = '';
    /**
     * @api Use when a url parameter value is required.
     */
    public const EMPTY_CHOICE_VALUE_ALTERNATIVE = '__';

    /** @var array<string, Model|LabelableInterface|string> $choices */
    private array $choices = [];
    /** @var array<string, callable|string|null> $choiceValues */
    private array $choiceValues = [];
    private array $groups = [];
    // @phpstan-ignore property.onlyWritten
    private array $choiceGroupMap = [];
    private string $modelSuffix = '';
    private bool $enabled = false;
    private bool $emptyOption = false;
    private string $emptyOptionValue = self::EMPTY_CHOICE_VALUE_DEFAULT;
    private LabelableInterface|string|null $emptyOptionLabel = null;
    private ?string $label = null;
    private array $mapTypeLabel = [];

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ParameterBagInterface $parameterBag,
    ) {}

    /** @api */
    public function setLabelForTable(?string $label, string $table): self
    {
        if (!$class = Model::getClassFromTable($table)) {
            throw new \InvalidArgumentException(\sprintf('Table "%s" does not exist.', $table));
        }

        $this->mapTypeLabel[$class] = $label;

        return $this;
    }

    /** @api */
    public function setLabelForModel(?string $label, Model $model): self
    {
        $this->mapTypeLabel[$model::class] = $label;

        return $this;
    }

    /** @api */
    public function setLabelForClass(?string $label, string $class): self
    {
        if (!$class) {
            throw new \InvalidArgumentException('Class name must not be empty.');
        }

        if (!\class_exists($class)) {
            throw new \InvalidArgumentException(\sprintf('Class "%s" does not exist.', $class));
        }

        if (!\is_subclass_of($class, Model::class)) {
            throw new \InvalidArgumentException(\sprintf('Class "%s" must be a subclass of "%s".', $class, Model::class));
        }

        $this->mapTypeLabel[$class] = $label;

        return $this;
    }

    /** @api */
    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /** @api */
    public function getChoice(string $key): Model|LabelableInterface|string|null
    {
        return $this->choices[$key] ?? null;
    }

    /** @api */
    public function add(
        string                          $alias,
        Model|LabelableInterface|string $choice,
        mixed                           $value = null,
        string|null                     $group = null,
    ): static {
        $this->choices[$alias] = $choice;

        if (!\is_null($value)) {
            $this->choiceValues[$alias] = $value;
        }

        if (!\is_null($group)) {
            $this->choiceGroupMap[$alias] = $group;
        }

        return $this;
    }

    /** @interal Functionality not yet implemented. */
    public function addGroup(string $key, string $label): static
    {
        $this->groups[$key] = $label;

        return $this;
    }

    /** @interal Functionality not yet implemented. */
    public function removeGroup(string $key): static
    {
        unset($this->groups[$key]);

        return $this;
    }

    /** @api */
    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    /** @api */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /** @api */
    public function enable(): static
    {
        $this->enabled = true;

        return $this;
    }

    /** @api */
    public function disable(): static
    {
        $this->enabled = false;

        return $this;
    }

    public function hasEmptyOption(): bool
    {
        return $this->emptyOption;
    }

    /** @api */
    public function setEmptyOption(LabelableInterface|string|bool|null $state_or_label, ?string $value = null): static
    {
        if (!\is_null($value)) {
            $this->emptyOptionValue = $value;
        }

        if (\is_bool($state_or_label))
        {
            $this->emptyOption = $state_or_label;

            return $this;
        }

        $this->emptyOption = true;
        $this->emptyOptionLabel = $state_or_label;

        return $this;
    }

    /** @api */
    public function setEmptyOptionValue(string $value): static
    {
        $this->emptyOptionValue = $value;

        return $this;
    }

    /** @api */
    public function setModelSuffix(string $modelSuffix): static
    {
        $this->modelSuffix = $modelSuffix;

        return $this;
    }

    /** @api */
    public function getModelSuffix(): string
    {
        return $this->modelSuffix;
    }

    /** @api */
    public function count(): int
    {
        return \count($this->choices);
    }

    /** @api */
    public function buildChoices(): array
    {
        $choices = [];

        if ($this->emptyOption)
        {
            $choices[self::EMPTY_CHOICE] = self::EMPTY_CHOICE;
        }

        foreach ($this->choices as $alias => $choice)
        {
            $choices['c_' . $alias] = $choice;
        }

        return $choices;
    }

    /** @api */
    public function buildChoiceValueCallback(): callable
    {
        return function (mixed $choice): string {
            if ($choice === self::EMPTY_CHOICE)
            {
                return $this->emptyOptionValue;
            }

            if (!$alias = \array_search($choice, $this->choices, true))
            {
                return '';
            }

            return (string) ($this->choiceValues[$alias] ?? $alias);
        };
    }

    /** @api */
    public function buildChoiceLabelCallback(): callable
    {
        return function (mixed $choice, string $key, mixed $value): TranslatableMessage|string {
            if ($key === self::EMPTY_CHOICE)
            {
                return $this->buildChoiceLabel($this->emptyOptionLabel, '', $this->emptyOptionValue);
            }

            return $this->buildChoiceLabel($choice, $key, $value);
        };
    }

    /** @api */
    public function buildChoiceLabel(mixed $choice, string $key, mixed $value): TranslatableMessage|string
    {
        $params = [
            '%@choice%' => Str::wrap($choice),
            '%@key%' => Str::wrap($key),
            '%@value%' => Str::wrap($value),
            '%@type%' => \gettype($choice),
            '%@class%' => \is_object($choice) ? \get_class($choice) : null,
        ];

        if (\is_null($choice))
        {
            return $this->translator->trans('empty_option.ndash', $params, 'flare_form') ?: '-';
        }

        if (\is_string($choice))
        {
            return $this->translator->trans($choice, $params, 'flare_form');
        }

        $label = $this->label;

        if (\is_object($choice))
        {
            $label = $this->tryGetTypeLabel($choice::class)
                ?? $this->label
                ?: $this->tryGetDefaultTypeLabel($choice::class);
        }

        if (!$label && $choice instanceof Model)
        {
            $label = (string) $choice->id;
        }

        if ($choice instanceof Model)
        {
            $params['%@table%'] = $choice::getTable();
            $params['%@name%'] = $this->translator->trans('table.' . $choice::getTable(), [], 'flare_form');

            foreach ($choice->row() as $field => $v) {
                $params['%' . $field . '%'] = $v;
            }

            if ($this->getModelSuffix()) {
                $label = \trim($label) . ' ' . \trim($this->getModelSuffix());
            }
        }

        if ($choice instanceof LabelableInterface)
        {
            $params = \array_merge($params, $choice->getLabelParameters());
        }

        return $this->translator->trans((string) $label ? : '-', $params, 'flare_form');
    }

    /**
     * Builds the Contao-compatible options array for a form field.
     *
     * @api
     */
    public function buildOptions(): array
    {
        $options = [];

        if ($this->emptyOption) {
            $options[''] = $this->buildChoiceLabel($this->emptyOptionLabel, '', $this->emptyOptionValue);
        }

        $labelFactory = $this->buildChoiceLabelCallback();

        foreach ($this->choices as $alias => $choice)
        {
            $value = $this->choiceValues[$alias] ?? $choice;
            $options[$alias] = $labelFactory($choice, $alias, $value);
        }

        return $options;
    }

    /**
     * @param class-string $type
     * @internal
     */
    private function tryGetTypeLabel(string $type): ?string
    {
        return $this->mapTypeLabel[$type] ?? null;
    }

    /**
     * @param class-string $type
     * @internal
     */
    private function tryGetDefaultTypeLabel(string $type): ?string
    {
        if (!$table = $this->tryGetTableFromClass($type)) {
            return null;
        }

        $defaults = $this->parameterBag->get('huh_flare.format_label_defaults') ?? [];

        return $defaults[$type] ?? $defaults[$table] ?? null;
    }

    /**
     * @param class-string $class
     * @internal
     */
    private function tryGetTableFromClass(string $class): ?string
    {
        if (!\class_exists($class)) {
            return null;
        }

        if (!\is_subclass_of($class, Model::class)) {
            return null;
        }

        return $class::getTable();
    }
}