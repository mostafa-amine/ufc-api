<?php

namespace App\Support;

class Format
{
    /**
     * A landed/attempted strike (or takedown) tally with a derived accuracy %.
     *
     * @return array{landed:int,attempted:int,pct:int}
     */
    public static function strikes(?int $landed, ?int $attempted): array
    {
        $landed = (int) $landed;
        $attempted = (int) $attempted;

        return [
            'landed' => $landed,
            'attempted' => $attempted,
            'pct' => $attempted > 0 ? (int) round($landed / $attempted * 100) : 0,
        ];
    }

    /** Seconds -> "m:ss" (e.g. 533 -> "8:53"); null passes through. */
    public static function clock(?int $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        return intdiv($seconds, 60).':'.str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
    }
}
