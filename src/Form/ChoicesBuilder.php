<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Form;

use Contao\Model;
use HeimrichHannot\FlareBundle\Contract\LabelableInterface;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ChoiceKey;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ChoiceObjectInterface;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ContaoModelChoice;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ValueChoice;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds Symfony form choice configurations from choice objects ({@see ChoiceObjectInterface}).
 *
 * Aggregates choices keyed by their canonical key ({@see ChoiceKey::of()}), then produces the artifacts a Symfony
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
 * The submitted value of a choice is its local key while all choices share at most one scope
 * ({@see usesLocalKeys()}), otherwise its canonical key.
 */
final class ChoicesBuilder
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

    /** @var array<string, ChoiceObjectInterface> $choices canonical key => choice */
    private array $choices = [];
    private ?bool $localKeys = null;
    private string $modelSuffix = '';
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
    public function add(ChoiceObjectInterface $choice): static
    {
        $this->choices[ChoiceKey::of($choice)] = $choice;
        $this->localKeys = null;

        return $this;
    }

    /**
     * True iff the choices have at most one distinct scope, so local keys are unambiguous.
     *
     * @api
     */
    public function usesLocalKeys(): bool
    {
        return $this->localKeys ??= \count(\array_unique(\array_map(
            static fn (ChoiceObjectInterface $c): ?string => $c->getChoiceScope(),
            $this->choices,
        ), \SORT_REGULAR)) <= 1;
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

    /**
     * @return array<string, ChoiceObjectInterface|string>
     * @api
     */
    public function buildChoices(): array
    {
        $choices = [];

        if ($this->emptyOption)
        {
            $choices[self::EMPTY_CHOICE] = self::EMPTY_CHOICE;
        }

        foreach ($this->choices as $key => $choice)
        {
            $choices[$key] = $choice;
        }

        return $choices;
    }

    public function buildCallbackChoiceLoader(): CallbackChoiceLoader
    {
        return new CallbackChoiceLoader($this->buildChoices(...));
    }

    /**
     * @return \Closure(mixed): string
     * @api
     */
    public function buildChoiceValueCallback(): \Closure
    {
        return function (mixed $choice): string {
            if ($choice === self::EMPTY_CHOICE)
            {
                return $this->emptyOptionValue;
            }

            if (!$choice instanceof ChoiceObjectInterface)
            {
                return '';
            }

            return $this->usesLocalKeys() ? $choice->getChoiceLocalKey() : ChoiceKey::of($choice);
        };
    }

    /** @api */
    public function buildChoiceLabelCallback(): callable
    {
        return function (mixed $choice, string|int $key, mixed $value): string {
            if ($key === self::EMPTY_CHOICE)
            {
                return $this->buildChoiceLabel($this->emptyOptionLabel, '', $this->emptyOptionValue);
            }

            return $this->buildChoiceLabel($choice, $key, $value);
        };
    }

    /** @api */
    public function buildChoiceLabel(mixed $choice, string|int $key, mixed $value): string
    {
        $params = [
            '%@choice%' => Str::wrap($choice),
            '%@key%' => (string) $key,
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

        $model = null;
        $table = '';

        if ($choice instanceof ContaoModelChoice)
        {
            $table = $choice->getChoiceScope();
            $model = $choice->getModel();

            if (!$model)
            {
                return $choice->table . '.' . $choice->id;
            }
        }

        $labelClass = match (true) {
            $model !== null => $model::class,
            \is_object($choice) => $choice::class,
            default => null,
        };

        $label = match (true) {
            $choice instanceof ValueChoice => $choice->label ?? $choice->value,
            $labelClass !== null => $this->tryGetTypeLabel($labelClass)
                ?? $this->label
                ?: $this->tryGetDefaultTypeLabel($labelClass),
            default => $this->label,
        };

        if ($model)
        {
            if (!$label)
            {
                $label = (string) $model->id;
            }

            $params['%@table%'] = $table;
            $params['%@name%'] = $this->translator->trans('table.' . $table, [], 'flare_form');

            foreach ($model->row() as $field => $v) {
                $params['%' . $field . '%'] = $v;
            }

            if ($this->getModelSuffix()) {
                $label = \trim((string) $label) . ' ' . \trim($this->getModelSuffix());
            }
        }

        if ($choice instanceof ChoiceObjectInterface)
        {
            $params = \array_merge($params, $choice->getLabelParameters());
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
    public function buildContaoOptions(): array
    {
        $options = [];

        if ($this->emptyOption) {
            $options[''] = $this->buildChoiceLabel($this->emptyOptionLabel, '', $this->emptyOptionValue);
        }

        $labelFactory = $this->buildChoiceLabelCallback();
        $toValue = $this->buildChoiceValueCallback();

        foreach ($this->choices as $key => $choice)
        {
            $options[$key] = $labelFactory($choice, $key, $toValue($choice));
        }

        return $options;
    }

    /**
     * Apply options to a Symfony Forms-compatible options array for a form field.
     *
     * @param array &$options
     * @return $this
     */
    public function applyFormOptions(array &$options): self
    {
        $options['choice_loader'] = $this->buildCallbackChoiceLoader();
        $options['choice_label'] = $this->buildChoiceLabelCallback();
        $options['choice_value'] = $this->buildChoiceValueCallback();

        return $this;
    }

    /**
     * Generate a Symfony Forms-compatible options array for a form field.
     *
     * @return array
     */
    public function buildFormOptions(): array
    {
        $options = [];

        $this->applyFormOptions($options);

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
