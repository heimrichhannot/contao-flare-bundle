<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Attribute;

use Symfony\Component\Form\FormTypeInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class AsFilterElement implements TranslatableInterface
{
    public array $attributes;

    /**
     * @param string $alias
     * @param class-string<FormTypeInterface>|null $formType
     * @param string $filterMethod
     */
    public function __construct(
        public string $alias,
        public ?string $formType = null,
        public ?string $filterMethod = null,
        ...$attributes
    ) {
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

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->alias, [], 'flare', $locale);
    }
}