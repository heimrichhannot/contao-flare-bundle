<?php

namespace HeimrichHannot\FlareBundle\Util;

class Arr
{
    public static function flatten(
        array  $input,
        string $separator = '.',
        int    $maxDepth = 10,
        string $prefix = '',
    ): array {
        $result = [];

        $walk =
            static function (
                array  $array,
                string $currentPrefix = '',
                int    $depth = 0,
            ) use (
                &$walk,
                &$result,
                $separator,
                $maxDepth
            ): void {
                foreach ($array as $key => $value)
                {
                    $path = $currentPrefix === ''
                        ? (string) $key
                        : $currentPrefix . $separator . $key;

                    if (is_array($value) && $depth < $maxDepth)
                    {
                        $walk($value, $path, $depth + 1);
                    }
                    else
                    {
                        $result[$path] = $value;
                    }
                }
            };

        $walk($input, $prefix);

        return $result;
    }
}