<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Mod;

use HeimrichHannot\FlareBundle\Engine\Engine;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(self::FLARE_ENGINE_MOD_TAG)]
interface ModInterface
{
    public const FLARE_ENGINE_MOD_TAG = 'flare.engine_mod';

    public static function getType(): string;

    public function apply(Engine $engine, array $options): void;
}
