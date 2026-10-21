<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\ValueProvider;

use Contao\Message;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Contract\FilterElement\ChoiceSourceContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaFieldBuilderInterface;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\UnreadableValueException;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Value\ChoiceValue;

/**
 * Stores a serialized list of canonical choice keys; the element maps them to reference choices.
 */
final readonly class ChoiceValueProvider implements ValueProviderInterface
{
    public function provide(mixed $stored, FilterElementInterface $element): ?ChoiceValue
    {
        if (!$element instanceof ChoiceSourceContract) {
            throw new FilterException(\sprintf(
                'The filter element "%s" must implement %s to provide a choice value.',
                $element::class,
                ChoiceSourceContract::class,
            ));
        }

        if ($stored === null || $stored === '' || $stored === []) {
            return null;
        }

        $keys = StringUtil::deserialize($stored);

        if (!\is_array($keys)) {
            throw new UnreadableValueException('The stored choice keys are not a serialized list.');
        }

        $choices = [];

        foreach ($keys as $key)
        {
            if (!\is_string($key) && !\is_int($key)) {
                continue;
            }

            if (($key = (string) $key) !== '' && ($choice = $element->choiceFromKey($key)) !== null) {
                $choices[] = $choice;
            }
        }

        if ($choices === []) {
            if (\array_filter($keys, static fn (mixed $key): bool => $key !== '' && $key !== null) === []) {
                return null;
            }

            throw new UnreadableValueException('None of the stored choice keys is readable.');
        }

        return new ChoiceValue($choices);
    }

    public function buildDcaField(DcaFieldBuilderInterface $field, DcaContext $context): void
    {
        $field
            ->inputType('select')
            ->eval(['multiple' => true, 'chosen' => true, 'tl_class' => 'clr'])
            ->options(static function () use ($context): array {
                $element = $context->element;
                $scope = $context->getFilterScope();

                if (!$element instanceof ChoiceSourceContract || $scope === null)
                {
                    Message::addError('The filter choices could not be loaded; check the list configuration.');

                    return [];
                }

                try {
                    return $element->createChoices($scope)->buildContaoOptions();
                }
                catch (FilterException $e) {
                    Message::addError($e->getMessage());
                }

                return [];
            });
    }
}
