<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\ValueProvider;

use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaFieldBuilderInterface;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * The authoritative service for one value class: turns the stored `value` column into a value
 * object and renders that column in the backend (SPEC_FILTER_FORMS.md §3.6).
 *
 * The value class is the narrowed return type of {@see self::provide()}, read by reflection at
 * compile time. There is exactly one provider per value class.
 *
 * @api
 */
#[AutoconfigureTag(ValueProviderInterface::TAG)]
interface ValueProviderInterface
{
    public const TAG = 'flare.value_provider';

    /**
     * The value object for the stored column content, or null for "no value".
     * Implementations MUST narrow the return type to one concrete value class.
     *
     * An unreadable non-empty stored value is reported with a FilterException (e.g. UnreadableValueException): a
     * filter without a control then fails its list, one with a control loses only its initial value (warning logged).
     * Any other exception is handled alike but logged as an error, as unexpected.
     *
     * @throws FilterException If a non-empty stored value is unreadable.
     */
    public function provide(mixed $stored, FilterElementInterface $element): ?ValueInterface;

    /**
     * Configures the backend widget of the `value` column for an element of this value class.
     */
    public function buildDcaField(DcaFieldBuilderInterface $field, DcaContext $context): void;
}
