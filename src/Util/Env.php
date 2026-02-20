<?php

namespace HeimrichHannot\FlareBundle\Util;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;

/**
 * @method static bool isContao4()
 * @method static bool isContao5()
 * @method static bool hasContaoCalendar()
 * @method static bool hasContaoComments()
 * @method static bool hasContaoNews()
 * @method static bool hasTerminal42ChangeLanguage()
 */
final class Env
{
    private static array $cache = [];

    public static function __callStatic(string $name, array $arguments)
    {
        if (isset(self::$cache[$name])) {
            return self::$cache[$name];
        }

        try
        {
            return self::$cache[$name] = match ($name) {
                'isContao4' => InstalledVersions::satisfies(new VersionParser(), 'contao/core-bundle', '^4.0'),
                'isContao5' => InstalledVersions::satisfies(new VersionParser(), 'contao/core-bundle', '^5.0'),
                'hasContaoCalendar' => InstalledVersions::isInstalled('contao/calendar-bundle'),
                'hasContaoComments' => InstalledVersions::isInstalled('contao/comments-bundle'),
                'hasContaoNews' => InstalledVersions::isInstalled('contao/news-bundle'),
                'hasTerminal42ChangeLanguage' => InstalledVersions::isInstalled('terminal42/contao-changelanguage'),
                default => false,
            };
        }
        catch (\Throwable)
        {
            return self::$cache[$name] = false;
        }
    }
}