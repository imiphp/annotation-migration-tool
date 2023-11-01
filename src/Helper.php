<?php

declare(strict_types=1);

namespace Imiphp\Tool\AnnotationMigration;

class Helper
{
    /**
     * Determine if a given string starts with a given substring.
     *
     * @param string|iterable<string> $needles
     */
    public static function strStartsWith(string $haystack, string|iterable $needles): bool
    {
        if (!is_iterable($needles))
        {
            $needles = [$needles];
        }

        foreach ($needles as $needle)
        {
            if ('' !== (string) $needle && str_starts_with($haystack, $needle))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * @template T
     *
     * @param array<T> $arr
     *
     * @return T
     */
    public static function arrayValueLast(array $arr): mixed
    {
        if (empty($arr))
        {
            return null;
        }

        return end($arr);
    }
}
