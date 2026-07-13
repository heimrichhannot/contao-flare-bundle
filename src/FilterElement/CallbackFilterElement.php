<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\FilterElement;

use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Closure-backed filter element for inline filters that need no registered service.
 *
 * @see \HeimrichHannot\FlareBundle\Filter\Filter::fromCallback()
 * @see \HeimrichHannot\FlareBundle\Filter\Filter::fromType()
 */
final readonly class CallbackFilterElement implements FilterElementInterface
{
    /**
     * @param \Closure(FilterBuilderInterface, FilterContext, array<string, mixed>): void $buildFilter
     * @param (\Closure(FormBuilderInterface, FilterContext): void)|null $buildForm
     */
    public function __construct(
        private \Closure  $buildFilter,
        private ?\Closure $buildForm = null,
    ) {}

    public function buildForm(FormBuilderInterface $builder, FilterContext $context): void
    {
        if ($this->buildForm) {
            ($this->buildForm)($builder, $context);
        }
    }

    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $data): void
    {
        ($this->buildFilter)($builder, $context, $data);
    }
}
