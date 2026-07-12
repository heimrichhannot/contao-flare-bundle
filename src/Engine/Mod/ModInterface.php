<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Mod;

use HeimrichHannot\FlareBundle\Engine\Engine;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('flare.engine_mod')]
interface ModInterface
{
    public static function getType(): string;

    public function apply(Engine $engine, array $options): void;
}