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

    private static function populate(string $name, callable $test): bool
    {
        try {
            self::$cache[$name] = $test();
        } catch (\Throwable) {
            self::$cache[$name] = false;
        }

        return self::$cache[$name];
    }

    public static function __callStatic(string $name, array $arguments)
    {
        if (isset(self::$cache[$name])) {
            return self::$cache[$name];
        }

        $callback = match ($name) {
            'isContao4' => static fn (): bool => InstalledVersions::satisfies(new VersionParser(), 'contao/core-bundle', '^4.0'),
            'isContao5' => static fn (): bool => InstalledVersions::satisfies(new VersionParser(), 'contao/core-bundle', '^5.0'),
            'hasContaoCalendar' => static fn (): bool => InstalledVersions::isInstalled('contao/calendar-bundle'),
            'hasContaoComments' => static fn (): bool => InstalledVersions::isInstalled('contao/comments-bundle'),
            'hasContaoNews' => static fn (): bool => InstalledVersions::isInstalled('contao/news-bundle'),
            'hasTerminal42ChangeLanguage' => static fn (): bool => InstalledVersions::isInstalled('terminal42/contao-changelanguage'),
            default => static fn (): bool => false,
        };

        return self::populate($name, $callback);
    }
}