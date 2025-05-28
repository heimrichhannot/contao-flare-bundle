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
    /** @var array<Model|LabelableInterface|string> $choices */
    private array $choices = [];
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

    /**
     * @param string|null $label Setting a label will override the default label for all choices. Null to use the
     *     default labels.
     * @param string|null $class Setting a class will override the default label for the given class. Null to use the
     *     default labels.
     * @return $this Fluent interface
     */
    public function setLabel(?string $label, ?string $class = null): self
    {
        if ($class === null)
        {
            $this->label = $label;
        }
        elseif ($label === null)
        {
            unset($this->mapTypeLabel[$class]);
        }
        else
        {
            $this->mapTypeLabel[$class] = $label;
        }

        return $this;
    }

    public function getChoice(string $key): Model|LabelableInterface|string|null
    {
        return $this->choices[$key] ?? null;
    }

    public function add(
        string                          $key,
        Model|LabelableInterface|string $value,
        string|null                     $group = null,
    ): static {
        $this->choices[$key] = $value;

        if ($group !== null) {
            $this->choiceGroupMap[$key] = $group;
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
        $keys = \array_keys($this->choices);

        $choices = [];
        if ($this->emptyOption) {
            $choices['__flare_empty__'] = '__flare_empty__';
        }

        return \array_merge($choices, \array_combine($keys, $keys) ?: []);
    }

    public function buildChoiceLabelCallback(): callable
    {
        return function (mixed $choice, string $key, mixed $value): TranslatableMessage|string {
            if ($key === '__flare_empty__')
            {
                return $this->buildChoiceLabel($this->emptyOptionLabel, '', '');
            }

            $obj = match (true) {
                \is_object($choice) => $choice,
                \is_string($choice), \is_numeric($choice) => $this->getChoice($key) ?? $choice,
                default => null,
            };

            return $this->buildChoiceLabel($obj, $key, $value);
        };
    }

    public function buildChoiceLabel(mixed $choice, string $key, mixed $value): TranslatableMessage|string {
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

        $label = $this->label ?? null;

        if ($choice instanceof Model) {
            $label = $this->tryGetModelLabel($choice::getTable()) ?? $label;
        }

        if (is_object($choice) && isset($this->mapTypeLabel[$choice::class])) {
            $label = $this->mapTypeLabel[$choice::class] ?? $label;
        }

        if (!$label && $choice instanceof Model) {
            $label = (string) $choice->id;
        }

        if ($choice instanceof Model)
        {
            $params['%@table%'] = $choice::getTable();
            $params['%@name%'] = $this->translator->trans('table.' . $choice::getTable(), [], 'flare_form');

            foreach ($choice->row() as $field => $value) {
                $params['%' . $field . '%'] = $value;
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
            $options[''] = $this->buildChoiceLabel($this->emptyOptionLabel, '', '', '');
        }

        $labelFactory = $this->buildChoiceLabelCallback();

        foreach ($this->choices as $key => $value) {
            $options[$key] = $labelFactory($value, $key, $value);
        }

        return $options;
    }

    private function tryGetModelLabel($table): ?string
    {
        if (isset($this->mapTypeLabel[$table])) {
            return $this->mapTypeLabel[$table];
        }

        $defaults = $this->parameterBag->get('huh_flare.format_label_defaults') ?? [];
        return $defaults[$table] ?? null;
    }
}