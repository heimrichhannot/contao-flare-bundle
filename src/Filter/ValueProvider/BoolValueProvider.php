<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\ValueProvider;

use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaFieldBuilderInterface;
use HeimrichHannot\FlareBundle\Exception\UnreadableValueException;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Stores a yes/no choice as `'1'` / `'0'`; an empty column is "no value".
 */
final readonly class BoolValueProvider implements ValueProviderInterface
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {}

    public function provide(mixed $stored, FilterElementInterface $element): ?BoolValue
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        return match ($stored) {
            '1', 1, true => new BoolValue(true),
            '0', 0, false => new BoolValue(false),
            default => throw new UnreadableValueException(\sprintf(
                'The stored bool value %s is neither empty, "1" nor "0".',
                \json_encode($stored),
            )),
        };
    }

    public function buildDcaField(DcaFieldBuilderInterface $field, DcaContext $context): void
    {
        $field
            ->inputType('select')
            ->options([
                '1' => $this->translator->trans('empty_option.yes', [], 'flare_form'),
                '0' => $this->translator->trans('empty_option.no', [], 'flare_form'),
            ])
            ->eval(['includeBlankOption' => true, 'tl_class' => 'w50']);
    }
}
