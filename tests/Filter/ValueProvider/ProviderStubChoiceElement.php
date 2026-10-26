<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\ValueProvider;

use HeimrichHannot\FlareBundle\Contract\FilterElement\ChoiceSourceContract;
use HeimrichHannot\FlareBundle\Filter\FilterScope;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ChoiceObjectInterface;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ValueChoice;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;

/** Knows only the keys "k1" and "k2". */
final class ProviderStubChoiceElement extends ProviderStubElement implements ChoiceSourceContract
{
    public function buildChoices(ChoicesBuilder $choices, FilterScope $scope): void
    {
        throw new \LogicException('Not needed.');
    }

    public function choiceFromKey(string $key): ?ChoiceObjectInterface
    {
        return \in_array($key, ['k1', 'k2'], true) ? new ValueChoice($key) : null;
    }
}
