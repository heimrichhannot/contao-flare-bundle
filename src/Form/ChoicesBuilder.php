<?php

namespace HeimrichHannot\FlareBundle\Form;

use Contao\Model;
use HeimrichHannot\FlareBundle\Contract\LabelableInterface;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ChoicesBuilder
{
    /** @var array<Model|LabelableInterface> $choices */
    private array $choices = [];
    private array $groups = [];
    private array $choiceGroupMap = [];
    private string $modelSuffix = '';
    private bool $enabled = false;
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

    public function add(
        string|int                      $key,
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
        return \array_combine($keys, $keys) ?: [];
    }

    public function buildChoiceLabelCallback(): callable
    {
        return function ($choice, $key, $value) {
            $obj = $this->choices[$key] ?? null;

            $params = [
                '%@choice%' => Str::force($choice),
                '%@key%' => Str::force($key),
                '%@value%' => Str::force($value),
                '%@type%' => \gettype($obj),
                '%@class%' => \is_object($obj) ? \get_class($obj) : null,
            ];

            if (\is_string($obj))
            {
                return $this->translator->trans($obj, $params, 'flare_form');
            }

            $label = $this->label
                ?? $this->mapTypeLabel[$obj::class]
                ?? ($obj instanceof Model ? $this->tryGetModelLabel($obj::getTable()) : null);

            if ($obj instanceof LabelableInterface)
            {
                $params = $obj->getLabelParameters();
            }
            elseif ($obj instanceof Model)
            {
                $label ??= (string) $obj->id;

                $params['%@table%'] = $obj::getTable();
                $params['%@name%'] = $this->translator->trans('table.' . $obj::getTable(), [], 'flare_form');

                foreach ($obj->row() as $field => $value) {
                    $params['%' . $field . '%'] = $value;
                }

                if ($this->getModelSuffix()) {
                    $label = \trim($label) . ' ' . \trim($this->getModelSuffix());
                }
            }

            return $this->translator->trans((string) $label ? : '-', $params, 'flare_form');
        };
    }

    public function buildOptions(): array
    {
        $options = [];

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