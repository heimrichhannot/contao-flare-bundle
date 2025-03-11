<?php

namespace HeimrichHannot\FlareBundle\Form;

use Contao\Model;
use HeimrichHannot\FlareBundle\Contract\LabelableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ChoicesBuilder
{
    /** @var array<Model|LabelableInterface> $map */
    private array $map = [];
    private bool $enabled = false;
    private ?string $defaultLabel = null;
    private array $mapTypeLabel = [];

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    public function setLabel(string $label, ?string $class = null): self
    {
        if ($class === null) {
            $this->defaultLabel = $label;
        } else {
            $this->mapTypeLabel[$class] = $label;
        }

        return $this;
    }

    public function add(string|int $key, Model|LabelableInterface $value): static
    {
        $this->map[$key] = $value;

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

    public function buildChoices(): array
    {
        $keys = \array_keys($this->map);
        return \array_combine($keys, $keys) ?: [];
    }

    public function buildChoiceLabel(): callable
    {
        return function ($choice, $key, $value) {
            $obj = $this->map[$key] ?? null;

            $label = $this->mapTypeLabel[$obj::class] ?? null;

            if (!$label && $obj instanceof Model)
            {
                $label = $this->mapTypeLabel[$obj::getTable()] ?? null;

                // todo read dca title config if label is null
            }

            $label ??= $this->defaultLabel;

            $params = [];

            if ($obj instanceof LabelableInterface)
            {
                $params = $obj->getLabelParameters();
            }
            elseif ($obj instanceof Model)
            {
                $label ??= (string) $obj->id;

                foreach ($obj->row() as $field => $value) {
                    $params['%' . $field . '%'] = $value;
                }
            }

            return $this->translator->trans((string) $label, $params, 'flare_form');
        };
    }
}