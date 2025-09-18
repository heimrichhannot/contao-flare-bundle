<?php

namespace HeimrichHannot\FlareBundle\Form;

use Contao\Model;
use HeimrichHannot\FlareBundle\Contract\LabelableInterface;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

class ChoicesBuilder
{
    public const EMPTY_CHOICE = '__flare_empty__';

    /** @var array<string, Model|LabelableInterface|string> $choices */
    private array $choices = [];
    /** @var array<string, callable|string|null> $choiceValues */
    private array $choiceValues = [];
    private array $groups = [];
    private array $choiceGroupMap = [];
    private string $modelSuffix = '';
    private bool $enabled = false;
    private bool $emptyOption = false;
    private LabelableInterface|string|null $emptyOptionLabel = null;
    private ?string $label = null;
    private array $mapTypeLabel = [];

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ParameterBagInterface $parameterBag,
    ) {}

    public function setLabelForTable(?string $label, string $table): self
    {
        if (!$class = Model::getClassFromTable($table)) {
            throw new \InvalidArgumentException(\sprintf('Table "%s" does not exist.', $table));
        }

        $this->mapTypeLabel[$class] = $label;

        return $this;
    }

    public function setLabelForModel(?string $label, Model $model): self
    {
        $this->mapTypeLabel[$model::class] = $label;

        return $this;
    }

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

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getChoice(string $key): Model|LabelableInterface|string|null
    {
        return $this->choices[$key] ?? null;
    }

    public function add(
        string                          $alias,
        Model|LabelableInterface|string $choice,
        string|null                     $value = null,
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

    public function addGroup(string $key, string $label): static
    {
        $this->groups[$key] = $label;

        return $this;
    }

    public function removeGroup(string $key): static
    {
        unset($this->groups[$key]);

        return $this;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function enable(): static
    {
        $this->enabled = true;

        return $this;
    }

    public function disable(): static
    {
        $this->enabled = false;

        return $this;
    }

    public function hasEmptyOption(): bool
    {
        return $this->emptyOption;
    }

    public function setEmptyOption(LabelableInterface|string|bool|null $value): static
    {
        if (\is_bool($value))
        {
            $this->emptyOption = $value;

            return $this;
        }

        $this->emptyOption = true;
        $this->emptyOptionLabel = $value;

        return $this;
    }

    public function setModelSuffix(string $modelSuffix): static
    {
        $this->modelSuffix = $modelSuffix;

        return $this;
    }

    public function getModelSuffix(): string
    {
        return $this->modelSuffix;
    }

    public function count(): int
    {
        return \count($this->choices);
    }

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

    public function buildChoiceValueCallback(): callable
    {
        return function (mixed $choice): string {
            if ($choice === self::EMPTY_CHOICE)
            {
                return '';
            }

            if (false === $alias = \array_search($choice, $this->choices, true))
            {
                return '';
            }

            return (string) ($this->choiceValues[$alias] ?? $alias);
        };
    }

    public function buildChoiceLabelCallback(): callable
    {
        return function (mixed $choice, string $key, mixed $value): TranslatableMessage|string {
            if ($key === self::EMPTY_CHOICE)
            {
                return $this->buildChoiceLabel($this->emptyOptionLabel, '', '');
            }

            return $this->buildChoiceLabel($choice, $key, $value);
        };
    }

    public function buildChoiceLabel(mixed $choice, string $key, mixed $value): TranslatableMessage|string
    {
        $params = [
            '%@choice%' => Str::force($choice),
            '%@key%' => Str::force($key),
            '%@value%' => Str::force($value),
            '%@type%' => \gettype($choice),
            '%@class%' => \is_object($choice) ? \get_class($choice) : null,
        ];

        if (\is_null($choice))
        {
            return $this->translator->trans('empty_option.dash', $params, 'flare_form');
        }

        if (\is_string($choice))
        {
            return $this->translator->trans($choice, $params, 'flare_form');
        }

        $label = $this->label;

        if (\is_object($choice))
        {
            $label = $this->tryGetTypeLabel($choice::class) ?? $label;
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
     */
    public function buildOptions(): array
    {
        $options = [];

        if ($this->emptyOption) {
            $options[''] = $this->buildChoiceLabel($this->emptyOptionLabel, '', '');
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
     */
    private function tryGetTypeLabel(string $type): ?string
    {
        if (isset($this->mapTypeLabel[$type])) {
            return $this->mapTypeLabel[$type];
        }

        $defaults = $this->parameterBag->get('huh_flare.format_label_defaults') ?? [];
        $table = $this->tryGetTableFromClass($type);

        return $defaults[$type] ?? $defaults[$table] ?? null;
    }

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