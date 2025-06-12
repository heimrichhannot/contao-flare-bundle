<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use Symfony\Contracts\EventDispatcher\Event;

class PaletteEvent extends Event
{
    public function __construct(
        private PaletteConfig $paletteConfig,
        private ?string $palette,
    ) {}

    public function getPaletteConfig(): PaletteConfig
    {
        return $this->paletteConfig;
    }

    public function setPaletteConfig(PaletteConfig $paletteConfig): self
    {
        $this->paletteConfig = $paletteConfig;

        return $this;
    }

    public function getPalette(): ?string
    {
        return $this->palette;
    }

    public function setPalette(?string $palette): self
    {
        $this->palette = $palette;

        return $this;
    }
}