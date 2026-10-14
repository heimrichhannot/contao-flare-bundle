<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Form;

use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use Symfony\Component\Form\FormInterface;

/**
 * A registrable presentation strategy for one filter: how the user supplies the filter's value.
 *
 * Bound to a *value class*. A form MAY additionally implement
 * - {@see \HeimrichHannot\FlareBundle\Contract\OptionsContract}
 * - {@see \HeimrichHannot\FlareBundle\Contract\DcaContract}
 *
 * Anything the form needs *pulled* from the element is an explicit capability port,
 * e.g. {@see \HeimrichHannot\FlareBundle\Contract\FilterElement\ChoiceSourceContract}.
 *
 * @template T of ValueInterface
 * @api
 */
interface FilterFormInterface
{
    /**
     * Returns the fully qualified class name of the value object this form produces.
     *
     * @return class-string<T>
     */
    public function getValueClass(): string;

    /**
     * Declares the filter's form fields on the collect-only per-filter builder.
     *
     * - **Single-field forms** declare their field via {@see FilterFormBuilderInterface::single()};
     *   it is mounted flat on the root form under the filter's alias.
     * - **Multi-field forms** use {@see FilterFormBuilderInterface::add()} to add children with local
     *   names, which mount as a compound sub-form.
     * - Declaring both `single()` and `add()` at once is not supported and fails when the form is built.
     * ---
     * - Pre-submission defaults belong in the fields' native `data` option.
     * - Event listeners registered on the builder are replayed onto the mounted form.
     * - Event subscribers are not supported.
     */
    public function buildForm(FilterFormBuilderInterface $builder, FilterContext $context): void;

    /**
     * Produces the element's canonical value from the form mount, or null to contribute nothing.
     *
     * - Receives the *mount*: the node this form mounted into the root form. Either a flat field or a
     *   compound group.
     * - Only the form knows whether it wants `getData()`, `getNormData()` or `getViewData()`, and it may
     *   need attributes set in {@see self::buildForm()}.
     * - The "user explicitly cleared" vs. "user never interacted" distinction is answerable here via
     *   `$form->isSubmitted()` / `$context->engineContext`.
     *
     * @return T|null A value object of the class this form is registered for.
     *   Null contributes nothing, which lets the element fall back to its own config.
     */
    public function decode(FormInterface $form, FilterContext $context): ?ValueInterface;
}
