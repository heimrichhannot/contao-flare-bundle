<?php

namespace HeimrichHannot\FlareBundle\Engine\Mod;

use HeimrichHannot\FlareBundle\Engine\Engine;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AutoconfigureTag('flare.engine_mod')]
interface ModInterface
{
    public static function getType(): string;

    public function configureOptions(OptionsResolver $resolver): void;

    public function apply(Engine $engine, array $options): void;
}