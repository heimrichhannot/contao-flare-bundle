<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Util;

use Contao\StringUtil;

final readonly class Str
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
        array|string|null $suffix = null,
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
        ?callable           $format = null,
    ): string {
        if ($filter !== false) {
            $pieces = \array_filter($pieces, $filter);
        }

        if ($format) {
            $pieces = \array_map($format, $pieces);
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
        $palettes = \array_filter(\array_map(
            static fn (string $palette): string => \trim($palette, ";, \n\r\t\v\0"),
            $palettes,
        ));

        return \implode(';', $palettes);
    }

    public static function isValidSqlName(?string $db_or_col_name): bool
    {
        return $db_or_col_name && \preg_match('/^[A-Za-z_]\w*$/', $db_or_col_name);
    }

    /**
     * Whether the given name is a valid, non-empty Symfony form name.
     * Mirrors {@see \Symfony\Component\Form\FormConfigBuilder::isValidName()} except that
     * empty names are rejected. Generated filter aliases like "_.tl_flare_filter.42" fail
     * this check by design and therefore never mount form children.
     */
    public static function isValidFormName(?string $name): bool
    {
        return $name !== null && $name !== ''
            && \preg_match('/^[a-zA-Z0-9_][a-zA-Z0-9_\-:]*$/D', $name) === 1;
    }

    public static function wrap(mixed $value): string
    {
        if (\is_null($value)) {
            return 'null';
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        // A value is considered scalar if it is of type int, float, string or bool.
        if (\is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        if (\is_iterable($value)) {
            return \sprintf(
                '[%s]',
                \implode(',', \array_map(self::wrap(...), \iterator_to_array($value))),
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
        $chars ??= self::CHARS_ALPHANUMERIC;
        $max = \mb_strlen($chars, '8bit') - 1;
        $rand = '';

        for ($i = 0; $i < $length; $i++) {
            $rand .= $chars[\random_int(0, $max)];
        }

        return $rand;
    }

    public static function normalizeHeadline(array|string|null $headline): ?array
    {
        if ($headline === null || $headline === '' || $headline === []) {
            return null;
        }

        $headline = StringUtil::deserialize($headline, true);

        if (!isset($headline['tag_name']) && !isset($headline['unit'])
            && !isset($headline['text']) && !isset($headline['value']))
        {
            return null;
        }

        return [
            'tag_name' => $headline['tag_name'] ?? $headline['unit'] ?? 'h2',
            'unit' => $headline['unit'] ?? $headline['tag_name'] ?? 'h2',
            'text' => $headline['text'] ?? $headline['value'] ?? '',
            'value' => $headline['value'] ?? $headline['text'] ?? '',
        ];
    }

    /**
     * Formats a Contao-formatted headline by processing the given input and optionally
     * wrapping it in HTML tags.
     *
     * If the `$withTags` parameter is set to true, the content is wrapped in the computed tag
     * (defaulting to `<h2>` if none is provided, or it's invalid). Supported tags are limited
     * to valid headings (`h1` through `h6`), `<hgroup>`, and `<p>`. If the tag is invalid, the
     * raw content is returned without tags.
     *
     * @param array|string|null $headline The headline input to be formatted, which can be a
     *                                    string, an associative array, or null.
     * @param bool $withTags Whether to wrap the headline in HTML tags. Defaults to false.
     *
     * @return string|null The formatted headline, optionally wrapped in HTML tags, or null if
     *                     the input is invalid or empty.
     */
    public static function formatHeadline(array|string|null $headline, bool $withTags = false): ?string
    {
        if ($headline === null || $headline === '' || $headline === []) {
            return null;
        }

        if (\is_string($headline)) {
            $headline = StringUtil::deserialize($headline);
        }

        if (\is_string($headline)) {
            return $headline;
        }

        if (!\is_array($headline)) {
            return null;
        }

        $value = $headline['text'] ?? $headline['value'] ?? '';

        if ($value === '') {
            return null;
        }

        $tagName = \strtolower($headline['tag_name'] ?? $headline['unit'] ?? 'h2');

        if (\is_numeric($tagName)) {
            $tagName = "h{$tagName}";
        }

        $allowedTags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hgroup', 'p', 'span'];

        if (!\in_array($tagName, $allowedTags, true)) {
            return $value;
        }

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
        string  $text,
        ?int    $charLimit = null,
        ?string $ellipsis = null,
        int     $flags = \ENT_QUOTES | \ENT_HTML5,
    ): string {
        $trim = \function_exists('mb_trim') ? \mb_trim(...) : \trim(...);

        $text = \preg_replace('/(\r\n|\n|\r){2,}/', "\n", $text);

        $text = $trim(\strip_tags($text));
        $text = (string) \preg_replace('/\s+/', ' ', $text);
        $text = \html_entity_decode($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        $originalTextLength = \mb_strlen($text);

        if (!\is_null($charLimit) && \mb_strlen($text) > $charLimit)
        {
            $text = \mb_substr($text, 0, $charLimit);

            $lastSpace = \mb_strrpos($text, ' ');

            if ($lastSpace !== false) {
                $text = \mb_substr($text, 0, $lastSpace);
            }
        }

        $text = $trim($text);

        if (!\is_null($ellipsis) && \mb_strlen($text) < $originalTextLength)
        {
            $ellipsis = \html_entity_decode($ellipsis, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

            if (!\in_array(\mb_substr($text, -1), [$ellipsis, '.', '!', '?'], true))
            {
                $text .= $ellipsis;
            }
        }

        return \htmlentities($text, $flags, 'UTF-8');
    }

    /**
     * Returns the first non-empty string from a list of arguments, evaluating callable arguments if necessary.
     *
     * This function iterates through a list of arguments and performs the following operations:
     * 1. If an argument is a callable, it executes the callable and uses its return value.
     * 2. Checks if the argument is a non-empty string.
     * 3. Returns the first non-empty string encountered.
     * 4. If no non-empty string is found, the function returns null.
     *
     * @param string|callable|null ...$args A variable number of arguments to check. Each argument can be:
     *                                      - A string,
     *                                      - A callable returning a value,
     *                                      - null.
     *
     * @return string|null The first non-empty string, or null if no such string is found.
     */
    public static function coalesce(string|callable|null ...$args): ?string
    {
        foreach ($args as $arg)
        {
            if (\is_callable($arg)) {
                $arg = $arg();
            }

            if (\is_string($arg) && $arg !== '') {
                return $arg;
            }
        }

        return null;
    }

    public static function htmlListClasses(string|array|null ...$classes): array
    {
        if (!$classes) {
            return [];
        }

        $normalClasses = [];
        $flatClasses = Arr::flatten($classes);

        foreach ($flatClasses as $class)
        {
            if ($class instanceof \Closure) {
                $class = $class();
            }

            if (!$class) {
                continue;
            }

            if (!\is_scalar($class) && !$class instanceof \Stringable) {
                continue;
            }

            $split = \explode(' ', (string) $class);
            \array_push($normalClasses, ...$split);
        }

        return \array_values(\array_unique(\array_filter($normalClasses)));
    }

    public static function htmlJoinClasses(string|array|null ...$classes): string
    {
        return \implode(' ', self::htmlListClasses(...$classes));
    }

    /**
     * Extracts the id value from Contao's legacy `cssID` template variable, which holds a
     * pre-built attribute fragment (` id="foo"`) rather than a bare value.
     *
     * @param string|null $cssID The legacy attribute fragment, e.g. ` id="foo"`.
     *
     * @return string|null The id value, or null if the fragment is empty or unparsable.
     */
    public static function htmlExtractId(?string $cssID): ?string
    {
        if (!$cssID || !\preg_match('/\bid\s*=\s*("[^"]*"|\'[^\']*\'|[^\s"\'>]+)/i', $cssID, $matches)) {
            return null;
        }

        return \trim($matches[1], '"\'') ?: null;
    }

    /**
     * Percent-encodes a URL path while preserving its segment separators, so that values
     * taken from the database (aliases containing spaces, `?` or `#`) cannot corrupt the
     * structure of a generated URL.
     */
    public static function urlEncodePath(string $path): string
    {
        return \implode('/', \array_map(\rawurlencode(...), \explode('/', $path)));
    }
}
