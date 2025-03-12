<?php

namespace HeimrichHannot\FlareBundle\Util;

readonly class Str
{
    public static function trimSubstrings(
        string            $str,
        array|string|null $both = null,
        array|string|null $prefix = null,
        array|string|null $suffix = null
    ): string {
        if ($str === '') {
            return $str;
        }

        $both = (array) ($both ?? []);
        $prefix = (array) ($prefix ?? []);
        $suffix = (array) ($suffix ?? []);

        if ($both) {
            $prefix = \array_merge($prefix, $both);
            $suffix = \array_merge($suffix, $both);
        }

        foreach ($prefix as $p)
        {
            if (!\is_string($p)) {
                throw new \InvalidArgumentException('Strip arguments must be a string or an array of strings.');
            }

            if (\str_starts_with($str, $p)) {
                $str = \substr($str, \strlen($p));
            }
        }

        foreach ($suffix as $s)
        {
            if (!\is_string($s)) {
                throw new \InvalidArgumentException('Strip arguments must be a string or an array of strings.');
            }

            if (\str_ends_with($str, $s)) {
                $str = \substr($str, 0, -\strlen($s));
            }
        }

        return $str;
    }

    public static function snakeCase(string $str): string
    {
        $str = \strtolower(\preg_replace('/(?<!^)[A-Z]/', '_$0', $str));
        return \preg_replace('/_+/', '_', $str);
    }

    public static function alphaNum(string $str, ?string $replacement = null): string
    {
        return \preg_replace('/[^a-z0-9-_]/', $replacement ?? '', $str);
    }

    public static function implode(
        string              $glue,
        array               $pieces,
        callable|false|null $filter = null,
        ?callable           $format = null
    ): string {
        if ($filter !== false) {
            $pieces = \array_filter($pieces, $filter ?? static fn ($piece) => (bool) $piece);
        }

        if ($format) {
            \array_walk($pieces, $format);
        }

        return \implode($glue, $pieces);
    }

    public static function formatAlias(string $alias): string
    {
        if (!\str_contains($alias, '\\')) {
            return static::alphaNum(static::snakeCase($alias));
        }

        $alias = \explode('\\', $alias);

        $class = \array_pop($alias);
        $vendor = \array_shift($alias);
        $bundle = \array_shift($alias);

        if (empty($vendor) || empty($class)) {
            throw new \InvalidArgumentException('Invalid alias format.');
        }

        $bundle = static::trimSubstrings($bundle ?? '', prefix: 'Contao', suffix: 'Bundle');
        $class = static::trimSubstrings($class, suffix: ['Controller', 'Element', 'Filter']);

        return static::implode(
            '__',
            \array_map(static fn ($piece) => static::snakeCase($piece), [$vendor, $bundle, $class]),
            format: static fn ($piece) => static::alphaNum($piece)
        );
    }

    /**
     * Merges multiple palettes into one.
     *
     * @param string ...$palettes
     */
    public static function mergePalettes(...$palettes): string
    {
        \array_walk($palettes, static fn ($palette) => \trim((string) $palette, ";, \n\r\t\v\0"));
        return \implode(';', \array_filter($palettes, static fn ($palette) => (bool) $palette));
    }

    public static function isValidSqlName(?string $db_or_col_name): bool
    {
        if (!$db_or_col_name) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $db_or_col_name);
    }

    public static function force(mixed $value): string
    {
        if (\is_scalar($value)) {
            return (string) $value;
        }

        if (\is_string($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        if (\is_array($value)) {
            return '['.static::implode(',', \array_map(static fn ($v) => static::force($v), \iterator_to_array($value))).']';
        }

        if (\is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        if (\is_object($value)) {
            return \get_class($value);
        }

        return \gettype($value);
    }
}