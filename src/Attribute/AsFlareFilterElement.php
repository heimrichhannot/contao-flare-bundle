<?php

namespace HeimrichHannot\FlareBundle\Attribute;

use Symfony\Component\Form\FormTypeInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[\Attribute(\Attribute::TARGET_CLASS)]
class AsFlareFilterElement implements TranslatableInterface
{
    /**
     * @param string $alias
     * @param class-string<FormTypeInterface>|null $formType
     * @param string $filterMethod
     */
    public function __construct(
        public string $alias,
        public ?string $formType = null,
        public string $filterMethod = '__invoke'
    ) {}

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