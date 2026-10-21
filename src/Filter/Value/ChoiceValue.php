<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Value;

use HeimrichHannot\FlareBundle\Filter\Value\Choice\ChoiceKey;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ChoiceObjectInterface;

/**
 * A selection of choice objects: a set, deduplicated and sorted by canonical key.
 */
final readonly class ChoiceValue implements ValueInterface
{
    /** @var non-empty-list<ChoiceObjectInterface> */
    public array $choices;

    /**
     * @param iterable<ChoiceObjectInterface> $choices Must contain at least one choice.
     */
    public function __construct(iterable $choices)
    {
        $byKey = [];

        foreach ($choices as $choice)
        {
            $byKey[ChoiceKey::of($choice)] ??= $choice;
        }

        if ($byKey === []) {
            throw new \InvalidArgumentException('A ChoiceValue requires at least one choice.');
        }

        \ksort($byKey, \SORT_STRING);

        $this->choices = \array_values($byKey);
    }

    /**
     * @param mixed $data One choice object, or an iterable of them. The empty-option sentinel,
     *   null and non-choice entries are ignored.
     */
    public static function tryFrom(mixed $data): ?self
    {
        if ($data instanceof ChoiceObjectInterface) {
            $data = [$data];
        }

        if (!\is_iterable($data)) {
            return null;
        }

        $choices = [];

        foreach ($data as $entry)
        {
            if ($entry instanceof ChoiceObjectInterface) {
                $choices[] = $entry;
            }
        }

        return $choices === [] ? null : new self($choices);
    }

    public function fingerprint(): string
    {
        return \json_encode(\array_map(ChoiceKey::of(...), $this->choices), \JSON_THROW_ON_ERROR);
    }
}
