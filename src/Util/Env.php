<?php

namespace HeimrichHannot\FlareBundle\Util;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;

class Env
{
    private static bool $isContao4;

    public static function isContao4(): bool
    {
        if (!isset(self::$isContao4)) {
            try {
                self::$isContao4 = InstalledVersions::satisfies(new VersionParser(), 'contao/core-bundle', '^4.0');
            } catch (\Throwable) {
                self::$isContao4 = false;
            }
        }

        return self::$isContao4;
    }
}