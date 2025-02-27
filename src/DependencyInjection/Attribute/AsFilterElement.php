<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Attribute;

use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class AsFilterElement
{
    public array $attributes;

    /**
     * @param string $alias
     * @param class-string<FormTypeInterface>|null $formType
     * @param string $filterMethod
     */
    public function __construct(
        private string $alias,
        public ?string $formType = null,
        public ?string $filterMethod = null,
        ...$attributes
    ) {
        $this->alias = $alias = Str::formatAlias($alias);

        $attributes['alias'] = $alias;
        $attributes['formType'] = $formType;
        $attributes['filterMethod'] = $filterMethod;

        $this->attributes = $attributes;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getFormType(): ?string
    {
        return $this->formType;
    }

    public function getFilterMethod(): string
    {
        return $this->filterMethod;
    }

    public function hasFormType(): bool
    {
        $class = $this->getFormType();
        return $class !== null && \class_exists($class);
    }
}