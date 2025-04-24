<?php

namespace HeimrichHannot\FlareBundle\Contract\ListType;

use HeimrichHannot\FlareBundle\Contract\Config\PresetFiltersConfig;

interface PresetFiltersContract
{
    public function getPresetFilters(PresetFiltersConfig $config): void;
}