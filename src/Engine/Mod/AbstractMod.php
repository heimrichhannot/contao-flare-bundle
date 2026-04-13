<?php

namespace HeimrichHannot\FlareBundle\Engine\Mod;

use HeimrichHannot\FlareBundle\Engine\Engine;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class AbstractMod implements ModInterface
{
    abstract public static function getType(): string;

    abstract public function __invoke(Engine $engine, array $options): void;

    final public function apply(Engine $engine, array $options): void
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $options = $resolver->resolve($options);

        $this($engine, $options);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // Implement in child classes
    }
}