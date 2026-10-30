<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Control;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\ControlScope;
use HeimrichHannot\FlareBundle\Filter\FilterComponentInterface;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Form\FilterFormBuilderInterface;

/**
 * A registrable presentation strategy for one filter: how the user supplies the filter's value
 * and how that input becomes a value object.
 *
 * The value class a control produces is the narrowed return type of {@see self::decode()}; it is
 * read by reflection at compile time and a control declares nothing else.
 *
 * A control MAY additionally implement
 * - {@see \HeimrichHannot\FlareBundle\Contract\OptionsContract}
 * - {@see \HeimrichHannot\FlareBundle\Contract\TransformerContract}
 * - {@see \HeimrichHannot\FlareBundle\Contract\DcaContract}
 *
 * for its own config. Anything it needs pulled from the element is an explicit capability port,
 * e.g. {@see \HeimrichHannot\FlareBundle\Contract\FilterElement\ChoiceSourceContract}.
 *
 * @api
 */
interface FilterControlInterface extends FilterComponentInterface
{
    /**
     * Declares the filter's form fields on the collect-only per-filter builder.
     *
     * - Single-field controls declare their field via {@see FilterFormBuilderInterface::single()};
     *   it is mounted flat on the root form under the filter's alias.
     * - Multi-field controls use {@see FilterFormBuilderInterface::add()} to add children with local
     *   names, which mount as a compound sub-form.
     * - Declaring both `single()` and `add()` at once is not supported.
     */
    public function buildForm(FilterFormBuilderInterface $builder, ControlScope $scope): void;

    /**
     * Turns the mount's data into this control's value object, or null to contribute nothing.
     * Implementations MUST narrow the return type to one concrete value class.
     *
     * @throws FilterException If the data cannot become a value: the filter contributes no value, and a submitted
     *   field gets a form error ({@see \HeimrichHannot\FlareBundle\Form\FormHarness::decode()}).
     */
    public function decode(mixed $data, ControlScope $scope): ?ValueInterface;

    /**
     * The inverse of {@see self::decode()}: the widget data for a value of this control's value class.
     * Used to hydrate an unsubmitted form from the filter's value.
     */
    public function hydrate(?ValueInterface $value, ControlScope $scope): mixed;
}
