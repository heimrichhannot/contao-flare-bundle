<?php

namespace HeimrichHannot\FlareBundle\Util;

use Contao\StringUtil;

readonly class Str
{
    public const CHARS_ALPHA = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    public const CHARS_ALPHA_LOWER = 'abcdefghijklmnopqrstuvwxyz';
    public const CHARS_ALPHA_UPPER = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    public const CHARS_ALPHANUMERIC = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    public const CHARS_HEX = '0123456789abcdef';
    public const CHARS_NUM = '0123456789';

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
        /** @mago-expect lint:no-boolean-literal-comparison We have to check for false specifically. */
        if ($filter !== false) {
            $pieces = \array_filter($pieces, $filter);
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

        $arrAlias = \explode('\\', $alias);

        $class = \array_pop($arrAlias);
        $vendor = \array_shift($arrAlias);
        $bundle = \array_shift($arrAlias);

        if (!$vendor || !$class) {
            throw new \InvalidArgumentException('Invalid alias format.');
        }

        $bundle = static::trimSubstrings($bundle ?? '', prefix: 'Contao', suffix: 'Bundle');
        $class = static::trimSubstrings($class, suffix: ['Controller', 'Element', 'Filter']);

        return static::implode(
            '__',
            \array_map(static::snakeCase(...), [$vendor, $bundle, $class]),
            format: static::alphaNum(...)
        );
    }

    /**
     * Merges multiple palettes into one.
     *
     * @param string ...$palettes
     */
    public static function mergePalettes(?string ...$palettes): string
    {
        $palettes = \array_filter($palettes);
        \array_walk($palettes, static fn (string $palette): string => \trim($palette, ";, \n\r\t\v\0"));
        return \implode(';', \array_filter($palettes));
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
            return \sprintf(
                '[%s]',
                static::implode(',', \array_map(static::force(...), \iterator_to_array($value)))
            );
        }

        if (\is_object($value) && \method_exists($value, '__toString')) {
            return (string) $value;
        }

        if (\is_object($value)) {
            return \get_class($value);
        }

        return \gettype($value);
    }

    public static function random(int $length = 10, ?string $chars = null): string
    {
        $chars ??= '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $max = \mb_strlen($chars, '8bit') - 1;
        $rand = '';

        for ($i = 0; $i < $length; $i++) {
            $rand .= $chars[\random_int(0, $max)];
        }

        return $rand;
    }

    public static function formatHeadline(array|string|null $headline, bool $withTags = false): ?string
    {
        if (!$headline) {
            return null;
        }

        if (\is_string($headline)) {
            $headline = StringUtil::deserialize($headline);
        }

        if (\is_string($headline)) {
            return $headline ?: null;
        }

        if (!\is_array($headline)) {
            return null;
        }

        $tagName = $headline['tag_name'] ?? $headline['unit'] ?? 'h2';
        $value = $headline['text'] ?? $headline['value'] ?? '';

        return $withTags ? "<{$tagName}>{$value}</{$tagName}>" : $value;
    }

    public static function htmlToMeta(
        string $text,
        ?int   $charLimit = null,
        int    $flags = \ENT_QUOTES | \ENT_HTML5,
    ): string {
        $trim = \function_exists('mb_trim') ? \mb_trim(...) : \trim(...);

        $text = \preg_replace('/(\r\n|\n|\r){2,}/', "\n", $text);

        $text = $trim(\strip_tags($text));
        $text = \preg_replace('/\s+/', ' ', $text);
        $text = \html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (!\is_null($charLimit) && \mb_strlen($text) > $charLimit)
        {
            $text = \mb_substr($text, 0, $charLimit);

            $lastSpace = \mb_strrpos($text, ' ');

            /** @mago-expect lint:no-boolean-literal-comparison We have to check for false specifically. */
            if ($lastSpace !== false) {
                $text = \mb_substr($text, 0, $lastSpace);
            }
        }

        return \htmlentities($trim($text), $flags, 'UTF-8');
    }
}