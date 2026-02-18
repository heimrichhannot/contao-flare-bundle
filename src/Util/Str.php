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
        return $db_or_col_name && \preg_match('/^[A-Za-z_]\w*$/', $db_or_col_name);
    }

    public static function wrap(mixed $value): string
    {
        if (\is_null($value)) {
            return 'null';
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (\is_scalar($value) // A value is considered scalar if it is of type int, float, string or bool.
            || $value instanceof \Stringable
            || (\is_object($value) && \method_exists($value, '__toString')))
        {
            return (string) $value;
        }

        if (\is_iterable($value)) {
            return \sprintf(
                '[%s]',
                \implode(',', \array_map(static::wrap(...), \iterator_to_array($value)))
            );
        }

        if (\is_resource($value)) {
            return \sprintf('resource(%s)', \get_resource_type($value));
        }

        if (\is_object($value)) {
            return \sprintf('object(%s)', \get_class($value));
        }

        return \sprintf('type(%s)', \gettype($value));
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

    /**
     * Converts an HTML string to a metadata-friendly string by removing tags and trimming content.
     *
     * This function cleans up an input string by performing the following actions:
     * 1. Replacing multiple consecutive line breaks with a single newline character.
     * 2. Stripping HTML tags from the string.
     * 3. Replacing consecutive whitespace characters with a single space.
     * 4. Decoding HTML entities in the string using UTF-8 encoding.
     * 5. Optionally, truncating the string to the specified character limit, if provided,
     *    ensuring the truncation respects word boundaries where possible.
     * 6. Optionally, appending an ellipsis to the string if it was truncated.
     * 7. Re-encoding the string as HTML entities with the specified flags.
     *
     * @param string $text The input text to be processed.
     * @param int|null $charLimit Optional character limit for the output string. If null, no limit is applied.
     * @param string|null $ellipsis Optional ellipsis string to be appended to the output string if it is truncated.
     * @param int $flags Flags for encoding HTML entities. Defaults to ENT_QUOTES | ENT_HTML5.
     *
     * @return string The processed metadata-friendly string.
     */
    public static function htmlToMeta(
        string $text,
        ?int   $charLimit = null,
        ?string $ellipsis = null,
        int    $flags = \ENT_QUOTES | \ENT_HTML5,
    ): string {
        $trim = \function_exists('mb_trim') ? \mb_trim(...) : \trim(...);

        $text = \preg_replace('/(\r\n|\n|\r){2,}/', "\n", $text);

        $text = $trim(\strip_tags($text));
        $text = \preg_replace('/\s+/', ' ', $text);
        $text = \html_entity_decode($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        $originalTextLength = \mb_strlen($text);

        if (!\is_null($charLimit) && \mb_strlen($text) > $charLimit)
        {
            $text = \mb_substr($text, 0, $charLimit);

            $lastSpace = \mb_strrpos($text, ' ');

            /** @mago-expect lint:no-boolean-literal-comparison We have to check for false specifically. */
            if ($lastSpace !== false) {
                $text = \mb_substr($text, 0, $lastSpace);
            }
        }

        $text = $trim($text);

        $ellipsis = \html_entity_decode($ellipsis, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        if ($ellipsis
            && \mb_strlen($text) < $originalTextLength
            && !\in_array(\mb_substr($text, -1), [$ellipsis, '.', '!', '?'], true))
        {
            $text .= $ellipsis;
        }

        return \htmlentities($text, $flags, 'UTF-8');
    }
}