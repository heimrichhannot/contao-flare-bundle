<?php

namespace HeimrichHannot\FlareBundle\Filter\Element;

use HeimrichHannot\FlareBundle\Contract\FilterElement\FormTypeOptionsContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use Symfony\Component\Form\Extension\Core\Type\TextType;

#[AsFilterElement(
    alias: TextElement::TYPE,
    palette: 'fieldGeneric',
    formType: TextType::class,
)]
class TextElement implements FormTypeOptionsContract
{
    public const TYPE = 'flare_text';

    public function __invoke(FilterContext $context, FilterQueryBuilder $qb): void
    {
        // TODO: Implement __invoke() method.
    }

    public function getFormTypeOptions(FilterContext $context, ChoicesBuilder $choices): array
    {
        return [
            'label' => 'label.text',
            'required' => false,
        ];
    }
}