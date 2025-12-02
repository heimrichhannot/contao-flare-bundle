<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;

class PaletteEvent extends AbstractFlareEvent
{
    public function __construct(
        private PaletteConfig $paletteConfig,
        private ?string       $palette,
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

    public function getEventName(): string
    {
        return match ($this->getPaletteConfig()->getDataContainer()->table) {
            FilterModel::getTable() => 'flare.filter.palette',
            ListModel::getTable() => 'flare.list.palette',
            default => static::class,
        };
    }
}